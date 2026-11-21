/* Adds a show/hide "eye" button to every password field on the page. */
(function () {
    'use strict';
    const css = document.createElement('style');
    css.textContent =
        '.pw-wrap{position:relative}' +
        '.pw-wrap>input{padding-right:2.9rem!important}' +
        '.pw-toggle{position:absolute;top:0;right:0;height:100%;width:2.75rem;border:0;background:transparent;' +
        'color:#64748b;display:grid;place-items:center;font-size:1.1rem;cursor:pointer;border-radius:0 10px 10px 0}' +
        '.pw-toggle:hover{color:#1d4ed8}.pw-toggle:focus-visible{outline:2px solid #93c5fd;outline-offset:-2px}' +
        '.g-auth .pw-toggle{color:#cbd5e1}.g-auth .pw-toggle:hover{color:#f5b301}';
    document.head.appendChild(css);

    function attach(input) {
        if (input.dataset.pwToggle) return;
        input.dataset.pwToggle = '1';
        const wrap = document.createElement('div');
        wrap.className = 'pw-wrap';
        // Keep spacing classes (mb-3 etc.) on the wrapper so the icon stays centred.
        [...input.classList].filter((c) => /^m[tbsexy]?-\d$/.test(c)).forEach((c) => {
            input.classList.remove(c);
            wrap.classList.add(c);
        });
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.tabIndex = 0;
        const set = (show) => {
            input.type = show ? 'text' : 'password';
            btn.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.title = show ? 'Hide password' : 'Show password';
        };
        set(false);
        btn.addEventListener('click', () => {
            const show = input.type === 'password';
            set(show);
            input.focus();
            const end = input.value.length;
            try { input.setSelectionRange(end, end); } catch (e) { }
        });
        // Never submit the form with the password visible in the field type.
        input.form?.addEventListener('submit', () => set(false));
        wrap.appendChild(btn);
    }

    function init() { document.querySelectorAll('input[type="password"]').forEach(attach); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
