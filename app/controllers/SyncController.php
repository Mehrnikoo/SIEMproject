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
        // If this is an AJAX request, return JSON
        if ($this->isAjaxRequest()) {
            return $this->statusJson();
        }
        
        // Otherwise render the UI
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
     * Get sync status as JSON
     */
    private function statusJson() {
        header('Content-Type: application/json');
        
        require_once dirname(__DIR__) . '/services/SyncService.php';
        $sync = new SyncService($this->config);
        $status = $sync->getStatus();
        
        echo json_encode($status);
        exit;
    }
    
    /**
     * Trigger synchronization
     */
    public function sync() {
        header('Content-Type: application/json');
        
        require_once dirname(__DIR__) . '/services/SyncService.php';
        $sync = new SyncService($this->config);
        $sync->syncAll();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Synchronization completed',
            'data' => $sync->getStatus()
        ]);
        exit;
    }
    
    /**
     * Check if this is an AJAX request
     */
    private function isAjaxRequest() {
        return (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
               (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    }
}

