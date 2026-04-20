<?php
/**
 * VLAN Controller - Handles VLAN dashboard and API endpoints
 */
class VlanController {
    private $vlanModel;
    private $eventModel;
    private $geoModel;
    private $logsModel;
    private $serverStatusModel;
    private $networkDiscovery;
    
    public function __construct($vlanModel, $eventModel, $geoModel, $logsModel, $serverStatusModel, $networkDiscovery = null) {
        $this->vlanModel = $vlanModel;
        $this->eventModel = $eventModel;
        $this->geoModel = $geoModel;
        $this->logsModel = $logsModel;
        $this->serverStatusModel = $serverStatusModel;
        $this->networkDiscovery = $networkDiscovery;
    }
    
    /**
     * Collect data for VLAN view/state
     */
    private function buildDataset() {
        // Use real network discovery if available
        if ($this->networkDiscovery) {
            $discoveredVlan = $this->networkDiscovery->getDiscoveredVlan();
            if ($discoveredVlan) {
                $vlans = [$discoveredVlan];
                
                // Build summary stats
                $summary = [
                    'total_vlans' => 1,
                    'total_endpoints' => count($discoveredVlan['endpoints'] ?? []),
                    'total_threats' => 0,
                ];
                
                // Load security events to correlate threats with discovered devices
                // Count ONLY REAL events that are threats (Critical or High severity)
                $realRaw = $this->eventModel->loadRealEvents();
                $realEvents = $this->geoModel->processEvents($realRaw);
                
                // Count severity levels for alert bars
                $criticalCount = 0;
                $highCount = 0;
                $mediumCount = 0;
                $lowCount = 0;
                foreach ($realEvents as $event) {
                    $severity = $event['severity'] ?? 'Low';
                    if ($severity === 'Critical') $criticalCount++;
                    elseif ($severity === 'High') $highCount++;
                    elseif ($severity === 'Medium') $mediumCount++;
                    else $lowCount++;
                }
                
                // Threat count = all Critical and High severity events (real threats only)
                $totalThreatsCount = $criticalCount + $highCount;
                
                // Add alert_bars to VLAN for frontend display
                $vlans[0]['threat_count'] = $totalThreatsCount;
                $vlans[0]['alert_bars'] = [
                    ['type' => 'Critical Attacks', 'count' => $criticalCount, 'color' => '#dc2626'],
                    ['type' => 'Suspicious Activity', 'count' => $highCount, 'color' => '#f59e0b'],
                    ['type' => 'Informationals', 'count' => ($mediumCount + $lowCount), 'color' => '#22c55e'],
                ];
                
                // Update summary with real threat count
                $summary['total_threats'] = $totalThreatsCount;
                
                // Load network stats from Python script if available
                $network = [];
                $logsDir = rtrim(__DIR__, '/') . '/../../captured_logs';
                
                try {
                    $scanFile = $logsDir . '/network_scan.json';
                    $statFile = $logsDir . '/network_stats.json';
                    $actionsFile = $logsDir . '/containment_actions.json';
                    $executedFile = $logsDir . '/containment_executed.json';

                    if (file_exists($scanFile)) {
                        $network['scan'] = json_decode(file_get_contents($scanFile), true);
                    }
                    if (file_exists($statFile)) {
                        $network['stats'] = json_decode(file_get_contents($statFile), true);
                    }
                    $histFile = $logsDir . '/network_stats_history.json';
                    if (file_exists($histFile)) {
                        $network['stats_history'] = json_decode(file_get_contents($histFile), true);
                    }
                    if (file_exists($actionsFile)) {
                        $network['pending_actions'] = json_decode(file_get_contents($actionsFile), true);
                    }
                    if (file_exists($executedFile)) {
                        $network['executed_actions'] = json_decode(file_get_contents($executedFile), true);
                    }
                } catch (Exception $e) {
                    $network = [];
                }
                
                return [
                    'vlans' => $vlans,
                    'summary' => $summary,
                    'server' => $this->serverStatusModel->getServerStatus(),
                    'network' => $network
                ];
            }
        }
        
        // Fallback to old VLAN inference method if discovery not available
        $realRaw = $this->eventModel->loadRealEvents();
        $simRaw = $this->eventModel->loadSimulatedEvents();
        
        $realEvents = $this->geoModel->processEvents($realRaw);
        $simEvents = $this->geoModel->processEvents($simRaw);
        $mergedEvents = array_merge($realEvents, $simEvents);
        
        $rawLogs = $this->logsModel->loadRawLogs();
        $serverStatus = $this->serverStatusModel->getServerStatus();
        
        $data = $this->vlanModel->buildVlanData($mergedEvents, $rawLogs, $serverStatus);

        // Attempt to load network scan/stats produced by the Python agent
        $network = [];
        $logsDir = rtrim(__DIR__, '/') . '/../../captured_logs';

        try {
            $scanFile = $logsDir . '/network_scan.json';
            $statFile = $logsDir . '/network_stats.json';
            $actionsFile = $logsDir . '/containment_actions.json';
            $executedFile = $logsDir . '/containment_executed.json';

            if (file_exists($scanFile)) {
                $network['scan'] = json_decode(file_get_contents($scanFile), true);
            }
            if (file_exists($statFile)) {
                $network['stats'] = json_decode(file_get_contents($statFile), true);
            }
            $histFile = $logsDir . '/network_stats_history.json';
            if (file_exists($histFile)) {
                $network['stats_history'] = json_decode(file_get_contents($histFile), true);
            }
            if (file_exists($actionsFile)) {
                $network['pending_actions'] = json_decode(file_get_contents($actionsFile), true);
            }
            if (file_exists($executedFile)) {
                $network['executed_actions'] = json_decode(file_get_contents($executedFile), true);
            }
        } catch (Exception $e) {
            $network = [];
        }

        return [
            'vlans' => $data['vlans'],
            'summary' => $data['summary'],
            'server' => $serverStatus,
            'network' => $network
        ];
    }

    /**
     * Handle containment action requests from the web UI.
     * Expects JSON POST body: { ip: '1.2.3.4', command: 'block' }
     */
    public function containment() {
        // Accept JSON input
        header('Content-Type: application/json');
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
            exit;
        }

        $ip = $payload['ip'] ?? null;
        $command = $payload['command'] ?? 'block';
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid IP']);
            exit;
        }

        $logsDir = $this->vlanModel->config['python_logs_dir'] ?? realpath(__DIR__ . '/../../captured_logs');
        $actionsFile = rtrim($logsDir, '/') . '/containment_actions.json';

        $action = [
            'id' => 'web-' . time(),
            'ip' => $ip,
            'command' => $command,
            'requested_at' => date('Y-m-d H:i:s')
        ];

        // Append to actions file atomically
        $existing = [];
        if (file_exists($actionsFile)) {
            $existing = json_decode(file_get_contents($actionsFile), true) ?: [];
        }
        $existing[] = $action;
        file_put_contents($actionsFile, json_encode($existing, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'ok', 'message' => 'Containment action queued', 'action' => $action]);
        exit;
    }
    
    public function index() {
        $dataset = $this->buildDataset();
        
        $view = new View('vlan', [
            'vlan_data' => $dataset['vlans'],
            'summary' => $dataset['summary'],
            'server' => $dataset['server']
        ]);
        $view->render();
    }
    
    public function state() {
        $dataset = $this->buildDataset();
        header('Content-Type: application/json');
        echo json_encode($dataset);
        exit;
    }
}

