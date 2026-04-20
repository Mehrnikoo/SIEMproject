# SIEM Project - Complete Setup & Configuration Guide

> **START HERE** - This is the only guide you need to get the SIEM system up and running on your system.

---

## 📋 Table of Contents

1. [What You're Installing](#what-youre-installing)
2. [System Requirements](#system-requirements)
3. [Quick Start (5 Minutes)](#quick-start-5-minutes)
4. [Complete Setup](#complete-setup)
5. [Verification & Testing](#verification--testing)
6. [System Architecture](#system-architecture)
7. [Configuration Details](#configuration-details)
8. [API Reference](#api-reference)
9. [Features Overview](#features-overview)
10. [Troubleshooting](#troubleshooting)

---

## What You're Installing

This is a **Python-PHP SIEM (Security Information Event Management) system** that:

✅ **Monitors your system** for security threats in real-time
✅ **Detects attacks** (SQL Injection, XSS, brute force, etc.)
✅ **Displays events** on a web dashboard with geolocation
✅ **Correlates logs** showing raw logs for each security event
✅ **Provides real-time sync** between Python backend and PHP frontend

### What It Does

- **Python SIEM Script**: Monitors Apache/Nginx logs, system logs, and network traffic
- **Attack Detection**: Identifies SQL Injection, XSS, brute force attempts, port scans, etc.
- **Web Dashboard**: Beautiful geolocation-based event visualization
- **Logs Viewer**: Browse and search all captured security events
- **REST API**: Machine-readable event data for integration

---

## System Requirements

### Hardware
- CPU: 2+ cores recommended
- RAM: 2GB minimum (4GB recommended)
- Disk: 500MB free space

### Software
- **Linux** (Ubuntu, CentOS, or similar)
- **LAMPP** (Apache + PHP + MySQL) - installed at `/opt/lampp/`
- **Python 3.6+** with libraries:
  - `subprocess`, `json`, `socket`, `re` (built-in)
  - `requests` (for API calls)
  - `geoip2` (for geolocation - optional)

### Verify Installation
```bash
# Check Apache
ls -la /opt/lampp/

# Check Python
python3 --version

# Check pip
pip3 --version
```

---

## Quick Start (5 Minutes)

Choose your installation method:

### Quick Start with Docker (Easiest)

```bash
# 1. In the project directory (where Dockerfile is)
docker build -t siem .

# 2. Run the container
docker run -p 80:80 -p 443:443 -p 3306:3306 siem

# 3. Open website
# http://localhost/SIEMproject/
```

**Done!** The system is running inside Docker with:
- Apache, PHP, MySQL pre-configured
- Python SIEM script running
- All dependencies installed
- Ready to detect threats

---

### Quick Start with Native LAMPP (Linux Only)

### Step 1: Start Apache/LAMPP
```bash
sudo /opt/lampp/lampp start
# Or just Apache:
sudo /opt/lampp/bin/apachectl start
```

### Step 2: Navigate to Project
```bash
cd /opt/lampp/htdocs/SIEMproject
```

### Step 3: Start Python SIEM
```bash
python3 pythonSIEMscript.py
```

### Step 4: Open Website
```
http://localhost/SIEMproject/
```

**Done!** The Python script is now:
- Monitoring your system logs
- Detecting threats
- Sending events to the PHP API
- Displaying them on the dashboard

---

## Complete Setup

### Step 1: Verify LAMPP Installation

```bash
# Check if LAMPP is installed
ls -la /opt/lampp/

# Check PHP is working
sudo /opt/lampp/bin/php -v

# Check Apache config files exist
ls /opt/lampp/etc/httpd/conf/
```

### Step 2: Verify Project Files

```bash
cd /opt/lampp/htdocs/SIEMproject

# Check main files
ls -la *.php *.py *.md
# You should see: api.php, index.php, pythonSIEMscript.py, etc.

# Check subdirectories
ls -la app/ public/ captured_logs/
```

### Step 3: Install Python Dependencies

```bash
# Install required Python packages
pip3 install requests geoip2 pycountry

# Or if pip3 has permission issues:
sudo pip3 install requests geoip2 pycountry
```

### Step 4: Create/Verify Data Directories

```bash
# Make sure these directories exist
mkdir -p /opt/lampp/htdocs/SIEMproject/captured_logs
mkdir -p /opt/lampp/htdocs/SIEMproject/archives

# Give Apache write permissions
sudo chown -R www-data:www-data /opt/lampp/htdocs/SIEMproject/

# Make sure JSON files are writable
chmod 666 /opt/lampp/htdocs/SIEMproject/*.json
```

### Step 5: Configure PHP

**File**: `app/config/config.php`

Key settings (already configured, verify they match):
```php
'php_api_url' => 'http://localhost/SIEMproject/api.php/security-events',
'log_data' => BASE_PATH . '/log_data.json',
'sim_data' => BASE_PATH . '/sim_data.json',
```

If running on a different server, update the URLs:
```php
'php_api_url' => 'http://YOUR_SERVER_IP/SIEMproject/api.php/security-events',
```

### Step 6: Configure Python Script

**File**: `pythonSIEMscript.py` (around line 20)

Key settings (already configured):
```python
# [PHP API CONFIG]
PHP_API_ENABLED = True
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
```

If running on different server:
```python
PHP_API_URL = "http://YOUR_SERVER_IP/SIEMproject/api.php/security-events"
```

---

## Verification & Testing

### 1. Verify Apache is Running

```bash
# Check if Apache is listening
sudo netstat -tlnp 2>/dev/null | grep apache2
# Or for LAMPP Apache:
sudo netstat -tlnp 2>/dev/null | grep -E ':(80|443)'

# You should see port 80 and 443 LISTEN
```

### 2. Verify Website Works

```bash
# Test basic connectivity
curl http://localhost/SIEMproject/
# Should return HTML content (no errors)

# Check API endpoint
curl http://localhost/SIEMproject/api.php/events
# Should return JSON with events array
```

### 3. Test Python Script Connection

```bash
cd /opt/lampp/htdocs/SIEMproject

# Run Python script (should connect to API)
python3 pythonSIEMscript.py

# Look for output showing it's connected and monitoring
```

### 4. Check Event Flow

```bash
# In another terminal, trigger a test attack
curl "http://localhost/SIEMproject/login.php?id=1 UNION SELECT NULL,username,password FROM users"

# Check if event appears in API response
curl -s http://localhost/SIEMproject/api.php/events | python3 -m json.tool | head -50

# Should show an SQL Injection event in the response
```

### 5. View on Dashboard

Open browser to: `http://localhost/SIEMproject/`

You should see:
- **Dashboard tab**: Map with event markers (red for high severity)
- **Event Details panel**: Shows selected event info
- **Raw Logs section**: Shows associated raw logs
- **Logs tab**: Browse all events with expandable raw logs
- **Sync Status tab**: Monitor system integration

---

## System Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                    SIEM SYSTEM OVERVIEW                      │
└──────────────────────────────────────────────────────────────┘

┌─────────────────────────────────┐
│  MONITORING LAYER               │
│  (Python - pythonSIEMscript.py) │
│                                 │
│  • Tails Apache/Nginx logs      │
│  • Monitors system logs         │
│  • Watches network traffic      │
│  • Detects attack patterns      │
└────────────┬────────────────────┘
             │
             │ Detected events
             │ POSTs JSON payload
             │
┌────────────▼────────────────────┐
│  API LAYER                      │
│  (PHP - api.php)                │
│                                 │
│  • Receives event POST          │
│  • Validates data               │
│  • Converts format              │
│  • Stores to log_data.json      │
└────────────┬────────────────────┘
             │
             │ Updates
             │
┌────────────▼────────────────────┐
│  DATA LAYER                     │
│  (JSON Files)                   │
│                                 │
│  • log_data.json (events)       │
│  • captured_logs/*.json (logs)  │
│  • security_events.json (sim)   │
└────────────┬────────────────────┘
             │
             │ Reads
             │
┌────────────▼────────────────────┐
│  PRESENTATION LAYER             │
│  (PHP Website)                  │
│                                 │
│  • Dashboard (map view)         │
│  • Logs Viewer (table view)     │
│  • Event Details (popup)        │
│  • Raw Logs (expandable)        │
│  • Sync Status (monitor)        │
└─────────────────────────────────┘
```

### Data Flow Example

**When an attack is detected:**

1. **Python Script** reads Apache log: `GET /login.php?id=1 UNION SELECT NULL,username,password FROM users`
2. **Pattern Matching** identifies SQL Injection attack
3. **Event Created** with fields: timestamp, severity, source_ip, attack_type, details
4. **POST to API** sends JSON: `POST /api.php/security-events` with event payload
5. **API Validates** event data and loads related raw logs
6. **Stores Event** in `log_data.json`
7. **Website Reads** the updated `log_data.json`
8. **Dashboard Updates** showing new attack marker on map
9. **Raw Logs Attached** to event showing all related log lines
10. **User Sees** the attack on dashboard with full context

---

## Configuration Details

### Python Script Configuration

**File**: `pythonSIEMscript.py` (line ~20)

```python
# [PHP API CONFIG]
PHP_API_ENABLED = True                    # Enable/disable API posting
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
PHP_API_TIMEOUT = 5                       # API request timeout (seconds)
API_RETRY_ATTEMPTS = 3                    # Retries if API fails
```

### PHP Configuration

**File**: `app/config/config.php`

```php
return [
    // API Configuration
    'php_api_url' => 'http://localhost/SIEMproject/api.php/security-events',
    
    // Data file paths
    'data_files' => [
        'log_data' => BASE_PATH . '/log_data.json',
        'sim_data' => BASE_PATH . '/sim_data.json',
        'security_events' => BASE_PATH . '/captured_logs/security_events.json',
    ],
    
    // Severity levels
    'severity_map' => [
        'Critical' => '#ff0000',  // Red
        'High' => '#ff6600',      // Orange
        'Medium' => '#ffcc00',    // Yellow
        'Low' => '#00cc00',       // Green
    ],
];
```

### Environment Variables (Optional)

If you need to change settings at runtime:

```bash
# Set Python API URL
export PHP_API_URL="http://your-server.com/SIEMproject/api.php/security-events"

# Then run script
python3 pythonSIEMscript.py
```

---

## API Reference

### Security Events Endpoint

**POST** `/api.php/security-events`

Receive security events from Python script.

**Request Body**:
```json
{
  "timestamp": "2026-04-19 14:30:45",
  "severity": "High",
  "attack_type": "SQL Injection",
  "source": "192.168.1.50",
  "target": "192.168.119.107",
  "details": "Payload: /login.php?id=1 UNION SELECT...",
  "formatted_log": "SQL Injection detected from 192.168.1.50"
}
```

**Response**:
```json
{
  "code": 200,
  "status": "success",
  "message": "Event saved successfully",
  "event_id": "evt_12345"
}
```

### Get All Events Endpoint

**GET** `/api.php/events`

Retrieve all security events with associated raw logs.

**Response**:
```json
{
  "code": 200,
  "status": "success",
  "count": 6,
  "events": [
    {
      "id": "sim-log-entry-1",
      "timestamp": "2026-04-19 13:27:24",
      "attack_type": "SQL Injection",
      "source": "192.168.1.50",
      "severity": "High",
      "raw_logs": [
        "GET /login.php?id=1",
        "GET /login.php?id=1",
        ...
      ]
    }
  ]
}
```

### Get Logs Endpoint

**GET** `/api.php/logs`

Retrieve all captured raw logs.

**Response**:
```json
{
  "code": 200,
  "status": "success",
  "count": 32,
  "logs": [
    {
      "timestamp": "2026-02-24 14:50:00",
      "log_type": "apache_access",
      "source_ip": "192.168.1.50",
      "data": {...}
    }
  ]
}
```

### Health Check Endpoint

**GET** `/api.php/status`

Check system health and connectivity.

**Response**:
```json
{
  "code": 200,
  "status": "healthy",
  "services": {
    "api": "online",
    "database": "online",
    "python_script": "running"
  }
}
```

---

## Features Overview

### 1. Real-Time Attack Detection

**What it does**: Monitors logs and detects security threats in real-time

**Supported Attack Types**:
- SQL Injection
- XSS (Cross-Site Scripting)
- Brute Force Attempts
- Port Scanning
- Command Injection
- Directory Traversal
- XML External Entity (XXE)

**How to test**:
```bash
# Test SQL Injection detection
curl "http://localhost/SIEMproject/login.php?id=1 UNION SELECT NULL,username,password FROM users"

# Test XSS detection
curl "http://localhost/SIEMproject/search.php?q=<script>alert(1)</script>"

# Check dashboard - new event should appear within seconds
```

### 2. Raw Logs Association

**What it does**: Automatically links each security event with all related raw logs

**How it works**:
1. Event detected with source IP: `192.168.1.50`
2. System searches `captured_logs/` for logs from that IP
3. All matching logs attached to event: `event['raw_logs'] = [...]`
4. Dashboard and Logs Viewer display them

**How to view**:
- **Dashboard**: Click event → scroll down to "Raw Logs" section
- **Logs Viewer**: Click event row → expand to see associated raw logs

### 3. Geolocation Mapping

**What it does**: Shows attack origin location on world map

**How it works**:
1. Event contains source IP: `192.168.1.50`
2. System performs GeoIP lookup (if external IP)
3. Gets country, city, latitude, longitude
4. Displays marker on Leaflet.js map

**Features**:
- Red markers = External (attacker) IPs
- Blue markers = Internal IPs
- Click marker = view event details
- Hover = show event summary

### 4. Logs Viewer

**What it does**: Browse all security events in table format

**Features**:
- Filter by severity (Critical, High, Medium, Low)
- Sort by timestamp, source IP, attack type
- Click row = expand to see raw logs
- Search functionality
- Auto-refresh every 5 minutes

### 5. Sync Status Monitor

**What it does**: Monitor integration health between Python and PHP

**Shows**:
- Total security events detected
- Total raw logs captured
- Last sync time
- Connection status
- Event throughput

---

## Troubleshooting

### Problem: Website shows "Connection Refused"

**Cause**: Apache/LAMPP not running

**Solution**:
```bash
# Start LAMPP
sudo /opt/lampp/lampp start

# Or start just Apache
sudo /opt/lampp/bin/apachectl start

# Verify it's running
sudo netstat -tlnp 2>/dev/null | grep -E ':(80|443)'
```

### Problem: "No events showing on dashboard"

**Cause**: Python script not running or not posting events

**Solution**:
```bash
# 1. Check Python script is running
ps aux | grep pythonSIEMscript.py

# 2. Start it if not running
cd /opt/lampp/htdocs/SIEMproject
python3 pythonSIEMscript.py

# 3. Verify API endpoint works
curl http://localhost/SIEMproject/api.php/events

# 4. Check for errors in script output
# Look for ERROR or FAILED messages
```

### Problem: API returns empty events

**Cause**: Events saved but logs not attached

**Solution**:
```bash
# 1. Verify log files exist
ls -la /opt/lampp/htdocs/SIEMproject/captured_logs/

# 2. Check log files are valid JSON
python3 -m json.tool /opt/lampp/htdocs/SIEMproject/captured_logs/apache_access.json

# 3. Check event source IP matches log file IPs
grep "192.168.1.50" /opt/lampp/htdocs/SIEMproject/captured_logs/apache_access.json
```

### Problem: "Permission Denied" errors

**Cause**: Files not readable by Apache process

**Solution**:
```bash
# Give Apache write permissions to project
sudo chown -R www-data:www-data /opt/lampp/htdocs/SIEMproject/

# Make data files writable
chmod 666 /opt/lampp/htdocs/SIEMproject/*.json

# Make directories writable
chmod 777 /opt/lampp/htdocs/SIEMproject/captured_logs/
chmod 777 /opt/lampp/htdocs/SIEMproject/archives/
```

### Problem: "Connection to API failed" in Python script

**Cause**: API URL incorrect or Apache not responding

**Solution**:
```bash
# 1. Test API endpoint manually
curl -X POST http://localhost/SIEMproject/api.php/security-events \
  -H "Content-Type: application/json" \
  -d '{"timestamp":"2026-04-19 14:00:00","severity":"High","attack_type":"Test"}'

# 2. Check Python API URL is correct
grep "PHP_API_URL" /opt/lampp/htdocs/SIEMproject/pythonSIEMscript.py

# 3. Test connectivity
python3 -c "import requests; requests.get('http://localhost/SIEMproject/')"
```

### Problem: Raw logs not showing for events

**Cause**: Event source IP doesn't match any logs

**Solution**:
```bash
# 1. Check event has source IP
curl -s http://localhost/SIEMproject/api.php/events | \
  python3 -c "import sys,json; e=json.load(sys.stdin); print(e['events'][0] if e.get('events') else 'No events')"

# 2. Check log files contain that IP
grep -r "192.168.1.50" /opt/lampp/htdocs/SIEMproject/captured_logs/

# 3. If no logs, generate test logs by triggering attacks
curl "http://localhost/SIEMproject/login.php?id=1 UNION SELECT NULL,username,password FROM users"
```

### Problem: Dashboard map not showing

**Cause**: JavaScript error or Leaflet.js not loaded

**Solution**:
```bash
# 1. Check browser console for errors (F12)
# Look for red error messages

# 2. Check map.js is loading
curl http://localhost/SIEMproject/public/assets/js/map.js | head -20

# 3. Check Leaflet CDN is accessible
curl https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js | head

# 4. Refresh browser and clear cache
# Ctrl+Shift+Delete (or Cmd+Shift+Delete on Mac)
```

### Problem: Logs are huge/slow to load

**Cause**: Too many events/logs accumulated

**Solution**:
```bash
# Archive old logs
mv /opt/lampp/htdocs/SIEMproject/log_data.json \
   /opt/lampp/htdocs/SIEMproject/archives/log_data_$(date +%s).json.bak

# Create empty data files
echo "[]" > /opt/lampp/htdocs/SIEMproject/log_data.json
echo "[]" > /opt/lampp/htdocs/SIEMproject/captured_logs/security_events.json

# Fix permissions
sudo chown www-data:www-data /opt/lampp/htdocs/SIEMproject/log_data.json
chmod 666 /opt/lampp/htdocs/SIEMproject/log_data.json
```

### Still Having Issues?

1. **Check the logs**:
   ```bash
   # Apache error log
   sudo tail -f /opt/lampp/logs/error_log
   
   # PHP error log
   sudo tail -f /opt/lampp/logs/php_error.log
   ```

2. **Enable debug mode** in Python script:
   ```python
   # Around line 50 in pythonSIEMscript.py
   DEBUG = True  # Shows detailed output
   ```

3. **Check file permissions**:
   ```bash
   ls -la /opt/lampp/htdocs/SIEMproject/
   ls -la /opt/lampp/htdocs/SIEMproject/captured_logs/
   ```

---

## Files Modified/Created

### New Files Created
- `api.php` - REST API endpoint
- `app/services/SyncService.php` - Sync service
- `app/controllers/SyncController.php` - Sync controller
- `app/views/sync-status.php` - Sync status page
- `RAW_LOGS_IMPLEMENTATION.md` - Raw logs feature docs
- `setup.sh` - Automated setup script

### Files Modified
- `pythonSIEMscript.py` - Added API integration
- `app/models/EventModel.php` - Added Python event loading
- `app/models/LogsModel.php` - Added raw log loading
- `app/config/config.php` - Added API settings
- `index.php` - Added sync routes
- `app/views/logs.php` - Added raw logs display
- `public/assets/js/map.js` - Added raw logs formatting

---

## Next Steps

### 1. Deploy to Production

```bash
# Update PHP_API_URL to your server domain
sudo nano /opt/lampp/htdocs/SIEMproject/pythonSIEMscript.py
# Update: PHP_API_URL = "http://your-domain.com/SIEMproject/..."

# Update PHP config
sudo nano /opt/lampp/htdocs/SIEMproject/app/config/config.php
# Update: 'php_api_url' => 'http://your-domain.com/SIEMproject/...'

# Restart services
sudo /opt/lampp/lampp restart
```

### 2. Enable Auto-Start

```bash
# Create systemd service for Python script
sudo nano /etc/systemd/system/siem-python.service

# Add content:
[Unit]
Description=Python SIEM Script
After=apache2.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/lampp/htdocs/SIEMproject
ExecStart=/usr/bin/python3 /opt/lampp/htdocs/SIEMproject/pythonSIEMscript.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target

# Enable service
sudo systemctl daemon-reload
sudo systemctl enable siem-python.service
sudo systemctl start siem-python.service
```

### 3. Monitor System

```bash
# Check if services are running
sudo systemctl status apache2
sudo systemctl status siem-python

# View logs
sudo journalctl -u siem-python -f
```

### 4. Backup Configuration

```bash
# Backup important files
mkdir -p ~/siem_backup
cp -r /opt/lampp/htdocs/SIEMproject/ ~/siem_backup/

# Schedule daily backups
(crontab -l 2>/dev/null; echo "0 2 * * * tar -czf ~/siem_backup/siem_$(date +\%Y\%m\%d).tar.gz /opt/lampp/htdocs/SIEMproject/") | crontab -
```

---

## Support & Additional Resources

- **Configuration Issues**: Check `app/config/config.php`
- **Python Errors**: See Python script debug output
- **API Issues**: Test endpoints with `curl` commands
- **Database**: All data in JSON files (no SQL database needed)
- **Web Server**: Using LAMPP's Apache with PHP

---

## Summary

You now have a **complete SIEM system** that:
- ✅ Monitors security threats in real-time
- ✅ Displays attacks on a geolocation map
- ✅ Associates raw logs with events
- ✅ Provides web dashboard and logs viewer
- ✅ Includes REST API for integration
- ✅ Auto-syncs Python backend with PHP frontend

**Next action**: Follow the [Quick Start](#quick-start-5-minutes) section to get running immediately!
