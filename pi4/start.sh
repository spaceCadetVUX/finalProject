#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

echo "[*] Pulling latest code..."
cd "$REPO_DIR"
git pull origin master

echo "[*] Starting app..."
cd "$SCRIPT_DIR"
export DISPLAY=:0
exec python3 ui/app.py
