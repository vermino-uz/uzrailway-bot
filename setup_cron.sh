#!/bin/bash

# Add cron jobs for railway bot monitoring and cookie refresh
# This script should be run once to set up the complete automation

SCRIPT_DIR="/www/wwwroot/vermino.uz/bots/orders/railways"
MONITOR_JOB="* * * * * /usr/bin/php ${SCRIPT_DIR}/monitor.php >> ${SCRIPT_DIR}/data/logs/monitor.log 2>&1"
COOKIE_JOB="0 */6 * * * ${SCRIPT_DIR}/auto_refresh_cron.sh"

echo "🔧 Setting up Railway Bot Automation"
echo "=" * 40

# Create log directories
mkdir -p "${SCRIPT_DIR}/data/logs"

# Check if monitor job already exists
if crontab -l 2>/dev/null | grep -q "monitor.php"; then
    echo "✅ Monitor cron job already exists"
else
    # Add monitor cron job
    (crontab -l 2>/dev/null; echo "$MONITOR_JOB") | crontab -
    echo "✅ Monitor cron job added - runs every minute"
fi

# Check if cookie refresh job already exists
if crontab -l 2>/dev/null | grep -q "auto_refresh_cron.sh"; then
    echo "✅ Cookie refresh cron job already exists"
else
    # Add cookie refresh cron job
    (crontab -l 2>/dev/null; echo "$COOKIE_JOB") | crontab -
    echo "✅ Cookie refresh cron job added - runs every 6 hours"
fi

echo ""
echo "📋 Current cron jobs:"
crontab -l

echo ""
echo "📁 Log files:"
echo "  Monitor: ${SCRIPT_DIR}/data/logs/monitor.log"
echo "  Bot: ${SCRIPT_DIR}/data/logs/bot.log"  
echo "  Cookie refresh: ${SCRIPT_DIR}/data/logs/cookie_refresh.log"

echo ""
echo "🎉 Setup complete!"
echo "💡 Test manually:"
echo "  php ${SCRIPT_DIR}/monitor.php"
echo "  ${SCRIPT_DIR}/auto_refresh_cron.sh"
