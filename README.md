# 🚀 SIEM Project - Start Here

> **⭐ NEW USERS**: Read **[SETUP_GUIDE.md](SETUP_GUIDE.md)** - it's the only documentation you need!

---

## Quick Links

📖 **[SETUP_GUIDE.md](SETUP_GUIDE.md)** ← **START HERE**
- Complete installation & configuration
- Step-by-step setup process  
- All features explained
- Troubleshooting guide

---

## What is This?

A **Security Information Event Management (SIEM) system** that monitors your server for threats and displays them on a web dashboard.

**Key Features:**
- 🎯 Real-time attack detection (SQL Injection, XSS, brute force, etc.)
- 🗺️ Geolocation-based attack visualization on interactive map
- 📊 Web dashboard with event details and raw logs
- 🔍 Logs viewer with expandable event details
- 🔄 REST API for system integration

---

## 60-Second Quick Start

```bash
# 1. Start Apache
sudo /opt/lampp/lampp start

# 2. Start Python SIEM
cd /opt/lampp/htdocs/SIEMproject
python3 pythonSIEMscript.py

# 3. Open website
# http://localhost/SIEMproject/
```

That's it! Events will start appearing on the dashboard.

---

## How It Works

```
Python Script  →  Detects threats  →  Sends to API  →  
Website shows  ←  Displays on map   ←  Stores events  ←
```

**Python script** monitors logs in the background and automatically posts security events to the **PHP API**, which stores them and displays them on the website dashboard.

---

## Need Help?

1. **Installation issues** → See SETUP_GUIDE.md section "Complete Setup"
2. **Not seeing events** → See SETUP_GUIDE.md section "Troubleshooting"
3. **Want details** → See SETUP_GUIDE.md section "System Architecture"

---

## System Requirements

### Option 1: Native Installation (Linux)
- Linux (Ubuntu, CentOS, etc.)
- LAMPP installed at `/opt/lampp/`
- Python 3.6+
- 2GB RAM minimum

### Option 2: Docker (Any OS - Windows, Mac, Linux)
```bash
# Install Docker first, then:
docker build -t siem .
docker run -p 80:80 -p 443:443 siem
```
✅ Works identically on Windows, Mac, Linux  
✅ No OS-specific setup needed  
✅ All dependencies pre-installed  

**See [SETUP_GUIDE.md](SETUP_GUIDE.md) for complete Docker or native installation steps**

---

**Ready to set up? → Open [SETUP_GUIDE.md](SETUP_GUIDE.md)**
