import cv2
import numpy as np
from datetime import datetime
from PyQt5.QtCore import Qt, QTimer, pyqtSignal, QPropertyAnimation, QEasingCurve
from PyQt5.QtGui import QFont, QImage, QPixmap, QLinearGradient, QColor, QPainter, QBrush
from PyQt5.QtWidgets import (QFrame, QGraphicsOpacityEffect, QHBoxLayout, QLabel,
                              QPushButton, QVBoxLayout, QWidget)

TIMEOUT_MS = 600_000

STATUS_COLOR = {
    "present":     "#3fb950",
    "late":        "#d29922",
    "early_leave": "#f85149",
    "offline":     "#8b949e",
}

STATUS_TEXT = {
    "present":     "ĐIỂM DANH THÀNH CÔNG",
    "late":        "VÀO TRỄ",
    "early_leave": "VỀ SỚM",
    "offline":     "LƯU OFFLINE",
}

ROLE_MAP = {
    "super_admin": "Quản Trị Hệ Thống",
    "admin":       "Quản Trị Viên",
    "manager":     "Quản Lý",
    "employee":    "Nhân Viên",
}


def _crop_fill(frame: np.ndarray, target_w: int, target_h: int) -> np.ndarray:
    cam_h, cam_w = frame.shape[:2]
    scale        = max(target_w / cam_w, target_h / cam_h)
    new_w, new_h = int(cam_w * scale), int(cam_h * scale)
    resized      = cv2.resize(frame, (new_w, new_h))
    x = (new_w - target_w) // 2
    y = (new_h - target_h) // 2
    return resized[y:y + target_h, x:x + target_w]


# ─────────────────────────────────────────────────────────────────────────────
class ConfirmPopup(QWidget):
    """Overlay xác nhận điểm danh — chỉ tắt khi bấm OK."""
    confirmed = pyqtSignal()

    def __init__(self, parent):
        super().__init__(parent)
        self.setAttribute(Qt.WA_TranslucentBackground)
        self.hide()
        self._build()

    def _build(self):
        root = QVBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setAlignment(Qt.AlignCenter)

        # Card
        card = QWidget()
        card.setStyleSheet("""
            background: #161b22;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
        """)
        card.setFixedWidth(340)
        cl = QVBoxLayout(card)
        cl.setContentsMargins(32, 28, 32, 28)
        cl.setSpacing(0)

        # Icon + status
        self.icon_lbl = QLabel("✓")
        self.icon_lbl.setAlignment(Qt.AlignCenter)
        self.icon_lbl.setFont(QFont("Arial", 36))
        self.icon_lbl.setStyleSheet("color: #3fb950; background: transparent; border: none;")
        cl.addWidget(self.icon_lbl)
        cl.addSpacing(4)

        self.status_lbl = QLabel("ĐIỂM DANH THÀNH CÔNG")
        self.status_lbl.setAlignment(Qt.AlignCenter)
        self.status_lbl.setFont(QFont("Arial", 13, QFont.Bold))
        self.status_lbl.setStyleSheet(
            "color: #3fb950; letter-spacing: 2px; background: transparent; border: none;")
        cl.addWidget(self.status_lbl)

        cl.addSpacing(20)
        cl.addWidget(self._divider())
        cl.addSpacing(16)

        # Name
        self.name_lbl = QLabel()
        self.name_lbl.setAlignment(Qt.AlignCenter)
        self.name_lbl.setFont(QFont("Arial", 22, QFont.Bold))
        self.name_lbl.setWordWrap(True)
        self.name_lbl.setStyleSheet("color: white; letter-spacing: 1px; background: transparent; border: none;")
        cl.addWidget(self.name_lbl)
        cl.addSpacing(6)

        # Code
        self.code_lbl = QLabel()
        self.code_lbl.setAlignment(Qt.AlignCenter)
        self.code_lbl.setStyleSheet(
            "color: rgba(255,255,255,0.4); font-size: 13px; letter-spacing: 2px; background: transparent; border: none;")
        cl.addWidget(self.code_lbl)
        cl.addSpacing(4)

        # Department
        self.dept_lbl = QLabel()
        self.dept_lbl.setAlignment(Qt.AlignCenter)
        self.dept_lbl.setStyleSheet(
            "color: #58a6ff; font-size: 14px; letter-spacing: 1px; background: transparent; border: none;")
        cl.addWidget(self.dept_lbl)

        cl.addSpacing(20)
        cl.addWidget(self._divider())
        cl.addSpacing(16)

        # Action + time
        self.action_lbl = QLabel()
        self.action_lbl.setAlignment(Qt.AlignCenter)
        self.action_lbl.setFont(QFont("Arial", 16))
        self.action_lbl.setStyleSheet(
            "color: rgba(255,255,255,0.8); letter-spacing: 1px; background: transparent; border: none;")
        cl.addWidget(self.action_lbl)

        cl.addSpacing(24)

        # OK button
        btn_ok = QPushButton("OK")
        btn_ok.setFixedHeight(52)
        btn_ok.setFont(QFont("Arial", 15, QFont.Bold))
        btn_ok.setStyleSheet("""
            QPushButton {
                background: #238636; color: white;
                border-radius: 10px; border: none; letter-spacing: 3px;
            }
            QPushButton:pressed { background: #2ea043; }
        """)
        btn_ok.clicked.connect(self._on_ok)
        cl.addWidget(btn_ok)

        root.addWidget(card)

    def _divider(self):
        line = QFrame()
        line.setFrameShape(QFrame.HLine)
        line.setFixedHeight(1)
        line.setStyleSheet("background: rgba(255,255,255,0.08); border: none;")
        return line

    def paintEvent(self, event):
        painter = QPainter(self)
        painter.fillRect(self.rect(), QColor(0, 0, 0, 160))

    def show_confirm(self, data: dict, rtype: str, status: str, time_str: str):
        color  = STATUS_COLOR.get(status, "#58a6ff")
        text   = STATUS_TEXT.get(status, "HOÀN TẤT")
        icon   = "✓" if status != "offline" else "💾"
        action = "↑  VÀO LÀM" if rtype == "check_in" else "↓  RA VỀ"

        self.icon_lbl.setText(icon)
        self.icon_lbl.setStyleSheet(
            f"color: {color}; font-size: 36px; background: transparent; border: none;")
        self.status_lbl.setText(text)
        self.status_lbl.setStyleSheet(
            f"color: {color}; font-size: 13px; font-weight: bold; "
            f"letter-spacing: 2px; background: transparent; border: none;")

        name = data.get("name", f"User {data.get('user_id', '')}").upper()
        self.name_lbl.setText(name)

        code = data.get("code", "")
        self.code_lbl.setText(f"#{code}" if code else f"ID {data.get('user_id', '')}")

        dept = data.get("department") or ""
        self.dept_lbl.setText(dept.upper() if dept else "")
        self.dept_lbl.setVisible(bool(dept))

        self.action_lbl.setText(f"{action}   lúc   {time_str}")
        self.show()
        self.raise_()

    def _on_ok(self):
        self.hide()
        self.confirmed.emit()


