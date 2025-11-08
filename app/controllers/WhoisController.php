<?php
/**
 * Whois Controller - Handles IP whois lookup requests
 */
class WhoisController {
    private $geoModel;
    
    public function __construct($geoModel) {
        $this->geoModel = $geoModel;
    }
    
    /**
     * Handle whois lookup request
     */
    public function lookup($ip) {
        // Validate IP
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid IP address format.']);
            exit;
        }
        
        // Get whois information
        $whoisData = $this->geoModel->getWhoisInfo($ip);
        
        header('Content-Type: application/json');
        echo json_encode($whoisData);
        exit;
    }
}

