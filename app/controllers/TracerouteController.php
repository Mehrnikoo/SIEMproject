<?php
/**
 * Traceroute Controller - Handles traceroute API requests
 */
class TracerouteController {
    private $tracerouteModel;
    private $geoModel;
    
    public function __construct($tracerouteModel, $geoModel) {
        $this->tracerouteModel = $tracerouteModel;
        $this->geoModel = $geoModel;
    }
    
    /**
     * Handle traceroute request
     */
    public function trace($ip) {
        // Validate IP
        if (!$this->tracerouteModel->validateIP($ip)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or private IP address.']);
            exit;
        }
        
        // Execute traceroute
        $output = $this->tracerouteModel->executeTraceroute($ip);
        
        if (empty($output)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Traceroute command failed or is not installed.']);
            exit;
        }
        
        // Extract hop IPs
        $hop_ips = $this->tracerouteModel->extractHopIPs($output);
        
        // Geocode hops
        $hops = $this->tracerouteModel->geocodeHops($hop_ips, $this->geoModel);
        
        header('Content-Type: application/json');
        echo json_encode(['hops' => $hops]);
        exit;
    }
}

