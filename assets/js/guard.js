/* Guard mobile app: duty check-in/out with selfie, live GPS tracking with offline queue, SOS,
   extra-hours requests, shift handover and attendance history. */
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
    let gpsState = 'unknown';    // ok | off | denied — reported to the control room
    let lastFixAt = 0;

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
        btn.innerHTML = on ? '<i class="bi bi-stop-circle-fill"></i> <span>End Duty</span>'
            : '<i class="bi ' + (state.settings.require_selfie ? 'bi-camera-fill' : 'bi-play-circle-fill') + '"></i> <span>Start Duty</span>';
        btn.disabled = busy;
        $('dutyActions').classList.toggle('d-none', !on);
        $('shiftInfo').classList.toggle('d-none', !on);
        renderExtension();
        renderHandIn();
        $('offBanner').classList.toggle('d-none', !state.today_off || on);
        $('offBannerText').textContent = (state.today_off || '') + ' You can still start duty if your supervisor asked you to.';
        renderLeaves();
        $('trackInfo').classList.toggle('d-none', !(on || state.sos));
        $('outsideWarn').classList.toggle('d-none', !(on && state.outside));

        $('sosActive').classList.toggle('d-none', !state.sos);
        $('sosCard').classList.toggle('d-none', !!state.sos);
        if (state.sos) {
            $('sosStatusText').textContent = state.sos.status === 'acknowledged'
                ? 'Control room has seen your SOS. Help is on the way. Stay where you are if it is safe.'
                : 'Sent ' + state.sos.raised + '. Waiting for the control room to respond…';
        }

        $('monthStats').textContent = state.month.shifts + ' shifts · ' + state.month.hours + ' h' + (state.month.ot_hours ? ' · OT ' + state.month.ot_hours + ' h' : '');
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
        renderShiftInfo();
    }

    function fmtMin(mins) { mins = Math.max(0, Math.floor(mins)); return Math.floor(mins / 60) + 'h ' + String(mins % 60).padStart(2, '0') + 'm'; }

    /** Shift end, late badge and live overtime counter under the duty timer. */
    function renderShiftInfo() {
        const sh = state && state.shift;
        if (!sh) return;
        const parts = [];
        if (sh.late_minutes) parts.push('<span class="g-tag late"><i class="bi bi-alarm"></i> Late ' + fmtMin(sh.late_minutes) + '</span>');
        if (sh.end_ts) {
            parts.push('<span class="g-tag"><i class="bi bi-flag"></i> Shift ends ' + esc(sh.end_label) + (sh.ext_end_label ? ' → ' + esc(sh.ext_end_label) : '') + '</span>');
            const otMin = (Date.now() / 1000 + clockOffset - sh.end_ts) / 60;
            if (otMin >= 1) {
                const ok = Math.min(otMin, sh.approved_ext);
                parts.push('<span class="g-tag ot"><i class="bi bi-hourglass-split"></i> Overtime ' + fmtMin(otMin) +
                    (otMin > sh.approved_ext + 0.5 ? (ok >= 1 ? ' (' + fmtMin(otMin - ok) + ' not approved)' : ' · not approved') : ' · approved') + '</span>');
            }
        }
        const html = parts.join('');
        if ($('shiftInfo').innerHTML !== html) $('shiftInfo').innerHTML = html;
    }

    function renderExtension() {
        const x = state.extension;
        const box = $('extBox');
        if (!state.shift || !x || x.status === 'cancelled') { box.innerHTML = ''; return; }
        const hrs = (m) => (m % 60 ? fmtMin(m) : (m / 60) + ' hr' + (m > 60 ? 's' : ''));
        if (x.status === 'pending') {
            box.innerHTML = '<div class="g-ext pending"><i class="bi bi-hourglass-split"></i><div class="flex-fill text-start"><strong>Extra ' + hrs(x.minutes) + ' requested</strong>' +
                '<div class="small">Waiting for the control room · sent ' + esc(x.requested) + '</div></div>' +
                '<button class="btn btn-sm btn-light" id="extCancel">Cancel</button></div>';
            $('extCancel').addEventListener('click', async () => {
                try { state = await api('api/guard/extension.php', { action: 'cancel' }); toast(state.message); render(); } catch (e) { toast(e.message, 5000); }
            });
        } else if (x.status === 'approved') {
            box.innerHTML = '<div class="g-ext ok"><i class="bi bi-check-circle-fill"></i><div class="text-start"><strong>Extra ' + hrs(x.approved_minutes || x.minutes) + ' approved</strong>' +
                (x.note ? '<div class="small">' + esc(x.note) + '</div>' : '') + '</div></div>';
        } else if (x.status === 'rejected') {
            box.innerHTML = '<div class="g-ext no"><i class="bi bi-x-circle-fill"></i><div class="text-start"><strong>Extra hours not approved</strong>' +
                (x.note ? '<div class="small">' + esc(x.note) + '</div>' : '<div class="small">End your duty at shift time.</div>') + '</div></div>';
        }
        $('extBtn').disabled = x.status === 'pending';
    }

    function renderHandIn() {
        const list = state.handover_in || [];
        $('handInBox').innerHTML = list.map((h) =>
            '<section class="g-card g-handin"><div class="g-label"><i class="bi bi-arrow-left-right"></i> Duty handed over to you</div>' +
            '<div class="fw-bold">' + esc(h.from) + ' <span class="text-muted fw-normal small">' + esc(h.from_code) + '</span></div>' +
            '<div class="small text-muted mb-2">' + esc(h.site || 'No site') + ' · ' + esc(h.at) + '</div>' +
            (h.items ? '<div class="small"><strong>Items:</strong> ' + esc(h.items) + '</div>' : '') +
            (h.notes ? '<div class="g-note mt-2">' + esc(h.notes) + '</div>' : '') +
            '<div class="d-flex gap-2 mt-3"><button class="btn btn-success flex-fill fw-semibold" data-hand-accept="' + h.id + '"><i class="bi bi-check2-circle"></i> Accept</button>' +
            '<button class="btn btn-outline-danger flex-fill" data-hand-dispute="' + h.id + '"><i class="bi bi-exclamation-triangle"></i> Report problem</button></div></section>').join('');
    }
    document.addEventListener('click', async (ev) => {
        const acc = ev.target.closest('[data-hand-accept]');
        const dis = ev.target.closest('[data-hand-dispute]');
        if (!acc && !dis) return;
        const id = +(acc || dis).dataset[acc ? 'handAccept' : 'handDispute'];
        let note = '';
        if (dis) {
            note = prompt('What is missing or wrong? (e.g. radio not handed over)') || '';
            if (note.trim().length < 3) return;
        }
        try {
            state = await api('api/guard/handover.php', { action: 'respond', id, decision: acc ? 'accept' : 'dispute', note });
            toast(state.message); render();
        } catch (e) { toast(e.message, 5000); }
    });
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
        if (err.code === 1) gpsState = 'denied';
        else if (err.code === 2) gpsState = 'off';
        else if (Date.now() - lastFixAt > 120000) gpsState = 'off';   // no fix for 2 minutes
        if (err.code === 1) $('dutyBtn').disabled = !(state && state.shift);
    }

    function onFix(p) {
        $('gpsBanner').classList.add('d-none');
        gpsState = 'ok';
        lastFixAt = Date.now();
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
        flush(true);
    }

    /** Sends queued GPS points. With heartbeat=true it also reports "online" + GPS state when there is nothing queued. */
    async function flush(heartbeat) {
        if (sending || (!queue.length && !heartbeat) || !navigator.onLine) {
            $('netBanner').classList.toggle('d-none', navigator.onLine);
            return;
        }
        sending = true;
        try {
            do {
                const batch = queue.slice(0, 60);
                const r = await api('api/guard/location.php', { points: batch, gps: gpsState });
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
            } while (queue.length);
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
    $('dutyBtn').addEventListener('click', () => {
        if (!state || busy) return;
        const ending = !!state.shift;
        if (ending) {
            if (confirm('End your duty now?' + (state.extension && state.extension.status === 'pending' ? ' Your extra-hours request will be cancelled.' : ''))) dutyRequest(true);
        } else if (state.settings.require_selfie) {
            openSelfie();
        } else {
            dutyRequest(false);
        }
    });

    async function dutyRequest(ending, selfie) {
        busy = true; render();
        $('dutyBtn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + (ending ? 'Ending…' : 'Getting your location…');
        try {
            const fix = await freshFix(15000, 15000);
            if (!ending && !fix) throw new Error('Could not get your GPS location. Turn on location and try again.');
            await flush();
            const body = fix ? { lat: fix.lat, lng: fix.lng, accuracy: fix.accuracy, battery: battery ? Math.round(battery.level * 100) : null } : {};
            if (selfie) body.selfie = selfie;
            const r = await api(ending ? 'api/guard/checkout.php' : 'api/guard/checkin.php', body);
            state = r;
            vibrate(120);
            toast(r.message || (ending ? 'Duty ended' : 'Duty started'), 6000);
            return true;
        } catch (e) {
            toast(e.message, 6000);
            return false;
        } finally {
            busy = false;
            render();
        }
    }

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

    // ---------- bottom sheets ----------
    function openSheet(id) { $(id).classList.add('open'); $(id).setAttribute('aria-hidden', 'false'); }
    function closeSheet(id) {
        $(id).classList.remove('open'); $(id).setAttribute('aria-hidden', 'true');
        if (id === 'sheetSelfie') stopCam();
    }
    document.querySelectorAll('.g-sheet').forEach((sh) => {
        sh.addEventListener('click', (e) => { if (e.target === sh || e.target.closest('[data-close]')) closeSheet(sh.id); });
    });

    // ---------- duty selfie ----------
    let camStream = null, selfieData = null;
    function stopCam() { if (camStream) { camStream.getTracks().forEach((t) => t.stop()); camStream = null; } }
    function camMode(shot) {
        $('camVideo').classList.toggle('d-none', shot);
        $('camShot').classList.toggle('d-none', !shot);
        $('camSnap').classList.toggle('d-none', shot);
        $('camUse').classList.toggle('d-none', !shot);
        $('camRetake').classList.toggle('d-none', !shot);
    }
    function camMsg(text) { $('camMsg').textContent = text || ''; $('camMsg').classList.toggle('d-none', !text); }

    async function openSelfie() {
        selfieData = null;
        camMode(false);
        camMsg('Opening camera…');
        openSheet('sheetSelfie');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { camMsg('Tap "Take selfie" to open your camera.'); return; }
        try {
            camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 960 } }, audio: false });
            $('camVideo').srcObject = camStream;
            camMsg('');
        } catch (e) {
            camStream = null;
            camMsg(e && e.name === 'NotAllowedError' ? 'Camera permission is blocked. Tap "Take selfie" to use the phone camera, or allow camera in settings.' : 'Tap "Take selfie" to open your camera.');
        }
    }

    /** Draws an image/video frame into a small JPEG (max 640 px) so it uploads fast on mobile data. */
    function toJpeg(src, w, h) {
        const scale = Math.min(1, 640 / Math.max(w, h));
        const c = document.createElement('canvas');
        c.width = Math.round(w * scale); c.height = Math.round(h * scale);
        c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
        return c.toDataURL('image/jpeg', 0.75);
    }
    function showShot(data) { selfieData = data; $('camShot').src = data; camMode(true); camMsg(''); stopCam(); }

    $('camSnap').addEventListener('click', () => {
        const v = $('camVideo');
        if (camStream && v.videoWidth) { vibrate(40); showShot(toJpeg(v, v.videoWidth, v.videoHeight)); }
        else $('camFile').click();
    });
    $('camFile').addEventListener('change', () => {
        const f = $('camFile').files[0];
        if (!f) return;
        const img = new Image();
        img.onload = () => { showShot(toJpeg(img, img.naturalWidth, img.naturalHeight)); URL.revokeObjectURL(img.src); };
        img.onerror = () => camMsg('Could not read that photo. Try again.');
        img.src = URL.createObjectURL(f);
        $('camFile').value = '';
    });
    $('camRetake').addEventListener('click', openSelfie);
    $('camUse').addEventListener('click', async () => {
        if (!selfieData) return;
        $('camUse').disabled = true;
        $('camUse').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Starting duty…';
        const ok = await dutyRequest(false, selfieData);
        $('camUse').disabled = false;
        $('camUse').innerHTML = '<i class="bi bi-play-circle-fill"></i> Use photo &amp; Start Duty';
        if (ok) { closeSheet('sheetSelfie'); selfieData = null; }
    });

    // ---------- extra hours (duty extension) ----------
    let extMin = 60;
    document.querySelectorAll('#extMinutes [data-min]').forEach((b) => b.addEventListener('click', () => {
        extMin = +b.dataset.min;
        document.querySelectorAll('#extMinutes [data-min]').forEach((x) => x.classList.toggle('active', x === b));
    }));
    $('extBtn').addEventListener('click', () => {
        const sh = state && state.shift;
        $('extShiftText').textContent = sh && sh.end_label ? 'Your shift ends at ' + sh.end_label + (sh.ext_end_label ? ' (already extended to ' + sh.ext_end_label + ')' : '') + '. The control room must approve extra hours.' : 'The control room must approve extra hours.';
        openSheet('sheetExt');
    });
    $('extSend').addEventListener('click', async () => {
        const reason = $('extReason').value.trim();
        if (reason.length < 3) { toast('Please write the reason.'); $('extReason').focus(); return; }
        $('extSend').disabled = true;
        try {
            state = await api('api/guard/extension.php', { action: 'request', minutes: extMin, reason });
            $('extReason').value = '';
            closeSheet('sheetExt'); toast(state.message, 5000); render();
        } catch (e) { toast(e.message, 5000); }
        finally { $('extSend').disabled = false; }
    });

    // ---------- shift handover ----------
    $('handBtn').addEventListener('click', async () => {
        openSheet('sheetHand');
        $('handGuard').innerHTML = '<option value="">Loading…</option>';
        try {
            const d = await api('api/guard/handover.php?action=candidates');
            const opt = (g) => '<option value="' + g.id + '">' + esc(g.name) + ' (' + esc(g.code) + ')' + (g.on_duty ? ' · on duty' : '') + '</option>';
            const same = d.guards.filter((g) => g.same_site), others = d.guards.filter((g) => !g.same_site);
            $('handGuard').innerHTML = '<option value="">— Select guard —</option>' +
                (same.length ? '<optgroup label="Same client">' + same.map(opt).join('') + '</optgroup>' : '') +
                (others.length ? '<optgroup label="' + (same.length ? 'Other guards' : 'All guards') + '">' + others.map(opt).join('') + '</optgroup>' : '');
            $('handItems').innerHTML = d.items.filter((i) => d.armed || i !== 'Weapon & ammunition').map((i, n) =>
                '<label><input type="checkbox" value="' + esc(i) + '" id="hi' + n + '"> ' + esc(i) + '</label>').join('');
        } catch (e) { toast(e.message, 5000); closeSheet('sheetHand'); }
    });
    $('handSend').addEventListener('click', async () => {
        const incoming = +$('handGuard').value;
        if (!incoming) { toast('Select the guard who is taking over.'); return; }
        if (!confirm('Hand over to ' + $('handGuard').selectedOptions[0].textContent + ' and END your duty?')) return;
        $('handSend').disabled = true;
        try {
            const fix = await freshFix(30000, 8000);
            const body = { action: 'create', incoming_guard_id: incoming, notes: $('handNotes').value, other_items: $('handOther').value,
                items: [...document.querySelectorAll('#handItems input:checked')].map((i) => i.value), battery: battery ? Math.round(battery.level * 100) : null };
            if (fix) Object.assign(body, { lat: fix.lat, lng: fix.lng, accuracy: fix.accuracy });
            await flush();
            state = await api('api/guard/handover.php', body);
            $('handNotes').value = ''; $('handOther').value = '';
            closeSheet('sheetHand'); vibrate(120); toast(state.message, 6000); render();
        } catch (e) { toast(e.message, 5000); }
        finally { $('handSend').disabled = false; }
    });

    // ---------- leave ----------
    const LEAVE_STATUS = { pending: ['Waiting for approval', 'pending'], approved: ['Approved', 'ok'], rejected: ['Not approved', 'no'], cancelled: ['Cancelled', 'none'] };
    function renderLeaves() {
        $('weeklyOffText').textContent = state.weekly_off;
        const list = state.leaves || [];
        $('leaveList').innerHTML = list.length ? list.map((l) => {
            const st = LEAVE_STATUS[l.status] || [l.status, 'none'];
            return '<div class="g-row"><div><div class="fw-semibold">' + esc(l.from) + (l.days > 1 ? ' – ' + esc(l.to) : '') +
                ' <span class="text-muted fw-normal">(' + l.days + ' day' + (l.days > 1 ? 's' : '') + ')</span></div>' +
                '<div class="text-muted">' + esc(l.type) + (l.reason ? ' · ' + esc(l.reason) : '') + (l.by_admin ? ' · added by control room' : '') + '</div>' +
                (l.note ? '<div class="text-muted"><i class="bi bi-chat-left-text"></i> ' + esc(l.note) + '</div>' : '') + '</div>' +
                '<div class="text-end"><span class="g-badge ' + st[1] + '">' + st[0] + '</span>' +
                (l.status === 'pending' ? '<div><button class="btn btn-link btn-sm p-0 mt-1" data-leave-cancel="' + l.id + '">Cancel</button></div>' : '') + '</div></div>';
        }).join('') : '<div class="text-muted">No leave taken recently.</div>';
    }
    let leaveType = 'casual';
    function leaveDaysText() {
        const f = $('leaveFrom').value, t = $('leaveTo').value || f;
        if (!f) { $('leaveDays').textContent = ''; return; }
        const n = Math.round((new Date(t + 'T00:00') - new Date(f + 'T00:00')) / 86400000) + 1;
        $('leaveDays').textContent = n > 0 ? n + ' day' + (n > 1 ? 's' : '') + ' of leave' : 'The last day cannot be before the first day.';
    }
    $('leaveBtn').addEventListener('click', () => {
        const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        $('leaveTypes').innerHTML = Object.entries(state.leave_types).map(([k, v]) => '<button type="button" data-type="' + k + '"' + (k === leaveType ? ' class="active"' : '') + '>' + esc(v.replace(' leave', '')) + '</button>').join('');
        $('leaveTypes').querySelectorAll('[data-type]').forEach((b) => b.addEventListener('click', () => {
            leaveType = b.dataset.type;
            $('leaveTypes').querySelectorAll('[data-type]').forEach((x) => x.classList.toggle('active', x === b));
        }));
        $('leaveFrom').min = today; $('leaveTo').min = today;
        if (!$('leaveFrom').value || $('leaveFrom').value < today) $('leaveFrom').value = today;
        if ($('leaveTo').value && $('leaveTo').value < $('leaveFrom').value) $('leaveTo').value = '';
        leaveDaysText();
        openSheet('sheetLeave');
    });
    $('leaveFrom').addEventListener('change', () => {
        $('leaveTo').min = $('leaveFrom').value;
        if ($('leaveTo').value && $('leaveTo').value < $('leaveFrom').value) $('leaveTo').value = $('leaveFrom').value;
        leaveDaysText();
    });
    $('leaveTo').addEventListener('change', leaveDaysText);
    $('leaveSend').addEventListener('click', async () => {
        const reason = $('leaveReason').value.trim();
        if (reason.length < 3) { toast('Please write the reason.'); $('leaveReason').focus(); return; }
        $('leaveSend').disabled = true;
        try {
            state = await api('api/guard/leave.php', { action: 'request', type: leaveType, from: $('leaveFrom').value, to: $('leaveTo').value || $('leaveFrom').value, reason });
            $('leaveReason').value = ''; $('leaveTo').value = '';
            closeSheet('sheetLeave'); toast(state.message, 5000); render();
            if (!$('viewHistory').classList.contains('d-none')) loadHistory(histMonth);
        } catch (e) { toast(e.message, 5000); }
        finally { $('leaveSend').disabled = false; }
    });
    document.addEventListener('click', async (ev) => {
        const b = ev.target.closest('[data-leave-cancel]');
        if (!b || !confirm('Cancel this leave request?')) return;
        try { state = await api('api/guard/leave.php', { action: 'cancel', id: +b.dataset.leaveCancel }); toast(state.message); render(); } catch (e) { toast(e.message, 5000); }
    });

    // ---------- tabs & attendance history ----------
    let histMonth = null, histData = null, histDay = null;
    function showView(v) {
        $('viewDuty').classList.toggle('d-none', v !== 'duty');
        $('viewHistory').classList.toggle('d-none', v !== 'history');
        document.querySelectorAll('.g-tabs [data-goto]').forEach((b) => b.classList.toggle('active', b.dataset.goto === v));
        window.scrollTo(0, 0);
        if (v === 'history') loadHistory(histMonth);
    }
    document.querySelectorAll('[data-goto]').forEach((b) => b.addEventListener('click', () => showView(b.dataset.goto)));

    async function loadHistory(month) {
        try {
            histData = await api('api/guard/attendance.php' + (month ? '?month=' + month : ''));
            histMonth = histData.month;
            histDay = null;
            renderHistory();
        } catch (e) { $('histDays').innerHTML = '<div class="text-danger">Could not load attendance. Check your internet.</div>'; }
    }
    $('histPrev').addEventListener('click', () => histData && loadHistory(histData.prev));
    $('histNext').addEventListener('click', () => histData && histData.next && loadHistory(histData.next));

    const DAY_LABEL = { present: 'Present', late: 'Late', absent: 'Absent', weekly_off: 'Weekly off', leave: 'Leave', pending: 'Not checked in yet', upcoming: 'Upcoming', none: 'No duty' };
    function renderHistory() {
        const d = histData, s = d.summary;
        $('histLabel').textContent = d.label;
        $('histNext').disabled = !d.next;
        const tile = (n, l, cls) => '<div class="g-stat ' + (cls || '') + '"><strong>' + n + '</strong><span>' + l + '</span></div>';
        $('histStats').innerHTML = tile(s.present, 'Present', 'ok') + tile(s.late, 'Late', 'warn') + tile(s.absent, 'Absent', 'bad') +
            tile(s.leave + s.weekly_off, 'Off / Leave', 'off') + tile((s.minutes / 60).toFixed(1), 'Hours') + tile((s.ot / 60).toFixed(1), 'OT hours', 'ot');
        const lead = d.days[0].dow;
        $('histCal').innerHTML = '<span></span>'.repeat(lead) + d.days.map((x) =>
            '<button class="g-day ' + x.status + (x.date === histDay ? ' sel' : '') + '" data-day="' + x.date + '">' + x.day +
            (x.ot ? '<i class="ot-dot"></i>' : '') + '</button>').join('');
        $('histCal').querySelectorAll('[data-day]').forEach((b) => b.addEventListener('click', () => {
            histDay = histDay === b.dataset.day ? null : b.dataset.day; renderHistory();
        }));
        const list = d.days.filter((x) => histDay ? x.date === histDay : (x.shifts.length || x.status === 'absent' || x.status === 'leave')).reverse();
        $('histDayLabel').textContent = histDay ? new Date(histDay + 'T00:00').toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' }) : 'Daily attendance';
        $('histDays').innerHTML = list.length ? list.map((x) =>
            '<div class="g-hday"><div class="d-flex justify-content-between align-items-center mb-1">' +
            '<strong>' + new Date(x.date + 'T00:00').toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' }) + '</strong>' +
            '<span><span class="g-badge ' + x.status + '">' + esc(x.status === 'leave' ? x.off_label : DAY_LABEL[x.status]) + '</span>' +
            (x.off && x.shifts.length ? ' <span class="g-badge off">Worked on ' + esc(x.off === 'leave' ? 'leave' : 'weekly off') + '</span>' : '') + '</span></div>' +
            (x.shifts.length ? x.shifts.map((sh) => '<div class="g-row"><div><div class="fw-semibold">' + esc(sh.in) + ' – ' + (sh.out ? esc(sh.out) + (sh.out_day ? ' <span class="text-muted">(' + esc(sh.out_day) + ')</span>' : '') : '<span class="text-success">on duty</span>') + '</div>' +
                '<div class="text-muted">' + esc(sh.client || 'No client') + (sh.site ? ' · ' + esc(sh.site) : '') + '</div>' +
                (sh.late ? '<div class="text-warning-emphasis">Late by ' + fmtMin(sh.late) + '</div>' : '') + '</div>' +
                '<div class="text-end"><div class="fw-semibold">' + fmtMin(sh.minutes) + '</div>' +
                (sh.ot ? '<div class="small ' + (sh.ot_approved >= sh.ot ? 'text-success' : 'text-warning-emphasis') + '">OT ' + fmtMin(sh.ot) + (sh.ot_approved < sh.ot ? ' (' + fmtMin(sh.ot - sh.ot_approved) + ' unapproved)' : '') + '</div>' : '') +
                '</div></div>').join('') : '<div class="text-muted">' + (x.status === 'absent' ? 'No check-in on this day.' : x.status === 'leave' ? 'On approved leave.' : x.status === 'weekly_off' ? 'Weekly off.' : 'No shifts.') + '</div>') +
            '</div>').join('') : '<div class="text-muted">No attendance yet this month.</div>';
    }

    // ---------- status refresh ----------
    async function refresh() {
        try {
            const s = await api('api/guard/status.php?gps=' + gpsState);
            const hadShift = state && state.shift;
            const prevExt = state && state.extension;
            const prevHand = state ? state.handover_in.length : 0;
            state = s;
            if (hadShift && !s.shift) toast('Your shift was ended by the control room.', 6000);
            if (prevExt && prevExt.status === 'pending' && s.extension && s.extension.id === prevExt.id && s.extension.status !== 'pending') {
                toast(s.extension.status === 'approved' ? 'Extra hours APPROVED by the control room.' : 'Extra hours request was not approved.', 7000);
                vibrate([200, 100, 200]);
            }
            if (s.handover_in.length > prevHand) { toast('A guard has handed over duty to you. Please check and accept.', 7000); vibrate([200, 100, 200]); }
            render();
        } catch (e) {
            if (!state) $('asgBox').innerHTML = '<div class="text-danger small">Could not load your duty details. Check your internet.</div>';
        }
    }
    setInterval(refresh, 15000);

    // ---------- install / battery / boot ----------
    let deferredPrompt = null;
    const installBtn = $('installBtn');   // optional: only some pages have an install button
    if (installBtn) {
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; installBtn.classList.remove('d-none'); });
        installBtn.addEventListener('click', async () => { if (!deferredPrompt) return; deferredPrompt.prompt(); deferredPrompt = null; installBtn.classList.add('d-none'); });
    }
    if (navigator.getBattery) navigator.getBattery().then((b) => { battery = b; }).catch(() => { });
    if ('serviceWorker' in navigator) navigator.serviceWorker.register(G.sw).catch(() => { });

    startWatch();
    refresh().then(flush);
})();
