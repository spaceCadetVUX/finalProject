import cv2
import numpy as np
from PyQt5.QtCore import Qt, pyqtSignal
from PyQt5.QtGui import QFont, QImage, QPixmap
from PyQt5.QtWidgets import (QFrame, QHBoxLayout, QLabel, QLineEdit,
                              QPushButton, QVBoxLayout, QWidget)

try:
    import face_recognition
    _FR_AVAILABLE = True
except ImportError:
    _FR_AVAILABLE = False


class AddEmployeeScreen(QWidget):
    back_clicked   = pyqtSignal()
    employee_added = pyqtSignal(dict)   # {name, code, encoding}

    def __init__(self):
        super().__init__()
        self._last_frame     = None
        self._captured_enc   = None
        self._do_capture     = False
        self._setup_ui()

    def _setup_ui(self):
        self.setStyleSheet("background-color: #ffffff;")
        root = QHBoxLayout(self)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)

        # ── Left: camera preview ──────────────────────────────────────────
        left = QWidget()
        left.setFixedWidth(400)
        left.setStyleSheet("background: #f6f8fa;")
        ll = QVBoxLayout(left)
        ll.setContentsMargins(16, 20, 16, 20)
        ll.setSpacing(12)

        self.cam_label = QLabel()
        self.cam_label.setFixedSize(368, 276)
        self.cam_label.setAlignment(Qt.AlignCenter)
        self.cam_label.setStyleSheet(
            "background: #eaeef2; border-radius: 6px;")
        ll.addWidget(self.cam_label)

        self.face_hint = QLabel("Nhìn thẳng vào camera rồi bấm Chụp ảnh")
        self.face_hint.setAlignment(Qt.AlignCenter)
        self.face_hint.setWordWrap(True)
        self.face_hint.setStyleSheet("color: #57606a; font-size: 13px;")
        ll.addWidget(self.face_hint)

        self.btn_capture = QPushButton("📷  Chụp ảnh")
        self.btn_capture.setFixedHeight(52)
        self.btn_capture.setStyleSheet(self._btn("#0969da", "white"))
        self.btn_capture.clicked.connect(self._on_capture)
        ll.addWidget(self.btn_capture)

        ll.addStretch()
        root.addWidget(left)

        div = QFrame()
        div.setFrameShape(QFrame.VLine)
        div.setFixedWidth(1)
        div.setStyleSheet("color: #d0d7de;")
        root.addWidget(div)

        # ── Right: form ───────────────────────────────────────────────────
        right = QWidget()
        right.setStyleSheet("background-color: #ffffff;")
        rl = QVBoxLayout(right)
        rl.setContentsMargins(28, 20, 28, 20)
        rl.setSpacing(10)
        root.addWidget(right, 1)

        # Header
        hdr = QHBoxLayout()
        btn_back = QPushButton("← Quay lại")
        btn_back.setFixedSize(130, 40)
        btn_back.setStyleSheet(self._btn("#f6f8fa", "#24292f"))
        btn_back.clicked.connect(self._on_back)
        hdr.addWidget(btn_back)
        title = QLabel("Thêm nhân viên")
        title.setFont(QFont("Sans", 18, QFont.Bold))
        title.setStyleSheet("color: #24292f;")
        hdr.addWidget(title)
        hdr.addStretch()
        rl.addLayout(hdr)

        sep = QFrame()
        sep.setFrameShape(QFrame.HLine)
        sep.setStyleSheet("color: #d0d7de;")
        rl.addWidget(sep)

        # Name
        lbl_name = QLabel("Họ và tên *")
        lbl_name.setStyleSheet("color: #57606a; font-size: 13px;")
        rl.addWidget(lbl_name)
        self.f_name = QLineEdit()
        self.f_name.setPlaceholderText("Ví dụ: Tạ Minh Vũ")
        self.f_name.setFixedHeight(46)
        self.f_name.setStyleSheet(self._field())
        rl.addWidget(self.f_name)

        # Code
        lbl_code = QLabel("Mã nhân viên *")
        lbl_code.setStyleSheet("color: #57606a; font-size: 13px;")
        rl.addWidget(lbl_code)
        self.f_code = QLineEdit()
        self.f_code.setPlaceholderText("Ví dụ: NV001")
        self.f_code.setFixedHeight(46)
        self.f_code.setStyleSheet(self._field())
        rl.addWidget(self.f_code)

        rl.addStretch()

        self.status_label = QLabel()
        self.status_label.setWordWrap(True)
        self.status_label.setStyleSheet("color: #57606a; font-size: 13px;")
        rl.addWidget(self.status_label)

        self.btn_save = QPushButton("✓  Lưu nhân viên")
        self.btn_save.setFixedHeight(56)
        self.btn_save.setStyleSheet(self._btn("#1a7f37", "white"))
        self.btn_save.setEnabled(False)
        self.btn_save.clicked.connect(self._on_save)
        rl.addWidget(self.btn_save)

    # ── styles ────────────────────────────────────────────────────────────

    def _btn(self, bg, fg="#24292f"):
        return f"""
            QPushButton {{
                background: {bg}; color: {fg};
                border-radius: 8px; font-size: 15px;
                border: 1px solid #d0d7de;
            }}
            QPushButton:pressed {{ opacity: 0.85; }}
            QPushButton:disabled {{ background: #eaeef2; color: #8c959f; }}
        """

    def _field(self):
        return """
            QLineEdit {
                background: #f6f8fa; border: 1px solid #d0d7de;
                border-radius: 6px; padding: 0 12px;
                font-size: 15px; color: #24292f;
            }
            QLineEdit:focus { border-color: #0969da; }
        """

    # ── camera feed ───────────────────────────────────────────────────────

    def set_frame(self, frame: np.ndarray):
        self._last_frame = frame

        if self._do_capture:
            self._do_capture = False
            self._process_capture(frame)
            return

        if self._captured_enc is not None:
            return  # freeze on captured photo

        h, w = 276, 368
        small = cv2.resize(frame, (w, h))
        rgb   = cv2.cvtColor(small, cv2.COLOR_BGR2RGB)
        rgb   = np.ascontiguousarray(rgb)
        img   = QImage(rgb.data, w, h, w * 3, QImage.Format_RGB888)
        self.cam_label.setPixmap(QPixmap.fromImage(img))

    def _on_capture(self):
        if not _FR_AVAILABLE:
            self._set_status("face_recognition chưa cài trên thiết bị này.", "#cf222e")
            return
        if self._last_frame is None:
            self._set_status("Camera chưa sẵn sàng.", "#cf222e")
            return
        self._captured_enc = None      # unfreeze display while processing
        self._do_capture   = True
        self._set_status("Đang nhận diện khuôn mặt...", "#57606a")
        self.btn_capture.setEnabled(False)

    def _process_capture(self, frame: np.ndarray):
        rgb       = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        rgb_small = np.ascontiguousarray(rgb[::2, ::2])

        locations = face_recognition.face_locations(rgb_small, model="hog")
        if not locations:
            self._set_status("Không tìm thấy khuôn mặt. Hãy chụp lại.", "#cf222e")
            self.btn_capture.setEnabled(True)
            return

        # Encode ở full resolution để chất lượng cao hơn
        locs_full  = [(t * 2, r * 2, b * 2, l * 2) for t, r, b, l in locations]
        rgb_full   = np.ascontiguousarray(rgb)
        encodings  = face_recognition.face_encodings(rgb_full, locs_full)
        if not encodings:
            self._set_status("Không encode được khuôn mặt. Hãy chụp lại.", "#cf222e")
            self.btn_capture.setEnabled(True)
            return

        self._captured_enc = encodings[0].tolist()

        # Hiện preview ảnh đã chụp (freeze)
        h, w  = 276, 368
        small = cv2.resize(frame, (w, h))

        # Vẽ box lên preview
        t, r, b, l = locs_full[0]
        # scale box sang preview coords
        oh, ow = frame.shape[:2]
        t2 = int(t * h / oh); b2 = int(b * h / oh)
        l2 = int(l * w / ow); r2 = int(r * w / ow)
        cv2.rectangle(small, (l2, t2), (r2, b2), (0, 180, 0), 2)

        rgb_p = cv2.cvtColor(small, cv2.COLOR_BGR2RGB)
        rgb_p = np.ascontiguousarray(rgb_p)
        img   = QImage(rgb_p.data, w, h, w * 3, QImage.Format_RGB888)
        self.cam_label.setPixmap(QPixmap.fromImage(img))

        self._set_status("✓ Nhận diện thành công. Điền thông tin rồi bấm Lưu.", "#1a7f37")
        self.btn_capture.setText("🔄  Chụp lại")
        self.btn_capture.setEnabled(True)
        self.btn_save.setEnabled(True)

    # ── form actions ──────────────────────────────────────────────────────

    def _on_save(self):
        name = self.f_name.text().strip()
        code = self.f_code.text().strip()
        if not name:
            self._set_status("Vui lòng nhập họ tên.", "#cf222e")
            return
        if not code:
            self._set_status("Vui lòng nhập mã nhân viên.", "#cf222e")
            return
        if not self._captured_enc:
            self._set_status("Vui lòng chụp ảnh trước.", "#cf222e")
            return
        self.btn_save.setEnabled(False)
        self.btn_capture.setEnabled(False)
        self._set_status("Đang lưu lên server...", "#57606a")
        self.employee_added.emit({
            "name":     name,
            "code":     code,
            "encoding": self._captured_enc,
        })

    def _on_back(self):
        self.reset()
        self.back_clicked.emit()

    # ── public helpers ────────────────────────────────────────────────────

    def set_result(self, success: bool, message: str):
        if success:
            self._set_status(f"✓ {message}", "#1a7f37")
            self.btn_save.setEnabled(False)
            self.btn_capture.setEnabled(True)
        else:
            self._set_status(f"✗ {message}", "#cf222e")
            self.btn_save.setEnabled(True)
            self.btn_capture.setEnabled(True)

    def reset(self):
        self._last_frame   = None
        self._captured_enc = None
        self._do_capture   = False
        self.f_name.clear()
        self.f_code.clear()
        self.btn_capture.setText("📷  Chụp ảnh")
        self.btn_capture.setEnabled(True)
        self.btn_save.setEnabled(False)
        self.status_label.clear()

    def _set_status(self, msg, color="#57606a"):
        self.status_label.setText(msg)
        self.status_label.setStyleSheet(f"color: {color}; font-size: 13px;")
