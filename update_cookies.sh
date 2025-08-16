#!/bin/bash

echo "🔧 Cookie Update Helper"
echo "======================"
echo
echo "When cookies expire, you'll see 'CSRF token' errors."
echo
echo "To fix:"
echo "1. Open https://eticket.railway.uz in browser"
echo "2. Open DevTools (F12) → Network tab"
echo "3. Search for any train route"
echo "4. Find the API request to /api/v3/handbook/trains/list"
echo "5. Copy the X-XSRF-TOKEN and Cookie header values"
echo
echo "6. Update these files:"
echo "   - botsdk/config.php (lines 6-7) - MAIN LOCATION"
echo "   - cookies_backup.json (optional backup)"
echo
echo "✅ RECOMMENDED: Edit botsdk/config.php"
echo "   Update \$railway_xsrf_token and \$railway_cookies variables"
echo
echo "Or run: nano botsdk/config.php"
echo "Then update the railway API configuration section"
echo
echo "Current status:"
if [ -f "data/cookies.json" ]; then
    echo "✅ Auto-refresh cookies file exists"
else
    echo "❌ No auto-refresh cookies found"
fi

if [ -f "cookies_backup.json" ]; then
    echo "✅ Backup cookies file exists"
else
    echo "❌ No backup cookies file"
fi

echo
echo "Test monitor with: php monitor.php"
