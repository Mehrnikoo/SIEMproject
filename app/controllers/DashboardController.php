<?php
/**
 * Dashboard Controller - Handles main dashboard view
 */
class DashboardController {
    private $eventModel;
    private $geoModel;
    private $serverStatusModel;
    private $config;
    
    public function __construct($eventModel, $geoModel, $serverStatusModel, $config) {
        $this->eventModel = $eventModel;
        $this->geoModel = $geoModel;
        $this->serverStatusModel = $serverStatusModel;
        $this->config = $config;
    }
    
    /**
     * Render dashboard view
     */
    public function index() {
        // Load limited event data for performance
        $real_events_raw = $this->eventModel->loadRealEvents(20);
        $sim_events_raw = $this->eventModel->loadSimulatedEvents(20);
        
        // Check for data errors
        $data_error = null;
        if (empty($real_events_raw) && empty($sim_events_raw)) {
            $data_error = "Could not load log data. Run 'python3 pythonSIEMscript.py' first.";
        }
        
        // Process and geocode events
        $real_event_data = $this->geoModel->processEvents($real_events_raw);
        $sim_event_data = $this->geoModel->processEvents($sim_events_raw);
        
        // Sort events by severity
        $real_event_data = $this->eventModel->sortEventsBySeverity($real_event_data);
        $sim_event_data = $this->eventModel->sortEventsBySeverity($sim_event_data);
        
        // Calculate summary statistics from loaded events
        $severity_counts = $this->eventModel->calculateSeverityCounts($real_event_data);
        $most_common_country = $this->eventModel->getMostCommonCountry($real_event_data);
        $total_all_events = count($real_event_data);
        
        // Get home location
        $home_location_data = $this->serverStatusModel->getHomeLocation($this->geoModel);
        
        // Prepare data for view
        $view_data = [
            'real_event_data' => $real_event_data,
            'sim_event_data' => $sim_event_data,
            'severity_map' => $this->config['severity_map'],
            'severity_counts' => $severity_counts,
            'most_common_country' => $most_common_country,
            'total_all_events' => $total_all_events,
            'home_location_data' => $home_location_data,
            'data_error' => $data_error
        ];
        
        // Render view
        $view = new View('dashboard', $view_data);
        $view->render();
    }
}

