<?php
/**
 * Bootstrap and Router
 * Entry point for the application using MVC architecture
 */

// SECURITY: Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

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
$logsModel = new LogsModel($config);
$vlanModel = new VlanModel($config);

// Initialize controllers
$dashboardController = new DashboardController($eventModel, $geoModel, $serverStatusModel, $config);
$tracerouteController = new TracerouteController($tracerouteModel, $geoModel);
$whoisController = new WhoisController($geoModel);
$eventsController = new EventsController($eventModel);
$logsController = new LogsController($logsModel, $eventModel, $geoModel, $config);
$vlanController = new VlanController($vlanModel, $eventModel, $geoModel, $logsModel, $serverStatusModel);
$syncController = new SyncController($config);

// Routing - SECURITY: Sanitize and validate inputs
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';

// SECURITY: Validate action parameter (whitelist approach)
if ($action === 'trace' && !empty($ip)) {
    // Handle traceroute API endpoint
    $tracerouteController->trace($ip);
} elseif ($action === 'whois' && !empty($ip)) {
    // Handle whois lookup API endpoint
    $whoisController->lookup($ip);
} elseif ($action === 'real_events_summary') {
    // Lightweight summary for polling new real events
    $eventsController->realSummary();
} elseif ($action === 'logs') {
    // Handle logs viewer page
    $logsController->index();
} elseif ($action === 'vlan') {
    // VLAN dashboard
    $vlanController->index();
} elseif ($action === 'vlan_state') {
    // JSON state for polling
    $vlanController->state();
} elseif ($action === 'containment') {
    // Web UI requests containment actions (POST JSON)
    $vlanController->containment();
} elseif ($action === 'sync_status') {
    // Show sync status
    $syncController->status();
} elseif ($action === 'sync') {
    // Trigger synchronization
    $syncController->sync();
} else {
    // Default: show dashboard
    $dashboardController->index();
}
