import json
import face_recognition
import numpy as np


class FaceRecognizer:
    def __init__(self):
        self._encodings: list[np.ndarray] = []
        self._user_ids:  list[int] = []
        self._names:     list[str] = []
        self._codes:     list[str] = []

    def load_from_file(self, path: str):
        """Nạp encoding từ file JSON local (dùng khi offline hoàn toàn)."""
        with open(path) as f:
            records = json.load(f)
        self.load_encodings(records)

    def load_encodings(self, records: list[dict]):
        """Nạp danh sách encoding từ server vào bộ nhớ."""
        self._encodings = [np.array(r["encoding"]) for r in records]
        self._user_ids  = [r["user_id"] for r in records]
        self._names     = [r.get("name", f"User {r['user_id']}") for r in records]
        self._codes     = [r.get("code", "") for r in records]
        print(f"[FaceRecognizer] Đã nạp {len(self._encodings)} encoding")

    def update_encoding(self, record: dict):
        """Thêm 1 encoding mới (delta sync)."""
        self._encodings.append(np.array(record["encoding"]))
        self._user_ids.append(record["user_id"])
        self._names.append(record.get("name", f"User {record['user_id']}"))
        self._codes.append(record.get("code", ""))

    def recognize(self, frame_rgb: np.ndarray, tolerance: float = 0.5) -> list[dict]:
        return [d for d in self.recognize_all(frame_rgb, tolerance) if d["recognized"]]

    def recognize_all(self, frame_rgb: np.ndarray, tolerance: float = 0.5) -> list[dict]:
        """
        Detect tất cả khuôn mặt, trả về recognized=True/False cho mỗi face.
        Dùng để vẽ bounding box kể cả khuôn mặt không nhận ra.
        """
        small     = np.ascontiguousarray(frame_rgb[::2, ::2])
        locations = face_recognition.face_locations(small, model="hog")
        if not locations:
            return []

        encodings = face_recognition.face_encodings(small, locations)
        results   = []

        for enc, loc in zip(encodings, locations):
            top, right, bottom, left = [x * 2 for x in loc]
            base = {"location": (top, right, bottom, left)}

            if self._encodings:
                distances = face_recognition.face_distance(self._encodings, enc)
                best_idx  = int(np.argmin(distances))
                best_dist = float(distances[best_idx])
                if best_dist <= tolerance:
                    base.update({
                        "recognized": True,
                        "user_id":    self._user_ids[best_idx],
                        "name":       self._names[best_idx],
                        "code":       self._codes[best_idx],
                        "confidence": round(1.0 - best_dist, 4),
                    })
                else:
                    base.update({"recognized": False, "user_id": None,
                                 "name": "Unknown", "code": "", "confidence": 0.0})
            else:
                base.update({"recognized": False, "user_id": None,
                             "name": "Unknown", "code": "", "confidence": 0.0})

            results.append(base)

        return results
