"""
Hệ thống chấm công nhận diện khuôn mặt — Raspberry Pi 4
Chạy: python main.py
"""

import base64
import time
from datetime import datetime

import cv2
import numpy as np

import api_client
import local_storage
from camera import Camera
from config import (COOLDOWN_SECONDS, MIN_CONFIDENCE,
                    PING_INTERVAL_SECONDS, PROCESS_EVERY_N)
from face_recognizer import FaceRecognizer
from sync_manager import refresh_encodings, sync_pending


def encode_image(frame: np.ndarray) -> str:
    """Nén frame thành JPEG và trả về base64 string."""
    _, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 70])
    return base64.b64encode(buf).decode()


def determine_type(user_id: int, cooldown_map: dict) -> str | None:
    """
    Xác định đây là check_in hay check_out dựa theo thời điểm trong ngày.
    Trả về None nếu vẫn trong cooldown.
    """
    now = time.time()
    last_time, last_type = cooldown_map.get(user_id, (0, None))

    if now - last_time < COOLDOWN_SECONDS:
        return None  # còn trong cooldown, bỏ qua

    hour = datetime.now().hour
    record_type = "check_in" if hour < 12 else "check_out"
    cooldown_map[user_id] = (now, record_type)
    return record_type


def main():
    local_storage.init_db()

    recognizer   = FaceRecognizer()
    camera       = Camera()
    cooldown_map = {}       # {user_id: (timestamp, type)}
    last_ping_ts = 0
    last_sync_ts = None
    frame_count  = 0
    online       = False

    print("[Main] Đang tải encoding từ server...")
    try:
        records = api_client.fetch_encodings()
        recognizer.load_encodings(records)
        last_sync_ts = int(time.time())
        online = True
    except Exception as e:
        print(f"[Main] Không kết nối được server khi khởi động: {e}")

    print("[Main] Bắt đầu vòng lặp nhận diện. Nhấn 'q' để thoát.")

    while True:
        ok, frame = camera.read_frame()
        if not ok:
            print("[Main] Không đọc được frame, thử lại...")
            time.sleep(0.5)
            continue

        frame_count += 1

        # ── Heartbeat & sync định kỳ ─────────────────────────────────────
        now = time.time()
        if now - last_ping_ts > PING_INTERVAL_SECONDS:
            online = api_client.ping()
            last_ping_ts = now
            if online:
                sync_pending(recognizer)
                last_sync_ts = refresh_encodings(recognizer, last_sync_ts)

        # ── Xử lý nhận diện mỗi N frame ──────────────────────────────────
        if frame_count % PROCESS_EVERY_N != 0:
            cv2.imshow("Attendance", frame)
            if cv2.waitKey(1) & 0xFF == ord('q'):
                break
            continue

        rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        detections = recognizer.recognize(rgb, tolerance=1.0 - MIN_CONFIDENCE)

        for det in detections:
            user_id    = det["user_id"]
            confidence = det["confidence"]
            record_type = determine_type(user_id, cooldown_map)

            if record_type is None:
                continue  # cooldown

            recorded_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            top, right, bottom, left = det["location"]

            # Cắt ảnh khuôn mặt để gửi kèm
            face_img   = frame[top:bottom, left:right]
            image_b64  = encode_image(face_img) if face_img.size > 0 else None

            # Hiển thị kết quả lên màn hình
            label = f"ID:{user_id} {confidence:.0%} [{record_type}]"
            cv2.rectangle(frame, (left, top), (right, bottom), (0, 255, 0), 2)
            cv2.putText(frame, label, (left, top - 8),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 1)

            if online:
                ok = api_client.post_attendance(user_id, record_type, confidence,
                                                image_b64, recorded_at)
                if not ok:
                    local_storage.save_record(user_id, record_type, confidence,
                                              image_b64, recorded_at)
                    online = False
            else:
                local_storage.save_record(user_id, record_type, confidence,
                                          image_b64, recorded_at)

        cv2.imshow("Attendance", frame)
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    camera.release()
    cv2.destroyAllWindows()


if __name__ == "__main__":
    main()
