/**
 * Frontend JavaScript Logic: Initializes a Leaflet Map, adds markers,
 * and draws animated CURVED attack lines on an HTML5 Canvas overlay.
 *
 * MODIFIED: Replaced the modal with a swappable "Details Panel."
 * MODIFIED: Traceroute animation now "hops" from node to node,
 * drawing the marker AND the line one by one.
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- 1. DATA AND VARIABLE SETUP ---
    const siemData = window.siemData || {};
    const homeLocation = siemData.homeLocation;
    const realEvents = siemData.realEvents || [];
    const simEvents = siemData.simEvents || [];
    const severityMap = siemData.severityMap || {};

    const mapContainer = document.getElementById('map');
    const canvas = document.getElementById('attackCanvas');
    const ctx = canvas.getContext('2d');
    
    const homeLat = homeLocation.lat;
    const homeLon = homeLocation.lon;
    const homeCoords = [homeLat, homeLon];
    
    // Global state for animation
    let dashOffset = 0;
    let currentEventsToDraw = [];
    let currentTraceHops = []; // Will hold the FULL list of hops, including home
    
    // Hop Animation State
    let currentHopToAnimate = -1; // Index of the hop being animated. -1 means idle.
    let hopAnimationTimer = 0;
    const HOP_ANIMATION_SPEED = 45; // Frames to wait (45 frames ≈ 0.75s) - Slower

    // Layer and marker tracking
    let eventLayer = null;
    let traceLayer = null;
    const markersById = {};

    // Panel Management Elements
    const eventListContainer = document.getElementById('eventListContainer');
    const detailsPanelContainer = document.getElementById('detailsPanelContainer');
    const backToEventsButton = document.getElementById('backToEventsButton');
    
    // Details Panel Elements
    const detailsDescription = document.getElementById('detailsDescription');
    const detailsSourceIp = document.getElementById('detailsSourceIp');
    const detailsRawLogs = document.getElementById('detailsRawLogs');
    const traceRouteButton = document.getElementById('traceRouteButton');
    const traceStatus = document.getElementById('trace-status');
    const traceHopList = document.getElementById('trace-hop-list');
    
    let currentEventForTrace = null; // Store the selected event

    // --- 2. LEAFLET MAP INITIALIZATION ---
    const map = L.map('map', {
        center: (homeLat !== 0 || homeLon !== 0) ? homeCoords : [20, 0],
        zoom: (homeLat !== 0 || homeLon !== 0) ? 3 : 2
    });

    L.tileLayer('https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a>, &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
        maxZoom: 18,
        id: 'stamen-toner-dark'
    }).addTo(map);

    eventLayer = L.layerGroup().addTo(map);
    traceLayer = L.layerGroup().addTo(map);

    function addHomeMarker() {
         if (homeLat !== 0 || homeLon !== 0) {
            L.marker(homeCoords, {
                icon: L.divIcon({
                    className: 'home-marker',
                    html: '<div style="background-color:#38bdf8; width: 15px; height: 15px; border-radius: 50%; border: 3px solid #1e293b; box-shadow: 0 0 5px #38bdf8;"></div>',
                    iconSize: [21, 21],
                    iconAnchor: [10, 10]
                })
            }).addTo(eventLayer).bindPopup(
                `<div style="font-family: 'Inter', sans-serif; text-align: center; color: #1e293b;">` +
                `<strong>Server Location</strong><br>` +
                `${homeLocation.city}, ${homeLocation.country}<br>` +
                `<small>Public IP: ${homeLocation.ip}</small><br>` +
                `<small>Private IP: ${homeLocation.private_ip}</small>` +
                `</div>`
            ).openPopup();
        }
    }
    
    // --- 3. CANVAS DRAWING LOGIC ---
    function resizeCanvas() {
        canvas.width = mapContainer.clientWidth;
        canvas.height = mapContainer.clientHeight;
    }

    function drawQuadraticCurve(startPixel, endPixel, color, lineDash = [8, 12], lineWidth = 2.5, alpha = 0.8, isAnimated = true) {
        const x1 = startPixel.x;
        const y1 = startPixel.y;
        const x2 = endPixel.x;
        const y2 = endPixel.y;
        const midX = (x1 + x2) / 2;
        const midY = (y1 + y2) / 2;
        const distance = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
        const curveHeight = Math.min(distance * 0.3, 100);
        const curveDirection = (x2 > x1) ? 1 : -1;
        const controlX = midX;
        const controlY = midY - (curveHeight * curveDirection);
        
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.quadraticCurveTo(controlX, controlY, x2, y2);
        
        ctx.strokeStyle = color;
        ctx.lineWidth = lineWidth;
        ctx.globalAlpha = alpha;
        ctx.setLineDash(lineDash);
        
        if (isAnimated) {
            ctx.lineDashOffset = dashOffset;
        } else {
            ctx.lineDashOffset = 0;
        }
        
        ctx.stroke();
    }

    /**
     * --- MODIFIED: The main animation loop ---
     * Handles hop-by-hop animation timer and drawing.
     */
    function animate() {
        dashOffset -= 0.2;
        if (dashOffset < -20) dashOffset = 0;

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // 1. Draw all main attack events
        currentEventsToDraw.forEach(event => {
            if (event.type === 'EXTERNAL' && event.lat !== null && event.lon !== null && (event.lat !== 0 || event.lon !== 0)) {
                const startPixel = map.latLngToContainerPoint([event.lat, event.lon]);
                const endPixel = map.latLngToContainerPoint(homeCoords);
                const severityColor = severityMap[event.severity] || '#fff';
                drawQuadraticCurve(startPixel, endPixel, severityColor, [8, 12], 2.5, 0.8, true);
            }
        });

        // 2. Draw the "hopping" traceroute path
        if (currentTraceHops.length > 0 && currentHopToAnimate >= 0) {
            
            // This loop draws all the "settled" (finished) hops
            // It loops up to currentHopToAnimate, drawing the path segment
            for (let i = 0; i < currentHopToAnimate; i++) {
                const hopA = currentTraceHops[i];
                const hopB = currentTraceHops[i+1];
                
                const startPixel = map.latLngToContainerPoint([hopA.lat, hopA.lon]);
                const endPixel = map.latLngToContainerPoint([hopB.lat, hopB.lon]);
                
                // Draw as a faint, static (isAnimated: false) line
                drawQuadraticCurve(startPixel, endPixel, '#0e7490', [5, 5], 2, 0.4, false);
            }
            
            // This draws the *current, active* hop
            // (if the animation isn't finished)
            if (currentHopToAnimate < currentTraceHops.length - 1) {
                const hopA = currentTraceHops[currentHopToAnimate];
                const hopB = currentTraceHops[currentHopToAnimate + 1];
                
                const startPixel = map.latLngToContainerPoint([hopA.lat, hopA.lon]);
                const endPixel = map.latLngToContainerPoint([hopB.lat, hopB.lon]);
                
                // Draw as a bright, animated (isAnimated: true) line
                drawQuadraticCurve(startPixel, endPixel, '#0e7490', [5, 5], 3, 1.0, true);
            }

            // 3. Update the hop animation timer
            // (Stops advancing once the last hop is drawn)
            if (currentHopToAnimate < currentTraceHops.length - 1) {
                hopAnimationTimer++;
                if (hopAnimationTimer > HOP_ANIMATION_SPEED) {
                    currentHopToAnimate++; // Move to the next hop
                    hopAnimationTimer = 0;
                    
                    // --- NEW: Draw the NEXT hop marker ---
                    // This is the "one-by-one" logic
                    drawHopMarker(currentHopToAnimate); 
                }
            }
        }

        requestAnimationFrame(animate);
    }

    // --- 4. EVENT LIST AND UI LOGIC ---

    function clearLeafletMarkers() {
        eventLayer.clearLayers();
        for (const k in markersById) delete markersById[k];
        addHomeMarker();
    }

    function renderEvents(list) {
        clearLeafletMarkers();
        currentEventsToDraw = list;
        if (!list) return;
        
        list.forEach(event => {
            if (event.type === 'EXTERNAL' && event.lat !== null && event.lon !== null && (event.lat !== 0 || event.lon !== 0)) {
                const attackCoords = [event.lat, event.lon];
                const severityColor = severityMap[event.severity] || '#fff';
                const marker = L.circleMarker(attackCoords, {
                    radius: 6,
                    fillColor: severityColor,
                    color: '#fff',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(eventLayer).bindPopup(
                    `<div style="font-family: 'Inter', sans-serif; color: #1e293b;">` +
                    `<strong>${event.severity} Attack</strong><br>` +
                    `${event.description}<br>` +
                    `<small>Source IP: ${event.ip}</small><br>` +
                    `<small>Location: ${event.city}, ${event.country}</small>` +
                    `</div>`
                );
                markersById[event.id] = marker;
            }
        });
    }

    function populateEventList(list) {
        const container = document.getElementById('eventList');
        container.innerHTML = '';
        
        if (!list || list.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#94a3b8;">No events to display for this selection.</p>';
            return;
        }

        list.forEach(event => {
            const item = document.createElement('div');
            item.className = 'event-item';
            const severityColor = severityMap[event.severity] || '#777';
            item.style.borderLeft = `4px solid ${severityColor}`;
            item.setAttribute('data-log-id', event.id);
            
            const details = document.createElement('div');
            details.className = 'details';
            const strong = document.createElement('strong');
            strong.textContent = event.description;
            const loc = document.createElement('span');
            loc.className = 'location';
            let locHTML = `Source: ${event.ip} `;
            if (event.type === 'EXTERNAL') {
                locHTML += `<span style="color:#94a3b8;"> (From: ${event.city || 'Unknown'})</span> <span class="target">| Target: ${event.target_device}</span>`;
            } else {
                locHTML += `<span class="target">| Target: ${event.target_device} (Local)</span>`;
            }
            loc.innerHTML = locHTML;
            details.appendChild(strong);
            details.appendChild(loc);
            const tag = document.createElement('span');
            tag.className = 'severity-tag';
            tag.style.backgroundColor = severityColor;
            tag.textContent = event.severity;
            item.appendChild(details);
            item.appendChild(tag);

            // Click listener opens the Details Panel
            item.addEventListener('click', () => {
                showDetailsPanel(event);
            });

            container.appendChild(item);
        });
    }

    // --- 5. PANEL MANAGEMENT & TRACE LOGIC ---

    function showDetailsPanel(event) {
        currentEventForTrace = event;
        eventListContainer.style.display = 'none';
        detailsPanelContainer.style.display = 'flex';
        detailsDescription.textContent = event.description;
        detailsSourceIp.textContent = `Source: ${event.ip} | Target: ${event.target_device}`;
        detailsRawLogs.textContent = (event.raw_logs || []).join('\n');
        
        // Reset trace button
        traceRouteButton.disabled = false;
        traceRouteButton.textContent = 'Trace Attacker\'s Route';
        traceStatus.textContent = '';
        traceHopList.innerHTML = '';
        
        if (event.type !== 'EXTERNAL') {
            traceRouteButton.disabled = true;
            traceStatus.textContent = 'Cannot trace internal IPs.';
        }

        clearTraceRoute();
        const m = markersById[event.id];
        if (m) {
            map.flyTo(m.getLatLng(), 5, {duration: 0.8});
            m.openPopup();
        }
    }

    function showEventListPanel() {
        detailsPanelContainer.style.display = 'none';
        eventListContainer.style.display = 'flex';
        clearTraceRoute();
        currentEventForTrace = null;
    }
    
    async function handleTraceRoute() {
        if (!currentEventForTrace) return;

        const ipToTrace = currentEventForTrace.ip;
        console.log(`[DEBUG] Starting trace for: ${ipToTrace}`);
        traceRouteButton.disabled = true;
        clearTraceRoute();
        traceHopList.innerHTML = '';

        // SIMULATION LOGIC
        if (currentEventForTrace.simulated && currentEventForTrace.simulated_hops && currentEventForTrace.simulated_hops.length > 0) {
            console.log('[DEBUG] This is a SIMULATED event. Using pre-canned hops.');
            traceStatus.textContent = 'Simulating trace...';
            
            drawTracePath(currentEventForTrace.simulated_hops);
            
            traceStatus.textContent = `Simulated trace for ${currentEventForTrace.simulated_hops.length} hops.`;
            traceRouteButton.textContent = 'Trace Complete';
            return;
        }

        // REAL TRACE LOGIC
        console.log('[DEBUG] This is a REAL event. Calling server API...');
        traceStatus.textContent = 'Tracing... (this may take a minute)';

        try {
            const response = await fetch(`index.php?action=trace&ip=${ipToTrace}`);
            if (!response.ok) throw new Error(`Server error: ${response.status}`);
            
            const data = await response.json();
            console.log('[DEBUG] Received trace data:', data);

            if (data.error) throw new Error(data.error);

            if (data.hops && data.hops.length > 0) {
                drawTracePath(data.hops);
                traceStatus.textContent = `Trace complete. Found ${data.hops.length} public hops.`;
                traceRouteButton.textContent = 'Trace Complete';
            } else {
                traceStatus.textContent = 'Trace complete, but no public hops were found.';
                traceRouteButton.textContent = 'No Hops Found';
            }

        } catch (error) {
            console.error('Traceroute failed:', error);
            traceStatus.textContent = `Error: ${error.message}`;
            traceRouteButton.disabled = false;
            traceRouteButton.textContent = 'Trace Attacker\'s Route';
        }
    }

    /**
     * Clears trace data AND resets animation
     */
    function clearTraceRoute() {
        currentTraceHops = [];
        traceLayer.clearLayers();
        if (traceHopList) {
            traceHopList.innerHTML = '';
        }
        currentHopToAnimate = -1;
        hopAnimationTimer = 0;
    }

    /**
     * --- NEW: Draws a SINGLE hop marker on the map ---
     * This is called by the animation loop timer.
     */
    function drawHopMarker(hopIndex) {
        let hop, isFirst, isHome, popupText;
        
        // Determine which hop to draw
        if (hopIndex === 0) {
            // This is the first hop (the attacker)
            hop = currentTraceHops[0];
            isFirst = true;
            isHome = false;
            popupText = `<strong>Hop 1 (Attacker)</strong><br><small>IP: ${hop.ip}</small><br><small>Location: ${hop.city || 'Unknown'}, ${hop.country || 'Unknown'}</small>`;
        
        } else if (hopIndex < currentTraceHops.length - 1) {
            // This is a middle hop
            hop = currentTraceHops[hopIndex];
            isFirst = false;
            isHome = false;
            popupText = `<strong>Hop ${hopIndex + 1}</strong><br><small>IP: ${hop.ip}</small><br><small>Location: ${hop.city || 'Unknown'}, ${hop.country || 'Unknown'}</small>`;
        
        } else if (hopIndex === currentTraceHops.length - 1) {
            // This is the LAST hop (our home)
            hop = currentTraceHops[hopIndex]; // This is the homeLocation object
            isFirst = false;
            isHome = true;
            popupText = `<strong>Server Location (Hop ${hopIndex + 1})</strong><br><small>Public IP: ${hop.ip}</small><br><small>Location: ${hop.city}, ${hop.country}</small>`;
        } else {
            return; // Invalid index
        }

        // Draw the marker
        const marker = L.circleMarker([hop.lat, hop.lon], {
            radius: isFirst || isHome ? 7 : 4,
            fillColor: isFirst ? '#ef4444' : (isHome ? '#38bdf8' : '#0e7490'),
            color: '#fff',
            weight: isFirst || isHome ? 2 : 1,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(traceLayer).bindPopup(
            `<div style="font-family: 'Inter', sans-serif; color: #1e293b;">${popupText}</div>`
        );
        
        // Automatically open the popup for the new hop
        marker.openPopup();
    }


    /**
     * --- MODIFIED: Populates data and STARTS the hop animation ---
     * This function NO LONGER draws markers itself.
     */
    function drawTracePath(hops) {
        clearTraceRoute();
        
        // Add our home location to the list of hops for the animation
        const allHopsForAnim = [
            ...hops,
            { 
                ip: homeLocation.ip, 
                lat: homeLocation.lat, 
                lon: homeLocation.lon, 
                city: homeLocation.city, 
                country: homeLocation.country,
                isHome: true
            }
        ];
        currentTraceHops = allHopsForAnim;

        // Populate the TEXT list in the side panel
        hops.forEach((hop, index) => {
            const hopDiv = document.createElement('div');
            hopDiv.innerHTML = `
                <strong>Hop ${index + 1}:</strong> 
                <span class="hop-ip">${hop.ip}</span><br>
                <span class="hop-location">${hop.city || 'Unknown'}, ${hop.country || 'Unknown'}</span>
            `;
            traceHopList.appendChild(hopDiv);
        });
        const homeDiv = document.createElement('div');
        homeDiv.innerHTML = `
            <strong>Hop ${hops.length + 1}:</strong> 
            <span class="hop-ip">${homeLocation.ip} (Home)</span><br>
            <span class="hop-location">${homeLocation.city}, ${homeLocation.country}</span>
        `;
        traceHopList.appendChild(homeDiv);

        // --- NEW: Kick off the animation ---
        // 1. Draw the VERY FIRST marker (the attacker) immediately.
        drawHopMarker(0); 
        // 2. Set the stepper to start animating from hop 0.
        currentHopToAnimate = 0;
        hopAnimationTimer = 0;
    }

    
    // --- 6. INITIALIZATION & TAB CONTROLS ---
    
    const tabReal = document.getElementById('tabReal');
    const tabSim = document.getElementById('tabSim');

    function setActiveTab(showSim) {
        showEventListPanel();
        if (showSim) {
            tabSim.classList.add('active');
            tabReal.classList.remove('active');
            renderEvents(simEvents);
            populateEventList(simEvents);
        } else {
            tabReal.classList.add('active');
            tabSim.classList.remove('active');
            renderEvents(realEvents);
            populateEventList(realEvents);
        }
    }

    tabReal.addEventListener('click', () => setActiveTab(false));
    tabSim.addEventListener('click', () => setActiveTab(true));
    
    traceRouteButton.addEventListener('click', handleTraceRoute);
    backToEventsButton.addEventListener('click', showEventListPanel);

    map.on('zoom move', animate);
    map.on('resize', resizeCanvas);
    
    // Initial setup
    resizeCanvas();
    setActiveTab(false);
    animate();
});

