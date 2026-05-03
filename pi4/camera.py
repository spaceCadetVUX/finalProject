import cv2
from config import CAMERA_INDEX, FRAME_WIDTH, FRAME_HEIGHT


class Camera:
    def __init__(self):
        self.cap = cv2.VideoCapture(CAMERA_INDEX)
        self.cap.set(cv2.CAP_PROP_FRAME_WIDTH, FRAME_WIDTH)
        self.cap.set(cv2.CAP_PROP_FRAME_HEIGHT, FRAME_HEIGHT)

        if not self.cap.isOpened():
            raise RuntimeError(f"Không thể mở camera (index={CAMERA_INDEX})")

    def read_frame(self):
        """Trả về (ok, frame). frame là BGR numpy array."""
        return self.cap.read()

    def release(self):
        self.cap.release()
