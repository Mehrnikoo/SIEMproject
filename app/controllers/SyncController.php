<?php
/**
 * Sync Controller - Manages synchronization between Python script and website
 */
class SyncController {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Display sync status page
     */
    public function status() {
        require_once dirname(__DIR__) . '/services/SyncService.php';
        $sync = new SyncService($this->config);
        $status = $sync->getStatus();
        
        $view_data = [
            'status' => $status,
            'config' => $this->config
        ];
        
        $view = new View('sync-status', $view_data);
        $view->render();
    }
    
    /**
     * Trigger synchronization
     */
    public function sync() {
        require_once dirname(__DIR__) . '/services/SyncService.php';
        $sync = new SyncService($this->config);
        $sync->syncAll();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Synchronization completed',
            'data' => $sync->getStatus()
        ]);
    }
}
