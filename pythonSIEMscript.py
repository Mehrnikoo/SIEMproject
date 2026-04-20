import json
import socket
import re
import os
import ipaddress
from datetime import datetime
import random
import argparse
import subprocess
import platform
import sys
import time
import threading
import queue
from collections import defaultdict, deque

# Attempt to import tkinter for the GUI (Traffic Visualizer).
try:
    import tkinter as tk
except ImportError:
    tk = None

# ==========================================
#               CONFIGURATION
# ==========================================
# Paths to common log files (Adjust as needed)
NGINX_LOG_PATH = "/var/log/nginx/access.log"
APACHE_LOG_PATH = "/var/log/apache2/access.log"
LINUX_NETWORK_LOG = "/var/log/kern.log" # Often contains UFW/IPTables logs

# [CENTRALIZED LOGGING CONFIG]
SIEM_HQ_IP = None 
SIEM_PORT = 5555

# [PHP API CONFIG]
PHP_API_ENABLED = True  # Send events to PHP API
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"

# [LOGSTASH SYSLOG CONFIG]
LOGSTASH_HOST = "127.0.0.1"
LOGSTASH_PORT = 10514


# ==========================================
#           BASE CLASS: LOGS
# ==========================================
class Logs:
    """
    Base class responsible for OS detection and initializing the Log Queue.
    """
    def __init__(self):
        self.os_type = self.detect_os()
        # A thread-safe queue to hold logs from all sources
        # Items in queue are tuples: (source_type, log_data_string)
        self.log_queue = queue.Queue()
        self.stop_event = threading.Event()

    def detect_os(self):
        """Determines if we are running on Linux, Windows, or macOS."""
        system = platform.system()
        if system == 'Windows':
            return 'Windows'
        elif system == 'Linux':
            return 'Linux'
        elif system == 'Darwin':
            return 'macOS'
        else:
            return 'Unknown'

    def start_tailing_thread(self, target, args=()):
        """Helper to start a daemon thread."""
        t = threading.Thread(target=target, args=args, daemon=True)
        t.start()
        return t


