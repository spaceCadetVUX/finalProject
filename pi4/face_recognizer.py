import json
import face_recognition
import numpy as np


class FaceRecognizer:
    def __init__(self):
        self._encodings: list[np.ndarray] = []
        self._user_ids:  list[int] = []

    def load_from_file(self, path: str):
        """Nạp encoding từ file JSON local (dùng khi offline hoàn toàn)."""
        with open(path) as f:
            records = json.load(f)
        self.load_encodings(records)

    def load_encodings(self, records: list[dict]):
        """Nạp danh sách encoding từ server vào bộ nhớ."""
        self._encodings = [np.array(r["encoding"]) for r in records]
        self._user_ids  = [r["user_id"] for r in records]
        print(f"[FaceRecognizer] Đã nạp {len(self._encodings)} encoding")

    def update_encoding(self, record: dict):
        """Thêm 1 encoding mới (delta sync)."""
        self._encodings.append(np.array(record["encoding"]))
        self._user_ids.append(record["user_id"])

    def recognize(self, frame_rgb: np.ndarray, tolerance: float = 0.5) -> list[dict]:
        """
        Nhận diện khuôn mặt trong frame.
        Trả về list [{"user_id": int, "confidence": float, "location": tuple}]
        """
        if not self._encodings:
            return []

        # Resize 1/4 để tăng tốc detect, giữ scale để map lại vị trí
        small = frame_rgb[::2, ::2]

        locations = face_recognition.face_locations(small, model="hog")
        if not locations:
            return []

        encodings = face_recognition.face_encodings(small, locations)

        results = []
        for enc, loc in zip(encodings, locations):
            distances = face_recognition.face_distance(self._encodings, enc)
            best_idx  = int(np.argmin(distances))
            best_dist = float(distances[best_idx])

            if best_dist <= tolerance:
                confidence = round(1.0 - best_dist, 4)
                # scale vị trí về kích thước gốc
                top, right, bottom, left = [x * 2 for x in loc]
                results.append({
                    "user_id":    self._user_ids[best_idx],
                    "confidence": confidence,
                    "location":   (top, right, bottom, left),
                })

        return results
