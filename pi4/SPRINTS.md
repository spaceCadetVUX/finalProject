# Pi4 — Kế Hoạch Sprint Toàn Bộ

> **Stack:** Python 3.9+ · face_recognition (dlib) · OpenCV · SQLite · requests
> **Chạy:** `python main.py` sau khi cấu hình `.env`

---

## Sprint 1 — Cài Đặt & Test Cơ Bản ✅ (cần hoàn thành)

### 1.1 Môi trường
- [ ] Python 3.9+ đã cài
- [ ] Tạo và kích hoạt virtual environment
- [ ] Chạy `pip install -r requirements.txt` thành công
- [ ] Không lỗi khi import `face_recognition`, `cv2`, `requests`

### 1.2 Cấu hình
- [ ] Copy `.env.example` → `.env`
- [ ] Điền `SERVER_URL` (URL Laravel đang chạy)
- [ ] Điền `DEVICE_TOKEN` (token tạm, lấy từ DB sau)
- [ ] Điền `CAMERA_INDEX` đúng với thiết bị

### 1.3 Test Camera
- [ ] Tạo `test_camera.py`
- [ ] Chạy → cửa sổ hiện video từ webcam
- [ ] Không bị đen, không bị giật

### 1.4 Test Face Recognition
- [ ] Tạo thư mục `test_images/` với ≥ 2 ảnh khuôn mặt rõ
- [ ] Tạo `test_face.py`
- [ ] Chạy → camera nhận diện được khuôn mặt, hiển thị tên + confidence %
- [ ] Confidence > 80% khi soi đúng ảnh đã train

### 1.5 Đo hiệu năng
- [ ] FPS trung bình ≥ 3 với `PROCESS_EVERY_N=3`
- [ ] Ghi lại FPS để so sánh sau khi optimize

### 1.6 Test SQLite
- [ ] `local_storage.init_db()` tạo file `.db` thành công
- [ ] `save_record()` lưu được record
- [ ] `get_unsynced()` đọc đúng
- [ ] `mark_synced()` cập nhật đúng flag

### Lệnh
```bash
python -m venv venv
venv\Scripts\activate          # Windows
pip install -r requirements.txt
python test_camera.py
python test_face.py
```

---

## Sprint 2 — Nhận Diện Khuôn Mặt Cục Bộ

### 2.1 Chuẩn bị encoding cục bộ
- [ ] Tạo `encode_local.py` — script encode ảnh từ thư mục local
  - [ ] Đọc tất cả ảnh trong `test_images/`
  - [ ] Tính face encoding cho mỗi ảnh
  - [ ] Lưu ra file `encodings.json` dạng `[{user_id, name, encoding}]`

### 2.2 FaceRecognizer load từ file JSON
- [ ] Cập nhật `face_recognizer.py`
  - [ ] Thêm method `load_from_file(path)` đọc `encodings.json`
  - [ ] Test nhận diện từ file JSON (chưa cần API)
- [ ] Xác nhận nhận diện được ≥ 2 người khác nhau

### 2.3 Hiển thị thông tin lên màn hình
- [ ] Khung xanh quanh khuôn mặt đã nhận diện
- [ ] Khung đỏ quanh khuôn mặt không nhận ra
- [ ] Label: `Tên | Confidence% | [check_in/check_out]`
- [ ] FPS counter góc trái trên
- [ ] Thông báo "Đã ghi nhận" khi nhận diện thành công

### 2.4 Logic xác định check_in / check_out
- [ ] Buổi sáng (trước 12:00) → `check_in`
- [ ] Buổi chiều (từ 12:00) → `check_out`
- [ ] Cooldown: cùng `user_id` không ghi lại trong `COOLDOWN_SECONDS` giây
- [ ] Test cooldown hoạt động đúng

### Lệnh
```bash
python encode_local.py
python main.py
```

### Kết quả kỳ vọng
- Nhận diện đúng ≥ 2 người từ encodings.json
- Cooldown hoạt động (không ghi 2 lần liên tiếp)
- FPS ổn định ≥ 3

---

## Sprint 3 — Kết Nối API Laravel (quan trọng nhất)

