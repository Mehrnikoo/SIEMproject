<?php
/**
 * Syslog Listener Service - Receives and processes syslog messages from network devices
 * 
 * Usage:
 *   nohup sudo php app/services/SyslogListener.php > logs/syslog_listener.log 2>&1 &
 * 
 * Configure network devices to send syslog to your SIEM server on UDP port 514
 */

class SyslogListener {
    private $port = 514;
    private $listen_addr = '0.0.0.0';
    private $socket = null;
    private $buffer_size = 65535;
    private $logs_dir = null;
    private $syslog_file = null;
    
    public function __construct($port = 514, $logs_dir = null) {
        $this->port = $port;
        $this->logs_dir = $logs_dir ?? dirname(__DIR__, 3) . '/captured_logs';
        $this->syslog_file = $this->logs_dir . '/syslog_received.json';
        
        // Create logs directory if it doesn't exist
        if (!is_dir($this->logs_dir)) {
            mkdir($this->logs_dir, 0777, true);
        }
    }
    
    /**
     * Start listening for syslog messages
     */
    public function start() {
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        
        if (!$this->socket) {
            echo "[ERROR] Failed to create UDP socket: " . socket_strerror(socket_last_error()) . "\n";
            exit(1);
        }
        
        // Set socket options
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVBUF, 1024 * 1024); // 1MB buffer
        
        // Bind to port
        $bind_result = socket_bind($this->socket, $this->listen_addr, $this->port);
        if (!$bind_result) {
            echo "[ERROR] Failed to bind to {$this->listen_addr}:{$this->port}: " . socket_strerror(socket_last_error()) . "\n";
            echo "[INFO] Make sure you have permission (try: sudo) or port is not already in use\n";
            exit(1);
        }
        
        echo "[INFO] Syslog Listener started on {$this->listen_addr}:{$this->port}\n";
        echo "[INFO] Storing received logs in: {$this->syslog_file}\n";
        echo "[INFO] Waiting for syslog messages...\n\n";
        
        // Main listening loop
        while (true) {
            $this->receive_and_process();
        }
    }
    
    /**
     * Receive a single syslog message and process it
     */
    private function receive_and_process() {
        $remote_addr = '';
        $remote_port = 0;
        $buffer = '';
        
        // Receive message
        $result = socket_recvfrom($this->socket, $buffer, $this->buffer_size, 0, $remote_addr, $remote_port);
        
        if ($result === false) {
            echo "[ERROR] socket_recvfrom failed\n";
            return;
        }
        
        if (strlen($buffer) === 0) {
            return;
        }
        
        // Parse and store the syslog message
        $syslog_entry = $this->parse_syslog($buffer, $remote_addr, $remote_port);
        $this->store_syslog($syslog_entry);
        
        // Log to console
        $facility = $this->get_facility_name($syslog_entry['facility']);
        $severity = $this->get_severity_name($syslog_entry['severity']);
        printf(
            "[%s] %s | %s | %s | %s\n",
            date('Y-m-d H:i:s'),
            $remote_addr,
            $facility,
            $severity,
            substr($syslog_entry['message'], 0, 80)
        );
    }
    
    /**
     * Parse RFC 3164/RFC 5424 syslog message
     */
    private function parse_syslog($message, $remote_addr, $remote_port) {
        $timestamp = date('c');
        $facility = 16; // Default: local0
        $severity = 6;  // Default: informational
        $hostname = 'unknown';
        $tag = '';
        $content = $message;
        
        // Try to parse priority field: <PRI>
        if (preg_match('/^<(\d+)>(.*)$/', $message, $matches)) {
            $priority = intval($matches[1]);
            $facility = intval($priority / 8);
            $severity = $priority % 8;
            $message = $matches[2];
        }
        
        // Try to parse timestamp (RFC 3164: Mmm dd hh:mm:ss or RFC 5424: 2026-04-20T...)
        if (preg_match('/^(\w+\s+\d+\s+\d+:\d+:\d+|\d{4}-\d{2}-\d{2}T[\d:+-]+)\s+(.*)$/', $message, $matches)) {
            $ts_str = $matches[1];
            $message = $matches[2];
            
            // Try to parse as RFC 5424
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $ts_str)) {
                $dt = DateTime::createFromFormat(DateTime::ISO8601, $ts_str);
                if ($dt) {
                    $timestamp = $dt->format('c');
                }
            }
        }
        
        // Parse hostname
        if (preg_match('/^([\w\-\.]+)\s+(.*)$/', $message, $matches)) {
            $hostname = $matches[1];
            $message = $matches[2];
        }
        
        // Parse tag (program name)
        if (preg_match('/^([\w\-\.]+)(?:\[\d+\])?:\s*(.*)$/', $message, $matches)) {
            $tag = $matches[1];
            $content = $matches[2];
        } else {
            $content = $message;
        }
        
        return [
            'timestamp' => $timestamp,
            'received_at' => date('c'),
            'source_ip' => $remote_addr,
            'source_port' => $remote_port,
            'priority' => $priority ?? 134,
            'facility' => $facility,
            'severity' => $severity,
            'facility_name' => $this->get_facility_name($facility),
            'severity_name' => $this->get_severity_name($severity),
            'hostname' => $hostname,
            'tag' => $tag,
            'message' => $content,
            'raw_message' => $buffer
        ];
    }
    
    /**
     * Store syslog entry to file
     */
    private function store_syslog($entry) {
        // Load existing syslog entries
        $logs = [];
        if (file_exists($this->syslog_file)) {
            $data = @file_get_contents($this->syslog_file);
            $logs = json_decode($data, true) ?: [];
        }
        
        // Add new entry (keep last 10000 entries to avoid huge file)
        $logs[] = $entry;
        if (count($logs) > 10000) {
            $logs = array_slice($logs, -10000);
        }
        
        // Save back
        @file_put_contents($this->syslog_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    /**
     * Get facility name from facility code
     */
    private function get_facility_name($facility) {
        $facilities = [
            0 => 'kernel',
            1 => 'user',
            2 => 'mail',
            3 => 'daemon',
            4 => 'auth',
            5 => 'syslog',
            6 => 'lpr',
            7 => 'news',
            8 => 'uucp',
            9 => 'cron',
            16 => 'local0',
            17 => 'local1',
            18 => 'local2',
            19 => 'local3',
            20 => 'local4',
            21 => 'local5',
            22 => 'local6',
            23 => 'local7'
        ];
        return $facilities[$facility] ?? "local{$facility}";
    }
    
    /**
     * Get severity name from severity code
     */
    private function get_severity_name($severity) {
        $severities = [
            0 => 'Emergency',
            1 => 'Alert',
            2 => 'Critical',
            3 => 'Error',
            4 => 'Warning',
            5 => 'Notice',
            6 => 'Informational',
            7 => 'Debug'
        ];
        return $severities[$severity] ?? "Unknown({$severity})";
    }
}

// ==========================================
// MAIN EXECUTION
// ==========================================

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Check if running as root
if (posix_geteuid() !== 0) {
    die("[ERROR] This script must run as root to bind to port 514.\nRun with: sudo php app/services/SyslogListener.php\n");
}

// Create and start listener
$listener = new SyslogListener();
$listener->start();
?>
