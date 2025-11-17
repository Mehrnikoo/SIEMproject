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
        
        return [
            'vlans' => $data['vlans'],
            'summary' => $data['summary'],
            'server' => $serverStatus
        ];
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

