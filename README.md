# Đề Xuất Tổng Quan Hệ Thống Điểm Danh Bằng Nhận Diện Khuôn Mặt

> **Đề tài:** Hệ thống điểm danh tự động sử dụng Raspberry Pi 4 và camera nhận diện khuôn mặt, tích hợp giao diện web quản lý.

---

## 1. Mục Tiêu Hệ Thống

- Tự động nhận diện khuôn mặt và ghi nhận điểm danh theo thời gian thực
- Cung cấp giao diện web để quản lý người dùng, lớp học, và dữ liệu điểm danh
- Hỗ trợ báo cáo thống kê, xuất dữ liệu và phân quyền người dùng
- Đảm bảo hệ thống hoạt động ổn định, bảo mật và có khả năng mở rộng

---

## 2. Kiến Trúc Tổng Quan

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                         │
│          Browser (Admin / Giáo viên / Học sinh)             │
└────────────────────────┬────────────────────────────────────┘
                         │ HTTP / WebSocket
┌────────────────────────▼────────────────────────────────────┐
│                      WEB APPLICATION                        │
│                  Laravel (PHP Framework)                    │
│   - REST API          - Blade Views         - Auth/ACL      │
│   - Queue Jobs        - WebSocket Server    - File Upload   │
└────────┬──────────────────────────────┬─────────────────────┘
         │ Eloquent ORM                 │ REST API (JSON)
