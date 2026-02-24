<?php
// Archive Script - Archives old logs and events
// Run this every 6 hours via cron: 0 */6 * * * /usr/bin/php /opt/lampp/htdocs/SIEMproject/archive.php

// Define base path
define('BASE_PATH', __DIR__);

// Autoload classes
spl_autoload_register(function ($class) {
    $directories = [
        BASE_PATH . '/app/models/',
        BASE_PATH . '/app/controllers/',
        BASE_PATH . '/app/services/',
    ];
    
    foreach ($directories as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once 'app/config/config.php';

$config = require 'app/config/config.php';

$logsModel = new LogsModel($config);
$eventModel = new EventModel($config);

// Archive logs older than 24 hours (adjust as needed)
$logsModel->archiveOldLogs(24);

// Archive events older than 24 hours
$eventModel->archiveOldEvents(24);

echo "Archiving completed at " . date('Y-m-d H:i:s') . "\n";
?>