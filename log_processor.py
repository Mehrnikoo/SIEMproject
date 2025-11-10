import json
import socket
import re
import os
from datetime import datetime
import random
import argparse

# --- CONFIGURATION ---
TARGET_IP = "10.50.2.56" # Default, will be overwritten by detection

# Paths to common log files to scan
LOG_PATHS = [
    '/var/log/auth.log',
    '/var/log/secure',
    '/var/log/syslog',
    './application.log' # Local fallback
]
TAIL_READ_BYTES = 200_000
FAILED_LOGIN_HIGH = 5

def get_private_ip():
    """Determine the primary private IP address."""
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        # Doesn't send data, just finds the interface
        s.connect(('10.255.255.255', 1))
        ip = s.getsockname()[0]
    except Exception:
        ip = '127.0.0.1' # Fallback
    finally:
        s.close()
    return ip

def tail_bytes(path, num_bytes=TAIL_READ_BYTES):
    """Read the last num_bytes of a file."""
    try:
        with open(path, 'rb') as f:
            f.seek(0, os.SEEK_END)
            file_size = f.tell()
            start = max(0, file_size - num_bytes)
            f.seek(start)
            data = f.read().decode('utf-8', errors='replace')
            return data.splitlines()
    except Exception:
        return []

def extract_ips(line):
    return re.findall(r'(?:\d{1,3}\.){3}\d{1,3}', line)

def detect_real_events(lines):
    """Scan provided lines and extract real suspicious events."""
    failed_logins = {}   # ip -> [lines]
    sql_injections = {}  # ip -> [lines]
    events = []
    next_id = 1

    for line in lines:
        ips = extract_ips(line)
        ip = ips[0] if ips else None
        if not ip:
            continue

        # SSH / auth failed patterns
        if 'Failed password for' in line or 'authentication failure' in line:
            failed_logins.setdefault(ip, []).append(line)
            continue

        # Web access logs: detect SQLi
        if any(token in line.lower() for token in ['union select', "' or '1'='1'"]):
            sql_injections.setdefault(ip, []).append(line)
            continue

    def create_event(ip, severity, desc, sample_lines):
        nonlocal next_id
        event = {
            'id': next_id,
            'source_ip': ip,
            'target_ip': TARGET_IP or 'Unknown',
            'severity': severity,
            'description': desc,
            'raw_logs': sample_lines[:5], # Keep it brief
            'simulated': False,
            'timestamp': datetime.now().isoformat()
        }
        next_id += 1
        return event

    for ip, lines_list in failed_logins.items():
        count = len(lines_list)
        sev = 'High' if count >= FAILED_LOGIN_HIGH else 'Medium'
        desc = f"Repeated failed authentication attempts ({count})"
        events.append(create_event(ip, sev, desc, lines_list))

    for ip, lines_list in sql_injections.items():
        desc = f"SQL Injection payload detected ({len(lines_list)} hits)"
        events.append(create_event(ip, 'Critical', desc, lines_list))

    return events

def generate_simulated_events(start_id=1000):
    """Create a fixed set of simulated attack events for debugging."""
    
    # --- NEW: Define simulated hop paths ---
    
    # Path 1: China -> USA
    path_china = [
        {"ip": "103.207.200.10", "lat": 39.9042, "lon": 116.4074, "city": "Beijing", "country": "China"},
        {"ip": "202.97.12.1", "lat": 31.2304, "lon": 121.4737, "city": "Shanghai", "country": "China"},
        {"ip": "138.197.250.1", "lat": 37.3382, "lon": -121.8863, "city": "San Jose", "country": "United States"},
        {"ip": "72.14.200.1", "lat": 41.8781, "lon": -87.6298, "city": "Chicago", "country": "United States"}
    ]
    
    # Path 2: Russia -> USA
    path_russia = [
        {"ip": "45.141.215.118", "lat": 55.7558, "lon": 37.6173, "city": "Moscow", "country": "Russia"},
        {"ip": "87.250.250.242", "lat": 59.9311, "lon": 30.3609, "city": "Saint Petersburg", "country": "Russia"},
        {"ip": "195.201.10.1", "lat": 52.5200, "lon": 13.4050, "city": "Berlin", "country": "Germany"},
        {"ip": "8.8.8.8", "lat": 37.4056, "lon": -122.0775, "city": "Mountain View", "country": "United States"}
    ]
    
    # Path 3: Brazil (Simulated Internal Hop)
    path_brazil = [
        {"ip": "176.113.115.155", "lat": -23.5505, "lon": -46.6333, "city": "São Paulo", "country": "Brazil"},
        {"ip": "10.1.1.1", "lat": 26.305, "lon": -80.0664, "city": "Boca Raton (Sim)", "country": "United States"}
    ]

    simulated_attacks = [
        ("103.207.200.10", "Brute Force SSH", "High", path_china),
        ("45.141.215.118", "SQL Injection Attempt", "Critical", path_russia),
        ("198.51.100.12", "Port Scan Detected", "Medium", path_brazil), # Re-using path for example
        ("80.93.88.25", "Remote Control Attempt", "Critical", path_russia), # Re-using path
        ("176.113.115.155", "Malware Download Signature", "High", path_brazil),
    ]
    
    events = []
    
    for i, (ip, desc, sev, path) in enumerate(simulated_attacks):
        full_desc = f"[SIMULATION] {desc}"
        raw_logs = [
            f"sim-log-entry-1: {full_desc} from {ip} targeting {TARGET_IP}",
            f"sim-log-entry-2: Action: BLOCKED"
        ]
        events.append({
            'id': start_id + i,
            'source_ip': ip,
            'target_ip': TARGET_IP or 'Unknown',
            'severity': sev,
            'description': full_desc,
            'raw_logs': raw_logs,
            'simulated': True,
            'simulated_hops': path, # --- NEW: Added the hop data ---
            'timestamp': datetime.now().isoformat()
        })
    return events

def main():
    global TARGET_IP
    
    # 1. Detect and write private IP status
    private_ip = get_private_ip()
    TARGET_IP = private_ip # Set the global for other functions
    server_status = {
        'private_ip': private_ip,
        'last_update': datetime.now().isoformat()
    }
    try:
        with open('server_status.json', 'w') as f:
            json.dump(server_status, f, indent=4)
        print(f"Wrote server_status.json (Private IP: {private_ip})")
    except Exception as e:
        print(f"Warning: failed to write server_status.json: {e}")

    # 2. Scan for REAL events
    aggregated_lines = []
    print("Scanning for real log files...")
    for path in LOG_PATHS:
        if os.path.exists(path):
            print(f"... found and scanning: {path}")
            aggregated_lines.extend(tail_bytes(path))

    real_events = detect_real_events(aggregated_lines)
    try:
        with open('log_data.json', 'w') as f:
            json.dump(real_events, f, indent=4)
        print(f"Wrote {len(real_events)} real events to log_data.json")
    except Exception as e:
        print(f"Error: could not write log_data.json: {e}")

    # 3. Generate SIMULATED events
    sim_events = generate_simulated_events()
    try:
        # Write to a separate, clean file
        with open('sim_data.json', 'w') as f:
            json.dump(sim_events, f, indent=4)
        print(f"Wrote {len(sim_events)} simulated events to sim_data.json")
    except Exception as e:
        print(f"Error: could not write sim_data.json: {e}")

if __name__ == '__main__':
    main()
