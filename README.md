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
- 📈 Interface-based RX/TX statistics with chart view
- 🗂 Modular PHP logic (dashboard, stats, meta, filters)
- 🔽 Dynamic dropdowns for source, interface, and date range

---

## ✅ Tested Environment

- **Debian 12 (Bookworm)**
- **Debian 12 (Bookworm)** on Raspberry Pi 3B (arm64 headless image)
- **Lighttpd 1.4.x** with PHP-FPM
- **PHP 8.2** (specifically tested on 8.2.15)
- **APRX** from Debian repo

---

## 📂 File Structure

```
/var/www/html/
├── index.php		# Dashboard summary view
├── live.php		# Live log viewer
├── logchk.php		# Test logfile permissions (for debug)
├── config.php		# Configuration file paths
├── functions.php	# Utility functions
├── footer.php		# Universal footer
├── stats.php		# Dynamic RX/TX stats per interface
├── operator_notice.txt	# Optional message displayed at top of live log
├── api/
│ └── logfetch.php	# AJAX endpoint for live log streaming
├── assets/
│ ├── css/
│ │ └── style.css	# Modern dark CSS theme
│ ├── js/
│ │ └── live-log.js	# JavaScript for real-time log rendering
│ ├── img/
│ │ └── aprslogo.png	# Logo shown in header
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
---

## 🆕 Recent Improvements

- `stats.php` now uses a centralized `generateStats()` function, enabling consistent RX/TX analysis and interface bucketing across time ranges.
- Operator notices (`operator_notice.txt`) now support basic Markdown formatting:
  - `# Heading`, `**bold**`, `*italic*`, `[link text](https://example.com)`
  - Plain URLs (e.g., `https://aprs.fi`) are auto-linked
  - Secure rendering via internal parser (no raw HTML allowed)
- Link colors inside the `.notice-box` have been updated for visibility on yellow background
- Live log viewer improvements:
  - DOM updates only occur when content has changed
  - Log lines capped to 100 for browser performance
  - Scroll position is preserved unless user is near the bottom (auto-scroll)
  - Filtered views are retained for export

### 🔐 Security & Input Handling

- All `$_GET` input is now validated with `filter_input()` or strict `in_array()` checks
- Log file access is restricted to `rf` and `daemon` keys only — no raw path access
- The operator notice is sanitized using `htmlspecialchars()` before display to prevent HTML/JS injection
- Only a safe subset of Markdown is allowed in operator messages (no raw tags, scripts, or HTML)

---

## 📜 License

This project is free for **non-commercial use only**.

Please credit all original contributors:

- Piotr SQ8VPS [GitHub](https://github.com/sq8vps/aprx-simplewebstat)
- Alfredo IZ7BOJ [GitHub](https://github.com/IZ7BOJ/aprx-simplewebstat)
- promo766 [GitHub](https://github.com/promo776/aprx-simplewebstat)
- Modernized by **VA3KWJ**

---

## 📎 Notes

- Live updates use native Server-Sent Events (SSE).
- Chart rendering utilizes [jsdelivr.com chart.js](https://www.jsdelivr.com/package/npm/chart.js)
- QRZ and APRS-IS lookups are based on the callsign string and use direct linking.
- Reverse geolocation (if used) leverages Nominatim — respect usage limits.
- Reverse geolocation only resolves to local metro area, suburbs may not resolve
- Log rotation limitations, see below
- Comments in aprx.conf are ignored during parsing
- All timestamps are shown in local server time (not UTC)
- PHP logic is now modularized in `functions.php` (e.g., dashboard data, stats generation)
- `getStationMeta()` will gracefully fall back to configured latitude/longitude
- `generateStats()` powers the RX/TX charts in `stats.php` and supports time-based bucketing
- Dropdowns and filters reflect the live state and persist across requests


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
