#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

stop_pidfile() {
  local file="$1"
  if [[ -f "$file" ]]; then
    local pid
    pid="$(cat "$file" 2>/dev/null || true)"
    if [[ -n "${pid:-}" ]] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
      echo "stopped pid ${pid} ($(basename "$file"))"
    fi
    rm -f "$file"
  fi
}

stop_pidfile "$ROOT/logs/php-server.pid"
stop_pidfile "$ROOT/logs/ngrok.pid"

# Fallback if pid files are stale
pkill -f "php -S 127.0.0.1:8080" 2>/dev/null || true
pkill -f "ngrok http 8080" 2>/dev/null || true

echo "Local PHP / ngrok stopped."
