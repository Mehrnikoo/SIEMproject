<?php
/**
 * Server Status Model - Handles server status data
 */
class ServerStatusModel {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Get server status data
     */
    public function getServerStatus() {
        $file = $this->config['data_files']['server_status'];
        
        if (!file_exists($file)) {
            return ['private_ip' => 'N/A (Run log_processor.py)'];
        }
        
        $content = @file_get_contents($file);
        $data = json_decode($content, true);
        
        if ($data && isset($data['private_ip'])) {
            return $data;
        }
        
        return ['private_ip' => 'N/A (Run log_processor.py)'];
    }
    
    /**
     * Get home location data (server's public IP geolocation)
     */
    public function getHomeLocation($geoModel) {
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $target_ip = $client_ip;
        $home_error_message = null;
        
        // If localhost, try to get public IP
        if (in_array($client_ip, ['127.0.0.1', '::1'])) {
            $public_ip_service_url = $this->config['public_ip_service'];
            $target_ip = @file_get_contents($public_ip_service_url);
            
            if ($target_ip === false || filter_var($target_ip, FILTER_VALIDATE_IP) === false) {
                $target_ip = '8.8.8.8'; // Fallback
                $home_error_message = "Could not determine server's public IP. Showing default location.";
            }
        }
        
        $status_data = $this->getServerStatus();
        $private_ip = $status_data['private_ip'] ?? 'N/A (Run log_processor.py)';
        
        $geo = $geoModel->geocodeIP($target_ip);
        
        return [
            'ip' => $target_ip,
            'lat' => $geo['lat'] ?? 0,
            'lon' => $geo['lon'] ?? 0,
            'city' => $geo['city'] ?? 'Unknown',
            'country' => $geo['country'] ?? 'Unknown',
            'regionName' => $geo['regionName'] ?? '',
            'isp' => $geo['isp'] ?? 'Unknown',
            'private_ip' => $private_ip,
            'error_message' => $home_error_message
        ];
    }
}