# ==========================================
#        CLASS: READ_LOGS (COLLECTOR)
# ==========================================
class Read_logs(Logs):
    """
    Spins up background threads to 'tail' various log sources.
    Pushes incoming lines to the central self.log_queue.
    """
    def __init__(self):
        super().__init__()
        self.log_dir = "captured_logs"
        if not os.path.exists(self.log_dir):
            os.makedirs(self.log_dir)
        
        # Start the background listeners immediately
        self.start_log_streams()

    def start_log_streams(self):
        print("[Read_logs] Initializing continuous log streams...")
        
        # 1. System Logs (OS specific)
        if self.os_type == 'Linux':
            # journalctl -f -o json (Follows system logs in JSON format)
            self.start_tailing_thread(self._tail_command, (['journalctl', '-f', '-o', 'json'], 'linux_system'))
            # Tail Network/Kernel logs
            if os.path.exists(LINUX_NETWORK_LOG):
                 self.start_tailing_thread(self._tail_file, (LINUX_NETWORK_LOG, 'linux_network'))

        elif self.os_type == 'macOS':
            # log stream --style json (Follows system logs in JSON)
            self.start_tailing_thread(self._tail_command, (['log', 'stream', '--style', 'json'], 'macos_system'))

        elif self.os_type == 'Windows':
            # Windows Event Logs don't "tail" natively. We run a polling loop in a thread.
            self.start_tailing_thread(self._poll_windows_events)

        # 2. Web Server Logs (File Tailing)
        # Nginx
        if os.path.exists(NGINX_LOG_PATH):
            print(f"[Read_logs] Found Nginx at {NGINX_LOG_PATH}, tailing...")
            self.start_tailing_thread(self._tail_file, (NGINX_LOG_PATH, 'nginx_access'))
        
        # Apache
        if os.path.exists(APACHE_LOG_PATH):
            print(f"[Read_logs] Found Apache at {APACHE_LOG_PATH}, tailing...")
            self.start_tailing_thread(self._tail_file, (APACHE_LOG_PATH, 'apache_access'))

    # --- WORKER: Tail a File (Linux/macOS/Windows) ---
    def _tail_file(self, filepath, source_tag):
        """
        Equivalent to 'tail -f'. Opens file, seeks to end, and reads new lines.
        """
        try:
            # Use 'subprocess' with tail on Unix for robustness, or python file seek
            # Using subprocess tail -f is often more robust against log rotation on Linux.
            if self.os_type in ['Linux', 'macOS']:
                cmd = ['tail', '-f', filepath]
                self._tail_command(cmd, source_tag)
            else:
                # Fallback for pure Python implementation (Windows mostly)
                with open(filepath, 'r') as f:
                    f.seek(0, 2) # Go to end
                    while not self.stop_event.is_set():
                        line = f.readline()
                        if not line:
                            time.sleep(0.1)
                            continue
                        self.log_queue.put((source_tag, line.strip()))
        except Exception as e:
            print(f"[Error] Tailing file {filepath}: {e}")

            
    def forward_to_syslog(self, source, data):
        """Broadcasts fetched logs to the Logstash Syslog server."""
        try:
            # Standard Syslog format: <Priority>Timestamp Hostname Tag: Message
            timestamp = datetime.now().strftime("%b %d %H:%M:%S")
            hostname = socket.gethostname()
            message = json.dumps(data) if isinstance(data, dict) else str(data)
            
            syslog_msg = f"<34>{timestamp} {hostname} {source}: {message}"
            
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            sock.sendto(syslog_msg.encode('utf-8'), (LOGSTASH_HOST, LOGSTASH_PORT))
            sock.close()
        except Exception:
            pass # Don't let a network blip crash your script



    # --- WORKER: Tail a Command (Linux/macOS) ---
    def _tail_command(self, cmd_list, source_tag):
        """
        Runs a shell command (like journalctl -f) and reads stdout line-by-line.
        """
        try:
            # subprocess.Popen allows us to read stdout as a stream
            process = subprocess.Popen(cmd_list, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, bufsize=1)
            
            # Read line by line as they arrive
            for line in iter(process.stdout.readline, ''):
                if self.stop_event.is_set():
                    break
                if line:
                    self.log_queue.put((source_tag, line.strip()))
            
            process.stdout.close()
            process.wait()
        except Exception as e:
            print(f"[Error] Running command {cmd_list}: {e}")

    # --- WORKER: Windows Event Poll (Windows Only) ---
    def _poll_windows_events(self):
        """
        Simulates 'tail -f' for Windows Event Logs by polling for new events.
        """
        last_index = 0
        try:
            while not self.stop_event.is_set():
                # Get events after the last one we saw
                # Note: This is a simplified approach. In prod, use 'Get-WinEvent' with bookmarks.
                ps_cmd = "Get-EventLog -LogName System -Newest 1 | Select-Object -Property Index, Source, Message, EntryType, InstanceId | ConvertTo-Json -Depth 1"
                
                try:
                    output = subprocess.check_output(["powershell", "-Command", ps_cmd], text=True)
                    entry = json.loads(output)
                    
                    current_index = entry.get('Index', 0)
                    if current_index != last_index:
                        last_index = current_index
                        self.log_queue.put(('windows_system', entry)) # It's already dict/json
                except:
                    pass
                
                time.sleep(1) # Poll every second
        except Exception as e:
            print(f"[Error] Windows polling: {e}")


    def process_and_store_logs(self):
        """
        Main Thread calls this to 'drain' the queue and forward to Logstash.
        """
        parsed_data = []
        
        while not self.log_queue.empty():
            try:
                # Use get_nowait() inside a try block to prevent crashes
                source, raw_data = self.log_queue.get_nowait()
                
                log_entry = None
                
                # --- [START] YOUR EXISTING PARSING LOGIC ---
                if source in ['linux_system', 'macos_system', 'windows_system']:
                    log_entry = raw_data if isinstance(raw_data, dict) else json.loads(raw_data)
                elif source == 'nginx_access':
                    log_entry = self._parse_nginx(raw_data)
                elif source == 'apache_access':
                    log_entry = self._parse_apache(raw_data)
                elif source == 'linux_network':
                    log_entry = {'message': raw_data}
                # --- [END] YOUR EXISTING PARSING LOGIC ---

                if log_entry:
                    # FORWARD TO LOGSTASH (SYSLOG)
                    self.forward_to_syslog(source, log_entry)
                    
                    parsed_data.append({'source': source, 'data': log_entry})

            except queue.Empty:
                break # Queue became empty unexpectedly, exit loop safely
            except Exception as e:
                print(f"[Error] Processing log from {source}: {e}")
                continue # Skip this one log and keep the script running

        # Save to local files as well
        if parsed_data:
            self.store_to_files(parsed_data)
            
        return parsed_data


    def _parse_nginx(self, line):
        # Nginx Combined Format - Capture full request to handle payloads with spaces
        regex = r'(?P<ip>[\d\.]+) - - \[(?P<date>[^\]]+)\] "(?P<request>[^"]*)" (?P<status>\d+)'
        match = re.search(regex, line)
        if match:
            data = match.groupdict()
            # Parse the request into method, path, and protocol
            request_parts = data['request'].split(' ')
            if len(request_parts) >= 2:
                data['method'] = request_parts[0]
                data['path'] = ' '.join(request_parts[1:-1]) if len(request_parts) > 2 else request_parts[1]
            else:
                data['method'] = request_parts[0] if request_parts else 'UNKNOWN'
                data['path'] = request_parts[1] if len(request_parts) > 1 else ''
            return data
        return None

    def _parse_apache(self, line):
        # Apache Common/Combined - Capture full request to handle payloads with spaces
        regex = r'(?P<ip>[\d\.]+) - - \[(?P<date>[^\]]+)\] "(?P<request>[^"]*)" (?P<status>\d+)'
        match = re.search(regex, line)
        if match:
            data = match.groupdict()
            # Parse the request into method, path, and protocol
            request_parts = data['request'].split(' ')
            if len(request_parts) >= 2:
                data['method'] = request_parts[0]
                data['path'] = ' '.join(request_parts[1:-1]) if len(request_parts) > 2 else request_parts[1]
            else:
                data['method'] = request_parts[0] if request_parts else 'UNKNOWN'
                data['path'] = request_parts[1] if len(request_parts) > 1 else ''
            return data
        return None

    def store_to_files(self, parsed_data):
        """Separates logs into files based on their source (e.g., sshd.json, nginx.json)."""
        for item in parsed_data:
            source_name = self.sanitize_filename(item['source'])
            filename = os.path.join(self.log_dir, f"{source_name}.json")
            
            content = []
            if os.path.exists(filename):
                try:
                    with open(filename, 'r') as f:
                        content = json.load(f)
                except: content = []
            
            # Simple append (no dedup needed for streaming usually, but good practice)
            content.append(item['data'])
            if len(content) > 100: content = content[-100:]
            
            with open(filename, 'w') as f:
                json.dump(content, f, indent=4)

    def sanitize_filename(self, name):
        """Removes illegal characters from filenames."""
        return re.sub(r'[\\/*?:"<>|]', "", str(name)).strip().replace(' ', '_')


