# SIEM Syslog Configuration Guide

## Overview

This guide covers how to configure the SIEM project to receive syslog messages from network devices such as:
- Firewalls (Palo Alto, Fortinet, pfSense)
- Routers and Switches (Cisco, Juniper, HP)
- Servers (Linux, Windows with syslog forwarding)
- Security Appliances (IDS/IPS, WAF)

## Architecture

```
[Network Devices] ---> UDP 514 [SIEM Syslog Listener] ---> [captured_logs/syslog_received.json]
                                                                     |
                                                                     v
                                                    [PHP Models] ---> [Dashboard/API]
                                                                     |
                                                                     v
                                                    [Threat Detection Engine]
```

## Prerequisites

### Server Requirements
- Linux-based SIEM server (Ubuntu, CentOS, Debian)
- PHP CLI support (for running syslog listener)
- Network connectivity to all devices sending syslog
- UDP port 514 accessible (or alternative port)
- Sufficient disk space for log storage

### Permission Requirements
- **Port 514 requires root access** (privileged port)
- Alternative: Use port 10514+ (non-privileged) with iptables redirect

### Network Requirements
- Network devices must be able to reach SIEM server on UDP port 514
- No firewall rules blocking UDP 514 between devices and SIEM
- Verify connectivity with: `nc -u -l -p 514` from SIEM server

## Installation

### Step 1: Start the Syslog Listener

The SIEM includes a PHP-based UDP syslog listener. Start it in the background:

```bash
# Run as root (required for port 514)
sudo php /opt/lampp/htdocs/SIEMproject/app/services/SyslogListener.php

# Or use nohup for persistent operation
nohup sudo php /opt/lampp/htdocs/SIEMproject/app/services/SyslogListener.php > syslog_listener.log 2>&1 &
```

### Step 2: Verify Listener is Running

Check if the listener is active:

```bash
# Check if port 514 is listening
sudo netstat -tlnup | grep 514
# or
sudo ss -tlnup | grep 514

# Expected output:
# udp  0  0  0.0.0.0:514  0.0.0.0:*  PID/php
```

### Step 3: Test with Local Message

Send a test syslog message to verify it's working:

```bash
# From the SIEM server itself
logger -t "TEST" -p local0.info "Test syslog message"

# Or use nc command
echo "<30>Jan 01 12:00:00 testhost testapp[1234]: Test message" | nc -u -w0 127.0.0.1 514
```

Check if message was received:

```bash
tail -f /opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json
```

## Configuration for Network Devices

### Linux/Unix Servers

**File:** `/etc/rsyslog.conf` or `/etc/rsyslog.d/99-forward.conf`

```bash
# Forward all logs to SIEM server
# Replace SIEM_IP with your SIEM server IP address

*.* @@SIEM_IP:514

# Or UDP (less reliable but faster):
*.* @SIEM_IP:514

# Forward only critical/high severity
*.warn @SIEM_IP:514
*.err @SIEM_IP:514
*.crit @SIEM_IP:514
*.alert @SIEM_IP:514
*.emerg @SIEM_IP:514
```

Restart rsyslog:
```bash
sudo systemctl restart rsyslog
# or
sudo service rsyslog restart
```

Test:
```bash
logger -t "Linux-Host" "Syslog test message"
```

### Cisco Routers/Switches

**Commands:**
```
configure terminal

! Set syslog server
logging SIEM_IP
logging facility local0

! Set log levels
logging trap informational

! Optional: Set source interface
logging source-interface Vlan1

! Optional: Include device hostname in logs
logging origin-id hostname

end
write memory
```

**Verify:**
```
show logging
```

### Palo Alto Firewalls

**GUI Path:** Device → Server Profiles → Syslog

- Server: SIEM_IP
- Port: 514
- Protocol: UDP
- Facility: LOG_LOCAL0
- Format: BSD (RFC 3164)

Or via CLI:

