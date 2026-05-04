import cv2
import numpy as np
from PyQt5.QtCore import Qt, QTimer, pyqtSignal
from PyQt5.QtGui import QFont, QImage, QPixmap
from PyQt5.QtWidgets import (QFrame, QHBoxLayout, QLabel, QPushButton,
                              QVBoxLayout, QWidget)

RESET_MS    = 30_000   # 30 giây reset về chờ
TIMEOUT_MS  = 600_000  # 10 phút quay về idle

STATUS_MAP = {
    "present":     ("#1f6feb", "PRESENT"),
    "late":        ("#9e6a03", "LATE"),
    "early_leave": ("#da3633", "EARLY LEAVE"),
    "offline":     ("#484f58", "SAVED OFFLINE"),
}


class ActiveScreen(QWidget):
    next_clicked = pyqtSignal()
    timed_out    = pyqtSignal()

    def __init__(self):
        super().__init__()
        self._reset_timer   = QTimer(self)
        self._timeout_timer = QTimer(self)
        self._reset_timer.setSingleShot(True)
        self._timeout_timer.setSingleShot(True)
        self._reset_timer.timeout.connect(self.show_waiting)
        self._timeout_timer.timeout.connect(self.timed_out)
        self._setup_ui()

    def _setup_ui(self):
        self.setStyleSheet("background-color: #0d1117;")
        root = QHBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── Left: camera feed ─────────────────────────────────────────────
        self.cam_label = QLabel()
        self.cam_label.setFixedWidth(400)
        self.cam_label.setAlignment(Qt.AlignCenter)
        self.cam_label.setStyleSheet("background: #000;")
        root.addWidget(self.cam_label)

        # ── Right: info panel ─────────────────────────────────────────────
        right = QWidget()
        right.setStyleSheet("background-color: #161b22;")
        rl = QVBoxLayout(right)
        rl.setContentsMargins(36, 36, 36, 36)
        rl.setSpacing(10)
        root.addWidget(right, 1)

        self.type_label = QLabel("● Chờ nhận diện")
        self.type_label.setStyleSheet(
            "color: #58a6ff; font-size: 26px; font-weight: bold;")
        rl.addWidget(self.type_label)

        self.status_badge = QLabel()
        self.status_badge.setFixedHeight(34)
        self.status_badge.setAlignment(Qt.AlignCenter)
        self.status_badge.hide()
        rl.addWidget(self.status_badge)

        line = QFrame()
        line.setFrameShape(QFrame.HLine)
        line.setStyleSheet("color: #30363d;")
        rl.addWidget(line)

        self.name_label = QLabel("—")
        self.name_label.setFont(QFont("Sans", 30, QFont.Bold))
        self.name_label.setStyleSheet("color: #e6edf3;")
        self.name_label.setWordWrap(True)
        rl.addWidget(self.name_label)

        self.code_label = QLabel()
        self.code_label.setStyleSheet("color: #8b949e; font-size: 17px;")
        rl.addWidget(self.code_label)

        self.time_label = QLabel()
        self.time_label.setStyleSheet("color: #58a6ff; font-size: 17px;")
        rl.addWidget(self.time_label)

        rl.addStretch()

        self.online_badge = QLabel("● ONLINE")
        self.online_badge.setStyleSheet("color: #3fb950; font-size: 13px;")
        rl.addWidget(self.online_badge)

        btn = QPushButton("Người tiếp theo →")
        btn.setFixedHeight(58)
        btn.setStyleSheet("""
            QPushButton {
                background: #21262d; color: #e6edf3;
                border-radius: 8px; font-size: 17px;
                border: 1px solid #30363d;
            }
            QPushButton:pressed { background: #30363d; }
        """)
        btn.clicked.connect(self.next_clicked)
        rl.addWidget(btn)

    def set_frame(self, frame: np.ndarray):
        h = max(self.height(), 480)
        resized = cv2.resize(frame, (400, h))
        rgb     = cv2.cvtColor(resized, cv2.COLOR_BGR2RGB)
        rgb     = np.ascontiguousarray(rgb)
        img     = QImage(rgb.data, 400, h, 400 * 3, QImage.Format_RGB888)
        self.cam_label.setPixmap(QPixmap.fromImage(img))

    def show_waiting(self):
        self._reset_timer.stop()
        self._timeout_timer.start(TIMEOUT_MS)
        self.type_label.setText("● Chờ nhận diện")
        self.type_label.setStyleSheet(
            "color: #58a6ff; font-size: 26px; font-weight: bold;")
        self.status_badge.hide()
        self.name_label.setText("—")
        self.code_label.setText("")
        self.time_label.setText("")

    def show_result(self, data: dict, status: str):
        self._reset_timer.start(RESET_MS)
        self._timeout_timer.stop()

        rtype = data.get("record_type", "check_in")
        if rtype == "check_in":
            self.type_label.setText("✓ CHECK IN")
            self.type_label.setStyleSheet(
                "color: #3fb950; font-size: 26px; font-weight: bold;")
        else:
            self.type_label.setText("✓ CHECK OUT")
            self.type_label.setStyleSheet(
                "color: #58a6ff; font-size: 26px; font-weight: bold;")

        color, text = STATUS_MAP.get(status, ("#1f6feb", status.upper()))
        self.status_badge.setText(text)
        self.status_badge.setStyleSheet(f"""
            background: {color}; color: white;
            border-radius: 6px; font-size: 15px; font-weight: bold;
        """)
        self.status_badge.show()

        self.name_label.setText(data.get("name", f"User {data['user_id']}"))
        code = data.get("code", "")
        self.code_label.setText(
            f"{code}  ·  ID {data['user_id']}" if code else f"ID {data['user_id']}")
        self.time_label.setText(data.get("recorded_at", ""))

    def set_online(self, online: bool):
        if online:
            self.online_badge.setText("● ONLINE")
            self.online_badge.setStyleSheet("color: #3fb950; font-size: 13px;")
        else:
            self.online_badge.setText("● OFFLINE")
            self.online_badge.setStyleSheet("color: #f85149; font-size: 13px;")
