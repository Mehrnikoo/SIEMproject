# Python SIEM Script ↔ PHP Website Integration Guide

## Overview

This integration synchronizes the Python SIEM monitoring script with the PHP website, allowing real-time security event detection and visualization on the dashboard.

## Architecture

```
┌─────────────────────────────┐
│  Python SIEM Script         │
│  - Monitors system logs     │
│  - Detects security events │
│  - Stores to captured_logs/ │
└──────────┬──────────────────┘
           │ POST events
           ▼
┌─────────────────────────────┐
│  PHP API (api.php)          │
│  - Receives events          │
│  - Converts format          │
│  - Saves to log_data.json   │
└──────────┬──────────────────┘
           │ Syncs
           ▼
┌─────────────────────────────┐
│  PHP Website                │
│  - Displays on dashboard    │
│  - Shows logs               │
│  - Geocodes events          │
└─────────────────────────────┘
```

## Components

### 1. **Python Script Integration**

**File**: `pythonSIEMscript.py`

**Changes Made**:
- Added `PHP_API_ENABLED` flag and `PHP_API_URL` configuration
- Added `send_to_php_api()` method to `Sort_event` class
- Events are automatically POSTed to the PHP API when detected

**Configuration**:
```python
PHP_API_ENABLED = True
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
```

### 2. **PHP REST API**

**File**: `api.php`

**Endpoints**:
- `POST /api.php/security-events` - Receive events from Python script
- `GET /api.php/events` - Get all events (real + Python-generated)
- `GET /api.php/logs` - Get raw logs from captured_logs/
- `GET /api.php/status` - Health check

**Event Format Conversion**:
The API automatically converts Python events to website format:
```
Python format:
{
  "id": "sim-log-entry-1",
  "timestamp": "2026-02-14 10:30:45",
  "severity_sticker": "Critical 🔴",
  "attack_type": "DDoS Attack",
  "source": "192.168.1.100",
  "target": "10.0.0.1",
  "details": "High traffic detected"
}

Website format:
{
  "id": "sim-log-entry-1",
  "timestamp": "2026-02-14 10:30:45",
  "severity": "Critical",
  "type": "INTERNAL",
  "attack_type": "DDoS Attack",
  "source": "192.168.1.100",
  "target": "10.0.0.1",
  "details": "High traffic detected",
  "country": "Unknown",
  "from_python": true
}
```

### 3. **Updated Models**

**EventModel.php**:
- `loadRealEvents()` now includes Python-generated security events
- `loadPythonSecurityEvents()` reads from captured_logs/security_events.json
- Automatic format normalization

