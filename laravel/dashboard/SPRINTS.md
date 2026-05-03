# Dashboard — Kế Hoạch Sprint Toàn Bộ

> **Stack:** Laravel 13 · PHP 8.2+ · MySQL 8 · Blade · TailwindCSS · Alpine.js · Chart.js
> **Tài khoản test mặc định:** `admin@attendance.com / password`

---

## Sprint 1 — Nền tảng DB & Models ✅

### 1.1 Database
- [x] Xóa migration `users` mặc định của Laravel
- [x] Tạo migration `departments` (check_in_time, check_out_time, late_tolerance)
- [x] Tạo migration `users` (code, role, department_id, soft delete)
- [x] Tạo migration `face_encodings`
- [x] Tạo migration `devices`
- [x] Tạo migration `attendances` (check_in + check_out, unique user_id+work_date)
- [x] Tạo bảng `sessions` bị thiếu
- [x] Chạy `php artisan migrate:fresh` thành công

### 1.2 Models
- [x] `User.php` — SoftDeletes, HasApiTokens, relationships
- [x] `Department.php` — manager, employees relationships
- [x] `FaceEncoding.php` — no timestamps, encoding cast array
- [x] `Attendance.php` — check_in/check_out casts
- [x] `Device.php` — last_ping cast datetime

### 1.3 Seed dữ liệu
- [x] `DepartmentSeeder` — 3 phòng ban mẫu
- [x] `UserSeeder` — admin, manager, nhân viên
- [x] `DatabaseSeeder` gọi đúng thứ tự

### 1.4 Packages
- [x] Cài `laravel/sanctum` (API auth cho Pi)
- [x] Cài `laravel/breeze` (web auth)

### Lệnh
```bash
php artisan migrate:fresh --seed
php artisan serve
```

---

## Sprint 2 — Quản Lý Nhân Viên & Phòng Ban

### 2.1 Routes & Controllers
- [ ] Tạo `Web/EmployeeController` với các method: `index`, `create`, `store`, `edit`, `update`, `destroy`
- [ ] Tạo `Web/DepartmentController` với các method: `index`, `create`, `store`, `edit`, `update`, `destroy`
- [ ] Đăng ký routes trong `routes/web.php`
- [ ] Tạo middleware kiểm tra role `admin` cho các route quản lý

### 2.2 Blade Views — Layout chung
- [ ] Tạo `resources/views/layouts/app.blade.php`
  - [ ] Sidebar navigation (Dashboard, Nhân viên, Phòng ban, Chấm công, Báo cáo, Thiết bị)
  - [ ] Header (tên user, logout)
  - [ ] TailwindCSS + Alpine.js CDN hoặc qua Vite
- [ ] Tạo `resources/views/components/alert.blade.php` (success/error flash)
- [ ] Tạo `resources/views/components/pagination.blade.php`

### 2.3 Blade Views — Nhân Viên
- [ ] `employees/index.blade.php` — bảng danh sách + search + filter phòng ban
- [ ] `employees/create.blade.php` — form thêm mới (name, email, code, role, department, password)
- [ ] `employees/edit.blade.php` — form chỉnh sửa + upload avatar
- [ ] Xác nhận xóa bằng Alpine.js modal

### 2.4 Blade Views — Phòng Ban
- [ ] `departments/index.blade.php` — bảng danh sách + số nhân viên
- [ ] `departments/create.blade.php` — form thêm (name, check_in_time, check_out_time, late_tolerance)
- [ ] `departments/edit.blade.php`

### 2.5 Upload Ảnh Khuôn Mặt
- [ ] Thêm method `uploadFace` vào `EmployeeController`
- [ ] Tạo route `POST /employees/{employee}/face`
- [ ] Lưu ảnh vào `storage/app/faces/`
- [ ] Tạo `Jobs/EncodeFaceJob` — gọi Python script encode
- [ ] Dispatch job vào queue sau khi upload
- [ ] Tạo `Services/AttendanceStatusService.php`

