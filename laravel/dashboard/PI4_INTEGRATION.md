# Tích Hợp Raspberry Pi 4 — Hệ Thống Chấm Công Khuôn Mặt

## Tổng Quan Kiến Trúc

```
┌─────────────────────────────────────────────────────────────────┐
│                        RASPBERRY PI 4                           │
│                                                                 │
│   Camera  →  face_recognition  →  So khớp encoding  →  API     │
│                                        ↕                       │
│                               face_encodings.pkl               │
│                               (local cache)                    │
└─────────────────────────┬───────────────────────────────────────┘
                          │  HTTP  (Bearer Token)
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│                     LARAVEL SERVER                              │
│                                                                 │
│   /api/auth/device   →  Đăng ký online                         │
│   /api/encodings     →  Trả face encoding (delta sync)         │
│   /api/attendance    →  Ghi nhận chấm công                     │
│   /api/attendance/batch → Gửi batch offline                    │
│   /api/device/ping   →  Heartbeat                              │
│                                                                 │
│   Dashboard (Blade) ←→  DB (SQLite/MySQL)                       │
└─────────────────────────────────────────────────────────────────┘
```

---

## 1. Cấu Hình Kết Nối

### 1.1 Tạo thiết bị trên Dashboard

1. Đăng nhập với role **admin** hoặc **super_admin**
2. Vào **Thiết Bị** → **Thêm thiết bị mới**
3. Điền tên và vị trí → nhấn **Thêm thiết bị**
4. **Sao chép token** ngay lập tức (chỉ hiển thị một lần)

### 1.2 Cấu hình trên Pi4

Dán token và địa chỉ server vào `config.py` trên Pi:

```python
# config.py
SERVER_URL   = "http://<ip-server>:8000"   # hoặc domain nếu có
DEVICE_TOKEN = "paste-token-here"

CONFIDENCE_THRESHOLD = 0.55   # ngưỡng nhận diện (0–1)
SYNC_INTERVAL        = 300    # giây giữa các lần tải encoding
PING_INTERVAL        = 60     # giây giữa các heartbeat
```

---

## 2. Luồng Khởi Động Pi4

```
Pi4 boot
  │
  ├─ 1. POST /api/auth/device
  │       Header: Authorization: Bearer <token>
  │       → server trả {id, name, location, status}
  │       → device.status = "online", last_ping = now()
  │
  ├─ 2. GET /api/encodings
  │       → tải toàn bộ face encoding về RAM / file cache
  │       → mỗi record: {user_id, name, code, encoding: [128 float]}
  │
  └─ 3. Vào vòng lặp chính (xem mục 3)
```

---

## 3. Vòng Lặp Chính — Nhận Diện & Chấm Công

```
Vòng lặp (mỗi frame camera):
  │
  ├─ Detect khuôn mặt trong frame
  │
  ├─ Nếu phát hiện mặt:
  │   ├─ So khớp với encoding cache (cosine/euclidean distance)
  │   ├─ Nếu confidence >= threshold:
  │   │   ├─ Xác định type: check_in (buổi sáng) hoặc check_out (chiều)
  │   │   └─ POST /api/attendance  ──────────────────────────────────┐
  │   └─ Nếu không khớp: bỏ qua / ghi log unknown                   │
  │                                                                  ▼
  │                                               Server nhận:
  │                                               - Tìm/tạo Attendance (1 ngày)
  │                                               - Tính status (present/late/early_leave)
  │                                               - Lưu ảnh base64 → storage/
  │                                               - Trả {id, work_date, status}
  │
  ├─ Mỗi SYNC_INTERVAL giây:
  │   └─ GET /api/encodings?updated_since=<unix_ts>  (delta sync)
  │       → chỉ tải encoding mới hơn lần sync trước
  │       → merge vào cache
  │
  └─ Mỗi PING_INTERVAL giây:
      └─ POST /api/device/ping
          → server: last_ping = now(), status = "online"
          → trả {server_ts} để Pi đồng bộ giờ nếu cần
```

---

## 4. API Reference

Tất cả request dùng header:
```
Authorization: Bearer <device_token>
Content-Type: application/json
```

### `POST /api/auth/device`
Đăng ký online khi Pi khởi động.

**Response:**
```json
{ "id": 1, "name": "Pi4 - Cổng Chính", "location": "Lobby", "status": "online" }
```

---

### `GET /api/encodings`
Tải face encoding của toàn bộ nhân viên.

| Query param | Mô tả |
|---|---|
| `updated_since` | Unix timestamp — chỉ trả encoding tạo sau mốc này (delta sync) |

