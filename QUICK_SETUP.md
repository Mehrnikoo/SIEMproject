# SIEM Quick Setup

This guide gets the project running quickly and includes a syslog quick start.

## Prerequisites

- Linux with LAMPP in `/opt/lampp/` (or compatible Apache/PHP setup)
- Python 3.6+
- PHP CLI
- `nc` (netcat) for syslog tests

## 5-Minute Start

```bash
# 1) Start LAMPP/Apache
sudo /opt/lampp/lampp start

# 2) Go to project
cd /opt/lampp/htdocs/SIEMproject

# 3) Start Python SIEM
python3 pythonSIEMscript.py
```

Open:

`http://localhost/SIEMproject/`

## Quick Verification

```bash
# Dashboard/API availability
curl http://localhost/SIEMproject/
curl http://localhost/SIEMproject/api.php/events
```

## Quick Attack Test

```bash
curl "http://localhost/SIEMproject/login.php?id=1 UNION SELECT NULL,username,password FROM users"
```

Then reload dashboard/logs and confirm a new event appears.

## Syslog Quick Start

### Start listener

```bash
cd /opt/lampp/htdocs/SIEMproject
sudo php app/services/SyslogListener.php
```

### Send test syslog messages

```bash
echo "<30>$(date +'%b %d %H:%M:%S') testhost testapp: Test message" | nc -u -w1 127.0.0.1 514
echo "<27>$(date +'%b %d %H:%M:%S') auth-server sshd[2000]: Failed password for invalid user admin" | nc -u -w1 127.0.0.1 514
echo "<21>$(date +'%b %d %H:%M:%S') ids-01 snort[500]: HTTP directory traversal attack" | nc -u -w1 127.0.0.1 514
```

### Verify syslog ingestion

```bash
curl http://localhost/SIEMproject/api.php/syslog-entries
curl http://localhost/SIEMproject/api.php/syslog-stats
```

## Optional Docker Quick Start

```bash
docker build -t siem .
docker run -p 80:80 -p 443:443 -p 3306:3306 siem
```

## If Something Fails Fast

- Apache not running: `sudo /opt/lampp/lampp start`
- No events: run `python3 pythonSIEMscript.py` and check terminal output
- Syslog port busy: `sudo netstat -tlnup | rg 514`
- Permission denied on 514: run listener with `sudo`
