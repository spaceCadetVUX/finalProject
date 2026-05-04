import requests
from config import SERVER_URL, DEVICE_TOKEN

HEADERS = {
    "Authorization": f"Bearer {DEVICE_TOKEN}",
    "Accept": "application/json",
    "Content-Type": "application/json",
}
TIMEOUT = 10  # giây


def auth_device() -> dict | None:
    """Đăng ký online khi boot. Trả về {id, name, location, status} hoặc None nếu lỗi."""
    try:
        resp = requests.post(f"{SERVER_URL}/api/auth/device",
                             headers=HEADERS, timeout=TIMEOUT)
        resp.raise_for_status()
        return resp.json()
    except requests.RequestException:
        return None


def fetch_encodings(updated_since: int | None = None) -> list[dict]:
    """Tải danh sách face encoding từ server. updated_since là unix timestamp."""
    params = {}
    if updated_since:
        params["updated_since"] = updated_since

    resp = requests.get(f"{SERVER_URL}/api/encodings", headers=HEADERS,
                        params=params, timeout=TIMEOUT)
    resp.raise_for_status()
    return resp.json().get("encodings", [])


def post_attendance(user_id: int, record_type: str, confidence: float,
                    image_b64: str | None, recorded_at: str) -> dict | None:
    """
    Ghi chấm công. Trả về {id, work_date, status} từ server, hoặc None nếu lỗi.
    status: 'present' | 'late' | 'early_leave'
    """
    payload = {
        "user_id":     user_id,
        "type":        record_type,
        "confidence":  confidence,
        "image":       image_b64,
        "recorded_at": recorded_at,
    }
    try:
        resp = requests.post(f"{SERVER_URL}/api/attendance", json=payload,
                             headers=HEADERS, timeout=TIMEOUT)
        if resp.status_code in (200, 201):
            return resp.json()
    except requests.RequestException:
        pass
    return None


def post_batch(records: list[dict]) -> int:
    """Trả về số record đã sync thành công. Tối đa 500 records/lần."""
    try:
        resp = requests.post(f"{SERVER_URL}/api/attendance/batch",
                             json={"records": records},
                             headers=HEADERS, timeout=30)
        if resp.status_code in (200, 201):
            return resp.json().get("saved", len(records))
    except requests.RequestException:
        pass
    return 0


def ping() -> bool:
    try:
        resp = requests.post(f"{SERVER_URL}/api/device/ping",
                             headers=HEADERS, timeout=5)
        return resp.status_code == 200
    except requests.RequestException:
        return False
