# Syslog Quick Start Guide

Get your SIEM receiving syslog messages from network devices in 5 minutes!

## What You'll Learn

- Start the syslog listener
- Send test messages
- View received logs in the dashboard
- Configure real network devices

## Prerequisites

```bash
# Check requirements
which nc                 # netcat (for testing)
which php               # PHP CLI
php -v                  # Should be PHP 7.0+
sudo netstat -tlnup     # Check if port 514 is available
```

## Step 1: Start the Syslog Listener (2 minutes)

### Option A: Using PHP (Recommended)

```bash
# Terminal 1: Start listener
cd /opt/lampp/htdocs/SIEMproject
sudo php app/services/SyslogListener.php

# Expected output:
# [INFO] Syslog Listener started on 0.0.0.0:514
# [INFO] Storing received logs in: /opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json
# [INFO] Waiting for syslog messages...
```

### Option B: Using Python

```bash
# Terminal 1: Start listener
cd /opt/lampp/htdocs/SIEMproject
sudo python3 syslog_receiver.py

# Or on non-privileged port:
python3 syslog_receiver.py --port 10514
```

## Step 2: Send Test Messages (1 minute)

### Terminal 2: Send test syslog messages

```bash
# Simple test message
echo "<30>$(date +'%b %d %H:%M:%S') testhost testapp: Test message" | \
  nc -u -w1 127.0.0.1 514

# Firewall log simulation
echo "<34>$(date +'%b %d %H:%M:%S') fw-01 pix[100]: %PIX-4-500002: ICMP denied" | \
  nc -u -w1 127.0.0.1 514

# Authentication failure
echo "<27>$(date +'%b %d %H:%M:%S') auth-server sshd[2000]: Failed password for invalid user admin" | \
  nc -u -w1 127.0.0.1 514

# High-severity alert (will trigger threat detection)
echo "<21>$(date +'%b %d %H:%M:%S') ids-01 snort[500]: [142:1:0] HTTP directory traversal attack" | \
  nc -u -w1 127.0.0.1 514
```

**Message Format Breakdown:**
```
<30>                      # Priority: facility (3) * 8 + severity (6)
Nov 15 10:30:45          # Timestamp
testhost                 # Hostname
testapp                  # Tag/Program name
Test message             # Message
```

## Step 3: Verify Reception (1 minute)

### Check in Terminal 1 (listener console):

```
[2026-04-20 10:30:45] 127.0.0.1 | local0 | Informational | Test message
[2026-04-20 10:30:46] 127.0.0.1 | local4 | Error | %PIX-4-500002: ICMP denied
[2026-04-20 10:30:47] 127.0.0.1 | local4 | Debug | Failed password for invalid user admin
[2026-04-20 10:30:48] 127.0.0.1 | local4 | Alert | [142:1:0] HTTP directory traversal attack
```

### Check in Terminal 2:

```bash
# View the syslog JSON file
tail -50 /opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json | jq '.'

# Expected output:
# [
#   {
#     "timestamp": "2026-04-20T10:30:45+00:00",
#     "source_ip": "127.0.0.1",
#     "hostname": "testhost",
#     "facility_name": "local0",
#     "severity_name": "Informational",
#     "message": "Test message"
#   },
#   ...
# ]
```

## Step 4: View in Dashboard (1 minute)

### Open Web Browser:

```
http://localhost/SIEMproject
```

Navigate to:
- **Logs Tab**: See received syslog entries
- **Dashboard**: High-severity messages appear in threat dashboard
- **Events Tab**: Critical/Alert severity entries

### View via API:

```bash
# Get all syslog entries
curl http://localhost/SIEMproject/api.php/syslog-entries | jq '.entries[] | {hostname, severity_name, message}'

# Get statistics
curl http://localhost/SIEMproject/api.php/syslog-stats | jq '.stats'

# Get detected threats
curl http://localhost/SIEMproject/api.php/syslog-threats | jq '.threats[0]'

# Get high-severity messages only
curl http://localhost/SIEMproject/api.php/syslog-high-severity | jq '.'
```

## Step 5: Test with Realistic Messages (Optional)

### Simulate Network Firewall Activity

```bash
#!/bin/bash
# Test data: Firewall logs

for i in {1..5}; do
    priority=$((32 + RANDOM % 8))  # local4, varying severity
    msg="<$priority>$(date +'%b %d %H:%M:%S') fw-prod syslog[100]: Block TCP from 203.0.113.$i:$((1000+i*100)) to 192.168.1.1:80"
    echo "$msg" | nc -u -w1 127.0.0.1 514
    sleep 0.5
done
```

### Simulate IDS/IPS Alerts

```bash
#!/bin/bash
# Test data: IDS/IPS alerts

threats=(
    "SQL Injection attempt detected"
    "Port scan from external IP"
    "XSS attack payload blocked"
    "DDoS attack pattern detected"
    "Malware signature matched"
)

for threat in "${threats[@]}"; do
    priority=$((22 + RANDOM % 3))  # Alert/Critical
    msg="<$priority>$(date +'%b %d %H:%M:%S') ids-prod snort[500]: $threat"
    echo "$msg" | nc -u -w1 127.0.0.1 514
    sleep 0.2
done
```

