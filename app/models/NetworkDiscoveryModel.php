<?php
/**
 * Network Discovery Model - Real network scanning and device detection
 * Uses nmap to discover actual devices on the network
 */
class NetworkDiscoveryModel {
    private $config;
    private $cacheDir;
    private $cacheTTL = 300; // 5 minutes
    
    public function __construct($config) {
        $this->config = $config;
        $this->cacheDir = rtrim($config['base_path'], '/') . '/captured_logs';
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get local network information
     */
    public function getLocalNetworkInfo() {
        // Get primary IP and calculate network CIDR
        $output = [];
        $returnCode = 0;
        
        @exec("hostname -I 2>/dev/null", $output, $returnCode);
        if ($returnCode !== 0 || empty($output)) {
            return null;
        }
        
        $ips = explode(' ', trim($output[0]));
        // Filter out loopback and docker
        $localIps = array_filter($ips, function($ip) {
            return !preg_match('/^127\./', $ip) && !preg_match('/^172\.17\./', $ip);
        });
        
        if (empty($localIps)) {
            return null;
        }
        
        $primaryIp = reset($localIps);
        
        // Calculate CIDR /24 network
        $parts = explode('.', $primaryIp);
        if (count($parts) !== 4) {
            return null;
        }
        
        $networkCidr = sprintf('%s.%s.%s.0/24', $parts[0], $parts[1], $parts[2]);
        
        return [
            'local_ip' => $primaryIp,
            'network_cidr' => $networkCidr,
            'network_base' => sprintf('%s.%s.%s', $parts[0], $parts[1], $parts[2]),
        ];
    }
    
    /**
     * Scan the network for devices using nmap
     */
    public function scanNetwork($cidr = null) {
        $networkInfo = $this->getLocalNetworkInfo();
        if (!$networkInfo) {
            return ['devices' => [], 'error' => 'Could not determine local network'];
        }
        
        if (!$cidr) {
            $cidr = $networkInfo['network_cidr'];
        }
        
        // Check if nmap is available
        $output = [];
        @exec('which nmap', $output);
        if (empty($output)) {
            return [
                'devices' => [],
                'error' => 'nmap not installed. Install with: sudo apt-get install nmap'
            ];
        }
        
        // Run nmap scan (light scan for speed - no port scan)
        // -sn = ping scan (no port scan)
        // -T4 = aggressive timing
        $cmd = sprintf('timeout 30 nmap -sn %s 2>/dev/null', escapeshellarg($cidr));
        
        $output = [];
        $returnCode = 0;
        @exec($cmd, $output, $returnCode);
        
        $devices = [];
        
        foreach ($output as $line) {
            // Match lines like "Nmap scan report for 192.168.119.107"
            if (preg_match('/Nmap scan report for (\S+)/', $line, $matches)) {
                $ip = $matches[1];
                
                // Skip localhost and docker
                if (preg_match('/^127\./', $ip) || preg_match('/^172\.17\./', $ip)) {
                    continue;
                }
                
                $device = [
                    'ip' => $ip,
                    'hostname' => $this->resolveHostname($ip),
                    'mac' => null,
                    'status' => 'online',
                    'last_seen' => date('c'),
                    'services' => [],
                ];
                
                $devices[] = $device;
            }
        }
        
        return [
            'devices' => $devices,
            'network' => $networkInfo['network_cidr'],
            'timestamp' => date('c'),
            'scan_type' => 'ping_scan',
        ];
    }
    
    /**
     * Resolve hostname from IP
     */
    private function resolveHostname($ip) {
        $output = [];
        $returnCode = 0;
        
        @exec(sprintf('timeout 2 nslookup %s 127.0.0.1 2>/dev/null | grep "name =" | awk -F"=" "{print $2}"', escapeshellarg($ip)), $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $hostname = trim($output[0]);
            $hostname = rtrim($hostname, '.');
            return !empty($hostname) ? $hostname : $ip;
        }
        
        return $ip;
    }
    
    /**
     * Detect services on a device using nmap
     */
    public function detectServices($ip) {
        $output = [];
        $returnCode = 0;
        
        // Quick port scan for common ports
        $ports = '22,80,443,3306,5432,8080,8443';
        $cmd = sprintf('timeout 15 nmap -p %s %s 2>/dev/null | grep -E "^[0-9]+/"', $ports, escapeshellarg($ip));
        
        @exec($cmd, $output, $returnCode);
        
        $services = [];
        
        foreach ($output as $line) {
            // Match lines like "22/tcp   open   ssh"
            if (preg_match('/^(\d+)\/tcp\s+(\w+)\s+(\w+)/', $line, $matches)) {
                $port = $matches[1];
                $state = $matches[2];
                $service = $matches[3];
                
                if ($state === 'open') {
                    $services[] = [
                        'port' => intval($port),
                        'service' => $service,
                        'state' => $state,
                    ];
                }
            }
        }
        
        return $services;
    }
    
    /**
     * Get cached scan or perform new scan
     */
    public function getDevices($useCache = true) {
        // Keep VLAN data aligned with Python SIEM script output when available.
        $pythonScan = $this->loadPythonNetworkScan();
        if ($pythonScan !== null) {
            return $pythonScan;
        }

        $cacheFile = $this->cacheDir . '/network_devices.json';
        
        // Check cache validity
        if ($useCache && file_exists($cacheFile)) {
            $modTime = filemtime($cacheFile);
            if ((time() - $modTime) < $this->cacheTTL) {
                $cached = json_decode(file_get_contents($cacheFile), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }
        
        // Perform fresh scan
        $scanResult = $this->scanNetwork();
        
        // Cache the result
        @file_put_contents($cacheFile, json_encode($scanResult, JSON_PRETTY_PRINT));
        
        return $scanResult;
    }

    /**
     * Load and normalize scan data generated by pythonSIEMscript.py
     */
    private function loadPythonNetworkScan() {
        $scanFile = $this->cacheDir . '/network_scan.json';
        if (!file_exists($scanFile)) {
            return null;
        }

        $raw = json_decode(@file_get_contents($scanFile), true);
        if (!is_array($raw)) {
            return null;
        }

        $deviceIps = $raw['devices'] ?? [];
        if (!is_array($deviceIps)) {
            $deviceIps = [];
        }

        $devices = [];
        foreach ($deviceIps as $ip) {
            if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $devices[] = [
                'ip' => $ip,
                'hostname' => $this->resolveHostname($ip),
                'mac' => null,
                'status' => 'online',
                'last_seen' => date('c'),
                'services' => [],
            ];
        }

        $network = 'Network';
        if (!empty($raw['private_ip']) && filter_var($raw['private_ip'], FILTER_VALIDATE_IP)) {
            $parts = explode('.', $raw['private_ip']);
            if (count($parts) === 4) {
                $network = sprintf('%s.%s.%s.0/24', $parts[0], $parts[1], $parts[2]);
            }
        }

        return [
            'devices' => $devices,
            'network' => $network,
            'timestamp' => $raw['scanned_at'] ?? date('c'),
            'scan_type' => 'python_script',
        ];
    }
    
    /**
     * Get device details with services
     */
    public function getDeviceDetails($ip) {
        $devices = $this->getDevices(true);
        
        $device = null;
        foreach ($devices['devices'] as $d) {
            if ($d['ip'] === $ip) {
                $device = $d;
                break;
            }
        }
        
        if (!$device) {
            return null;
        }
        
        // Get services
        $device['services'] = $this->detectServices($ip);
        
        return $device;
    }
    
    /**
     * Convert discovered devices to VLAN-compatible format
     * Returns a single "Network" VLAN containing all discovered devices
     */
    public function getDiscoveredVlan() {
        $scanData = $this->getDevices(true);
        
        if (isset($scanData['error']) && !isset($scanData['devices'])) {
            return null;
        }
        
        $devices = $scanData['devices'] ?? [];
        
        $networkInfo = $this->getLocalNetworkInfo();
        $networkCidr = $networkInfo ? $networkInfo['network_cidr'] : 'Network';
        
        // Build VLAN-compatible structure
        $vlan = [
            'cidr' => $networkCidr,
            'name' => 'Network: ' . $networkCidr,
            'endpoints' => [],
            'severity' => [
                'Critical' => 0,
                'High' => 0,
                'Medium' => 0,
                'Low' => 0,
            ],
            'traffic' => [
                'firewall' => 0,
                'network' => 0,
                'http' => 0,
                'other' => 0,
            ],
            'threat_count' => 0,
            'alert_bars' => [],
            'last_updated' => date('c'),
        ];
        
        // Convert devices to endpoints
        foreach ($devices as $device) {
            $endpoint = [
                'ip' => $device['ip'],
                'label' => $device['hostname'] ?? $device['ip'],
                'type' => $this->classifyDevice($device),
                'last_seen' => $device['last_seen'],
                'status' => $device['status'],
            ];
            
            $vlan['endpoints'][] = $endpoint;
        }
        
        $vlan['endpoint_count'] = count($vlan['endpoints']);
        
        return $vlan;
    }
    
    /**
     * Classify device type based on hostname or services
     */
    private function classifyDevice($device) {
        $hostname = strtolower($device['hostname'] ?? $device['ip']);
        
        // Classify by hostname patterns
        if (strpos($hostname, 'router') !== false || strpos($hostname, 'gateway') !== false) {
            return 'Router/Gateway';
        }
        if (strpos($hostname, 'server') !== false || strpos($hostname, 'ns') === 0) {
            return 'Server';
        }
        if (strpos($hostname, 'switch') !== false) {
            return 'Switch';
        }
        if (strpos($hostname, 'printer') !== false) {
            return 'Printer';
        }
        if (strpos($hostname, 'phone') !== false || strpos($hostname, 'mobile') !== false) {
            return 'Mobile Device';
        }
        
        // Default classification
        return 'Workstation';
    }
}
?>