# ==========================================
#      CLASS: SORT_EVENT (CLASSIFIER)
# ==========================================
class Sort_event(Logs):
    def __init__(self):
        super().__init__()
        self.output_file = "captured_logs/security_events.json"
        self.event_counter = 1
        
        if not os.path.exists(self.output_file):
            with open(self.output_file, 'w') as f:
                json.dump([], f)
        else:
            try:
                with open(self.output_file, 'r') as f:
                    data = json.load(f)
                    self.event_counter = len(data) + 1
            except: pass

    def classify_severity(self, attack_type):
        severity_map = {
            "DDoS Attack": "Critical 🔴",
            "Privilege Escalation": "Critical 🔴",
            "Privilege Escalation Risk": "Critical 🔴",
            "Potential Malware": "Critical 🔴",
            "SQL Injection": "High 🟠",
            "XSS Attack": "High 🟠",
            "Brute Force": "High 🟠",
            "Directory Traversal": "Medium 🟡",
            "Suspicious Activity": "Low 🔵"
        }
        return severity_map.get(attack_type, "Low 🔵")

    def send_to_logstash_alert(self, log_object):
        """
        Sends the security alert to Logstash Syslog with high priority.
        """
        try:
            # Change priority based on severity
            priority = "<32>" if "Critical" in log_object["severity_sticker"] else "<33>"
            timestamp = datetime.now().strftime("%b %d %H:%M:%S")
            hostname = socket.gethostname()
            
            # Create a structured message
            message = json.dumps(log_object)
            syslog_msg = f"{priority}{timestamp} {hostname} SIEM_ALERT: {message}"
            
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            sock.sendto(syslog_msg.encode('utf-8'), ("127.0.0.1", 10514))
            sock.close()
        except Exception:
            pass


    def log_security_event(self, attack_type, source_ip, target_ip, details, remote_reporter_ip=None):
        severity = self.classify_severity(attack_type)
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        entry_id = f"sim-log-entry-{self.event_counter}"
        action = "BLOCKED" if "Critical" in severity or "High" in severity else "FLAGGED"

        if remote_reporter_ip:
            formatted_message = (
                f"{entry_id}: [REMOTE REPORT FROM {remote_reporter_ip}] {attack_type} detected. Source: {source_ip}\n"
                f"{entry_id}: Action: {action} (Reported by Agent)"
            )
            target_ip = f"{target_ip} (Reported by {remote_reporter_ip})"
        else:
            formatted_message = (
                f"{entry_id}: {attack_type} from {source_ip} targeting {target_ip}\n"
                f"{entry_id}: Action: {action}"
            )

        log_object = {
            "id": entry_id,
            "timestamp": timestamp,
            "severity_sticker": severity,
            "attack_type": attack_type,
            "source": source_ip,
            "target": target_ip,
            "formatted_log": formatted_message,
            "details": details
        }

        self.save_to_json(log_object)
        self.send_to_logstash_alert(log_object)
        print(f"\n[{severity}] {entry_id} {'(REMOTE)' if remote_reporter_ip else ''}")
        print(formatted_message)
        self.event_counter += 1
        return log_object 

    def save_to_json(self, log_object):
        data = []
        if os.path.exists(self.output_file):
            try:
                with open(self.output_file, 'r') as f:
                    data = json.load(f)
            except: pass
        data.append(log_object)
        with open(self.output_file, 'w') as f:
            json.dump(data, f, indent=4)
        
        # Also send to PHP API if enabled
        if PHP_API_ENABLED:
            self.send_to_php_api(log_object)
    
    def send_to_php_api(self, log_object):
        """
        Send security event to PHP API for real-time dashboard updates
        """
        try:
            import urllib.request
            import urllib.error
            
            # Convert Python object to JSON payload
            payload = json.dumps({
                'id': log_object.get('id'),
                'timestamp': log_object.get('timestamp'),
                'severity_sticker': log_object.get('severity_sticker'),
                'attack_type': log_object.get('attack_type'),
                'source': log_object.get('source'),
                'target': log_object.get('target'),
                'details': log_object.get('details'),
                'formatted_log': log_object.get('formatted_log')
            }).encode('utf-8')
            
            # Send POST request to PHP API
            request = urllib.request.Request(
                PHP_API_URL,
                data=payload,
                headers={'Content-Type': 'application/json'},
                method='POST'
            )
            
            response = urllib.request.urlopen(request, timeout=2)
            response.read()
            response.close()
            
        except Exception as e:
            # Silently fail - don't block log processing if API is unavailable
            pass