┌────────▼───────────┐       ┌──────────▼───────────────────┐
│    MySQL Database  │       │      Raspberry Pi 4           │
│  - users           │       │  Python + OpenCV              │
│  - attendances     │       │  face_recognition library     │
│  - classes         │       │  Camera Module v2             │
│  - sessions        │       │  Auto-sync khi có mạng        │
│  - face_encodings  │       └──────────────────────────────┘
└────────────────────┘
```

---

## 3. Stack Công Nghệ

### 3.1 Backend (Web Server)

| Thành phần | Công nghệ | Lý do chọn |
|---|---|---|
| Framework | Laravel 11 | Quen thuộc, ecosystem phong phú, có Sanctum/Passport cho API auth |
| Ngôn ngữ | PHP 8.2+ | Hỗ trợ tốt các tính năng OOP hiện đại |
| Database | MySQL 8.0 | Ổn định, dễ quản lý, phù hợp dữ liệu quan hệ |
| Queue | Laravel Queue + Redis | Xử lý tác vụ nền (gửi email, sync dữ liệu) |
| Real-time | Laravel Echo + Pusher/Soketi | Cập nhật điểm danh thời gian thực |
| Storage | Laravel Storage (local/S3) | Lưu ảnh khuôn mặt |

### 3.2 Frontend (Giao diện Web)

| Thành phần | Công nghệ |
|---|---|
| Template engine | Blade (Laravel) |
| CSS Framework | TailwindCSS |
| JS Framework | Alpine.js (tương tác nhẹ) hoặc Vue.js (nếu cần SPA) |
| Charts | Chart.js hoặc ApexCharts |
| DataTable | DataTables.js |
| Icons | Heroicons / FontAwesome |

### 3.3 Raspberry Pi 4 (Edge Device)

| Thành phần | Công nghệ |
|---|---|
| Ngôn ngữ | Python 3.9+ |
| Nhận diện khuôn mặt | `face_recognition` (dlib) + OpenCV |
| Camera | Raspberry Pi Camera Module v2 / USB Camera |
| Giao tiếp server | `requests` (HTTP POST lên Laravel API) |
| Lưu trữ cục bộ | SQLite (offline buffer khi mất mạng) |
| Màn hình (tuỳ chọn) | HDMI display hiển thị kết quả nhận diện |

---

## 4. Các Module Chức Năng

### 4.1 Dashboard (Trang Tổng Quan)
- Thống kê tổng số người, số lớp, số điểm danh trong ngày
- Biểu đồ điểm danh theo tuần/tháng
- Danh sách điểm danh gần đây (real-time)
- Cảnh báo: thiết bị Pi offline, tỉ lệ vắng cao

### 4.2 Quản Lý Người Dùng
- **CRUD:** Thêm, sửa, xóa sinh viên / nhân viên
- **Upload ảnh khuôn mặt:** Tải lên 1 hoặc nhiều ảnh, hệ thống tự mã hóa face encoding
- **Import từ Excel/CSV:** Nhập hàng loạt
- **Tìm kiếm & lọc:** Theo tên, mã số, lớp, trạng thái

### 4.3 Quản Lý Lớp / Ca Làm Việc
- Tạo, sửa, xóa lớp học hoặc ca làm việc
- Gán danh sách sinh viên vào lớp
- Thiết lập lịch học (ngày, giờ bắt đầu, giờ kết thúc)
- Gán thiết bị Pi cho từng phòng/lớp

### 4.4 Quản Lý Phiên Điểm Danh
- Tạo phiên điểm danh thủ công hoặc tự động theo lịch
- Xem trạng thái điểm danh theo thời gian thực
- Override thủ công: đánh dấu có mặt / vắng mặt / trễ
- Ghi chú lý do vắng mặt

### 4.5 Lịch Sử Điểm Danh
- Xem lịch sử theo ngày, tuần, tháng
- Lọc theo: người, lớp, trạng thái (có mặt / vắng / trễ)
- Xem chi tiết: thời gian nhận diện, độ chính xác, ảnh chụp lúc điểm danh
- Xuất dữ liệu: Excel, PDF, CSV

### 4.6 Báo Cáo & Thống Kê
- Tỉ lệ điểm danh theo từng người / lớp / khoảng thời gian
- Xếp hạng tỉ lệ vắng mặt
- Báo cáo tổng kết học kỳ / tháng
- Gửi báo cáo qua email tự động (Queue)

### 4.7 Quản Lý Thiết Bị Pi
- Danh sách thiết bị đăng ký (tên, IP, vị trí)
- Trạng thái online/offline (heartbeat API)
- Log nhận diện từ từng thiết bị
- Đồng bộ face encodings xuống Pi

### 4.8 Phân Quyền (Role & Permission)
| Role | Quyền |
|---|---|
| Super Admin | Toàn quyền hệ thống |
| Admin | Quản lý người dùng, lớp, thiết bị |
| Giáo viên | Xem & override điểm danh lớp của mình |
| Học sinh | Xem lịch sử điểm danh cá nhân |

---

## 5. Luồng Hoạt Động Chính

### 5.1 Luồng Điểm Danh Tự Động

```
[Camera Pi4] → Chụp frame liên tục
      ↓
[Python] → Phát hiện khuôn mặt (face_detection)
      ↓
[Python] → So sánh face encoding với database cục bộ
      ↓
[Nhận diện thành công] → POST /api/attendance với {user_id, session_id, confidence, image}
      ↓
[Laravel API] → Xác thực token Pi → Lưu DB → Broadcast WebSocket
      ↓
[Web Client] → Cập nhật real-time không cần refresh
```

### 5.2 Luồng Thêm Người Mới Kèm Ảnh

```
[Admin Web] → Upload ảnh khuôn mặt
      ↓
[Laravel] → Lưu ảnh → Gọi Python service mã hóa face encoding
      ↓
[Python service] → Trả về encoding vector (128 dimensions)
      ↓
[Laravel] → Lưu encoding vào DB
      ↓
[Sync Job] → Đẩy encoding mới xuống tất cả thiết bị Pi qua API
```

### 5.3 Luồng Offline (Mất kết nối mạng)

```
[Pi4 mất mạng] → Tiếp tục nhận diện
      ↓
[Python] → Lưu kết quả vào SQLite local
      ↓
[Khi có mạng trở lại] → Sync toàn bộ dữ liệu tồn đọng lên server
      ↓
