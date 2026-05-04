import cv2
import numpy as np
from PyQt5.QtCore import Qt, QTimer, pyqtSignal
from PyQt5.QtGui import QFont, QImage, QPixmap
from PyQt5.QtWidgets import (QFrame, QHBoxLayout, QLabel, QPushButton,
                              QVBoxLayout, QWidget)

RESULT_MS  = 4_000    # hiện kết quả 4 giây rồi reset về show_person
TIMEOUT_MS = 600_000  # 10 phút không ai → về idle

STATUS_MAP = {
    "present":     ("#1a7f37", "PRESENT"),
    "late":        ("#9a6700", "LATE"),
    "early_leave": ("#cf222e", "EARLY LEAVE"),
    "offline":     ("#57606a", "SAVED OFFLINE"),
}


def _crop_fill(frame: np.ndarray, target_w: int, target_h: int) -> np.ndarray:
    cam_h, cam_w = frame.shape[:2]
    scale   = max(target_w / cam_w, target_h / cam_h)
    new_w, new_h = int(cam_w * scale), int(cam_h * scale)
    resized = cv2.resize(frame, (new_w, new_h))
    x = (new_w - target_w) // 2
    y = (new_h - target_h) // 2
    return resized[y:y + target_h, x:x + target_w]


class ActiveScreen(QWidget):
    record_requested = pyqtSignal(str)   # "check_in" hoặc "check_out"
    timed_out        = pyqtSignal()

    def __init__(self):
        super().__init__()
        self._current_person = None
        self._result_timer   = QTimer(self)
        self._timeout_timer  = QTimer(self)
        self._result_timer.setSingleShot(True)
        self._timeout_timer.setSingleShot(True)
        self._timeout_timer.timeout.connect(self.timed_out)
        self._setup_ui()

    def _setup_ui(self):
        self.setStyleSheet("background-color: #ffffff;")
        root = QHBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── Left: camera feed ─────────────────────────────────────────────
        self.cam_label = QLabel()
        self.cam_label.setFixedWidth(400)
        self.cam_label.setAlignment(Qt.AlignCenter)
        self.cam_label.setStyleSheet("background: #f6f8fa;")
        root.addWidget(self.cam_label)

        div = QFrame()
        div.setFrameShape(QFrame.VLine)
        div.setFixedWidth(1)
        div.setStyleSheet("color: #d0d7de;")
        root.addWidget(div)

        # ── Right: info panel ─────────────────────────────────────────────
        right = QWidget()
        right.setStyleSheet("background-color: #ffffff;")
        rl = QVBoxLayout(right)
        rl.setContentsMargins(28, 20, 28, 16)
        rl.setSpacing(8)
        root.addWidget(right, 1)

        # Person info
        self.name_label = QLabel("Chờ nhận diện...")
        self.name_label.setFont(QFont("Sans", 24, QFont.Bold))
        self.name_label.setStyleSheet("color: #24292f;")
        self.name_label.setWordWrap(True)
        rl.addWidget(self.name_label)

        self.code_label = QLabel()
        self.code_label.setStyleSheet("color: #57606a; font-size: 14px;")
        rl.addWidget(self.code_label)

        self.conf_label = QLabel()
        self.conf_label.setStyleSheet("color: #57606a; font-size: 13px;")
        rl.addWidget(self.conf_label)

        # Result (ẩn mặc định, hiện sau khi nhấn nút)
        self.result_label = QLabel()
        self.result_label.setFixedHeight(32)
        self.result_label.setAlignment(Qt.AlignCenter)
        self.result_label.setStyleSheet("""
            background: #f6f8fa; border-radius: 6px;
            font-size: 15px; font-weight: bold; color: #24292f;
        """)
        self.result_label.hide()
        rl.addWidget(self.result_label)

        line1 = QFrame()
        line1.setFrameShape(QFrame.HLine)
        line1.setStyleSheet("color: #d0d7de;")
        rl.addWidget(line1)

        # ── Hai nút chính ────────────────────────────────────────────────
        btn_row = QHBoxLayout()
        btn_row.setSpacing(12)

        self.btn_checkin = QPushButton("↑  Vào làm")
        self.btn_checkin.setFixedHeight(64)
        self.btn_checkin.setStyleSheet(self._btn_style("#1a7f37", "white"))
        self.btn_checkin.setEnabled(False)
        self.btn_checkin.clicked.connect(lambda: self.record_requested.emit("check_in"))
        btn_row.addWidget(self.btn_checkin)

        self.btn_checkout = QPushButton("↓  Ra về")
        self.btn_checkout.setFixedHeight(64)
        self.btn_checkout.setStyleSheet(self._btn_style("#0969da", "white"))
        self.btn_checkout.setEnabled(False)
        self.btn_checkout.clicked.connect(lambda: self.record_requested.emit("check_out"))
        btn_row.addWidget(self.btn_checkout)

        rl.addLayout(btn_row)

        line2 = QFrame()
        line2.setFrameShape(QFrame.HLine)
        line2.setStyleSheet("color: #d0d7de;")
        rl.addWidget(line2)

        # ── Recent log ────────────────────────────────────────────────────
        self.log_title = QLabel("Hôm nay — 0 lượt")
        self.log_title.setStyleSheet(
            "color: #57606a; font-size: 12px; font-weight: bold;")
        rl.addWidget(self.log_title)

        self._log_rows = []
        for _ in range(4):
            row = QLabel()
            row.setStyleSheet(
                "color: #24292f; font-size: 12px; "
                "background: #f6f8fa; border-radius: 4px; padding: 2px 6px;")
            row.hide()
            rl.addWidget(row)
            self._log_rows.append(row)

        rl.addStretch()

        # online badge
        self.online_badge = QLabel("● ONLINE")
        self.online_badge.setStyleSheet("color: #1a7f37; font-size: 12px;")
        rl.addWidget(self.online_badge)

    def _btn_style(self, bg: str, fg: str) -> str:
        return f"""
            QPushButton {{
                background: {bg}; color: {fg};
                border-radius: 10px; font-size: 17px; font-weight: bold;
                border: none;
            }}
            QPushButton:pressed {{ opacity: 0.85; }}
            QPushButton:disabled {{
                background: #eaeef2; color: #8c959f;
            }}
        """

    def set_frame(self, frame: np.ndarray):
        h = max(self.height(), 480)
        cropped = _crop_fill(frame, 400, h)
        rgb     = cv2.cvtColor(cropped, cv2.COLOR_BGR2RGB)
        rgb     = np.ascontiguousarray(rgb)
        img     = QImage(rgb.data, 400, h, 400 * 3, QImage.Format_RGB888)
        self.cam_label.setPixmap(QPixmap.fromImage(img))

    def show_waiting(self):
        """Không có ai trong frame."""
        self._current_person = None
        self._timeout_timer.start(TIMEOUT_MS)
        self.name_label.setText("Chờ nhận diện...")
        self.name_label.setStyleSheet("color: #8c959f; font-size: 24px; font-weight: bold;")
        self.code_label.setText("")
        self.conf_label.setText("")
        self.result_label.hide()
        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)

    def show_person(self, data: dict):
        """Face recognized — hiện thông tin và enable nút."""
        self._current_person = data
        self._timeout_timer.start(TIMEOUT_MS)
        self.name_label.setText(data.get("name", f"User {data['user_id']}"))
        self.name_label.setStyleSheet("color: #24292f; font-size: 24px; font-weight: bold;")
        code = data.get("code", "")
        self.code_label.setText(f"{code}  ·  ID {data['user_id']}" if code else f"ID {data['user_id']}")
        self.conf_label.setText(f"Độ chính xác: {data.get('confidence', 0):.0%}")
        self.result_label.hide()
        self.btn_checkin.setEnabled(True)
        self.btn_checkout.setEnabled(True)

    def show_result(self, data: dict, status: str):
        """Hiện kết quả sau khi nhấn nút, rồi tự reset về show_person."""
        rtype  = data.get("record_type", "check_in")
        color, badge = STATUS_MAP.get(status, ("#0969da", status.upper()))
        icon   = "↑" if rtype == "check_in" else "↓"
        action = "VÀO LÀM" if rtype == "check_in" else "RA VỀ"
        self.result_label.setText(f"{icon} {action}  ·  {badge}")
        self.result_label.setStyleSheet(f"""
            background: {color}; color: white;
            border-radius: 6px; font-size: 15px; font-weight: bold;
        """)
        self.result_label.show()
        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)

        # Reset về show_person sau 4 giây
        self._result_timer.timeout.disconnect() if self._result_timer.receivers(
            self._result_timer.timeout) > 0 else None
        self._result_timer.timeout.connect(
            lambda: self.show_person(data) if self._current_person else None)
        self._result_timer.start(RESULT_MS)

    def update_log(self, entries: list):
        count = len(entries)
        self.log_title.setText(f"Hôm nay — {count} lượt")
        recent = list(reversed(entries[-4:]))
        for i, row in enumerate(self._log_rows):
            if i < len(recent):
                e = recent[i]
                icon = "↑" if e["type"] == "check_in" else "↓"
                dot  = {"present": "🟢", "late": "🟡",
                        "early_leave": "🔴", "offline": "⚫"}.get(e["status"], "●")
                row.setText(f"{icon} {e['name']}  {dot}  {e['time']}")
                row.show()
            else:
                row.hide()

    def set_online(self, online: bool):
        if online:
            self.online_badge.setText("● ONLINE")
            self.online_badge.setStyleSheet("color: #1a7f37; font-size: 12px;")
        else:
            self.online_badge.setText("● OFFLINE")
            self.online_badge.setStyleSheet("color: #cf222e; font-size: 12px;")

    def current_person(self):
        return self._current_person
