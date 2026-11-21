/* Live guard tracking map (dashboard mini map + full tracking page) */
(function () {
    'use strict';
    const APP = window.APP;
    const esc = APP.esc;

    /**
     * cfg: { el, list?, search?, clientFilter?, chips?, refreshMs?, query?, focusGuard? }
     */
    APP.liveMap = function (cfg) {
        const map = APP.map.create(cfg.el);
        APP.map.legend(map);
        const cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45, spiderfyOnMaxZoom: true });
        const urgentLayer = L.layerGroup();   // SOS / outside guards are never hidden inside clusters
        const siteLayer = L.layerGroup();
        const trailLayer = L.layerGroup();
        map.addLayer(cluster); map.addLayer(urgentLayer); map.addLayer(siteLayer); map.addLayer(trailLayer);

        const markers = new Map();   // guard id -> { marker, layer }
        const sites = new Map();     // assignment key -> { marker, circle }
        let guards = [];
        let filterStatus = 'all';
        let fitted = false;
        let selectedId = cfg.focusGuard || null;

        function popupHtml(g) {
            const s = APP.map.STATUS[g.status] || APP.map.STATUS.available;
            const kv = (k, v) => v === null || v === undefined || v === '' ? '' : '<div class="row-kv"><span>' + k + '</span><strong>' + v + '</strong></div>';
            return '<div class="map-popup">' +
                '<div class="d-flex align-items-center gap-2 mb-2">' +
                (g.photo ? '<img src="' + esc(g.photo) + '" class="avatar avatar-md">' : '<span class="avatar avatar-md avatar-initials">' + esc(APP.map.initialsOf(g.name)) + '</span>') +
                '<div><div class="t">' + esc(g.name) + '</div><div class="small text-muted">' + esc(g.code) + (g.armed ? ' · <i class="bi bi-shield-fill-check"></i> Armed' : '') + '</div></div></div>' +
                '<div class="mb-2"><span class="badge" style="background:' + s.color + '">' + esc(g.status_label) + '</span></div>' +
                (g.client_name ? '<div class="hired-by mb-2"><i class="bi bi-briefcase-fill"></i> Hired by ' + esc(g.client_name) + '</div>' : '<div class="small text-muted mb-2">Not hired — available</div>') +
                kv('Site', g.site_name ? esc(g.site_name) : null) +
                kv('Shift', g.shift ? esc(g.shift) : null) +
                kv('From site', g.distance_m !== null && g.on_duty ? (g.distance_m >= 1000 ? (g.distance_m / 1000).toFixed(2) + ' km' : g.distance_m + ' m') : null) +
                kv('Last update', g.last_seen ? esc(g.last_seen_ago) : 'never') +
                kv('GPS accuracy', g.accuracy !== null ? '±' + g.accuracy + ' m' : null) +
                kv('Speed', g.speed_kmh !== null ? g.speed_kmh + ' km/h' : null) +
                kv('Battery', g.battery !== null ? g.battery + '%' : null) +
                kv('Phone', '<a href="tel:' + esc(g.phone) + '">' + esc(g.phone) + '</a>') +
                '<div class="d-flex gap-2 mt-2">' +
                '<a class="btn btn-sm btn-primary text-white" href="' + APP.url('admin/guard_view.php?id=' + g.id) + '">Profile</a>' +
                '<button class="btn btn-sm btn-outline-secondary js-trail" data-id="' + g.id + '">Today\'s route</button>' +
                '</div></div>';
        }

        function matches(g) {
            if (filterStatus !== 'all' && g.status !== filterStatus) return false;
            const term = (cfg.search ? document.querySelector(cfg.search).value : '').trim().toLowerCase();
            if (term && !(g.name + ' ' + g.code + ' ' + (g.client_name || '') + ' ' + (g.site_name || '') + ' ' + g.phone).toLowerCase().includes(term)) return false;
            return true;
        }

        function render() {
            const seen = new Set();
            const bounds = [];
            guards.forEach((g) => {
                if (g.lat === null || g.lng === null || !matches(g)) return;
                seen.add(g.id);
                bounds.push([g.lat, g.lng]);
                const urgent = g.status === 'sos' || g.status === 'outside';
                const layer = urgent ? urgentLayer : cluster;
                let m = markers.get(g.id);
                if (m && m.layer !== layer) { m.layer.removeLayer(m.marker); m = null; markers.delete(g.id); }
                if (!m) {
                    const marker = L.marker([g.lat, g.lng], { icon: APP.map.guardIcon(g.status, g.name), zIndexOffset: urgent ? 1000 : 0 });
                    marker.bindPopup(popupHtml(g), { maxWidth: 300 });
                    marker.on('popupopen', () => { selectedId = g.id; highlightList(); });
                    layer.addLayer(marker);
                    m = { marker, layer, status: g.status };
                    markers.set(g.id, m);
                } else {
                    m.marker.setLatLng([g.lat, g.lng]);
                    if (m.status !== g.status) { m.marker.setIcon(APP.map.guardIcon(g.status, g.name)); m.status = g.status; }
                    m.marker.setPopupContent(popupHtml(g));
                }
            });
            markers.forEach((m, id) => { if (!seen.has(id)) { m.layer.removeLayer(m.marker); markers.delete(id); } });

            // Client sites with geofence circles (one per site)
            const siteSeen = new Set();
            guards.forEach((g) => {
                if (g.site_lat === null || !g.client_id || !matches(g)) return;
                const key = g.site_lat.toFixed(5) + ',' + g.site_lng.toFixed(5);
                siteSeen.add(key);
                if (!sites.has(key)) {
                    const marker = L.marker([g.site_lat, g.site_lng], { icon: APP.map.siteIcon(), zIndexOffset: -500 })
                        .bindTooltip(esc(g.site_name) + ' — ' + esc(g.client_name));
                    const circle = L.circle([g.site_lat, g.site_lng], { radius: g.radius || 300, color: '#1d4ed8', weight: 1.5, dashArray: '4 4', fillOpacity: 0.05 });
                    siteLayer.addLayer(marker); siteLayer.addLayer(circle);
                    sites.set(key, { marker, circle });
                }
            });
            sites.forEach((s, key) => { if (!siteSeen.has(key)) { siteLayer.removeLayer(s.marker); siteLayer.removeLayer(s.circle); sites.delete(key); } });

            if (!fitted && bounds.length) {
                fitted = true;
                if (selectedId && markers.has(selectedId)) {
                    map.setView(markers.get(selectedId).marker.getLatLng(), 16);
                } else {
                    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                }
            }
            renderList();
        }

        const SHORT = { sos: 'SOS', outside: 'Outside', on_duty: 'Live', offline_duty: 'No signal', assigned: 'Off duty', available: 'Available', unavailable: 'On leave' };

        function renderList() {
            if (!cfg.list) return;
            const box = document.querySelector(cfg.list);
            const order = { sos: 0, outside: 1, offline_duty: 2, on_duty: 3, assigned: 4, available: 5, unavailable: 6 };
            const list = guards.filter(matches).sort((a, b) => (order[a.status] - order[b.status]) || a.name.localeCompare(b.name));
            if (!list.length) { box.innerHTML = '<div class="empty-state"><i class="bi bi-search"></i>No guards match</div>'; return; }
            box.innerHTML = list.map((g) => {
                const s = APP.map.STATUS[g.status] || APP.map.STATUS.available;
                return '<div class="tracking-item' + (g.id === selectedId ? ' active' : '') + '" data-id="' + g.id + '">' +
                    '<span class="status-dot" style="background:' + s.color + ';width:11px;height:11px"></span>' +
                    '<div class="min-w-0 flex-fill"><div class="fw-semibold text-truncate">' + esc(g.name) + ' <span class="text-muted fw-normal small">' + esc(g.code) + '</span></div>' +
                    '<div class="meta text-truncate">' + (g.client_name ? '<i class="bi bi-briefcase"></i> ' + esc(g.client_name) : 'Available') +
                    ' · ' + (g.lat === null ? 'no location yet' : esc(g.last_seen_ago)) + '</div></div>' +
                    '<span class="small text-nowrap fw-semibold" style="color:' + s.color + '">' + esc(SHORT[g.status] || s.label) + '</span></div>';
            }).join('');
            box.querySelectorAll('.tracking-item').forEach((el) => el.addEventListener('click', () => focus(+el.dataset.id)));
        }

        function highlightList() {
            if (!cfg.list) return;
            document.querySelectorAll(cfg.list + ' .tracking-item').forEach((el) => el.classList.toggle('active', +el.dataset.id === selectedId));
        }

        function focus(id) {
            selectedId = id;
            const m = markers.get(id);
            if (!m) {
                const g = guards.find((x) => x.id === id);
                APP.toast(esc(g ? g.name : 'Guard') + ' has not shared a location yet.', 'secondary', 4000);
                highlightList();
                return;
            }
            if (m.layer === cluster) {
                cluster.zoomToShowLayer(m.marker, () => m.marker.openPopup());
            } else {
                map.setView(m.marker.getLatLng(), Math.max(map.getZoom(), 16));
                m.marker.openPopup();
            }
            highlightList();
        }

        async function showTrail(id) {
            trailLayer.clearLayers();
            try {
                const d = await APP.api('api/admin/trail.php?guard_id=' + id);
                if (d.points.length < 2) { APP.toast('Not enough route points recorded today.', 'secondary', 4000); return; }
                const latlngs = d.points.map((p) => [p.lat, p.lng]);
                L.polyline(latlngs, { color: '#7c3aed', weight: 4, opacity: 0.8 }).addTo(trailLayer);
                L.circleMarker(latlngs[0], { radius: 6, color: '#16a34a', fillOpacity: 1 }).bindTooltip('Start ' + d.points[0].time).addTo(trailLayer);
                map.fitBounds(latlngs, { padding: [40, 40] });
                APP.toast('Showing today\'s route (' + d.points.length + ' points). <a href="#" class="text-white fw-semibold js-clear-trail">Clear</a>', 'dark', 6000);
            } catch (e) { APP.toast('Could not load route: ' + esc(e.message), 'danger', 5000); }
        }
        document.addEventListener('click', (ev) => {
            const t = ev.target.closest('.js-trail');
            if (t) { ev.preventDefault(); showTrail(+t.dataset.id); }
            if (ev.target.closest('.js-clear-trail')) { ev.preventDefault(); trailLayer.clearLayers(); }
        });

        async function load() {
            try {
                const qs = [];
                if (cfg.clientFilter) { const v = document.querySelector(cfg.clientFilter).value; if (v) qs.push('client_id=' + encodeURIComponent(v)); }
                if (cfg.query) qs.push(cfg.query);
                const d = await APP.api('api/admin/live.php' + (qs.length ? '?' + qs.join('&') : ''));
                guards = d.guards;
                Object.entries(d.counts).forEach(([k, v]) => document.querySelectorAll('[data-count="' + k + '"]').forEach((el) => { el.textContent = v; }));
                document.querySelectorAll('[data-count="total"]').forEach((el) => { el.textContent = guards.length; });
                document.querySelectorAll('[data-live-updated]').forEach((el) => { el.textContent = new Date().toLocaleTimeString(); });
                render();
            } catch (e) {
                console.warn('Live map refresh failed:', e.message);
            }
        }

        if (cfg.search) document.querySelector(cfg.search).addEventListener('input', render);
        if (cfg.clientFilter) document.querySelector(cfg.clientFilter).addEventListener('change', () => { fitted = false; load(); });
        if (cfg.chips) document.querySelectorAll(cfg.chips + ' [data-filter]').forEach((b) => b.addEventListener('click', () => {
            filterStatus = b.dataset.filter;
            document.querySelectorAll(cfg.chips + ' [data-filter]').forEach((x) => x.classList.toggle('active', x === b));
            render();
        }));
        document.addEventListener('app:new-alerts', load);

        load();
        setInterval(load, cfg.refreshMs || 15000);
        setTimeout(() => map.invalidateSize(), 250);
        return { map, reload: load, focus };
    };
})();
