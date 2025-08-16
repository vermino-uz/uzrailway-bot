#!/bin/bash

echo "🔧 Installing Python Cookie Fetcher Dependencies"
echo "=" * 50

# Check if we're on Ubuntu/Debian
if command -v apt-get >/dev/null 2>&1; then
    echo "📦 Installing system packages..."
    
    # Update package list
    apt-get update
    
    # Install Python3 and pip if not available
    apt-get install -y python3 python3-pip
    
    # Install Chrome
    echo "🌐 Installing Google Chrome..."
    wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | apt-key add -
    echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" > /etc/apt/sources.list.d/google-chrome.list
    apt-get update
    apt-get install -y google-chrome-stable
    
    # Install ChromeDriver
    echo "🚗 Installing ChromeDriver..."
    CHROME_VERSION=$(google-chrome --version | awk '{print $3}' | cut -d'.' -f1)
    DRIVER_VERSION=$(curl -s "https://chromedriver.storage.googleapis.com/LATEST_RELEASE_${CHROME_VERSION}")
    wget -O /tmp/chromedriver.zip "https://chromedriver.storage.googleapis.com/${DRIVER_VERSION}/chromedriver_linux64.zip"
    unzip /tmp/chromedriver.zip -d /tmp/
    mv /tmp/chromedriver /usr/local/bin/
    chmod +x /usr/local/bin/chromedriver
    rm /tmp/chromedriver.zip
    
else
    echo "⚠️ This script is designed for Ubuntu/Debian systems"
    echo "Please install manually:"
    echo "- Python 3 and pip"
    echo "- Google Chrome"
    echo "- ChromeDriver"
fi

# Install Python packages
echo "🐍 Installing Python packages..."
pip3 install selenium

# Make the script executable
chmod +x get_cookies.py

echo ""
echo "✅ Installation complete!"
echo ""
echo "Usage:"
echo "  ./get_cookies.py         # Get fresh cookies"
echo "  python3 get_cookies.py   # Alternative way to run"
echo ""
echo "Test installation:"
echo "  python3 -c 'import selenium; print(\"Selenium OK\")"'
echo "  google-chrome --version"
echo "  chromedriver --version"
