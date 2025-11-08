<?php
/**
 * Application Configuration
 */
return [
    'severity_map' => [
        'Critical' => '#ef4444', 
        'High'     => '#f97316', 
        'Medium'   => '#fbbf24', 
        'Low'      => '#22c55e', 
    ],
    'log_processor_url' => 'http://10.50.2.100/api/security-events',
    'data_files' => [
        'log_data' => __DIR__ . '/../../log_data.json',
        'sim_data' => __DIR__ . '/../../sim_data.json',
        'server_status' => __DIR__ . '/../../server_status.json',
    ],
    'geo_api' => 'http://ip-api.com/json/',
    'public_ip_service' => 'https://api.ipify.org',
];

