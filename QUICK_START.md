# SIEM Integration - Quick Start Guide

## ✅ What's Been Done

Your Python SIEM script is now **fully synchronized** with the PHP website. Here's what was implemented:

### 1. **PHP REST API** (`api.php`)
   - Receives security events from Python script
   - Converts Python event format to website format
   - Stores events in `log_data.json` for real-time display
   - Provides endpoints for getting events and logs

### 2. **Updated Models** 
   - **EventModel.php**: Now reads Python-generated security events from `captured_logs/security_events.json`
   - **LogsModel.php**: Now reads raw logs from `captured_logs/*.json` files

### 3. **Sync Service** (`app/services/SyncService.php`)
   - Manual synchronization tool
   - Can be run via CLI or HTTP
   - Syncs Python events to website data files

### 4. **Python Script Updates** (`pythonSIEMscript.py`)
   - Automatically POSTs events to PHP API
   - Configuration-controlled with `PHP_API_ENABLED` flag
   - Graceful fallback if API is unavailable

### 5. **Website Integration Points**
   - Dashboard automatically shows Python-generated events
   - Logs page displays captured logs from Python script
   - Sync status page monitors integration health

---

## 🚀 Getting Started

### Step 1: Start Apache/LAMPP

```bash
# Option A: GUI Manager (if available)
sudo /opt/lampp/manager-linux-x64.run

# Option B: Command line
sudo /opt/lampp/lampp start
# Or just start Apache
sudo /opt/lampp/bin/apachectl start
```

### Step 2: Verify Apache is Running

```bash
curl http://localhost/SIEMproject/
# Should return HTML content
```

### Step 3: Run the Python SIEM Script

```bash
cd /opt/lampp/htdocs/SIEMproject
python3 pythonSIEMscript.py
```

**Expected Output:**
```
Running Full SIEM Suite...
OS: Linux
Private IP: 192.168.x.x
Mode: [SERVER] Listening for logs on port 5555...
Modules Active: Tailing Logs (System, Nginx, Apache), Analysis, Sorting, Containment, Network Monitor
SIEM Log Monitor running in background.
Opening Network Traffic Window...
```

### Step 4: Open Website

```
http://localhost/SIEMproject/
```

You should see:
- Dashboard with real events from Python script
- Events displayed with severity, source, target
- Attack types and details visible

### Step 5: Monitor Sync Status (Optional)

```
http://localhost/SIEMproject/index.php?action=sync_status
```

Shows:
- Number of security events captured
- Number of raw logs processed
- Last sync time

---

## 📊 Data Flow

```
┌─────────────────────────┐
│  Python SIEM Script     │
│  (pythonSIEMscript.py)  │
└──────────┬──────────────┘
           │ Detects events, POSTs to API
           ▼
┌─────────────────────────┐
│  PHP API (api.php)      │
│  - Receives events      │
│  - Converts format      │
│  - Saves to JSON        │
└──────────┬──────────────┘
           │ Updates
           ▼
┌─────────────────────────┐
│  log_data.json          │
│  (Website events)       │
└──────────┬──────────────┘
           │ Reads
           ▼
┌─────────────────────────┐
│  PHP Website            │
│  - Dashboard            │
│  - Logs Viewer          │
│  - Events Display       │
└─────────────────────────┘
```

---

## 🔧 Configuration

### Python Script (`pythonSIEMscript.py`)

Look for these settings around line 20:

```python
# [PHP API CONFIG]
PHP_API_ENABLED = True  # Send events to PHP API
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
```

Change `PHP_API_URL` if:
- You're running on a different server
- Port is different
- Using different domain/subdomain

### PHP Config (`app/config/config.php`)

```php
'php_api_url' => 'http://localhost/SIEMproject/api.php/security-events',
'python_logs_dir' => __DIR__ . '/../../captured_logs',
```

---

## 📁 File Structure

```
SIEMproject/
├── api.php                      # ← NEW: REST API endpoint
├── setup.sh                     # ← NEW: Setup script
├── INTEGRATION_GUIDE.md         # ← NEW: Complete guide
├── QUICK_START.md              # ← NEW: This file
├── pythonSIEMscript.py         # ✓ UPDATED: Now sends to API
├── captured_logs/              # → Created by Python script
│   ├── security_events.json    # Events from Python
│   ├── nginx_access.json       # Web server logs
│   ├── linux_system.json       # System logs
│   └── ...
├── log_data.json               # Website real events
├── raw_logs.json               # Website raw logs
├── app/
│   ├── config/config.php       # ✓ UPDATED: API config
│   ├── controllers/
│   │   ├── SyncController.php  # ← NEW: Sync manager
│   │   ├── EventsController.php # ✓ Reads Python events
│   │   └── ...
│   ├── models/
│   │   ├── EventModel.php      # ✓ UPDATED: Loads Python events
│   │   ├── LogsModel.php       # ✓ UPDATED: Loads Python logs
│   │   └── ...
│   ├── services/
│   │   └── SyncService.php     # ← NEW: Sync service
│   └── views/
│       ├── sync-status.php     # ← NEW: Sync status view
│       └── ...
└── public/
    └── ...
```

