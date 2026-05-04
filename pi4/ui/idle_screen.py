from datetime import datetime

import cv2
import numpy as np
from PyQt5.QtCore import Qt, QTimer, pyqtSignal
from PyQt5.QtGui import QFont, QImage, QPixmap
from PyQt5.QtWidgets import (QHBoxLayout, QLabel, QPushButton, QVBoxLayout,
                              QWidget)


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

    def _setup_ui(self):
        self.setStyleSheet("background-color: #0d1117;")
        root = QVBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── Top bar ───────────────────────────────────────────────────────
        top = QHBoxLayout()
        top.setContentsMargins(24, 16, 24, 0)
        top.addStretch()
        self.online_badge = QLabel("● ONLINE")
        self.online_badge.setStyleSheet(
            "color: #3fb950; font-size: 14px; font-weight: bold;")
        top.addWidget(self.online_badge)
        root.addLayout(top)

        root.addStretch(2)

        # ── Clock ─────────────────────────────────────────────────────────
        self.clock = QLabel("00:00:00")
        self.clock.setAlignment(Qt.AlignCenter)
        self.clock.setFont(QFont("Monospace", 68, QFont.Bold))
        self.clock.setStyleSheet("color: #e6edf3;")
        root.addWidget(self.clock)

        # ── Date ──────────────────────────────────────────────────────────
        self.date = QLabel()
        self.date.setAlignment(Qt.AlignCenter)
        self.date.setStyleSheet("color: #8b949e; font-size: 20px;")
        root.addWidget(self.date)

        root.addSpacing(28)

        # ── Hint ──────────────────────────────────────────────────────────
        hint = QLabel("Nhìn vào camera hoặc chạm màn hình để điểm danh")
        hint.setAlignment(Qt.AlignCenter)
        hint.setStyleSheet("color: #58a6ff; font-size: 17px;")
        root.addWidget(hint)

        root.addStretch(2)

        # ── Bottom bar ────────────────────────────────────────────────────
        bottom = QHBoxLayout()
        bottom.setContentsMargins(24, 0, 24, 20)

        self.cam_preview = QLabel()
        self.cam_preview.setFixedSize(160, 120)
        self.cam_preview.setStyleSheet(
            "background: #000; border: 1px solid #30363d; border-radius: 4px;")
        bottom.addWidget(self.cam_preview)

        bottom.addStretch()

        btn = QPushButton("⚙")
        btn.setFixedSize(56, 56)
        btn.setStyleSheet("""
            QPushButton {
                background: #21262d; color: #8b949e;
                border-radius: 28px; font-size: 24px;
                border: 1px solid #30363d;
            }
            QPushButton:pressed { background: #30363d; }
        """)
        btn.clicked.connect(self.settings_clicked)
        bottom.addWidget(btn)
        root.addLayout(bottom)

    def _tick(self):
        now = datetime.now()
        self.clock.setText(now.strftime("%H:%M:%S"))
        self.date.setText(now.strftime("%A, %d %B %Y"))

    def set_frame(self, frame: np.ndarray):
        small = cv2.resize(frame, (160, 120))
        rgb   = cv2.cvtColor(small, cv2.COLOR_BGR2RGB)
        rgb   = np.ascontiguousarray(rgb)
        img   = QImage(rgb.data, 160, 120, 160 * 3, QImage.Format_RGB888)
        self.cam_preview.setPixmap(QPixmap.fromImage(img))

    def set_online(self, online: bool):
        if online:
            self.online_badge.setText("● ONLINE")
            self.online_badge.setStyleSheet(
                "color: #3fb950; font-size: 14px; font-weight: bold;")
        else:
            self.online_badge.setText("● OFFLINE")
            self.online_badge.setStyleSheet(
                "color: #f85149; font-size: 14px; font-weight: bold;")

    def mousePressEvent(self, event):
        self.screen_tapped.emit()
