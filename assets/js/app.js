/* Admin portal: shared behaviour (sidebar, confirmations, live alert polling) */
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

    let audioCtx = null;
    APP.beep = function (urgent) {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            const pattern = urgent ? [0, 0.25, 0.5, 0.75] : [0];
            pattern.forEach((t) => {
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.type = 'square';
                o.frequency.value = urgent ? 880 : 660;
                g.gain.setValueAtTime(0.0001, audioCtx.currentTime + t);
                g.gain.exponentialRampToValueAtTime(0.25, audioCtx.currentTime + t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + t + 0.2);
                o.connect(g).connect(audioCtx.destination);
                o.start(audioCtx.currentTime + t);
                o.stop(audioCtx.currentTime + t + 0.22);
            });
        } catch (e) { /* audio not allowed yet */ }
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
        sos: { icon: 'bi-exclamation-octagon-fill', cls: 'tint-red', title: 'SOS' },
        geofence_exit: { icon: 'bi-sign-turn-right-fill', cls: 'tint-amber', title: 'Left site' }
    };

    function renderAlertMenu(list) {
        const box = document.getElementById('alertMenuList');
        if (!box) return;
        if (!list.length) { box.innerHTML = '<div class="p-4 text-center small text-muted"><i class="bi bi-check2-circle fs-4 d-block mb-1"></i>No open alerts</div>'; return; }
        box.innerHTML = list.map((a) => {
            const m = typeMeta[a.type] || typeMeta.sos;
            return '<a class="alert-menu-item" href="' + APP.url('admin/alerts.php?id=' + a.id) + '">' +
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

    let first = true;
    let memLast = null;
    async function poll() {
        try {
            // null = first poll in this browser tab session: only remember the latest id.
            let last = memLast;
            try { const s = sessionStorage.getItem(KEY); if (s !== null) last = parseInt(s, 10) || 0; } catch (e) { }
            const data = await APP.api('api/admin/alerts_poll.php' + (last !== null ? '?since_id=' + last : ''));
            setCount(data.open_count);
            renderAlertMenu(data.open);
            const newLast = Math.max(last || 0, data.max_id || 0);
            memLast = newLast;
            try { sessionStorage.setItem(KEY, String(newLast)); } catch (e) { }
            if (last !== null && data.new.length) {
                const urgent = data.new.some((a) => a.type === 'sos');
                data.new.forEach((a) => {
                    const m = typeMeta[a.type] || typeMeta.sos;
                    APP.toast('<strong><i class="bi ' + m.icon + ' me-1"></i>' + m.title + ': ' + APP.esc(a.guard_name) + '</strong><br>' +
                        APP.esc(a.message || '') + '<br><a class="text-white fw-semibold" href="' + APP.url('admin/alerts.php?id=' + a.id) + '">Open alert →</a>',
                        a.type === 'sos' ? 'danger' : 'warning', a.type === 'sos' ? 0 : 10000);
                });
                APP.beep(urgent);
                document.getElementById('alertBell')?.classList.add('bell-ring');
                setTimeout(() => document.getElementById('alertBell')?.classList.remove('bell-ring'), 3000);
                document.dispatchEvent(new CustomEvent('app:new-alerts', { detail: data.new }));
            }
        } catch (e) {
            if (!first) console.warn('Alert poll failed', e.message);
        }
        first = false;
    }
    if (document.getElementById('alertBell')) {
        poll();
        setInterval(poll, 12000);
    }

    window.APP = APP;
})();
