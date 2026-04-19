#!/usr/bin/env python3
"""Test script to verify Apache log parsing and SQL injection detection"""
import re
import sys
from urllib.parse import unquote

# Test Apache log entry (the one from your terminal)
test_log = '192.168.1.50 - - [24/Feb/2026:14:50:00 +0000] "GET /login.php?id=1 UNION SELECT NULL,username,password FROM users HTTP/1.1" 200 512'

def parse_apache(line):
    """Fixed Apache parser - captures full request with spaces"""
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

def detect_web_attacks(data):
    """Detect web attacks from parsed log"""
    path = data.get('path', '')
    try:
        path = unquote(path)
    except:
        pass
    
    sqli_pattern = r"(union\s+select|select\s+.*\s+from|\bOR\b\s+\d+=\d+|--|;\s*DROP)"
    xss_pattern = r"(<script>|javascript:|onerror=|onload=|alert\()"
    traversal_pattern = r"(\.\./|\.\.\\|/etc/passwd|c:\\windows)"
    
    attacks = []
    if re.search(sqli_pattern, path, re.IGNORECASE):
        attacks.append(("SQL Injection", data.get('ip'), f"Payload found: {path}"))
    if re.search(xss_pattern, path, re.IGNORECASE):
        attacks.append(("XSS Attack", data.get('ip'), f"Payload found: {path}"))
    if re.search(traversal_pattern, path, re.IGNORECASE):
        attacks.append(("Directory Traversal", data.get('ip'), f"Payload found: {path}"))
    
    return attacks

print("=" * 70)
print("SIEM LOG PARSING & DETECTION TEST")
print("=" * 70)
print(f"\n[TEST LOG]")
print(f"{test_log}\n")

# Test parsing
parsed = parse_apache(test_log)
print(f"[PARSING RESULT]")
if parsed:
    print(f"  IP:       {parsed.get('ip')}")
    print(f"  Date:     {parsed.get('date')}")
    print(f"  Method:   {parsed.get('method')}")
    print(f"  Path:     {parsed.get('path')}")
    print(f"  Status:   {parsed.get('status')}")
    print(f"  Request:  {parsed.get('request')}\n")
    
    # Test detection
    attacks = detect_web_attacks(parsed)
    print(f"[DETECTION RESULT]")
    if attacks:
        print(f"  ✓ THREAT DETECTED ({len(attacks)} attack type(s)):")
        for attack_type, source_ip, details in attacks:
            print(f"    - {attack_type} from {source_ip}")
            print(f"      {details}")
    else:
        print(f"  ✗ No threats detected")
else:
    print(f"  ✗ PARSING FAILED")
    sys.exit(1)

print("\n" + "=" * 70)