# ==========================================
#     CLASS: CONTAINMENT (NETWORK COMMS)
# ==========================================
class Containment(Logs):
    def __init__(self, sort_engine):
        super().__init__()
        self.sort_engine = sort_engine
        self.port = SIEM_PORT

    def start_listener(self):
        server_thread = threading.Thread(target=self._server_loop, daemon=True)
        server_thread.start()
        print(f"[Containment] Central Log Server Listening on Port {self.port}...")

    def _server_loop(self):
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            s.bind(('0.0.0.0', self.port))
            s.listen(5)
            while True:
                conn, addr = s.accept()
                sender_ip = addr[0]
                try:
                    data = conn.recv(8192).decode('utf-8')
                    if data:
                        event_data = json.loads(data)
                        self.sort_engine.log_security_event(
                            attack_type=event_data.get('attack_type', 'Unknown Remote'),
                            source_ip=event_data.get('source', 'Unknown'),
                            target_ip=event_data.get('target', 'Remote Agent'),
                            details=event_data.get('details', ''),
                            remote_reporter_ip=sender_ip 
                        )
                except: pass
                finally: conn.close()
        except Exception as e:
            print(f"[Containment] Server Error: {e}")

    def forward_log_to_hq(self, log_object):
        if not SIEM_HQ_IP: return 
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            s.settimeout(2) 
            s.connect((SIEM_HQ_IP, self.port))
            s.send(json.dumps(log_object).encode('utf-8'))
            s.close()
            print(f"   -> [Sent to HQ: {SIEM_HQ_IP}]")
        except Exception as e:
            print(f"   -> [Failed to send to HQ: {e}]")


