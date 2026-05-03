import os
from dotenv import load_dotenv

load_dotenv()

SERVER_URL    = os.getenv("SERVER_URL", "http://192.168.1.100")
DEVICE_TOKEN  = os.getenv("DEVICE_TOKEN", "")

# Ngưỡng nhận diện — confidence thấp hơn sẽ bị bỏ qua
MIN_CONFIDENCE = float(os.getenv("MIN_CONFIDENCE", "0.50"))

# Cooldown: cùng 1 người không ghi lại trong vòng N giây
COOLDOWN_SECONDS = int(os.getenv("COOLDOWN_SECONDS", "300"))

# Camera
CAMERA_INDEX    = int(os.getenv("CAMERA_INDEX", "0"))
FRAME_WIDTH     = int(os.getenv("FRAME_WIDTH", "640"))
FRAME_HEIGHT    = int(os.getenv("FRAME_HEIGHT", "480"))
PROCESS_EVERY_N = int(os.getenv("PROCESS_EVERY_N", "3"))  # xử lý 1 frame trong N frame

# Sync
PING_INTERVAL_SECONDS = int(os.getenv("PING_INTERVAL_SECONDS", "60"))

# Database SQLite local
DB_PATH = os.getenv("DB_PATH", "local_attendance.db")
