# Pi4 — Kế Hoạch Sprint Toàn Bộ

> **Stack:** Python 3.9+ · face_recognition (dlib) · OpenCV · PyQt5 · SQLite · requests
> **Chạy:** `python main.py` sau khi cấu hình `.env`
> **Màn hình:** 9 inch landscape touchscreen, camera chiếm 1/3 trái

---

## Sprint 1 — Cài Đặt & Test Cơ Bản

### 1.1 Môi trường
- [ ] Python 3.9+ đã cài
- [ ] Tạo và kích hoạt virtual environment
- [ ] Chạy `pip install -r requirements.txt` thành công
- [ ] Không lỗi khi import `face_recognition`, `cv2`, `requests`, `PyQt5`

### 1.2 Cấu hình
- [ ] Copy `.env.example` → `.env`
- [ ] Điền `SERVER_URL` (URL Laravel đang chạy)
- [ ] Điền `DEVICE_TOKEN` (token lấy từ DB Laravel)
- [ ] Điền `CAMERA_INDEX` đúng với thiết bị

### 1.3 Test Camera
- [ ] Tạo `test_camera.py`
- [ ] Chạy → cửa sổ hiện video từ webcam
- [ ] Không bị đen, không bị giật

### 1.4 Test Face Recognition
- [ ] Tạo thư mục `test_images/` với ≥ 2 ảnh khuôn mặt rõ
- [ ] Tạo `test_face.py`
- [ ] Chạy → nhận diện được khuôn mặt, hiển thị tên + confidence %
- [ ] Confidence > 80% khi soi đúng ảnh đã train

### 1.5 Test SQLite
- [ ] `local_storage.init_db()` tạo file `.db` thành công
- [ ] `save_record()` lưu được record
- [ ] `get_unsynced()` đọc đúng
- [ ] `mark_synced()` cập nhật đúng flag

### Lệnh
```bash
python -m venv venv
source venv/bin/activate        # Linux/Pi
pip install -r requirements.txt
python test_camera.py
python test_face.py
```

---

## Sprint 2 — Nhận Diện Khuôn Mặt Cục Bộ

### 2.1 Chuẩn bị encoding cục bộ
- [ ] Tạo `encode_local.py` — encode ảnh từ thư mục local
  - [ ] Đọc tất cả ảnh trong `test_images/`
  - [ ] Lưu ra file `encodings.json` dạng `[{user_id, name, encoding}]`

### 2.2 FaceRecognizer load từ file JSON
- [ ] Thêm method `load_from_file(path)` vào `face_recognizer.py`
- [ ] Test nhận diện từ file JSON (chưa cần API)
- [ ] Xác nhận nhận diện được ≥ 2 người

### 2.3 Logic xác định check_in / check_out
- [ ] Buổi sáng (trước 12:00) → `check_in`
- [ ] Buổi chiều (từ 12:00) → `check_out`
- [ ] Cooldown: cùng `user_id` không ghi lại trong `COOLDOWN_SECONDS` giây

### Lệnh
```bash
python encode_local.py
python main.py
```

---

## Sprint 3 — Kết Nối API Laravel

### 3.1 Test kết nối server
- [ ] Laravel đang chạy trên cùng mạng LAN
- [ ] Ping đến `SERVER_URL` thành công
- [ ] Lấy token từ bảng `devices` trong DB Laravel
- [ ] Điền token vào `.env`

### 3.2 Auth device
- [ ] Test `POST /api/auth/device` trả về 200
- [ ] `api_client.py` gọi thành công, nhận device_id

### 3.3 Fetch encodings từ server
- [ ] `GET /api/encodings` trả về JSON đúng format
- [ ] Test delta sync: `GET /api/encodings?updated_since=<ts>` chỉ trả encoding mới
- [ ] Chạy main.py → nhận diện từ API (không cần `encodings.json`)

### 3.4 Gửi attendance lên server
- [ ] `POST /api/attendance` với payload mẫu → DB Laravel có record
- [ ] Ảnh lưu đúng vào `storage/app/attendance/`

### 3.5 Heartbeat ping
- [ ] `POST /api/device/ping` → device status = `online` trong DB
- [ ] Ping tự động mỗi `PING_INTERVAL_SECONDS` giây

### 3.6 Tích hợp vào main.py
- [ ] Khi khởi động: fetch encodings từ server, nếu lỗi → load từ file local
- [ ] Mỗi nhận diện thành công: gửi API → nếu fail → lưu SQLite
- [ ] Mỗi `PING_INTERVAL_SECONDS`: ping + sync pending + delta fetch encoding

---

## Sprint 4 — Offline Buffer & Sync

### 4.1 Xử lý mất mạng
- [ ] Tắt Laravel server → Pi vẫn nhận diện bình thường
- [ ] Kết quả được lưu vào SQLite (`synced=0`)

### 4.2 Sync khi có mạng trở lại
- [ ] Pi tự phát hiện mạng khi ping thành công
- [ ] `sync_manager.sync_pending()` gửi `POST /api/attendance/batch`
- [ ] Records được đánh dấu `synced=1`

