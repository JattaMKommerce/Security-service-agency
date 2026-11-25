<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$title = 'Install Guard Duty App';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07070a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= e($title) ?> · <?= e(company_name()) ?></title>
    
    <link rel="manifest" href="<?= url('guard/manifest.php') ?>">
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= asset('img/icon-192.png') ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #07070a;
            --bg-card: #0e0f18;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --accent-purple: #8b5cf6;
            --accent-gradient: linear-gradient(135deg, #7c3aed 0%, #9333ea 50%, #a855f7 100%);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }
        
        * { box-sizing: border-box; }
        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: var(--font-main);
            min-height: 100vh;
            margin: 0;
            padding-bottom: calc(2rem + env(safe-area-inset-bottom));
        }

        .ambient-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 500px;
            max-width: 100vw;
            height: 300px;
            background: radial-gradient(ellipse at 50% 0%, rgba(139, 92, 246, 0.25) 0%, rgba(124, 58, 237, 0.08) 50%, transparent 70%);
            filter: blur(50px);
            pointer-events: none;
            z-index: 0;
        }

        .app-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: calc(0.85rem + env(safe-area-inset-top)) 1.25rem 0.85rem;
            background: rgba(7, 7, 10, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-subtle);
        }

        .btn-signin {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-subtle);
            color: #fff;
            padding: 0.4rem 1rem;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
        }

        .install-container {
            position: relative;
            z-index: 1;
            max-width: 440px;
            margin: 0 auto;
            padding: 2rem 1.25rem;
            text-align: center;
        }

        .guard-avatar-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(168, 85, 247, 0.6);
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
            margin-bottom: 1rem;
        }

        .app-title {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }

        .app-sub {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }

        .btn-apk-main {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            background: var(--accent-gradient);
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            padding: 1.1rem;
            border-radius: 14px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.35);
            transition: transform 0.15s, opacity 0.15s;
        }
        .btn-apk-main:active {
            transform: scale(0.98);
            opacity: 0.9;
            color: #fff;
        }

        .btn-pwa-sub {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.85rem;
            border-radius: 12px;
            margin-top: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-pwa-sub:active {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .steps-box {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.25rem;
            margin: 2rem 0 1.5rem;
            text-align: left;
        }

        .step-row {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 1rem;
        }
        .step-row:last-child { margin-bottom: 0; }

        .step-num {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(139, 92, 246, 0.15);
            color: var(--accent-purple);
            font-weight: 700;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .step-text {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .step-text strong {
            color: #fff;
            display: block;
            margin-bottom: 0.15rem;
        }

        .qr-toggle {
            font-size: 0.82rem;
            color: var(--text-muted);
            cursor: pointer;
            text-decoration: underline;
            margin-top: 1rem;
            display: inline-block;
        }

        .qr-box {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
        }
    </style>
</head>
<body>

<div class="ambient-glow"></div>

<header class="app-topbar">
    <a href="<?= url('index.php') ?>" style="color: var(--text-secondary); text-decoration: none; font-size: 0.88rem; font-weight: 600;">
        <i class="bi bi-chevron-left me-1"></i> Home
    </a>
    <a href="<?= url('guard/login.php') ?>" class="btn-signin">
        Sign In
    </a>
</header>

<main class="install-container">
    <img src="<?= asset('img/guard-avatar.jpg') ?>" alt="Guard" class="guard-avatar-img">
    <?php
    // The new app (background tracking + notifications) once it has been uploaded; the old one until then.
    $newApk = is_file(APP_ROOT . '/downloads/apk/Jatta-Security.apk');
    $apkFile = $newApk ? 'Jatta-Security.apk' : 'Jatta-Security-Guard.apk';
    ?>
    <h1 class="app-title">Jatta Security App</h1>
    <p class="app-sub">Duty, GPS attendance &amp; alerts — for guards and admins</p>

    <!-- 1-Tap APK Download Button -->
    <a href="<?= url('downloads/apk/' . $apkFile) ?>" class="btn-apk-main" download="<?= e($apkFile) ?>">
        <i class="bi bi-android2 fs-5"></i>
        <span>Download Android App (APK)</span>
    </a>

    <!-- 1-Tap Web App Alternative -->
    <button class="btn-pwa-sub" id="pwaTriggerBtn">
        <i class="bi bi-phone"></i>
        <span>Or Add to Home Screen (screen must stay on during duty)</span>
    </button>
    <div id="pwaStatus" class="small mt-2" style="color: var(--accent-purple);"></div>

    <!-- Ultra-simple 2 steps -->
    <div class="steps-box">
        <?php if ($newApk): ?>
        <div class="step-row">
            <div class="step-num">1</div>
            <div class="step-text">
                <strong>Remove the old app</strong>
                If you have the old <em>Jatta Guard</em> app, uninstall it first (long-press its icon → Uninstall).
            </div>
        </div>
        <?php endif; ?>
        <div class="step-row">
            <div class="step-num"><?= $newApk ? 2 : 1 ?></div>
            <div class="step-text">
                <strong>Download &amp; Install</strong>
                Tap the button above. When downloaded, tap to install (allow "Install unknown apps" if asked).
            </div>
        </div>
        <div class="step-row">
            <div class="step-num"><?= $newApk ? 3 : 2 ?></div>
            <div class="step-text">
                <strong>Sign In &amp; allow everything</strong>
                Open the app, sign in with your Badge ID or Mobile Number. Allow <b>Location (all the time)</b>, <b>Notifications</b> and set <b>Battery: No restrictions</b> — the app shows a checklist.
            </div>
        </div>
    </div>

    <!-- Quick link to login -->
    <div class="mt-4">
        <a href="<?= url('guard/login.php') ?>" class="text-white text-decoration-none fw-bold" style="font-size: 0.95rem;">
            Already installed? Open Guard Login <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <!-- Collapsible Supervisor QR Code -->
    <div class="mt-4">
        <span class="qr-toggle" onclick="toggleQr()">Show Supervisor QR Code</span>
        <div class="qr-box" id="qrWrap">
            <div id="installQrCanvas" class="d-inline-block p-2 bg-white rounded-3 mb-2"></div>
            <div class="small text-muted">Scan with phone camera to open this download page</div>
        </div>
    </div>
</main>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
let pwaPrompt;
const pwaBtn = document.getElementById('pwaTriggerBtn');
const pwaStatus = document.getElementById('pwaStatus');

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    pwaPrompt = e;
});

if (pwaBtn) {
    pwaBtn.addEventListener('click', async () => {
        if (pwaPrompt) {
            pwaPrompt.prompt();
            const { outcome } = await pwaPrompt.userChoice;
            pwaPrompt = null;
            if (outcome === 'accepted') {
                pwaStatus.textContent = 'App installed! Check your home screen.';
            }
        } else {
            const isIos = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (isIos) {
                alert('On iPhone: Tap Share (bottom icon) -> "Add to Home Screen".');
            } else {
                alert('On Android: Tap browser menu (⋮) -> "Install app" or "Add to Home screen".');
            }
        }
    });
}

function toggleQr() {
    const wrap = document.getElementById('qrWrap');
    wrap.style.display = wrap.style.display === 'block' ? 'none' : 'block';
}

new QRCode(document.getElementById("installQrCanvas"), {
    text: <?= json_encode(url('guard/install.php')) ?>,
    width: 130,
    height: 130,
    colorDark : "#07070a",
    colorLight : "#ffffff",
    correctLevel : QRCode.CorrectLevel.M
});
</script>
</body>
</html>
