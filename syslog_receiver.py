#!/usr/bin/env python3
"""
Syslog Server for SIEM - Receives syslog messages from network devices
This can run alongside or instead of the PHP syslog listener

Usage:
    sudo python3 syslog_receiver.py [--port 514] [--output captured_logs/]
    
    # Or with non-privileged port (requires iptables redirect)
    python3 syslog_receiver.py --port 10514
"""

import socket
import json
import os
import sys
import argparse
import re
from datetime import datetime
from pathlib import Path
import signal

class SyslogServer:
    def __init__(self, host='0.0.0.0', port=514, output_dir='captured_logs'):
        self.host = host
        self.port = port
        self.output_dir = Path(output_dir)
        self.output_dir.mkdir(parents=True, exist_ok=True)
        self.syslog_file = self.output_dir / 'syslog_received.json'
        self.socket = None
        self.running = True
        
        # Signal handlers for graceful shutdown
        signal.signal(signal.SIGINT, self.signal_handler)
        signal.signal(signal.SIGTERM, self.signal_handler)
        
    def signal_handler(self, sig, frame):
        """Handle Ctrl+C and graceful shutdown"""
        print('\n[INFO] Shutting down syslog server...')
        self.running = False
        if self.socket:
            self.socket.close()
        sys.exit(0)
    
    def start(self):
        """Start the syslog server"""
        try:
            self.socket = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            
            # Allow port reuse
            self.socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
            
            # Set receive buffer size
            self.socket.setsockopt(socket.SOL_SOCKET, socket.SO_RCVBUF, 10485760)  # 10MB
            
            # Bind to port
            self.socket.bind((self.host, self.port))
            
            print(f"[INFO] Syslog Server started on {self.host}:{self.port}")
            print(f"[INFO] Storing logs in: {self.syslog_file}")
            print(f"[INFO] Listening for incoming syslog messages...\n")
            
            # Main loop
            while self.running:
                try:
                    data, addr = self.socket.recvfrom(65535)
                    if data:
                        self.process_syslog(data.decode('utf-8', errors='ignore'), addr)
                except Exception as e:
                    print(f"[ERROR] {e}")
                    
        except PermissionError:
            print(f"[ERROR] Permission denied binding to port {self.port}")
            print(f"[INFO] Try: sudo python3 {sys.argv[0]}")
            sys.exit(1)
        except OSError as e:
            print(f"[ERROR] Failed to bind socket: {e}")
            sys.exit(1)
        finally:
            if self.socket:
                self.socket.close()
    
    def process_syslog(self, message, addr):
        """Parse and store syslog message"""
        remote_addr = addr[0]
        remote_port = addr[1]
        
        # Parse syslog
        entry = self.parse_syslog(message, remote_addr, remote_port)
        
        # Store to file
        self.store_syslog(entry)
        
        # Log to console
        facility = entry.get('facility_name', 'unknown')
        severity = entry.get('severity_name', 'unknown')
        msg_preview = entry.get('message', '')[:80]
        
        print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {remote_addr} | {facility} | {severity} | {msg_preview}")
    
    def parse_syslog(self, message, remote_addr, remote_port):
        """Parse RFC 3164/RFC 5424 syslog message"""
        timestamp = datetime.now().isoformat()
        facility = 16  # default: local0
        severity = 6   # default: informational
        hostname = 'unknown'
        tag = ''
        content = message
        priority = 134
        
        # Parse priority field: <PRI>
        pri_match = re.match(r'^<(\d+)>(.*)$', message)
        if pri_match:
            priority = int(pri_match.group(1))
            facility = int(priority / 8)
            severity = priority % 8
            message = pri_match.group(2)
        
        # Parse timestamp and hostname
        # RFC 3164: Mmm dd hh:mm:ss HOSTNAME ...
        # RFC 5424: 2026-04-20T12:34:56+00:00 HOSTNAME ...
        
        ts_match = re.match(
            r'^(\w+\s+\d+\s+\d+:\d+:\d+|\d{4}-\d{2}-\d{2}T[\d:+-]+)\s+(.*)$',
            message
        )
        if ts_match:
            ts_str = ts_match.group(1)
            message = ts_match.group(2)
            
            # Try to parse as RFC 5424
            if re.match(r'^\d{4}-\d{2}-\d{2}', ts_str):
                try:
                    dt = datetime.fromisoformat(ts_str.replace('Z', '+00:00'))
                    timestamp = dt.isoformat()
                except:
                    pass
        
        # Parse hostname
        host_match = re.match(r'^([\w\-\.]+)\s+(.*)$', message)
        if host_match:
            hostname = host_match.group(1)
            message = host_match.group(2)
        
        # Parse tag (program name)
        tag_match = re.match(r'^([\w\-\.]+)(?:\[\d+\])?:\s*(.*)$', message)
        if tag_match:
            tag = tag_match.group(1)
            content = tag_match.group(2)
        else:
            content = message
        
        return {
            'timestamp': timestamp,
            'received_at': datetime.now().isoformat(),
            'source_ip': remote_addr,
            'source_port': remote_port,
            'priority': priority,
            'facility': facility,
            'severity': severity,
            'facility_name': self.get_facility_name(facility),
            'severity_name': self.get_severity_name(severity),
            'hostname': hostname,
            'tag': tag,
            'message': content,
            'raw_message': message[:1000]  # Truncate very long messages
        }
    
    def store_syslog(self, entry):
        """Store syslog entry to JSON file"""
        logs = []
        
        # Load existing entries
        if self.syslog_file.exists():
            try:
                with open(self.syslog_file, 'r') as f:
                    logs = json.load(f)
            except:
                logs = []
        
        # Add new entry
        logs.append(entry)
        
        # Keep only last 10000 entries
        if len(logs) > 10000:
            logs = logs[-10000:]
        
        # Save back
        try:
            with open(self.syslog_file, 'w') as f:
                json.dump(logs, f, indent=2)
        except Exception as e:
            print(f"[ERROR] Failed to save syslog: {e}")
    
    def get_facility_name(self, facility):
        """Get facility name from code"""
        facilities = {
            0: 'kernel',
            1: 'user',
            2: 'mail',
            3: 'daemon',
            4: 'auth',
            5: 'syslog',
            6: 'lpr',
            7: 'news',
            8: 'uucp',
            9: 'cron',
            16: 'local0',
            17: 'local1',
            18: 'local2',
            19: 'local3',
            20: 'local4',
            21: 'local5',
            22: 'local6',
            23: 'local7'
        }
        return facilities.get(facility, f'local{facility}')
    
    def get_severity_name(self, severity):
        """Get severity name from code"""
        severities = {
            0: 'Emergency',
            1: 'Alert',
            2: 'Critical',
            3: 'Error',
            4: 'Warning',
            5: 'Notice',
            6: 'Informational',
            7: 'Debug'
        }
        return severities.get(severity, f'Unknown({severity})')

