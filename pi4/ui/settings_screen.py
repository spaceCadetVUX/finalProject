import os

from dotenv import dotenv_values, set_key
from PyQt5.QtCore import pyqtSignal
from PyQt5.QtWidgets import (QHBoxLayout, QLabel, QLineEdit, QPushButton,
                              QVBoxLayout, QWidget)

import api_client

ENV_PATH = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))), ".env")


class SettingsScreen(QWidget):
    back_clicked = pyqtSignal()

    def __init__(self):
        super().__init__()
        self._setup_ui()
        self._load()

    def _setup_ui(self):
        self.setStyleSheet("background-color: #0d1117; color: #e6edf3;")
        root = QVBoxLayout(self)
        root.setContentsMargins(48, 32, 48, 32)
        root.setSpacing(14)

        # ── Header ────────────────────────────────────────────────────────
        header = QHBoxLayout()
        btn_back = QPushButton("← Quay lại")
        btn_back.setFixedSize(140, 44)
        btn_back.setStyleSheet(self._btn("#21262d"))
        btn_back.clicked.connect(self.back_clicked)
        header.addWidget(btn_back)
        title = QLabel("Cài Đặt")
        title.setStyleSheet("font-size: 22px; font-weight: bold;")
        header.addWidget(title)
        header.addStretch()
        root.addLayout(header)

        # ── Fields ────────────────────────────────────────────────────────
        self.f_server   = self._field(root, "Server URL")
        self.f_token    = self._field(root, "Device Token", masked=True)
        self.f_camera   = self._field(root, "Camera Index")
        self.f_conf     = self._field(root, "Min Confidence (0.0 – 1.0)")
        self.f_cooldown = self._field(root, "Cooldown (giây)")

        root.addStretch()

        # ── Status ────────────────────────────────────────────────────────
        self.status = QLabel()
        self.status.setStyleSheet("color: #8b949e; font-size: 14px;")
        root.addWidget(self.status)

        # ── Buttons ───────────────────────────────────────────────────────
        btn_row = QHBoxLayout()
        btn_test = QPushButton("Test kết nối")
        btn_test.setFixedHeight(54)
        btn_test.setStyleSheet(self._btn("#1f6feb"))
        btn_test.clicked.connect(self._test)
        btn_row.addWidget(btn_test)

        btn_save = QPushButton("Lưu")
        btn_save.setFixedHeight(54)
        btn_save.setStyleSheet(self._btn("#238636"))
        btn_save.clicked.connect(self._save)
        btn_row.addWidget(btn_save)
        root.addLayout(btn_row)

    def _field(self, parent_layout, label: str, masked=False) -> QLineEdit:
        parent_layout.addWidget(QLabel(label))
        field = QLineEdit()
        field.setFixedHeight(46)
        if masked:
            field.setEchoMode(QLineEdit.Password)
        field.setStyleSheet("""
            QLineEdit {
                background: #161b22; border: 1px solid #30363d;
                border-radius: 6px; padding: 0 12px;
                font-size: 15px; color: #e6edf3;
            }
            QLineEdit:focus { border-color: #58a6ff; }
        """)
        parent_layout.addWidget(field)
        return field

    def _btn(self, bg: str) -> str:
        return f"""
            QPushButton {{
                background: {bg}; color: #e6edf3;
                border-radius: 8px; font-size: 15px;
                border: 1px solid #30363d;
            }}
            QPushButton:pressed {{ background: #30363d; }}
        """

    def _load(self):
        try:
            cfg = dotenv_values(ENV_PATH)
            self.f_server.setText(cfg.get("SERVER_URL", ""))
            self.f_token.setText(cfg.get("DEVICE_TOKEN", ""))
            self.f_camera.setText(cfg.get("CAMERA_INDEX", "0"))
            self.f_conf.setText(cfg.get("MIN_CONFIDENCE", "0.55"))
            self.f_cooldown.setText(cfg.get("COOLDOWN_SECONDS", "300"))
        except Exception:
            pass

    def _save(self):
        try:
            set_key(ENV_PATH, "SERVER_URL",       self.f_server.text())
            set_key(ENV_PATH, "DEVICE_TOKEN",     self.f_token.text())
            set_key(ENV_PATH, "CAMERA_INDEX",     self.f_camera.text())
            set_key(ENV_PATH, "MIN_CONFIDENCE",   self.f_conf.text())
            set_key(ENV_PATH, "COOLDOWN_SECONDS", self.f_cooldown.text())
            self._set_status("✓ Đã lưu — khởi động lại để áp dụng", "#3fb950")
        except Exception as e:
            self._set_status(f"✗ Lỗi: {e}", "#f85149")

    def _test(self):
        self._set_status("Đang kiểm tra...", "#8b949e")
        result = api_client.auth_device()
        if result:
            self._set_status(f"✓ OK — {result.get('name')}", "#3fb950")
        else:
            self._set_status("✗ Không kết nối được server", "#f85149")

    def _set_status(self, msg: str, color: str):
        self.status.setText(msg)
        self.status.setStyleSheet(f"color: {color}; font-size: 14px;")
