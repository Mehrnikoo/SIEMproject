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
    
    public function __construct($vlanModel, $eventModel, $geoModel, $logsModel, $serverStatusModel) {
        $this->vlanModel = $vlanModel;
        $this->eventModel = $eventModel;
        $this->geoModel = $geoModel;
        $this->logsModel = $logsModel;
        $this->serverStatusModel = $serverStatusModel;
    }
    
    /**
     * Collect data for VLAN view/state
     */
    private function buildDataset() {
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
        // Use captured_logs directory inside project as the authoritative location
        $logsDir = realpath(__DIR__ . '/../../captured_logs');
        if (!$logsDir) {
            $logsDir = __DIR__ . '/../../captured_logs';
        }

        try {
            $scanFile = rtrim($logsDir, '/') . '/network_scan.json';
            $statFile = rtrim($logsDir, '/') . '/network_stats.json';
            $actionsFile = rtrim($logsDir, '/') . '/containment_actions.json';
            $executedFile = rtrim($logsDir, '/') . '/containment_executed.json';

            if (file_exists($scanFile)) {
                $network['scan'] = json_decode(file_get_contents($scanFile), true);
            }
            if (file_exists($statFile)) {
                $network['stats'] = json_decode(file_get_contents($statFile), true);
            }
            $histFile = rtrim($logsDir, '/') . '/network_stats_history.json';
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

