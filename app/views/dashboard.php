<?php
// Prepare JSON data for JavaScript
$event_data_json = json_encode($real_event_data);
$sim_event_data_json = json_encode($sim_event_data);
$home_location_json = json_encode($home_location_data);
$severity_map_json = json_encode($severity_map);
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
    <!-- Note: Using 1.9.4 for stability. Latest version may have fixes but we suppress warnings below -->
    
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
            align-items: center;
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
        
        /* IP Search Bar */
        .ip-search-container {
            display: flex;
            gap: 8px;
            flex: 1;
            margin-left: auto;
        }
        .ip-search-input {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            background: #1f2937;
            border: 1px solid #334155;
            color: #cbd5e1;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .ip-search-input:focus {
            outline: none;
            border-color: #38bdf8;
            background: #0f172a;
        }
        .ip-search-input::placeholder {
            color: #64748b;
        }
        .ip-search-button {
            padding: 8px 16px;
            border-radius: 8px;
            background: #0e7490;
            color: white;
            border: none;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        .ip-search-button:hover {
            background: #155e75;
        }
        .ip-search-button:disabled {
            background: #475569;
            cursor: not-allowed;
            opacity: 0.7;
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
        
        /* Whois Panel Styling (Panel 3) */
        .whois-panel-container {
            display: none; /* Set to 'flex' or 'none' by JS */
            flex-direction: column;
            overflow: hidden;
            flex-grow: 1;
            height: 100%;
        }
        
        .whois-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .whois-panel-header h2 {
            color: #cbd5e1;
            margin: 0;
            font-size: 1.25rem;
        }
        
        .whois-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .whois-info-item {
            background-color: #0f172a;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #334155;
        }
        
        .whois-info-item label {
            display: block;
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .whois-info-item .value {
            color: #e2e8f0;
            font-size: 0.95rem;
            word-break: break-word;
        }
        
        .whois-info-item .value.ip-address {
            color: #fbbf24;
            font-weight: 600;
            font-family: monospace;
        }
        
        .whois-loading {
            text-align: center;
            color: #94a3b8;
            padding: 40px 20px;
        }
        
        .whois-error {
            text-align: center;
            color: #fca5a5;
            padding: 20px;
            background-color: #7f1d1d;
            border-radius: 6px;
            border: 1px solid #991b1b;
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Web-Based SIEM Geolocation Dashboard</h1>
            <a href="index.php?action=logs" style="display: inline-block; padding: 10px 20px; background-color: #0e7490; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: background-color 0.2s;">
                📋 View Logs
            </a>
        </div>

        <div class="summary-bar">
            <!-- Monitored Network Status (Home Location) -->
            <div class="summary-block home-block">
                <strong>Monitored Network Endpoints</strong>
                <span style="display: block;">Private IP: <?php echo View::escape($home_location_data['private_ip']); ?> (Server)</span>

                Public IP: <?php echo View::escape($home_location_data['ip']); ?>
                <span style="color: #90cdf4;">| Location: <?php echo View::escape("{$home_location_data['city']}, {$home_location_data['country']}"); ?></span>
                <?php if (!empty($home_location_data['error_message'])): ?>
                    <span style="display: block; color: #fecaca; margin-top: 5px; font-weight: 400; font-size: 0.8rem;">Note: <?php echo View::escape($home_location_data['error_message']); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Attack Summary (Uses real events only) -->
            <div class="summary-block attack-block">
                <strong>Total REAL Events Analyzed: <?php echo $total_all_events; ?></strong>
                <span style="color: #fca5a5; font-weight: 700;">External Hotspot: <?php echo empty($most_common_country) ? 'N/A' : View::escape($most_common_country); ?></span>
                
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
                    <div class="ip-search-container">
                        <input type="text" id="ipSearchInput" class="ip-search-input" placeholder="Enter IP address (e.g., 8.8.8.8)" />
                        <button id="ipSearchButton" class="ip-search-button">Whois Lookup</button>
                    </div>
                </div>
                <div id="map">
                    <canvas id="attackCanvas"></canvas>
                </div>
            </div>

            <!-- Right Panel Column (Holds swappable content) -->
            <div id="right-panel-column">

                <!-- Panel 1: Event Stream (Visible by default) -->
                <div class="event-list-container" id="eventListContainer">
                    <h2 style="color: #cbd5e1; margin-top: 0; font-size: 1.25rem; border-bottom: 1px solid #334155; padding-bottom: 10px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <span>Security Event Stream</span>
                        <span style="display:flex; align-items:center; gap:8px;">
                            <label for="eventSortSelect" style="color:#94a3b8; font-size:0.9rem;">Sort by</label>
                            <select id="eventSortSelect" style="background:#0f172a; color:#e2e8f0; border:1px solid #334155; border-radius:6px; padding:6px 8px; font-family:'Inter', sans-serif;">
                                <option value="severity_desc">Severity (High → Low)</option>
                                <option value="severity_asc">Severity (Low → High)</option>
                                <option value="date_desc">Date (Newest First)</option>
                                <option value="date_asc">Date (Oldest First)</option>
                            </select>
                        </span>
                    </h2>
                    <div id="eventList">
                        <?php if ($data_error): ?>
                             <p style="text-align: center; color: #fca5a5; margin-top: 40px;">&nbsp;<?php echo View::escape($data_error); ?></p>
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

                <!-- Panel 3: Whois Lookup (Hidden by default) -->
                <div class="whois-panel-container" id="whoisPanelContainer">
                    <div class="whois-panel-header">
                        <h2>IP Address Information</h2>
                        <button id="backToEventsFromWhoisButton" class="back-button">&larr; Back to Events</button>
                    </div>
                    <div class="details-panel-content" id="whoisContent">
                        <div class="whois-loading" id="whoisLoading" style="display: none;">
                            Loading IP information...
                        </div>
                        <div class="whois-error" id="whoisError" style="display: none;"></div>
                        <div class="whois-info-grid" id="whoisInfoGrid" style="display: none;">
                            <!-- Whois information will be populated here by JS -->
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Load Leaflet Map JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    
    <!-- Suppress harmless Leaflet deprecation warnings -->
    <script>
        // Suppress Firefox-specific deprecation warnings from Leaflet.js
        // These are harmless warnings from Leaflet's internal code
        const originalWarn = console.warn;
        console.warn = function(...args) {
            const message = args.join(' ');
            // Filter out the specific deprecation warnings from Leaflet
            if (message.includes('mozPressure') || message.includes('mozInputSource')) {
                return; // Suppress these warnings
            }
            // Allow all other warnings to pass through
            originalWarn.apply(console, args);
        };
    </script>
    
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
    <script src="public/assets/js/map.js"></script>
</body>
</html>

