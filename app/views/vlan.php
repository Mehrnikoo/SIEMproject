<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VLAN Intelligence - SIEM Dashboard</title>
    <!-- Local lightweight styles so page works without external CDNs -->
    <link rel="stylesheet" href="public/assets/css/vlan.css">
</head>
<body>
    <div class="vlan-page">
        <header class="vlan-header">
            <h1 class="vlan-title">VLAN &amp; Network Intelligence Center</h1>
            <nav class="vlan-nav">
                <a href="index.php" class="vlan-btn vlan-btn-primary">← Dashboard</a>
                <a href="index.php?action=logs" class="vlan-btn vlan-btn-secondary">📋 Logs Viewer</a>
            </nav>
        </header>

        <div class="vlan-grid">
            <aside class="vlan-card">
                <div class="vlan-card-body">
                    <div class="vlan-dropdown-wrap">
                        <button type="button" id="vlan-toggle" class="vlan-dropdown-btn" onclick="toggleDropdown()">
                            <span id="vlan-selection">Loading VLANs...</span>
                            <svg id="dropdown-arrow" class="vlan-dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div id="vlan-menu" class="vlan-dropdown-menu hidden"></div>
                    </div>

                    <h2 class="vlan-card-title">VLAN &amp; SIEM Status</h2>
                    <ul class="vlan-status-list">
                        <li class="vlan-status-item">
                            <span class="vlan-status-label">Current VLAN</span>
                            <span id="vlan-display" class="vlan-status-value">--</span>
                        </li>
                        <li class="vlan-status-item">
                            <span class="vlan-status-label">Threat Count</span>
                            <span id="threat-count" class="vlan-status-value accent-warning">-- Alerts</span>
                        </li>
                        <li class="vlan-status-item">
                            <span class="vlan-status-label">Active Endpoints</span>
                            <span id="endpoint-count" class="vlan-status-value accent-success">--</span>
                        </li>
                    </ul>

                    <div class="vlan-stat-grid">
                        <div class="vlan-stat-chip emerald">
                            <span id="summary-endpoints" class="value">--</span>
                            <span class="label">Total Endpoints</span>
                        </div>
                        <div class="vlan-stat-chip amber">
                            <span id="summary-threats" class="value">--</span>
                            <span class="label">Total Threats</span>
                        </div>
                        <div class="vlan-stat-chip sky">
                            <span id="summary-vlans" class="value">--</span>
                            <span class="label">Active VLANs</span>
                        </div>
                    </div>

                    <div class="vlan-device-header">
                        <h3 class="vlan-device-title">VLAN Device List</h3>
                        <span id="device-count" class="vlan-device-count"></span>
                    </div>
                    <div class="vlan-device-cols">
                        <span>Type</span>
                        <span>IP Address</span>
                        <span>Last Seen</span>
                        <span class="col-action">Actions</span>
                    </div>
                    <div id="device-list" class="vlan-device-list">
                        <p class="vlan-empty">Gathering endpoints...</p>
                    </div>
                </div>
            </aside>

            <main class="vlan-card vlan-metrics-card">
                <div class="vlan-card-body">
                    <h2 class="vlan-metrics-title">VLAN Performance Metrics: <span id="graph-vlan-display">--</span></h2>
                    <div id="traffic-summary" class="vlan-traffic-summary"></div>
                    <div class="vlan-graph-area" id="vlan-graph-area">
                        <div class="vlan-graph-axis" style="position:absolute;top:20px;left:20px;right:20px;bottom:20px;"></div>
                        <p class="vlan-graph-placeholder" id="vlan-graph-placeholder">
                            Traffic &amp; latency data refresh every few seconds. Polling live VLAN statistics…
                        </p>
                    </div>
                </div>
                <div class="vlan-card vlan-alerts-card">
                    <h3 class="vlan-alerts-title">Critical Alerts Summary</h3>
                    <div id="alerts-container" class="vlan-alerts-list">
                        <p class="vlan-empty vlan-empty-sm">Awaiting data…</p>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        window.vlanState = <?php echo json_encode($vlan_data ?? []); ?>;
        window.vlanSummary = <?php echo json_encode($summary ?? []); ?>;
        
        let currentVlanName = null;
        
        function toggleDropdown() {
            const menu = document.getElementById('vlan-menu');
            const arrow = document.getElementById('dropdown-arrow');
            menu.classList.toggle('hidden');
            arrow.style.transform = menu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
        }
        
        function closeDropdown() {
            const menu = document.getElementById('vlan-menu');
            const arrow = document.getElementById('dropdown-arrow');
            if (!menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
                arrow.style.transform = 'rotate(0deg)';
            }
        }
        
        document.addEventListener('click', (event) => {
            const dropdownContainer = document.getElementById('vlan-toggle').parentElement;
            if (!dropdownContainer.contains(event.target)) {
                closeDropdown();
            }
        });
        
        function buildDropdown() {
            const menu = document.getElementById('vlan-menu');
            menu.innerHTML = '';
            if (!Array.isArray(window.vlanState) || window.vlanState.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'vlan-empty vlan-empty-sm';
                empty.textContent = 'No VLAN data available';
                menu.appendChild(empty);
                return;
            }
            window.vlanState.forEach(vlan => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'vlan-dropdown-item';
                item.textContent = vlan.name || vlan.cidr;
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    selectVlan(vlan.name || vlan.cidr);
                    closeDropdown();
                });
                menu.appendChild(item);
            });
        }
        
        function updateSummary() {
            document.getElementById('summary-vlans').textContent = window.vlanSummary.total_vlans ?? '--';
            document.getElementById('summary-endpoints').textContent = window.vlanSummary.total_endpoints ?? '--';
            document.getElementById('summary-threats').textContent = window.vlanSummary.total_threats ?? '--';
        }
        
        function formatTime(ts) {
            if (!ts) return 'Unknown';
            const date = new Date(ts);
            if (isNaN(date.getTime())) return ts;
            return date.toLocaleString();
        }
        
        function renderDeviceList(vlan) {
            const container = document.getElementById('device-list');
            if (!vlan || !Array.isArray(vlan.endpoints) || vlan.endpoints.length === 0) {
                container.innerHTML = '<p class="vlan-empty">No endpoints detected for this VLAN.</p>';
                document.getElementById('endpoint-count').textContent = '0';
                document.getElementById('device-count').textContent = '';
                return;
            }
            document.getElementById('endpoint-count').textContent = vlan.endpoint_count ?? vlan.endpoints.length;
            document.getElementById('device-count').textContent = `${vlan.endpoints.length} endpoints`;
            container.innerHTML = '';
            vlan.endpoints.slice(0, 25).forEach(endpoint => {
                const row = document.createElement('div');
                row.className = 'vlan-device-row';
                const ip = endpoint.ip || '';
                row.innerHTML = `
                    <span class="type">${endpoint.type || 'Host'}</span>
                    <span class="ip">${ip}</span>
                    <span class="time">${formatTime(endpoint.last_seen)}</span>
                    <span class="col-action">
                        <button type="button" class="vlan-btn-contain" data-ip="${ip}">Contain</button>
                    </span>
                `;
                const btn = row.querySelector('button');
                if (btn) {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        if (!ip) return;
                        if (!confirm('Contain device ' + ip + '? This will queue a containment action.')) return;
                        sendContainmentAction(ip);
                    });
                }
                container.appendChild(row);
            });
        }
        
        function renderAlerts(vlan) {
            const container = document.getElementById('alerts-container');
            const containmentLog = document.getElementById('containment-log');
            if (containmentLog) containmentLog.remove();
            container.innerHTML = '';
            if (!vlan || !Array.isArray(vlan.alert_bars) || vlan.alert_bars.length === 0) {
                container.innerHTML = '<p class="vlan-empty vlan-empty-sm">No alerts for this VLAN.</p>';
                document.getElementById('threat-count').textContent = '0 Alerts';
                if (containmentLog) container.appendChild(containmentLog);
                return;
            }
            document.getElementById('threat-count').textContent = `${vlan.threat_count ?? 0} Alerts`;
            vlan.alert_bars.forEach(item => {
                const maxWidth = Math.min(100, (item.count || 0) * 4);
                const wrapper = document.createElement('div');
                wrapper.innerHTML = `
                    <div class="vlan-alert-row">
                        <span>${item.type}</span>
                        <span class="count">${item.count}</span>
                    </div>
                    <div class="vlan-alert-bar">
                        <div class="vlan-alert-bar-fill" style="background-color:${item.color}; width:${maxWidth}%;"></div>
                    </div>
                `;
                container.appendChild(wrapper);
            });
            if (containmentLog) container.appendChild(containmentLog);
        }
        
        function renderTraffic(vlan) {
            const container = document.getElementById('traffic-summary');
            container.innerHTML = '';
            if (!vlan || !vlan.traffic) {
                container.innerHTML = '<span class="vlan-empty vlan-empty-sm">No traffic data.</span>';
                return;
            }
            Object.entries(vlan.traffic).forEach(([key, value]) => {
                const badge = document.createElement('div');
                badge.className = 'vlan-traffic-badge';
                badge.innerHTML = `<span class="label">${key}</span><span class="value">${value}</span>`;
                container.appendChild(badge);
            });

            // If Python network stats available and this VLAN contains the SIEM host, show host-level metrics
            try {
                const net = window.vlanNetwork || {};
                const stats = net.stats || null;
                const scan = net.scan || null;
                const hist = net.stats_history || null;
                // Determine if VLAN contains the host IP
                let showHost = false;
                if (scan && scan.private_ip) {
                    // Compare cidr base to vlan.cidr
                    if (vlan.cidr && scan.private_ip.startsWith(vlan.cidr.split('.0/')[0])) {
                        showHost = true;
                    }
                    // Or if any device in scan.devices is part of this VLAN endpoints
                    if (!showHost && Array.isArray(scan.devices) && scan.devices.length) {
                        const deviceSet = new Set(scan.devices);
                        const endpointIps = (vlan.endpoints || []).map(e => e.ip);
                        if (endpointIps.some(ip => deviceSet.has(ip))) showHost = true;
                    }
                }

                if (showHost && stats) {
                    const sent = stats.bytes_sent || 0;
                    const recv = stats.bytes_recv || 0;
                    const devices = (scan && Array.isArray(scan.devices)) ? scan.devices.length : 0;

                    // compute KB/s using history if available
                    let kbpsSent = null, kbpsRecv = null;
                    if (Array.isArray(hist) && hist.length >= 2) {
                        const a = hist[hist.length - 2];
                        const b = hist[hist.length - 1];
                        try {
                            const ta = new Date(a.timestamp).getTime() / 1000;
                            const tb = new Date(b.timestamp).getTime() / 1000;
                            const dt = Math.max(1, tb - ta);
                            kbpsSent = ((b.bytes_sent - a.bytes_sent) / dt) / 1024;
                            kbpsRecv = ((b.bytes_recv - a.bytes_recv) / dt) / 1024;
                        } catch (e) {
                            kbpsSent = null; kbpsRecv = null;
                        }
                    } else {
                        // fallback to previous sample stored in browser between polls
                        try {
                            const prev = window.lastNetworkSample || null;
                            if (prev && prev.timestamp && stats.timestamp) {
                                const ta = new Date(prev.timestamp).getTime() / 1000;
                                const tb = new Date(stats.timestamp).getTime() / 1000;
                                const dt = Math.max(1, tb - ta);
                                kbpsSent = ((stats.bytes_sent - prev.bytes_sent) / dt) / 1024;
                                kbpsRecv = ((stats.bytes_recv - prev.bytes_recv) / dt) / 1024;
                            } else if (prev && prev._ts) {
                                const ta = prev._ts / 1000;
                                const tb = Date.now() / 1000;
                                const dt = Math.max(1, tb - ta);
                                kbpsSent = ((stats.bytes_sent - prev.bytes_sent) / dt) / 1024;
                                kbpsRecv = ((stats.bytes_recv - prev.bytes_recv) / dt) / 1024;
                            }
                        } catch (e) {
                            kbpsSent = null; kbpsRecv = null;
                        }
                        // update lastNetworkSample for next poll
                        try { window.lastNetworkSample = Object.assign({}, stats); window.lastNetworkSample._ts = Date.now(); } catch(e) {}
                    }

                    const hostBadge = document.createElement('div');
                    hostBadge.className = 'vlan-traffic-badge';
                    hostBadge.innerHTML = `<span class="label">host sent</span><span class="value">${(sent/1024).toFixed(1)} KB</span><div class="label">${kbpsSent !== null ? kbpsSent.toFixed(1) + ' KB/s' : ''}</div>`;
                    container.appendChild(hostBadge);

                    const hostBadge2 = document.createElement('div');
                    hostBadge2.className = 'vlan-traffic-badge';
                    hostBadge2.innerHTML = `<span class="label">host recv</span><span class="value">${(recv/1024).toFixed(1)} KB</span><div class="label">${kbpsRecv !== null ? kbpsRecv.toFixed(1) + ' KB/s' : ''}</div>`;
                    container.appendChild(hostBadge2);

                    const devBadge = document.createElement('div');
                    devBadge.className = 'vlan-traffic-badge';
                    devBadge.innerHTML = `<span class="label">discovered</span><span class="value">${devices}</span>`;
                    container.appendChild(devBadge);

                    if (Array.isArray(hist) && hist.length) {
                        const slice = hist.slice(-60);
                        const canvas = document.createElement('canvas');
                        canvas.width = 240;
                        canvas.height = 48;
                        canvas.style.borderRadius = '6px';
                        const wrapper = document.createElement('div');
                        wrapper.className = 'vlan-traffic-badge';
                        wrapper.style.minWidth = '260px';
                        wrapper.appendChild(canvas);
                        container.appendChild(wrapper);

                        const ctx = canvas.getContext('2d');
                        const arr = slice.map(x => (x.bytes_recv || 0) + (x.bytes_sent || 0));
                        const max = Math.max(...arr, 1);
                        // clear
                        ctx.clearRect(0,0,canvas.width,canvas.height);
                        // gradient fill
                        const grad = ctx.createLinearGradient(0,0,0,canvas.height);
                        grad.addColorStop(0, 'rgba(59,130,246,0.18)');
                        grad.addColorStop(1, 'rgba(59,130,246,0.02)');
                        ctx.fillStyle = grad;
                        ctx.strokeStyle = '#3b82f6';
                        ctx.lineWidth = 2;

                        // path
                        ctx.beginPath();
                        arr.forEach((v, i) => {
                            const x = (i / (arr.length - 1 || 1)) * canvas.width;
                            const y = canvas.height - (v / max) * canvas.height;
                            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                        });
                        ctx.stroke();
                        // fill under curve
                        ctx.lineTo(canvas.width, canvas.height);
                        ctx.lineTo(0, canvas.height);
                        ctx.closePath();
                        ctx.fill();
                    }
                }
            } catch (e) {
                console.error('Error rendering host metrics', e);
            }
        }

        // Draws main network performance chart (bytes over time) using Python Network stats_history
        function renderNetworkGraph() {
            const area = document.getElementById('vlan-graph-area');
            if (!area) return;
            const placeholder = document.getElementById('vlan-graph-placeholder');
            const net = window.vlanNetwork || {};
            const hist = Array.isArray(net.stats_history) ? net.stats_history : null;

            if (!hist || hist.length < 2) {
                if (placeholder) placeholder.style.display = 'flex';
                const existing = document.getElementById('vlan-graph-canvas');
                if (existing) existing.style.display = 'none';
                return;
            }

            let canvas = document.getElementById('vlan-graph-canvas');
            if (!canvas) {
                canvas = document.createElement('canvas');
                canvas.id = 'vlan-graph-canvas';
                area.appendChild(canvas);
            }
            if (placeholder) placeholder.style.display = 'none';
            canvas.style.display = 'block';

            const rect = area.getBoundingClientRect();
            const paddingX = 20;
            const paddingY = 20;
            const width = Math.max(260, rect.width - paddingX * 2);
            const height = Math.max(160, rect.height - paddingY * 2);
            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            const slice = hist.slice(-120);
            const sentArr = slice.map(x => (x.bytes_sent || 0));
            const recvArr = slice.map(x => (x.bytes_recv || 0));
            const maxVal = Math.max(...sentArr, ...recvArr, 1);

            ctx.clearRect(0, 0, width, height);

            function drawSeries(arr, color) {
                ctx.beginPath();
                arr.forEach((v, i) => {
                    const x = (i / (arr.length - 1 || 1)) * (width - 2);
                    const y = height - (v / maxVal) * (height - 4);
                    if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                });
                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.stroke();
            }

            // Sent = blue, Recv = teal
            drawSeries(sentArr, '#3b82f6');
            drawSeries(recvArr, '#14b8a6');
        }
        
        function selectVlan(name) {
            if (!Array.isArray(window.vlanState) || window.vlanState.length === 0) {
                document.getElementById('vlan-selection').textContent = 'No VLANs';
                return;
            }
            const target = window.vlanState.find(v => v.name === name) || window.vlanState[0];
            currentVlanName = target.name;
            document.getElementById('vlan-selection').textContent = target.name;
            document.getElementById('vlan-display').textContent = target.name;
            document.getElementById('graph-vlan-display').textContent = target.name;
            renderDeviceList(target);
            renderAlerts(target);
            renderTraffic(target);
        }
        
        async function refreshVlanState() {
            try {
                const response = await fetch('index.php?action=vlan_state', { cache: 'no-store', headers: { 'Accept': 'application/json' } });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                window.vlanState = data.vlans || [];
                window.vlanSummary = data.summary || {};
                // expose network details globally for UI
                window.vlanNetwork = data.network || {};
                // keep last network sample to compute live rates when history isn't available
                if (!window.lastNetworkSample && window.vlanNetwork.stats) {
                    try {
                        window.lastNetworkSample = Object.assign({}, window.vlanNetwork.stats);
                        window.lastNetworkSample._ts = Date.now();
                    } catch(e) { window.lastNetworkSample = null; }
                }
                updateSummary();
                buildDropdown();
                renderNetworkGraph();
                if (currentVlanName) {
                    const exists = window.vlanState.find(v => v.name === currentVlanName);
                    if (exists) {
                        selectVlan(currentVlanName);
                        return;
                    }
                }
                selectVlan(window.vlanState.length ? window.vlanState[0].name : null);
            } catch (error) {
                console.error('Failed to refresh VLAN data', error);
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            updateSummary();
            buildDropdown();
            selectVlan(window.vlanState.length ? window.vlanState[0].name : null);
            renderNetworkGraph();

    async function sendContainmentAction(ip) {
        try {
            const payload = { ip: ip, command: 'block' };
            const r = await fetch('index.php?action=containment', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await r.json();
            if (data && data.status === 'ok') {
                alert('Containment queued for ' + ip);
                // refresh state to show pending action
                refreshVlanState();
            } else {
                alert('Failed to queue containment: ' + (data.message || 'unknown'));
            }
        } catch (e) {
            console.error(e);
            alert('Error sending containment action');
        }
    }
            setInterval(refreshVlanState, 3000);
        });
        // Display executed containment actions if present
        (function showContainmentLog() {
            const container = document.getElementById('alerts-container');
            const addLogSection = document.createElement('div');
            addLogSection.id = 'containment-log';
            container.appendChild(addLogSection);

            setInterval(() => {
                const logEl = document.getElementById('containment-log');
                if (!logEl) return;
                const executed = (window.vlanNetwork && window.vlanNetwork.executed_actions) || [];
                if (!executed.length) {
                    logEl.innerHTML = '<p class="vlan-empty vlan-empty-sm">No containment actions executed.</p>';
                    return;
                }
                logEl.innerHTML = '';
                executed.slice(-6).reverse().forEach(a => {
                    const row = document.createElement('div');
                    row.innerHTML = `<span>${a.ip} <span style="color:var(--vlan-text-muted)">(${a.status})</span></span><span style="font-size:0.8rem;color:var(--vlan-text-muted)">${a.executed_at || a.requested_at}</span>`;
                    logEl.appendChild(row);
                });
            }, 5000);
        })();
    </script>
</body>
</html>

