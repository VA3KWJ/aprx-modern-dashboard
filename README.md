# APRX Modern Dashboard

A clean, modernized PHP web interface for monitoring and visualizing APRX digipeater and iGate activity from log files. This project is based on earlier APRX log parsers but rebuilt for PHP 8.2+ with a responsive, mobile-friendly interface.

---

## 🚀 Features

- 📊 Dashboard summary of recent activity
- 📡 Live log viewer with APRX-RF and Daemon logs
- ⏱ Selectable time ranges (1h, 2h, 4h, 6h, 12h, 24h, 7d, All)
- 🧭 Station table with QRZ and APRS-IS links
- 🧠 Uptime, mode (Digipeater/iGate), APRX version, interface label
- 🌐 Simple, modern CSS theme (no external dependencies)
- 🔍 Search and filter functionality

---

## ✅ Tested Environment

- **Debian 12 (Bookworm)**
- **Lighttpd 1.4.x** with PHP-FPM
- **PHP 8.2** (specifically tested on 8.2.15)
- APRX compiled or installed from source

---

## 📂 File Structure

```
/var/www/html/
├── index.php         # Dashboard summary view
├── live.php          # Live log viewer
├── summary.php       # Statistics generator
├── config.php        # Configuration file paths
├── functions.php     # Utility functions
├── tail.php          # Log tailing backend
├── style.css         # Modern dark CSS
├── aprslogo.png      # Header logo
```

---

## 🔧 Setup Instructions

### 1. Configure Web Server (Lighttpd + PHP-FPM)
Ensure your FastCGI points to the right socket:
```lighttpd
fastcgi.server += ( ".php" =>
  ((
    "socket" => "/run/php/php8.2-fpm.sock",
    "broken-scriptfilename" => "enable"
  ))
)
```

### 2. Permissions
Make sure the web server user (`www-data`) can read:

- `/var/log/aprx/aprx-rf.log`
- `/var/log/aprx/aprx.log`
- `/etc/aprx.conf`

Example (with ADM group):
```bash
sudo usermod -a -G adm www-data
sudo chgrp adm /var/log/aprx/*
sudo chmod 640 /var/log/aprx/*
```

### 3. Allow APRX version check (optional)
The dashboard runs:
```php
shell_exec("sudo aprx --v")
```
To allow this without prompting for a password:
```bash
echo "www-data ALL=(ALL) NOPASSWD: /usr/sbin/aprx" | sudo tee /etc/sudoers.d/aprx-dashboard
```

---

## 📜 License

This project is free for **non-commercial use only**.

Please credit all original contributors:

- Peter SQ8VPS & Alfredo IZ7BOJ
- Ryan KF6ODE
- Modernized by **VA3KWJ**

---

## 📎 Notes

- No external JS or PHP libraries required.
- QRZ and APRS-IS lookups are based on the callsign string and use direct linking.
- Reverse geolocation (if used) leverages Nominatim — respect usage limits.

---

🛰 APRX on the air, now with style.  
https://github.com/VA3KWJ/aprx-modern-dashboard
