# SIEM Project: Security Information and Event Management System
## Comprehensive Thesis Presentation (30 Slides)

---

## Slide 1: Title Slide
**Security Information and Event Management (SIEM) Platform**

Subtitle: A Hybrid Python + PHP Architecture for Real-Time Security Event Detection, Log Aggregation, and Threat Analysis

**Project Overview**
- Modern SIEM implementation combining backend intelligence with web-based visualization
- Targets security operations centers (SOCs) and enterprise security teams
- Open-source architecture with real-time detection capabilities

---

## Slide 2: Project Motivation & Problem Statement

**Challenges in Enterprise Security:**
- Exponential growth of log data from diverse sources
- Difficulty correlating events across multiple systems
- Slow incident detection and response times
- Complex attack patterns difficult to identify manually
- Need for centralized security monitoring

**Project Goals:**
- Create affordable, accessible SIEM solution
- Detect common attack vectors automatically
- Provide real-time visualization of security events
- Enable integration with network monitoring devices
- Support multiple data sources (web, system, network logs)

---

## Slide 3: What is SIEM?

**Security Information and Event Management (SIEM):**

A security solution that:
1. **Collects** log data from multiple sources across IT infrastructure
2. **Normalizes** diverse log formats into standardized events
3. **Analyzes** patterns to detect anomalies and attacks
4. **Correlates** related events to identify coordinated threats
5. **Visualizes** security data for quick assessment
6. **Responds** by alerting administrators and enabling automation

**Core Functions:**
- Log collection and aggregation
- Real-time threat detection
- Security incident investigation
- Compliance and audit reporting

---

