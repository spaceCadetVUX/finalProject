"""
Script được gọi bởi Laravel EncodeFaceJob.
Nhận đường dẫn ảnh, trả về JSON chứa face encoding.

Sử dụng: python face_encode_single.py <image_path>
"""

import sys
import json
import face_recognition


def encode(image_path: str) -> dict:
    try:
        img = face_recognition.load_image_file(image_path)
        encodings = face_recognition.face_encodings(img)

        if not encodings:
            return {"encoding": None, "error": "Không tìm thấy khuôn mặt trong ảnh"}

        return {"encoding": encodings[0].tolist()}

    except Exception as e:
        return {"encoding": None, "error": str(e)}


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"encoding": None, "error": "Thiếu đường dẫn ảnh"}))
        sys.exit(1)

    result = encode(sys.argv[1])
    print(json.dumps(result))
