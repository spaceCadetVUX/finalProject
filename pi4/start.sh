#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "[*] Pulling latest code..."
cd "$REPO_DIR"
git pull origin master

echo "[*] Stopping old process (if any)..."
pkill -f "python3 ui/app.py" 2>/dev/null || true
sleep 1

echo "[*] Starting app..."
cd "$SCRIPT_DIR"
export DISPLAY=:0
exec python3 ui/app.py
