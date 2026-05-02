<?php
/**
 * Syslog Controller - API endpoints for syslog data
 */

class SyslogController {
    private $syslog_model;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        require_once dirname(__DIR__) . '/models/SyslogModel.php';
        $this->syslog_model = new SyslogModel($config);
    }
    
    /**
     * Get recent syslog entries
     * GET /api.php/syslog-entries?limit=100&severity=high
     */
    public function getEntries() {
        $limit = $_GET['limit'] ?? 100;
        $severity_filter = $_GET['severity'] ?? null;
        
        $entries = $this->syslog_model->loadSyslogEntries($limit);
        
        // Filter by severity if requested
        if ($severity_filter) {
            $severity_map = [
                'critical' => [0, 1, 2],
                'high' => [0, 1, 2, 3],
                'medium' => [0, 1, 2, 3, 4],
                'low' => [0, 1, 2, 3, 4, 5, 6, 7]
            ];
            
            $allowed_severities = $severity_map[$severity_filter] ?? [];
            $entries = array_filter($entries, function($entry) use ($allowed_severities) {
                return in_array($entry['severity'] ?? 7, $allowed_severities);
            });
        }
        
        return [
            'status' => 'success',
            'count' => count($entries),
            'entries' => array_values($entries)
        ];
    }
    
    /**
     * Get syslog entries from specific source IP
     * GET /api.php/syslog-by-ip?ip=192.168.1.1&limit=50
     */
    public function getByIP() {
        $ip = $_GET['ip'] ?? null;
        $limit = $_GET['limit'] ?? 100;
        
        if (!$ip) {
            return ['status' => 'error', 'message' => 'IP parameter required'];
        }
        
        $entries = $this->syslog_model->getSyslogByIP($ip, $limit);
        
        return [
            'status' => 'success',
            'source_ip' => $ip,
            'count' => count($entries),
            'entries' => $entries
        ];
    }
    
    /**
     * Get high-severity syslog entries
     * GET /api.php/syslog-high-severity?limit=50
     */
    public function getHighSeverity() {
        $limit = $_GET['limit'] ?? 100;
        $entries = $this->syslog_model->getHighSeveritySyslog($limit);
        
        return [
            'status' => 'success',
            'count' => count($entries),
            'entries' => $entries
        ];
    }
    
    /**
     * Get syslog statistics
     * GET /api.php/syslog-stats
     */
    public function getStats() {
        $stats = $this->syslog_model->getSyslogStats();
        
        return [
            'status' => 'success',
            'stats' => $stats
        ];
    }
    
    /**
     * Detect threats in syslog
     * GET /api.php/syslog-threats?limit=50
     */
    public function detectThreats() {
        $threats = $this->syslog_model->detectSyslogThreats();
        
        // Limit results
        $limit = $_GET['limit'] ?? 50;
        $threats = array_slice($threats, 0, $limit);
        
        return [
            'status' => 'success',
            'threat_count' => count($threats),
            'threats' => $threats
        ];
    }
    
    /**
     * Get syslog status (listener running?)
     * GET /api.php/syslog-status
     */
    public function getStatus() {
        $syslog_file = dirname(__DIR__, 2) . '/captured_logs/syslog_received.json';
        $is_receiving = false;
        $last_entry_time = null;
        $total_entries = 0;
        $listener_status = 'unknown';
        
        if (file_exists($syslog_file)) {
            $data = @file_get_contents($syslog_file);
            $entries = json_decode($data, true) ?: [];
            $total_entries = count($entries);
            
            if (!empty($entries)) {
                $last_entry = $entries[count($entries) - 1];
                $last_entry_time = $last_entry['received_at'] ?? null;
                
                // If last entry was in last 5 minutes, listener is active
                if ($last_entry_time) {
                    $last_time = strtotime($last_entry_time);
                    $now = time();
                    $is_receiving = ($now - $last_time) < 300; // 5 minutes
                }
            }
        }
        
        // Try to check if listener process is running
        if (function_exists('shell_exec')) {
            $ps_output = @shell_exec('ps aux | grep SyslogListener.php | grep -v grep | wc -l');
            $listener_status = (intval(trim($ps_output)) > 0) ? 'running' : 'stopped';
        }
        
        return [
            'status' => 'success',
            'syslog_listener' => [
                'process_status' => $listener_status,
                'is_receiving' => $is_receiving,
                'total_entries' => $total_entries,
                'last_entry_time' => $last_entry_time,
                'storage_file' => $syslog_file
            ]
        ];
    }
    
    /**
     * Export syslog data as CSV
     * GET /api.php/syslog-export
     */
    public function export() {
        $filename = $this->syslog_model->exportAsCSV();
        
        if (!$filename) {
            return ['status' => 'error', 'message' => 'Export failed'];
        }
        
        return [
            'status' => 'success',
            'message' => 'Syslog exported to CSV',
            'file' => basename($filename),
            'path' => $filename
        ];
    }
    
    /**
     * Clear syslog data (admin only)
     * POST /api.php/syslog-clear
     */
    public function clear() {
        // Check if admin token provided
        $token = $_GET['token'] ?? $_POST['token'] ?? null;
        if ($token !== 'admin-clear-syslog-token') {
            return ['status' => 'error', 'message' => 'Unauthorized'];
        }
        
        $syslog_file = dirname(__DIR__, 2) . '/captured_logs/syslog_received.json';
        if (file_put_contents($syslog_file, '[]')) {
            return [
                'status' => 'success',
                'message' => 'Syslog data cleared'
            ];
        }
        
        return ['status' => 'error', 'message' => 'Failed to clear syslog'];
    }
}
?>
