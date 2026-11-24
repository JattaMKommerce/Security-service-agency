/* Guard mobile app: duty check-in/out, live GPS tracking with offline queue, SOS. */
(function () {
    'use strict';
    const G = window.GUARD;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const QKEY = 'g_loc_queue';
    const MAX_QUEUE = 2000;

    let state = null;
    let clockOffset = 0;         // server time - device time (seconds)
    let lastFix = null;          // { lat, lng, accuracy, speed, heading, ts }
    let lastQueuedTs = 0;
    let watchId = null;
    let tickTimer = null;
    let wakeLock = null;
    let battery = null;
    let sending = false;
    let lastSentAt = 0;
    let busy = false;

    // ---------- helpers ----------
    function toast(msg, ms = 3500) {
        const t = $('gToast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(toast._t);
        toast._t = setTimeout(() => t.classList.remove('show'), ms);
    }

    function loadQueue() { try { return JSON.parse(localStorage.getItem(QKEY) || '[]'); } catch (e) { return []; } }
    function saveQueue(q) { try { localStorage.setItem(QKEY, JSON.stringify(q.slice(-MAX_QUEUE))); } catch (e) { } }
    let queue = loadQueue();

    async function api(path, body, retried) {
        const opts = { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-Token': G.csrf } };
        if (body !== undefined) {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(G.base + '/' + path, opts);
        if (res.status === 401) { window.location.href = G.login; throw new Error('Signed out'); }
        if (res.status === 419 && !retried) {
            const s = await api('api/guard/status.php');
            G.csrf = s.csrf;
            return api(path, body, true);
        }
        const data = await res.json().catch(() => ({ ok: false, error: 'Unexpected server response' }));
        if (!res.ok || data.ok === false) {
            const err = new Error(data.error || ('Error ' + res.status));
            err.http = res.status;
            throw err;
        }
        if (data.csrf) G.csrf = data.csrf;
        if (data.server_time) clockOffset = data.server_time - Date.now() / 1000;
        return data;
    }

    function fmtDist(m) { return m >= 1000 ? (m / 1000).toFixed(2) + ' km' : Math.round(m) + ' m'; }
    function distance(a, b) {
        const R = 6371000, toR = (d) => d * Math.PI / 180;
        const dLat = toR(b[0] - a[0]), dLng = toR(b[1] - a[1]);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(toR(a[0])) * Math.cos(toR(b[0])) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    // ---------- map & location ----------
    const mapEl = $('gMap');
    const map = (typeof L !== 'undefined' && mapEl)
        ? L.map('gMap', { zoomControl: false, attributionControl: true }).setView([G.mapDefault[0], G.mapDefault[1]], G.mapDefault[2])
        : null;
    if (map) {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    }
    let siteMarker = null, siteCircle = null, meMarker = null, meCircle = null, fittedOnce = false;

    function renderMap() {
        const a = state && state.assignment;
        if (map && a && a.lat !== null) {
            if (!siteMarker) {
                siteMarker = L.marker([a.lat, a.lng]).addTo(map).bindTooltip(esc(a.site_name));
                siteCircle = L.circle([a.lat, a.lng], { radius: a.radius, color: '#1d4ed8', weight: 2, fillOpacity: .08 }).addTo(map);
            } else {
                siteMarker.setLatLng([a.lat, a.lng]);
                siteCircle.setLatLng([a.lat, a.lng]).setRadius(a.radius);
            }
        } else if (map && siteMarker) {
            map.removeLayer(siteMarker); map.removeLayer(siteCircle); siteMarker = siteCircle = null;
        }
        if (lastFix) {
            const ll = [lastFix.lat, lastFix.lng];
            if (map) {
                if (!meMarker) {
                    meMarker = L.circleMarker(ll, { radius: 9, color: '#fff', weight: 3, fillColor: '#16a34a', fillOpacity: 1 }).addTo(map);
                    meCircle = L.circle(ll, { radius: lastFix.accuracy || 0, color: '#16a34a', weight: 1, fillOpacity: .1 }).addTo(map);
                } else {
                    meMarker.setLatLng(ll);
                    meCircle.setLatLng(ll).setRadius(lastFix.accuracy || 0);
                }
                if (!fittedOnce) {
                    fittedOnce = true;
                    if (siteCircle) map.fitBounds(L.latLngBounds([ll]).extend(siteCircle.getBounds()), { padding: [20, 20], maxZoom: 17 });
                    else map.setView(ll, 16);
                }
            }
            if ($('myPos')) {
                $('myPos').textContent = 'GPS Active ±' + Math.round(lastFix.accuracy || 0) + ' m';
            }
            if (a && a.lat !== null && $('myDist')) {
                const d = distance(ll, [a.lat, a.lng]);
                $('myDist').textContent = fmtDist(d) + ' from post';
                $('myDist').className = 'fw-semibold ' + (d > a.radius + Math.min(150, lastFix.accuracy || 0) ? 'text-danger' : 'text-success');
            } else if ($('myDist')) {
                $('myDist').textContent = 'Location Synced';
                $('myDist').className = 'fw-semibold text-success';
            }
        } else if (map && siteMarker && !fittedOnce) {
            map.fitBounds(siteCircle.getBounds(), { padding: [20, 20] });
        }
    }

    // ---------- rendering ----------
    function render() {
        if (!state) return;
        const a = state.assignment;
        $('asgBox').innerHTML = a ? (
            '<div class="g-hired"><i class="bi bi-briefcase-fill"></i> Hired by <strong>' + esc(a.client_name) + '</strong></div>' +
            '<div class="fw-bold fs-5 mt-2">' + esc(a.site_name) + '</div>' +
            (a.site_address ? '<div class="small text-muted">' + esc(a.site_address) + '</div>' : '') +
            '<div class="g-kv mt-2">' +
            (a.shift ? '<div><span>Shift</span><strong>' + esc(a.shift) + '</strong></div>' : '') +
            '<div><span>Period</span><strong>' + esc(a.period) + '</strong></div>' +
            (a.contact_person ? '<div><span>Contact</span><strong>' + esc(a.contact_person) + '</strong></div>' : '') +
            '</div>' +
            (a.notes ? '<div class="g-note mt-2"><i class="bi bi-chat-left-text"></i> ' + esc(a.notes) + '</div>' : '') +
            (a.lat !== null ? '<a class="btn btn-sm btn-outline-primary mt-3" target="_blank" rel="noopener" href="https://www.openstreetmap.org/directions?route=%3B' + a.lat + '%2C' + a.lng + '#map=16/' + a.lat + '/' + a.lng + '"><i class="bi bi-sign-turn-right"></i> Directions</a>' : '') +
            (a.client_phone ? ' <a class="btn btn-sm btn-outline-secondary mt-3" href="tel:' + esc(a.client_phone) + '"><i class="bi bi-telephone"></i> Call client</a>' : '')
        ) : '<div class="text-muted">You are not assigned to any client right now. You can still start duty if your supervisor asked you to.</div>';

        const on = !!state.shift;
        $('dutyState').textContent = on ? 'ON DUTY' : 'OFF DUTY';
        $('dutyState').className = 'g-duty-state ' + (on ? 'on' : 'off');
        $('dutyTimer').classList.toggle('d-none', !on);
        $('dutySince').textContent = on ? 'Since ' + state.shift.check_in_at : '';
        const btn = $('dutyBtn');
        btn.className = 'btn btn-lg w-100 g-duty-btn ' + (on ? 'stop' : 'start');
        btn.innerHTML = on ? '<i class="bi bi-stop-circle-fill"></i> <span>End Duty</span>' : '<i class="bi bi-play-circle-fill"></i> <span>Start Duty</span>';
        btn.disabled = busy;
        $('trackInfo').classList.toggle('d-none', !(on || state.sos));
        $('outsideWarn').classList.toggle('d-none', !(on && state.outside));

        $('sosActive').classList.toggle('d-none', !state.sos);
        $('sosCard').classList.toggle('d-none', !!state.sos);
        if (state.sos) {
            $('sosStatusText').textContent = state.sos.status === 'acknowledged'
                ? 'Control room has seen your SOS. Help is on the way. Stay where you are if it is safe.'
                : 'Sent ' + state.sos.raised + '. Waiting for the control room to respond…';
        }

        $('monthStats').textContent = state.month.shifts + ' shifts · ' + state.month.hours + ' h';
        $('recentList').innerHTML = state.recent.length ? state.recent.map((r) =>
            '<div class="g-row"><div><div class="fw-semibold">' + esc(r.in) + (r.out ? ' – ' + esc(r.out) : '') + '</div><div class="text-muted">' + esc(r.client || 'No client') + '</div></div>' +
            '<div class="fw-semibold ' + (r.status === 'open' ? 'text-success' : '') + '">' + esc(r.duration) + '</div></div>').join('')
            : '<div class="text-muted">No shifts yet.</div>';
        renderMap();
        updateTimer();
        syncTracking();
    }

    function updateTimer() {
        if (!state || !state.shift) return;
        const secs = Math.max(0, Math.floor(Date.now() / 1000 + clockOffset - state.shift.started));
        const h = String(Math.floor(secs / 3600)).padStart(2, '0');
        const m = String(Math.floor((secs % 3600) / 60)).padStart(2, '0');
        const s = String(secs % 60).padStart(2, '0');
        $('dutyTimer').textContent = h + ':' + m + ':' + s;
    }
    setInterval(updateTimer, 1000);

    function updateTrackText() {
        if (!state || !(state.shift || state.sos)) return;
        const pending = queue.length;
        const ago = lastSentAt ? Math.round((Date.now() - lastSentAt) / 1000) + 's ago' : 'not yet';
        $('trackText').textContent = 'Live location ON · last sent ' + ago + (pending > 1 ? ' · ' + pending + ' waiting' : '') +
            (lastFix ? ' · ±' + Math.round(lastFix.accuracy) + ' m' : '') + (battery ? ' · 🔋' + Math.round(battery.level * 100) + '%' : '');
    }
    setInterval(updateTrackText, 2000);

    // ---------- GPS ----------
    function gpsError(err) {
        const msgs = {
            1: 'Location permission denied. Allow location for this site in your browser settings, then reload.',
            2: 'Location unavailable. Turn on GPS / Location on your phone.',
            3: 'Getting your location is taking long. Move to an open area.'
        };
        $('gpsBannerText').textContent = msgs[err.code] || 'Location error: ' + err.message;
        $('gpsBanner').classList.remove('d-none');
        if (err.code === 1) $('dutyBtn').disabled = !(state && state.shift);
    }

    function onFix(p) {
        $('gpsBanner').classList.add('d-none');
        lastFix = {
            lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy,
            speed: p.coords.speed, heading: p.coords.heading, ts: p.timestamp || Date.now()
        };
        if (state && !busy) $('dutyBtn').disabled = false;
        renderMap();
    }

    function startWatch() {
        if (!('geolocation' in navigator)) {
            gpsError({ code: 2, message: 'This browser has no GPS support.' });
            return;
        }
        if (!window.isSecureContext) {
            $('gpsBannerText').textContent = 'GPS needs a secure (https://) connection. Ask the admin to enable SSL.';
            $('gpsBanner').classList.remove('d-none');
            return;
        }
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = navigator.geolocation.watchPosition(onFix, gpsError, { enableHighAccuracy: true, maximumAge: 5000, timeout: 30000 });
    }

    /** A fresh fix (or the latest good one) within the timeout. */
    function freshFix(maxAgeMs = 15000, timeoutMs = 12000) {
        if (lastFix && Date.now() - lastFix.ts < maxAgeMs) return Promise.resolve(lastFix);
        return new Promise((resolve) => {
            if (!('geolocation' in navigator)) return resolve(lastFix);
            navigator.geolocation.getCurrentPosition((p) => { onFix(p); resolve(lastFix); },
                () => resolve(lastFix), { enableHighAccuracy: true, maximumAge: 0, timeout: timeoutMs });
        });
    }

    // ---------- tracking loop ----------
    function intervalMs() { return Math.max(10, (state && state.settings.interval_sec) || 20) * 1000; }

    function syncTracking() {
        const active = state && (state.shift || state.sos);
        if (active && !tickTimer) {
            tick();
            tickTimer = setInterval(tick, intervalMs());
            keepAwake();
        } else if (!active && tickTimer) {
            clearInterval(tickTimer);
            tickTimer = null;
            releaseWake();
        }
    }

    async function tick() {
        if (!state || !(state.shift || state.sos)) return;
        let fix = lastFix;
        if (!fix || Date.now() - fix.ts > intervalMs()) {
            fix = await freshFix(intervalMs(), Math.min(15000, intervalMs()));
        }
        if (fix && fix.ts !== lastQueuedTs) {
            lastQueuedTs = fix.ts;
            queue.push({
                lat: +fix.lat.toFixed(7), lng: +fix.lng.toFixed(7), accuracy: Math.round(fix.accuracy || 0),
                speed: fix.speed, heading: fix.heading, battery: battery ? Math.round(battery.level * 100) : null, ts: fix.ts
            });
            saveQueue(queue);
        }
        flush();
    }

    async function flush() {
        if (sending || !queue.length || !navigator.onLine) {
            $('netBanner').classList.toggle('d-none', navigator.onLine);
            return;
        }
        sending = true;
        try {
            while (queue.length) {
                const batch = queue.slice(0, 60);
                const r = await api('api/guard/location.php', { points: batch });
                queue = queue.slice(batch.length);
                saveQueue(queue);
                lastSentAt = Date.now();
                if (r.geofence && state && state.shift) {
                    const wasOutside = !!state.outside;
                    state.outside = r.geofence.inside === false ? { distance_m: r.geofence.distance_m } : null;
                    $('outsideWarn').classList.toggle('d-none', !state.outside);
                    if (!wasOutside && state.outside) { toast('You left the duty area! The control room has been notified.', 6000); vibrate([300, 150, 300]); }
                    if (wasOutside && !state.outside) toast('Back inside the duty area.');
                }
            }
            $('netBanner').classList.add('d-none');
        } catch (e) {
            if (e.http && e.http < 500 && e.http !== 419 && e.http !== 429) {
                queue = []; saveQueue(queue);       // rejected data: don't retry forever
            } else {
                $('netBanner').classList.remove('d-none');
            }
        } finally {
            sending = false;
            updateTrackText();
        }
    }
    window.addEventListener('online', () => { $('netBanner').classList.add('d-none'); flush(); refresh(); });
    window.addEventListener('offline', () => $('netBanner').classList.remove('d-none'));

    // ---------- screen wake lock ----------
    async function keepAwake() {
        try {
            if ('wakeLock' in navigator && !wakeLock && document.visibilityState === 'visible') {
                wakeLock = await navigator.wakeLock.request('screen');
                wakeLock.addEventListener('release', () => { wakeLock = null; });
            }
        } catch (e) { /* not supported or denied */ }
    }
    function releaseWake() { if (wakeLock) { wakeLock.release().catch(() => { }); wakeLock = null; } }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            startWatch();
            if (state && (state.shift || state.sos)) { keepAwake(); tick(); }
            refresh();
        }
    });

    function vibrate(p) { try { navigator.vibrate && navigator.vibrate(p); } catch (e) { } }

    // ---------- duty button ----------
    $('dutyBtn').addEventListener('click', async () => {
        if (!state || busy) return;
        const ending = !!state.shift;
        if (ending && !confirm('End your duty now?')) return;
        busy = true; render();
        $('dutyBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + (ending ? 'Ending…' : 'Getting your location…');
        try {
            const fix = await freshFix(15000, 15000);
            if (!ending && !fix) throw new Error('Could not get your GPS location. Turn on location and try again.');
            await flush();
            const body = fix ? { lat: fix.lat, lng: fix.lng, accuracy: fix.accuracy, battery: battery ? Math.round(battery.level * 100) : null } : {};
            const r = await api(ending ? 'api/guard/checkout.php' : 'api/guard/checkin.php', body);
            state = r;
            vibrate(120);
            toast(r.message || (ending ? 'Duty ended' : 'Duty started'));
        } catch (e) {
            toast(e.message, 6000);
        } finally {
            busy = false;
            render();
        }
    });

    // ---------- SOS (press & hold) ----------
    const HOLD_MS = 2000;
    const ring = $('sosProgress');
    const CIRC = 2 * Math.PI * 56;
    ring.style.strokeDasharray = CIRC;
    ring.style.strokeDashoffset = CIRC;
    let holdStart = 0, holdRaf = null;

    function playSosAlarmSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            if (ctx.state === 'suspended') ctx.resume();
            let now = ctx.currentTime;
            for (let i = 0; i < 6; i++) {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(800, now);
                osc.frequency.exponentialRampToValueAtTime(1500, now + 0.22);
                gain.gain.setValueAtTime(0.85, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.32);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.34);
                now += 0.38;
            }
        } catch (e) {
            console.warn('SOS alarm audio error:', e);
        }
    }

    function holdFrame() {
        const p = Math.min(1, (Date.now() - holdStart) / HOLD_MS);
        ring.style.strokeDashoffset = CIRC * (1 - p);
        if (p >= 1) { cancelHold(); raiseSos(); return; }
        holdRaf = requestAnimationFrame(holdFrame);
    }
    function startHold(e) { e.preventDefault(); holdStart = Date.now(); vibrate(40); holdRaf = requestAnimationFrame(holdFrame); }
    function cancelHold() { if (holdRaf) cancelAnimationFrame(holdRaf); holdRaf = null; ring.style.strokeDashoffset = CIRC; }
    const sosBtn = $('sosBtn');
    sosBtn.addEventListener('pointerdown', startHold);
    ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) => sosBtn.addEventListener(ev, cancelHold));
    sosBtn.addEventListener('contextmenu', (e) => e.preventDefault());

    async function raiseSos() {
        playSosAlarmSound();
        vibrate([400, 100, 400, 100, 400]);
        toast('Sending SOS…', 8000);
        // Never wait long for GPS in an emergency; location follows with tracking.
        const fix = await freshFix(60000, 5000);
        const body = { action: 'raise', battery: battery ? Math.round(battery.level * 100) : null };
        if (fix) Object.assign(body, { lat: fix.lat, lng: fix.lng, accuracy: fix.accuracy });
        for (let attempt = 0; attempt < 5; attempt++) {
            try {
                state = await api('api/guard/sos.php', body);
                toast('SOS sent. Help is being arranged.', 6000);
                render();
                return;
            } catch (e) {
                if (e.http === 401) return;
                toast('SOS not sent yet (' + e.message + '). Retrying…', 4000);
                await new Promise((r) => setTimeout(r, 3000));
            }
        }
        toast('Could not reach the server. CALL your supervisor now!', 15000);
    }

    $('sosCancel').addEventListener('click', async () => {
        if (!confirm('Are you safe? This will cancel the SOS alert.')) return;
        try {
            const fix = lastFix;
            state = await api('api/guard/sos.php', fix ? { action: 'cancel', lat: fix.lat, lng: fix.lng, accuracy: fix.accuracy } : { action: 'cancel' });
            toast(state.message || 'SOS cancelled');
            render();
        } catch (e) { toast(e.message, 5000); }
    });

    $('logoutForm').addEventListener('submit', (e) => {
        const msg = state && state.shift ? 'Logging out will END your duty and stop location sharing. Continue?' : 'Log out of the guard app?';
        if (!confirm(msg)) e.preventDefault();
        else { try { localStorage.removeItem(QKEY); } catch (x) { } }
    });

    // ---------- status refresh ----------
    async function refresh() {
        try {
            const s = await api('api/guard/status.php');
            const hadShift = state && state.shift;
            state = s;
            if (hadShift && !s.shift) toast('Your shift was ended by the control room.', 6000);
            render();
        } catch (e) {
            if (!state) $('asgBox').innerHTML = '<div class="text-danger small">Could not load your duty details. Check your internet.</div>';
        }
    }
    setInterval(refresh, 15000);

    // ---------- install / battery / boot ----------
    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; $('installBtn').classList.remove('d-none'); });
    $('installBtn').addEventListener('click', async () => { if (!deferredPrompt) return; deferredPrompt.prompt(); deferredPrompt = null; $('installBtn').classList.add('d-none'); });
    if (navigator.getBattery) navigator.getBattery().then((b) => { battery = b; }).catch(() => { });
    if ('serviceWorker' in navigator) navigator.serviceWorker.register(G.sw).catch(() => { });

    startWatch();
    refresh().then(flush);
})();
