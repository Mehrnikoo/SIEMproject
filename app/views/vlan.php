<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VLAN Intelligence - SIEM Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0e111a;
            color: #e5e7eb;
        }
        .graph-axis {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
        }
        .graph-axis::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 1px;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .graph-axis::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 1px;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .custom-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: #1a2130;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background-color: #374151;
            border-radius: 20px;
        }
    </style>
</head>
<body class="p-4 md:p-8 min-h-screen antialiased">
    <div class="max-w-7xl mx-auto h-full space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h1 class="text-2xl font-extrabold text-sky-400">VLAN &amp; Network Intelligence Center</h1>
            <div class="flex gap-2 flex-wrap">
                <a href="index.php" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white font-medium text-sm rounded-lg shadow-md transition duration-200 flex items-center justify-center">
                    ← Dashboard
                </a>
                <a href="index.php?action=logs" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white font-medium text-sm rounded-lg shadow-md transition duration-200 flex items-center justify-center">
                    📋 Logs Viewer
                </a>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-[85vh]">
            <div class="lg:col-span-1 flex flex-col space-y-4">
                <div class="flex flex-col space-y-1 w-full">
                    <div class="flex items-center justify-center w-full m-2">
                        <div class="relative w-full max-w-xs">
                            <button id="vlan-toggle" class="w-full flex items-center justify-between px-4 py-3 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded-lg shadow-md transition duration-200" onclick="toggleDropdown()">
                                <span id="vlan-selection" class="truncate">Loading VLANs...</span>
                                <svg id="dropdown-arrow" class="w-4 h-4 ml-2 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                            <div id="vlan-menu" class="absolute left-0 right-0 z-10 mt-2 bg-[#1a2130] border border-[#374151] rounded-lg shadow-xl py-1 hidden"></div>
                        </div>
                    </div>
                    <div class="h-0 border-t border-dashed border-gray-600 w-full mb-2"></div>
                </div>
                
                <div class="flex-1 bg-[#1a2130] rounded-xl p-6 shadow-2xl border border-[#374151]/50 flex flex-col">
                    <h2 class="text-lg font-semibold mb-4 text-white/90">VLAN &amp; SIEM Status</h2>
                    
                    <div class="space-y-3 mb-4">
                        <div class="pb-2 border-b border-dashed border-gray-600/70">
                            <span class="text-sm text-gray-400">Current VLAN Selected: <span id="vlan-display" class="font-bold text-white">--</span></span>
                        </div>
                        <div class="pb-2 border-b border-dashed border-gray-600/70">
                            <span class="text-sm text-gray-400">Threat Count (Active): <span class="font-bold text-yellow-400" id="threat-count">-- Alerts</span></span>
                        </div>
                        <div class="pb-2 border-b border-dashed border-gray-600/70">
                            <span class="text-sm text-gray-400">Active Endpoints: <span class="font-bold text-green-400" id="endpoint-count">--</span></span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3 text-center text-xs text-gray-400 mb-4">
                        <div class="bg-slate-800/60 rounded-lg p-3 border border-slate-700">
                            <div class="text-emerald-300 text-lg font-bold" id="summary-endpoints">--</div>
                            <p>Total Endpoints</p>
                        </div>
                        <div class="bg-slate-800/60 rounded-lg p-3 border border-slate-700">
                            <div class="text-amber-300 text-lg font-bold" id="summary-threats">--</div>
                            <p>Total Threats</p>
                        </div>
                        <div class="bg-slate-800/60 rounded-lg p-3 border border-slate-700">
                            <div class="text-sky-300 text-lg font-bold" id="summary-vlans">--</div>
                            <p>Active VLANs</p>
                        </div>
                    </div>
                    
                    <h3 class="text-md font-semibold text-white/90 mb-2 mt-2 flex items-center justify-between">
                        VLAN Device List
                        <span class="text-xs text-slate-400 font-normal" id="device-count"></span>
                    </h3>
                    <div class="flex justify-between text-xs text-gray-400 border-b border-gray-700 pb-1 mb-2 font-medium uppercase">
                        <span class="w-1/3">Type</span>
                        <span class="w-1/3">IP Address</span>
                        <span class="w-1/3 text-right">Last Seen</span>
                    </div>
                    <div id="device-list" class="flex-1 overflow-y-scroll custom-scroll space-y-3 pr-2 text-sm text-gray-300">
                        <p class="text-gray-500 text-center">Gathering endpoints...</p>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-2 flex flex-col space-y-6">
                <div class="flex-1 bg-[#1a2130] rounded-xl shadow-2xl p-6 relative overflow-hidden border border-[#374151]/50 flex flex-col">
                    <h2 class="text-lg font-semibold text-white/90 mb-2">VLAN Performance Metrics: <span id="graph-vlan-display">--</span></h2>
                    <div class="flex gap-4 text-sm text-gray-400 mb-3 flex-wrap" id="traffic-summary"></div>
                    <div class="flex-1 relative">
                        <div class="graph-axis"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-xl text-gray-600 font-light text-center p-10">
                            Traffic &amp; Latency data refreshes every few seconds. Polling live VLAN statistics...
                        </div>
                    </div>
                </div>
                
                <div class="h-40 bg-[#1a2130] rounded-xl shadow-xl p-4 flex flex-col justify-center border border-[#374151]/50">
                    <h3 class="text-sm font-medium text-white/70 mb-2">Critical Alerts Summary</h3>
                    <div id="alerts-container" class="space-y-2">
                        <p class="text-gray-500 text-sm">Awaiting data...</p>
                    </div>
                </div>
            </div>
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
                empty.className = 'px-4 py-2 text-sm text-gray-400';
                empty.textContent = 'No VLAN data available';
                menu.appendChild(empty);
                return;
            }
            window.vlanState.forEach(vlan => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'block px-4 py-2 text-sm text-gray-300 hover:bg-sky-500/30';
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
                container.innerHTML = '<p class="text-gray-500 text-center">No endpoints detected for this VLAN.</p>';
                document.getElementById('endpoint-count').textContent = '0';
                document.getElementById('device-count').textContent = '';
                return;
            }
            document.getElementById('endpoint-count').textContent = vlan.endpoint_count ?? vlan.endpoints.length;
            document.getElementById('device-count').textContent = `${vlan.endpoints.length} endpoints`;
            container.innerHTML = '';
            vlan.endpoints.slice(0, 25).forEach(endpoint => {
                const row = document.createElement('div');
                row.className = 'flex justify-between text-sm hover:bg-gray-700/30 p-1 rounded-md transition duration-150';
                row.innerHTML = `
                    <span class="w-1/3 font-semibold text-blue-200">${endpoint.type || 'Host'}</span>
                    <span class="w-1/3 text-gray-200">${endpoint.ip}</span>
                    <span class="w-1/3 text-right text-gray-500 text-xs">${formatTime(endpoint.last_seen)}</span>
                `;
                container.appendChild(row);
            });
        }
        
        function renderAlerts(vlan) {
            const container = document.getElementById('alerts-container');
            container.innerHTML = '';
            if (!vlan || !Array.isArray(vlan.alert_bars) || vlan.alert_bars.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-sm">No alerts for this VLAN.</p>';
                document.getElementById('threat-count').textContent = '0 Alerts';
                return;
            }
            document.getElementById('threat-count').textContent = `${vlan.threat_count ?? 0} Alerts`;
            vlan.alert_bars.forEach(item => {
                const maxWidth = Math.min(100, (item.count || 0) * 4);
                const wrapper = document.createElement('div');
                wrapper.innerHTML = `
                    <div class="flex justify-between text-sm text-gray-400 mb-1">
                        <span>${item.type}</span>
                        <span class="font-bold">${item.count}</span>
                    </div>
                    <div class="h-2 rounded-full transition-all duration-500" style="background-color:${item.color}; width:${maxWidth}%; max-width:100%;"></div>
                `;
                container.appendChild(wrapper);
            });
        }
        
        function renderTraffic(vlan) {
            const container = document.getElementById('traffic-summary');
            container.innerHTML = '';
            if (!vlan || !vlan.traffic) {
                container.innerHTML = '<span class="text-xs text-gray-500">No traffic data.</span>';
                return;
            }
            Object.entries(vlan.traffic).forEach(([key, value]) => {
                const badge = document.createElement('div');
                badge.className = 'bg-slate-800/70 rounded-lg px-3 py-2 border border-slate-700 text-xs';
                badge.innerHTML = `<span class="text-gray-400 capitalize">${key}</span><div class="text-white font-bold text-base">${value}</div>`;
                container.appendChild(badge);
            });
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
                const response = await fetch('index.php?action=vlan_state', { cache: 'no-store' });
                if (!response.ok) {
                    return;
                }
                const data = await response.json();
                window.vlanState = data.vlans || [];
                window.vlanSummary = data.summary || {};
                updateSummary();
                buildDropdown();
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
            setInterval(refreshVlanState, 15000);
        });
    </script>
</body>
</html>