### 3.1 Test kết nối server
- [ ] Laravel đang chạy trên cùng mạng LAN
- [ ] Ping đến `SERVER_URL` thành công
- [ ] Tạo device trong DB Laravel (chạy seeder hoặc thêm manual)
- [ ] Lấy token từ bảng `devices`
- [ ] Điền token vào `.env`

### 3.2 Auth device
- [ ] Test `POST /api/auth/device` trả về 200
- [ ] `api_client.py` gọi thành công, nhận device_id

### 3.3 Fetch encodings từ server
- [ ] Test `GET /api/encodings` trả về JSON đúng format
- [ ] `face_recognizer.load_encodings(records)` nạp được từ API response
- [ ] Xóa `encodings.json` local, chạy main.py → nhận diện từ API
- [ ] Test delta sync: `GET /api/encodings?updated_since=<ts>` chỉ trả encoding mới

### 3.4 Gửi attendance lên server
- [ ] Test `POST /api/attendance` với payload mẫu → DB Laravel có record
- [ ] Trạng thái tính đúng (đúng giờ → `present`, trễ → `late`)
- [ ] Ảnh lưu đúng vào `storage/app/attendance/`

### 3.5 Heartbeat ping
- [ ] `POST /api/device/ping` → device status = `online` trong DB
- [ ] Ping tự động mỗi `PING_INTERVAL_SECONDS` giây

### 3.6 Tích hợp vào main.py
- [ ] Khi khởi động: fetch encodings từ server, nếu lỗi → load từ file local
- [ ] Mỗi nhận diện thành công: gửi API → nếu fail → lưu SQLite
- [ ] Mỗi `PING_INTERVAL_SECONDS`: ping + sync pending + delta fetch encoding

### Lệnh
```bash
# Test API thủ công
python -c "import api_client; print(api_client.ping())"
python -c "import api_client; print(api_client.fetch_encodings())"
python main.py
```

### Kết quả kỳ vọng
- Nhận diện → gửi API → record xuất hiện trong HeidiSQL ngay
- Dashboard Laravel cập nhật khi refresh

---

## Sprint 4 — Offline Buffer & Sync

### 4.1 Xử lý mất mạng
- [ ] Test: tắt Laravel server → Pi vẫn nhận diện bình thường
- [ ] Kết quả được lưu vào SQLite (`pending_records` với `synced=0`)
- [ ] Kiểm tra file `.db` có records đúng

### 4.2 Sync khi có mạng trở lại
- [ ] Bật lại Laravel server
- [ ] Pi tự động phát hiện mạng hoạt động khi ping thành công
- [ ] `sync_manager.sync_pending()` gửi `POST /api/attendance/batch`
- [ ] Records trong SQLite được đánh dấu `synced=1`
- [ ] Records xuất hiện trong bảng `attendances` của Laravel

### 4.3 Xử lý duplicate
- [ ] Gửi cùng 1 record 2 lần → Laravel chỉ lưu 1 lần (unique constraint)
- [ ] Batch không crash khi có record trùng

### 4.4 Delta sync encoding khi reconnect
- [ ] Sau khi reconnect, Pi tải encoding mới theo `updated_since`
- [ ] Không tải lại toàn bộ encoding

### Test scenario
```
1. Thêm nhân viên mới trong Laravel (admin web)
2. Tắt Laravel server
3. Pi nhận diện vài lần → lưu SQLite
4. Bật lại Laravel
5. Kiểm tra: records sync lên + encoding nhân viên mới được tải
```

### Kết quả kỳ vọng
- Mất mạng không làm crash Pi
- Reconnect → sync tự động trong vòng `PING_INTERVAL_SECONDS` giây

---

## Sprint 5 — Tối Ưu & Hiển Thị

### 5.1 Tối ưu hiệu năng Pi 4
- [ ] Benchmark FPS trước và sau khi optimize
- [ ] Resize frame xuống 1/4 trước khi detect (đã có trong code)
- [ ] Chỉ xử lý mỗi `PROCESS_EVERY_N` frame
- [ ] Tắt OpenCV display khi chạy headless (Pi không có màn hình)
- [ ] Dùng `model='hog'` (không dùng `cnn`)

