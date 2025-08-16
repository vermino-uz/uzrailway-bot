#!/bin/bash

# Auto Cookie Refresh for Railway Bot
# Run this script every 6 hours to keep cookies fresh

LOG_FILE="/www/wwwroot/vermino.uz/bots/orders/railways/data/logs/cookie_refresh.log"
SCRIPT_DIR="/www/wwwroot/vermino.uz/bots/orders/railways"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting cookie refresh..." >> "$LOG_FILE"

cd "$SCRIPT_DIR"

# Try the simple Python method first
if python3 get_cookies_simple.py >> "$LOG_FILE" 2>&1; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✅ Simple cookie refresh successful" >> "$LOG_FILE"
    exit 0
fi

# If simple method fails, try the browser method
if command -v google-chrome >/dev/null 2>&1 && python3 -c "import selenium" 2>/dev/null; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Simple method failed, trying browser method..." >> "$LOG_FILE"
    if python3 get_cookies.py >> "$LOG_FILE" 2>&1; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✅ Browser cookie refresh successful" >> "$LOG_FILE"
        exit 0
    fi
fi

# If all methods fail, log it
echo "[$(date '+%Y-%m-%d %H:%M:%S')] ❌ All cookie refresh methods failed" >> "$LOG_FILE"
exit 1