**Response:**
```json
[
  {
    "id": 5,
    "user_id": 12,
    "name": "Nguyễn Văn A",
    "code": "NV001",
    "encoding": [0.123, -0.456, ...]   // mảng 128 float (face_recognition lib)
  }
]
```

---

### `POST /api/attendance`
Ghi một lượt chấm công.

**Body:**
```json
{
  "user_id":     12,
  "type":        "check_in",           // hoặc "check_out"
  "confidence":  0.87,                 // 0.0 – 1.0
  "recorded_at": 1746270000,           // unix timestamp hoặc "2025-05-03 08:01:00"
  "image":       "<base64 JPEG>"       // tuỳ chọn, để null nếu không cần lưu ảnh
}
```

**Response `201`:**
```json
{ "id": 99, "work_date": "2025-05-03", "status": "present" }
```

**Quy tắc trùng lặp:** Server dùng `firstOrCreate(user_id, work_date)` — nếu đã check-in rồi thì bỏ qua lần check-in thứ hai trong ngày (tương tự check-out).

---

### `POST /api/attendance/batch`
Gửi nhiều lượt chấm công cùng lúc (dùng khi Pi mất mạng và cần sync lại).

**Body:**
```json
{
  "records": [
    { "user_id": 12, "type": "check_in",  "confidence": 0.9, "recorded_at": 1746270000 },
    { "user_id": 13, "type": "check_out", "confidence": 0.8, "recorded_at": 1746295200 }
  ]
}
```

Giới hạn tối đa **500 records** mỗi request.

**Response `201`:**
```json
{ "saved": 2, "skipped": 0, "errors": {} }
```

---

### `POST /api/device/ping`
Heartbeat để dashboard hiển thị trạng thái online.

**Response:**
```json
{ "message": "pong", "server_ts": 1746270060 }
```

Thiết bị được coi là **online** nếu `last_ping` trong vòng **5 phút** gần nhất.

---

## 5. Logic Tính Trạng Thái

Server tự động tính status dựa trên cấu hình phòng ban của nhân viên:

| Trường hợp | Status |
|---|---|
| Check-in ≤ `check_in_time` + `late_tolerance` phút | `present` |
| Check-in > `check_in_time` + `late_tolerance` phút | `late` |
| Check-out < `check_out_time` của phòng ban | `early_leave` |
| Check-out đúng giờ / muộn | Giữ nguyên status check-in |
| Nhân viên không có phòng ban | Luôn `present` |
| Không chấm công cả ngày (job 23:59) | `absent` |

Cấu hình `check_in_time`, `check_out_time`, `late_tolerance` được set trong **Phòng Ban** trên dashboard.

---

## 6. Luồng Đăng Ký Khuôn Mặt (Upload từ Dashboard)

```
Admin upload ảnh nhân viên (dashboard)
  │
  ├─ POST /employees/{id}/face  (multipart/form-data)
  │
  ├─ File lưu vào: storage/app/faces/{user_id}/...
  │
  ├─ Dispatch EncodeFaceJob (queue)
  │   └─ Gọi Python: python ../pi4/face_encode_single.py "<path>"
  │       → Output JSON: {"encoding": [128 float]}
  │       → Lưu vào bảng face_encodings
  │
  └─ Pi sync encoding mới qua GET /api/encodings?updated_since=<ts>
      tại lần SYNC_INTERVAL tiếp theo
```

> **Lưu ý:** Queue driver mặc định dùng `database`. Cần chạy `php artisan queue:work` trên server để job được xử lý.

---

## 7. Xử Lý Offline

Khi Pi mất kết nối internet:

1. Pi tiếp tục nhận diện và **ghi log local** (file hoặc SQLite)
2. Khi kết nối lại → gọi `POST /api/attendance/batch` với toàn bộ records chờ
3. Server xử lý từng record, bỏ qua record lỗi validation, trả về `saved/skipped`

---

## 8. Checklist Vận Hành

```
[ ] php artisan queue:work  (hoặc supervisor) đang chạy trên server
[ ] php artisan storage:link  đã chạy (để ảnh chấm công hiển thị)
[ ] Pi4 có thể reach SERVER_URL (kiểm tra firewall / port)
[ ] Token đã được copy đúng vào config.py
[ ] Giờ hệ thống Pi4 và server đồng bộ (NTP) — ảnh hưởng tính trạng thái
[ ] face_encode_single.py nằm đúng tại ../pi4/ so với thư mục dashboard
```
