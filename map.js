/**
 * Frontend JavaScript Logic: Initializes a Leaflet Map, adds markers,
 * and draws animated CURVED attack lines on an HTML5 Canvas overlay
 * using quadratic Bezier curves.
 */
document.addEventListener('DOMContentLoaded', () => {
    // --- 1. DATA AND VARIABLE SETUP ---

    // Retrieve data injected by PHP
    const siemData = window.siemData || {};
    const homeLocation = siemData.homeLocation;
    const realEvents = siemData.realEvents || [];
    const simEvents = siemData.simEvents || [];
    const severityMap = siemData.severityMap || {};

    // Get map and canvas elements
    const mapContainer = document.getElementById('map');
    const canvas = document.getElementById('attackCanvas');
    const ctx = canvas.getContext('2d');
    
    // Server coordinates
    const homeLat = homeLocation.lat;
    const homeLon = homeLocation.lon;
    const homeCoords = [homeLat, homeLon];
    
    // Global state for animation
    let dashOffset = 0;
    let currentEventsToDraw = []; // Holds the list of events (real or sim)
    
    // Layer and marker tracking
    let eventLayer = null; // Will hold Leaflet markers
    const markersById = {};

    // --- 2. LEAFLET MAP INITIALIZATION ---
    
    // Initialize the Leaflet map
    const map = L.map('map', {
        center: (homeLat !== 0 || homeLon !== 0) ? homeCoords : [20, 0],
        zoom: (homeLat !== 0 || homeLon !== 0) ? 3 : 2
    });

    // Add the dark mode tile layer
    L.tileLayer('https://tiles.stadiamaps.com/tiles/alidade_smooth_dark/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a>, &copy; <a href="https://openmaptiles.org/">OpenMapTiles</a> &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
        maxZoom: 18,
        id: 'stamen-toner-dark'
    }).addTo(map);

    // Create a Layer Group just for markers
    eventLayer = L.layerGroup().addTo(map);

    // Add Home Marker (Server Location)
    function addHomeMarker() {
         if (homeLat !== 0 || homeLon !== 0) {
            L.marker(homeCoords, {
                icon: L.divIcon({
                    className: 'home-marker',
                    html: '<div style="background-color:#38bdf8; width: 15px; height: 15px; border-radius: 50%; border: 3px solid #1e293b; box-shadow: 0 0 5px #38bdf8;"></div>',
                    iconSize: [21, 21],
                    iconAnchor: [10, 10]
                })
            }).addTo(eventLayer).bindPopup(`
                <div style="font-family: 'Inter', sans-serif; text-align: center; color: #1e293b;">
                    <strong>Server Location</strong><br>
                    ${homeLocation.city}, ${homeLocation.country}<br>
                    <small>Public IP: ${homeLocation.ip}</small><br>
                    <small>Private IP: ${homeLocation.private_ip}</small>
                </div>
            `).openPopup();
        }
    }
    
    // --- 3. CANVAS DRAWING LOGIC (From Inspiration File) ---

    // Resizes the canvas to fill the map container
    function resizeCanvas() {
        canvas.width = mapContainer.clientWidth;
        canvas.height = mapContainer.clientHeight;
    }

    /**
     * Draws a single animated quadratic Bezier curve on the canvas.
     * This logic is adapted directly from the inspiration file.
     * @param {object} startPixel - {x, y} pixel coords for start
     * @param {object} endPixel - {x, y} pixel coords for end
     * @param {string} color - The CSS color for the line
     */
    function drawQuadraticCurve(startPixel, endPixel, color) {
        const x1 = startPixel.x;
        const y1 = startPixel.y;
        const x2 = endPixel.x;
        const y2 = endPixel.y;

        // 1. Find midpoint
        const midX = (x1 + x2) / 2;
        const midY = (y1 + y2) / 2;

        // 2. Calculate curve height
        const distance = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
        const curveHeight = Math.min(distance * 0.3, 100); // Bend 30% of distance, max 100px

        // 3. Determine direction and find control point
        const curveDirection = (x2 > x1) ? 1 : -1; // Bend "up" or "down"
        const controlX = midX;
        const controlY = midY - (curveHeight * curveDirection);

        // 4. Draw the line
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.quadraticCurveTo(controlX, controlY, x2, y2); // The core canvas function

        // 5. Style it with the animated dash
        ctx.strokeStyle = color;
        ctx.lineWidth = 2.5;
        ctx.globalAlpha = 0.8;
        ctx.setLineDash([8, 12]);
        ctx.lineDashOffset = dashOffset;
        ctx.stroke();
    }

    /**
     * The main animation loop.
     * This function runs every frame, clears the canvas, and redraws all curves.
     */
    function animate() {
        // Update the animation offset
        dashOffset -= 0.5; // Controls speed of the "marching ants"
        if (dashOffset < -20) dashOffset = 0;

        // Clear the entire canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Loop through the currently active list (real or sim)
        currentEventsToDraw.forEach(event => {
            // Only draw external events that have coordinates
            if (event.type === 'EXTERNAL' && event.lat !== null && event.lon !== null && (event.lat !== 0 || event.lon !== 0)) {
                
                // CRITICAL: Convert lat/lon to (x,y) pixels *every frame*
                // This syncs the canvas drawing with the Leaflet map's zoom/pan
                const attackCoords = [event.lat, event.lon];
                const startPixel = map.latLngToContainerPoint(attackCoords);
                const endPixel = map.latLngToContainerPoint(homeCoords);
                
                const severityColor = severityMap[event.severity] || '#fff';

                // Draw the curve
                drawQuadraticCurve(startPixel, endPixel, severityColor);
            }
        });

        // Request the next frame
        requestAnimationFrame(animate);
    }

    // --- 4. EVENT LIST AND UI LOGIC ---

    // Clears only the Leaflet markers
    function clearLeafletMarkers() {
        eventLayer.clearLayers();
        for (const k in markersById) delete markersById[k];
        addHomeMarker(); // Re-add the home marker
    }

    /**
     * Renders markers on Leaflet and sets the list for the canvas to draw.
     * @param {Array} list - The list of events to display (real or sim)
     */
    function renderEvents(list) {
        clearLeafletMarkers();
        
        // Set this list as the one for the 'animate' loop to draw
        currentEventsToDraw = list;
        if (!list) return;
        
        list.forEach(event => {
            // We ONLY add the circle markers to Leaflet.
            // The canvas 'animate' loop handles drawing the lines.
            if (event.type === 'EXTERNAL' && event.lat !== null && event.lon !== null && (event.lat !== 0 || event.lon !== 0)) {
                const attackCoords = [event.lat, event.lon];
                const severityColor = severityMap[event.severity] || '#fff';

                // --- DELETED L.polyline() and animateLine() ---
                // ... The canvas 'animate' function replaces this ...

                // Add the attacker's circle marker to Leaflet
                const marker = L.circleMarker(attackCoords, {
                    radius: 6,
                    fillColor: severityColor,
                    color: '#fff',
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(eventLayer).bindPopup(`
                    <div style="font-family: 'Inter', sans-serif; color: #1e293b;">
                        <strong>${event.severity} Attack</strong><br>
                        ${event.description}<br>
                        <small>Source IP: ${event.ip}</small><br>
                        <small>Location: ${event.city}, ${event.country}</small>
                    </div>
                `);

                markersById[event.id] = marker;
            }
        });
    }

    // Populates the right-hand list panel (This logic is unchanged)
    function populateEventList(list) {
        const container = document.getElementById('eventList');
        container.innerHTML = ''; // Clear previous list
        
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

            item.addEventListener('click', () => {
                // open modal
                const modal = document.getElementById('logDetailModal');
                document.getElementById('modalDescription').textContent = event.description;
                document.getElementById('modalSourceIp').textContent = event.ip;
                document.getElementById('modalTargetAsset').textContent = event.target_device;
                const severityElement = document.getElementById('modalSeverity');
                severityElement.textContent = event.severity;
                severityElement.style.color = severityMap[event.severity];
                document.getElementById('modalRawLogs').textContent = (event.raw_logs || []).join('\n');
                modal.style.display = 'block';

                // If marker exists, pan to it and open popup
                const m = markersById[event.id];
                if (m) {
                    map.flyTo(m.getLatLng(), 5, {duration: 0.8});
                    m.openPopup();
                }
            });

            container.appendChild(item);
        });
    }

    // --- 5. MODAL AND TAB CONTROLS (Unchanged) ---
    
    const modal = document.getElementById('logDetailModal');
    const closeBtn = document.querySelector('.close-btn');

    closeBtn.onclick = () => {
        modal.style.display = "none";
    };

    window.onclick = (event) => {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };
    
    const tabReal = document.getElementById('tabReal');
    const tabSim = document.getElementById('tabSim');

    function setActiveTab(showSim) {
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

    // --- 6. INITIALIZATION ---

    // Sync canvas size and drawing with map move/zoom/resize
    map.on('zoom move', animate);
    map.on('resize', resizeCanvas);
    
    // Initial setup
    resizeCanvas();     // Size the canvas
    setActiveTab(false); // Set initial data and render markers
    animate();          // Start the canvas animation loop
});
