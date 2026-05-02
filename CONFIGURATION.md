# SIEM Configuration Reference

This file consolidates configuration details for core SIEM behavior and syslog integration.

## 1) Python SIEM Configuration

File: `pythonSIEMscript.py`

Primary controls:

- `PHP_API_ENABLED`
- `PHP_API_URL`
- `SIEM_HQ_IP`
- `SIEM_PORT`
- `LOGSTASH_HOST`
- `LOGSTASH_PORT`
- `NGINX_LOG_PATH`
- `APACHE_LOG_PATH`
- `LINUX_NETWORK_LOG`

Recommended API setting:

```python
PHP_API_ENABLED = True
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
```

## 2) PHP Application Configuration

File: `app/config/config.php`

Key entries:

- `geo_api`
- `public_ip_service`
- `php_api_url`
- `data_files.log_data`
- `data_files.sim_data`
- `data_files.raw_logs`
- `data_files.server_status`
- `python_logs_dir`
- `severity_map`

## 3) File & Permission Configuration

```bash
mkdir -p /opt/lampp/htdocs/SIEMproject/captured_logs
mkdir -p /opt/lampp/htdocs/SIEMproject/archives

sudo chown -R www-data:www-data /opt/lampp/htdocs/SIEMproject/
chmod 666 /opt/lampp/htdocs/SIEMproject/*.json
```

## 4) Syslog Configuration (Listener Side)

### Listener options

- PHP listener (recommended): `app/services/SyslogListener.php`
- Python listener (alternative): `syslog_receiver.py`

### Start listener

```bash
sudo php /opt/lampp/htdocs/SIEMproject/app/services/SyslogListener.php
```

### Port and privilege

- Standard syslog port: UDP `514` (requires root)
- Non-privileged option: use `10514` and redirect traffic

Example redirect:

```bash
sudo iptables -t nat -I PREROUTING -p udp --dport 514 -j REDIRECT --to-port 10514
```

## 5) Syslog Configuration (Device Side)

### Linux/Unix with rsyslog

```conf
*.* @SIEM_IP:514
```

Or TCP:

```conf
*.* @@SIEM_IP:514
```

### Cisco (example)

```text
configure terminal
logging SIEM_IP
logging facility local0
logging trap informational
end
write memory
```

### Palo Alto (summary)

- Device -> Server Profiles -> Syslog
- Server: `SIEM_IP`
- Port: `514`
- Protocol: UDP
- Format: RFC 3164/BSD

### Fortinet (summary)

```text
config log syslogd setting
    set status enable
    set server "SIEM_IP"
    set port 514
    set facility local0
    set min-log-level information
end
```

### Windows (nxlog summary)

```conf
<Output siem>
    Module om_udp
    Host SIEM_IP
    Port 514
</Output>
```

## 6) Firewall Rules for Syslog

UFW example:

```bash
sudo ufw allow from 192.168.0.0/16 to any port 514 proto udp
```

iptables example:

```bash
sudo iptables -I INPUT -p udp --dport 514 -s 192.168.0.0/16 -j ACCEPT
```

## 7) Threat Detection Mapping (Syslog + Event Pipeline)

- Severity and attack types are normalized and mapped to internal categories.
- High-risk patterns include authentication failures, escalation attempts, scanning behavior, and exploit signatures.
- Deduplication is applied in backend models and script persistence paths to reduce repeated records.

## 8) Operational Configuration Tips

- Use systemd for persistent listener/script startup.
- Keep log rotation and archive strategy in place for JSON growth.
- Restrict syslog source networks to trusted ranges.
- Verify API and listener health regularly (`/api.php/syslog-status`, `/api.php/status`).