def main():
    parser = argparse.ArgumentParser(
        description='SIEM Syslog Server - Receive logs from network devices',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
Examples:
  # Run on standard syslog port (requires root)
  sudo python3 syslog_receiver.py
  
  # Run on non-privileged port
  python3 syslog_receiver.py --port 10514
  
  # Specify custom output directory
  python3 syslog_receiver.py --output /var/log/siem/
        '''
    )
    
    parser.add_argument(
        '--port',
        type=int,
        default=514,
        help='UDP port to listen on (default: 514, requires root)'
    )
    parser.add_argument(
        '--host',
        default='0.0.0.0',
        help='Host to bind to (default: 0.0.0.0)'
    )
    parser.add_argument(
        '--output',
        default='captured_logs',
        help='Output directory for syslog JSON (default: captured_logs)'
    )
    
    args = parser.parse_args()
    
    # Check permissions for privileged ports
    if args.port < 1024 and os.geteuid() != 0:
        print(f"[ERROR] Ports below 1024 require root access")
        print(f"[INFO] Run with: sudo python3 {sys.argv[0]}")
        sys.exit(1)
    
    # Start server
    server = SyslogServer(
        host=args.host,
        port=args.port,
        output_dir=args.output
    )
    server.start()

if __name__ == '__main__':
    main()
