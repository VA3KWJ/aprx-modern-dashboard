# APRX Modern Dashboard

A clean, modernized PHP web interface for monitoring and visualizing APRX digipeater and iGate activity from log files. This project is based on earlier APRX log parsers but rebuilt for PHP 8.2+ with a responsive, mobile-friendly interface.

---

### 📡 Live View

Want to see it in action?

👉 **[View the live dashboard here](https://aprx.va3kwj.ca)**

---

## 🚀 Features

- 📊 Dashboard summary of recent activity
- 📡 📡 Live log viewer with selectable APRX-RF and Daemon logs
- ⏱ Selectable time ranges (1h, 2h, 4h, 6h, 12h, 24h, 7d, All)
- 🧭 Station table with QRZ and APRS-IS links
- 🧠 Uptime, mode (Digipeater/iGate), APRX version, interface label
- 🌐 Simple, modern CSS theme (no external dependencies)
- 🔍 Search and filter functionality
- 📥 Log source dropdown and real-time stream updates
- 📢 Optional operator notices shown at top of live log view (edit `operator_notice.txt`)

---

## ✅ Tested Environment

- **Debian 12 (Bookworm)**
- **Lighttpd 1.4.x** with PHP-FPM
- **PHP 8.2** (specifically tested on 8.2.15)
- APRX compiled or installed from source
- **Debian 12 (Bookworm)** on Raspberry PI 3B (arm64 image)

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
├── test-sse.php      # SSE test for live tailing
├── test-tail.php     # PHP tail test script
├── operator_notice.txt # Optional message displayed at top of live log
├── api/
│ └── logfetch.php    # AJAX endpoint for live log streaming
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

### 4. Optional: Operator Notices

You can create a simple `operator_notice.txt` file in the root directory. If present and non-empty, its contents will be displayed in a prominent banner on the Live Log page. If the file is blank or missing, no banner will appear.

This can be useful for:

- Scheduled maintenance
- Troubleshooting notices
- Real-time station status updates

---

## 📜 License

This project is free for **non-commercial use only**.

Please credit all original contributors:

- Peter SQ8VPS & Alfredo IZ7BOJ
- Ryan KF6ODE
- Modernized by **VA3KWJ**

---

## 📎 Notes

- No external JS or PHP libraries required. Live updates use native Server-Sent Events (SSE).
- QRZ and APRS-IS lookups are based on the callsign string and use direct linking.
- Reverse geolocation (if used) leverages Nominatim — respect usage limits.
- Reverse geolocation only resolves to local metro area, suburbs may not resolve
- Log rotation limitations, see below

---

### 🔁 APRX Log Rotation Notice

**Heads up:** APRX rotates its log files automatically based on size or time (depending on your system’s logrotate configuration). As a result:

- The visible data in the dashboard will change over time
- Historical data may no longer be available once the log rotates
- For high-traffic stations, the "Last 7 Days" or "All" view may only reflect what’s still in the current `.log` file

To increase retention, you can:
- Adjust your system's `logrotate` policy
- Symlink or configure APRX to write to a larger or persistent log file
- Periodically archive logs yourself if needed