# ==========================================
#      CLASS: PARSE_LOGS (DETECTION CORE)
# ==========================================
class Parse_logs(Logs):
    def __init__(self, sort_engine, network_engine, containment_engine):
        super().__init__()
        self.sort_engine = sort_engine
        self.network_engine = network_engine
        self.containment_engine = containment_engine
        
        self.brute_force_registry = defaultdict(deque) 
        self.ddos_registry = defaultdict(deque)
        self.BRUTE_FORCE_LIMIT = 5 
        self.BRUTE_FORCE_WINDOW = 60 
        self.DDOS_LIMIT = 20 
        self.DDOS_WINDOW = 2 

    def analyze_logs(self, log_batch):
        for log in log_batch:
            source = log['source']
            data = log['data']
            
            if 'nginx' in source or 'apache' in source:
                self.detect_web_attacks(data)
                self.detect_ddos(data)
            else:
                self.detect_brute_force(data)
                self.detect_privilege_escalation(data)
                self.detect_malware_indicators(data)

    def detect_ddos(self, data):
        ip = data.get('ip')
        if not ip: return
        now = time.time()
        self.ddos_registry[ip].append(now)
        while self.ddos_registry[ip] and (now - self.ddos_registry[ip][0] > self.DDOS_WINDOW):
            self.ddos_registry[ip].popleft()
        if len(self.ddos_registry[ip]) > self.DDOS_LIMIT:
            self.trigger_alert("DDoS Attack", ip, f"High traffic: {len(self.ddos_registry[ip])} reqs in {self.DDOS_WINDOW}s")
            self.ddos_registry[ip].clear()

    def detect_web_attacks(self, data):
        path = data.get('path', '')
        try:
            from urllib.parse import unquote
            path = unquote(path)
        except: pass
        sqli_pattern = r"(union\s+select|select\s+.*\s+from|\bOR\b\s+\d+=\d+|--|;\s*DROP)"
        xss_pattern = r"(<script>|javascript:|onerror=|onload=|alert\()"
        traversal_pattern = r"(\.\./|\.\.\\|/etc/passwd|c:\\windows)"
        if re.search(sqli_pattern, path, re.IGNORECASE):
            self.trigger_alert("SQL Injection", data.get('ip'), f"Payload found: {path}")
        if re.search(xss_pattern, path, re.IGNORECASE):
            self.trigger_alert("XSS Attack", data.get('ip'), f"Payload found: {path}")
        if re.search(traversal_pattern, path, re.IGNORECASE):
            self.trigger_alert("Directory Traversal", data.get('ip'), f"Payload found: {path}")

    def detect_brute_force(self, data):
        is_failure = False
        identifier = "Unknown"
        message = ""
        # Handle JSON dicts vs Raw Strings
        if isinstance(data, dict):
             message = data.get('MESSAGE', str(data))
        else:
             message = str(data)

        if self.os_type == 'Linux':
            if re.search(r'(Failed password|authentication failure)', message, re.IGNORECASE):
                is_failure = True
                if isinstance(data, dict):
                    identifier = data.get('SYSLOG_IDENTIFIER', 'system')

        elif self.os_type == 'Windows':
            if isinstance(data, dict):
                if str(data.get('InstanceId')) == '4625' or re.search(r'Audit Failure', str(data), re.IGNORECASE):
                    is_failure = True
                    identifier = "Windows User"

        if is_failure:
            now = time.time()
            self.brute_force_registry[identifier].append(now)
            while self.brute_force_registry[identifier] and (now - self.brute_force_registry[identifier][0] > self.BRUTE_FORCE_WINDOW):
                self.brute_force_registry[identifier].popleft()
            if len(self.brute_force_registry[identifier]) >= self.BRUTE_FORCE_LIMIT:
                self.trigger_alert("Brute Force", identifier, f"{len(self.brute_force_registry[identifier])} failures detected")
                self.brute_force_registry[identifier].clear()

    def detect_privilege_escalation(self, data):
        message = ""
        if isinstance(data, dict):
            message = data.get('MESSAGE', str(data))
        else:
            message = str(data)

        if self.os_type == 'Linux':
            if 'sudo' in message or ('SYSLOG_IDENTIFIER' in data and 'sudo' in data['SYSLOG_IDENTIFIER']):
                if 'COMMAND=' in message:
                    user = re.search(r'user=([a-zA-Z0-9_-]+)', message)
                    cmd = re.search(r'COMMAND=(.+)', message)
                    user_str = user.group(1) if user else "Unknown"
                    cmd_str = cmd.group(1) if cmd else "Unknown"
                    if any(x in cmd_str for x in ['/bin/bash', '/bin/sh', 'visudo', 'shadow']):
                        self.trigger_alert("Privilege Escalation Risk", user_str, f"Sensitive SUDO command: {cmd_str}")

        elif self.os_type == 'Windows':
            if isinstance(data, dict) and str(data.get('InstanceId')) == '4732': 
                self.trigger_alert("Privilege Escalation", "Windows", "User added to Admin group (Event 4732)")

    def detect_malware_indicators(self, data):
        message = str(data)
        suspicious_keywords = [
            r'nc\s+.*-e\s+/bin/sh', r'bash\s+-i\s+>&', r'curl\s+.*\|\s*bash',
            r'wget\s+.*\|\s*bash', r'/etc/shadow', r'trojan', r'miner'
        ]
        for pattern in suspicious_keywords:
            if re.search(pattern, message, re.IGNORECASE):
                self.trigger_alert("Potential Malware", "System", f"Suspicious pattern found: {pattern}")

    def trigger_alert(self, detection_type, source_ip, details):
        target_ip = self.network_engine.private_ip
        log_obj = self.sort_engine.log_security_event(detection_type, source_ip, target_ip, details)
        if SIEM_HQ_IP:
            self.containment_engine.forward_log_to_hq(log_obj)