[Laravel] → Xử lý duplicate, lưu DB, cập nhật UI
```

---

## 6. Thiết Kế Cơ Sở Dữ Liệu (Sơ lược)

```
users               → id, name, code, role, avatar, class_id, ...
face_encodings      → id, user_id, encoding (JSON/BLOB), created_at
classes             → id, name, description, teacher_id, schedule, ...
attendance_sessions → id, class_id, device_id, started_at, ended_at, ...
attendances         → id, session_id, user_id, status, confidence, image, checked_at
devices             → id, name, location, token, last_ping, ...
```

---

## 7. API Endpoints (Pi4 ↔ Laravel)

| Method | Endpoint | Mô tả |
|---|---|---|
| POST | `/api/auth/device` | Pi đăng nhập lấy token |
| GET | `/api/encodings` | Pi tải face encodings mới nhất |
| POST | `/api/attendance` | Pi gửi kết quả điểm danh |
| POST | `/api/device/ping` | Heartbeat kiểm tra online |
| POST | `/api/attendance/batch` | Sync hàng loạt khi offline |

---

## 8. Bảo Mật

- **API Authentication:** Laravel Sanctum (token-based cho Pi)
- **Web Authentication:** Laravel Auth + Session
- **HTTPS:** Bắt buộc trên môi trường production (VPS + SSL)
- **Rate Limiting:** Giới hạn request từ Pi tránh spam
- **Input Validation:** Validate toàn bộ dữ liệu đầu vào
- **Image Upload:** Kiểm tra định dạng, giới hạn kích thước, lưu ngoài public dir

---

## 9. Môi Trường Triển Khai

### Development
- Local machine + Laravel Sail (Docker)
- Pi4 kết nối qua mạng LAN

### Production
- **VPS:** Ubuntu 22.04, Nginx, PHP-FPM, MySQL, Redis
- **Domain:** HTTPS với Let's Encrypt
- **Pi4:** Kết nối qua IP tĩnh hoặc DDNS

---

## 10. Kế Hoạch Phát Triển Theo Giai Đoạn

| Giai đoạn | Nội dung | Thời gian dự kiến |
|---|---|---|
| Phase 1 | Thiết kế DB, Auth, CRUD người dùng, upload ảnh | Tuần 1-2 |
| Phase 2 | API cho Pi4, module điểm danh, real-time | Tuần 3-4 |
| Phase 3 | Báo cáo, thống kê, export Excel/PDF | Tuần 5 |
| Phase 4 | Phân quyền, quản lý thiết bị, UI hoàn thiện | Tuần 6 |
| Phase 5 | Testing, deploy VPS, viết tài liệu | Tuần 7-8 |

---

## 11. Các Rủi Ro & Giải Pháp

| Rủi ro | Giải pháp |
|---|---|
| Ánh sáng kém ảnh hưởng nhận diện | Cải thiện lighting, dùng IR camera |
| Pi4 mất mạng | Offline buffer với SQLite |
| Nhận diện nhầm (false positive) | Đặt ngưỡng confidence tối thiểu (ví dụ ≥ 85%) |
| Dữ liệu ảnh lớn | Nén ảnh, chỉ lưu thumbnail + encoding vector |
| Hiệu năng Pi4 thấp | Giảm độ phân giải frame, dùng `face_recognition` với model `small` |

---

*Tài liệu này là cơ sở để triển khai từng phần theo yêu cầu. Các mục có thể điều chỉnh theo tiến độ và phản hồi thực tế.*

## SƠ ĐỒ CHỨC NĂNG
![alt text](diagram/sodochucnang1.png)
![alt text](diagram/sodochucnag2.png)

## SƠ ĐỒ USECASE
![alt text](diagram/usecase.png)

##  SEQUENCE
![alt text](diagram/sequence_diagram_diem_danh.svg)

##  activity
![alt text](diagram/activity_diagram_diem_danh.svg)

##  CLASS
![alt text](diagram/class_diagram.svg)