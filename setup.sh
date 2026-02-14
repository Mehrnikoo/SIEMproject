#!/bin/bash
# Quick Start Setup for Python SIEM ↔ PHP Integration
# Usage: bash setup.sh

set -e

echo "=========================================="
echo "SIEM Integration Setup"
echo "=========================================="

SIEM_DIR="/opt/lampp/htdocs/SIEMproject"
cd "$SIEM_DIR" || exit 1

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 1. Create directories
echo -e "${BLUE}1. Creating required directories...${NC}"
mkdir -p captured_logs
mkdir -p app/services
chmod 755 captured_logs
echo -e "${GREEN}   ✓ Directories created${NC}"

# 2. Initialize JSON files
echo -e "${BLUE}2. Initializing JSON data files...${NC}"
[ ! -f log_data.json ] && echo "[]" > log_data.json
[ ! -f raw_logs.json ] && echo "[]" > raw_logs.json
[ ! -f sim_data.json ] && echo "[]" > sim_data.json
chmod 666 log_data.json raw_logs.json sim_data.json
echo -e "${GREEN}   ✓ JSON files initialized${NC}"

# 3. Verify API endpoint
echo -e "${BLUE}3. Checking Apache/PHP setup...${NC}"
if sudo /opt/lampp/bin/lamp status > /dev/null 2>&1 || \
   curl -s http://localhost/SIEMproject/ > /dev/null 2>&1; then
    echo -e "${GREEN}   ✓ Web server is running${NC}"
else
    echo -e "${YELLOW}   ⚠ Web server may not be running${NC}"
    echo "   Start it with: sudo /opt/lampp/manager-linux-x64.run"
fi

# 4. Test API
echo -e "${BLUE}4. Testing PHP API...${NC}"
RESPONSE=$(curl -s http://localhost/SIEMproject/api.php/status)
if echo "$RESPONSE" | grep -q '"status":"ok"'; then
    echo -e "${GREEN}   ✓ PHP API is working${NC}"
else
    echo -e "${YELLOW}   ⚠ Could not reach PHP API${NC}"
fi

# 5. Show next steps
echo ""
echo -e "${GREEN}=========================================="
echo "Setup Complete! Next Steps:"
echo "==========================================${NC}"
echo ""
echo -e "${BLUE}1. Start the Python SIEM script:${NC}"
echo "   cd $SIEM_DIR"
echo "   python3 pythonSIEMscript.py"
echo ""
echo -e "${BLUE}2. Open the website:${NC}"
echo "   http://localhost/SIEMproject/"
echo ""
echo -e "${BLUE}3. Monitor sync status:${NC}"
echo "   http://localhost/SIEMproject/index.php?action=sync_status"
echo ""
echo -e "${BLUE}4. Real-time integration test:${NC}"
echo "   The Python script will automatically send events to the API"
echo "   Check the dashboard for new security events"
echo ""
echo -e "${BLUE}5. Manual sync (optional):${NC}"
echo "   php app/services/SyncService.php sync"
echo "   php app/services/SyncService.php status"
echo ""
echo -e "${YELLOW}For more details, see: INTEGRATION_GUIDE.md${NC}"
echo ""
