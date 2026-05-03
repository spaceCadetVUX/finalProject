import sqlite3
import time
from config import DB_PATH


def get_conn():
    return sqlite3.connect(DB_PATH)


def init_db():
    with get_conn() as conn:
        conn.execute("""
            CREATE TABLE IF NOT EXISTS pending_records (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL,
                type        TEXT NOT NULL,       -- 'check_in' | 'check_out'
                confidence  REAL NOT NULL,
                image_b64   TEXT,
                recorded_at TEXT NOT NULL,
                synced      INTEGER DEFAULT 0    -- 0 = chưa sync, 1 = đã sync
            )
        """)
        conn.commit()


def save_record(user_id: int, record_type: str, confidence: float,
                image_b64: str | None, recorded_at: str):
    with get_conn() as conn:
        conn.execute(
            "INSERT INTO pending_records (user_id, type, confidence, image_b64, recorded_at) "
            "VALUES (?, ?, ?, ?, ?)",
            (user_id, record_type, confidence, image_b64, recorded_at)
        )
        conn.commit()


def get_unsynced() -> list[dict]:
    with get_conn() as conn:
        rows = conn.execute(
            "SELECT id, user_id, type, confidence, image_b64, recorded_at "
            "FROM pending_records WHERE synced = 0 ORDER BY id"
        ).fetchall()
    return [
        {"_local_id": r[0], "user_id": r[1], "type": r[2],
         "confidence": r[3], "image": r[4], "recorded_at": r[5]}
        for r in rows
    ]


def mark_synced(local_ids: list[int]):
    if not local_ids:
        return
    placeholders = ",".join("?" * len(local_ids))
    with get_conn() as conn:
        conn.execute(f"UPDATE pending_records SET synced=1 WHERE id IN ({placeholders})", local_ids)
        conn.commit()
