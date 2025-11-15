<?php
/**
 * Logs Controller - Handles logs viewer page
 */
class LogsController {
    private $logsModel;
    
    public function __construct($logsModel) {
        $this->logsModel = $logsModel;
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
        
        // Prepare view data
        $view_data = [
            'logs' => $pagination['logs'],
            'current_page' => $pagination['current_page'],
            'total_pages' => $pagination['total_pages'],
            'total_logs' => $pagination['total_logs'],
            'per_page' => $pagination['per_page'],
            'log_types' => $logTypes,
            'current_type_filter' => $typeFilter,
            'current_search_filter' => $searchFilter
        ];
        
        // Render view
        $view = new View('logs', $view_data);
        $view->render();
    }
}