# ==========================================
#        CLASS: NETWORK (VISUALIZATION)
# ==========================================
class Network(Logs):
    def __init__(self):
        super().__init__()
        self._private_ip = self.get_private_ip()
        self._last_ip_check = time.time()
        self.persist_server_status()  # Initial persistence

    @property
    def private_ip(self):
        """Get the current private IP, refreshing if it's been more than 5 minutes since last check."""
        if time.time() - self._last_ip_check > 300:  # 5 minutes
            self.update_private_ip()
        return self._private_ip

    def get_private_ip(self):
        """Fetch the most likely LAN private IP (avoid tunnel/VPN interfaces)."""
        candidates = []
        avoided_prefixes = ('utun', 'tun', 'tap', 'wg', 'tailscale', 'ppp', 'docker', 'veth', 'br-', 'lo')
        preferred_prefixes = ('wlan', 'wl', 'eth', 'en', 'eno', 'enp', 'wlp')

        def add_candidate(ip, iface=''):
            try:
                ip_obj = ipaddress.ip_address(ip)
            except Exception:
                return
            if ip_obj.version != 4:
                return
            if not ip_obj.is_private or ip_obj.is_loopback or ip_obj.is_link_local:
                return
            iface_l = (iface or '').lower()
            score = 0
            if iface_l.startswith(preferred_prefixes):
                score += 3
            if any(iface_l.startswith(prefix) for prefix in avoided_prefixes):
                score -= 5
            # Prefer common LAN blocks over carrier-grade NAT/tunnel ranges.
            if ip.startswith('192.168.') or ip.startswith('10.'):
                score += 2
            if ip.startswith('100.64.') or ip.startswith('100.'):
                score -= 2
            candidates.append((score, ip))

        # Linux: reliable interface + IPv4 list
        if self.os_type == 'Linux':
            try:
                output = subprocess.check_output(['ip', '-4', '-o', 'addr', 'show', 'up'], text=True)
                for line in output.splitlines():
                    # Example: "2: wlan0    inet 192.168.1.10/24 ..."
                    parts = line.split()
                    if len(parts) >= 4:
                        iface = parts[1]
                        inet_cidr = parts[3]
                        ip = inet_cidr.split('/')[0]
                        add_candidate(ip, iface)
            except Exception:
                pass
        # macOS/BSD fallback
        elif self.os_type == 'macOS':
            try:
                output = subprocess.check_output(['ifconfig'], text=True)
                current_iface = ''
                for line in output.splitlines():
                    if line and not line.startswith('\t') and ':' in line:
                        current_iface = line.split(':', 1)[0].strip()
                        continue
                    m = re.search(r'inet (\d+\.\d+\.\d+\.\d+)', line)
                    if m:
                        add_candidate(m.group(1), current_iface)
            except Exception:
                pass

        # Generic fallback
        try:
            host_ips = socket.gethostbyname_ex(socket.gethostname())[2]
            for ip in host_ips:
                add_candidate(ip)
        except Exception:
            pass

        # Last resort from outbound route (can pick tunnel, low score mitigates this if others exist).
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect(("8.8.8.8", 80))
            outbound_ip = s.getsockname()[0]
            s.close()
            add_candidate(outbound_ip, 'route')
        except Exception:
            pass

        if candidates:
            candidates.sort(key=lambda x: x[0], reverse=True)
            return candidates[0][1]
        return "127.0.0.1"

    def update_private_ip(self):
        """Update the stored private IP address with the current value."""
        old_ip = self._private_ip
        self._private_ip = self.get_private_ip()
        self._last_ip_check = time.time()
        
        if old_ip != self._private_ip:
            print(f"[Network] Private IP updated: {old_ip} -> {self._private_ip}")
            self.persist_server_status()
        
        return self._private_ip

    def force_refresh_private_ip(self):
        """Force refresh the private IP immediately, regardless of timing."""
        return self.update_private_ip()

    def persist_server_status(self):
        """Persist the current server status including private IP to server_status.json"""
        try:
            status = {
                'private_ip': self.private_ip,
                'last_update': datetime.now().strftime('%Y-%m-%dT%H:%M:%S.%f')
            }
            with open('server_status.json', 'w') as f:
                json.dump(status, f, indent=4)
            print(f"[Network] Server status updated: {status}")
        except Exception as e:
            print(f"[Network] Failed to persist server status: {e}")

    def scan_network(self):
        print("\n--- Network Device Scan (ARP) ---")
        devices = []
        try:
            output = subprocess.check_output(['arp', '-a'], text=True)
            for line in output.splitlines():
                ip_match = re.search(r'\(?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\)?', line)
                if ip_match:
                    devices.append(ip_match.group(1))
                    print(f"Device found: {ip_match.group(1)} | Raw: {line.strip()}")
        except Exception as e:
            print(f"Error scanning network: {e}")
        print("---------------------------------")
        return devices

    def persist_network_scan(self, devices):
        """
        Persist the latest network scan to captured_logs/network_scan.json
        """
        try:
            out = {
                'scanned_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'private_ip': self.private_ip,
                'devices': devices
            }
            path = os.path.join('captured_logs', 'network_scan.json')
            with open(path, 'w') as f:
                json.dump(out, f, indent=4)
        except Exception as e:
            print(f"[Network] Failed to persist scan: {e}")

    def persist_traffic_stats(self, bytes_sent, bytes_recv):
        try:
            out = {
                'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
                'private_ip': self.private_ip,
                'bytes_sent': bytes_sent,
                'bytes_recv': bytes_recv
            }
            path = os.path.join('captured_logs', 'network_stats.json')
            with open(path, 'w') as f:
                json.dump(out, f, indent=4)
            # Also append to history (trim to last 120 entries)
            try:
                hist_path = os.path.join('captured_logs', 'network_stats_history.json')
                history = []
                if os.path.exists(hist_path):
                    try:
                        with open(hist_path, 'r') as hf:
                            history = json.load(hf)
                    except: history = []
                history.append(out)
                if len(history) > 120:
                    history = history[-120:]
                with open(hist_path, 'w') as hf:
                    json.dump(history, hf, indent=4)
            except Exception:
                pass
        except Exception as e:
            print(f"[Network] Failed to persist traffic stats: {e}")

    def get_traffic_stats(self):
        bytes_sent = 0
        bytes_recv = 0
        if self.os_type == 'Linux':
            try:
                with open('/proc/net/dev', 'r') as f:
                    lines = f.readlines()[2:]
                    for line in lines:
                        data = line.split()
                        if len(data) > 9:
                            bytes_recv += int(data[1])
                            bytes_sent += int(data[9])
            except: pass
        elif self.os_type == 'Windows':
            try:
                output = subprocess.check_output(['netstat', '-e'], text=True)
                for line in output.splitlines():
                    if "Bytes" in line:
                        parts = line.split()
                        if len(parts) >= 3:
                            bytes_recv = int(parts[1])
                            bytes_sent = int(parts[2])
            except: pass
        return bytes_sent, bytes_recv

    def check_for_containment_actions(self, enabled=False):
        """
        Check for containment actions written by the web UI.
        Actions should be written to captured_logs/containment_actions.json as a list of objects.
        This function will mark actions as executed in containment_executed.json and optionally
        attempt to apply a local block (only when enabled and running as root on Linux).
        """
        try:
            actions_path = os.path.join('captured_logs', 'containment_actions.json')
            executed_path = os.path.join('captured_logs', 'containment_executed.json')
            if not os.path.exists(actions_path):
                return
            try:
                with open(actions_path, 'r') as f:
                    actions = json.load(f)
            except Exception:
                return

            remaining = []
            executed = []
            if os.path.exists(executed_path):
                try:
                    with open(executed_path, 'r') as f:
                        executed = json.load(f)
                except: executed = []

            for action in actions:
                # Expected action shape: { 'ip': '1.2.3.4', 'command': 'block', 'requested_at': '...' }
                ip = action.get('ip')
                command = action.get('command')
                action_id = action.get('id') or f"action-{int(time.time())}"
                result = {'id': action_id, 'ip': ip, 'command': command, 'requested_at': action.get('requested_at'), 'executed_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'), 'status': 'skipped'}

                if not ip or not command:
                    result['status'] = 'invalid'
                    executed.append(result)
                    continue

                # Safe default: do not run destructive commands unless explicitly enabled
                if enabled and self.os_type == 'Linux' and os.geteuid() == 0:
                    try:
                        if command == 'block':
                            # Attempt to add an iptables DROP rule for the IP
                            subprocess.check_call(['iptables', '-I', 'INPUT', '-s', ip, '-j', 'DROP'])
                            result['status'] = 'blocked'
                        else:
                            result['status'] = 'unknown-command'
                    except Exception as e:
                        result['status'] = 'failed'
                        result['error'] = str(e)
                else:
                    # Log the intended action for manual review
                    result['status'] = 'logged'
                executed.append(result)

            # Write executed log and clear input actions file
            try:
                with open(executed_path, 'w') as f:
                    json.dump(executed, f, indent=4)
            except Exception:
                pass

            # Remove containment_actions.json so actions are not reprocessed
            try:
                os.remove(actions_path)
            except Exception:
                pass

        except Exception as e:
            print(f"[Network] containment check failed: {e}")

    def visualize_traffic(self):
        if not tk:
            print("[Error] Tkinter not installed. Cannot show diagram.")
            return
        root = tk.Tk()
        root.title(f"Network Monitor - {self.private_ip}")
        root.geometry("400x300")
        lbl_sent = tk.Label(root, text="Upload: 0 KB/s", fg="blue", font=("Arial", 12))
        lbl_sent.pack(pady=5)
        lbl_recv = tk.Label(root, text="Download: 0 KB/s", fg="green", font=("Arial", 12))
        lbl_recv.pack(pady=5)
        canvas = tk.Canvas(root, width=300, height=150, bg="white")
        canvas.pack(pady=10)
        canvas.create_line(10, 140, 290, 140, width=2)
        bar_up = canvas.create_rectangle(50, 140, 100, 140, fill="blue")
        bar_down = canvas.create_rectangle(200, 140, 250, 140, fill="green")
        canvas.create_text(75, 155, text="Upload")
        canvas.create_text(225, 155, text="Download")
        self.last_sent, self.last_recv = self.get_traffic_stats()
        self.last_time = time.time()
        def update_graph():
            current_sent, current_recv = self.get_traffic_stats()
            current_time = time.time()
            time_diff = current_time - self.last_time
            if time_diff == 0: time_diff = 1 
            sent_speed = (current_sent - self.last_sent) / time_diff
            recv_speed = (current_recv - self.last_recv) / time_diff
            self.last_sent = current_sent
            self.last_recv = current_recv
            self.last_time = current_time
            lbl_sent.config(text=f"Upload: {sent_speed/1024:.1f} KB/s")
            lbl_recv.config(text=f"Download: {recv_speed/1024:.1f} KB/s")
            scale_factor = 0.001 
            h_up = min(130, sent_speed * scale_factor)
            h_down = min(130, recv_speed * scale_factor)
            canvas.coords(bar_up, 50, 140 - h_up, 100, 140)
            canvas.coords(bar_down, 200, 140 - h_down, 250, 140)
            root.after(1000, update_graph)
        root.after(1000, update_graph)
        root.mainloop()

