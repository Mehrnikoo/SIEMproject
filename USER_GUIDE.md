# SIEM User Guide

This guide covers day-to-day usage, verification, API usage, troubleshooting, maintenance, and deployment practices.

## 1) Daily Usage

### Dashboard

- Open `http://localhost/SIEMproject/`
- Use Real/Simulated tabs for event streams
- Check severity summary, map markers, and event details
- Expand event details to inspect related raw logs

### Logs View

- Open `http://localhost/SIEMproject/index.php?action=logs`
- Filter by type/severity and search terms
- Expand rows to inspect associated raw logs

### VLAN View

- Open `http://localhost/SIEMproject/index.php?action=vlan`
- Monitor network endpoints, traffic, and containment actions
- Confirm host/network fields align with latest script output

## 2) API Usage

### Core event endpoints

- `GET /api.php/events`
- `GET /api.php/logs`
- `POST /api.php/security-events`
- `GET /api.php/status`

### Syslog endpoints

- `GET /api.php/syslog-entries`
- `GET /api.php/syslog-by-ip?ip=X.X.X.X`
- `GET /api.php/syslog-high-severity`
- `GET /api.php/syslog-stats`
- `GET /api.php/syslog-threats`
- `GET /api.php/syslog-status`
- `GET /api.php/syslog-export`
- `POST /api.php/syslog-clear` (admin-protected)

## 3) Verification and Testing

```bash
# Core checks
curl http://localhost/SIEMproject/
curl http://localhost/SIEMproject/api.php/events
curl http://localhost/SIEMproject/api.php/logs

# Syslog checks
curl http://localhost/SIEMproject/api.php/syslog-status
curl http://localhost/SIEMproject/api.php/syslog-stats
```

If available in your repo:

```bash
bash test_syslog.sh
```

## 4) Troubleshooting

### No events in dashboard

1. Ensure Python SIEM is running: `python3 pythonSIEMscript.py`
2. Test API endpoint: `curl http://localhost/SIEMproject/api.php/events`
3. Verify file permissions in project JSON files

### Syslog listener not receiving

1. Check listener process and UDP binding:
   - `sudo netstat -tlnup | rg 514`
2. Send local test packet with `nc` and recheck API
3. Confirm network/firewall path from device to SIEM host

### Port/permission errors

- Port 514 needs root privileges.
- If unavailable, run listener on a non-privileged port and redirect or reconfigure senders.

### Data mismatch or duplicate suspicion

- Hard-refresh browser to avoid stale frontend cache.
- Confirm source JSON files and API counts match.
- Check dedup behavior in backend models and script output.

## 5) Maintenance

### Log rotation / archive

```bash
cd /opt/lampp/htdocs/SIEMproject
cp captured_logs/syslog_received.json archives/syslog_backup_$(date +%Y%m%d_%H%M%S).json
echo "[]" > captured_logs/syslog_received.json
```

### Health checks

- Monitor Apache/PHP and Python processes
- Track disk growth in `captured_logs/` and `archives/`
- Verify periodic updates in network status files

## 6) Production Recommendations

- Run services under systemd (auto-restart)
- Restrict syslog traffic to trusted source ranges
- Keep backups of JSON data files
- Test API paths as part of deployment checks
- Document your environment-specific IP/URL overrides

## 7) Feature Coverage Summary

- Threat detection: SQLi, XSS, brute force, escalation, malware indicators, suspicious activity
- Network intelligence: IP discovery, network scans, traffic history, containment workflow
- Syslog ingestion: RFC-style messages with severity/facility parsing and threat scoring
- UI modules: dashboard map/event stream, logs table, VLAN/network intelligence page

## 8) Frequently Asked Questions

### Which listener should I use, PHP or Python?

Use PHP listener by default for simpler integration with this app; Python listener is a valid alternative.

### Why does a count differ between views?

Usually due to dedup/normalization scope or stale page state. Refresh and compare against API data to confirm.

### Where should I start if something breaks?

Start with service/process checks, then API responses, then file permissions and recent script output.