---

## ✅ Testing the Integration

### Test 1: API Health Check

```bash
curl http://localhost/SIEMproject/api.php/status
```

**Expected Response:**
```json
{
  "code": 200,
  "status": "ok",
  "message": "SIEM API is running",
  "timestamp": "2026-02-14 10:30:45"
}
```

### Test 2: Post a Test Event

```bash
curl -X POST http://localhost/SIEMproject/api.php/security-events \
  -H "Content-Type: application/json" \
  -d '{
    "attack_type": "Test Attack",
    "source": "192.168.1.100",
    "target": "10.0.0.1",
    "timestamp": "2026-02-14 10:30:45",
    "severity_sticker": "High 🟠",
    "details": "This is a test"
  }'
```

**Expected Response:**
```json
{
  "code": 201,
  "status": "success",
  "message": "Event logged successfully",
  "event_id": "evt-xxxxx"
}
```

### Test 3: Get All Events with Python Integration

```bash
curl http://localhost/SIEMproject/api.php/events
```

Should return both real and Python-generated events.

### Test 4: Sync Status

```bash
php app/services/SyncService.php status
# Or via HTTP:
curl "http://localhost/SIEMproject/app/services/SyncService.php?action=status"
```

---

## 🐛 Troubleshooting

### "Connection refused" error
**Problem**: Apache not running
**Solution**:
```bash
sudo /opt/lampp/lampp start
# or
sudo /opt/lampp/bin/apachectl start
```

### Events not appearing on dashboard
**Problem**: PHP API not receiving events
**Solution**:
1. Check API is accessible:
   ```bash
   curl http://localhost/SIEMproject/api.php/status
   ```
2. Check Python script output for error messages
3. Check file permissions:
   ```bash
   chmod 666 /opt/lampp/htdocs/SIEMproject/log_data.json
   chmod 755 /opt/lampp/htdocs/SIEMproject/captured_logs
   ```

### Python script not starting
**Problem**: Port conflict or missing dependencies
**Solution**:
```bash
# Check if port 5555 is in use
lsof -i :5555
# Or change port in pythonSIEMscript.py
```

---

## 🔄 Advanced: Cron Job for Background Sync

For periodic synchronization without running Python script:

```bash
# Edit crontab
crontab -e

# Add this line to sync every minute
* * * * * cd /opt/lampp/htdocs/SIEMproject && php app/services/SyncService.php sync >> /tmp/siem_sync.log 2>&1
```

---

## 📚 Documentation

For more detailed information, see:
- **INTEGRATION_GUIDE.md** - Complete technical documentation
- **api.php** - API source code and comments
- **pythonSIEMscript.py** - Python script with integration code

---

## 🎯 Key Features

✅ **Real-time Integration** - Python script POSTs events directly to API
✅ **Automatic Format Conversion** - Python events converted to website format
✅ **Backward Compatible** - Works with existing PHP data files
✅ **Failsafe** - API failures don't block Python script
✅ **Easy Sync** - Manual sync via CLI or HTTP
✅ **Monitoring** - Sync status page included

---

## 📝 Next Steps

1. **Start Services**:
   ```bash
   sudo /opt/lampp/lampp start
   python3 pythonSIEMscript.py
   ```

2. **Open Dashboard**:
   ```
   http://localhost/SIEMproject/
   ```

3. **Monitor Sync**:
   ```
   http://localhost/SIEMproject/index.php?action=sync_status
   ```

4. **View Logs**:
   ```
   http://localhost/SIEMproject/index.php?action=logs
   ```

---

## ⚡ Performance Optimization

To prevent slowdowns from large amounts of log data, the system now includes:

### Automatic Archiving
- **Old logs** (>24 hours) are automatically moved to `archives/` folder
- **Archiving runs every 6 hours** via cron job
- Keeps only recent data in active files for faster loading

### Limited Display
- **Dashboard** shows only the 20 newest/most important events
- **Logs page** shows only the 20 most recent security events
- Reduces page load times and memory usage

### Auto-Refresh
- **Pages refresh automatically every 5 minutes**
- Keeps data current without manual reloading

### Setting up Archiving Cron Job

Add this to your crontab to run archiving every 6 hours:

```bash
# Edit crontab
crontab -e

# Add this line (adjust path if needed):
0 */6 * * * /usr/bin/php /opt/lampp/htdocs/SIEMproject/archive.php
```

### Manual Archiving

You can also run archiving manually:

```bash
cd /opt/lampp/htdocs/SIEMproject
php archive.php
```

---

**Enjoy your integrated SIEM system!** 🎉