### 2.6 Import Excel (tuỳ chọn — nếu còn thời gian)
- [ ] Cài `maatwebsite/excel`
- [ ] Tạo `Imports/EmployeeImport.php`
- [ ] Route `POST /employees/import`

### Lệnh
```bash
php artisan make:controller Web/EmployeeController --resource
php artisan make:controller Web/DepartmentController --resource
php artisan make:job EncodeFaceJob
php artisan queue:work --tries=3
```

### Kết quả kỳ vọng
- Thêm/sửa/xóa nhân viên được
- Thêm/sửa/xóa phòng ban được
- Upload ảnh khuôn mặt → lưu vào storage
- Queue job được dispatch (kiểm tra bảng `jobs` trong DB)

---

## Sprint 3 — API cho Pi (quan trọng nhất)

### 3.1 Auth Device
- [ ] Tạo `Api/AuthDeviceController`
  - [ ] `POST /api/auth/device` — nhận token → trả device info
  - [ ] Cập nhật `status = online`, `last_ping = now()`
- [ ] Tạo middleware `DeviceTokenMiddleware` kiểm tra token trong header

### 3.2 Encoding Sync
- [ ] Tạo `Api/EncodingController`
  - [ ] `GET /api/encodings` — trả danh sách encoding
  - [ ] Hỗ trợ `?updated_since=<unix_timestamp>` (delta sync)
  - [ ] Chỉ trả `id, user_id, name, code, encoding`

### 3.3 Attendance API
- [ ] Tạo `Api/AttendanceApiController`
  - [ ] `POST /api/attendance` — nhận check_in hoặc check_out
    - [ ] Validate: user_id, type, confidence, recorded_at
    - [ ] `firstOrCreate` attendance theo (user_id, work_date)
    - [ ] Gọi `AttendanceStatusService` tính trạng thái (present/late)
    - [ ] Lưu ảnh base64 vào storage
  - [ ] `POST /api/attendance/batch` — sync offline hàng loạt
    - [ ] Xử lý tối đa 500 records/request
    - [ ] Bỏ qua record lỗi, tiếp tục batch

### 3.4 Device Ping
- [ ] Tạo `Api/DevicePingController`
  - [ ] `POST /api/device/ping` — cập nhật last_ping + status online

### 3.5 Routes API
- [ ] Đăng ký đầy đủ trong `routes/api.php`
- [ ] Group middleware `auth:sanctum` cho các route cần token

### 3.6 Service tính trạng thái
- [ ] Tạo `Services/AttendanceStatusService`
  - [ ] `calculateStatus()` — so sánh giờ check-in với department.check_in_time + late_tolerance
  - [ ] Trả về: `present` | `late`

### 3.7 Test API
- [ ] Tạo device trong DB (thêm seeder hoặc manual)
- [ ] Test bằng Postman/Hoppscotch:
  - [ ] `POST /api/auth/device` với token
  - [ ] `GET /api/encodings`
  - [ ] `POST /api/attendance` với payload mẫu
  - [ ] `POST /api/device/ping`

### Lệnh
```bash
php artisan make:controller Api/AuthDeviceController
php artisan make:controller Api/EncodingController
php artisan make:controller Api/AttendanceApiController
php artisan make:controller Api/DevicePingController
php artisan make:middleware DeviceTokenMiddleware
php artisan make:service AttendanceStatusService
```

### Kết quả kỳ vọng
- Pi gửi POST → Laravel lưu vào bảng `attendances`
- Delta sync hoạt động (chỉ tải encoding mới)
- Batch sync không crash khi có record lỗi

---

## Sprint 4 — Giao Diện Chấm Công

### 4.1 Danh Sách Chấm Công
- [ ] Tạo `Web/AttendanceController`
  - [ ] `index` — danh sách có filter
  - [ ] `show` — chi tiết 1 bản ghi
  - [ ] `update` — override thủ công
