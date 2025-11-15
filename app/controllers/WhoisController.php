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
     * Handle whois lookup request - SECURITY: Input validation and sanitization
     */
    public function lookup($ip) {
        // SECURITY: Ensure input is a string
        if (!is_string($ip)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid input type.']);
            exit;
        }
        
        // SECURITY: Trim whitespace
        $ip = trim($ip);
        
        // SECURITY: Validate IP format strictly
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

