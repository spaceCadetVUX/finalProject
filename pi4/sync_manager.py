from api_client import post_batch, fetch_encodings, fetch_encoding_delta, ping
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
    Đồng bộ encoding từ server.
    - last_sync_ts=None → full replace toàn bộ (dùng khi manual sync hoặc khởi động)
    - last_sync_ts set  → delta: thêm mới + xóa nhân viên đã bị xóa
    Trả về unix timestamp của lần sync này để dùng cho lần sau.
    """
    import time
    try:
        if last_sync_ts is None:
            records = fetch_encodings()
            recognizer.load_encodings(records)
            print(f"[Sync] Full sync: {len(records)} encodings")
        else:
            data = fetch_encoding_delta(last_sync_ts)
            new_records = data.get("encodings", [])
            deleted_ids = data.get("deleted_user_ids", [])
            for record in new_records:
                recognizer.update_encoding(record)
            for uid in deleted_ids:
                recognizer.remove_encoding(uid)
            if new_records or deleted_ids:
                print(f"[Sync] Delta: +{len(new_records)} mới, -{len(deleted_ids)} đã xóa")
        return int(time.time())
    except Exception as e:
        print(f"[Sync] Lỗi khi tải encoding: {e}")
        return last_sync_ts or 0