- [ ] `attendances/index.blade.php`
  - [ ] Filter: ngày, phòng ban, nhân viên, trạng thái
  - [ ] Bảng: avatar, tên, phòng ban, check-in, check-out, trạng thái (badge màu), confidence
  - [ ] Phân trang 20 records/trang
- [ ] `attendances/show.blade.php`
  - [ ] Ảnh check-in + check-out
  - [ ] Thông tin nhận diện (confidence %)
  - [ ] Form override: chọn trạng thái, nhập ghi chú

### 4.2 Badge Trạng Thái
- [ ] `present` → badge xanh
- [ ] `late` → badge vàng
- [ ] `absent` → badge đỏ
- [ ] `early_leave` → badge cam
- [ ] `leave` → badge xám

### 4.3 Auto-tạo bản ghi vắng hàng ngày (tuỳ chọn)
- [ ] Tạo `Commands/GenerateDailyAbsences`
- [ ] Scheduled task chạy 23:59 mỗi ngày
- [ ] Tạo record `absent` cho nhân viên chưa có attendance trong ngày

### Lệnh
```bash
php artisan make:controller Web/AttendanceController
```

### Kết quả kỳ vọng
- Xem được danh sách chấm công với filter
- Click vào record xem ảnh nhận diện
- Admin/Manager override được trạng thái

---

## Sprint 5 — Dashboard & Báo Cáo

### 5.1 Trang Dashboard
- [ ] Tạo `Web/DashboardController`
- [ ] `dashboard/index.blade.php`
  - [ ] Thẻ thống kê: tổng nhân viên, số người đi làm hôm nay, số người vắng, số người đi trễ
  - [ ] Biểu đồ đường: tỉ lệ chấm công 7 ngày gần nhất (Chart.js)
  - [ ] Bảng: 10 check-in gần nhất (real-time polling mỗi 10 giây)
  - [ ] Cảnh báo: thiết bị Pi offline (last_ping > 5 phút)

### 5.2 Polling Real-time
- [ ] Route `GET /api/dashboard/recent` trả JSON 10 check-in mới nhất
- [ ] Alpine.js gọi fetch mỗi 10 giây cập nhật bảng (không reload trang)

### 5.3 Báo Cáo
- [ ] Tạo `Web/ReportController`
- [ ] `reports/index.blade.php`
  - [ ] Filter: phòng ban, tháng/năm
  - [ ] Bảng tổng hợp: nhân viên | số ngày đi làm | số ngày vắng | số ngày trễ | tỉ lệ %
  - [ ] Biểu đồ cột theo phòng ban (Chart.js)
- [ ] Export Excel: cài `maatwebsite/excel`, tạo `Exports/AttendanceExport`
- [ ] Export PDF: cài `barryvdh/laravel-dompdf`, tạo Blade PDF template

### 5.4 Quản Lý Thiết Bị
- [ ] Tạo `Web/DeviceController`
- [ ] `devices/index.blade.php`
  - [ ] Danh sách thiết bị: tên, vị trí, trạng thái (online/offline badge), last_ping
  - [ ] Form thêm thiết bị mới (auto-generate token)
  - [ ] Nút xóa thiết bị

### Lệnh
```bash
php artisan make:controller Web/DashboardController
php artisan make:controller Web/ReportController
php artisan make:controller Web/DeviceController
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf
```

### Kết quả kỳ vọng
- Dashboard hiển thị số liệu thực từ DB
- Chart tỉ lệ chấm công hiển thị đúng
- Export Excel ra file đúng dữ liệu

---

## Sprint 6 — Phân Quyền & Hoàn Thiện

### 6.1 Phân Quyền Role
- [ ] Tạo middleware `RoleMiddleware` kiểm tra role
- [ ] Đăng ký trong `bootstrap/app.php`
- [ ] Áp dụng:
  - [ ] `super_admin`, `admin` → toàn quyền
  - [ ] `manager` → chỉ xem phòng ban của mình, override chấm công phòng ban
  - [ ] `employee` → chỉ xem chấm công cá nhân

