<?php
/**
 * Vlan Model - Aggregates VLAN data from events and raw logs
 */
class VlanModel {
    private $config;
    private $severityLevels = ['Critical', 'High', 'Medium', 'Low'];
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Determine if an IP is private (internal)
     */
    private function isPrivateIp($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Convert an IP to a VLAN key (CIDR /24)
     */
    private function getVlanKey($ip) {
        if (!$this->isPrivateIp($ip)) {
            return null;
        }
        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return null;
        }
        return sprintf('%s.%s.%s.0/24', $parts[0], $parts[1], $parts[2]);
    }
    
    private function createVlan($cidr) {
        return [
            'cidr' => $cidr,
            'name' => 'VLAN ' . $cidr,
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
            'last_updated' => null,
        ];
    }
    
    private function &ensureVlan(array &$vlans, $cidr) {
        if (!isset($vlans[$cidr])) {
            $vlans[$cidr] = $this->createVlan($cidr);
        }
        return $vlans[$cidr];
    }
    
    private function addEndpoint(array &$vlan, $ip, $label, $type, $timestamp) {
        if (empty($ip)) {
            return;
        }
        if (!isset($vlan['endpoints'][$ip])) {
            $vlan['endpoints'][$ip] = [
                'ip' => $ip,
                'label' => $label ?: 'Endpoint',
                'type' => $type ?: 'Host',
                'last_seen' => $timestamp ?: date('c'),
            ];
        } else {
            if ($timestamp && strtotime($timestamp) > strtotime($vlan['endpoints'][$ip]['last_seen'])) {
                $vlan['endpoints'][$ip]['last_seen'] = $timestamp;
            }
            if ($label && $vlan['endpoints'][$ip]['label'] === 'Endpoint') {
                $vlan['endpoints'][$ip]['label'] = $label;
            }
            if ($type && $vlan['endpoints'][$ip]['type'] === 'Host') {
                $vlan['endpoints'][$ip]['type'] = $type;
            }
        }
    }
    
    private function mapTrafficCategory($logType) {
        $type = strtolower($logType ?? 'other');
        if (strpos($type, 'firewall') !== false || strpos($type, 'iptables') !== false) {
            return 'firewall';
        }
        if (strpos($type, 'network') !== false || strpos($type, 'connection') !== false) {
            return 'network';
        }
        if (strpos($type, 'http') !== false || strpos($type, 'https') !== false || strpos($type, 'request') !== false) {
            return 'http';
        }
        return 'other';
    }
    
    private function extractIpFromTarget($targetDevice) {
        if (preg_match('/(\\d{1,3}(?:\\.\\d{1,3}){3})/', $targetDevice ?? '', $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Build VLAN overview data from events and raw logs
     */
    public function buildVlanData(array $events, array $rawLogs, array $homeLocation = []) {
        $vlans = [];
        
        // Seed with SIEM server if available
        if (!empty($homeLocation['private_ip']) && $this->isPrivateIp($homeLocation['private_ip'])) {
            $cidr = $this->getVlanKey($homeLocation['private_ip']);
            if ($cidr) {
                $vlan =& $this->ensureVlan($vlans, $cidr);
                $label = 'SIEM Server (' . ($homeLocation['private_ip'] ?? 'Host') . ')';
                $this->addEndpoint($vlan, $homeLocation['private_ip'], $label, 'Server', $homeLocation['last_update'] ?? date('c'));
            }
        }
        
        foreach ($events as $event) {
            $ip = $this->extractIpFromTarget($event['target_device'] ?? '') ?: ($event['ip'] ?? null);
            if (!$ip || !$this->isPrivateIp($ip)) {
                continue;
            }
            $cidr = $this->getVlanKey($ip);
            if (!$cidr) {
                continue;
            }
            $vlan =& $this->ensureVlan($vlans, $cidr);
            $severity = $event['severity'] ?? 'Low';
            if (!isset($vlan['severity'][$severity])) {
                $vlan['severity'][$severity] = 0;
            }
            $vlan['severity'][$severity]++;
            $vlan['last_updated'] = $event['timestamp'] ?? $vlan['last_updated'];
            
            $type = ($event['type'] ?? '') === 'EXTERNAL' ? 'External Source' : 'Endpoint';
            $this->addEndpoint($vlan, $ip, $event['target_device'] ?? 'Asset', $type, $event['timestamp'] ?? date('c'));
        }
        
        foreach ($rawLogs as $log) {
            foreach (['source_ip', 'destination_ip'] as $field) {
                $ip = $log[$field] ?? null;
                if (!$ip || !$this->isPrivateIp($ip)) {
                    continue;
                }
                $cidr = $this->getVlanKey($ip);
                if (!$cidr) {
                    continue;
                }
                $vlan =& $this->ensureVlan($vlans, $cidr);
                $typeLabel = $field === 'source_ip' ? 'Source Host' : 'Destination Host';
                $this->addEndpoint($vlan, $ip, $typeLabel, 'Host', $log['timestamp'] ?? $log['extracted_at'] ?? date('c'));
                
                $category = $this->mapTrafficCategory($log['log_type'] ?? '');
                if (!isset($vlan['traffic'][$category])) {
                    $vlan['traffic'][$category] = 0;
                }
                $vlan['traffic'][$category]++;
                $vlan['last_updated'] = $log['timestamp'] ?? $log['extracted_at'] ?? $vlan['last_updated'];
            }
        }
        
        // Finalize VLAN structures
        $totalEndpoints = 0;
        $totalThreats = 0;
        
        foreach ($vlans as $cidr => &$vlan) {
            $vlan['endpoints'] = array_values($vlan['endpoints']);
            usort($vlan['endpoints'], function ($a, $b) {
                return strtotime($b['last_seen']) <=> strtotime($a['last_seen']);
            });
            
            $critical = $vlan['severity']['Critical'] ?? 0;
            $high = $vlan['severity']['High'] ?? 0;
            $info = ($vlan['severity']['Medium'] ?? 0) + ($vlan['severity']['Low'] ?? 0);
            
            $vlan['threat_count'] = $critical + $high;
            $vlan['alert_bars'] = [
                ['type' => 'Critical Attacks', 'count' => $critical, 'color' => '#dc2626'],
                ['type' => 'Suspicious Activity', 'count' => $high, 'color' => '#f59e0b'],
                ['type' => 'Informationals', 'count' => $info, 'color' => '#22c55e'],
            ];
            
            $vlan['endpoint_count'] = count($vlan['endpoints']);
            $totalEndpoints += $vlan['endpoint_count'];
            $totalThreats += $vlan['threat_count'];
        }
        unset($vlan);
        
        if (empty($vlans)) {
            // Create a placeholder VLAN so the UI has something to render
            $placeholder = $this->createVlan('192.168.1.0/24');
            $placeholder['name'] = 'VLAN 1';
            $placeholder['last_updated'] = date('c');
            $vlans[$placeholder['cidr']] = $placeholder;
        }
        
        // Sort VLANs by threat count desc
        usort($vlans, function ($a, $b) {
            return $b['threat_count'] <=> $a['threat_count'];
        });
        
        $summary = [
            'total_vlans' => count($vlans),
            'total_endpoints' => $totalEndpoints,
            'total_threats' => $totalThreats,
        ];
        
        return [
            'vlans' => $vlans,
            'summary' => $summary
        ];
    }
}

