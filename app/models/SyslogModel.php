<?php
/**
 * Syslog Model - Reads and processes syslog messages from network devices
 */
class SyslogModel {
    private $config;
    private $syslog_file;
    
    public function __construct($config) {
        $this->config = $config;
        $this->syslog_file = dirname(__DIR__, 2) . '/captured_logs/syslog_received.json';
    }
    
    /**
     * Load all received syslog entries
     */
    public function loadSyslogEntries($limit = 1000) {
        if (!file_exists($this->syslog_file)) {
            return [];
        }
        
        $data = @file_get_contents($this->syslog_file);
        $entries = json_decode($data, true) ?: [];
        
        // Sort by received_at (newest first)
        usort($entries, function($a, $b) {
            $timeA = strtotime($a['received_at'] ?? '');
            $timeB = strtotime($b['received_at'] ?? '');
            return $timeB <=> $timeA;
        });
        
        // Limit results
        if ($limit) {
            $entries = array_slice($entries, 0, $limit);
        }
        
        return $entries;
    }
    
    /**
     * Get syslog entries from a specific source IP
     */
    public function getSyslogByIP($ip, $limit = 100) {
        $entries = $this->loadSyslogEntries(10000);
        $filtered = array_filter($entries, function($entry) use ($ip) {
            return ($entry['source_ip'] ?? '') === $ip;
        });
        return array_slice($filtered, 0, $limit);
    }
    
    /**
     * Get syslog entries with high severity (emergency, alert, critical, error)
     */
    public function getHighSeveritySyslog($limit = 100) {
        $entries = $this->loadSyslogEntries(10000);
        $filtered = array_filter($entries, function($entry) {
            $severity = $entry['severity'] ?? 7;
            return $severity <= 3; // 0=Emergency, 1=Alert, 2=Critical, 3=Error
        });
        return array_slice($filtered, 0, $limit);
    }
    
    /**
     * Get syslog statistics
     */
    public function getSyslogStats() {
        $entries = $this->loadSyslogEntries(10000);
        
        $stats = [
            'total_entries' => count($entries),
            'unique_sources' => 0,
            'severity_breakdown' => [],
            'facility_breakdown' => [],
            'top_sources' => [],
            'last_entry_time' => null
        ];
        
        $sources = [];
        $severities = [];
        $facilities = [];
        
        foreach ($entries as $entry) {
            $sources[$entry['source_ip']] = ($sources[$entry['source_ip']] ?? 0) + 1;
            $severity = $entry['severity_name'] ?? 'Unknown';
            $severities[$severity] = ($severities[$severity] ?? 0) + 1;
            $facility = $entry['facility_name'] ?? 'Unknown';
            $facilities[$facility] = ($facilities[$facility] ?? 0) + 1;
        }
        
        $stats['unique_sources'] = count($sources);
        $stats['severity_breakdown'] = $severities;
        $stats['facility_breakdown'] = $facilities;
        
        // Top 10 sources
        arsort($sources);
        $stats['top_sources'] = array_slice($sources, 0, 10, true);
        
        // Last entry time
        if (!empty($entries)) {
            $stats['last_entry_time'] = $entries[0]['received_at'] ?? null;
        }
        
        return $stats;
    }
    
    /**
     * Detect potential threats in syslog entries
     * Returns array of flagged entries
     */
    public function detectSyslogThreats() {
        $entries = $this->loadSyslogEntries(1000);
        $threats = [];
        
        foreach ($entries as $entry) {
            $threat_score = 0;
            $threat_reasons = [];
            
            $message = $entry['message'] ?? '';
            $tag = $entry['tag'] ?? '';
            $severity = $entry['severity'] ?? 7;
            
            // 1. High severity messages
            if ($severity <= 2) { // Emergency, Alert, Critical
                $threat_score += 50;
                $threat_reasons[] = 'High severity level: ' . $entry['severity_name'];
            }
            
            // 2. Authentication failures
            if (preg_match('/failed|refused|denied|failure|invalid|unauthorized/i', $message)) {
                $threat_score += 30;
                $threat_reasons[] = 'Authentication failure pattern detected';
            }
            
            // 3. Root/Privilege escalation attempts
            if (preg_match('/root|sudo|privilege|escalation|SUID|uid=0/i', $message)) {
                $threat_score += 25;
                $threat_reasons[] = 'Privilege escalation pattern detected';
            }
            
            // 4. Port scanning
            if (preg_match('/scan|port\s+scan|network\s+scan|nmap|masscan/i', $message)) {
                $threat_score += 20;
                $threat_reasons[] = 'Port scanning detected';
            }
            
            // 5. Firewall blocked connections
            if (preg_match('/DROP|REJECT|blocked|denied|firewall/i', $message)) {
                $threat_score += 15;
                $threat_reasons[] = 'Firewall blocking detected';
            }
            
            // 6. SSH/RDP attacks
            if (preg_match('/ssh|sshd|invalid user|rsa|key exchange|authentication/i', $tag . $message)) {
                $threat_score += 10;
                $threat_reasons[] = 'SSH/RDP activity detected';
            }
            
            // 7. System errors that may indicate attacks
            if (preg_match('/segmentation|buffer|overflow|exploit|crash|panic/i', $message)) {
                $threat_score += 35;
                $threat_reasons[] = 'Potential exploit pattern detected';
            }
            
            // Add to threats if score is high enough
            if ($threat_score >= 20) {
                $threats[] = [
                    'syslog_entry' => $entry,
                    'threat_score' => $threat_score,
                    'threat_reasons' => $threat_reasons,
                    'estimated_severity' => $this->scoreTothreshold($threat_score)
                ];
            }
        }
        
        // Sort by threat score
        usort($threats, function($a, $b) {
            return $b['threat_score'] <=> $a['threat_score'];
        });
        
        return $threats;
    }
    
    /**
     * Convert threat score to severity level
     */
    private function scoreTothreshold($score) {
        if ($score >= 60) return 'Critical';
        if ($score >= 40) return 'High';
        if ($score >= 25) return 'Medium';
        return 'Low';
    }
    
    /**
     * Export syslog entries as CSV
     */
    public function exportAsCSV($filename = null) {
        if (!$filename) {
            $filename = dirname(__DIR__, 2) . '/archives/syslog_export_' . date('Ymd_His') . '.csv';
        }
        
        $entries = $this->loadSyslogEntries(10000);
        
        $fp = fopen($filename, 'w');
        if (!$fp) return false;
        
        // Write CSV header
        fputcsv($fp, [
            'Timestamp',
            'Received At',
            'Source IP',
            'Source Port',
            'Facility',
            'Severity',
            'Hostname',
            'Tag',
            'Message'
        ]);
        
        // Write rows
        foreach ($entries as $entry) {
            fputcsv($fp, [
                $entry['timestamp'] ?? '',
                $entry['received_at'] ?? '',
                $entry['source_ip'] ?? '',
                $entry['source_port'] ?? '',
                $entry['facility_name'] ?? '',
                $entry['severity_name'] ?? '',
                $entry['hostname'] ?? '',
                $entry['tag'] ?? '',
                $entry['message'] ?? ''
            ]);
        }
        
        fclose($fp);
        return $filename;
    }
}
?>
