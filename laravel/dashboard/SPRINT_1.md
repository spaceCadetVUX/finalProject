# Sprint 1 — Nền tảng DB & Models (Dashboard)

## Mục tiêu
Thiết lập database schema đúng với thiết kế ERD, đặt Models vào đúng chỗ, verify toàn bộ bảng trên HeidiSQL.

---

## Checklist nhiệm vụ

### 1. Xóa migration mặc định của Laravel
Laravel tạo sẵn `create_users_table` — cần thay bằng version tùy chỉnh của project.

- [ ] Xóa file `database/migrations/0001_01_01_000000_create_users_table.php`
- [ ] Giữ lại `0001_01_01_000001_create_cache_table.php` và `0001_01_01_000002_create_jobs_table.php`

---

### 2. Copy migrations tùy chỉnh vào project

Copy các file sau từ `laravel/database/migrations/` vào `dashboard/database/migrations/`:

| File nguồn | Nội dung |
|---|---|
| `0001_create_departments_table.php` | Bảng phòng ban, giờ làm việc |
| `0002_create_users_table.php` | Bảng nhân viên + soft delete + FK department |
| `0003_create_face_encodings_table.php` | Bảng lưu vector khuôn mặt |
| `0004_create_devices_table.php` | Bảng thiết bị Pi |
| `0005_create_attendances_table.php` | Bảng chấm công check-in/check-out |

**Lưu ý thứ tự:** departments → users → face_encodings → devices → attendances

---

### 3. Copy Models vào project

Copy các file sau từ `laravel/app/Models/` vào `dashboard/app/Models/`:

- [ ] `User.php` — thay thế file mặc định (thêm SoftDeletes, department relationship)
- [ ] `Department.php` — model phòng ban
- [ ] `FaceEncoding.php` — model encoding khuôn mặt
- [ ] `Attendance.php` — model chấm công
- [ ] `Device.php` — model thiết bị Pi

---

### 4. Cài package bổ sung

```bash
# Sanctum cho API auth (Pi device) — đã có sẵn trong Laravel 11+, chỉ cần publish
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

---

### 5. Chạy migrate

```bash
php artisan migrate:fresh
```

> Dùng `migrate:fresh` để reset sạch, xóa toàn bộ bảng cũ và chạy lại từ đầu.

---

### 6. Verify schema trên HeidiSQL

Mở HeidiSQL → `attendance_db` → kiểm tra các bảng sau tồn tại và đúng cột:

| Bảng | Các cột bắt buộc cần kiểm tra |
|---|---|
| `departments` | `check_in_time`, `check_out_time`, `late_tolerance` |
| `users` | `code`, `role`, `department_id`, `deleted_at` |
| `face_encodings` | `user_id`, `encoding` (JSON), `image_path` |
| `devices` | `token`, `last_ping`, `status` |
| `attendances` | `check_in_at`, `check_out_at`, `work_date`, unique(`user_id`,`work_date`) |

---

### 7. Seed dữ liệu test cơ bản

Tạo seeder để có dữ liệu test ngay từ đầu:

```bash
php artisan make:seeder DepartmentSeeder
php artisan make:seeder UserSeeder
```

**DepartmentSeeder** — tạo 2 phòng ban mẫu:
```php
Department::create([
    'name'           => 'Phòng Kỹ Thuật',
    'check_in_time'  => '08:00:00',
    'check_out_time' => '17:00:00',
    'late_tolerance' => 15,
]);
Department::create([
    'name'           => 'Phòng Kinh Doanh',
    'check_in_time'  => '08:30:00',
    'check_out_time' => '17:30:00',
    'late_tolerance' => 10,
]);
```

**UserSeeder** — tạo tài khoản admin:
```php
User::create([
    'name'          => 'Super Admin',
    'email'         => 'admin@attendance.com',
    'code'          => 'AD001',
    'password'      => bcrypt('password'),
    'role'          => 'super_admin',
    'department_id' => null,
]);
```

Chạy seeder:
```bash
php artisan db:seed
```

---

### 8. Test đăng nhập

```bash
php artisan serve
```

Mở `http://127.0.0.1:8000/login` → đăng nhập bằng `admin@attendance.com / password`

---

## Kết quả kỳ vọng khi hoàn thành Sprint 1

- [ ] `php artisan migrate:fresh --seed` chạy không lỗi
- [ ] HeidiSQL hiển thị đúng 8 bảng: `departments`, `users`, `face_encodings`, `devices`, `attendances`, `cache`, `jobs`, `migrations`
- [ ] Đăng nhập được vào trang `/dashboard` của Breeze
- [ ] Không có lỗi nào trong `storage/logs/laravel.log`

---

## Ghi chú kỹ thuật

> **Circular FK giữa `departments` và `users`:**
> Migration `0001` tạo `departments` *không có* FK `manager_id`.
> Migration `0002` tạo `users` với FK `department_id`, sau đó *mới thêm* FK `manager_id` vào `departments`.
> Đây là cách xử lý circular reference trong MySQL — không thay đổi thứ tự này.

> **`face_encodings` không có `updated_at`:**
> Encoding một khi đã tạo không cần sửa. Nếu cần cập nhật, xóa cũ → tạo mới.
> Pi dùng `created_at` để delta sync: `GET /api/encodings?updated_since=<timestamp>`
