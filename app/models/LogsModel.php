<?php
/**
 * Logs Model - Handles raw log data retrieval
 */
class LogsModel {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Load all raw logs including Python-generated logs
     */
    public function loadRawLogs() {
        $logs = [];
        
        // Load from raw_logs.json
        $file = $this->config['data_files']['raw_logs'];
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $data = json_decode($content, true);
            if (is_array($data)) {
                $logs = array_merge($logs, $data);
            }
        }
        
        // Load from Python-generated captured_logs
        $pythonLogs = $this->loadPythonCapturedLogs();
        $logs = array_merge($logs, $pythonLogs);
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            $timeA = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
            $timeB = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
            return $timeB <=> $timeA;
        });
        
        return $logs;
    }
    
    /**
     * Load logs captured by Python SIEM script from captured_logs directory
     */
    public function loadPythonCapturedLogs() {
        $logs = [];
        $baseDir = dirname(__DIR__, 2); // Go up to SIEMproject root
        $capturedLogsDir = $baseDir . '/captured_logs';
        
        if (!is_dir($capturedLogsDir)) {
            return [];
        }
        
        // Read all JSON files except security_events.json
        $files = glob($capturedLogsDir . '/*.json');
        foreach ($files as $file) {
            $filename = basename($file, '.json');
            
            // Skip security events as they're handled separately
            if ($filename === 'security_events') {
                continue;
            }
            
            $content = @file_get_contents($file);
            $data = json_decode($content, true);
            
            if (is_array($data)) {
                foreach ($data as $entry) {
                    // Normalize to standard log format
                    $log = [
                        'timestamp' => $entry['timestamp'] ?? date('Y-m-d H:i:s'),
                        'log_type' => $filename, // e.g., "nginx_access", "linux_system"
                        'log_file' => $filename,
                        'raw_line' => $this->extractRawLine($entry),
                        'source_ip' => $entry['ip'] ?? $entry['source'] ?? 'unknown',
                        'from_python' => true,
                        'data' => $entry
                    ];
                    
                    $logs[] = $log;
                }
            }
        }
        
        return $logs;
    }
    
    /**
     * Extract a readable single-line representation from a log entry
     */
    private function extractRawLine($entry) {
        if (is_string($entry)) {
            return $entry;
        }
        
        if (is_array($entry)) {
            // Try to find a message field
            if (isset($entry['message'])) {
                return $entry['message'];
            }
            if (isset($entry['MESSAGE'])) {
                return $entry['MESSAGE'];
            }
            if (isset($entry['formatted_log'])) {
                return $entry['formatted_log'];
            }
            
            // Fallback: convert to JSON string
            return json_encode($entry);
        }
        
        return '';
    }
    
    /**
     * Filter logs by type
     */
    public function filterLogsByType($logs, $type) {
        if (empty($type) || $type === 'All') {
            return $logs;
        }
        
        return array_filter($logs, function($log) use ($type) {
            return isset($log['log_type']) && $log['log_type'] === $type;
        });
    }
    
    /**
     * Filter logs by search term
     */
    public function filterLogsBySearch($logs, $search) {
        if (empty($search)) {
            return $logs;
        }
        
        $searchLower = strtolower($search);
        return array_filter($logs, function($log) use ($searchLower) {
            $rawLine = isset($log['raw_line']) ? strtolower($log['raw_line']) : '';
            $logFile = isset($log['log_file']) ? strtolower($log['log_file']) : '';
            $logType = isset($log['log_type']) ? strtolower($log['log_type']) : '';
            $sourceIp = isset($log['source_ip']) ? strtolower($log['source_ip']) : '';
            
            return strpos($rawLine, $searchLower) !== false ||
                   strpos($logFile, $searchLower) !== false ||
                   strpos($logType, $searchLower) !== false ||
                   strpos($sourceIp, $searchLower) !== false;
        });
    }
    
    /**
     * Get unique log types
     */
    public function getLogTypes($logs) {
        $types = [];
        foreach ($logs as $log) {
            if (isset($log['log_type'])) {
                $types[$log['log_type']] = true;
            }
        }
        return array_keys($types);
    }
    
    /**
     * Paginate logs
     */
    public function paginateLogs($logs, $page = 1, $perPage = 50) {
        $total = count($logs);
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        
        return [
            'logs' => array_slice($logs, $offset, $perPage),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_logs' => $total,
            'per_page' => $perPage
        ];
    }
    
    /**
     * Archive old logs older than specified hours
     */
    public function archiveOldLogs($hoursOld = 24) {
        $archiveDir = dirname(__DIR__, 2) . '/archives';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }
        
        $cutoffTime = time() - ($hoursOld * 3600);
        
        // Archive raw_logs.json
        $rawLogsFile = $this->config['data_files']['raw_logs'];
        if (file_exists($rawLogsFile)) {
            $content = @file_get_contents($rawLogsFile);
            $logs = json_decode($content, true) ?: [];
            
            $current = [];
            $archive = [];
            
            foreach ($logs as $log) {
                $logTime = isset($log['timestamp']) ? strtotime($log['timestamp']) : 0;
                if ($logTime < $cutoffTime) {
                    $archive[] = $log;
                } else {
                    $current[] = $log;
                }
            }
            
            if (!empty($archive)) {
                $archiveFile = $archiveDir . '/raw_logs_' . date('Y-m-d_H-i-s') . '.json';
                file_put_contents($archiveFile, json_encode($archive, JSON_PRETTY_PRINT));
            }
            
            file_put_contents($rawLogsFile, json_encode($current, JSON_PRETTY_PRINT));
        }
        
        // Archive captured_logs
        $capturedDir = dirname(__DIR__, 2) . '/captured_logs';
        if (is_dir($capturedDir)) {
            $files = glob($capturedDir . '/*.json');
            foreach ($files as $file) {
                $content = @file_get_contents($file);
                $logs = json_decode($content, true) ?: [];
                
                $current = [];
                $archive = [];
                
                foreach ($logs as $log) {
                    $logTime = isset($log['timestamp']) ? strtotime($log['timestamp']) : 0;
                    if ($logTime < $cutoffTime) {
                        $archive[] = $log;
                    } else {
                        $current[] = $log;
                    }
                }
                
                if (!empty($archive)) {
                    $basename = basename($file, '.json');
                    $archiveFile = $archiveDir . '/' . $basename . '_' . date('Y-m-d_H-i-s') . '.json';
                    file_put_contents($archiveFile, json_encode($archive, JSON_PRETTY_PRINT));
                }
                
                file_put_contents($file, json_encode($current, JSON_PRETTY_PRINT));
            }
        }
    }
}

