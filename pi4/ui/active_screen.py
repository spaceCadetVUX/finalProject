import cv2
import numpy as np
from PyQt5.QtCore import Qt, QTimer, pyqtSignal
from PyQt5.QtGui import QFont, QImage, QPixmap, QLinearGradient, QColor, QPainter, QBrush
from PyQt5.QtWidgets import (QFrame, QHBoxLayout, QLabel, QPushButton,
                              QVBoxLayout, QWidget)

RESULT_MS  = 4_000
TIMEOUT_MS = 600_000

STATUS_MAP = {
    "present":     ("#3fb950", "PRESENT"),
    "late":        ("#d29922", "LATE"),
    "early_leave": ("#f85149", "EARLY LEAVE"),
    "offline":     ("#8b949e", "SAVED OFFLINE"),
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
    record_requested = pyqtSignal(str)
    timed_out        = pyqtSignal()
    settings_clicked = pyqtSignal()

    def __init__(self):
        super().__init__()
        self._current_person = None
        self._result_timer   = QTimer(self)
        self._timeout_timer  = QTimer(self)
        self._result_timer.setSingleShot(True)
        self._timeout_timer.setSingleShot(True)
        self._timeout_timer.timeout.connect(self.timed_out)
        self._setup_ui()

    def paintEvent(self, event):
        painter = QPainter(self)
        painter.setRenderHint(QPainter.Antialiasing)
        grad = QLinearGradient(0, 0, self.width(), self.height())
        grad.setColorAt(0.0, QColor("#0d1117"))
        grad.setColorAt(1.0, QColor("#0a0f1e"))
        painter.fillRect(self.rect(), QBrush(grad))

    def _setup_ui(self):
        self.setStyleSheet("background: transparent; color: white;")
        root = QHBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── Left: camera feed ─────────────────────────────────────────────
        self.cam_label = QLabel()
        self.cam_label.setFixedWidth(400)
        self.cam_label.setAlignment(Qt.AlignCenter)
        self.cam_label.setStyleSheet("background: #080c14;")
        root.addWidget(self.cam_label)

        # Subtle vertical divider
        div = QFrame()
        div.setFrameShape(QFrame.VLine)
        div.setFixedWidth(1)
        div.setStyleSheet("background: rgba(255,255,255,0.08); border: none;")
        root.addWidget(div)

        # ── Right: info panel ─────────────────────────────────────────────
        right = QWidget()
        right.setStyleSheet("background: transparent;")
        rl = QVBoxLayout(right)
        rl.setContentsMargins(32, 28, 32, 20)
        rl.setSpacing(10)
        root.addWidget(right, 1)

        # Person info
        self.name_label = QLabel("Chờ nhận diện...")
        self.name_label.setFont(QFont("Arial", 26, QFont.Light))
        self.name_label.setStyleSheet("color: rgba(255,255,255,0.35); letter-spacing: 1px;")
        self.name_label.setWordWrap(True)
        rl.addWidget(self.name_label)

        self.code_label = QLabel()
        self.code_label.setStyleSheet(
            "color: rgba(255,255,255,0.3); font-size: 13px; letter-spacing: 2px;")
        rl.addWidget(self.code_label)

        self.conf_label = QLabel()
        self.conf_label.setStyleSheet(
            "color: rgba(255,255,255,0.25); font-size: 12px; letter-spacing: 1px;")
        rl.addWidget(self.conf_label)

        rl.addSpacing(4)

        # Result badge
        self.result_label = QLabel()
        self.result_label.setFixedHeight(36)
        self.result_label.setAlignment(Qt.AlignCenter)
        self.result_label.setStyleSheet("""
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            font-size: 13px; font-weight: bold;
            color: white; letter-spacing: 2px;
        """)
        self.result_label.hide()
        rl.addWidget(self.result_label)

        # Divider
        line1 = QFrame()
        line1.setFrameShape(QFrame.HLine)
        line1.setFixedHeight(1)
        line1.setStyleSheet("background: rgba(255,255,255,0.08); border: none;")
        rl.addWidget(line1)

        # ── Buttons ──────────────────────────────────────────────────────
        btn_row = QHBoxLayout()
        btn_row.setSpacing(12)

        self.btn_checkin = QPushButton("↑  VÀO LÀM")
        self.btn_checkin.setFixedHeight(60)
        self.btn_checkin.setStyleSheet(self._btn_style("#238636", "#3fb950"))
        self.btn_checkin.setEnabled(False)
        self.btn_checkin.clicked.connect(lambda: self.record_requested.emit("check_in"))
        btn_row.addWidget(self.btn_checkin)

        self.btn_checkout = QPushButton("↓  RA VỀ")
        self.btn_checkout.setFixedHeight(60)
        self.btn_checkout.setStyleSheet(self._btn_style("#1158a7", "#58a6ff"))
        self.btn_checkout.setEnabled(False)
        self.btn_checkout.clicked.connect(lambda: self.record_requested.emit("check_out"))
        btn_row.addWidget(self.btn_checkout)

        rl.addLayout(btn_row)

        # Divider
        line2 = QFrame()
        line2.setFrameShape(QFrame.HLine)
        line2.setFixedHeight(1)
        line2.setStyleSheet("background: rgba(255,255,255,0.08); border: none;")
        rl.addWidget(line2)

        # ── Log ──────────────────────────────────────────────────────────
        self.log_title = QLabel("HÔM NAY — 0 LƯỢT")
        self.log_title.setStyleSheet(
            "color: rgba(255,255,255,0.25); font-size: 11px; letter-spacing: 3px;")
        rl.addWidget(self.log_title)

        self._log_rows = []
        for _ in range(4):
            row = QLabel()
            row.setStyleSheet("""
                color: rgba(255,255,255,0.55); font-size: 12px;
                background: rgba(255,255,255,0.04);
                border: 1px solid rgba(255,255,255,0.07);
                border-radius: 6px; padding: 3px 10px;
                letter-spacing: 0.5px;
            """)
            row.hide()
            rl.addWidget(row)
            self._log_rows.append(row)

        rl.addStretch()

        # ── Bottom bar ───────────────────────────────────────────────────
        bottom = QHBoxLayout()
        self.online_badge = QLabel("● ONLINE")
        self.online_badge.setStyleSheet("""
            color: #3fb950; font-size: 11px; font-weight: bold;
            letter-spacing: 2px;
            background: rgba(63,185,80,0.12);
            border: 1px solid rgba(63,185,80,0.25);
            border-radius: 8px; padding: 3px 10px;
        """)
        bottom.addWidget(self.online_badge)
        bottom.addStretch()

        btn_settings = QPushButton("⚙")
        btn_settings.setFixedSize(44, 44)
        btn_settings.setStyleSheet("""
            QPushButton {
                background: rgba(255,255,255,0.07);
                color: rgba(255,255,255,0.5);
                border-radius: 22px; font-size: 18px;
                border: 1px solid rgba(255,255,255,0.12);
            }
            QPushButton:pressed { background: rgba(255,255,255,0.15); }
        """)
        btn_settings.clicked.connect(self.settings_clicked)
        bottom.addWidget(btn_settings)
        rl.addLayout(bottom)

    def _btn_style(self, bg: str, border: str) -> str:
        return f"""
            QPushButton {{
                background: rgba(255,255,255,0.04);
                color: {border};
                border-radius: 10px; font-size: 15px; font-weight: bold;
                border: 1px solid {border};
                letter-spacing: 2px;
            }}
            QPushButton:pressed {{
                background: rgba(255,255,255,0.10);
            }}
            QPushButton:disabled {{
                background: rgba(255,255,255,0.03);
                color: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.08);
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
        self._current_person = None
        self._timeout_timer.start(TIMEOUT_MS)
        self.name_label.setText("Chờ nhận diện...")
        self.name_label.setStyleSheet(
            "color: rgba(255,255,255,0.3); font-size: 26px; font-weight: 300; letter-spacing: 1px;")
        self.code_label.setText("")
        self.conf_label.setText("")
        self.result_label.hide()
        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)

    def show_person(self, data: dict):
        self._current_person = data
        self._timeout_timer.start(TIMEOUT_MS)
        self.name_label.setText(data.get("name", f"User {data['user_id']}").upper())
        self.name_label.setStyleSheet(
            "color: #ffffff; font-size: 26px; font-weight: 300; letter-spacing: 2px;")
        code = data.get("code", "")
        self.code_label.setText(
            f"#{code}  ·  ID {data['user_id']}" if code else f"ID {data['user_id']}")
        self.conf_label.setText(f"ĐỘ CHÍNH XÁC  {data.get('confidence', 0):.0%}")
        self.result_label.hide()
        self.btn_checkin.setEnabled(True)
        self.btn_checkout.setEnabled(True)

    def show_result(self, data: dict, status: str):
        rtype  = data.get("record_type", "check_in")
        color, badge = STATUS_MAP.get(status, ("#58a6ff", status.upper()))
        icon   = "↑" if rtype == "check_in" else "↓"
        action = "VÀO LÀM" if rtype == "check_in" else "RA VỀ"
        self.result_label.setText(f"{icon}  {action}  ·  {badge}")
        self.result_label.setStyleSheet(f"""
            background: rgba(255,255,255,0.06);
            border: 1px solid {color};
            border-radius: 8px; font-size: 13px; font-weight: bold;
            color: {color}; letter-spacing: 2px;
        """)
        self.result_label.show()
        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)

        self._result_timer.timeout.disconnect() if self._result_timer.receivers(
            self._result_timer.timeout) > 0 else None
        self._result_timer.timeout.connect(
            lambda: self.show_person(data) if self._current_person else None)
        self._result_timer.start(RESULT_MS)

    def update_log(self, entries: list):
        count = len(entries)
        self.log_title.setText(f"HÔM NAY — {count} LƯỢT")
        recent = list(reversed(entries[-4:]))
        for i, row in enumerate(self._log_rows):
            if i < len(recent):
                e = recent[i]
                icon = "↑" if e["type"] == "check_in" else "↓"
                dot  = {"present": "●", "late": "●",
                        "early_leave": "●", "offline": "●"}.get(e["status"], "●")
                color = {"present": "#3fb950", "late": "#d29922",
                         "early_leave": "#f85149", "offline": "#8b949e"}.get(e["status"], "#fff")
                row.setText(f"{icon} {e['name']}  <span style='color:{color}'>{dot}</span>  {e['time']}")
                row.setTextFormat(Qt.RichText)
                row.show()
            else:
                row.hide()

    def set_online(self, online: bool):
        if online:
            self.online_badge.setText("● ONLINE")
            self.online_badge.setStyleSheet("""
                color: #3fb950; font-size: 11px; font-weight: bold;
                letter-spacing: 2px;
                background: rgba(63,185,80,0.12);
                border: 1px solid rgba(63,185,80,0.25);
                border-radius: 8px; padding: 3px 10px;
            """)
        else:
            self.online_badge.setText("● OFFLINE")
            self.online_badge.setStyleSheet("""
                color: #f85149; font-size: 11px; font-weight: bold;
                letter-spacing: 2px;
                background: rgba(248,81,73,0.12);
                border: 1px solid rgba(248,81,73,0.25);
                border-radius: 8px; padding: 3px 10px;
            """)

    def current_person(self):
        return self._current_person
