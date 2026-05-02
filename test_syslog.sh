#!/bin/bash
# SIEM Syslog Testing Script
# Tests the syslog listener functionality

set -e

PROJECT_DIR="/opt/lampp/htdocs/SIEMproject"
SYSLOG_FILE="$PROJECT_DIR/captured_logs/syslog_received.json"
API_URL="http://localhost/SIEMproject/api.php"

echo "============================================"
echo "SIEM Syslog Functionality Test"
echo "============================================"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Check if listener is running
echo -e "${YELLOW}[TEST 1] Checking if syslog listener is running...${NC}"
if sudo netstat -tlnup 2>/dev/null | grep -q ':514'; then
    echo -e "${GREEN}✓ Syslog listener is listening on port 514${NC}"
else
    echo -e "${RED}✗ Syslog listener NOT running on port 514${NC}"
    echo "  Start with: sudo php app/services/SyslogListener.php"
fi
echo ""

# Test 2: Send test syslog message
echo -e "${YELLOW}[TEST 2] Sending test syslog message...${NC}"
TEST_MESSAGE="<34>$(date +'%b %d %H:%M:%S') testhost testapp[1234]: SIEM test message"
echo "$TEST_MESSAGE" | nc -u -w1 127.0.0.1 514
echo -e "${GREEN}✓ Test message sent${NC}"
sleep 1
echo ""

# Test 3: Check if message was received
echo -e "${YELLOW}[TEST 3] Checking if message was received...${NC}"
if [ -f "$SYSLOG_FILE" ]; then
    COUNT=$(cat "$SYSLOG_FILE" | jq 'length' 2>/dev/null || echo "0")
    if [ "$COUNT" -gt 0 ]; then
        echo -e "${GREEN}✓ Received $COUNT syslog entries${NC}"
        echo "  Latest entry:"
        cat "$SYSLOG_FILE" | jq '.[-1]' | head -10
    else
        echo -e "${RED}✗ No syslog entries received yet${NC}"
    fi
else
    echo -e "${RED}✗ Syslog file not found at: $SYSLOG_FILE${NC}"
fi
echo ""

# Test 4: Test API endpoints
echo -e "${YELLOW}[TEST 4] Testing Syslog API endpoints...${NC}"

endpoints=(
    "syslog-status"
    "syslog-entries?limit=5"
    "syslog-stats"
    "syslog-threats"
)

for endpoint in "${endpoints[@]}"; do
    echo -n "  Testing /$endpoint... "
    if curl -s "$API_URL/$endpoint" | jq -e '.status' > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
    else
        echo -e "${RED}✗${NC}"
    fi
done
echo ""

# Test 5: Send test messages from different sources
echo -e "${YELLOW}[TEST 5] Sending test messages from various facilities/severities...${NC}"
test_messages=(
    "<30>$(date +'%b %d %H:%M:%S') fw-01 pix[100]: %PIX-4-500002: ICMP denied"
    "<33>$(date +'%b %d %H:%M:%S') router-01 OS[2000]: %OS-6-SYSLOG_MSG_DROPPED: Syslog message dropped"
    "<27>$(date +'%b %d %H:%M:%S') switch-01 SYSLOG[3000]: %SWITCH-5-SECURITY_EVENT: Failed login attempt"
)

for msg in "${test_messages[@]}"; do
    echo "$msg" | nc -u -w1 127.0.0.1 514
    echo "  Sent: ${msg:30:50}..."
    sleep 0.5
done
echo -e "${GREEN}✓ Test messages sent${NC}"
sleep 1
echo ""

# Test 6: Get statistics
echo -e "${YELLOW}[TEST 6] Syslog Statistics${NC}"
if command -v curl &> /dev/null; then
    STATS=$(curl -s "$API_URL/syslog-stats" | jq '.stats')
    if [ "$STATS" != "null" ]; then
        echo "$STATS" | jq '.'
    fi
else
    echo "  Curl not installed, skipping"
fi
echo ""

# Test 7: Get high-severity entries
echo -e "${YELLOW}[TEST 7] High-Severity Syslog Entries${NC}"
if command -v curl &> /dev/null; then
    THREATS=$(curl -s "$API_URL/syslog-high-severity" | jq '.count')
    echo "  Found $THREATS high-severity entries"
else
    echo "  Curl not installed, skipping"
fi
echo ""

# Summary
echo "============================================"
echo -e "${GREEN}Syslog Test Complete!${NC}"
echo "============================================"
echo ""
echo "Next steps:"
echo "1. View live logs: tail -f $SYSLOG_FILE"
echo "2. Check threats: curl -s $API_URL/syslog-threats | jq ."
echo "3. Configure devices: See SYSLOG_SETUP.md"
echo ""
