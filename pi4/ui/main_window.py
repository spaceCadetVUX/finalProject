import base64
import queue
import threading
import time
from datetime import datetime

import cv2
import numpy as np
from PyQt5.QtCore import QThread, pyqtSignal
from PyQt5.QtWidgets import QMainWindow, QStackedWidget

import api_client
import local_storage
from camera import Camera
from config import COOLDOWN_SECONDS, MIN_CONFIDENCE, PING_INTERVAL_SECONDS, PROCESS_EVERY_N
from face_recognizer import FaceRecognizer
from sync_manager import refresh_encodings, sync_pending

from ui.active_screen import ActiveScreen
from ui.idle_screen import IdleScreen
from ui.settings_screen import SettingsScreen

FRAME_INTERVAL = 1 / 20  # cap 20 FPS cho camera feed


class CameraThread(QThread):
    new_frame     = pyqtSignal(object)  # np.ndarray BGR (đã vẽ box)
    face_detected = pyqtSignal(object)  # dict

    def __init__(self, recognizer: FaceRecognizer):
        super().__init__()
        self.recognizer  = recognizer
        self._running    = True
        self._mode       = "idle"
        self._cooldown   = {}
        self._last_faces = []           # cập nhật bởi recognition worker
        self._faces_lock = threading.Lock()
        self._rec_queue  = queue.Queue(maxsize=1)

    def set_mode(self, mode: str):
        self._mode = mode

    def _record_type(self, user_id: int) -> str | None:
        now = time.time()
        if now - self._cooldown.get(user_id, 0) < COOLDOWN_SECONDS:
            return None
        rtype = "check_in" if datetime.now().hour < 12 else "check_out"
        self._cooldown[user_id] = now
        return rtype

    def _recognition_worker(self):
        """Chạy recognition trong Python thread riêng, không block camera feed."""
        while self._running:
            try:
                frame_rgb = self._rec_queue.get(timeout=0.2)
            except queue.Empty:
                continue

            detections = self.recognizer.recognize_all(
                frame_rgb, tolerance=1.0 - MIN_CONFIDENCE)

            faces_info = [{"location":   d["location"],
                           "recognized": d["recognized"],
                           "name":       d.get("name", ""),
                           "confidence": d.get("confidence", 0)}
                          for d in detections]

            with self._faces_lock:
                self._last_faces = faces_info

            for det in detections:
                if not det["recognized"]:
                    continue
                rtype = self._record_type(det["user_id"])
                if rtype is None:
                    continue
                self.face_detected.emit({
                    "user_id":     det["user_id"],
                    "name":        det["name"],
                    "code":        det["code"],
                    "confidence":  det["confidence"],
                    "record_type": rtype,
                    "recorded_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    "image_b64":   None,
                    "location":    det["location"],
                })

    def run(self):
        worker = threading.Thread(target=self._recognition_worker, daemon=True)
        worker.start()

        cam = Camera()
        frame_count = 0

        while self._running:
            t0 = time.time()
            ok, frame = cam.read_frame()
            if not ok:
                time.sleep(0.1)
                continue

            # Vẽ box từ kết quả recognition gần nhất (không block)
            with self._faces_lock:
                faces = list(self._last_faces)

            annotated = frame.copy()
            for face in faces:
                t, r, b, l = face["location"]
                color = (0, 180, 0) if face["recognized"] else (0, 0, 210)
                cv2.rectangle(annotated, (l, t), (r, b), color, 2)
                label = (f"{face['name']} {face['confidence']:.0%}"
                         if face["recognized"] else "Unknown")
                cv2.putText(annotated, label, (l, max(t - 8, 0)),
                            cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 1)

            self.new_frame.emit(annotated)
            frame_count += 1

            # Submit frame cho recognition worker (drop nếu đang bận)
            skip = PROCESS_EVERY_N if self._mode == "active" else PROCESS_EVERY_N * 4
            if frame_count % skip == 0:
                rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
                try:
                    self._rec_queue.put_nowait(rgb)
                except queue.Full:
                    pass  # worker vẫn đang xử lý frame trước, bỏ qua

            elapsed = time.time() - t0
            sleep = FRAME_INTERVAL - elapsed
            if sleep > 0:
                time.sleep(sleep)

        cam.release()

    def stop(self):
        self._running = False
        self.wait()


class SyncThread(QThread):
    online_status = pyqtSignal(bool)

    def __init__(self, recognizer: FaceRecognizer):
        super().__init__()
        self.recognizer = recognizer
        self._running   = True
        self._last_sync = None

    def run(self):
        while self._running:
            online = api_client.ping()
            self.online_status.emit(online)
            if online:
                sync_pending(self.recognizer)
                self._last_sync = refresh_encodings(self.recognizer, self._last_sync)
            for _ in range(PING_INTERVAL_SECONDS * 10):
                if not self._running:
                    break
                time.sleep(0.1)

    def stop(self):
        self._running = False
        self.wait()


class MainWindow(QMainWindow):
    def __init__(self):
        super().__init__()
        self.setWindowTitle("Attendance")

        local_storage.init_db()
        self.recognizer = FaceRecognizer()
        try:
            self.recognizer.load_encodings(api_client.fetch_encodings())
        except Exception:
            pass

        self.stack    = QStackedWidget()
        self.idle     = IdleScreen()
        self.active   = ActiveScreen()
        self.settings = SettingsScreen()

        self.setCentralWidget(self.stack)
        self.stack.addWidget(self.idle)      # 0
        self.stack.addWidget(self.active)    # 1
        self.stack.addWidget(self.settings)  # 2

        self.idle.settings_clicked.connect(lambda: self.stack.setCurrentIndex(2))
        self.idle.screen_tapped.connect(self._on_tap_idle)
        self.active.next_clicked.connect(self._on_next_person)
        self.active.timed_out.connect(self._show_idle)
        self.settings.back_clicked.connect(self._show_idle)

        self.cam_thread = CameraThread(self.recognizer)
        self.cam_thread.new_frame.connect(self.idle.set_frame)
        self.cam_thread.new_frame.connect(self.active.set_frame)
        self.cam_thread.face_detected.connect(self._on_face_detected)
        self.cam_thread.start()

        self.sync_thread = SyncThread(self.recognizer)
        self.sync_thread.online_status.connect(self.idle.set_online)
        self.sync_thread.online_status.connect(self.active.set_online)
        self.sync_thread.start()

    def _show_idle(self):
        self.cam_thread.set_mode("idle")
        self.stack.setCurrentIndex(0)

    def _on_tap_idle(self):
        self.cam_thread.set_mode("active")
        self.active.show_waiting()
        self.stack.setCurrentIndex(1)

    def _on_next_person(self):
        self.active.show_waiting()

    def _on_face_detected(self, data: dict):
        if self.stack.currentIndex() != 1:
            self.cam_thread.set_mode("active")
            self.stack.setCurrentIndex(1)

        result = api_client.post_attendance(
            data["user_id"], data["record_type"], data["confidence"],
            data["image_b64"], data["recorded_at"],
        )
        status = result.get("status", "present") if result else "offline"
        if not result:
            local_storage.save_record(
                data["user_id"], data["record_type"], data["confidence"],
                data["image_b64"], data["recorded_at"],
            )

        self.active.show_result(data, status)

    def closeEvent(self, event):
        self.cam_thread.stop()
        self.sync_thread.stop()
        event.accept()