### 5.2 Hiển thị kết quả (nếu có màn hình HDMI)
- [ ] Fullscreen window trên Pi display
- [ ] Hiển thị: tên nhân viên + ảnh + trạng thái (check_in/check_out) + giờ
- [ ] Thông báo nổi bật 3 giây khi nhận diện thành công
- [ ] Hiển thị trạng thái kết nối server (online/offline)

### 5.3 Headless mode (Pi không màn hình)
- [ ] Thêm flag `HEADLESS=true` vào `.env`
- [ ] Khi `HEADLESS=true`: tắt `cv2.imshow()`, chỉ log ra console

### 5.4 Logging
- [ ] Ghi log ra file `attendance.log`
- [ ] Format: `[timestamp] [INFO/ERROR] message`
- [ ] Log: khởi động, nhận diện thành công, lỗi API, sync

### Lệnh
```bash
# Chạy headless
HEADLESS=true python main.py

# Xem log
tail -f attendance.log
```

---

## Sprint 6 — Auto-start & Deploy Pi

### 6.1 Systemd service (Pi chạy tự động khi bật)
- [ ] Tạo file `/etc/systemd/system/attendance.service`
  ```ini
  [Unit]
  Description=Attendance Face Recognition
  After=network.target

  [Service]
  User=pi
  WorkingDirectory=/home/pi/attendance/pi4
  ExecStart=/home/pi/attendance/pi4/venv/bin/python main.py
  Restart=always
  RestartSec=5

  [Install]
  WantedBy=multi-user.target
  ```
- [ ] Enable service: `sudo systemctl enable attendance`
- [ ] Start: `sudo systemctl start attendance`
- [ ] Kiểm tra: `sudo systemctl status attendance`

### 6.2 Test trên Pi 4 thật
- [ ] Copy code lên Pi (git clone hoặc scp)
- [ ] Cài dependencies trên Pi OS
- [ ] Cấu hình `.env` với IP server thật
- [ ] Chạy thử, kiểm tra FPS thực tế
- [ ] Đo thời gian phản hồi từ nhận diện đến lưu DB

### 6.3 Xử lý edge cases
- [ ] Nhiều khuôn mặt cùng lúc trong frame → nhận diện tất cả
- [ ] Ánh sáng yếu → log cảnh báo, không crash
- [ ] Camera bị ngắt → reconnect tự động

### 6.4 Tinh chỉnh ngưỡng
- [ ] Điều chỉnh `MIN_CONFIDENCE` dựa trên kết quả thực tế
- [ ] Điều chỉnh `COOLDOWN_SECONDS` theo nhu cầu thực tế
- [ ] Test với nhiều người, nhiều điều kiện ánh sáng

---

## Tổng Quan Tiến Độ

| Sprint | Nội dung | Trạng thái |
|---|---|---|
| Sprint 1 | Cài đặt & Test cơ bản | ⬜ Cần hoàn thành |
| Sprint 2 | Nhận diện cục bộ | ⬜ Chưa bắt đầu |
| Sprint 3 | Kết nối API Laravel | ⬜ Chưa bắt đầu |
| Sprint 4 | Offline buffer & sync | ⬜ Chưa bắt đầu |
| Sprint 5 | Tối ưu & Hiển thị | ⬜ Chưa bắt đầu |
| Sprint 6 | Auto-start & Deploy | ⬜ Chưa bắt đầu |

---

## Files Cần Tạo/Cập Nhật Theo Sprint

```
pi4/
├── main.py                ← Sprint 3 (update), Sprint 5 (headless)
├── face_recognizer.py     ← Sprint 2 (load_from_file), Sprint 3 (load_encodings)
├── camera.py              ← Sprint 1
├── api_client.py          ← Sprint 3
├── local_storage.py       ← Sprint 1 (test), Sprint 4 (verify)
├── sync_manager.py        ← Sprint 4
├── config.py              ← Sprint 1
├── encode_local.py        ← Sprint 2 (tạo mới)
├── test_camera.py         ← Sprint 1 (tạo mới)
├── test_face.py           ← Sprint 1 (tạo mới)
├── attendance.service     ← Sprint 6 (tạo mới)
├── requirements.txt       ← Sprint 1
└── .env                   ← Sprint 1
```