## Slide 4: High-Level System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    DATA SOURCES LAYER                       │
│  (System Logs, Web Logs, Network Logs, Syslog Devices)      │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│              LOG COLLECTION LAYER                           │
│  (Python SIEM Script, Syslog Listener Service)              │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│         DETECTION & ANALYSIS LAYER                          │
│  (Attack Pattern Matching, Severity Classification)         │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│           DATA PERSISTENCE LAYER                            │
│  (JSON Files, Event Store, Raw Logs, Syslog DB)             │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│         APPLICATION LOGIC LAYER                             │
│  (PHP Models, Controllers, API Endpoints)                   │
└────────────────┬────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────┐
│      PRESENTATION & INTEGRATION LAYER                       │
│  (Web Dashboard, Maps, API, Mobile-Ready UI)                │
└─────────────────────────────────────────────────────────────┘
```

---

## Slide 5: Technology Stack - Overview

**Backend Technologies:**
- **Python 3.6+**: Real-time log collection and threat detection
- **PHP 7.0+**: Web application, API endpoints, data models
- **Apache/LAMPP**: Web server and hosting platform

**Data Storage:**
- **JSON format**: Event data, configurations, status information
- **File-based persistence**: Simple, lightweight, no external database required

**Frontend:**
- **HTML5 + CSS3 + JavaScript**: Responsive web interface
- **Map.js library**: Geolocation visualization
- **Real-time updates**: Live event stream display

---

## Slide 6: Technology Stack - Python (Backend)

**Python SIEM Script (`pythonSIEMscript.py`)**

Key Libraries:
- `socket`: Network communication
- `re`: Regular expressions for log parsing
- `json`: Data serialization
- `datetime`: Timestamp handling
- `threading`: Parallel log processing
- `queue`: Thread-safe event queuing

**Core Capabilities:**
- Tail log files in real-time
- Parse multiple log formats (Apache, Nginx, system logs)
- Detect security anomalies
- Classify threat severity
- Forward events to PHP API
- Collect network statistics

---

## Slide 7: Technology Stack - PHP (Application)

**Key Components:**

1. **Models** (`app/models/`)
   - Event loading and deduplication
   - Syslog analytics
   - Data enrichment
   - Geographic correlation

2. **Controllers** (`app/controllers/`)
   - Dashboard API
   - Logs viewer
   - VLAN visualization
   - Syslog management
   - Sync status monitoring

3. **Services** (`app/services/`)
   - Syslog listener (UDP 514)
   - Event normalization
   - Data persistence

---

## Slide 8: Core Components - Python SIEM Script

**Responsibilities:**
1. **Log Tailing**: Real-time monitoring of log files
2. **Pattern Matching**: Detect SQL injection, XSS, brute force
3. **Event Classification**: Assign severity levels (CRITICAL, HIGH, MEDIUM, LOW)
4. **Data Aggregation**: Compile network statistics and scan metrics
5. **API Integration**: Send events to PHP backend
6. **GUI Visualization**: Optional traffic visualization interface

**Detection Patterns (20+ attack types):**
- SQL Injection (UNION, SELECT, DROP, INSERT, UPDATE)
- Cross-Site Scripting (XSS) attempts
- Directory traversal attacks (../, ..\\)
- Malware signatures (cmd.exe, bash, wget)
- Brute force login attempts
- Privilege escalation
- Network port scanning
- Suspicious file uploads

---

## Slide 9: Core Components - Syslog Infrastructure

**Syslog Listener Service** (`app/services/SyslogListener.php` or `syslog_receiver.py`)

**Features:**
- Listens on UDP port 514 (standard syslog port)
- Parses RFC 3164/5424 syslog messages
- Extracts: Severity, Facility, Host, Application, Message
- Correlates with security events
- Stores for compliance and auditing

**Support for Network Devices:**
- Cisco routers and switches
- Palo Alto Networks firewalls
- Juniper devices
- Check Point appliances
- Generic syslog producers

**Analysis Capabilities:**
- Syslog event frequency tracking
- Source IP analysis
- Application activity monitoring
- Facility distribution
- Severity breakdown

---

## Slide 10: Core Components - PHP API Layer

**REST API Endpoints (`api.php`)**

Event Management:
- `POST /security-events` - Create new security events
- `GET /events` - List all events
- `GET /events/{id}` - Retrieve specific event
- `DELETE /events/{id}` - Delete event

Syslog Management:
- `GET /syslog-entries` - List syslog messages
- `POST /syslog-entries` - Add syslog entry
- `GET /syslog-stats` - Get statistics

Dashboard & Status:
- `GET /dashboard` - Summary metrics
- `GET /server-status` - Host and network status
- `GET /logs-sync-status` - Synchronization status

Data Export:
- `GET /export/{format}` - Export events (JSON, CSV)
- `GET /archive/{date}` - Historical data

---

## Slide 11: Data Persistence & Storage

**JSON File Structure:**

1. **log_data.json** (Primary Event Store)
   - Detected security events
   - Severity levels
   - Timestamps
   - Attack descriptions
   - Source/destination IPs

2. **raw_logs.json** (Raw Log Consolidation)
   - Unprocessed log entries
   - Correlation with events
   - Full context preservation

3. **sim_data.json** (Simulated/Test Data)
   - Synthetic events for testing
   - Demo attack scenarios

4. **server_status.json** (Infrastructure State)
   - Host availability
   - Network connectivity
   - Resource metrics

5. **captured_logs/** (Python Script Output)
   - `security_events.json` - Detected attacks
   - `syslog_received.json` - Syslog entries
   - `network_*.json` - Network statistics

---

## Slide 12: Event Detection & Classification

**Attack Detection Pipeline:**

```
Raw Log Entry
    ↓
[Pattern Matching Engine]
    ↓
Match Found? → YES → [Threat Analysis]
    ↓                      ↓
    NO → Store            [Severity Scoring]
    ↓                      ↓
  Complete             Create Event
                           ↓
                    [Store in Event DB]
                           ↓
                    [Forward to API]
                           ↓
                    [Frontend Update]
