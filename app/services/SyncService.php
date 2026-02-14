<?php
/**
 * Sync Service - Synchronizes Python-generated logs with website
 * Can be run manually via CLI or periodically via cron
 * Usage: php app/services/SyncService.php
 */

define('BASE_PATH', dirname(__DIR__, 2));

// Load configuration
$config = require_once BASE_PATH . '/app/config/config.php';

require_once BASE_PATH . '/app/models/EventModel.php';
require_once BASE_PATH . '/app/models/LogsModel.php';

class SyncService {
    private $config;
    private $eventModel;
    private $logsModel;
    private $pythonLogsDir;
    
    public function __construct($config) {
        $this->config = $config;
        $this->eventModel = new EventModel($config);
        $this->logsModel = new LogsModel($config);
        $this->pythonLogsDir = BASE_PATH . '/captured_logs';
        
        if (!is_dir($this->pythonLogsDir)) {
            mkdir($this->pythonLogsDir, 0755, true);
        }
    }
    
    /**
     * Sync all Python events and logs to website data files
     */
    public function syncAll() {
        $this->log("[Sync] Starting synchronization...");
        
        $events_synced = $this->syncSecurityEvents();
        $this->log("[Sync] ✓ Security events synced: $events_synced events");
        
        $logs_synced = $this->syncRawLogs();
        $this->log("[Sync] ✓ Raw logs synced: $logs_synced logs");
        
        $this->log("[Sync] Synchronization complete!");
        return true;
    }
    
    /**
     * Output message only in CLI mode
     */
    private function log($message) {
        if (php_sapi_name() === 'cli') {
            echo $message . "\n";
        }
    }
    