### 4.3 Xử lý duplicate
- [ ] Gửi cùng 1 record 2 lần → Laravel chỉ lưu 1 lần (unique constraint)

### 4.4 Delta sync encoding khi reconnect
- [ ] Sau khi reconnect, Pi tải encoding mới theo `updated_since`

---

## Sprint 5 — PyQt5 Touchscreen UI (9 inch Landscape)

### 5.1 Cài đặt & cấu trúc UI
- [ ] `pip install PyQt5` thành công trên Pi
- [ ] Tạo `ui/` folder:
  ```
  ui/
  ├── app.py           ← QApplication entry point
  ├── main_window.py   ← QMainWindow, quản lý chuyển màn
  ├── idle_screen.py   ← Màn hình chờ
  ├── active_screen.py ← Màn hình điểm danh
  └── settings_screen.py ← Cài đặt token/server
  ```
- [ ] Window fullscreen, không có title bar, không resize

### 5.2 Idle Screen (màn hình chờ)
- [ ] Layout landscape, nền tối (dark theme)
- [ ] Hiển thị đồng hồ to ở giữa: `HH:MM:SS`
- [ ] Ngày tháng năm bên dưới: `Monday, 03 May 2026`
- [ ] Dòng chữ nhỏ: `"Approach camera or tap to check in"`
- [ ] Icon Settings góc dưới phải để vào Settings screen
- [ ] Camera chạy ở background ở low FPS (1 FPS) để detect motion/face

### 5.3 Active Screen (màn hình điểm danh)
Layout:
```
┌─────────────────┬──────────────────────────────────┐
│                 │  ✓ CHECK IN                       │
│  CAMERA FEED    │  ─────────────────────────────── │
│  (live video)   │  John Doe                         │
│  640px wide     │  EMP-001  |  Engineering          │
│                 │  08:02 AM  Mon 03 May 2026        │
│  [face bbox]    │                                   │
│                 │  [ Next Person ]  (button)        │
└─────────────────┴──────────────────────────────────┘
```
- [ ] Camera feed chiếm 1/3 trái, full height
- [ ] Vẽ bounding box xanh (recognized) / đỏ (unknown) lên camera feed
- [ ] Phần phải 2/3: hiện tên, mã NV, phòng ban, loại (CHECK IN / CHECK OUT), giờ
- [ ] Animation fade-in khi nhận diện thành công
- [ ] Sau 30 giây tự reset về trạng thái chờ nhận diện tiếp
- [ ] Nút `[ Next Person ]` để reset ngay lập tức

### 5.4 Hybrid Trigger (auto-detect + touch)
- [ ] Idle → face detected trong frame → chuyển sang Active screen tự động
- [ ] Idle → user tap màn hình → chuyển sang Active screen
- [ ] Active → không có ai 10 phút → quay về Idle, camera giảm xuống 1 FPS
- [ ] Logic trong `main_window.py` quản lý state machine:
  ```
  IDLE ──(face/tap)──▶ ACTIVE ──(10 min idle)──▶ IDLE
                         │
                    (30s or tap Next)
                         │
                       ACTIVE (reset, chờ người tiếp)
  ```

### 5.5 Settings Screen
- [ ] Mở bằng tap icon Settings từ Idle screen
- [ ] Fields:
  - `Server URL` (text input)
  - `Device Token` (text input, masked)
  - `Camera Index` (số)
  - `Min Confidence` (slider 0.5–0.95)
  - `Cooldown (seconds)` (số)
- [ ] Nút `[ Save ]` → ghi vào `.env` → restart config
- [ ] Nút `[ Test Connection ]` → ping server → hiện kết quả
- [ ] Nút `[ Back ]` → quay về Idle

### 5.6 Thread architecture
- [ ] `CameraThread(QThread)` — đọc frame, chạy recognition, emit signal
- [ ] `MainWindow` nhận signal → update UI (không block main thread)
- [ ] `SyncThread(QThread)` — ping + sync offline records định kỳ

### Lệnh
```bash
python ui/app.py
# hoặc từ main.py khi có flag
DISPLAY_UI=true python main.py
```

---

## Sprint 6 — Anti-Spoofing (Chống Giả Mạo)

### 6.1 Cài đặt MiniFASNet
- [ ] Clone `Silent-Face-Anti-Spoofing` repo
- [ ] Copy model weights vào `models/anti_spoof/`
- [ ] Test predict trên ảnh tĩnh → phân biệt được ảnh thật vs ảnh in

### 6.2 Tích hợp vào pipeline nhận diện
- [ ] Thêm `liveness_detector.py`:
  ```python
  class LivenessDetector:
      def is_real(self, frame, bbox) -> tuple[bool, float]:
          # Trả về (is_real, score)
  ```
- [ ] Gắn vào `face_recognizer.py`: chạy liveness check trước khi compare encoding
- [ ] Nếu `score < LIVENESS_THRESHOLD` → bỏ qua, hiện thông báo `"Spoof detected"`
- [ ] Thêm `LIVENESS_THRESHOLD=0.7` vào `config.py` và `.env.example`

### 6.3 Hiển thị trên UI
- [ ] Camera feed: bounding box **vàng** khi đang check liveness
- [ ] Thông báo `"Please look at camera"` khi score thấp
- [ ] Log liveness score vào `attendance.log`

