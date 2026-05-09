import cv2
import numpy as np
from PyQt5.QtCore import Qt, QTimer, pyqtSignal, QPropertyAnimation, QEasingCurve
from PyQt5.QtGui import QFont, QImage, QPixmap, QLinearGradient, QColor, QPainter, QBrush
from PyQt5.QtWidgets import (QFrame, QGraphicsOpacityEffect, QHBoxLayout, QLabel,
                              QPushButton, QVBoxLayout, QWidget)

RESULT_MS  = 4_000
TIMEOUT_MS = 600_000

STATUS_MAP = {
    "present":     ("#3fb950", "PRESENT"),
    "late":        ("#d29922", "LATE"),
    "early_leave": ("#f85149", "EARLY LEAVE"),
    "offline":     ("#8b949e", "SAVED OFFLINE"),
}

ROLE_MAP = {
    "super_admin": "Quản trị hệ thống",
    "admin":       "Quản trị viên",
    "manager":     "Quản lý",
    "employee":    "Nhân viên",
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
        self._setup_animation()

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

        div = QFrame()
        div.setFrameShape(QFrame.VLine)
        div.setFixedWidth(1)
        div.setStyleSheet("background: rgba(255,255,255,0.07); border: none;")
        root.addWidget(div)

        # ── Right panel ───────────────────────────────────────────────────
        right = QWidget()
        right.setStyleSheet("background: transparent;")
        rl = QVBoxLayout(right)
        rl.setContentsMargins(32, 24, 32, 20)
        rl.setSpacing(0)
        root.addWidget(right, 1)

        # ── Top: online badge ─────────────────────────────────────────────
        top_row = QHBoxLayout()
        top_row.addStretch()
        self.online_badge = QLabel("● ONLINE")
        self.online_badge.setStyleSheet("""
            color: #3fb950; font-size: 11px; font-weight: bold; letter-spacing: 2px;
            background: rgba(63,185,80,0.12); border: 1px solid rgba(63,185,80,0.25);
            border-radius: 8px; padding: 3px 10px;
        """)
        top_row.addWidget(self.online_badge)
        rl.addLayout(top_row)

        rl.addSpacing(20)

        # ── Info block (animatable) ───────────────────────────────────────
        self.info_widget = QWidget()
        self.info_widget.setStyleSheet("background: transparent;")
        info_layout = QVBoxLayout(self.info_widget)
        info_layout.setContentsMargins(0, 0, 0, 0)
        info_layout.setSpacing(4)

        self.name_label = QLabel("Chờ nhận diện...")
        self.name_label.setFont(QFont("Arial", 28, QFont.Light))
        self.name_label.setStyleSheet("color: rgba(255,255,255,0.3); letter-spacing: 1px;")
        self.name_label.setWordWrap(True)
        info_layout.addWidget(self.name_label)

        self.role_label = QLabel()
        self.role_label.setStyleSheet(
            "color: rgba(255,255,255,0.45); font-size: 14px; letter-spacing: 1px;")
        info_layout.addWidget(self.role_label)

        self.dept_label = QLabel()
        self.dept_label.setStyleSheet(
            "color: #58a6ff; font-size: 13px; letter-spacing: 1px;")
        info_layout.addWidget(self.dept_label)

        info_layout.addSpacing(8)

        self.code_label = QLabel()
        self.code_label.setStyleSheet(
            "color: rgba(255,255,255,0.2); font-size: 12px; letter-spacing: 2px;")
        info_layout.addWidget(self.code_label)

        self.conf_label = QLabel()
        self.conf_label.setStyleSheet(
            "color: rgba(255,255,255,0.18); font-size: 11px; letter-spacing: 1px;")
        info_layout.addWidget(self.conf_label)

        rl.addWidget(self.info_widget)

        rl.addSpacing(20)

        # ── Divider ───────────────────────────────────────────────────────
        self._add_divider(rl)
        rl.addSpacing(16)

        # ── Today attendance block ────────────────────────────────────────
        today_title = QLabel("ĐIỂM DANH HÔM NAY")
        today_title.setStyleSheet(
            "color: rgba(255,255,255,0.2); font-size: 10px; letter-spacing: 3px;")
        rl.addWidget(today_title)
        rl.addSpacing(10)

        ci_row = QHBoxLayout()
        ci_icon = QLabel("↑")
        ci_icon.setFixedWidth(20)
        ci_icon.setStyleSheet("color: #3fb950; font-size: 16px; font-weight: bold;")
        ci_label = QLabel("VÀO LÀM")
        ci_label.setStyleSheet(
            "color: rgba(255,255,255,0.35); font-size: 12px; letter-spacing: 2px;")
        self.ci_time = QLabel("—")
        self.ci_time.setAlignment(Qt.AlignRight | Qt.AlignVCenter)
        self.ci_time.setStyleSheet(
            "color: rgba(255,255,255,0.7); font-size: 18px; font-weight: 300; letter-spacing: 2px;")
        ci_row.addWidget(ci_icon)
        ci_row.addWidget(ci_label)
        ci_row.addStretch()
        ci_row.addWidget(self.ci_time)
        rl.addLayout(ci_row)

        rl.addSpacing(8)

        co_row = QHBoxLayout()
        co_icon = QLabel("↓")
        co_icon.setFixedWidth(20)
        co_icon.setStyleSheet("color: #58a6ff; font-size: 16px; font-weight: bold;")
        co_label = QLabel("RA VỀ")
        co_label.setStyleSheet(
            "color: rgba(255,255,255,0.35); font-size: 12px; letter-spacing: 2px;")
        self.co_time = QLabel("—")
        self.co_time.setAlignment(Qt.AlignRight | Qt.AlignVCenter)
        self.co_time.setStyleSheet(
            "color: rgba(255,255,255,0.7); font-size: 18px; font-weight: 300; letter-spacing: 2px;")
        co_row.addWidget(co_icon)
        co_row.addWidget(co_label)
        co_row.addStretch()
        co_row.addWidget(self.co_time)
        rl.addLayout(co_row)

        rl.addSpacing(16)

        # Result badge
        self.result_label = QLabel()
        self.result_label.setFixedHeight(34)
        self.result_label.setAlignment(Qt.AlignCenter)
        self.result_label.setStyleSheet("""
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px; font-size: 12px; font-weight: bold;
            color: white; letter-spacing: 2px;
        """)
        self.result_label.hide()
        rl.addWidget(self.result_label)

        rl.addSpacing(16)
        self._add_divider(rl)
        rl.addSpacing(14)

        # ── Action buttons ────────────────────────────────────────────────
        btn_row = QHBoxLayout()
        btn_row.setSpacing(12)

        self.btn_checkin = QPushButton("↑  VÀO LÀM")
        self.btn_checkin.setFixedHeight(54)
        self.btn_checkin.setStyleSheet(self._btn_style("#3fb950"))
        self.btn_checkin.setEnabled(False)
        self.btn_checkin.clicked.connect(lambda: self.record_requested.emit("check_in"))
        btn_row.addWidget(self.btn_checkin)

        self.btn_checkout = QPushButton("↓  RA VỀ")
        self.btn_checkout.setFixedHeight(54)
        self.btn_checkout.setStyleSheet(self._btn_style("#58a6ff"))
        self.btn_checkout.setEnabled(False)
        self.btn_checkout.clicked.connect(lambda: self.record_requested.emit("check_out"))
        btn_row.addWidget(self.btn_checkout)

        rl.addLayout(btn_row)
        rl.addStretch()

        # ── Bottom: settings ──────────────────────────────────────────────
        bottom = QHBoxLayout()
        bottom.addStretch()
        btn_settings = QPushButton("⚙")
        btn_settings.setFixedSize(44, 44)
        btn_settings.setStyleSheet("""
            QPushButton {
                background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.5);
                border-radius: 22px; font-size: 18px;
                border: 1px solid rgba(255,255,255,0.12);
            }
            QPushButton:pressed { background: rgba(255,255,255,0.15); }
        """)
        btn_settings.clicked.connect(self.settings_clicked)
        bottom.addWidget(btn_settings)
        rl.addLayout(bottom)

    def _add_divider(self, layout):
        line = QFrame()
        line.setFrameShape(QFrame.HLine)
        line.setFixedHeight(1)
        line.setStyleSheet("background: rgba(255,255,255,0.07); border: none;")
        layout.addWidget(line)

    def _btn_style(self, color: str) -> str:
        return f"""
            QPushButton {{
                background: rgba(255,255,255,0.04); color: {color};
                border-radius: 10px; font-size: 14px; font-weight: bold;
                border: 1px solid {color}; letter-spacing: 2px;
            }}
            QPushButton:pressed {{ background: rgba(255,255,255,0.10); }}
            QPushButton:disabled {{
                background: rgba(255,255,255,0.02);
                color: rgba(255,255,255,0.12);
                border: 1px solid rgba(255,255,255,0.07);
            }}
        """

    def _setup_animation(self):
        self._opacity_effect = QGraphicsOpacityEffect(self.info_widget)
        self.info_widget.setGraphicsEffect(self._opacity_effect)
        self._fade_anim = QPropertyAnimation(self._opacity_effect, b"opacity")
        self._fade_anim.setDuration(350)
        self._fade_anim.setEasingCurve(QEasingCurve.OutCubic)

    def _fade_in(self):
        self._fade_anim.stop()
        self._fade_anim.setStartValue(0.0)
        self._fade_anim.setEndValue(1.0)
        self._fade_anim.start()

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
            "color: rgba(255,255,255,0.25); font-size: 28px; font-weight: 300;")
        self.role_label.setText("")
        self.dept_label.setText("")
        self.code_label.setText("")
        self.conf_label.setText("")
        self.ci_time.setText("—")
        self.co_time.setText("—")
        self.result_label.hide()
        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)

    def show_person(self, data: dict):
        self._current_person = data
        self._timeout_timer.start(TIMEOUT_MS)

        self.name_label.setText(data.get("name", f"User {data['user_id']}").upper())
        self.name_label.setStyleSheet(
            "color: #ffffff; font-size: 28px; font-weight: 300; letter-spacing: 2px;")

        role = ROLE_MAP.get(data.get("role", ""), data.get("role", ""))
        self.role_label.setText(role.upper() if role else "")

        dept = data.get("department") or ""
        self.dept_label.setText(dept.upper() if dept else "")

        code = data.get("code", "")
        self.code_label.setText(f"#{code}  ·  ID {data['user_id']}" if code else f"ID {data['user_id']}")
        self.conf_label.setText(f"ĐỘ CHÍNH XÁC  {data.get('confidence', 0):.0%}")

        self.ci_time.setText(data.get("check_in_at") or "—")
        self.co_time.setText(data.get("check_out_at") or "—")

        self.result_label.hide()
        self.btn_checkin.setEnabled(True)
        self.btn_checkout.setEnabled(True)
        self._fade_in()

    def show_result(self, data: dict, status: str):
        rtype  = data.get("record_type", "check_in")
        color, badge = STATUS_MAP.get(status, ("#58a6ff", status.upper()))
        icon   = "↑" if rtype == "check_in" else "↓"
        action = "VÀO LÀM" if rtype == "check_in" else "RA VỀ"

        if rtype == "check_in":
            from datetime import datetime
            self.ci_time.setText(datetime.now().strftime("%H:%M"))
            self._current_person = {**data, "check_in_at": datetime.now().strftime("%H:%M")}
        else:
            from datetime import datetime
            self.co_time.setText(datetime.now().strftime("%H:%M"))
            self._current_person = {**data, "check_out_at": datetime.now().strftime("%H:%M")}

        self.result_label.setText(f"{icon}  {action}  ·  {badge}")
        self.result_label.setStyleSheet(f"""
            background: rgba(255,255,255,0.05); border: 1px solid {color};
            border-radius: 8px; font-size: 12px; font-weight: bold;
            color: {color}; letter-spacing: 2px;
        """)
        self.result_label.show()
        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)

        self._result_timer.timeout.disconnect() if self._result_timer.receivers(
            self._result_timer.timeout) > 0 else None
        self._result_timer.timeout.connect(
            lambda: self.show_person(self._current_person) if self._current_person else None)
        self._result_timer.start(RESULT_MS)

    def update_log(self, entries: list):
        pass  # log per-person now shown in the today block

    def set_online(self, online: bool):
        if online:
            self.online_badge.setText("● ONLINE")
            self.online_badge.setStyleSheet("""
                color: #3fb950; font-size: 11px; font-weight: bold; letter-spacing: 2px;
                background: rgba(63,185,80,0.12); border: 1px solid rgba(63,185,80,0.25);
                border-radius: 8px; padding: 3px 10px;
            """)
        else:
            self.online_badge.setText("● OFFLINE")
            self.online_badge.setStyleSheet("""
                color: #f85149; font-size: 11px; font-weight: bold; letter-spacing: 2px;
                background: rgba(248,81,73,0.12); border: 1px solid rgba(248,81,73,0.25);
                border-radius: 8px; padding: 3px 10px;
            """)

    def current_person(self):
        return self._current_person