    /**
     * Sync security events from captured_logs/security_events.json to log_data.json
     */
    private function syncSecurityEvents() {
        $security_file = $this->pythonLogsDir . '/security_events.json';
        
        if (!file_exists($security_file)) {
            return 0;
        }
        
        $content = @file_get_contents($security_file);
        $python_events = json_decode($content, true) ?: [];
        
        if (empty($python_events)) {
            return 0;
        }
        
        // Load existing events
        $log_data_file = $this->config['data_files']['log_data'];
        $existing_events = [];
        
        if (file_exists($log_data_file)) {
            $content = @file_get_contents($log_data_file);
            $existing_events = json_decode($content, true) ?: [];
        }
        
        // Normalize Python events
        $normalized_events = [];
        foreach ($python_events as $event) {
            $normalized = $this->normalizePythonEvent($event);
            $normalized_events[] = $normalized;
        }
        
        // Merge, deduplicate, and save
        $merged = array_merge($existing_events, $normalized_events);
        $merged = $this->deduplicateEvents($merged);
        
        // Keep only last 500 events
        if (count($merged) > 500) {
            $merged = array_slice($merged, -500);
        }
        
        file_put_contents($log_data_file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        return count($normalized_events);
    }
    
    /**
     * Sync raw logs from captured_logs/*.json to raw_logs.json
     */
    private function syncRawLogs() {
        if (!is_dir($this->pythonLogsDir)) {
            return 0;
        }
        
        $logs = [];
        $files = glob($this->pythonLogsDir . '/*.json');
        $log_count = 0;
        
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
                    $log = [
                        'timestamp' => $entry['timestamp'] ?? date('Y-m-d H:i:s'),
                        'log_type' => $filename,
                        'log_file' => stripslashes($filename),
                        'raw_line' => $this->extractRawLine($entry),
                        'source_ip' => $entry['ip'] ?? $entry['source'] ?? 'unknown',
                        'from_python' => true,
                        'data' => $entry
                    ];
                    
                    $logs[] = $log;
                    $log_count++;
                }
            }
        }
        
        if (empty($logs)) {
            return 0;
        }
        
        // Sort by timestamp
        usort($logs, function($a, $b) {
            $timeA = strtotime($a['timestamp']);
            $timeB = strtotime($b['timestamp']);
            return $timeB <=> $timeA;
        });
        
        // Keep only last 1000 logs
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        $raw_logs_file = $this->config['data_files']['raw_logs'];
        file_put_contents($raw_logs_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        return $log_count;
    }
    
    /**
     * Normalize Python event to website format
     */
    private function normalizePythonEvent($event) {
        // Extract severity level
        $severity = 'Low';
        if (isset($event['severity_sticker'])) {
            preg_match('/^(\w+)/', $event['severity_sticker'], $matches);
            $severity = $matches[1] ?? 'Low';
        }
        
        // Determine event type
        $type = $this->isInternalIP($event['source'] ?? '') ? 'INTERNAL' : 'EXTERNAL';
        
        return [
            'id' => $event['id'] ?? 'evt-' . uniqid(),
            'timestamp' => $event['timestamp'] ?? date('Y-m-d H:i:s'),
            'severity' => $severity,
            'type' => $type,
            'attack_type' => $event['attack_type'] ?? 'Unknown',
            'source' => $event['source'] ?? '0.0.0.0',
            'target' => $event['target'] ?? 'localhost',
            'details' => $event['details'] ?? '',
            'formatted_log' => $event['formatted_log'] ?? '',
            'country' => 'Unknown',
            'from_python' => true
        ];
    }
    
    /**
     * Extract raw line from log entry
     */
    private function extractRawLine($entry) {
        if (is_string($entry)) {
            return $entry;
        }
        
        if (is_array($entry)) {
            if (isset($entry['message'])) {
                return $entry['message'];
            }
            if (isset($entry['MESSAGE'])) {
                return $entry['MESSAGE'];
            }
            if (isset($entry['raw'])) {
                return $entry['raw'];
            }
            return json_encode($entry);
        }
        
        return '';
    }
    
    /**
     * Remove duplicate events by ID
     */
    private function deduplicateEvents($events) {
        $seen_ids = [];
        $unique = [];
        
        foreach ($events as $event) {
            $id = $event['id'] ?? null;
            if ($id && !in_array($id, $seen_ids)) {
                $seen_ids[] = $id;
                $unique[] = $event;
            } elseif (!$id) {
                $unique[] = $event;
            }
        }
        
        return $unique;
    }
    
    /**
     * Check if IP is private
     */
    private function isInternalIP($ip) {
        $patterns = [
            '/^10\./i',
            '/^172\.(1[6-9]|2[0-9]|3[01])\./i',
            '/^192\.168\./i',
            '/^127\./i',
            '/^169\.254\./i',
            '/localhost/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $ip)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get sync status
     */
    public function getStatus() {
        $security_file = $this->pythonLogsDir . '/security_events.json';
        $security_count = 0;
        
        if (file_exists($security_file)) {
            $content = @file_get_contents($security_file);
            $events = json_decode($content, true) ?: [];
            $security_count = count($events);
        }
        
        // Count raw logs
        $raw_count = 0;
        $files = glob($this->pythonLogsDir . '/*.json');
        foreach ($files as $file) {
            if (basename($file, '.json') !== 'security_events') {
                $content = @file_get_contents($file);
                $data = json_decode($content, true) ?: [];
                $raw_count += count($data);
            }
        }
        
        return [
            'security_events' => $security_count,
            'raw_logs' => $raw_count,
            'python_logs_dir' => $this->pythonLogsDir,
            'last_sync' => date('Y-m-d H:i:s')
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $sync = new SyncService($config);
    
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'status':
                $status = $sync->getStatus();
                echo "=== Sync Status ===\n";
                echo "Security Events: " . $status['security_events'] . "\n";
                echo "Raw Logs: " . $status['raw_logs'] . "\n";
                echo "Python Logs Dir: " . $status['python_logs_dir'] . "\n";
                echo "Last Sync: " . $status['last_sync'] . "\n";
                break;
            case 'sync':
            default:
                $sync->syncAll();
        }
    } else {
        $sync->syncAll();
    }
}
