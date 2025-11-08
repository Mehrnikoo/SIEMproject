<?php
/**
 * GeoLocation Model - Handles IP geolocation
 */
class GeoLocationModel {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * Check if IP is external (public)
     */
    public function isExternalIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Geocode a single IP address
     */
    public function geocodeIP($ip, $fields = 'status,message,lat,lon,city,country,regionName,isp') {
        if (!$this->isExternalIP($ip)) {
            return [
                'lat' => null,
                'lon' => null,
                'city' => 'Internal Network',
                'country' => 'Local',
                'regionName' => '',
                'isp' => 'Internal'
            ];
        }
        
        $api_url = $this->config['geo_api'] . $ip . '?fields=' . $fields;
        $location_data = @file_get_contents($api_url);
        
        if ($location_data === false) {
            return [
                'lat' => null,
                'lon' => null,
                'city' => 'Unknown',
                'country' => 'Unknown',
                'regionName' => '',
                'isp' => 'Unknown'
            ];
        }
        
        $data = json_decode($location_data, true);
        
        if (isset($data['status']) && $data['status'] === 'success') {
            return [
                'lat' => $data['lat'] ?? 0,
                'lon' => $data['lon'] ?? 0,
                'city' => $data['city'] ?? 'Unknown',
                'country' => $data['country'] ?? 'Unknown',
                'regionName' => $data['regionName'] ?? '',
                'isp' => $data['isp'] ?? 'Unknown'
            ];
        }
        
        return [
            'lat' => null,
            'lon' => null,
            'city' => 'Unknown',
            'country' => 'Unknown',
            'regionName' => '',
            'isp' => 'Unknown'
        ];
    }
    
    /**
     * Get comprehensive whois information for an IP address
     * Returns detailed information including ASN, organization, timezone, etc.
     */
    public function getWhoisInfo($ip) {
        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['error' => 'Invalid IP address format.'];
        }
        
        // Check if it's a private IP
        if (!$this->isExternalIP($ip)) {
            return [
                'ip' => $ip,
                'status' => 'private',
                'type' => 'Internal/Private IP',
                'city' => 'Internal Network',
                'country' => 'Local',
                'regionName' => '',
                'isp' => 'Internal Network',
                'org' => 'Private Network',
                'as' => 'N/A',
                'asname' => 'N/A',
                'timezone' => 'N/A',
                'lat' => null,
                'lon' => null,
                'query' => $ip
            ];
        }
        
        // Request comprehensive fields from ip-api.com
        $fields = 'status,message,continent,continentCode,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,reverse,mobile,proxy,hosting,query';
        $api_url = $this->config['geo_api'] . $ip . '?fields=' . $fields;
        
        $location_data = @file_get_contents($api_url);
        
        if ($location_data === false) {
            return ['error' => 'Failed to fetch IP information. API may be unavailable.'];
        }
        
        $data = json_decode($location_data, true);
        
        if (isset($data['status']) && $data['status'] === 'fail') {
            return ['error' => $data['message'] ?? 'Failed to retrieve IP information.'];
        }
        
        if (isset($data['status']) && $data['status'] === 'success') {
            return [
                'ip' => $data['query'] ?? $ip,
                'status' => 'success',
                'continent' => $data['continent'] ?? 'Unknown',
                'continentCode' => $data['continentCode'] ?? '',
                'country' => $data['country'] ?? 'Unknown',
                'countryCode' => $data['countryCode'] ?? '',
                'region' => $data['region'] ?? '',
                'regionName' => $data['regionName'] ?? '',
                'city' => $data['city'] ?? 'Unknown',
                'district' => $data['district'] ?? '',
                'zip' => $data['zip'] ?? '',
                'lat' => $data['lat'] ?? 0,
                'lon' => $data['lon'] ?? 0,
                'timezone' => $data['timezone'] ?? 'Unknown',
                'offset' => $data['offset'] ?? 0,
                'currency' => $data['currency'] ?? '',
                'isp' => $data['isp'] ?? 'Unknown',
                'org' => $data['org'] ?? 'Unknown',
                'as' => $data['as'] ?? 'N/A',
                'asname' => $data['asname'] ?? 'N/A',
                'reverse' => $data['reverse'] ?? '',
                'mobile' => $data['mobile'] ?? false,
                'proxy' => $data['proxy'] ?? false,
                'hosting' => $data['hosting'] ?? false,
                'query' => $data['query'] ?? $ip
            ];
        }
        
        return ['error' => 'Unexpected response from API.'];
    }
    
    /**
     * Process events and add geolocation data
     */
    public function processEvents(array $events_in) {
        $out = [];
        
        foreach ($events_in as $event) {
            $ip = $event['source_ip'] ?? '';
            $event_severity = $event['severity'] ?? 'Low';
            $event_type = $this->isExternalIP($ip) ? 'EXTERNAL' : 'INTERNAL';
            
            $event_data = [
                'id' => $event['id'] ?? 0,
                'type' => $event_type,
                'ip' => $ip,
                'severity' => $event_severity,
                'target_device' => "Asset (" . ($event['target_ip'] ?? 'Unknown') . ")",
                'description' => $event['description'] ?? '',
                'raw_logs' => $event['raw_logs'] ?? [],
                'simulated' => $event['simulated'] ?? false,
                'simulated_hops' => $event['simulated_hops'] ?? []
            ];
            
            // Geocode external IPs only
            if ($event_type === 'EXTERNAL') {
                $geo = $this->geocodeIP($ip);
                $event_data['lat'] = $geo['lat'];
                $event_data['lon'] = $geo['lon'];
                $event_data['city'] = $geo['city'];
                $event_data['country'] = $geo['country'];
            } else {
                $event_data['lat'] = null;
                $event_data['lon'] = null;
                $event_data['city'] = 'Internal Network';
                $event_data['country'] = 'Local';
            }
            
            $out[] = $event_data;
        }
        
        return $out;
    }
}

