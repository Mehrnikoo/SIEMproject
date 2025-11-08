<?php
/**
 * Event Model - Handles event data retrieval and processing
 */
class EventModel {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Load real events from log_data.json
     */
    public function loadRealEvents() {
        $file = $this->config['data_files']['log_data'];
        $data = @file_get_contents($file);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Load simulated events from sim_data.json
     */
    public function loadSimulatedEvents() {
        $file = $this->config['data_files']['sim_data'];
        $data = @file_get_contents($file);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Calculate severity counts from events
     */
    public function calculateSeverityCounts($events) {
        $severity_map = $this->config['severity_map'];
        $counts = array_fill_keys(array_keys($severity_map), 0);
        
        foreach ($events as $event) {
            $sev = $event['severity'] ?? 'Low';
            if (isset($counts[$sev])) {
                $counts[$sev]++;
            }
        }
        
        return $counts;
    }
    
    /**
     * Get most common country from external events
     */
    public function getMostCommonCountry($events) {
        $external_countries = [];
        
        foreach ($events as $event) {
            if (($event['type'] ?? '') === 'EXTERNAL' && !empty($event['country'])) {
                $external_countries[] = $event['country'];
            }
        }
        
        if (empty($external_countries)) {
            return 'None Detected';
        }
        
        $country_counts = array_count_values($external_countries);
        arsort($country_counts);
        return key($country_counts);
    }
    
    /**
     * Sort events by severity
     */
    public function sortEventsBySeverity($events) {
        $severity_order = array_flip(['Critical', 'High', 'Medium', 'Low']);
        
        usort($events, function($a, $b) use ($severity_order) {
            $sevA = $severity_order[$a['severity']] ?? 99;
            $sevB = $severity_order[$b['severity']] ?? 99;
            return $sevA <=> $sevB;
        });
        
        return $events;
    }
}