```
set deviceconfig system syslog server siem-01 server SIEM_IP
set deviceconfig system syslog server siem-01 port 514
set deviceconfig system syslog server siem-01 format bsd
set deviceconfig system syslog server siem-01 facility LOG_LOCAL0
```

### Fortinet FortiGate

**GUI Path:** System → Logging & Report → Syslog Servers

- IP Address: SIEM_IP
- Port: 514
- Facility: Local 0
- Severity: Information or higher

Or via CLI:

```
config log syslogd setting
    set status enable
    set server "SIEM_IP"
    set port 514
    set facility local0
    set min-log-level information
end
```

### Windows Servers (via syslog-ng or nxlog)

**Option A: Using syslog-ng**

`/etc/syslog-ng/syslog-ng.conf`:
```
destination d_siem { udp("SIEM_IP" port(514)); };
log { source(s_src); destination(d_siem); };
```

**Option B: Using nxlog**

`C:\Program Files\nxlog\conf\nxlog.conf`:
```
<Output siem>
    Module om_udp
    Host SIEM_IP
    Port 514
</Output>

<Route r1>
    Path in => siem
</Route>
```

## Firewall/Network Configuration

### UFW (Ubuntu)

```bash
# Allow syslog from trusted networks
sudo ufw allow from 192.168.119.0/24 to any port 514 proto udp
sudo ufw allow from 10.0.0.0/8 to any port 514 proto udp
```

### iptables (Generic Linux)

```bash
# Allow syslog from specific IP
sudo iptables -I INPUT -p udp --dport 514 -s 192.168.119.0/24 -j ACCEPT
sudo iptables -I INPUT -p udp --dport 514 -s 10.0.0.0/8 -j ACCEPT

# Save rules
sudo iptables-save > /etc/iptables/rules.v4
```

### Port Forwarding (Non-Root Alternative)

If you can't run on port 514 as root, use iptables redirect:

```bash
# Redirect UDP 10514 to 514 (listener runs on 10514 as non-root)
sudo iptables -t nat -I PREROUTING -p udp --dport 514 -j REDIRECT --to-port 10514

# Verify
sudo iptables -t nat -L PREROUTING -v
```

## Monitoring & Maintenance

### Check Listener Status

```bash
# View live console output
sudo ps aux | grep SyslogListener.php

# Check port binding
sudo netstat -tlnup | grep 514

# Check recent logs
tail -50 /opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json | jq '.[].source_ip, .[].hostname, .[].message'
```

### Storage Management

Logs are stored in JSON format in:
```
/opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json
```

Default configuration keeps last 10,000 entries (~50-100MB depending on message size).

To rotate logs:

```bash
# Archive current logs
cd /opt/lampp/htdocs/SIEMproject
cp captured_logs/syslog_received.json archives/syslog_backup_$(date +%Y%m%d_%H%M%S).json
echo "[]" > captured_logs/syslog_received.json
```

### Monitor for High-Severity Messages

```bash
# Watch for critical/alert syslog messages in real-time
tail -f /opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json | \
  jq '.[] | select(.severity <= 2) | {severity: .severity_name, source: .source_ip, message: .message}'
```

## Troubleshooting

### Syslog Listener Won't Start

**Problem:** "Permission denied" error

**Solution:**
```bash
# Run with sudo
sudo php app/services/SyslogListener.php

# OR use port 10514 (non-privileged)
# Edit SyslogListener.php line ~18: $port = 10514;
php app/services/SyslogListener.php
```

### Port Already in Use

**Problem:** "Address already in use" error

**Solution:**
```bash
# Find what's using port 514
sudo netstat -tlnup | grep 514

# Kill the process
sudo kill -9 PID

# Or use different port (requires reconfiguring devices)
```

### Not Receiving Messages

**Problem:** Logs not appearing in syslog_received.json

