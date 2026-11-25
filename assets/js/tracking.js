/* Live guard tracking map (dashboard mini map + full tracking page with location history & playback) */
(function () {
    'use strict';
    const APP = window.APP;
    const esc = APP.esc;

    /**
     * cfg: { el, list?, search?, clientFilter?, categoryFilter?, weaponFilter?, chips?, refreshMs?, query?, focusGuard?, history? }
     */
    APP.liveMap = function (cfg) {
        const map = APP.map.create(cfg.el);
        APP.map.legend(map);
        const cluster = L.markerClusterGroup({ showCoverageOnHover: false, maxClusterRadius: 45, spiderfyOnMaxZoom: true });
        const urgentLayer = L.layerGroup();   // emergency / late / problem guards are never hidden inside clusters
        const siteLayer = L.layerGroup();
        const trailLayer = L.layerGroup();
        map.addLayer(cluster); map.addLayer(urgentLayer); map.addLayer(siteLayer); map.addLayer(trailLayer);

        const STATUS = APP.map.STATUS, FLAGS = APP.map.FLAGS;
        const markers = new Map();   // guard id -> { marker, layer, key }
        const sites = new Map();     // site key -> { marker, circle }
        let guards = [];
        let filterStatus = 'all';
        let fitted = false;
        let selectedId = cfg.focusGuard || null;

        const SHARING_ICON = { live: 'bi-broadcast text-success', waiting: 'bi-hourglass text-warning', offline: 'bi-wifi-off text-danger',
                               gps_off: 'bi-geo-alt text-danger', denied: 'bi-slash-circle text-danger', off: 'bi-pause-circle text-muted' };
        const meta = (g) => STATUS[g.status] || STATUS.off_duty;

        function popupHtml(g) {
            const s = meta(g);
            const kv = (k, v) => v === null || v === undefined || v === '' ? '' : '<div class="row-kv"><span>' + k + '</span><strong>' + v + '</strong></div>';
            return '<div class="map-popup">' +
                '<div class="d-flex align-items-center gap-2 mb-2">' +
                (g.photo ? '<img src="' + esc(g.photo) + '" class="avatar avatar-md">' : '<span class="avatar avatar-md avatar-initials">' + esc(APP.map.initialsOf(g.name)) + '</span>') +
                '<div><div class="t">' + esc(g.name) + '</div>' +
                '<div class="small text-muted">' + esc(g.code) + ' · <span class="badge bg-light text-dark border">' + esc(g.category_label || 'Guard') + '</span>' +
                (g.armed ? ' <span class="badge bg-warning text-dark"><i class="bi bi-shield-fill-check"></i> Armed</span>' : '') +
                '</div></div></div>' +
                '<div class="mb-2 d-flex flex-wrap gap-1"><span class="badge" style="background:' + s.color + '">' + esc(g.status_label) + '</span>' +
                (g.flags || []).map((f) => FLAGS[f] ? '<span class="badge bg-light border" style="color:' + FLAGS[f].color + '"><i class="bi ' + FLAGS[f].icon + '"></i> ' + FLAGS[f].label + '</span>' : '').join('') + '</div>' +
                (g.client_name ? '<div class="hired-by mb-2"><i class="bi bi-briefcase-fill"></i> Hired by ' + esc(g.client_name) + '</div>' : '<div class="small text-muted mb-2">Not deployed</div>') +
                kv('Site', g.site_name ? esc(g.site_name) : null) +
                kv('Shift', g.shift ? esc(g.shift) : null) +
                kv('On duty since', g.duty_since ? esc(g.duty_since) : null) +
                kv('Location sharing', '<i class="bi ' + (SHARING_ICON[g.sharing] || '') + '"></i> ' + esc(g.sharing_label)) +
                kv('From site', g.distance_m !== null && g.on_duty ? (g.distance_m >= 1000 ? (g.distance_m / 1000).toFixed(2) + ' km' : g.distance_m + ' m') : null) +
                kv(g.on_duty ? 'Last GPS fix' : 'Last active location', g.last_seen ? esc(g.last_seen_ago) : 'never') +
                kv('GPS accuracy', g.accuracy !== null ? '±' + g.accuracy + ' m' : null) +
                kv('Speed', g.speed_kmh !== null ? g.speed_kmh + ' km/h' : null) +
                kv('Battery', g.battery !== null ? g.battery + '%' : null) +
                kv('Phone', '<a href="tel:' + esc(g.phone) + '">' + esc(g.phone) + '</a>') +
                '<div class="d-flex flex-wrap gap-2 mt-2">' +
                '<a class="btn btn-sm btn-primary text-white" href="' + APP.url('admin/guard_view.php?id=' + g.id) + '">Profile</a>' +
                (g.on_duty ? '<button class="btn btn-sm btn-outline-primary js-trail" data-id="' + g.id + '" data-mode="duty">Duty movement</button>' : '') +
                '<button class="btn btn-sm btn-outline-secondary js-trail" data-id="' + g.id + '">Today\'s route</button>' +
                '</div></div>';
        }

        function matches(g) {
            if (filterStatus === 'issues') { if (!(g.flags && g.flags.length)) return false; }
            else if (filterStatus !== 'all' && g.status !== filterStatus) return false;
            const term = (cfg.search ? document.querySelector(cfg.search).value : '').trim().toLowerCase();
            if (term && !(g.name + ' ' + g.code + ' ' + (g.client_name || '') + ' ' + (g.site_name || '') + ' ' + (g.category_label || '') + ' ' + g.phone).toLowerCase().includes(term)) return false;
            if (cfg.categoryFilter) {
                const cv = document.querySelector(cfg.categoryFilter).value;
                if (cv && g.category !== cv) return false;
            }
            if (cfg.weaponFilter) {
                const wv = document.querySelector(cfg.weaponFilter).value;
                if (wv === 'armed' && !g.armed) return false;
                if (wv === 'unarmed' && g.armed) return false;
            }
            return true;
        }

        function render() {
            const seen = new Set();
            const bounds = [];
            guards.forEach((g) => {
                if (g.lat === null || g.lng === null || !matches(g)) return;
                seen.add(g.id);
                bounds.push([g.lat, g.lng]);
                const urgent = g.status === 'emergency' || g.status === 'late' || (g.flags && g.flags.length > 0);
                const layer = urgent ? urgentLayer : cluster;
                const key = g.status + '|' + (g.flags || []).join(',');
                let m = markers.get(g.id);
                if (m && m.layer !== layer) { m.layer.removeLayer(m.marker); m = null; markers.delete(g.id); }
                if (!m) {
                    const marker = L.marker([g.lat, g.lng], { icon: APP.map.guardIcon(g.status, g.name, g.flags), zIndexOffset: urgent ? 1000 : 0, opacity: g.status === 'off_duty' ? 0.75 : 1 });
                    marker.bindPopup(popupHtml(g), { maxWidth: 300 });
                    marker.on('popupopen', () => { selectedId = g.id; highlightList(); });
                    layer.addLayer(marker);
                    m = { marker, layer, key };
                    markers.set(g.id, m);
                } else {
                    m.marker.setLatLng([g.lat, g.lng]);
                    if (m.key !== key) { m.marker.setIcon(APP.map.guardIcon(g.status, g.name, g.flags)); m.marker.setOpacity(g.status === 'off_duty' ? 0.75 : 1); m.key = key; }
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
            if (history) history.fillGuards(guards);
        }

        const SHORT = { emergency: 'SOS', late: 'Late', patrol: 'Patrol', on_duty: 'On duty', off_duty: 'Off duty' };

        function renderList() {
            if (!cfg.list) return;
            const box = document.querySelector(cfg.list);
            const order = { emergency: 0, late: 1, patrol: 3, on_duty: 3, off_duty: 5 };
            const rank = (g) => order[g.status] - (g.flags && g.flags.length ? 1 : 0);
            const list = guards.filter(matches).sort((a, b) => (rank(a) - rank(b)) || a.name.localeCompare(b.name));
            if (!list.length) { box.innerHTML = '<div class="empty-state"><i class="bi bi-search"></i>No guards match</div>'; return; }
            box.innerHTML = list.map((g) => {
                const s = meta(g);
                const flagIcons = (g.flags || []).map((f) => FLAGS[f] ? '<i class="bi ' + FLAGS[f].icon + ' ms-1" style="color:' + FLAGS[f].color + '" title="' + FLAGS[f].label + '"></i>' : '').join('');
                return '<div class="tracking-item' + (g.id === selectedId ? ' active' : '') + '" data-id="' + g.id + '">' +
                    '<span class="status-dot" style="background:' + s.color + ';width:11px;height:11px"></span>' +
                    '<div class="min-w-0 flex-fill"><div class="fw-semibold text-truncate">' + esc(g.name) +
                    (g.armed ? ' <i class="bi bi-shield-fill-check text-warning" title="Armed"></i>' : '') +
                    ' <span class="text-muted fw-normal small">' + esc(g.code) + '</span></div>' +
                    '<div class="meta text-truncate">' + (g.client_name ? '<i class="bi bi-briefcase"></i> ' + esc(g.client_name) + ' · ' : '') +
                    (g.lat === null ? 'no location yet' : (g.on_duty ? '' : 'last seen ') + esc(g.last_seen_ago)) + '</div>' +
                    '<div class="meta text-truncate"><i class="bi ' + (SHARING_ICON[g.sharing] || '') + '"></i> ' + esc(g.sharing_label) + '</div></div>' +
                    '<span class="small text-nowrap fw-semibold text-end" style="color:' + s.color + '">' + esc(SHORT[g.status] || s.label) + flagIcons + '</span></div>';
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

        // ---------- routes / location history ----------
        let route = null;    // last loaded route data (for playback)
        let playMarker = null;

        function drawRoute(d) {
            trailLayer.clearLayers();
            playMarker = null;
            route = d;
            if (!d.points.length) return false;
            const latlngs = d.points.map((p) => [p.lat, p.lng]);
            L.polyline(latlngs, { color: '#7c3aed', weight: 4, opacity: 0.85 }).addTo(trailLayer);
            const first = d.points[0], last = d.points[d.points.length - 1];
            L.circleMarker(latlngs[0], { radius: 7, color: '#fff', weight: 2, fillColor: '#16a34a', fillOpacity: 1 }).bindTooltip('Start ' + first.time).addTo(trailLayer);
            L.circleMarker(latlngs[latlngs.length - 1], { radius: 7, color: '#fff', weight: 2, fillColor: '#dc2626', fillOpacity: 1 }).bindTooltip('Last ' + last.time).addTo(trailLayer);
            d.stops.forEach((s) => L.marker([s.lat, s.lng], {
                icon: L.divIcon({ className: 'stop-pin', html: '<div>' + (s.minutes >= 60 ? Math.floor(s.minutes / 60) + 'h' : s.minutes + 'm') + '</div>', iconSize: [34, 20], iconAnchor: [17, 10] })
            }).bindTooltip('Stopped ' + s.from + ' – ' + s.to + ' (' + s.minutes + ' min)').addTo(trailLayer));
            d.alerts.forEach((a) => { if (a.lat !== null) L.marker([a.lat, a.lng], {
                icon: L.divIcon({ className: 'alert-pin', html: '<div><i class="bi bi-exclamation-lg"></i></div>', iconSize: [22, 22], iconAnchor: [11, 11] })
            }).bindTooltip(esc(a.title) + ' · ' + a.time).addTo(trailLayer); });
            map.fitBounds(latlngs, { padding: [40, 40], maxZoom: 17 });
            return true;
        }

        async function showTrail(id, mode) {
            try {
                const d = await APP.api('api/admin/trail.php?guard_id=' + id + (mode === 'duty' ? '&mode=duty' : ''));
                if (history) { history.show(id, d); return; }
                if (d.points.length < 2) { APP.toast('Not enough route points recorded' + (mode === 'duty' ? ' during this duty.' : ' today.'), 'secondary', 4000); return; }
                drawRoute(d);
                APP.toast(esc(d.label) + ': ' + d.points.length + ' points, ' + d.distance_km + ' km. <a href="#" class="text-white fw-semibold js-clear-trail">Clear</a>', 'dark', 6000);
            } catch (e) { APP.toast('Could not load route: ' + esc(e.message), 'danger', 5000); }
        }
        document.addEventListener('click', (ev) => {
            const t = ev.target.closest('.js-trail');
            if (t) { ev.preventDefault(); map.closePopup(); showTrail(+t.dataset.id, t.dataset.mode); }
            if (ev.target.closest('.js-clear-trail')) { ev.preventDefault(); trailLayer.clearLayers(); route = null; if (history) history.reset(); }
        });

        /** Location history panel: pick guard + day (or current duty), see stops / distance, replay the movement. */
        const history = cfg.history ? (function () {
            const box = document.querySelector(cfg.history);
            const $h = (sel) => box.querySelector(sel);
            const selG = $h('[data-h="guard"]'), selD = $h('[data-h="date"]'), out = $h('[data-h="result"]'), slider = $h('[data-h="slider"]');
            const playBtn = $h('[data-h="play"]'), clock = $h('[data-h="clock"]'), player = $h('[data-h="player"]');
            let timer = null, filled = '';
            selD.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
            selD.max = selD.value;

            function fillGuards(list) {
                const sig = list.map((g) => g.id + ':' + g.on_duty).join(',');
                if (sig === filled) return;
                filled = sig;
                const cur = selG.value;
                selG.innerHTML = '<option value="">Select guard…</option>' + list.slice().sort((a, b) => a.name.localeCompare(b.name))
                    .map((g) => '<option value="' + g.id + '">' + esc(g.name) + ' (' + esc(g.code) + ')' + (g.on_duty ? ' · on duty' : '') + '</option>').join('');
                selG.value = cur;
            }
            function stop() { if (timer) { clearInterval(timer); timer = null; } playBtn.innerHTML = '<i class="bi bi-play-fill"></i>'; }
            function seek(i) {
                if (!route || !route.points.length) return;
                const p = route.points[i];
                if (!playMarker) playMarker = L.circleMarker([p.lat, p.lng], { radius: 9, color: '#fff', weight: 3, fillColor: '#7c3aed', fillOpacity: 1 }).addTo(trailLayer);
                playMarker.setLatLng([p.lat, p.lng]);
                clock.textContent = p.time + (p.speed_kmh !== null ? ' · ' + p.speed_kmh + ' km/h' : '') + (p.battery !== null ? ' · 🔋' + p.battery + '%' : '');
            }
            function show(id, d) {
                stop();
                selG.value = String(id);
                if (d.date) selD.value = d.date;
                box.classList.add('open');
                const okRoute = d.points.length >= 2 && drawRoute(d);
                out.innerHTML = '<div class="fw-semibold">' + esc(d.guard) + '</div><div class="small text-muted mb-2">' + esc(d.label) + '</div>' +
                    (okRoute ? '<div class="hist-stats"><div><strong>' + d.distance_km + ' km</strong><span>moved</span></div>' +
                        '<div><strong>' + esc(d.first) + '</strong><span>first point</span></div><div><strong>' + esc(d.last) + '</strong><span>last point</span></div>' +
                        '<div><strong>' + d.stops.length + '</strong><span>stops 10m+</span></div></div>' : '<div class="small text-muted">No location points recorded for this period.</div>') +
                    (d.shifts.length ? '<div class="small mt-2"><i class="bi bi-clock"></i> Duty: ' + d.shifts.map((s) => esc(s.in) + ' – ' + (s.out ? esc(s.out) : 'now')).join(', ') + '</div>' : '') +
                    (d.stops.length ? '<div class="small mt-2"><strong>Stops</strong>' + d.stops.map((s, i) => '<div class="hist-stop" data-stop="' + i + '"><i class="bi bi-pause-circle"></i> ' + esc(s.from) + ' – ' + esc(s.to) + ' <span class="text-muted">(' + s.minutes + ' min)</span></div>').join('') + '</div>' : '') +
                    (d.alerts.length ? '<div class="small mt-2"><strong>Alerts</strong>' + d.alerts.map((a) => '<div class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + esc(a.time) + ' · ' + esc(a.title) + '</div>').join('') + '</div>' : '');
                out.querySelectorAll('[data-stop]').forEach((el) => el.addEventListener('click', () => { const s = d.stops[+el.dataset.stop]; map.setView([s.lat, s.lng], 18); }));
                player.classList.toggle('d-none', !okRoute);
                if (okRoute) { slider.max = String(d.points.length - 1); slider.value = '0'; seek(0); }
            }
            async function load(mode) {
                const id = +selG.value;
                if (!id) { APP.toast('Select a guard first.', 'secondary', 3000); return; }
                try {
                    const d = await APP.api('api/admin/trail.php?guard_id=' + id + (mode === 'duty' ? '&mode=duty' : '&date=' + encodeURIComponent(selD.value)));
                    show(id, d);
                } catch (e) { APP.toast(esc(e.message), 'secondary', 5000); }
            }
            $h('[data-h="load"]').addEventListener('click', () => load());
            $h('[data-h="duty"]').addEventListener('click', () => load('duty'));
            $h('[data-h="toggle"]').addEventListener('click', () => box.classList.toggle('open'));
            slider.addEventListener('input', () => { stop(); seek(+slider.value); });
            playBtn.addEventListener('click', () => {
                if (timer) { stop(); return; }
                if (+slider.value >= +slider.max) slider.value = '0';
                playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
                const step = Math.max(1, Math.round(route.points.length / 300));   // whole route in ~30 s
                timer = setInterval(() => {
                    const n = Math.min(+slider.max, +slider.value + step);
                    slider.value = String(n); seek(n);
                    if (n >= +slider.max) stop();
                }, 100);
            });
            return { fillGuards, show, reset() { stop(); out.innerHTML = ''; player.classList.add('d-none'); } };
        })() : null;

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
        if (cfg.categoryFilter) document.querySelector(cfg.categoryFilter).addEventListener('change', render);
        if (cfg.weaponFilter) document.querySelector(cfg.weaponFilter).addEventListener('change', render);
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
