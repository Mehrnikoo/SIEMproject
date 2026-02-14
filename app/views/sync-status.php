<?php
/**
 * Sync Status View - Display synchronization information
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIEM - Sync Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        
        .status-card {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
        }
        
        .status-card label {
            display: block;
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .status-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .status-card.critical .value {
            color: #ef4444;
        }
        
        .status-card.warning .value {
            color: #f97316;
        }
        
        .status-card.success .value {
            color: #22c55e;
        }
        
        .info-section {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 13px;
            color: #333;
        }
        
        .info-section strong {
            display: block;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 30px;
        }
        
        button {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .btn-full {
            grid-column: 1 / -1;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background: #fef2f2;
            border-color: #ef4444;
            color: #7f1d1d;
        }
        
        .timestamp {
            color: #999;
            font-size: 12px;
            margin-top: 20px;
            text-align: center;
        }
        
        .loading {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #667eea;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 5px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>SIEM Synchronization Status</h1>
            <p class="subtitle">Monitor integration between Python script and website</p>
        </header>
        
        <div class="status-grid">
            <div class="status-card success">
                <label>Security Events</label>
                <div class="value" id="security-events"><?php echo $status['security_events']; ?></div>
            </div>
            
            <div class="status-card warning">
                <label>Raw Logs</label>
                <div class="value" id="raw-logs"><?php echo $status['raw_logs']; ?></div>
            </div>
        </div>
        
        <div class="info-section">
            <strong>Python Logs Directory:</strong>
            <code><?php echo View::escape($status['python_logs_dir']); ?></code>
        </div>
        
        <div class="info-section">
            <strong>Last Status Check:</strong>
            <?php echo $status['last_sync']; ?>
        </div>
        
        <div id="sync-message" class="hidden"></div>
        
        <div class="actions">
            <a href="http://localhost/SIEMproject/" class="btn-secondary">Dashboard</a>
            <button class="btn-primary" onclick="syncNow()">
                Sync Now
                <span id="sync-spinner" class="loading hidden"></span>
            </button>
        </div>
        
        <div class="timestamp">
            Last update: <span id="update-time"><?php echo date('H:i:s'); ?></span>
            <br>
            <small>Auto-refresh enabled</small>
        </div>
    </div>
    
    <script>
        // Auto-refresh status every 5 seconds
        setInterval(refreshStatus, 5000);
        
        function refreshStatus() {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=status')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('security-events').textContent = data.security_events;
                    document.getElementById('raw-logs').textContent = data.raw_logs;
                    document.getElementById('update-time').textContent = new Date().toLocaleTimeString();
                })
                .catch(e => console.log('Refresh failed:', e));
        }
        
        function syncNow() {
            const btn = event.target;
            const spinner = document.getElementById('sync-spinner');
            const msg = document.getElementById('sync-message');
            
            btn.disabled = true;
            spinner.classList.remove('hidden');
            msg.classList.add('hidden');
            
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?action=sync')
                .then(r => r.json())
                .then(data => {
                    msg.className = 'alert alert-success';
                    msg.textContent = '✓ ' + data.message;
                    msg.classList.remove('hidden');
                    
                    setTimeout(() => {
                        refreshStatus();
                    }, 1000);
                })
                .catch(e => {
                    msg.className = 'alert alert-error';
                    msg.textContent = '✗ Sync failed: ' + e.message;
                    msg.classList.remove('hidden');
                })
                .finally(() => {
                    btn.disabled = false;
                    spinner.classList.add('hidden');
                });
        }
    </script>
</body>
</html>
