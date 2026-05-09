from datetime import datetime

import cv2
import numpy as np
from PyQt5.QtCore import Qt, QTimer, pyqtSignal
from PyQt5.QtGui import QFont, QImage, QPixmap, QLinearGradient, QColor, QPainter, QBrush
from PyQt5.QtWidgets import (QHBoxLayout, QLabel, QPushButton, QVBoxLayout,
                              QWidget, QFrame)


class IdleScreen(QWidget):
    settings_clicked = pyqtSignal()
    screen_tapped    = pyqtSignal()

    def __init__(self):
        super().__init__()
        self._setup_ui()
        timer = QTimer(self)
        timer.timeout.connect(self._tick)
        timer.start(1000)
        self._tick()

    def paintEvent(self, event):
        painter = QPainter(self)
        painter.setRenderHint(QPainter.Antialiasing)
        grad = QLinearGradient(0, 0, self.width(), self.height())
        grad.setColorAt(0.0, QColor("#0d1117"))
        grad.setColorAt(0.5, QColor("#111827"))
        grad.setColorAt(1.0, QColor("#0a0f1e"))
        painter.fillRect(self.rect(), QBrush(grad))

    def _setup_ui(self):
        self.setStyleSheet("background: transparent; color: white;")
        root = QVBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── Top bar ───────────────────────────────────────────────────────
        top = QHBoxLayout()
        top.setContentsMargins(32, 24, 32, 0)

        self.online_badge = QLabel("● ONLINE")
        self.online_badge.setStyleSheet("""
            color: #3fb950; font-size: 12px; font-weight: bold;
            letter-spacing: 2px;
            background: rgba(63,185,80,0.12);
            border: 1px solid rgba(63,185,80,0.3);
            border-radius: 10px; padding: 4px 12px;
        """)
        top.addStretch()
        top.addWidget(self.online_badge)
        root.addLayout(top)

        root.addStretch(3)

        # ── Clock ─────────────────────────────────────────────────────────
        self.clock = QLabel("00:00:00")
        self.clock.setAlignment(Qt.AlignCenter)
        self.clock.setFont(QFont("Arial", 80, QFont.Thin))
        self.clock.setStyleSheet("""
            color: #ffffff;
            letter-spacing: 6px;
        """)
        root.addWidget(self.clock)

        root.addSpacing(8)

        # ── Divider line under clock ───────────────────────────────────────
        div = QFrame()
        div.setFixedHeight(1)
        div.setStyleSheet("background: rgba(255,255,255,0.08);")
        div.setContentsMargins(80, 0, 80, 0)
        root.addWidget(div)

        root.addSpacing(16)

        # ── Date ──────────────────────────────────────────────────────────
        self.date = QLabel()
        self.date.setAlignment(Qt.AlignCenter)
        self.date.setStyleSheet("""
            color: rgba(255,255,255,0.45);
            font-size: 16px;
            letter-spacing: 4px;
        """)
        root.addWidget(self.date)

        root.addSpacing(40)

        # ── Hint pill ─────────────────────────────────────────────────────
        hint_wrap = QHBoxLayout()
        hint = QLabel("NHÌN VÀO CAMERA ĐỂ ĐIỂM DANH")
        hint.setAlignment(Qt.AlignCenter)
        hint.setStyleSheet("""
            color: rgba(255,255,255,0.55);
            font-size: 12px;
            letter-spacing: 3px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 8px 28px;
        """)
        hint_wrap.addStretch()
        hint_wrap.addWidget(hint)
        hint_wrap.addStretch()
        root.addLayout(hint_wrap)

        root.addSpacing(12)

        # ── Stats ─────────────────────────────────────────────────────────
        self.stats_label = QLabel("0 lượt điểm danh hôm nay")
        self.stats_label.setAlignment(Qt.AlignCenter)
        self.stats_label.setStyleSheet("""
            color: rgba(255,255,255,0.25);
            font-size: 13px;
            letter-spacing: 1px;
        """)
        root.addWidget(self.stats_label)

        root.addStretch(3)

        # ── Bottom bar ────────────────────────────────────────────────────
        bottom = QHBoxLayout()
        bottom.setContentsMargins(32, 0, 32, 28)

        # Camera preview — rounded glass style
        cam_wrap = QWidget()
        cam_wrap.setFixedSize(148, 112)
        cam_wrap.setStyleSheet("""
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
        """)
        cam_layout = QVBoxLayout(cam_wrap)
        cam_layout.setContentsMargins(4, 4, 4, 4)
        self.cam_preview = QLabel()
        self.cam_preview.setAlignment(Qt.AlignCenter)
        self.cam_preview.setStyleSheet("background: transparent; border: none;")
        cam_layout.addWidget(self.cam_preview)

        bottom.addWidget(cam_wrap)
        bottom.addStretch()

        # Settings button — glass circle
        btn = QPushButton("⚙")
        btn.setFixedSize(52, 52)
        btn.setStyleSheet("""
            QPushButton {
                background: rgba(255,255,255,0.08);
                color: rgba(255,255,255,0.6);
                border-radius: 26px;
                font-size: 20px;
                border: 1px solid rgba(255,255,255,0.15);
            }
            QPushButton:pressed {
                background: rgba(255,255,255,0.18);
            }
        """)
        btn.clicked.connect(self.settings_clicked)
        bottom.addWidget(btn)
        root.addLayout(bottom)

    def _tick(self):
        now = datetime.now()
        self.clock.setText(now.strftime("%H:%M:%S"))
        self.date.setText(now.strftime("%A, %d %B %Y").upper())

    def set_frame(self, frame: np.ndarray):
        small = cv2.resize(frame, (140, 104))
        rgb   = cv2.cvtColor(small, cv2.COLOR_BGR2RGB)
        rgb   = np.ascontiguousarray(rgb)
        img   = QImage(rgb.data, 140, 104, 140 * 3, QImage.Format_RGB888)
        self.cam_preview.setPixmap(QPixmap.fromImage(img))

    def set_online(self, online: bool):
        if online:
            self.online_badge.setText("● ONLINE")
            self.online_badge.setStyleSheet("""
                color: #3fb950; font-size: 12px; font-weight: bold;
                letter-spacing: 2px;
                background: rgba(63,185,80,0.12);
                border: 1px solid rgba(63,185,80,0.3);
                border-radius: 10px; padding: 4px 12px;
            """)
        else:
            self.online_badge.setText("● OFFLINE")
            self.online_badge.setStyleSheet("""
                color: #f85149; font-size: 12px; font-weight: bold;
                letter-spacing: 2px;
                background: rgba(248,81,73,0.12);
                border: 1px solid rgba(248,81,73,0.3);
                border-radius: 10px; padding: 4px 12px;
            """)

    def update_stats(self, count: int):
        self.stats_label.setText(f"{count} lượt điểm danh hôm nay")

    def mousePressEvent(self, event):
        self.screen_tapped.emit()