**Debug Steps:**
```bash
# 1. Verify listener is running
sudo netstat -tlnup | grep 514

# 2. Test connectivity from device
ssh device-ip
nc -u -w1 SIEM_IP 514 < /dev/null && echo "Open" || echo "Closed"

# 3. Check firewall rules
sudo ufw status
sudo iptables -L INPUT -v

# 4. Test with logger command
logger -t "TEST" -h SIEM_IP "Test message"

# 5. Check listener logs
sudo tail -100 syslog_listener.log
```

### Messages Received but Not Parsed Correctly

**Problem:** Message fields are empty or incorrect

**Solution:**
- Verify device is sending RFC 3164 format: `<PRI>Mmm dd hh:mm:ss HOSTNAME TAG: MESSAGE`
- Check device syslog configuration for format selection
- Review parsed entry in syslog_received.json to identify missing fields
- Enable debug output by adding `echo` statements in SyslogListener.php around line ~85

## Integration with SIEM Dashboard

### Viewing Syslog in Web Interface

Once configured, syslog logs appear in:

1. **API Endpoint:** `/api.php/syslog-entries`
   ```bash
   curl http://localhost/SIEMproject/api.php/syslog-entries
   ```

2. **Dashboard:** Logs view shows received syslog messages

3. **High-Severity Alerts:** Threat detection identifies critical syslog entries

### Detected Threats

The SIEM automatically detects threats in syslog:
- Authentication failures (30 points)
- Privilege escalation attempts (25 points)
- Port scanning (20 points)
- Firewall blocks (15 points)
- Buffer overflows/exploits (35 points)
- Critical/Alert severity (50 points)

Entries with threat score ≥ 20 appear in threat dashboard.

## Performance Optimization

### High-Volume Environments

If receiving >1000 logs/second:

1. **Increase Buffer Size** (SyslogListener.php ~30):
   ```php
   socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 10485760); // 10MB
   ```

2. **Run Multiple Listeners** on different ports:
   ```bash
   # Instance 1: Port 514
   sudo php SyslogListener.php
   
   # Instance 2: Port 10515 (requires iptables redirect)
   php SyslogListener.php 10515
   ```

3. **Use systemd Service** for auto-restart:
   Create `/etc/systemd/system/siem-syslog.service`
   ```ini
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
   ```

   Then:
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl start siem-syslog
   sudo systemctl enable siem-syslog
   ```

## Security Best Practices

1. **Restrict Syslog Access:** Only allow from trusted networks
   ```bash
   sudo ufw allow from 192.168.0.0/16 to any port 514
   ```

2. **Verify Source IPs:** Check syslog_received.json regularly for unexpected sources

3. **Rotate Logs:** Prevent disk from filling up
   ```bash
   # Keep only 7 days of logs
   find /opt/lampp/htdocs/SIEMproject/archives/syslog_*.json -mtime +7 -delete
   ```

4. **Use TLS for Sensitive Environments:** (Future enhancement)
   - Currently uses plain UDP
   - For TLS syslog (port 6514), enable in device config

5. **Monitor Listener Process:**
   ```bash
   ps aux | grep SyslogListener
   # Should show: sudo php ... SyslogListener.php
   ```

## Reference: RFC 3164 Syslog Format

Standard syslog message format:
```
<PRI>HEADER MSG

Where:
- PRI = Priority (facility * 8 + severity)
  Facility: 0=kernel, 1=user, 16=local0, 17=local1, etc.
  Severity: 0=Emergency, 1=Alert, 2=Critical, 3=Error, 4=Warning, 5=Notice, 6=Info, 7=Debug

- HEADER = Timestamp (Mmm dd hh:mm:ss) and HOSTNAME
- MSG = TAG[PID]: MESSAGE

Example:
<30>Jan 15 10:30:45 firewall-01 pix[4323]: %PIX-4-106001: Inbound TCP connection denied
```

## Support & Contact

For issues:
1. Check troubleshooting section above
2. Review listener output logs
3. Verify device syslog configuration
4. Test connectivity from device to SIEM server

---

**Last Updated:** 2024
**SIEM Version:** 2.0+
**Syslog Support:** RFC 3164 (RFC 5424 partial)