### 6.4 Benchmark
- [ ] Đo FPS trước và sau khi thêm liveness check
- [ ] Target: FPS ≥ 3 sau khi thêm anti-spoof

---

## Sprint 7 — Tối Ưu & Logging

### 7.1 Tối ưu hiệu năng Pi 4
- [ ] Resize frame xuống 1/2 trước khi detect (đã resize 1/2 trong `face_recognizer.py`)
- [ ] Chỉ xử lý mỗi `PROCESS_EVERY_N` frame
- [ ] Idle mode: camera chạy 1 FPS, chỉ tăng khi phát hiện chuyển động
- [ ] Benchmark FPS trong từng mode

### 7.2 Logging ra file
- [ ] Tạo `logger.py` — wrapper quanh `logging` module
- [ ] Format: `[2026-05-03 08:02:11] [INFO] Check-in: user_id=5, confidence=0.92`
- [ ] Log vào file `attendance.log` + stdout
- [ ] Rotate log khi > 10MB

### 7.3 Headless mode (không màn hình)
- [ ] Flag `HEADLESS=true` trong `.env`
- [ ] Khi headless: không khởi động PyQt5, không `cv2.imshow()`
- [ ] Chỉ chạy recognition loop + API sync

---

## Sprint 8 — Auto-start & Deploy Pi

### 8.1 Systemd service
- [ ] Tạo `attendance.service`:
  ```ini
  [Unit]
  Description=Attendance Face Recognition
  After=network.target graphical.target

  [Service]
  User=pi
  Environment=DISPLAY=:0
  WorkingDirectory=/home/pi/attendance/pi4
  ExecStart=/home/pi/attendance/pi4/venv/bin/python ui/app.py
  Restart=always
  RestartSec=5

  [Install]
  WantedBy=graphical.target
  ```
- [ ] `sudo systemctl enable attendance`
- [ ] `sudo systemctl start attendance`
- [ ] Kiểm tra: `sudo systemctl status attendance`

### 8.2 Test trên Pi 4 thật
- [ ] Copy code lên Pi (git clone hoặc scp)
- [ ] Cài dependencies trên Pi OS
- [ ] Mở Settings screen → nhập Server URL + Token → Save
- [ ] Test flow đầy đủ: Idle → nhận diện → hiện info → reset

### 8.3 Edge cases
- [ ] Nhiều khuôn mặt cùng lúc → nhận diện tất cả, hiện lần lượt
- [ ] Camera bị ngắt → reconnect tự động, hiện thông báo lỗi trên UI
- [ ] Mất mạng → UI hiện badge `OFFLINE`, vẫn nhận diện và lưu local

---

## Tổng Quan Tiến Độ

| Sprint | Nội dung | Trạng thái |
|---|---|---|
| Sprint 1 | Cài đặt & Test cơ bản | ⬜ Cần chạy trên Pi |
| Sprint 2 | Nhận diện khuôn mặt cục bộ | ⬜ Cần test thực tế |
| Sprint 3 | Kết nối API Laravel | ⬜ Cần test thực tế |
| Sprint 4 | Offline buffer & sync | ⬜ Cần test thực tế |
| Sprint 5 | PyQt5 Touchscreen UI | ⬜ Chưa bắt đầu |
| Sprint 6 | Anti-Spoofing (MiniFASNet) | ⬜ Chưa bắt đầu |
| Sprint 7 | Tối ưu & Logging | ⬜ Chưa bắt đầu |
| Sprint 8 | Auto-start & Deploy | ⬜ Chưa bắt đầu |

---

## Files Cần Tạo/Cập Nhật Theo Sprint

```
pi4/
├── main.py                  ← Sprint 3, Sprint 7 (headless flag)
├── face_recognizer.py       ← Sprint 2, Sprint 6 (liveness hook)
├── camera.py                ← Sprint 1
├── api_client.py            ← Sprint 3
├── local_storage.py         ← Sprint 1, Sprint 4
├── sync_manager.py          ← Sprint 4
├── config.py                ← Sprint 1, Sprint 6 (LIVENESS_THRESHOLD)
├── liveness_detector.py     ← Sprint 6 (tạo mới)
├── logger.py                ← Sprint 7 (tạo mới)
├── encode_local.py          ← Sprint 2 (tạo mới)
├── test_camera.py           ← Sprint 1 (tạo mới)
├── test_face.py             ← Sprint 1 (tạo mới)
├── attendance.service       ← Sprint 8 (tạo mới)
├── requirements.txt         ← Sprint 1, Sprint 5 (thêm PyQt5)
├── .env.example             ← Sprint 1, Sprint 6 (thêm LIVENESS_THRESHOLD)
├── face_encode_single.py    ← Dùng bởi Laravel EncodeFaceJob
├── models/
│   └── anti_spoof/          ← Sprint 6 (model weights)
└── ui/                      ← Sprint 5 (tạo mới)
    ├── app.py
    ├── main_window.py
    ├── idle_screen.py
    ├── active_screen.py
    └── settings_screen.py
```
