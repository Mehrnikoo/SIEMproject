<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300"> <!-- Auto refresh every 5 minutes -->
    <title>Logs Viewer - SIEM Dashboard</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap"></noscript>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background-color: #1e293b;
            border-radius: 12px;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #334155;
            gap: 20px;
        }
        
        .header h1 {
            color: #38bdf8;
            font-size: 1.75rem;
            font-weight: 800;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .nav-link {
            display: inline-block;
            padding: 10px 20px;
            background-color: #0e7490;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.2s;
        }
        
        .nav-link:hover {
            background-color: #155e75;
        }
        
        .event-section {
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .event-section h2 {
            margin-bottom: 4px;
            font-size: 1.25rem;
            color: #f8fafc;
        }
        
        .event-section p.description {
            color: #94a3b8;
            margin-bottom: 15px;
        }
        
        .event-group {
            margin-bottom: 20px;
        }
        
        .event-group h3 {
            font-size: 1rem;
            margin-bottom: 10px;
            color: #cbd5e1;
        }
        
        .event-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .event-table th,
        .event-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #1f2937;
        }
        
        .severity-pill {
            padding: 2px 8px;
            border-radius: 999px;
            color: #0f172a;
            font-weight: 700;
            font-size: 0.75rem;
            display: inline-block;
        }
        
        .event-meta {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
            font-size: 0.9rem;
        }
        
        .filter-group input[type="text"] {
            min-width: 250px;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #38bdf8;
        }
        
        .search-button {
            padding: 8px 16px;
            background-color: #0e7490;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
        }
        
        .search-button:hover {
            background-color: #155e75;
        }
        
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-box {
            background-color: #0f172a;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #334155;
        }
        
        .stat-box .label {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-bottom: 4px;
        }
        
        .stat-box .value {
            color: #38bdf8;
            font-size: 1.1rem;
            font-weight: 700;
        }
        
        .logs-table-container {
            background-color: #0f172a;
            border-radius: 8px;
            border: 1px solid #334155;
            overflow-x: auto;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .logs-table thead {
            background-color: #1e293b;
            position: sticky;
            top: 0;
        }
        
        .logs-table th {
            padding: 12px;
            text-align: left;
            color: #cbd5e1;
            font-weight: 700;
            border-bottom: 2px solid #334155;
            white-space: nowrap;
        }
        
        .logs-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #334155;
            color: #e2e8f0;
        }
        
        .logs-table tbody tr:hover {
            background-color: #1e293b;
        }
        
        .logs-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .log-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .log-type-Authentication { background-color: #7f1d1d; color: #fca5a5; }
        .log-type-Connection { background-color: #1e3a8a; color: #93c5fd; }
        .log-type-Firewall { background-color: #78350f; color: #fbbf24; }
        .log-type-Network { background-color: #065f46; color: #6ee7b7; }
        .log-type-HTTP { background-color: #581c87; color: #c4b5fd; }
        .log-type-System { background-color: #374151; color: #d1d5db; }
        .log-type-Other { background-color: #475569; color: #cbd5e1; }
        
        .event-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .event-row:hover {
            background-color: #1e293b;
        }
        
        .event-row-expanded {
            background-color: #1e293b;
        }
        
        .raw-logs-container {
            background-color: #0f172a;
            border-left: 4px solid #38bdf8;
            padding: 12px;
            margin-top: 8px;
            border-radius: 4px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .raw-logs-container h4 {
            color: #38bdf8;
            margin: 0 0 8px 0;
            font-size: 0.9rem;
        }
        
        .raw-log-line {
            background-color: #0f172a;
            border: 1px solid #334155;
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #94a3b8;
            word-break: break-all;
            max-height: 100px;
            overflow-y: auto;
        }
        
        .raw-log-line:last-child {
            margin-bottom: 0;
        }
        
        .raw-logs-empty {
            color: #64748b;
            font-size: 0.85rem;
            font-style: italic;
        }
        
        .log-raw {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #94a3b8;
            max-width: 600px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .log-raw:hover {
            white-space: normal;
            word-break: break-all;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #334155;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            background-color: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #e2e8f0;
            text-decoration: none;
            font-weight: 600;
        }
        
        .pagination a:hover {
            background-color: #1e293b;
            border-color: #38bdf8;
        }
        
        .pagination .current {
            background-color: #0e7490;
            border-color: #0e7490;
            color: white;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .no-logs {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .no-logs h2 {
            color: #64748b;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<?php
$defaultSeverityColors = [
    'Critical' => '#ef4444',
    'High' => '#f97316',
    'Medium' => '#fbbf24',
    'Low' => '#22c55e',
];
$severityColors = array_merge($defaultSeverityColors, $severity_map ?? []);
$real_events = $real_events ?? [];
$simulated_events = $simulated_events ?? [];

// Deduplication function: Remove duplicate suspicious events
function deduplicateEventsList($events) {
    if (empty($events) || !is_array($events)) {
        return [];
    }
    
    $seen = [];
    $deduplicated = [];
    
    foreach ($events as $event) {
        // Create a unique key based on: timestamp + ip + description + target
        // This ensures we don't show the same attack from the same source at the same time twice
        $timestamp = $event['timestamp'] ?? $event['time'] ?? '';
        $ip = $event['ip'] ?? $event['source_ip'] ?? '';
        $description = $event['description'] ?? '';
        $target = $event['target_device'] ?? '';
        
        $uniqueKey = "{$timestamp}||{$ip}||{$description}||{$target}";
        
        if (!isset($seen[$uniqueKey])) {
            $seen[$uniqueKey] = true;
            $deduplicated[] = $event;
        }
    }
    
    return $deduplicated;
}

// Deduplicate both real and simulated events
$real_events = deduplicateEventsList($real_events);
$simulated_events = deduplicateEventsList($simulated_events);

$real_event_count = count($real_events);
$sim_event_count = count($simulated_events);

if (!function_exists('logs_view_format_timestamp')) {
    function logs_view_format_timestamp($timestamp) {
        if (empty($timestamp)) {
            return 'N/A';
        }
        $time = strtotime($timestamp);
        if ($time === false) {
            return $timestamp;
        }
        return date('Y-m-d H:i:s', $time);
    }
}
?>
    <div class="container">
        <div class="header">
            <h1>📋 Logs Viewer</h1>
            <div class="header-actions">
                <a href="index.php" class="nav-link">← Dashboard</a>
                <a href="index.php?action=vlan" class="nav-link">VLAN View</a>
            </div>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <label for="typeFilter">Type:</label>
                <select id="typeFilter" name="type">
                    <option value="All" <?php echo $current_type_filter === 'All' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($log_types as $type): ?>
                        <option value="<?php echo View::escape($type); ?>" <?php echo $current_type_filter === $type ? 'selected' : ''; ?>>
                            <?php echo View::escape($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="searchFilter">Search:</label>
                <input type="text" id="searchFilter" name="search" 
                       value="<?php echo View::escape($current_search_filter); ?>" 
                       placeholder="Search logs...">
            </div>
            
            <button class="search-button" onclick="applyFilters()">Search</button>
            <a href="index.php?action=logs" class="search-button" style="text-decoration: none; display: inline-block;">Reset</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="label">Total Logs</div>
                <div class="value"><?php echo number_format($total_logs); ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Showing</div>
                <div class="value"><?php echo number_format(count($logs)); ?> entries</div>
            </div>
            <div class="stat-box">
                <div class="label">Page</div>
                <div class="value"><?php echo $current_page; ?> / <?php echo $total_pages; ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Real Events</div>
                <div class="value"><?php echo number_format($real_event_count); ?></div>
            </div>
            <div class="stat-box">
                <div class="label">Simulated Events</div>
                <div class="value"><?php echo number_format($sim_event_count); ?></div>
            </div>
        </div>
        
        <div class="event-section">
            <h2>Security Events Overview</h2>
            <p class="description">Latest events detected by the SIEM. Use this section to correlate raw log entries with higher-level alerts.</p>
            
            <div class="event-group">
                <h3>Real Events (<?php echo number_format($real_event_count); ?>)</h3>
                <?php if (empty($real_events)): ?>
                    <div class="no-logs" style="padding:20px 0;">No real events available.</div>
                <?php else: ?>
                    <div class="logs-table-container" style="margin-bottom:15px;">
                        <table class="event-table">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Time</th>
                                    <th>Source IP</th>
                                    <th>Target</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($real_events as $event): ?>
                                    <tr class="event-row" onclick="toggleRawLogs(this, 'event-<?php echo isset($event['id']) ? md5($event['id']) : md5(json_encode($event)); ?>')">
                                        <td>
                                            <?php $sev = $event['severity'] ?? 'Low'; ?>
                                            <span class="severity-pill" style="background-color: <?php echo View::escape($severityColors[$sev] ?? '#64748b'); ?>;">
                                                <?php echo View::escape($sev); ?>
                                            </span>
                                        </td>
                                        <td><?php echo View::escape(logs_view_format_timestamp($event['timestamp'] ?? null)); ?></td>
                                        <td>
                                            <div><?php echo View::escape($event['ip'] ?? 'Unknown'); ?></div>
                                            <div class="event-meta"><?php echo View::escape($event['city'] ?? ''); ?> <?php echo View::escape($event['country'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo View::escape($event['target_device'] ?? 'Asset'); ?></td>
                                        <td><?php echo View::escape($event['description'] ?? ''); ?></td>
                                    </tr>
                                    <tr id="event-<?php echo isset($event['id']) ? md5($event['id']) : md5(json_encode($event)); ?>" style="display:none;">
                                        <td colspan="5">
                                            <?php 
                                                $rawLogs = $event['raw_logs'] ?? [];
                                                if (!empty($rawLogs)):
                                            ?>
                                                <div class="raw-logs-container">
                                                    <h4>📋 Associated Raw Logs (<?php echo count($rawLogs); ?>)</h4>
                                                    <?php foreach ($rawLogs as $log): ?>
                                                        <div class="raw-log-line"><?php echo View::escape($log); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="raw-logs-container">
                                                    <div class="raw-logs-empty">No associated raw logs found for this event.</div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="event-group">
                <h3>Simulated Events (<?php echo number_format($sim_event_count); ?>)</h3>
                <?php if (empty($simulated_events)): ?>
                    <div class="no-logs" style="padding:20px 0;">No simulated events available.</div>
                <?php else: ?>
                    <div class="logs-table-container">
                        <table class="event-table">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Time</th>
                                    <th>Source IP</th>
                                    <th>Target</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($simulated_events as $event): ?>
                                    <tr class="event-row" onclick="toggleRawLogs(this, 'event-<?php echo isset($event['id']) ? md5($event['id']) : md5(json_encode($event)); ?>')">
                                        <td>
                                            <?php $sev = $event['severity'] ?? 'Low'; ?>
                                            <span class="severity-pill" style="background-color: <?php echo View::escape($severityColors[$sev] ?? '#64748b'); ?>;">
                                                <?php echo View::escape($sev); ?>
                                            </span>
                                        </td>
                                        <td><?php echo View::escape(logs_view_format_timestamp($event['timestamp'] ?? null)); ?></td>
                                        <td>
                                            <div><?php echo View::escape($event['ip'] ?? 'Unknown'); ?></div>
                                            <div class="event-meta"><?php echo View::escape($event['city'] ?? ''); ?> <?php echo View::escape($event['country'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo View::escape($event['target_device'] ?? 'Asset'); ?></td>
                                        <td><?php echo View::escape($event['description'] ?? ''); ?></td>
                                    </tr>
                                    <tr id="event-<?php echo isset($event['id']) ? md5($event['id']) : md5(json_encode($event)); ?>" style="display:none;">
                                        <td colspan="5">
                                            <?php 
                                                $rawLogs = $event['raw_logs'] ?? [];
                                                if (!empty($rawLogs)):
                                            ?>
                                                <div class="raw-logs-container">
                                                    <h4>📋 Associated Raw Logs (<?php echo count($rawLogs); ?>)</h4>
                                                    <?php foreach ($rawLogs as $log): ?>
                                                        <div class="raw-log-line"><?php echo View::escape($log); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="raw-logs-container">
                                                    <div class="raw-logs-empty">No associated raw logs found for this event.</div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="no-logs">
                <h2>No raw logs found</h2>
                <p>Try adjusting your filters or run 'python3 log_processor.py' to generate logs.</p>
            </div>
        <?php else: ?>
            <div class="logs-table-container">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Timestamp</th>
                            <th>Type</th>
                            <th>Log File</th>
                            <th>Source IP</th>
                            <th>Destination IP</th>
                            <th>Raw Log</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo View::escape($log['id'] ?? 'N/A'); ?></td>
                                <td><?php echo View::escape($log['timestamp'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="log-type-badge log-type-<?php echo View::escape($log['log_type'] ?? 'Other'); ?>">
                                        <?php echo View::escape($log['log_type'] ?? 'Other'); ?>
                                    </span>
                                </td>
                                <td><?php echo View::escape($log['log_file'] ?? 'N/A'); ?></td>
                                <td><?php echo View::escape($log['source_ip'] ?? '-'); ?></td>
                                <td><?php echo View::escape($log['destination_ip'] ?? '-'); ?></td>
                                <td>
                                    <div class="log-raw" title="<?php echo View::escape($log['raw_line'] ?? ''); ?>">
                                        <?php echo View::escape($log['raw_line'] ?? ''); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?action=logs&page=<?php echo $current_page - 1; ?>&type=<?php echo urlencode($current_type_filter); ?>&search=<?php echo urlencode($current_search_filter); ?>">← Previous</a>
                    <?php else: ?>
                        <span class="disabled">← Previous</span>
                    <?php endif; ?>
                    
                    <span class="current">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?action=logs&page=<?php echo $current_page + 1; ?>&type=<?php echo urlencode($current_type_filter); ?>&search=<?php echo urlencode($current_search_filter); ?>">Next →</a>
                    <?php else: ?>
                        <span class="disabled">Next →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function applyFilters() {
            const type = document.getElementById('typeFilter').value;
            const search = document.getElementById('searchFilter').value;
            const params = new URLSearchParams();
            params.set('action', 'logs');
            if (type !== 'All') params.set('type', type);
            if (search) params.set('search', search);
            window.location.href = 'index.php?' + params.toString();
        }
        
        // Allow Enter key to trigger search
        document.getElementById('searchFilter').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Toggle raw logs display for events
        function toggleRawLogs(rowElement, logRowId) {
            const logRow = document.getElementById(logRowId);
            const isVisible = logRow.style.display !== 'none';
            
            // Close all other expanded rows
            document.querySelectorAll('[id^="event-"]').forEach(el => {
                if (el.id !== logRowId) {
                    el.style.display = 'none';
                }
            });
            
            // Toggle this row
            logRow.style.display = isVisible ? 'none' : 'table-row';
            rowElement.classList.toggle('event-row-expanded');
        }
    </script>
</body>
</html>