### 6.2 Trang Profile Nhân Viên
- [ ] Route `GET /profile`
- [ ] Xem lịch sử chấm công cá nhân theo tháng
- [ ] Thống kê: số ngày đi làm, số ngày vắng, số ngày trễ trong tháng

### 6.3 UI Polish
- [ ] Responsive mobile cho các trang chính
- [ ] Loading spinner khi submit form
- [ ] Confirm dialog trước khi xóa (Alpine.js)
- [ ] Flash message success/error nhất quán
- [ ] Favicon + tên app

### 6.4 Bảo Mật
- [ ] Rate limiting cho API routes (`throttle:60,1`)
- [ ] Validate kích thước & định dạng ảnh upload
- [ ] Ảnh lưu ngoài `public/` (dùng `storage:link`)
- [ ] CSRF protection kiểm tra

### 6.5 Testing
- [ ] Test đăng nhập với từng role
- [ ] Test API với Postman (tất cả endpoints)
- [ ] Test upload ảnh
- [ ] Test export Excel/PDF
- [ ] Test trường hợp Pi offline → sync lại

### 6.6 Deploy (tuỳ chọn)
- [ ] Cấu hình `.env` production
- [ ] `php artisan optimize`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] Setup Nginx + PHP-FPM trên VPS

### Lệnh
```bash
php artisan make:middleware RoleMiddleware
php artisan optimize
```

---

## Tổng Quan Tiến Độ

| Sprint | Nội dung | Trạng thái |
|---|---|---|
| Sprint 1 | DB, Models, Seed | ✅ Hoàn thành |
| Sprint 2 | Employee & Department CRUD | ⬜ Chưa bắt đầu |
| Sprint 3 | API cho Pi | ⬜ Chưa bắt đầu |
| Sprint 4 | Giao diện Chấm công | ⬜ Chưa bắt đầu |
| Sprint 5 | Dashboard & Báo cáo | ⬜ Chưa bắt đầu |
| Sprint 6 | Phân quyền & Hoàn thiện | ⬜ Chưa bắt đầu |

---

## Files Cần Tạo Theo Sprint

```
dashboard/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Web/
│   │   │   │   ├── DashboardController.php     ← Sprint 5
│   │   │   │   ├── EmployeeController.php      ← Sprint 2
│   │   │   │   ├── DepartmentController.php    ← Sprint 2
│   │   │   │   ├── AttendanceController.php    ← Sprint 4
│   │   │   │   ├── DeviceController.php        ← Sprint 5
│   │   │   │   └── ReportController.php        ← Sprint 5
│   │   │   └── Api/
│   │   │       ├── AuthDeviceController.php    ← Sprint 3
│   │   │       ├── EncodingController.php      ← Sprint 3
│   │   │       ├── AttendanceApiController.php ← Sprint 3
│   │   │       └── DevicePingController.php    ← Sprint 3
│   │   └── Middleware/
│   │       ├── DeviceTokenMiddleware.php       ← Sprint 3
│   │       └── RoleMiddleware.php              ← Sprint 6
│   ├── Jobs/
│   │   └── EncodeFaceJob.php                  ← Sprint 2
│   └── Services/
│       └── AttendanceStatusService.php         ← Sprint 3
├── resources/views/
│   ├── layouts/app.blade.php                  ← Sprint 2
│   ├── dashboard/index.blade.php              ← Sprint 5
│   ├── employees/{index,create,edit}.blade.php← Sprint 2
│   ├── departments/{index,create,edit}.blade.php← Sprint 2
│   ├── attendances/{index,show}.blade.php     ← Sprint 4
│   ├── devices/index.blade.php                ← Sprint 5
│   └── reports/index.blade.php                ← Sprint 5
└── routes/
    ├── web.php                                ← Sprint 2
    └── api.php                                ← Sprint 3
```
