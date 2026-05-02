# SIEM Project Documentation

This repository uses four documentation files only:

1. `QUICK_SETUP.md` - fast installation and first-run steps (includes syslog quick start)
2. `CONFIGURATION.md` - full configuration reference (includes syslog configuration)
3. `README.md` - core project documentation and architecture overview (this file)
4. `USER_GUIDE.md` - operations, usage, troubleshooting, API usage, and maintenance

## Project Overview

This is a Python + PHP SIEM platform that:
- collects logs from system/web/network sources and syslog devices
- detects suspicious events (SQLi, XSS, brute force, malware indicators, privilege escalation, and more)
- stores and correlates events with raw logs
- visualizes security data in the web dashboard and VLAN views
- exposes API endpoints for integrations and automation

## Core Capabilities

- Real-time detection and event generation from monitored logs
- Syslog ingestion from network infrastructure devices
- Severity classification and threat scoring
- Event + raw-log correlation
- Geolocation visualization and event stream UI
- Logs viewer, sync status, and REST APIs

## High-Level Architecture

```
Data Sources (system logs, web logs, network logs, syslog devices)
  -> Collectors (pythonSIEMscript.py, SyslogListener.php/syslog_receiver.py)
  -> Detection + Normalization
  -> JSON persistence (captured_logs/*.json, log_data.json, raw_logs.json, sim_data.json)
  -> PHP models/controllers
  -> Dashboard / VLAN / Logs UI + API endpoints
```

## Main Components

- `pythonSIEMscript.py`
  - tails and parses log streams
  - detects attacks and classifies severity
  - persists network status and scan metrics
  - forwards events to API
- `api.php`
  - handles event and syslog routes
  - serves data to frontend/API clients
- `app/models/*`
  - event loading, deduplication, enrichment, syslog analytics
- `app/controllers/*`
  - dashboard, logs, vlan, sync, and syslog APIs
- `public/assets/js/map.js`
  - map rendering, event list rendering, frontend filtering/dedup behavior

## Data Files

- `log_data.json` - primary event data
- `sim_data.json` - simulated events
- `raw_logs.json` - consolidated raw logs
- `server_status.json` - host/network status
- `captured_logs/security_events.json` - Python-generated security events
- `captured_logs/syslog_received.json` - received syslog entries
- `captured_logs/network_*.json` - network scan/stats data

## Where To Go Next

- Need to run it now? Open `QUICK_SETUP.md`
- Need to tune settings or syslog devices? Open `CONFIGURATION.md`
- Need day-to-day usage, API calls, and troubleshooting? Open `USER_GUIDE.md`
