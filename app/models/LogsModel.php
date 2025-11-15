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
     * Load all raw logs
     */
    public function loadRawLogs() {
        $file = $this->config['data_files']['raw_logs'];
        
        if (!file_exists($file)) {
            return [];
        }
        
        $content = @file_get_contents($file);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
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
}

