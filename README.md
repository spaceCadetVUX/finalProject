<div align="center">

# Face Recognition Attendance System

**Automated employee attendance tracking powered by deep learning and IoT**

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Python](https://img.shields.io/badge/Python-3.9+-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://python.org)
[![TailwindCSS](https://img.shields.io/badge/TailwindCSS-3.x-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Raspberry Pi](https://img.shields.io/badge/Raspberry_Pi-4B-A22846?style=for-the-badge&logo=raspberrypi&logoColor=white)](https://raspberrypi.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

*A full-stack attendance management system that combines a Raspberry Pi 4 edge device running real-time face recognition with a Laravel web dashboard for administration, reporting, and shift management.*

</div>

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [System Architecture](#system-architecture)
- [Tech Stack](#tech-stack)
- [How It Works](#how-it-works)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Web Dashboard Setup](#web-dashboard-setup)
  - [Raspberry Pi Setup](#raspberry-pi-setup)
- [API Reference](#api-reference)
- [Project Structure](#project-structure)
- [Screenshots](#screenshots)
- [License](#license)

---

## Overview

Traditional attendance methods — sign-in sheets, swipe cards, manual entry — are slow, error-prone, and easy to abuse. This system replaces them entirely.

A **Raspberry Pi 4** mounted at the entrance runs a Python program that captures live camera frames, detects faces using a **HOG + SVM detector**, encodes them into **128-dimensional embeddings** via a **ResNet-34 deep neural network** (dlib), and matches against registered employees in under 400 ms. Check-in and check-out events are sent to the **Laravel REST API** in real time. When the network is unavailable, records are buffered in a local **SQLite** database and synced automatically when connectivity is restored.

The **Laravel dashboard** gives administrators full control: manage employees, departments, shift templates, shift schedules, and devices — all with live statistics updating every 10 seconds.

---

## Key Features

### Edge Device (Raspberry Pi 4)
- **Real-time face detection** using HOG descriptor + SVM sliding-window classifier
- **Face encoding** via dlib's ResNet-34 model — 128-D L2-normalized embedding per face
- **Automatic check-in / check-out** disambiguation based on active shift schedule
- **5-minute cooldown** per employee to prevent duplicate records
- **Offline-first**: buffers attendance records to local SQLite when network is down; auto-syncs on reconnect
- **Delta sync**: only downloads new/updated face encodings since last sync — saves bandwidth

### Web Dashboard (Laravel)
- **Role-based access control** — Super Admin / Admin / Manager, enforced at middleware level
- **Employee management** with face registration: upload photo → background Queue Job extracts 128-D encoding via Python script
- **Department management** — assign managers, configure descriptions
- **Shift template & schedule system** — define shift times, assign to employees or whole departments, with conflict detection
- **Live dashboard** — attendance counts, 7-day chart, recent check-ins, device status — auto-refreshes every 10 seconds
- **Manual attendance override** — Admins and Managers can add or edit records with notes
- **Export to Excel (.xlsx) and PDF** — filterable by department, employee, and date range
- **Device registry** — register Pi devices, issue/revoke API tokens, monitor online/offline via heartbeat

---

## System Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                          CLIENT LAYER                            │
│              Browser  (Admin / Manager / Employee)               │
└───────────────────────────┬──────────────────────────────────────┘
                            │  HTTPS
┌───────────────────────────▼──────────────────────────────────────┐
│                      WEB APPLICATION                             │
│                   Laravel 13  (PHP 8.3+)                         │
│                                                                  │
│   Blade + Alpine.js + TailwindCSS        REST API (Sanctum)      │
│   Queue Worker (face encoding jobs)      Device heartbeat        │
└────────────┬─────────────────────────────────────┬───────────────┘
             │ Eloquent ORM                         │ JSON over HTTPS
┌────────────▼───────────┐           ┌─────────────▼──────────────┐
│    MySQL 8.0           │           │      Raspberry Pi 4         │
│                        │           │                             │
│  users                 │           │  Python 3.9+                │
│  departments           │           │  face_recognition (dlib)    │
│  shift_templates       │           │  OpenCV  ·  NumPy           │
│  shift_schedules       │           │  Camera Module / USB Cam    │
│  attendances           │           │  SQLite  (offline buffer)   │
│  devices               │           │  Auto-sync on reconnect     │
│  face_encodings        │           └─────────────────────────────┘
└────────────────────────┘
```

---

## Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| **Web Framework** | Laravel 13 (PHP 8.3+) | MVC, REST API, Queue, Auth |
| **Frontend** | Blade · TailwindCSS 3 · Alpine.js 3 | UI templates and interactivity |
| **Build Tool** | Vite 8 | Asset bundling and HMR |
| **Charts** | Chart.js | 7-day attendance chart |
| **Tables** | DataTables.js | Paginated, sortable data tables |
| **API Auth** | Laravel Sanctum | Token-based auth for Pi devices |
| **Web Auth** | Laravel Breeze | Session-based login for web users |
| **Primary DB** | MySQL 8.0 | All relational data |
| **Edge DB** | SQLite | Offline attendance buffer on Pi |
| **Face Detection** | dlib HOG + SVM | Real-time face localization |
| **Face Encoding** | dlib ResNet-34 (128-D) | Deep face embeddings |
| **Image Processing** | OpenCV 4.9 | Camera capture and frame preprocessing |
| **Matching** | Euclidean distance (NumPy) | Identity lookup against known encodings |
| **Excel Export** | PhpSpreadsheet 2.0 | .xlsx report generation |
| **PDF Export** | DomPDF 3.1 | PDF report generation |
| **Hardware** | Raspberry Pi 4 Model B | Edge inference device |

---

## How It Works

### Face Recognition Pipeline

```
Camera frame (1280×720)
        │
        ▼ Downscale to 1/4 · BGR → RGB  (OpenCV)
        │
        ▼ HOG feature extraction + SVM sliding window  (dlib)
        │   └─ Non-Maximum Suppression → bounding boxes
        │
        ▼ 68-landmark alignment → Affine transform → 150×150 crop  (dlib)
        │
        ▼ ResNet-34 forward pass → 128-D L2-normalized embedding  (dlib)
        │
        ▼ Euclidean distance vs. all registered embeddings  (NumPy)
        │   └─ best_distance < 0.5  →  identity confirmed
        │
        ▼ Cooldown check (5 min per employee)
        │
        ▼ POST /api/attendance  (online)  or  SQLite buffer  (offline)
```

### Check-in / Check-out Status Logic

The server determines attendance status based on a **Shift → Department → None** priority chain:

```
Active shift assigned?
  YES → compare check_in_at with shift.check_in_time + late_tolerance
  NO  → fall back to department defaults
        → if none: mark as present (no penalty)
```

Status values: `present` · `late` · `early_leave` · `absent` · `leave`

---

## Getting Started

### Prerequisites

- PHP 8.3+, Composer
- Node.js 20+ and npm
- MySQL 8.0
- Python 3.9+ (on the Raspberry Pi)
- A Raspberry Pi 4 with a camera module (USB or CSI)

---

### Web Dashboard Setup

```bash
# 1. Clone the repository
git clone <repo-url>
cd finalProject/laravel/dashboard

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies and build assets
npm install && npm run build

# 4. Configure environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

```bash
# 5. Run migrations and seed demo data
php artisan migrate --seed

# 6. Start the development server
php artisan serve

# 7. (Optional) Start the queue worker for face encoding jobs
php artisan queue:work
```

Visit `http://localhost:8000` — default Super Admin credentials are set in `DatabaseSeeder`.

---

### Raspberry Pi Setup

```bash
# 1. Navigate to the Pi source directory
cd finalProject/pi4

# 2. Create and activate a virtual environment
python3 -m venv venv
source venv/bin/activate

# 3. Install dependencies
# Note: dlib compilation requires cmake and a C++ compiler
pip install -r requirements.txt

# 4. Configure environment
cp .env.example .env
```

Edit `.env` on the Pi:

```env
API_BASE_URL=http://<your-server-ip>/api
DEVICE_TOKEN=<token-from-dashboard>
CAMERA_INDEX=0
TOLERANCE=0.5
COOLDOWN_SECONDS=300
```

```bash
# 5. Run the attendance client
python main.py

# Or use the startup script (runs on boot)
chmod +x start.sh && ./start.sh
```

---

## API Reference

All Pi-facing endpoints are prefixed with `/api` and require a `Bearer` token.

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/device` | Device login — exchange token for device info |
| `GET` | `/api/encodings` | Fetch all face encodings |
| `GET` | `/api/encodings?updated_since={ts}` | Delta sync — only encodings newer than timestamp |
| `POST` | `/api/attendance` | Submit a single check-in or check-out event |
| `POST` | `/api/attendance/batch` | Bulk sync offline-buffered records |
| `POST` | `/api/device/ping` | Heartbeat — marks device as online |

**Attendance payload:**

```json
{
  "user_id": 5,
  "type": "check_in",
  "confidence": 0.92,
  "image": "<base64-encoded-jpeg>",
  "recorded_at": "2025-05-03T08:05:00"
}
```

---

## Project Structure

```
finalProject/
├── laravel/
│   └── dashboard/               # Laravel web application
│       ├── app/
│       │   ├── Http/Controllers/
│       │   │   ├── Api/         # Pi-facing REST API controllers
│       │   │   └── Web/         # Browser-facing controllers
│       │   ├── Models/          # Eloquent models
│       │   └── Services/        # AttendanceStatusService, ShiftConflictService
│       ├── database/
│       │   ├── migrations/      # Schema definitions
│       │   └── seeders/         # Demo data
│       └── resources/views/     # Blade templates
│
├── pi4/                         # Raspberry Pi edge client
│   ├── main.py                  # Entry point — main recognition loop
│   ├── face_recognizer.py       # HOG detection + ResNet-34 encoding + matching
│   ├── camera.py                # OpenCV camera wrapper
│   ├── api_client.py            # HTTP communication with Laravel API
│   ├── local_storage.py         # SQLite offline buffer
│   ├── sync_manager.py          # Offline → online sync logic
│   └── config.py                # Environment config loader
│
├── diagram/                     # Architecture and UML diagrams
└── Report/                      # Project report documents
```

---

## Screenshots

> *(Add screenshots of the Dashboard, Employee Management, Shift Schedule, and Pi terminal output here)*

---

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

---

<div align="center">
  Built with Laravel · dlib · OpenCV · Raspberry Pi 4
</div>
