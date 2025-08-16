# 🚄 O'zbekiston Temir Yo'l Biletlari Telegram Bot

O'zbekiston temir yo'l biletlarini qidirish va kuzatib borish uchun Telegram bot. Bu bot foydalanuvchilarga poyezd joylarini qidirish va mavjud joylar chiqganda avtomatik xabar olish imkonini beradi.

## 🎯 Asosiy Funksiyalar

- **🔍 Poyezd Qidiruv**: Ketish va borish stansiyalarini tanlash orqali mavjud poyezdlarni ko'rish
- **📅 Kunlik Kuzatuv**: Belgilangan kun uchun istalgan poyezdda joy chiqsa xabar olish
- **🚂 Poyezd Kuzatuvi**: Aniq poyezd uchun joy chiqsa xabar olish
- **📋 Kuzatuvlarni Boshqarish**: Faol kuzatuvlarni ko'rish va bekor qilish
- **🔄 Avtomatik Monitoring**: Daqiqada bir marta joylarni tekshirish

## 📋 Texnik Talablar

- PHP 7.4+
- Python 3.8+ (cookie avtomatik yangilanishi uchun)
- cURL PHP extension
- JSON PHP extension
- Cron job imkoniyati
- Telegram Bot Token

## 🚀 O'rnatish

### 1. Repositoriyani klonlash

```bash
git clone https://github.com/vermino-uz/uzrailway-bot.git
cd uzrailway-bot
```

### 2. Konfiguratsiya faylini sozlash

```bash
cp botsdk/config.php.sample botsdk/config.php
```

`botsdk/config.php` faylini tahrirlang:

```php
<?php
$api_key = 'SIZNING_TELEGRAM_BOT_TOKEN';

// Railway API konfiguratsiyasi
$railway_xsrf_token = "sizning-xsrf-token";
$railway_cookies = "sizning-cookies";
?>
```

### 3. Python muhitini sozlash (ixtiyoriy)

Cookie avtomatik yangilanishi uchun:

```bash
# Virtual muhit yaratish
python3 -m venv venv
source venv/bin/activate  # Linux/Mac
# yoki
venv\Scripts\activate  # Windows

# Kerakli kutubxonalarni o'rnatish
./install_deps.sh
```

### 4. Webhook sozlash

Telegram webhook ni o'rnatish:

```bash
curl -X POST "https://api.telegram.org/bot<SIZNING_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://sizning-domen.uz/bot/index.php"}'
```

### 5. Cron job sozlash

```bash
chmod +x setup_cron.sh
./setup_cron.sh
```

Bu quyidagi cron job'larni qo'shadi:
- Har daqiqada monitor.php ishga tushadi
- Har 6 soatda cookie avtomatik yangilanadi

## 📁 Fayl Tuzilishi

```
├── index.php              # Webhook entry point
├── botsdk/
│   ├── config.php         # Asosiy konfiguratsiya
│   └── tg.php            # Bot logikasi
├── helpers.php           # Yordamchi funksiyalar
├── monitor.php           # Monitoring skripti
├── map.json             # Stansiyalar ro'yxati
├── data/
│   ├── follows.json     # Kuzatuv ma'lumotlari
│   ├── state_*.json     # Foydalanuvchi holatlari
│   └── logs/           # Log fayllar
├── get_cookies.py       # Cookie olish (Selenium)
├── get_cookies_simple.py # Cookie olish (oddiy)
└── auto_refresh_cron.sh # Cookie yangilash skripti
```

## 🔧 Cookie Va XSRF Token Sozlash

### Manual usul:

1. Brauzerda eticket.railway.uz ni oching
2. Developer Tools → Network → har qanday so'rovni tanlang
3. Request Headers qismidan cookie va X-XSRF-TOKEN ni nusxalang
4. `botsdk/config.php` ga joylashtiring

### Avtomatik usul:

```bash
# Oddiy usul (tez)
python3 get_cookies_simple.py

# Selenium usul (ishonchli)
python3 get_cookies.py
```

## 📊 Monitoring

### Log fayllarni ko'rish:

```bash
# Bot loglar
tail -f data/logs/bot.log

# Monitor loglar  
tail -f data/logs/monitor.log

# Cookie yangilash loglar
tail -f data/logs/cookie_refresh.log
```

### Manual monitoring test:

```bash
php monitor.php
```

## 🎮 Bot Ishlatish

### 1. Botni ishga tushirish
`/start` - Asosiy menyu

### 2. Poyezd qidirish
1. "🚄 Poyezd qidirish" tugmasini bosing
2. Ketish stansiyasini tanlang
3. Borish stansiyasini tanlang  
4. Sanani kiriting (YYYY-MM-DD)
5. Natijalarni ko'ring

### 3. Kuzatuv qo'shish
Poyezdlar ro'yxatida "Kuzatuv" tugmasini bosing:
- **📅 Kunlik kuzatuv**: Istalgan poyezdda joy chiqsa xabar
- **🚂 Poyezd kuzatuvi**: Aniq poyezdda joy chiqsa xabar

### 4. Kuzatuvlarni boshqarish
"📋 Mening kuzatuvlarim" → Faol kuzatuvlarni ko'rish va bekor qilish

## 🔧 Muammolarni Hal Qilish

### Bot javob bermayapti:
```bash
# Log fayllarni tekshiring
tail -50 data/logs/bot.log

# Webhook holatini tekshiring
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

### Monitoring ishlamayapti:
```bash
# Cron job holatini tekshiring
crontab -l

# Manual test
php monitor.php

# Monitor logini tekshiring
tail -20 data/logs/monitor.log
```

### Cookie muammolari:
```bash
# Yangi cookie oling
python3 get_cookies_simple.py

# Config faylni yangilang
nano botsdk/config.php
```

## 🛠️ Xatoliklarni Tuzatish

### "An expected CSRF token cannot be found"
- Cookie va XSRF token muddati tugagan
- `get_cookies_simple.py` ishga tushiring
- Yangi qiymatlarni `config.php` ga yozing

### "No active follows found"
- `data/follows.json` faylini tekshiring
- Foydalanuvchi hali kuzatuv qo'shmagan

### Permission denied
```bash
chmod 755 setup_cron.sh auto_refresh_cron.sh
chmod 777 data/
```

## 🔐 Xavfsizlik

- **Bot Token**: `.env` faylda saqlang, git'ga qo'shmang
- **Cookie**: Muntazam yangilab turing
- **Log fayllar**: Shaxsiy ma'lumotlar uchun tekshiring
- **Webhook URL**: HTTPS ishlatish majburiy

## 🤝 Hissa Qo'shish

1. Repository'ni fork qiling
2. Yangi branch yarating: `git checkout -b yangi-funksiya`
3. O'zgarishlaringizni commit qiling: `git commit -am 'Yangi funksiya qo'shildi'`
4. Branch'ni push qiling: `git push origin yangi-funksiya`
5. Pull Request yarating

## 📄 Litsenziya

Bu loyiha MIT litsenziyasi ostida tarqatiladi. Batafsil ma'lumot uchun [LICENSE](LICENSE) faylini ko'ring.

## 📞 Yordam

Savollar yoki muammolar uchun:
- Issue ochish: [GitHub Issues](https://github.com/vermino-uz/uzrailway-bot/issues)
- Telegram: @vermino

## 🙏 Minnatdorchilik

- [O'zbekiston Temir Yo'llari](https://railway.uz) - API uchun

---

⭐ Agar loyiha foydali bo'lsa, yulduzcha qo'yishni unutmang!