# ==========================================
#            MAIN EXECUTION BLOCK
# ==========================================
if __name__ == "__main__":
    siem_monitor = Read_logs()       
    siem_network = Network()         
    siem_sorter = Sort_event()       
    siem_containment = Containment(siem_sorter) 
    siem_parser = Parse_logs(siem_sorter, siem_network, siem_containment)

    print(f"Running Full SIEM Suite...")
    print(f"OS: {siem_monitor.os_type}")
    print(f"Private IP: {siem_network.private_ip}")
    
    if SIEM_HQ_IP is None:
        print(f"Mode: [SERVER] Listening for logs on port {SIEM_PORT}...")
        siem_containment.start_listener()
    else:
        print(f"Mode: [CLIENT] Sending logs to HQ at {SIEM_HQ_IP}")

    print(f"Modules Active: Tailing Logs (System, Nginx, Apache), Analysis, Sorting, Containment, Network Monitor")
    
    def log_loop():
        try:
            while True:
                # Drains the thread-safe queue containing new logs
                logs = siem_monitor.process_and_store_logs()
                if logs:
                    siem_parser.analyze_logs(logs)
                time.sleep(1) # Check queue every second
        except KeyboardInterrupt:
            pass

    t = threading.Thread(target=log_loop, daemon=True)
    t.start()
    print("SIEM Log Monitor running in background.")
    print("Opening Network Traffic Window...")
    # Start a background thread to persist network scans, traffic stats, and handle containment actions
    def network_maintenance_loop():
        ip_check_counter = 0
        try:
            while True:
                try:
                    # Refresh private IP every 20 cycles (every 5 minutes at 15s intervals)
                    ip_check_counter += 1
                    if ip_check_counter >= 20:
                        old_ip = siem_network.private_ip
                        siem_network.update_private_ip()
                        if old_ip != siem_network.private_ip:
                            siem_network.persist_server_status()
                        ip_check_counter = 0
                    
                    # Refresh host IP every cycle so dashboard files reflect network changes quickly.
                    siem_network.force_refresh_private_ip()
                    devices = siem_network.scan_network()
                    # Always persist scan output (even empty) to avoid stale VLAN data.
                    siem_network.persist_network_scan(devices)
                    sent, recv = siem_network.get_traffic_stats()
                    siem_network.persist_traffic_stats(sent, recv)
                    # Do not enable destructive containment by default. Set ENABLE_CONTAINMENT env var to '1' to enable.
                    enable_containment = os.environ.get('ENABLE_CONTAINMENT', '0') == '1'
                    siem_network.check_for_containment_actions(enabled=enable_containment)
                except Exception as e:
                    print(f"[Network] maintenance error: {e}")
                time.sleep(15)
        except KeyboardInterrupt:
            pass

    net_thread = threading.Thread(target=network_maintenance_loop, daemon=True)
    net_thread.start()
    try:
        siem_network.visualize_traffic()
    except KeyboardInterrupt:
        print("Stopping...")