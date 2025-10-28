<?php
// --- TRACEROUTE API ENDPOINT ---
if (isset($_GET['action']) && $_GET['action'] === 'trace' && isset($_GET['ip'])) {
    
    $ip_to_trace = $_GET['ip'];
    $hops = [];

    // --- SECURITY: Validate the IP and sanitize for shell command ---
    if (!filter_var($ip_to_trace, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid or private IP address.']);
        exit;
    }
    
    $safe_ip = escapeshellarg($ip_to_trace);
    $command = "traceroute -I -n -w 1 -q 1 -m 20 $safe_ip 2>&1";
    $output = @shell_exec($command);

    if (empty($output)) {
        $command = "tracert -d -h 20 -w 1000 $safe_ip 2>&1";
        $output = @shell_exec($command);
    }
    
    if (empty($output)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Traceroute command failed or is not installed.']);
        exit;
    }

    $lines = explode("\n", $output);
    $hop_ips = [];

    preg_match_all('/\(?([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\)?/', $output, $matches);
    
    if (isset($matches[1])) {
        foreach ($matches[1] as $hop_ip) {
            if (filter_var($hop_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) && !in_array($hop_ip, $hop_ips)) {
                $hop_ips[] = $hop_ip;
            }
        }
    }

    // Now, geolocate each hop IP
    foreach ($hop_ips as $hop_ip) {
        $api_url = "http://ip-api.com/json/" . $hop_ip . '?fields=status,lat,lon,city,country';
        $location_data_raw = @file_get_contents($api_url);
        
        if ($location_data_raw) {
            $data = json_decode($location_data_raw, true);
            if ($data && $data['status'] === 'success') {
                $hops[] = [
                    'ip' => $hop_ip,
                    'lat' => $data['lat'],
                    'lon' => $data['lon'],
                    'city' => $data['city'],
                    'country' => $data['country']
                ];
            }
        }
        usleep(150000); // 150ms
    }

    header('Content-Type: application/json');
    echo json_encode(['hops' => $hops]);
    exit;
}
// --- END OF TRACEROUTE API ENDPOINT ---


// --- Regular page load continues below ---
// PHP Backend Logic: Consolidated SIEM Analysis and Geolocation

// --- CONFIGURATION ---
$severity_map = [
    'Critical' => '#ef4444', 
    'High'     => '#f97316', 
    'Medium'   => '#fbbf24', 
    'Low'      => '#22c55e', 
];
$LOG_PROCESSOR_URL = 'http://10.50.2.100/api/security-events';
$data_error = null;

// --- 1. Data Retrieval: Reads data from log_processor.py output files ---
// Load REAL events from log_data.json
$log_data_raw = @file_get_contents('log_data.json');
$real_events_raw = json_decode($log_data_raw, true) ?: [];

// Load SIMULATED events from sim_data.json
$sim_data_raw = @file_get_contents('sim_data.json');
$sim_events_raw = json_decode($sim_data_raw, true) ?: [];

if (empty($real_events_raw) && empty($sim_events_raw)) {
    $data_error = "Could not load log data. Run 'python3 log_processor.py' first.";
}


// --- 2. Helper: Geocode an events array ---
function process_events(array $events_in) {
    $out = [];
    foreach ($events_in as $event) {
        $ip = $event['source_ip'] ?? '';
        $event_severity = $event['severity'] ?? 'Low';
        $event_type = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? 'EXTERNAL' : 'INTERNAL';
        $event_data = [
            'id' => $event['id'] ?? 0,
            'type' => $event_type,
            'ip' => $ip,
            'severity' => $event_severity,
            'target_device' => "Asset (" . ($event['target_ip'] ?? 'Unknown') . ")",
            'description' => $event['description'] ?? '',
            'raw_logs' => $event['raw_logs'] ?? [],
            'simulated' => $event['simulated'] ?? false,
            // Pass simulated hops data to frontend
            'simulated_hops' => $event['simulated_hops'] ?? []
        ];
        $event_data['lat'] = null;
        $event_data['lon'] = null;
        $event_data['city'] = 'Internal Network';
        $event_data['country'] = 'Local';
        if ($event_type === 'EXTERNAL') {
            // Geocode external IPs only
            $api_url = "http://ip-api.com/json/" . $ip . '?fields=status,message,lat,lon,city,country,regionName,isp';
            $location_data = @file_get_contents($api_url);
            if ($location_data !== false) {
                $data = json_decode($location_data, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    $event_data['lat'] = $data['lat'] ?? 0;
                    $event_data['lon'] = $data['lon'] ?? 0;
                    $event_data['city'] = $data['city'] ?? 'Unknown';
                    $event_data['country'] = $data['country'] ?? 'Unknown';
                }
            }
        }
        $out[] = $event_data;
    }
    return $out;
}

// --- 3. Data Processing and Geolocation ---
$real_event_data = process_events($real_events_raw);
$sim_event_data = process_events($sim_events_raw);


// --- 4. Summary Calculations (using real data only) ---
$severity_counts = array_fill_keys(array_keys($severity_map), 0);
$external_countries = [];

foreach ($real_event_data as $e) {
    $sev = $e['severity'] ?? 'Low';
    if (isset($severity_counts[$sev])) $severity_counts[$sev]++;
    if ($e['type'] === 'EXTERNAL' && !empty($e['country'])) $external_countries[] = $e['country'];
}

$country_counts = array_count_values($external_countries);
arsort($country_counts);
$most_common_country = key($country_counts) ?? 'None Detected';

// Sort real events by severity (Critical first)
$severity_order = array_flip(['Critical', 'High', 'Medium', 'Low']);
usort($real_event_data, function($a, $b) use ($severity_order) {
    $sevA = $severity_order[$a['severity']] ?? 99;
    $sevB = $severity_order[$b['severity']] ?? 99;
    return $sevA <=> $sevB;
});
// Also sort simulated events
usort($sim_event_data, function($a, $b) use ($severity_order) {
    $sevA = $severity_order[$a['severity']] ?? 99;
    $sevB = $severity_order[$b['severity']] ?? 99;
    return $sevA <=> $sevB;
});


// --- 5. Detect and Geocode the Home Network's Public IP ---
$client_ip = $_SERVER['REMOTE_ADDR'];
$target_ip = $client_ip;
$home_error_message = null;

if (in_array($client_ip, ['127.0.0.1', '::1'])) {
    $public_ip_service_url = 'https://api.ipify.org';
    $target_ip = @file_get_contents($public_ip_service_url);

    if ($target_ip === false || filter_var($target_ip, FILTER_VALIDATE_IP) === false) {
        $target_ip = '8.8.8.8'; // Fallback
        $home_error_message = "Could not determine server's public IP. Showing default location.";
    }
}

// --- RELIABLE PRIVATE IP DETECTION (Reads from server_status.json) ---
$status_file = 'server_status.json';
$private_ip = 'N/A (Run log_processor.py)'; 

if (file_exists($status_file)) {
    $status_content = @file_get_contents($status_file);
    $status_data = json_decode($status_content, true);
    
    if ($status_data && isset($status_data['private_ip'])) {
        $private_ip = $status_data['private_ip'];
    }
}

$api_url_home = "http://ip-api.com/json/" . $target_ip . '?fields=status,message,lat,lon,city,country,regionName,isp';
$home_location_data = ['ip' => $target_ip, 'lat' => 0, 'lon' => 0, 'city' => 'Unknown', 'country' => 'Unknown', 'private_ip' => $private_ip]; 

$home_data_raw = @file_get_contents($api_url_home);

if ($home_data_raw !== false) {
    $data = json_decode($home_data_raw, true);
    if (isset($data['status']) && $data['status'] === 'success') {
        $home_location_data = [
            'ip' => $target_ip,
            'lat' => $data['lat'] ?? 0,
            'lon' => $data['lon'] ?? 0,
            'city' => $data['city'] ?? 'Unknown',
            'country' => $data['country'] ?? 'Unknown',
            'regionName' => $data['regionName'] ?? '',
            'isp' => $data['isp'] ?? 'Unknown',
            'private_ip' => $private_ip,
        ];
    }
}


// --- Variables for HTML/JS injection ---
$event_data_json = json_encode($real_event_data);
$sim_event_data_json = json_encode($sim_event_data);
$home_location_json = json_encode($home_location_data);
$severity_map_json = json_encode($severity_map);
$total_all_events = count($real_event_data); // Summary bar always shows REAL events
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web SIEM Geolocation Dashboard</title>
    
    <!-- --- NEW: LCP Optimization --- -->
    <!-- Tells the browser to resolve the map tile domain's IP address ASAP -->
    <link rel="dns-prefetch" href="https://tiles.stadiamaps.com">
    
    <!-- Load Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    
    <!-- Load Leaflet Map CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    
    <style>
        /* General Styles */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #1e293b; 
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            color: #f8fafc; 
            padding: 20px;
        }

        /* Container & Layout */
        .container {
            background-color: #0f172a; 
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            padding: 30px;
            width: 95%;
            max-width: 1400px;
            display: flex;
            flex-direction: column;
        }
        
        /* Header */
        h1 {
            color: #38bdf8; 
            margin-bottom: 10px;
            font-weight: 800;
            text-align: center;
        }

        /* Summary Bar */
        .summary-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            background-color: #1e293b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #334155;
        }
        
        .summary-block {
            padding: 5px 15px;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .home-block {
            background-color: #0c4a6e; 
            border-left: 4px solid #38bdf8; 
        }

        .attack-block {
            background-color: #7f1d1d; 
            border-left: 4px solid #ef4444; 
        }

        .severity-counts {
            display: flex;
            gap: 10px;
            margin-top: 5px;
            font-size: 0.85rem;
            color: #e2e8f0;
            flex-wrap: wrap; 
        }

        .severity-counts span {
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .summary-block strong {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 3px;
        }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr; 
            gap: 20px;
        }

        /* Map Styling */
        #map {
            position: relative;
            height: 600px; 
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            background-color: #0a2340; /* Deep Navy/Dark Blue for 'Ocean' */
        }
        
        #attackCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 500; /* On top of tiles, below popups */
            pointer-events: none; /* Allows clicks to go through to the map */
        }
        
        #map .leaflet-tile-container {
            filter: hue-rotate(-30deg) saturate(1.1) brightness(0.9);
        }
        
        /* Map Controls (Tabs) */
        .map-controls {
            display:flex; 
            gap:8px; 
            margin-bottom:10px;
        }
        .map-controls .tab {
            padding:8px 12px; 
            border-radius:8px; 
            background:#1f2937; /* Inactive tab */
            color:#cbd5e1; 
            border: none; 
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            transition: all 0.2s;
        }
        .map-controls .tab:hover {
             background: #334155;
        }
        .map-controls .tab.active {
            background:#063a5a; /* Active tab */
            color:#fff;
        }
        
        /* Right panel container for swapping */
        #right-panel-column {
            background-color: #1e293b;
            border-radius: 12px;
            padding: 15px;
            max-height: 648px; /* Match map + controls height */
            border: 1px solid #334155;
            display: flex;
            flex-direction: column;
        }

        /* Event List Styling (Panel 1) */
        .event-list-container {
            display: flex; /* Set to 'flex' or 'none' by JS */
            flex-direction: column;
            overflow: hidden;
            flex-grow: 1;
        }
        
        #eventList {
            margin-top:12px;
            flex-grow: 1;
            overflow-y: auto;
        }
        
        .event-item {
            background-color: #334155; 
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            line-height: 1.4;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer; 
            transition: background-color 0.2s;
        }
        .event-item:hover {
            background-color: #475569;
        }


        .event-item .details {
            flex-grow: 1;
        }

        .event-item .severity-tag {
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.75rem;
            color: #f8fafc;
            flex-shrink: 0;
            margin-left: 10px;
        }
        
        .event-item strong {
            color: #38bdf8;
            font-weight: 600;
        }

        .event-item .location {
            display: block;
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 2px;
        }
        
        .event-item .target {
             color: #fbbf24; 
             font-weight: 500;
        }
        
        /* Details Panel Styling (Panel 2) */
        .details-panel-container {
            display: none; /* Set to 'flex' or 'none' by JS */
            flex-direction: column;
            overflow: hidden;
            flex-grow: 1;
            height: 100%;
        }

        .details-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
            padding-bottom: 10px;
        }
        
        .details-panel-header h2 {
            color: #cbd5e1;
            margin: 0;
            font-size: 1.25rem;
        }

        .back-button {
            background: #334155;
            color: #e2e8f0;
            border: none;
            padding: 5px 10px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .back-button:hover { background: #475569; }
        
        .details-panel-content {
            margin-top: 15px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .details-section {
            margin-bottom: 15px;
        }

        .details-section h4 {
            color: #38bdf8;
            margin-top: 0;
            margin-bottom: 8px;
            border-bottom: 1px solid #334155;
            padding-bottom: 5px;
        }

        .raw-log-output {
            background-color: #0f172a;
            padding: 10px;
            border-radius: 6px;
            max-height: 200px;
            overflow-y: scroll;
            font-family: monospace;
            font-size: 0.85rem;
            white-space: pre-wrap;
            border: 1px solid #334155;
        }

        .trace-button {
            background-color: #0e7490;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            transition: background-color 0.2s;
        }
        .trace-button:hover { background-color: #155e75; }
        .trace-button:disabled { background-color: #475569; cursor: not-allowed; opacity: 0.7; }

        #trace-status {
            font-style: italic;
            color: #94a3b8;
            margin-left: 10px;
        }

        #trace-hop-list {
            font-family: monospace;
            font-size: 0.85rem;
            color: #e2e8f0;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            padding: 10px;
            margin-top: 10px;
            max-height: 250px;
            overflow-y: auto;
        }
        
        #trace-hop-list div {
            padding: 2px 0;
            border-bottom: 1px dashed #334155;
        }
        #trace-hop-list div:last-child { border-bottom: none; }
        #trace-hop-list .hop-ip { color: #fbbf24; }
        #trace-hop-list .hop-location { color: #94a3b8; }


        /* Mobile adjustments */
        @media (max-width: 900px) {
            .content-grid {
                grid-template-columns: 1fr; 
            }
            #map {
                height: 400px;
            }
            #right-panel-column {
                max-height: 400px;
            }
            .severity-counts {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Web-Based SIEM Geolocation Dashboard</h1>

        <div class="summary-bar">
            <!-- Monitored Network Status (Home Location) -->
            <div class="summary-block home-block">
                <strong>Monitored Network Endpoints</strong>
                <span style="display: block;">Private IP: <?php echo htmlspecialchars($home_location_data['private_ip']); ?> (Server)</span>

                Public IP: <?php echo htmlspecialchars($home_location_data['ip']); ?>
                <span style="color: #90cdf4;">| Location: <?php echo htmlspecialchars("{$home_location_data['city']}, {$home_location_data['country']}"); ?></span>
                <?php if ($home_error_message): ?>
                    <span style="display: block; color: #fecaca; margin-top: 5px; font-weight: 400; font-size: 0.8rem;">Note: <?php echo htmlspecialchars($home_error_message); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Attack Summary (Uses real events only) -->
            <div class="summary-block attack-block">
                <strong>Total REAL Events Analyzed: <?php echo $total_all_events; ?></strong>
                <span style="color: #fca5a5; font-weight: 700;">External Hotspot: <?php echo empty($most_common_country) ? 'N/A' : htmlspecialchars($most_common_country); ?></span>
                
                <div class="severity-counts">
                    <?php 
                    foreach ($severity_map as $severity => $color): 
                        if ($severity_counts[$severity] > 0):
                    ?>
                        <span style="background-color: <?php echo $color; ?>;"><?php echo $severity; ?>: <?php echo $severity_counts[$severity]; ?></span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Map Column -->
            <div>
                <div class="map-controls">
                    <button id="tabReal" class="tab active">Real Events (<?php echo count($real_event_data); ?>)</button>
                    <button id="tabSim" class="tab">Simulated (<?php echo count($sim_event_data); ?>)</button>
                </div>
                <div id="map">
                    <canvas id="attackCanvas"></canvas>
                </div>
            </div>

            <!-- Right Panel Column (Holds swappable content) -->
            <div id="right-panel-column">

                <!-- Panel 1: Event Stream (Visible by default) -->
                <div class="event-list-container" id="eventListContainer">
                    <h2 style="color: #cbd5e1; margin-top: 0; font-size: 1.25rem; border-bottom: 1px solid #334155; padding-bottom: 10px;">Security Event Stream</h2>
                    <div id="eventList">
                        <?php if ($data_error): ?>
                             <p style="text-align: center; color: #fca5a5; margin-top: 40px;"><?php echo htmlspecialchars($data_error); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Panel 2: Details & Trace (Hidden by default) -->
                <div class="details-panel-container" id="detailsPanelContainer">
                    <div class="details-panel-header">
                        <h2>Event Details</h2>
                        <button id="backToEventsButton" class="back-button">&larr; Back to Events</button>
                    </div>
                    <div class="details-panel-content">
                        <div class="details-section">
                            <h4>Event Info</h4>
                            <span id="detailsDescription" style="display: block; margin-bottom: 5px; font-weight: 600;"></span>
                            <span id="detailsSourceIp" style="font-size: 0.9rem;"></span>
                        </div>
                        
                        <div class="details-section">
                            <h4>Raw Logs</h4>
                            <pre class="raw-log-output" id="detailsRawLogs"></pre>
                        </div>
                        
                        <div class="details-section">
                            <h4>Traceroute</h4>
                            <button id="traceRouteButton" class="trace-button">Trace Attacker's Route</button>
                            <span id="trace-status"></span>
                            <div id="trace-hop-list">
                                <!-- Hops will be populated here by JS -->
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Load Leaflet Map JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <!-- Inject PHP data into a global JS object -->
    <script>
        window.siemData = {
            homeLocation: <?php echo $home_location_json; ?>,
            realEvents: <?php echo $event_data_json; ?>,
            simEvents: <?php echo $sim_event_data_json; ?>,
            mostCommonCountry: <?php echo json_encode($most_common_country); ?>,
            severityMap: <?php echo $severity_map_json; ?> 
        };
    </script>
    
    <!-- Load the external JavaScript file that uses window.siemData -->
    <script src="map.js"></script>
</body>
</html>