```

**Severity Classification:**
- **CRITICAL**: Remote code execution, privilege escalation
- **HIGH**: SQL injection, XSS, authentication bypass
- **MEDIUM**: Suspicious patterns, anomalies
- **LOW**: Informational, monitoring events

---

## Slide 13: Event Correlation & Enrichment

**Correlation Features:**

1. **Temporal Correlation**
   - Group related events within time windows
   - Detect attack sequences
   - Identify coordinated activities

2. **Geolocation Correlation**
   - Map attack sources globally
   - Identify geographic patterns
   - Detect unusual locations

3. **Deduplication**
   - Eliminate duplicate events
   - Reduce noise and false positives
   - Consolidate similar attacks

4. **Context Enrichment**
   - Add threat intelligence data
   - Include system context
   - Link to network topology

5. **Event Aggregation**
   - Group by attack type
   - Consolidate source IPs
   - Calculate attack velocity

---

## Slide 14: Frontend - Dashboard UI

**Dashboard Components:**

1. **Security Summary Widget**
   - Total events
   - Events by severity
   - Critical alerts count
   - 24-hour trend

2. **Real-Time Event Feed**
   - Live event stream
   - Filterable by type/severity
   - Click-through details
   - Export capabilities

3. **Geographic Map Visualization**
   - Attack source locations
   - Global threat distribution
   - Interactive map controls
   - Hover for details

4. **Statistics & Charts**
   - Event trends over time
   - Top attack types
   - Affected assets
   - Response times

---

## Slide 15: Frontend - VLAN & Network Visualization

**Network Views:**

1. **VLAN Overview**
   - Network segment visualization
   - Host connectivity
   - Inter-VLAN traffic
   - Anomaly highlighting

2. **Network Topology Map**
   - Device relationships
   - Traffic flows
   - Security zones
   - Point-of-ingress identification

3. **Asset Inventory**
   - Connected hosts
   - Services running
   - Vulnerability indicators
   - Compliance status

4. **Traffic Analysis**
   - Protocol distribution
   - Port usage
   - Bandwidth trends
   - Anomalous flows

---

## Slide 16: Frontend - Logs Viewer

**Comprehensive Log Interface:**

**Features:**
- Real-time log stream
- Full-text search
- Filter by:
  - Source IP
  - Destination IP
  - Attack type
  - Severity
  - Time range
  - Protocol

**Log Viewing Options:**
- Tail view (latest entries first)
- Timeline view (chronological)
- Raw log display
- Parsed event view

**Actions:**
- Export to CSV/JSON
- Archive for long-term storage
- Create alerts from patterns
- Send to SIEM HQ for correlation

---

## Slide 17: Integration Capabilities

**External Integrations:**

1. **Syslog Integration**
   - Ingest from 100+ device types
   - Unified analysis dashboard
   - Event correlation with web logs

2. **API-First Design**
   - REST endpoints for third-party tools
   - Integration with SOAR platforms
   - Custom alert workflows
   - Automation and orchestration

3. **Export Capabilities**
   - JSON for programmatic access
   - CSV for spreadsheet tools
   - Syslog forwarding
   - Custom webhook integration

4. **Authentication**
   - Session-based access control
   - API key management
   - Role-based permissions (future)

---

## Slide 18: Real-World Attack Scenarios Detected

**1. SQL Injection Attack**
```
Detected: UNION SELECT in HTTP parameter
Severity: CRITICAL
Pattern: SELECT.*FROM.*WHERE
Source IP: 192.168.1.100
Timestamp: 2024-06-15 14:32:45
```

**2. Brute Force Login Attempt**
```
Detected: 50+ failed login attempts
Severity: HIGH
Source IP: 10.0.0.50
Time Window: 5 minutes
Usernames Targeted: admin, root, user
```

**3. Malware Signature Detection**
```
Detected: wget command in web request
Severity: CRITICAL
Pattern: (cmd.exe|bash.*wget|curl.*|*.exe)
Source: Web application logs
Action: Quarantine and alert
```

---

## Slide 19: Threat Scoring & Risk Assessment

**Multi-Factor Risk Scoring:**

```
Risk Score = Base_Threat_Level + 
             (Frequency_Multiplier × Attack_Count) +
             (Geolocation_Risk × Geographic_Factor) +
             (Temporal_Risk × Time_Of_Day_Factor)
