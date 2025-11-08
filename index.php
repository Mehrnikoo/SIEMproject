<?php
/**
 * Bootstrap and Router
 * Entry point for the application using MVC architecture
 */

// Define base path
define('BASE_PATH', __DIR__);

// Autoload classes
spl_autoload_register(function ($class) {
    $directories = [
        BASE_PATH . '/app/models/',
        BASE_PATH . '/app/controllers/',
        BASE_PATH . '/app/views/',
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load configuration
$config = require_once BASE_PATH . '/app/config/config.php';

// Initialize models
$eventModel = new EventModel($config);
$geoModel = new GeoLocationModel($config);
$serverStatusModel = new ServerStatusModel($config);
$tracerouteModel = new TracerouteModel();

// Initialize controllers
$dashboardController = new DashboardController($eventModel, $geoModel, $serverStatusModel, $config);
$tracerouteController = new TracerouteController($tracerouteModel, $geoModel);
$whoisController = new WhoisController($geoModel);

// Routing
if (isset($_GET['action']) && $_GET['action'] === 'trace' && isset($_GET['ip'])) {
    // Handle traceroute API endpoint
    $tracerouteController->trace($_GET['ip']);
} elseif (isset($_GET['action']) && $_GET['action'] === 'whois' && isset($_GET['ip'])) {
    // Handle whois lookup API endpoint
    $whoisController->lookup($_GET['ip']);
} else {
    // Default: show dashboard
    $dashboardController->index();
}
