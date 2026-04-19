# Raw Logs Implementation - Complete Fix

## Summary
Fixed SIEM to display associated raw logs for both real and simulated security events throughout the system (Dashboard Event Details panel and Logs Viewer).

## Changes Made

### 1. **API Enhancement** (`api.php`)
- Modified `handle_get_events()` function to load raw logs from captured_logs directory
- Loads all JSON files from captured_logs (apache_access.json, network_scan.json, etc.)
- Matches raw logs to events by source IP
- Returns raw logs as array of log lines for each event

**Result**: All 6 events now have 1-32 raw logs attached (100% coverage)

### 2. **Logs Viewer** (`app/views/logs.php`)
- Added expandable rows for each event (click to expand/collapse)
- Shows "📋 Associated Raw Logs (count)" header with number of logs
- Displays each raw log as a line with monospace font
- Fallback message when no logs are found
- CSS styling for raw logs container with:
  - Dark background (#0f172a)
  - Blue left border accent
  - Scrollable container (max-height: 400px)
  - Word-break enabled for long URLs

**Result**: Real and simulated events now show their associated raw logs when expanded in logs viewer

### 3. **Dashboard Event Details** (`public/assets/js/map.js`)
- Formats raw logs for display in Event Details panel
- Shows numbered list: "[1] log_line", "[2] log_line", etc.
- Separates logs with double newline for readability
- Fallback: "No raw logs associated with this event."

**Result**: Dashboard Event Details panel now displays associated raw logs when clicking on events

## How It Works

### Data Flow

```
1. API Request (handle_get_events)
   ↓
2. Load events from:
   - log_data.json (real events)
   - captured_logs/security_events.json (Python-detected events)
   ↓
3. Load raw logs from captured_logs:
   - apache_access.json
   - network_scan.json
   - linux_system.json
   - (all other JSON files in directory)
   ↓
4. Match logs to events by source IP
   ↓
5. Attach raw_logs array to each event
   ↓
6. Return events with raw_logs via API response
   ↓
7. Display in:
   - Dashboard Event Details panel (formatted as numbered list)
   - Logs Viewer expandable rows (click event to expand)
```

### Example API Response

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

## Testing Results

### API Verification
✅ **Total Events**: 6  
✅ **Events with raw logs**: 6/6 (100% coverage)  
✅ **SQL Injection events**: 32 raw logs each  
✅ **XSS Attack events**: 1+ raw logs  

### Syntax Validation
✅ **api.php**: No syntax errors  
✅ **EventModel.php**: No syntax errors  
✅ **logs.php**: No syntax errors  

## User Interactions

### Dashboard Event Details Panel
1. Click on an event marker on the map
2. Event Details panel opens on the right
3. Scroll down to see "Raw Logs" section
4. View numbered list of associated raw logs

### Logs Viewer
1. Navigate to Logs page
2. View "Real Events" or "Simulated Events" sections
3. Click on an event row to expand
4. View associated raw logs in expanded section
5. Click again to collapse

## Files Modified

| File | Changes |
|------|---------|
| `api.php` | Enhanced handle_get_events() to load and match raw logs |
| `app/views/logs.php` | Added expandable rows with raw logs display |
| `public/assets/js/map.js` | Added raw logs formatting for dashboard |

## Files Not Modified (Already Configured)

| File | Status |
|------|--------|
| `app/models/EventModel.php` | Already has raw logs attachment logic |
| `app/models/LogsModel.php` | Already has getLogsNearEvent() method |
| `index.php` | Already has LogsModel dependency injection |
| `app/models/GeoLocationModel.php` | Already preserves raw_logs field |

## Performance Considerations

- Raw logs loaded from captured_logs directory at request time
- Indexed by source IP for O(1) lookup
- Returns up to 500 events per API call
- Raw logs container scrollable (max-height: 400px) to prevent page bloat

## Troubleshooting

### No raw logs showing?
1. Verify captured_logs directory has JSON files
2. Check that events have source IP field
3. Verify API response contains raw_logs array (not empty)
4. Check browser console for JavaScript errors

### API returns empty raw_logs?
1. Ensure captured_logs/*.json files exist and are readable
2. Check that event source IP matches log file entries
3. Verify JSON files are valid (use `python3 -m json.tool`)

### Expandable rows not working?
1. Check browser console for JavaScript errors
2. Verify logs.php has toggleRawLogs() function
3. Check that CSS is loaded properly
4. Clear browser cache and reload

## Future Enhancements

- Add timestamp-based log filtering (±X seconds from event)
- Add log type filtering (Apache, network, system, etc.)
- Add search/filter capability within raw logs
- Export raw logs to CSV/JSON
- Add log highlighting for matching patterns