```

**Risk Levels:**
- **Critical (90-100)**: Immediate action required
- **High (70-89)**: Urgent investigation needed
- **Medium (40-69)**: Monitor and document
- **Low (1-39)**: Informational, routine monitoring

**Dynamic Adjustment:**
- Machine learning scoring (future)
- Historical baseline comparison
- Industry threat feeds integration
- Contextual risk factors

---

## Slide 20: Incident Response Workflow

**5-Step Incident Response Process:**

1. **Detection** (Automated)
   - SIEM identifies anomaly
   - Pattern matched to threat library
   - Alert generated

2. **Investigation** (Manual/Automated)
   - Review event context
   - Correlate related events
   - Query raw logs
   - Check network status

3. **Containment** (Manual)
   - Isolate affected systems
   - Block suspicious IPs
   - Disable compromised accounts
   - Document actions

4. **Remediation**
   - Patch vulnerabilities
   - Update security rules
   - Close attack vectors
   - Verify system integrity

5. **Post-Incident Review**
   - Document lessons learned
   - Update detection rules
   - Improve response procedures
   - Update playbooks

---

## Slide 21: Deployment Architecture

**Deployment Options:**

**Option 1: Standalone Server**
```
Single Host:
- Apache + PHP
- Python SIEM Script
- JSON storage
- All-in-one solution
Best for: Small teams, POC
```

**Option 2: Distributed Architecture**
```
SIEM Collector Nodes → Central SIEM HQ → Dashboard/API
(Multiple sources)    (Aggregation)     (Analysis)
```

**Option 3: Docker Containerized**
```
Docker Container:
- Isolated environment
- All dependencies included
- Rapid deployment
- Scaling capabilities
```

**Network Requirements:**
- Access to log sources
- Outbound API calls (PHP API)
- Syslog UDP 514 (listener)
- HTTP/HTTPS for dashboard (80/443)

---

## Slide 22: Installation & Setup

**Prerequisites:**
- Linux server with LAMPP
- Python 3.6+
- PHP CLI support
- 2GB+ storage for logs
- Network access to log sources

**5-Minute Installation:**

```bash
# 1. Start LAMPP
sudo /opt/lampp/lampp start

# 2. Navigate to project
cd /opt/lampp/htdocs/SIEMproject

# 3. Set permissions
sudo chown -R www-data:www-data .
chmod 666 *.json

# 4. Create log directories
mkdir -p captured_logs archives

# 5. Start Python SIEM
python3 pythonSIEMscript.py

