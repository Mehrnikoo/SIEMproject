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
        $logs = $this->deduplicateLogs($logs);
        
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
     * Get raw logs for a specific source IP (within a time window)
     */
    public function getLogsBySourceIP($sourceIp, $timeWindow = 300) {
        $allLogs = $this->loadRawLogs();
        
        $matching = [];
        foreach ($allLogs as $log) {
            if (isset($log['source_ip']) && $log['source_ip'] === $sourceIp) {
                $matching[] = $log;
            }
        }
        
        // Sort by timestamp, newest first
        usort($matching, function($a, $b) {
            $timeA = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
            $timeB = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
            return $timeB <=> $timeA;
        });
        
        return $matching;
    }
    
    /**
     * Get raw logs that occurred around a specific event time
     * Uses a generous time window (±1 hour by default) to account for processing delays
     */
    public function getLogsNearEvent($sourceIp, $eventTime, $timeWindowSeconds = 3600) {
        $allLogs = $this->loadRawLogs();
        
        if (!$eventTime) {
            // If no event time, just return all logs for this IP
            return $this->getLogsBySourceIP($sourceIp);
        }
        
        $eventTimestamp = strtotime($eventTime);
        if ($eventTimestamp === false) {
            // Fallback: return all logs for this IP
            return $this->getLogsBySourceIP($sourceIp);
        }
        
        $matching = [];
        foreach ($allLogs as $log) {
            if (isset($log['source_ip']) && $log['source_ip'] === $sourceIp) {
                $logTime = isset($log['timestamp']) ? strtotime($log['timestamp']) : 0;
                
                // Check if log is within the time window
                // Allow time before and after the event (processing delays)
                if (abs($logTime - $eventTimestamp) <= $timeWindowSeconds) {
                    $matching[] = $log;
                }
            }
        }
        
        // If no logs within the time window, get ALL logs for this IP
        if (empty($matching)) {
            $matching = $this->getLogsBySourceIP($sourceIp);
        }
        
        // Sort by timestamp, newest first
        usort($matching, function($a, $b) {
            $timeA = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
            $timeB = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
            return $timeB <=> $timeA;
        });

        return $this->deduplicateLogs($matching);
    }

    /**
     * Remove duplicate raw logs to keep table/rendering clean.
     */
    private function deduplicateLogs($logs) {
        if (!is_array($logs) || empty($logs)) {
            return [];
        }

        $seen = [];
        $deduped = [];
        foreach ($logs as $log) {
            if (!is_array($log)) {
                continue;
            }
            $id = trim((string)($log['id'] ?? ''));
            $idLower = strtolower($id);
            if ($id !== '' && $id !== '0' && $idLower !== 'unknown' && $idLower !== 'n/a') {
                $key = 'id:' . $idLower;
            } else {
                $key = 'fp:' . md5(strtolower(implode('|', [
                    (string)($log['timestamp'] ?? ''),
                    (string)($log['log_type'] ?? ''),
                    (string)($log['log_file'] ?? ''),
                    (string)($log['source_ip'] ?? ''),
                    (string)($log['destination_ip'] ?? ''),
                    (string)($log['raw_line'] ?? ''),
                ])));
            }

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $log;
        }
        return $deduped;
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

