#!/usr/bin/env python3
"""
Railway Cookies Fetcher
Automatically gets fresh XSRF token and cookies from eticket.railway.uz
"""

import json
import time
import sys
import os
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, WebDriverException

def setup_driver():
    """Setup headless Chrome driver"""
    options = Options()
    options.add_argument('--headless')
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-gpu')
    options.add_argument('--disable-extensions')
    options.add_argument('--user-agent=Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36')
    
    try:
        driver = webdriver.Chrome(options=options)
        return driver
    except WebDriverException as e:
        print(f"❌ Failed to setup Chrome driver: {e}")
        print("💡 Install Chrome and ChromeDriver:")
        print("   apt-get update && apt-get install -y google-chrome-stable")
        print("   pip install selenium")
        return None

def get_railway_cookies():
    """Get cookies and XSRF token from railway website"""
    
    print("🔄 Starting headless browser...")
    driver = setup_driver()
    if not driver:
        return None
    
    try:
        # Navigate to railway website
        print("🌐 Loading eticket.railway.uz...")
        driver.get("https://eticket.railway.uz/uz/home")
        
        # Wait for page to load
        time.sleep(3)
        
        # Try to trigger CSRF token generation
        print("🔐 Getting CSRF token...")
        
        # Method 1: Try to access the CSRF endpoint directly
        try:
            driver.execute_script("""
                fetch('/sanctum/csrf-cookie', {
                    method: 'GET',
                    credentials: 'same-origin'
                });
            """)
            time.sleep(2)
        except:
            pass
        
        # Method 2: Try to interact with search form to trigger token
        try:
            # Look for search elements and interact with them
            wait = WebDriverWait(driver, 10)
            
            # Try to find and click search elements
            search_elements = driver.find_elements(By.CSS_SELECTOR, 
                "input, button, select, [data-testid*='search'], [class*='search']")
            
            if search_elements:
                print("🔍 Interacting with search form...")
                for element in search_elements[:3]:  # Try first 3 elements
                    try:
                        if element.is_displayed() and element.is_enabled():
                            element.click()
                            time.sleep(1)
                            break
                    except:
                        continue
                        
        except TimeoutException:
            print("⚠️ Could not find search form, continuing...")
        
        # Get all cookies
        cookies = driver.get_cookies()
        
        if not cookies:
            print("❌ No cookies found")
            return None
        
        # Format cookies for HTTP header
        cookie_pairs = []
        xsrf_token = None
        
        for cookie in cookies:
            name = cookie['name']
            value = cookie['value']
            cookie_pairs.append(f"{name}={value}")
            
            if name == 'XSRF-TOKEN':
                xsrf_token = value
        
        cookie_string = '; '.join(cookie_pairs)
        
        if not xsrf_token:
            print("⚠️ XSRF-TOKEN not found in cookies, checking page...")
            # Try to get XSRF token from page meta or scripts
            try:
                xsrf_elements = driver.find_elements(By.CSS_SELECTOR, 
                    'meta[name="csrf-token"], meta[name="_token"], input[name="_token"]')
                for element in xsrf_elements:
                    content = element.get_attribute('content') or element.get_attribute('value')
                    if content:
                        xsrf_token = content
                        break
            except:
                pass
        
        if not xsrf_token:
            print("❌ Could not find XSRF token")
            return None
        
        print(f"✅ Got {len(cookies)} cookies and XSRF token")
        return {
            'xsrf_token': xsrf_token,
            'cookies': cookie_string,
            'timestamp': int(time.time())
        }
        
    except Exception as e:
        print(f"❌ Error getting cookies: {e}")
        return None
        
    finally:
        driver.quit()

def update_config_file(data):
    """Update PHP config file with new cookies"""
    config_file = 'botsdk/config.php'
    
    try:
        with open(config_file, 'r') as f:
            content = f.read()
        
        # Update XSRF token
        import re
        content = re.sub(
            r'(\$railway_xsrf_token\s*=\s*")[^"]*(")',
            f'\\g<1>{data["xsrf_token"]}\\g<2>',
            content
        )
        
        # Update cookies (escape $ characters for PHP)
        escaped_cookies = data['cookies'].replace('$', '\\$')
        content = re.sub(
            r'(\$railway_cookies\s*=\s*")[^"]*(")',
            f'\\g<1>{escaped_cookies}\\g<2>',
            content
        )
        
        with open(config_file, 'w') as f:
            f.write(content)
        
        print(f"✅ Updated {config_file}")
        return True
        
    except Exception as e:
        print(f"❌ Failed to update config file: {e}")
        return False

def save_backup(data):
    """Save cookies to backup file"""
    backup_file = 'data/cookies_auto.json'
    
    try:
        os.makedirs('data', exist_ok=True)
        
        backup_data = {
            'xsrf_token': data['xsrf_token'],
            'cookies': data['cookies'],
            'created_at': time.strftime('%Y-%m-%d %H:%M:%S'),
            'timestamp': data['timestamp'],
            'expires_at': data['timestamp'] + (23 * 3600)  # 23 hours
        }
        
        with open(backup_file, 'w') as f:
            json.dump(backup_data, f, indent=2)
        
        print(f"✅ Saved backup to {backup_file}")
        return True
        
    except Exception as e:
        print(f"❌ Failed to save backup: {e}")
        return False

def main():
    print("🚀 Railway Cookie Fetcher")
    print("=" * 30)
    
    # Get fresh cookies
    data = get_railway_cookies()
    if not data:
        print("❌ Failed to get cookies")
        sys.exit(1)
    
    print(f"🎯 XSRF Token: {data['xsrf_token'][:20]}...")
    print(f"🍪 Cookies: {len(data['cookies'])} characters")
    
    # Update config file
    if update_config_file(data):
        print("✅ Config file updated successfully")
    else:
        print("❌ Failed to update config file")
        sys.exit(1)
    
    # Save backup
    save_backup(data)
    
    print("\n🎉 Cookies updated successfully!")
    print("💡 Test with: php monitor.php")

if __name__ == "__main__":
    main()
