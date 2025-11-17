<?php
/**
 * Logs Controller - Handles logs viewer page
 */
class LogsController {
    private $logsModel;
    private $eventModel;
    private $geoModel;
    private $severityMap;
    
    public function __construct($logsModel, $eventModel, $geoModel, $config) {
        $this->logsModel = $logsModel;
        $this->eventModel = $eventModel;
        $this->geoModel = $geoModel;
        $this->severityMap = $config['severity_map'] ?? [];
    }
    
    /**
     * Load and process real/simulated events for the logs view
     */
    private function loadSecurityEvents() {
        $realRaw = $this->eventModel->loadRealEvents();
        $simRaw = $this->eventModel->loadSimulatedEvents();
        
        $realEvents = $this->geoModel->processEvents($realRaw);
        $simEvents = $this->geoModel->processEvents($simRaw);
        
        // Sort newest first by timestamp/id
        $sortFn = function ($a, $b) {
            $timeA = isset($a['timestamp']) ? strtotime($a['timestamp']) : 0;
            $timeB = isset($b['timestamp']) ? strtotime($b['timestamp']) : 0;
            if ($timeA === $timeB) {
                return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
            }
            return $timeB <=> $timeA;
        };
        
        usort($realEvents, $sortFn);
        usort($simEvents, $sortFn);
        
        return [
            'real' => array_slice($realEvents, 0, 150),
            'simulated' => array_slice($simEvents, 0, 150)
        ];
    }
    
    /**
     * Render logs viewer page
     */
    public function index() {
        // Get filter parameters
        $typeFilter = isset($_GET['type']) ? trim($_GET['type']) : 'All';
        $searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        
        // Load all logs
        $allLogs = $this->logsModel->loadRawLogs();
        
        // Apply filters
        $filteredLogs = $this->logsModel->filterLogsByType($allLogs, $typeFilter);
        $filteredLogs = $this->logsModel->filterLogsBySearch($filteredLogs, $searchFilter);
        
        // Re-index array after filtering
        $filteredLogs = array_values($filteredLogs);
        
        // Paginate
        $pagination = $this->logsModel->paginateLogs($filteredLogs, $page, 50);
        
        // Get available log types
        $logTypes = $this->logsModel->getLogTypes($allLogs);
        sort($logTypes);
        
        // Load security events
        $events = $this->loadSecurityEvents();
        
        // Prepare view data
        $view_data = [
            'logs' => $pagination['logs'],
            'current_page' => $pagination['current_page'],
            'total_pages' => $pagination['total_pages'],
            'total_logs' => $pagination['total_logs'],
            'per_page' => $pagination['per_page'],
            'log_types' => $logTypes,
            'current_type_filter' => $typeFilter,
            'current_search_filter' => $searchFilter,
            'real_events' => $events['real'],
            'simulated_events' => $events['simulated'],
            'severity_map' => $this->severityMap,
            'real_event_count' => count($events['real']),
            'sim_event_count' => count($events['simulated'])
        ];
        
        // Render view
        $view = new View('logs', $view_data);
        $view->render();
    }
}

