"""
Hệ thống chấm công nhận diện khuôn mặt — Raspberry Pi 4
Chạy: python main.py
"""

import base64
import time
from datetime import datetime, timedelta

import cv2
import numpy as np

import api_client
import local_storage
from camera import Camera
from config import (COOLDOWN_SECONDS, MIN_CONFIDENCE,
                    PING_INTERVAL_SECONDS, PROCESS_EVERY_N,
                    SHIFT_REFRESH_SECONDS)
from face_recognizer import FaceRecognizer
from sync_manager import refresh_encodings, sync_pending


def encode_image(frame: np.ndarray) -> str:
    """Nén frame thành JPEG và trả về base64 string."""
    _, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 70])
    return base64.b64encode(buf).decode()


def get_shift_cached(user_id: int, shift_cache: dict, online: bool) -> dict | None:
    """
    Lấy ca làm việc active của nhân viên, cache để tránh gọi API mỗi frame.
    Trả về dict shift hoặc None nếu không có ca / offline không có cache.
    """
    now = time.time()
    cached_at, cached_shift = shift_cache.get(user_id, (0, "UNSET"))
    if cached_shift != "UNSET" and now - cached_at < SHIFT_REFRESH_SECONDS:
        return cached_shift
    if not online:
        return cached_shift if cached_shift != "UNSET" else None
    shift = api_client.fetch_active_shift(user_id)
    shift_cache[user_id] = (now, shift)
    return shift


def determine_type(user_id: int, shift: dict | None,
                   cooldown_map: dict) -> tuple[str | None, int | None]:
    """
    Xác định check_in / check_out dựa trên ca làm việc và thời điểm hiện tại.
    Trả về (record_type, shift_schedule_id) hoặc (None, None) nếu bị block.

    Block khi:
    - Không có ca hôm nay
    - Ngoài cửa sổ hợp lệ (2h trước check_in_time ~ 2h sau check_out_time)
    - Còn trong cooldown
    """
    if shift is None:
        print(f"[Shift] user={user_id}: không có ca hôm nay → bỏ qua")
        return None, None

    now = time.time()
    last_time, _ = cooldown_map.get(user_id, (0, None))
    if now - last_time < COOLDOWN_SECONDS:
        return None, None  # còn trong cooldown

    now_dt = datetime.now()
    today  = now_dt.strftime("%Y-%m-%d")

    check_in_dt  = datetime.strptime(f"{today} {shift['check_in_time']}",  "%Y-%m-%d %H:%M")
    check_out_dt = datetime.strptime(f"{today} {shift['check_out_time']}", "%Y-%m-%d %H:%M")

    # Cửa sổ hợp lệ: 2h trước giờ vào ~ 2h sau giờ ra
    window_start = check_in_dt  - timedelta(hours=2)
    window_end   = check_out_dt + timedelta(hours=2)

    if not (window_start <= now_dt <= window_end):
        print(f"[Shift] user={user_id}: ngoài giờ ca "
              f"({shift['check_in_time']}–{shift['check_out_time']}) → bỏ qua")
        return None, None

    # Dùng điểm giữa ca để phân loại check_in / check_out
    mid_ts      = (check_in_dt.timestamp() + check_out_dt.timestamp()) / 2
    record_type = "check_in" if now_dt.timestamp() < mid_ts else "check_out"

    cooldown_map[user_id] = (now, record_type)
    return record_type, shift["shift_schedule_id"]


def main():
    local_storage.init_db()

    recognizer   = FaceRecognizer()
    camera       = Camera()
    cooldown_map = {}   # {user_id: (timestamp, type)}
    shift_cache  = {}   # {user_id: (fetched_at, shift_or_none)}
    last_ping_ts = 0
    last_sync_ts = None
    frame_count  = 0
    online       = False

    print("[Main] Đang kết nối server...")
    device_info = api_client.auth_device()
    if device_info:
        print(f"[Main] Auth OK — {device_info.get('name')} ({device_info.get('location')})")
        online = True
    else:
        print("[Main] Auth thất bại, chạy offline")

    print("[Main] Đang tải encoding từ server...")
    try:
        records = api_client.fetch_encodings()
        recognizer.load_encodings(records)
        last_sync_ts = int(time.time())
    except Exception as e:
        print(f"[Main] Không tải được encoding từ server: {e}")

    print("[Main] Bắt đầu vòng lặp nhận diện. Nhấn 'q' để thoát.")

    while True:
        ok, frame = camera.read_frame()
        if not ok:
            print("[Main] Không đọc được frame, thử lại...")
            time.sleep(0.5)
            continue

        frame_count += 1

        # ── Heartbeat & sync định kỳ ─────────────────────────────────────
        now = time.time()
        if now - last_ping_ts > PING_INTERVAL_SECONDS:
            online = api_client.ping()
            last_ping_ts = now
            if online:
                sync_pending(recognizer)
                last_sync_ts = refresh_encodings(recognizer, last_sync_ts)

        # ── Xử lý nhận diện mỗi N frame ──────────────────────────────────
        if frame_count % PROCESS_EVERY_N != 0:
            cv2.imshow("Attendance", frame)
            if cv2.waitKey(1) & 0xFF == ord('q'):
                break
            continue

        rgb        = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        detections = recognizer.recognize(rgb, tolerance=1.0 - MIN_CONFIDENCE)

        for det in detections:
            user_id    = det["user_id"]
            confidence = det["confidence"]

            shift = get_shift_cached(user_id, shift_cache, online)
            record_type, shift_schedule_id = determine_type(user_id, shift, cooldown_map)

            if record_type is None:
                continue

            recorded_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            top, right, bottom, left = det["location"]

            face_img  = frame[top:bottom, left:right]
            image_b64 = encode_image(face_img) if face_img.size > 0 else None

            label = f"ID:{user_id} {confidence:.0%} [{record_type}]"
            cv2.rectangle(frame, (left, top), (right, bottom), (0, 255, 0), 2)
            cv2.putText(frame, label, (left, top - 8),
                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 1)

            if online:
                result = api_client.post_attendance(user_id, record_type, confidence,
                                                    image_b64, recorded_at,
                                                    shift_schedule_id)
                if result:
                    status = result.get("status", "")
                    print(f"[Main] Chấm công: user={user_id} {record_type} → {status}")
                else:
                    local_storage.save_record(user_id, record_type, confidence,
                                              image_b64, recorded_at, shift_schedule_id)
                    online = False
            else:
                local_storage.save_record(user_id, record_type, confidence,
                                          image_b64, recorded_at, shift_schedule_id)

        cv2.imshow("Attendance", frame)
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    camera.release()
    cv2.destroyAllWindows()


if __name__ == "__main__":
    main()