# 6. Start Syslog Listener (in another terminal)
sudo php app/services/SyslogListener.php
```

**Verification:**
```bash
curl http://localhost/SIEMproject/
curl http://localhost/SIEMproject/api.php/events
```

---

## Slide 23: Configuration Management

**Python Configuration** (`pythonSIEMscript.py`):
```python
PHP_API_ENABLED = True
PHP_API_URL = "http://localhost/SIEMproject/api.php/security-events"
NGINX_LOG_PATH = "/var/log/nginx/access.log"
APACHE_LOG_PATH = "/var/log/apache2/access.log"
SIEM_PORT = 5555
```

**PHP Configuration** (`app/config/config.php`):
```php
$config = [
    'data_files' => [
        'log_data' => 'log_data.json',
        'raw_logs' => 'raw_logs.json',
        'sim_data' => 'sim_data.json'
    ],
    'severity_map' => [
        'CRITICAL' => 5,
        'HIGH' => 4,
        'MEDIUM' => 3,
        'LOW' => 2
    ]
];
```

---

## Slide 24: Performance & Scalability

**Current Performance Metrics:**
- Event processing: ~1000s per minute
- Query response: <200ms for dashboard
- Real-time log tail: <1 second latency
- Concurrent connections: 100+ users

**Scalability Strategies:**

1. **Horizontal Scaling**
   - Multiple collector nodes
   - Distributed event processing
   - Parallel log ingestion

2. **Vertical Scaling**
   - Increase server resources
   - Optimize Python/PHP code
   - Cache frequently accessed data

3. **Database Optimization** (Future)
   - Migrate from JSON to database
   - Implement indexing
   - Partitioning by time/severity

4. **Load Balancing**
   - Round-robin API requests
   - Distributed collector nodes
   - Geographic distribution

---

## Slide 25: Security Best Practices

**Protecting the SIEM Itself:**

1. **Access Control**
   - Restrict dashboard access
   - API authentication required
   - Network segmentation
   - Firewall rules

2. **Data Protection**
   - Encrypt sensitive logs (future)
   - Secure log file permissions
   - Regular backups
   - Data retention policies

3. **Audit Logging**
   - Log all SIEM access
   - Track API calls
   - Monitor configuration changes
   - Alert on unauthorized access

4. **Network Security**
   - Limit syslog listener exposure
   - Use VPN for remote access
   - Disable unnecessary services
   - Regular security patches

---

## Slide 26: Compliance & Audit Reporting

**Standards Supported:**

1. **PCI-DSS** (Payment Card Industry)
   - Event logging requirements
   - Real-time alerting
   - Log retention and review

2. **HIPAA** (Healthcare)
   - Access controls
   - Audit trails
   - Incident detection

3. **SOC 2** (Service Organization Control)
   - Security controls
   - Availability and reliability
   - Data confidentiality

4. **GDPR** (General Data Protection)
   - Data retention limits
   - Privacy controls
   - Breach notification

**Reporting Capabilities:**
- Compliance dashboard
- Automated report generation
- Evidence collection
- Audit trail export

---

## Slide 27: Lessons Learned & Future Enhancements

**Current Limitations:**
- JSON-based storage lacks indexing
- Single-host scalability limits
- No machine learning (yet)
- Manual incident response workflows
- Limited historical analysis

**Proposed Enhancements:**

1. **Database Migration**
   - Replace JSON with PostgreSQL/MongoDB
   - Implement full-text search
   - Time-series optimization

2. **Machine Learning**
   - Anomaly detection algorithms
   - Automated threat scoring
   - Behavioral baselines

3. **Advanced Correlation**
   - Cross-cluster analysis
   - Multi-source threat tracking
   - Campaign attribution

4. **Automation**
   - SOAR integration
   - Automated response playbooks
   - Orchestrated incident handling

5. **Mobile & Cloud**
   - Mobile dashboard
   - Cloud-native deployment
   - Container orchestration

---

## Slide 28: Testing & Validation

**Testing Coverage:**

1. **Unit Testing**
   - Pattern matching accuracy
   - Severity classification
   - Data parsing correctness

2. **Integration Testing**
   - API endpoint functionality
   - Event flow end-to-end
   - Syslog ingestion
   - Frontend data binding

3. **Security Testing**
   - Attack pattern detection
   - False positive rates
   - Threshold tuning
   - Known attack scenarios

4. **Performance Testing**
   - Load testing (1000+ events/min)
   - Concurrent user connections
   - Database query response times
   - Memory footprint

**Validation Methods:**
- Injected attack simulation
- Log file injection tests
- Syslog message validation
- Comparison with industry tools

---

## Slide 29: Conclusion & Key Takeaways

**Project Summary:**

This SIEM platform demonstrates a practical approach to security event management by combining:
- **Real-time detection** with Python pattern matching
- **Web-based visualization** with intuitive PHP interface
- **Flexible integration** with syslog and external systems
- **Scalable architecture** for enterprise deployments

**Key Achievements:**
✓ Detects 20+ attack patterns in real-time
✓ Processes thousands of log entries per minute
✓ Provides geographic threat visualization
✓ Supports network device integration
✓ Offers REST API for third-party tools
✓ Implements incident response workflow

**Impact:**
- Reduces mean-time-to-detect (MTTD)
- Improves SOC team efficiency
- Enables automated incident response
- Provides compliance audit trails
- Demonstrates open-source SIEM viability

---

## Slide 30: Questions & Technical Resources

**Questions for Defense:**

*Anticipated questions:*
1. How does performance scale with 10,000+ events/day?
2. What's the false positive rate and tuning process?
3. How does this compare to commercial SIEM solutions?
4. What's the recovery strategy for data loss?
5. How would you implement real-time alerting?
6. What's the roadmap for machine learning?

**Technical Resources:**

**Documentation Files:**
- `README.md` - Architecture and components
- `CONFIGURATION.md` - Setup and tuning guide
- `QUICK_SETUP.md` - Installation instructions
- `USER_GUIDE.md` - Operations and troubleshooting

**GitHub Repository:**
`https://github.com/Mehrnikoo/SIEMproject`

**Key Code Locations:**
- Detection engine: `pythonSIEMscript.py`
- API layer: `api.php`
- Frontend dashboard: `public/` directory
- Models & controllers: `app/models/` and `app/controllers/`

**Contact & Support:**
- Repository issues tracker
- Documentation wiki
- Community forum (if applicable)

---

## End of Presentation

**Thesis Slide Content Complete**
- **Total Slides**: 30
- **Topics Covered**: Architecture, Components, Technology, Deployment, Security, Operations
- **Format**: Markdown - Ready for conversion to PowerPoint/Google Slides/PDF

**Recommended Next Steps:**
1. Convert to presentation software (PowerPoint, Google Slides, Beamer)
2. Add visuals/diagrams for each slide
3. Include live demo video
4. Prepare speaker notes
5. Create handout materials

---
