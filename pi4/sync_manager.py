from api_client import post_batch, fetch_encodings, ping
from local_storage import get_unsynced, mark_synced
from face_recognizer import FaceRecognizer


def sync_pending(recognizer: FaceRecognizer) -> int:
    """
    Upload toàn bộ record offline lên server khi có mạng.
    Trả về số record đã sync thành công.
    """
    pending = get_unsynced()
    if not pending:
        return 0

    local_ids = [r.pop("_local_id") for r in pending]
    synced_count = post_batch(pending)

    if synced_count > 0:
        mark_synced(local_ids[:synced_count])
        print(f"[Sync] Đã sync {synced_count}/{len(pending)} record offline")

    return synced_count


def refresh_encodings(recognizer: FaceRecognizer, last_sync_ts: int | None) -> int:
    """
    Tải encoding mới từ server (delta sync).
    Trả về unix timestamp của lần sync này để dùng cho lần sau.
    """
    import time
    try:
        new_records = fetch_encodings(updated_since=last_sync_ts)
        for record in new_records:
            recognizer.update_encoding(record)
        if new_records:
            print(f"[Sync] Cập nhật {len(new_records)} encoding mới")
        return int(time.time())
    except Exception as e:
        print(f"[Sync] Lỗi khi tải encoding: {e}")
        return last_sync_ts or 0
