# Sprint 1 — Cài đặt môi trường & Test cơ bản (Pi4)

## Mục tiêu
Cài đặt đủ thư viện, xác nhận camera đọc được frame, và test nhận diện khuôn mặt cơ bản trên máy dev trước khi chạy trên Pi thật.

---

## Checklist nhiệm vụ

### 1. Chuẩn bị môi trường Python

**Yêu cầu:** Python 3.9+ (khuyến nghị 3.11)

```bash
# Kiểm tra phiên bản
python --version

# Tạo virtual environment
python -m venv venv

# Kích hoạt (Windows)
venv\Scripts\activate

# Kích hoạt (Linux/Pi)
source venv/bin/activate
```

---

### 2. Cài thư viện

```bash
pip install -r requirements.txt
```

**Lưu ý khi cài trên Windows (máy dev):**
`face_recognition` phụ thuộc vào `dlib` — cần C++ build tools.

Nếu bị lỗi khi cài `dlib`:
```bash
# Cách 1: Cài dlib pre-built wheel
pip install dlib‑19.24.1‑cp311‑cp311‑win_amd64.whl
# (tải từ: https://github.com/z-mahmud22/Dlib_Windows_Python3.x)

# Cách 2: Dùng conda
conda install -c conda-forge dlib
```

**Trên Raspberry Pi 4 (Raspberry Pi OS):**
```bash
sudo apt-get update
sudo apt-get install -y build-essential cmake libopenblas-dev liblapack-dev libx11-dev
pip install face_recognition opencv-python numpy requests python-dotenv
```

---

### 3. Cấu hình `.env`

```bash
cp .env.example .env
```

Chỉnh sửa `.env` cho máy dev:
```env
SERVER_URL=http://127.0.0.1:8000    # Laravel đang chạy local
DEVICE_TOKEN=test_token_sprint1     # token tạm, chưa cần thật
MIN_CONFIDENCE=0.50
COOLDOWN_SECONDS=10                 # giảm xuống để test nhanh
CAMERA_INDEX=0
FRAME_WIDTH=640
FRAME_HEIGHT=480
PROCESS_EVERY_N=3
PING_INTERVAL_SECONDS=60
DB_PATH=local_attendance.db
```

---

### 4. Test camera

Tạo file `test_camera.py` để kiểm tra camera hoạt động:

```python
import cv2

cap = cv2.VideoCapture(0)

if not cap.isOpened():
    print("Không mở được camera!")
else:
    print("Camera OK")
    while True:
        ret, frame = cap.read()
        if ret:
            cv2.imshow("Test Camera", frame)
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

cap.release()
cv2.destroyAllWindows()
```

```bash
python test_camera.py
```

**Kết quả kỳ vọng:** Cửa sổ hiện ra, thấy được hình từ webcam. Nhấn `q` để thoát.

**Nếu không thấy camera:**
- Windows: Kiểm tra Device Manager → Cameras
- Thử `CAMERA_INDEX=1` hoặc `2` trong `.env`

---

### 5. Chuẩn bị ảnh mẫu để test nhận diện

Tạo thư mục `test_images/` và thêm ít nhất 2 ảnh khuôn mặt:

```
pi4/
└── test_images/
    ├── nguoi_1.jpg   ← ảnh rõ mặt, đủ ánh sáng
    └── nguoi_2.jpg
```

**Yêu cầu ảnh:**
- Chụp thẳng mặt, đủ sáng
- Kích thước tối thiểu 200×200px
- Định dạng JPG hoặc PNG

---

### 6. Test face_recognition cơ bản

Tạo file `test_face.py`:

```python
import face_recognition
import cv2
import numpy as np

# Load ảnh mẫu và tính encoding
img_1 = face_recognition.load_image_file("test_images/nguoi_1.jpg")
enc_1 = face_recognition.face_encodings(img_1)

if not enc_1:
    print("Không tìm thấy khuôn mặt trong nguoi_1.jpg")
    exit()

known_encodings = [enc_1[0]]
known_names     = ["Nguoi 1"]

print(f"Đã load {len(known_encodings)} encoding")

# Mở camera và nhận diện real-time
cap = cv2.VideoCapture(0)

while True:
    ret, frame = cap.read()
    if not ret:
        break

    # Resize 1/2 để tăng tốc
    small = frame[::2, ::2]
    rgb   = cv2.cvtColor(small, cv2.COLOR_BGR2RGB)

    locations = face_recognition.face_locations(rgb)
    encodings = face_recognition.face_encodings(rgb, locations)

    for enc, (top, right, bottom, left) in zip(encodings, locations):
        distances = face_recognition.face_distance(known_encodings, enc)
        best      = int(np.argmin(distances))
        name      = known_names[best] if distances[best] < 0.5 else "Unknown"
        conf      = round((1 - distances[best]) * 100, 1)

        # Scale về kích thước gốc
        top *= 2; right *= 2; bottom *= 2; left *= 2

        cv2.rectangle(frame, (left, top), (right, bottom), (0, 255, 0), 2)
        cv2.putText(frame, f"{name} {conf}%", (left, top - 8),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2)

    cv2.imshow("Test Face Recognition", frame)
    if cv2.waitKey(1) & 0xFF == ord('q'):
        break

cap.release()
cv2.destroyAllWindows()
```

```bash
python test_face.py
```

**Kết quả kỳ vọng:** Camera mở ra, khuôn mặt được khoanh vùng và hiển thị tên + % confidence.

---

### 7. Đo hiệu năng

Thêm đoạn đo FPS vào `test_face.py` để biết Pi 4 xử lý được bao nhiêu frame/giây:

```python
import time

prev_time = time.time()
fps_list  = []

# Trong vòng lặp while, sau khi xử lý frame:
curr_time = time.time()
fps = 1 / (curr_time - prev_time)
fps_list.append(fps)
prev_time = curr_time

cv2.putText(frame, f"FPS: {fps:.1f}", (10, 30),
            cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 0), 2)

# Sau khi thoát vòng lặp:
print(f"FPS trung bình: {sum(fps_list)/len(fps_list):.1f}")
```

**Mục tiêu:** ≥ 3 FPS trên Pi 4 với `PROCESS_EVERY_N=3`

---

### 8. Test local_storage (SQLite offline buffer)

```bash
python -c "
import local_storage
local_storage.init_db()
local_storage.save_record(1, 'check_in', 0.92, None, '2025-05-03 08:05:00')
records = local_storage.get_unsynced()
print('Pending records:', records)
local_storage.mark_synced([records[0]['_local_id']])
print('Sau khi mark synced:', local_storage.get_unsynced())
"
```

**Kết quả kỳ vọng:**
```
Pending records: [{'_local_id': 1, 'user_id': 1, 'type': 'check_in', ...}]
Sau khi mark synced: []
```

---

## Kết quả kỳ vọng khi hoàn thành Sprint 1

- [ ] `pip install -r requirements.txt` thành công, không lỗi
- [ ] `test_camera.py` mở được camera, hiển thị video
- [ ] `test_face.py` nhận diện được khuôn mặt với confidence > 80%
- [ ] FPS đo được ≥ 3 trên máy dev (Pi 4 thường thấp hơn ~30%)
- [ ] SQLite tạo được, lưu và đọc record không lỗi
- [ ] File `.env` đã được cấu hình đúng

---

## Ghi chú kỹ thuật

> **`model='hog'` vs `model='cnn'`:**
> Code dùng `model='hog'` trong `face_recognizer.py` — nhanh hơn, phù hợp Pi 4.
> `model='cnn'` chính xác hơn nhưng cần GPU, không dùng cho Pi.

> **Resize 1/2 trong test vs 1/4 trong production:**
> Test dùng resize 1/2 để dễ thấy kết quả hơn.
> `face_recognizer.py` trong production dùng `[::2, ::2]` (1/4) để tăng tốc tối đa.

> **`COOLDOWN_SECONDS=10` khi test:**
> Giảm xuống 10 giây để test nhanh, thực tế set 300 giây (5 phút).

> **Trên Pi 4 thật:**
> Kết nối Pi vào cùng mạng LAN với máy chạy Laravel.
> Đổi `SERVER_URL` trong `.env` thành IP máy host, ví dụ `http://192.168.1.x:8000`.
