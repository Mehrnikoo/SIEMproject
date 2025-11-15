<?php
/**
 * Traceroute Model - Handles traceroute operations
 */
class TracerouteModel {
    
    /**
     * Validate IP address (must be public) - SECURITY: Strict validation
     */
    public function validateIP($ip) {
        // SECURITY: Ensure input is a string and not empty
        if (!is_string($ip) || empty(trim($ip))) {
            return false;
        }
        
        // SECURITY: Remove any whitespace
        $ip = trim($ip);
        
        // SECURITY: Validate IP format strictly (must be public)
        $valid = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        
        // SECURITY: Additional check - ensure it's exactly an IP (no extra characters)
        if ($valid && $ip === filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Execute traceroute command - SECURITY: Protected against command injection
     */
    public function executeTraceroute($ip) {
        // SECURITY: Validate IP before using it in command
        if (!$this->validateIP($ip)) {
            return '';
        }
        
        // SECURITY: Use escapeshellarg to prevent command injection
        $safe_ip = escapeshellarg($ip);
        
        // SECURITY: Use absolute path and limit command options
        $command = "/usr/bin/traceroute -I -n -w 1 -q 1 -m 20 $safe_ip 2>&1";
        $output = @shell_exec($command);
        
        // Try Windows tracert if Linux traceroute fails
        if (empty($output)) {
            $command = "tracert -d -h 20 -w 1000 $safe_ip 2>&1";
            $output = @shell_exec($command);
        }
        
        return $output;
    }
    
    /**
     * Extract IP addresses from traceroute output
     */
    public function extractHopIPs($output) {
        $hop_ips = [];
        preg_match_all('/\(?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\)?/', $output, $matches);
        
        if (isset($matches[1])) {
            foreach ($matches[1] as $hop_ip) {
                if (filter_var($hop_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) && !in_array($hop_ip, $hop_ips)) {
                    $hop_ips[] = $hop_ip;
                }
            }
        }
        
        return $hop_ips;
    }
    
    /**
     * Geocode hop IPs
     */
    public function geocodeHops($hop_ips, $geoModel) {
        $hops = [];
        
        foreach ($hop_ips as $hop_ip) {
            $geo = $geoModel->geocodeIP($hop_ip, 'status,lat,lon,city,country');
            
            if ($geo['lat'] !== null && $geo['lon'] !== null) {
                $hops[] = [
                    'ip' => $hop_ip,
                    'lat' => $geo['lat'],
                    'lon' => $geo['lon'],
                    'city' => $geo['city'],
                    'country' => $geo['country']
                ];
            }
            
            usleep(150000); // 150ms delay to respect API rate limits
        }
        
        return $hops;
    }
}