### Simulate Linux Server Logs

```bash
#!/bin/bash
# Test data: Linux server authentication

for user in admin root ubuntu; do
    priority=$((29 + RANDOM % 2))  # Warning/Error
    msg="<$priority>$(date +'%b %d %H:%M:%S') linux-srv sshd[3000]: Failed password for $user from 192.168.1.100"
    echo "$msg" | nc -u -w1 127.0.0.1 514
    sleep 0.3
done
```

## Testing Checklist

- [ ] Listener starts successfully (`sudo php app/services/SyslogListener.php`)
- [ ] No "permission denied" errors (if using port 514)
- [ ] Test messages appear in listener console
- [ ] JSON file is created at `captured_logs/syslog_received.json`
- [ ] Dashboard shows received syslog entries
- [ ] API endpoints return data
- [ ] High-severity messages trigger threat detection
- [ ] Different severities are properly categorized

## Troubleshooting

### Port 514 Already in Use

```bash
# Check what's using it
sudo netstat -tlnup | grep 514
sudo fuser 514/udp

# Kill existing process
sudo kill -9 <PID>

# Or use different port (requires device config change)
```

### Permission Denied

```bash
# Port 514 requires root
sudo php app/services/SyslogListener.php

# Alternative: Use non-privileged port with iptables redirect
python3 syslog_receiver.py --port 10514
```

### No Messages Received

```bash
# 1. Verify listener is running
sudo netstat -tlnup | grep 514
ps aux | grep Syslog

# 2. Test connectivity
nc -u -w1 127.0.0.1 514 < /dev/null && echo "Open" || echo "Closed"

# 3. Check listener logs (if running in background)
tail -50 /opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json

# 4. Test from different terminal
echo "<30>test" | nc -u -w1 127.0.0.1 514
```

### Message Format Issues

```bash
# Verify RFC 3164 format is correct
# Format: <PRI>HEADER MSG

# Good examples:
echo "<30>Nov 15 10:30:45 host app: msg" | nc -u -w1 127.0.0.1 514
echo "<134>Apr 20 12:00:00 testhost app[123]: info" | nc -u -w1 127.0.0.1 514

# Check for common issues:
# - Missing timestamp
# - Missing hostname
# - Invalid priority number (should be 0-191)
```

## Next Steps

### 1. Configure Real Devices

See [SYSLOG_SETUP.md](SYSLOG_SETUP.md) for device-specific instructions:
- Linux/Unix servers (rsyslog)
- Cisco routers/switches
- Palo Alto firewalls
- Fortinet FortiGate
- Windows servers

### 2. Monitor for Threats

Threat detection automatically runs on high-severity messages:

```bash
# View detected threats
curl http://localhost/SIEMproject/api.php/syslog-threats | jq '.threats[] | {severity: .estimated_severity, reasons: .threat_reasons}'
```

### 3. Set Up Continuous Logging

For production use, run listener as systemd service:

```bash
# Create service file
sudo nano /etc/systemd/system/siem-syslog.service

# Content:
[Unit]
Description=SIEM Syslog Listener
After=network.target

[Service]
Type=simple
User=root
ExecStart=/usr/bin/php /opt/lampp/htdocs/SIEMproject/app/services/SyslogListener.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target

# Enable and start
sudo systemctl daemon-reload
sudo systemctl enable siem-syslog
sudo systemctl start siem-syslog
```

### 4. Monitor Listener Status

```bash
# Check if listener is running
sudo systemctl status siem-syslog

# View real-time logs
sudo journalctl -u siem-syslog -f

# Check API status
curl http://localhost/SIEMproject/api.php/syslog-status
```

## Common Syslog Priorities (PRI)

### Calculation: PRI = facility * 8 + severity

**Facilities:**
- 16 = local0, 17 = local1, ... 23 = local7
- 0 = kernel, 1 = user, 3 = daemon, 4 = auth, 16 = local0

**Severities:**
- 0 = Emergency, 1 = Alert, 2 = Critical
- 3 = Error, 4 = Warning, 5 = Notice
- 6 = Informational, 7 = Debug

**Common PRIs:**
- 30 = local0 + informational (log messages)
- 34 = local4 + critical (firewalls)
- 27 = local3 + error (authorization)
- 21 = local2 + alert (security)

## Files Reference

- **Listener:** `app/services/SyslogListener.php` (PHP)
- **Python Listener:** `syslog_receiver.py` (Alternative)
- **Syslog Logs:** `captured_logs/syslog_received.json`
- **API Controller:** `app/controllers/SyslogController.php`
- **Models:** `app/models/SyslogModel.php`
- **API Endpoints:** See `api.php` syslog routes
- **Setup Guide:** `SYSLOG_SETUP.md` (device-specific)

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review `SYSLOG_SETUP.md` for detailed configuration
3. Check listener console output for error messages
4. Run test script: `bash test_syslog.sh`

---

**Estimated Time to Complete:** 5-10 minutes  
**Difficulty:** Beginner  
**Updated:** 2024
