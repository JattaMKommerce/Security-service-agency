/* Admin portal: shared behaviour (sidebar, confirmations, live alert polling & 2-way emergency siren) */
(function () {
    'use strict';
    const APP = window.APP || {};

    APP.url = (p) => APP.base + '/' + String(p).replace(/^\//, '');
    APP.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    APP.api = async function (path, opts = {}) {
        const init = { credentials: 'same-origin', headers: { 'X-CSRF-Token': APP.csrf, 'Accept': 'application/json' } };
        if (opts.body !== undefined) {
            init.method = 'POST';
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        const res = await fetch(APP.url(path), init);
        if (res.status === 401) { window.location.href = APP.url('login.php'); throw new Error('Logged out'); }
        const data = await res.json().catch(() => ({ ok: false, error: 'Bad response' }));
        if (!res.ok || data.ok === false) throw new Error(data.error || ('HTTP ' + res.status));
        return data;
    };

    APP.toast = function (html, variant = 'dark', delay = 8000) {
        const stack = document.getElementById('toastStack');
        if (!stack || !window.bootstrap) return;
        const el = document.createElement('div');
        el.className = 'toast align-items-center text-bg-' + variant + ' border-0 shadow';
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="d-flex"><div class="toast-body">' + html + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        stack.appendChild(el);
        const t = new bootstrap.Toast(el, { delay, autohide: delay > 0 });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        t.show();
    };

    // ---- Two-Way Audible Emergency Siren Alert ----
    let audioCtx = null;
    function getAudioContext() {
        if (!audioCtx) {
            const AudioC = window.AudioContext || window.webkitAudioContext;
            if (AudioC) audioCtx = new AudioC();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }
        return audioCtx;
    }

    // Pre-unlock audio on user gesture so emergency siren can sound immediately
    function unlockAudio() {
        getAudioContext();
    }
    ['click', 'keydown', 'pointerdown', 'touchstart'].forEach((ev) => {
        document.addEventListener(ev, unlockAudio, { passive: true });
    });

    APP.playSosAlarm = function () {
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            if (ctx.state === 'suspended') ctx.resume();
            const now = ctx.currentTime;
            // 8 rapid sweeping siren pulses (2-way emergency siren)
            for (let i = 0; i < 8; i++) {
                const t = now + (i * 0.35);
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(750, t);
                osc.frequency.exponentialRampToValueAtTime(1600, t + 0.22);
                gain.gain.setValueAtTime(0.9, t);
                gain.gain.exponentialRampToValueAtTime(0.01, t + 0.32);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(t);
                osc.stop(t + 0.34);
            }
        } catch (e) {
            console.warn('SOS alarm audio playback failed:', e);
        }
    };

    APP.beep = function (urgent) {
        if (urgent) {
            APP.playSosAlarm();
            return;
        }
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            if (ctx.state === 'suspended') ctx.resume();
            const t = ctx.currentTime;
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.value = 660;
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.3, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.2);
            o.connect(g).connect(ctx.destination);
            o.start(t);
            o.stop(t + 0.22);
        } catch (e) { }
    };

    // ---- Sidebar (mobile) ----
    const sidebar = document.getElementById('sidebar');
    document.getElementById('sidebarToggle')?.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.getElementById('sidebarBackdrop')?.addEventListener('click', () => sidebar.classList.remove('open'));

    // ---- Confirm dialogs: <form data-confirm="..."> or <button data-confirm="..."> ----
    document.addEventListener('submit', (ev) => {
        const form = ev.target;
        const btn = ev.submitter;
        const msg = (btn && btn.dataset.confirm) || form.dataset.confirm;
        if (msg && !window.confirm(msg)) ev.preventDefault();
    });

    // ---- Live alerts ----
    const KEY = 'sa_last_alert_id';
    const typeMeta = {
        sos: { icon: 'bi-exclamation-octagon-fill', cls: 'tint-red', title: 'SOS EMERGENCY' },
        geofence_exit: { icon: 'bi-sign-turn-right-fill', cls: 'tint-amber', title: 'Left site' },
        late: { icon: 'bi-alarm-fill', cls: 'tint-amber', title: 'Late / not reported' },
        no_movement: { icon: 'bi-pause-circle-fill', cls: 'tint-amber', title: 'Not moving' },
        gps_off: { icon: 'bi-geo-alt', cls: 'tint-red', title: 'GPS turned off' },
        offline: { icon: 'bi-wifi-off', cls: 'tint-red', title: 'No internet / signal' },
        battery_low: { icon: 'bi-battery-half', cls: 'tint-amber', title: 'Battery low' },
        phone_off: { icon: 'bi-power', cls: 'tint-red', title: 'Phone switched off' }
    };
    const EXT_KEY = 'sa_last_ext_id';
    const LEAVE_KEY = 'sa_last_leave_id';

    function renderAlertMenu(list) {
        const box = document.getElementById('alertMenuList');
        if (!box) return;
        if (!list.length) { box.innerHTML = '<div class="p-4 text-center small text-muted"><i class="bi bi-check2-circle fs-4 d-block mb-1"></i>No open alerts</div>'; return; }
        box.innerHTML = list.map((a) => {
            const m = typeMeta[a.type] || typeMeta.sos;
            return '<a class="alert-menu-item' + (a.type === 'sos' ? ' bg-danger-subtle' : '') + '" href="' + APP.url('admin/alerts.php?id=' + a.id) + '">' +
                '<span class="ico ' + m.cls + '"><i class="bi ' + m.icon + '"></i></span>' +
                '<span class="min-w-0"><strong>' + m.title + ' · ' + APP.esc(a.guard_name) + '</strong>' +
                '<span class="d-block small text-muted text-truncate">' + APP.esc(a.client_name || 'No client') + ' · ' + APP.esc(a.ago) + '</span></span></a>';
        }).join('');
    }

    function setCount(n) {
        document.querySelectorAll('.js-alert-count').forEach((el) => {
            el.textContent = n;
            el.classList.toggle('d-none', !n);
        });
    }

    function triggerSosBanner(guardName, alertId) {
        // High visibility audio prompt and response link
        APP.toast(
            '<div class="d-flex align-items-center gap-2">' +
            '<i class="bi bi-exclamation-octagon-fill fs-3 text-warning"></i>' +
            '<div><strong class="d-block fs-6">🚨 SOS FROM ' + APP.esc(guardName).toUpperCase() + '</strong>' +
            '<div class="small">Tap to hear siren & respond immediately!</div></div></div>' +
            '<a class="btn btn-warning btn-sm w-100 mt-2 fw-bold" onclick="APP.playSosAlarm()" href="' + APP.url('admin/alerts.php?id=' + alertId) + '">RESPOND TO SOS →</a>',
            'danger', 0
        );
    }

    let first = true;
    let memLast = null;
    async function poll() {
        try {
            let last = memLast;
            try { const s = sessionStorage.getItem(KEY); if (s !== null) last = parseInt(s, 10) || 0; } catch (e) { }
            let extLast = null;
            try { const s = sessionStorage.getItem(EXT_KEY); if (s !== null) extLast = parseInt(s, 10) || 0; } catch (e) { }
            const qs = [];
            if (last !== null) qs.push('since_id=' + last);
            if (extLast !== null) qs.push('ext_since=' + extLast);
            let leaveLast = null;
            try { const s = sessionStorage.getItem(LEAVE_KEY); if (s !== null) leaveLast = parseInt(s, 10) || 0; } catch (e) { }
            if (leaveLast !== null) qs.push('leave_since=' + leaveLast);
            const data = await APP.api('api/admin/alerts_poll.php' + (qs.length ? '?' + qs.join('&') : ''));
            setCount(data.open_count);
            const reqPending = (data.ext_pending || 0) + (data.leave_pending || 0);
            document.querySelectorAll('.js-ext-count').forEach((el) => { el.textContent = reqPending; el.classList.toggle('d-none', !reqPending); });
            try { sessionStorage.setItem(LEAVE_KEY, String(Math.max(leaveLast || 0, data.leave_max_id || 0))); } catch (e) { }
            if (leaveLast !== null && data.leave_new && data.leave_new.length) {
                data.leave_new.forEach((l) => APP.toast('<strong><i class="bi bi-calendar-x me-1"></i>Leave request: ' + APP.esc(l.guard_name) + '</strong><br>' +
                    APP.esc(l.type) + ' · ' + APP.esc(l.dates) + '<br><a class="text-white fw-semibold" href="' + APP.url('admin/duty.php?tab=leave') + '">Approve / reject →</a>', 'primary', 15000));
                APP.beep(false);
            }
            try { sessionStorage.setItem(EXT_KEY, String(Math.max(extLast || 0, data.ext_max_id || 0))); } catch (e) { }
            if (extLast !== null && data.ext_new && data.ext_new.length) {
                data.ext_new.forEach((x) => APP.toast('<strong><i class="bi bi-hourglass-split me-1"></i>Extra hours request: ' + APP.esc(x.guard_name) + '</strong><br>+' +
                    APP.esc(x.minutes) + ' — ' + APP.esc(x.reason) + '<br><a class="text-white fw-semibold" href="' + APP.url('admin/duty.php?tab=requests') + '">Approve / reject →</a>', 'primary', 15000));
                APP.beep(false);
            }
            renderAlertMenu(data.open);

            const newLast = Math.max(last || 0, data.max_id || 0);
            memLast = newLast;
            try { sessionStorage.setItem(KEY, String(newLast)); } catch (e) { }

            // Check if any open SOS needs alerting (e.g. fresh page load with active SOS)
            const openSos = (data.open || []).filter((a) => a.type === 'sos' && a.status === 'open');
            openSos.forEach((s) => {
                const sKey = 'sa_sos_alerted_' + s.id;
                let alreadyAlerted = false;
                try { alreadyAlerted = sessionStorage.getItem(sKey) === '1'; } catch (e) { }
                if (!alreadyAlerted) {
                    try { sessionStorage.setItem(sKey, '1'); } catch (e) { }
                    APP.playSosAlarm();
                    triggerSosBanner(s.guard_name, s.id);
                    document.getElementById('alertBell')?.classList.add('bell-ring');
                }
            });

            // Process newly arrived alerts from this polling cycle
            if (last !== null && data.new && data.new.length) {
                const hasSos = data.new.some((a) => a.type === 'sos');
                data.new.forEach((a) => {
                    const m = typeMeta[a.type] || typeMeta.sos;
                    if (a.type === 'sos') {
                        triggerSosBanner(a.guard_name, a.id);
                    } else {
                        APP.toast('<strong><i class="bi ' + m.icon + ' me-1"></i>' + m.title + ': ' + APP.esc(a.guard_name) + '</strong><br>' +
                            APP.esc(a.message || '') + '<br><a class="text-white fw-semibold" href="' + APP.url('admin/alerts.php?id=' + a.id) + '">Open alert →</a>',
                            'warning', 10000);
                    }
                });
                APP.beep(hasSos);
                document.getElementById('alertBell')?.classList.add('bell-ring');
                setTimeout(() => document.getElementById('alertBell')?.classList.remove('bell-ring'), 4000);
                document.dispatchEvent(new CustomEvent('app:new-alerts', { detail: data.new }));
            }
        } catch (e) {
            if (!first) console.warn('Alert poll failed', e.message);
        }
        first = false;
    }

    // ---- Jatta Security Android app: push notifications for this admin, printing ----
    if (window.JattaApp && document.getElementById('alertBell')) {
        window.__jattaPushToken = (token) => {
            if (token) APP.api('api/push/register.php', { body: { token, as: 'admin', app_version: window.JattaApp.appVersion() } }).catch(() => { });
        };
        try { window.JattaApp.getPushToken('__jattaPushToken'); } catch (e) { }
        window.print = () => { try { window.JattaApp.printPage(document.title); } catch (e) { } };
    }

    if (document.getElementById('alertBell')) {
        poll();
        // High frequency 4-second poll for rapid emergency detection
        setInterval(poll, 4000);
    }

    window.APP = APP;
})();