# ─────────────────────────────────────────────────────────────────────────────
class ActiveScreen(QWidget):
    record_requested = pyqtSignal(str)
    timed_out        = pyqtSignal()
    settings_clicked = pyqtSignal()

    def __init__(self):
        super().__init__()
        self._current_person = None
        self._timeout_timer  = QTimer(self)
        self._timeout_timer.setSingleShot(True)
        self._timeout_timer.timeout.connect(self.timed_out)
        self._setup_ui()
        self._setup_animations()

        self.popup = ConfirmPopup(self)
        self.popup.confirmed.connect(self._on_popup_confirmed)

    def paintEvent(self, event):
        painter = QPainter(self)
        grad = QLinearGradient(0, 0, self.width(), self.height())
        grad.setColorAt(0.0, QColor("#0d1117"))
        grad.setColorAt(1.0, QColor("#0a0f1e"))
        painter.fillRect(self.rect(), QBrush(grad))

    def resizeEvent(self, event):
        self.popup.setGeometry(400, 0, self.width() - 400, self.height())
        super().resizeEvent(event)

    def _setup_ui(self):
        self.setStyleSheet("background: transparent; color: white;")
        root = QHBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── Left: camera ──────────────────────────────────────────────────
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
        rl.setContentsMargins(32, 20, 32, 16)
        rl.setSpacing(0)
        root.addWidget(right, 1)

        # Online badge
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
        rl.addSpacing(16)

        # Employee info
        self.info_widget = QWidget()
        self.info_widget.setStyleSheet("background: transparent;")
        il = QVBoxLayout(self.info_widget)
        il.setContentsMargins(0, 0, 0, 0)
        il.setSpacing(6)

        self.name_label = QLabel("Chờ nhận diện...")
        self.name_label.setFont(QFont("Arial", 34, QFont.Bold))
        self.name_label.setStyleSheet("color: rgba(255,255,255,0.2); letter-spacing: 1px;")
        self.name_label.setWordWrap(True)
        il.addWidget(self.name_label)

        self.role_label = QLabel()
        self.role_label.setFont(QFont("Arial", 15))
        self.role_label.setStyleSheet("color: rgba(255,255,255,0.55); letter-spacing: 1px;")
        il.addWidget(self.role_label)

        self.dept_label = QLabel()
        self.dept_label.setFont(QFont("Arial", 13))
        self.dept_label.setStyleSheet("color: #58a6ff; letter-spacing: 1px;")
        il.addWidget(self.dept_label)

        il.addSpacing(6)
        detail_row = QHBoxLayout()
        detail_row.setSpacing(16)
        self.code_label = QLabel()
        self.code_label.setStyleSheet(
            "color: rgba(255,255,255,0.25); font-size: 12px; letter-spacing: 2px;")
        self.conf_label = QLabel()
        self.conf_label.setStyleSheet(
            "color: rgba(255,255,255,0.2); font-size: 12px; letter-spacing: 1px;")
        detail_row.addWidget(self.code_label)
        detail_row.addWidget(self.conf_label)
        detail_row.addStretch()
        il.addLayout(detail_row)

        rl.addWidget(self.info_widget)
        rl.addSpacing(18)
        self._add_divider(rl)
        rl.addSpacing(14)

        # Điểm danh hôm nay
        today_lbl = QLabel("ĐIỂM DANH HÔM NAY")
        today_lbl.setStyleSheet(
            "color: rgba(255,255,255,0.2); font-size: 10px; letter-spacing: 3px;")
        rl.addWidget(today_lbl)
        rl.addSpacing(12)

        ci_row = QHBoxLayout()
        ci_row.addWidget(self._icon_lbl("↑", "#3fb950"))
        ci_lbl = QLabel("VÀO LÀM")
        ci_lbl.setStyleSheet(
            "color: rgba(255,255,255,0.35); font-size: 12px; letter-spacing: 2px;")
        ci_row.addWidget(ci_lbl)
        ci_row.addStretch()
        self.ci_time = QLabel("—")
        self.ci_time.setStyleSheet(
            "color: white; font-size: 22px; font-weight: 300; letter-spacing: 3px;")
        ci_row.addWidget(self.ci_time)
        rl.addLayout(ci_row)
        rl.addSpacing(8)

        co_row = QHBoxLayout()
        co_row.addWidget(self._icon_lbl("↓", "#58a6ff"))
        co_lbl = QLabel("RA VỀ")
        co_lbl.setStyleSheet(
            "color: rgba(255,255,255,0.35); font-size: 12px; letter-spacing: 2px;")
        co_row.addWidget(co_lbl)
        co_row.addStretch()
        self.co_time = QLabel("—")
        self.co_time.setStyleSheet(
            "color: white; font-size: 22px; font-weight: 300; letter-spacing: 3px;")
        co_row.addWidget(self.co_time)
        rl.addLayout(co_row)

        rl.addSpacing(18)
        self._add_divider(rl)
        rl.addSpacing(14)

        # Buttons
        btn_row = QHBoxLayout()
        btn_row.setSpacing(12)

        self.btn_checkin = QPushButton("↑   VÀO LÀM")
        self.btn_checkin.setFixedHeight(58)
        self.btn_checkin.setFont(QFont("Arial", 14, QFont.Bold))
        self.btn_checkin.setStyleSheet(self._btn_style("#3fb950"))
        self.btn_checkin.setEnabled(False)
        self.btn_checkin.clicked.connect(lambda: self.record_requested.emit("check_in"))
        btn_row.addWidget(self.btn_checkin)

        self.btn_checkout = QPushButton("↓   RA VỀ")
        self.btn_checkout.setFixedHeight(58)
        self.btn_checkout.setFont(QFont("Arial", 14, QFont.Bold))
        self.btn_checkout.setStyleSheet(self._btn_style("#58a6ff"))
        self.btn_checkout.setEnabled(False)
        self.btn_checkout.clicked.connect(lambda: self.record_requested.emit("check_out"))
        btn_row.addWidget(self.btn_checkout)

        rl.addLayout(btn_row)
        rl.addStretch()

        # Settings
        bottom = QHBoxLayout()
        bottom.addStretch()
        btn_cfg = QPushButton("⚙")
        btn_cfg.setFixedSize(44, 44)
        btn_cfg.setStyleSheet("""
            QPushButton {
                background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.45);
                border-radius: 22px; font-size: 18px;
                border: 1px solid rgba(255,255,255,0.12);
            }
            QPushButton:pressed { background: rgba(255,255,255,0.15); }
        """)
        btn_cfg.clicked.connect(self.settings_clicked)
        bottom.addWidget(btn_cfg)
        rl.addLayout(bottom)

    def _icon_lbl(self, text: str, color: str) -> QLabel:
        lbl = QLabel(text)
        lbl.setFixedWidth(22)
        lbl.setStyleSheet(f"color: {color}; font-size: 16px; font-weight: bold;")
        return lbl

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
                border-radius: 10px; border: 1.5px solid {color};
                letter-spacing: 2px;
            }}
            QPushButton:pressed {{ background: rgba(255,255,255,0.12); }}
            QPushButton:disabled {{
                background: rgba(255,255,255,0.02);
                color: rgba(255,255,255,0.12);
                border: 1px solid rgba(255,255,255,0.07);
            }}
        """

    def _setup_animations(self):
        self._opacity = QGraphicsOpacityEffect(self.info_widget)
        self.info_widget.setGraphicsEffect(self._opacity)
        self._anim = QPropertyAnimation(self._opacity, b"opacity")
        self._anim.setDuration(400)
        self._anim.setEasingCurve(QEasingCurve.OutCubic)

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
        self.name_label.setFont(QFont("Arial", 34, QFont.Bold))
        self.name_label.setStyleSheet("color: rgba(255,255,255,0.2);")
        self.role_label.setText("")
        self.dept_label.setText("")
        self.code_label.setText("")
        self.conf_label.setText("")
        self.ci_time.setText("—")
        self.co_time.setText("—")
        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)

    def show_person(self, data: dict):
        self._current_person = data
        self._timeout_timer.start(TIMEOUT_MS)

        self.name_label.setText(data.get("name", f"User {data['user_id']}").upper())
        self.name_label.setFont(QFont("Arial", 34, QFont.Bold))
        self.name_label.setStyleSheet("color: #ffffff; letter-spacing: 1px;")

        role = ROLE_MAP.get(data.get("role", ""), data.get("role", ""))
        self.role_label.setText(role)

        dept = data.get("department") or ""
        self.dept_label.setText(dept.upper() if dept else "")

        code = data.get("code", "")
        self.code_label.setText(f"#{code}" if code else f"ID {data['user_id']}")
        self.conf_label.setText(f"ĐỘ CHÍNH XÁC  {data.get('confidence', 0):.0%}")

        self.ci_time.setText(data.get("check_in_at") or "—")
        self.co_time.setText(data.get("check_out_at") or "—")

        self.btn_checkin.setEnabled(True)
        self.btn_checkout.setEnabled(True)

        self._anim.stop()
        self._anim.setStartValue(0.0)
        self._anim.setEndValue(1.0)
        self._anim.start()

    def show_result(self, data: dict, status: str):
        rtype = data.get("record_type", "check_in")
        t     = datetime.now().strftime("%H:%M")

        if rtype == "check_in":
            self.ci_time.setText(t)
            self._current_person = {**data, "check_in_at": t}
        else:
            self.co_time.setText(t)
            self._current_person = {**data, "check_out_at": t}

        self.btn_checkin.setEnabled(False)
        self.btn_checkout.setEnabled(False)
        self.popup.show_confirm(data, rtype, status, t)

    def show_already_recorded(self, rtype: str):
        action = "VÀO LÀM" if rtype == "check_in" else "RA VỀ"
        # Hiện popup thông báo đã điểm danh rồi (không phải xác nhận)
        if self._current_person:
            t = (self._current_person.get("check_in_at") if rtype == "check_in"
                 else self._current_person.get("check_out_at")) or "—"
            data = self._current_person
            # dùng popup với status đặc biệt
            self.popup.icon_lbl.setText("ℹ")
            self.popup.icon_lbl.setStyleSheet(
                "color: #8b949e; font-size: 36px; background: transparent; border: none;")
            self.popup.status_lbl.setText(f"ĐÃ ĐIỂM DANH {action} RỒI")
            self.popup.status_lbl.setStyleSheet(
                "color: #8b949e; font-size: 13px; font-weight: bold; "
                "letter-spacing: 2px; background: transparent; border: none;")
            self.popup.name_lbl.setText(data.get("name", "").upper())
            code = data.get("code", "")
            self.popup.code_lbl.setText(f"#{code}" if code else "")
            dept = data.get("department") or ""
            self.popup.dept_lbl.setText(dept.upper() if dept else "")
            self.popup.dept_lbl.setVisible(bool(dept))
            self.popup.action_lbl.setText(f"{'↑' if rtype == 'check_in' else '↓'}  {action}   lúc   {t}")
            self.popup.show()
            self.popup.raise_()

    def _on_popup_confirmed(self):
        self.btn_checkin.setEnabled(True)
        self.btn_checkout.setEnabled(True)
        if self._current_person:
            self.show_person(self._current_person)

    def update_log(self, entries: list):
        pass

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
