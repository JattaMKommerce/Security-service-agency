/* OpenStreetMap / Leaflet helpers shared by admin pages */
(function () {
    'use strict';
    const APP = window.APP = window.APP || {};

    // Live map colours: green on duty, red emergency, orange late, grey off duty, blue on patrol (moving).
    const STATUS = {
        emergency: { label: 'Emergency (SOS)', color: '#dc2626' },
        late:      { label: 'Late',            color: '#ea580c' },
        patrol:    { label: 'On Patrol',       color: '#2563eb' },
        on_duty:   { label: 'On Duty',         color: '#16a34a' },
        off_duty:  { label: 'Off Duty',        color: '#94a3b8' },
        // older names still used by single-alert maps
        sos:       { label: 'SOS Alert',       color: '#dc2626' },
        outside:   { label: 'Outside Site',    color: '#ea580c' }
    };
    const LEGEND = ['on_duty', 'patrol', 'late', 'emergency', 'off_duty'];
    // Small badge on a marker for problems while on duty.
    const FLAGS = {
        outside:   { icon: 'bi-sign-turn-right-fill', label: 'Outside site',          color: '#ea580c' },
        no_signal: { icon: 'bi-wifi-off',             label: 'No internet / signal',  color: '#dc2626' },
        gps_off:   { icon: 'bi-geo-alt',              label: 'GPS off',               color: '#dc2626' },
        idle:      { icon: 'bi-pause-fill',           label: 'Not moving for long',   color: '#ca8a04' },
        battery_low: { icon: 'bi-battery-half',       label: 'Battery low',           color: '#ca8a04' }
    };

    function create(el, opts = {}) {
        const d = APP.mapDefault || { lat: 20.59, lng: 78.96, zoom: 5 };
        const map = L.map(el, { zoomControl: true, ...opts }).setView([opts.lat ?? d.lat, opts.lng ?? d.lng], opts.zoom ?? d.zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        return map;
    }

    function initialsOf(name) {
        return String(name || '?').trim().split(/\s+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase();
    }

    function guardIcon(status, name, flags) {
        const s = STATUS[status] || STATUS.off_duty;
        const f = (flags || []).map((k) => FLAGS[k]).filter(Boolean)[0];
        return L.divIcon({
            className: 'guard-pin' + (status === 'sos' || status === 'emergency' ? ' pulse' : ''),
            html: '<div class="pin" style="background:' + s.color + '"><span>' + APP.esc(initialsOf(name)) + '</span></div>' +
                (f ? '<span class="pin-flag" style="background:' + f.color + '" title="' + f.label + '"><i class="bi ' + f.icon + '"></i></span>' : ''),
            iconSize: [34, 34], iconAnchor: [17, 34], popupAnchor: [0, -32]
        });
    }

    function siteIcon() {
        return L.divIcon({ className: 'site-pin', html: '<div><i class="bi bi-building"></i></div>', iconSize: [26, 26], iconAnchor: [13, 13] });
    }

    function legend(map) {
        const ctl = L.control({ position: 'bottomleft' });
        ctl.onAdd = function () {
            const div = L.DomUtil.create('div', 'legend');
            div.innerHTML = LEGEND.map((k) =>
                '<div><span class="status-dot" style="background:' + STATUS[k].color + '"></span>' + STATUS[k].label + '</div>').join('') +
                '<div class="legend-flags">' + Object.values(FLAGS).map((f) =>
                '<span title="' + f.label + '"><i class="bi ' + f.icon + '" style="color:' + f.color + '"></i> ' + f.label + '</span>').join('') + '</div>';
            return div;
        };
        ctl.addTo(map);
    }

    /**
     * Location picker for a client site: click / drag / search an address.
     * cfg: { el, lat, lng, radius, search, searchBtn, locateBtn, address }
     */
    function picker(cfg) {
        const latIn = document.querySelector(cfg.lat);
        const lngIn = document.querySelector(cfg.lng);
        const radIn = cfg.radius ? document.querySelector(cfg.radius) : null;
        const hasPoint = latIn.value && lngIn.value;
        const map = create(cfg.el, hasPoint ? { lat: +latIn.value, lng: +lngIn.value, zoom: 16 } : {});
        let marker = null, circle = null;

        function set(lat, lng, pan) {
            latIn.value = (+lat).toFixed(7);
            lngIn.value = (+lng).toFixed(7);
            if (!marker) {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', () => { const p = marker.getLatLng(); set(p.lat, p.lng, false); });
            } else {
                marker.setLatLng([lat, lng]);
            }
            const r = radIn ? Math.max(50, parseInt(radIn.value || '300', 10)) : 0;
            if (r) {
                if (!circle) circle = L.circle([lat, lng], { radius: r, color: '#1d4ed8', weight: 2, fillOpacity: .08 }).addTo(map);
                circle.setLatLng([lat, lng]).setRadius(r);
            }
            if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 16));
        }

        if (hasPoint) set(+latIn.value, +lngIn.value, false);
        map.on('click', (e) => set(e.latlng.lat, e.latlng.lng, false));
        radIn?.addEventListener('input', () => { if (latIn.value) set(+latIn.value, +lngIn.value, false); });
        [latIn, lngIn].forEach((i) => i.addEventListener('change', () => { if (latIn.value && lngIn.value) set(+latIn.value, +lngIn.value, true); }));

        const searchIn = cfg.search ? document.querySelector(cfg.search) : null;
        const results = cfg.results ? document.querySelector(cfg.results) : null;
        async function doSearch() {
            const qv = (searchIn?.value || '').trim();
            if (qv.length < 3) return;
            if (results) results.innerHTML = '<div class="list-group-item small text-muted">Searching…</div>';
            try {
                const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(qv), { headers: { 'Accept-Language': 'en' } });
                const list = await res.json();
                if (!results) { if (list[0]) set(+list[0].lat, +list[0].lon, true); return; }
                results.innerHTML = list.length ? list.map((r, i) =>
                    '<button type="button" class="list-group-item list-group-item-action small" data-i="' + i + '">' + APP.esc(r.display_name) + '</button>').join('')
                    : '<div class="list-group-item small text-muted">No results. Try a nearby landmark, or click on the map.</div>';
                results.querySelectorAll('[data-i]').forEach((b) => b.addEventListener('click', () => {
                    const r = list[+b.dataset.i];
                    set(+r.lat, +r.lon, true);
                    const addr = cfg.address ? document.querySelector(cfg.address) : null;
                    if (addr && !addr.value) addr.value = r.display_name;
                    results.innerHTML = '';
                }));
            } catch (e) {
                if (results) results.innerHTML = '<div class="list-group-item small text-danger">Search failed. Click on the map instead.</div>';
            }
        }
        if (cfg.searchBtn) document.querySelector(cfg.searchBtn)?.addEventListener('click', doSearch);
        searchIn?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });

        if (cfg.locateBtn) document.querySelector(cfg.locateBtn)?.addEventListener('click', () => {
            if (!navigator.geolocation) return alert('Location is not available in this browser.');
            navigator.geolocation.getCurrentPosition((p) => set(p.coords.latitude, p.coords.longitude, true),
                () => alert('Could not get your location. Allow location access, or click on the map.'), { enableHighAccuracy: true, timeout: 10000 });
        });

        setTimeout(() => map.invalidateSize(), 200);
        return { map, set };
    }

    APP.map = { STATUS, FLAGS, create, guardIcon, siteIcon, legend, picker, initialsOf };
})();