**LogsModel.php**:
- `loadRawLogs()` now includes Python-generated raw logs
- `loadPythonCapturedLogs()` reads from captured_logs/*.json files
- Seamless merging with existing logs

### 4. **Sync Service**

**File**: `app/services/SyncService.php`

**Purpose**: Synchronize Python-generated logs to website data files

**Usage**:
```bash
# Via CLI
php app/services/SyncService.php sync
php app/services/SyncService.php status

# Via URL
http://localhost/SIEMproject/app/services/SyncService.php?action=sync
http://localhost/SIEMproject/app/services/SyncService.php?action=status
```

**Methods**:
- `syncAll()` - Sync security events and raw logs
- `syncSecurityEvents()` - Sync captured_logs/security_events.json to log_data.json
- `syncRawLogs()` - Sync captured_logs/*.json to raw_logs.json
- `getStatus()` - Get sync statistics

### 5. **Sync Controller**

**File**: `app/controllers/SyncController.php`

**Routes**:
- `?action=sync_status` - Display sync status page
- `?action=sync` - Trigger synchronization (JSON response)

## Data Flow

### Real-Time Flow (Python → PHP API → Website)

1. **Python Script** detects security event
2. **Python Script** POSTs event to `http://localhost/SIEMproject/api.php/security-events`
3. **PHP API** receives, converts, and saves to `log_data.json`
4. **Website** automatically loads updated events on next page load/refresh
5. **Dashboard** displays event with geocoding and severity

### Batch Sync Flow (Background Sync)

1. **Python Script** generates events and saves to `captured_logs/security_events.json`
2. **Cron job** or **manual trigger** runs `SyncService.php`
3. **SyncService** reads captured_logs and syncs to website JSON files
4. **Website** reflects updated event data

## Setup Instructions

### 1. Start the Python SIEM Script

```bash
cd /opt/lampp/htdocs/SIEMproject
python3 pythonSIEMscript.py
```

The script will:
- Start monitoring system logs
- Listen for security events
- POST events to `http://localhost/SIEMproject/api.php/security-events`
- Store events to `captured_logs/security_events.json`

### 2. Verify PHP API

```bash
curl http://localhost/SIEMproject/api.php/status
```

Response:
```json
{
  "code": 200,
  "status": "ok",
  "message": "SIEM API is running",
  "timestamp": "2026-02-14 10:30:45"
}
```

### 3. View Dashboard

Open `http://localhost/SIEMproject/` in your browser

- Real events from Python script appear automatically
- Events show severity, source, target, and attack type
- Geocoding automatically looks up country locations

### 4. Optional: Set Up Cron Sync

For periodic background synchronization, add a cron job:

```bash
# Sync every minute
* * * * * cd /opt/lampp/htdocs/SIEMproject && php app/services/SyncService.php sync
```

Or access via HTTP (e.g., from a monitoring service):

```bash
curl http://localhost/SIEMproject/index.php?action=sync
```

## File Structure

```
SIEMproject/
├── api.php                          # REST API endpoint
├── pythonSIEMscript.py             # Python SIEM script (updated)
├── captured_logs/                   # Python-generated logs (created by Python)
│   ├── security_events.json        # Security events from Python
│   ├── nginx_access.json           # Nginx logs
│   ├── linux_system.json           # Linux system logs
│   └── ...
├── log_data.json                    # Website real events
├── raw_logs.json                    # Website raw logs
├── app/
│   ├── config/
│   │   └── config.php              # Configuration (updated)
│   ├── controllers/
│   │   ├── SyncController.php       # New: Sync controller
│   │   ├── EventsController.php     # Updated
│   │   └── ...
│   ├── models/
│   │   ├── EventModel.php           # Updated: Loads Python events
│   │   ├── LogsModel.php            # Updated: Loads Python logs
│   │   └── ...
│   ├── services/
│   │   └── SyncService.php          # New: Sync service
│   └── views/
│       └── ...
└── public/
    └── ...
```

## Configuration

### PHP Config (`app/config/config.php`)

```php
'python_logs_dir' => __DIR__ . '/../../captured_logs',
'php_api_url' => 'http://localhost/SIEMproject/api.php/security-events',
```

### Python Config (`pythonSIEMscript.py`)

```python
PHP_API_ENABLED = True
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
```

## Monitoring

### Check API Status

```bash
curl http://localhost/SIEMproject/api.php/status
```

### Get All Events

```bash
curl http://localhost/SIEMproject/api.php/events
```

### Get All Raw Logs

```bash
curl http://localhost/SIEMproject/api.php/logs
```

### Get Sync Status

Via CLI:
```bash
php app/services/SyncService.php status
```

Via HTTP:
```bash
curl http://localhost/SIEMproject/app/services/SyncService.php?action=status
```

## Troubleshooting

### Events Not Appearing in Website

1. **Check PHP API is running**:
   ```bash
   curl http://localhost/SIEMproject/api.php/status
   ```

2. **Check Python script is sending events**:
   - Look for POST requests in Apache/Nginx error logs
   - Verify `log_data.json` is being updated

3. **Check file permissions**:
   ```bash
   chmod 755 /opt/lampp/htdocs/SIEMproject/captured_logs
   chmod 666 /opt/lampp/htdocs/SIEMproject/log_data.json
   ```

4. **Check API endpoint**:
   ```bash
   # Test API directly
   curl -X POST http://localhost/SIEMproject/api.php/security-events \
     -H "Content-Type: application/json" \
     -d '{
       "attack_type": "Test Attack",
       "source": "192.168.1.100",
       "target": "10.0.0.1",
       "timestamp": "2026-02-14 10:30:45",
       "details": "Test"
     }'
   ```

### Python Script Error Posting to API

1. **Check URL is correct** in `pythonSIEMscript.py`
2. **Check if LAMPPP is running**:
   ```bash
   sudo /opt/lampp/manager-linux-x64.run
   ```
3. **Check firewall** isn't blocking localhost connections
4. **Enable PHP_API_ENABLED** flag

### File Permissions Issues

```bash
# Fix permissions
chmod 755 /opt/lampp/htdocs/SIEMproject
chmod 755 /opt/lampp/htdocs/SIEMproject/captured_logs
chmod 666 /opt/lampp/htdocs/SIEMproject/log_data.json
chmod 666 /opt/lampp/htdocs/SIEMproject/raw_logs.json
chmod 666 /opt/lampp/htdocs/SIEMproject/sim_data.json
```

## Performance Considerations

- **Max events stored**: 500 (real + Python)
- **Max raw logs**: 1000
- **Update frequency**: Real-time via API, configurable via cron
- **Log rotation**: Automatic (oldest events pruned when limits reached)

## Security Notes

- API accepts POST requests from localhost only (can be restricted further)
- No authentication required for local API (intended for internal network)
- Input validation and sanitization performed
- SQL injection protection via prepared statements (where applicable)

## Future Enhancements

- [ ] Add authentication to API endpoints
- [ ] Implement websocket for real-time updates
- [ ] Add event deduplication logic
- [ ] Create admin panel for API management
- [ ] Add support for remote agents with certificate exchange
- [ ] Implement event retention policies
- [ ] Add backup/archival functionality
