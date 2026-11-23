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
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #07070a;
            --bg-card: #0e0f18;
            --bg-card-inner: #141522;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-active: rgba(168, 85, 247, 0.4);
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
            -webkit-tap-highlight-color: transparent;
        }

        .ambient-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            max-width: 100vw;
            height: 350px;
            background: radial-gradient(ellipse at 50% 0%, rgba(139, 92, 246, 0.22) 0%, rgba(124, 58, 237, 0.08) 50%, transparent 70%);
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
        }

        .app-topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: calc(0.85rem + env(safe-area-inset-top)) 1.25rem 0.85rem;
            background: rgba(7, 7, 10, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-subtle);
        }

        .app-topbar a {
            color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            transition: color 0.2s;
        }
        .app-topbar a:hover { color: #fff; }

        .btn-topbar-signin {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid var(--border-subtle);
            color: #fff;
            padding: 0.45rem 1rem;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-topbar-signin:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .install-container {
            position: relative;
            z-index: 1;
            max-width: 580px;
            margin: 0 auto;
            padding: 1.5rem 1.1rem;
        }

        /* Hero */
        .hero-badge-wrap {
            text-align: center;
            margin-bottom: 2rem;
        }
        .guard-avatar-img {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(168, 85, 247, 0.6);
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.35);
            margin-bottom: 1rem;
        }
        .hero-title {
            font-size: 1.45rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }
        .hero-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .pill-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.76rem;
            font-weight: 600;
            background: rgba(168, 85, 247, 0.12);
            color: #d8b4fe;
            border: 1px solid rgba(168, 85, 247, 0.25);
        }

        /* Comfortable Clean Cards */
        .clean-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 22px;
            padding: 1.6rem;
            margin-bottom: 1.4rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
        }
        .clean-card.featured {
            border-color: var(--border-active);
            background: linear-gradient(180deg, rgba(20, 21, 34, 0.9) 0%, rgba(14, 15, 24, 0.95) 100%);
        }

        .card-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        .card-badge-method {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.28rem 0.65rem;
            border-radius: 9999px;
            background: rgba(52, 211, 153, 0.12);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.25);
        }
        .card-badge-apk {
            background: rgba(168, 85, 247, 0.12);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.25);
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
            color: #fff;
        }
        .card-desc {
            font-size: 0.88rem;
            color: var(--text-secondary);
            margin-bottom: 1.35rem;
            line-height: 1.5;
        }

        /* Big Comfortable Buttons */
        .btn-install-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.95rem 1.5rem;
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 800;
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 28px rgba(124, 58, 237, 0.45);
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-install-primary:active {
            transform: scale(0.98);
        }

        .btn-apk-secondary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            width: 100%;
            padding: 0.9rem 1.5rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 16px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-apk-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(168, 85, 247, 0.5);
            color: #ffffff;
        }
        .btn-apk-secondary:active {
            transform: scale(0.98);
        }

        /* Clean 3-Step Guide */
        .steps-box {
            background: var(--bg-card-inner);
            border-radius: 16px;
            padding: 1.15rem;
            margin-top: 1.25rem;
            border: 1px solid var(--border-subtle);
        }
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
            margin-bottom: 0.85rem;
        }
        .step-item:last-child { margin-bottom: 0; }
        .step-icon-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(168, 85, 247, 0.2);
            color: #c084fc;
            display: grid;
            place-items: center;
            font-size: 0.8rem;
            font-weight: 800;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .step-content {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }
        .step-content strong {
            color: #fff;
            display: block;
            margin-bottom: 2px;
            font-size: 0.88rem;
        }

        /* Bottom Action */
        .bottom-nav {
            text-align: center;
            margin-top: 2rem;
            padding-bottom: 1.5rem;
        }
        .btn-proceed-signin {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 2rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid var(--border-subtle);
            border-radius: 9999px;
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-proceed-signin:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
        }
    </style>
</head>
<body>

<div class="ambient-glow"></div>

<header class="app-topbar">
    <a href="<?= url('guard/login.php') ?>">
        <i class="bi bi-arrow-left"></i>
        <span>Guard Portal</span>
    </a>
    <a href="<?= url('guard/login.php') ?>" class="btn-topbar-signin">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
    </a>
</header>

<main class="install-container">
    
    <!-- Top Hero Badge -->
    <div class="hero-badge-wrap">
        <img src="<?= asset('img/guards/hero-guard-sunglasses.jpg') ?>" alt="Officer" class="guard-avatar-img">
        <h1 class="hero-title"><?= e(company_name()) ?></h1>
        <p class="hero-subtitle">Official Guard & Bodyguard Duty App Installation</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <span class="pill-tag"><i class="bi bi-check-circle-fill"></i> Free Install</span>
            <span class="pill-tag"><i class="bi bi-geo-alt-fill"></i> Live GPS Duty</span>
            <span class="pill-tag"><i class="bi bi-shield-fill-check"></i> Instant SOS</span>
        </div>
    </div>

    <!-- 1. PRIMARY: 1-Tap Mobile App Install (Recommended) -->
    <section class="clean-card featured">
        <div class="card-header-flex">
            <span class="card-badge-method"><i class="bi bi-lightning-charge-fill"></i> Instant WebAPK</span>
            <span class="text-muted small"><i class="bi bi-phone me-1"></i>Android & iPhone</span>
        </div>
        
        <h2 class="card-title">1-Tap Direct Install</h2>
        <p class="card-desc">Installs directly to your home screen with zero storage overhead. Auto-updates when you start duty.</p>
        
        <button id="pwaTriggerBtn" class="btn-install-primary">
            <i class="bi bi-download"></i>
            <span>Install Guard App on Phone</span>
        </button>
        <div id="pwaStatusText" class="small text-center text-muted mt-2">
            One tap setup · Launches in standalone fullscreen
        </div>

        <div class="steps-box mt-3">
            <div class="step-item">
                <div class="step-icon-circle">1</div>
                <div class="step-content">
                    <strong>Tap "Install Guard App"</strong>
                    Tap the button above. If prompted by your browser, tap <strong>Install</strong> or <strong>Add</strong>.
                </div>
            </div>
            <div class="step-item">
                <div class="step-icon-circle">2</div>
                <div class="step-content">
                    <strong>For iPhone / Safari:</strong>
                    Tap Safari's <strong>Share</strong> button (<i class="bi bi-box-arrow-up"></i>) at the bottom $\rightarrow$ choose <strong>"Add to Home Screen"</strong>.
                </div>
            </div>
            <div class="step-item">
                <div class="step-icon-circle">3</div>
                <div class="step-content">
                    <strong>Open & Start Duty</strong>
                    Launch the app from your home screen and sign in with your Badge ID or Phone.
                </div>
            </div>
        </div>
    </section>

    <!-- 2. SEGREGATED: Direct Android APK Package -->
    <section class="clean-card">
        <div class="card-header-flex">
            <span class="card-badge-method card-badge-apk"><i class="bi bi-android2"></i> Standalone APK</span>
            <span class="text-muted small">v1.0.0 · 1.3 MB</span>
        </div>

        <h2 class="card-title">Download Android APK File</h2>
        <p class="card-desc">Prefer a direct sideloadable file? Download the standalone signed APK package for any Android device.</p>

        <a href="<?= url('downloads/apk/Jatta-Security-Guard.apk') ?>" class="btn-apk-secondary" download="Jatta-Security-Guard.apk">
            <i class="bi bi-file-earmark-arrow-down-fill text-purple"></i>
            <span>Download Jatta-Security-Guard.apk</span>
        </a>

        <div class="steps-box mt-3">
            <div class="step-item">
                <div class="step-icon-circle">1</div>
                <div class="step-content">
                    <strong>Download & Open</strong>
                    Tap the button above. When the file finishes downloading, tap to open it.
                </div>
            </div>
            <div class="step-item">
                <div class="step-icon-circle">2</div>
                <div class="step-content">
                    <strong>Allow Installation</strong>
                    If Android asks to allow installing unknown apps, tap <strong>Settings</strong> $\rightarrow$ toggle <strong>"Allow from this source"</strong>.
                </div>
            </div>
            <div class="step-item">
                <div class="step-icon-circle">3</div>
                <div class="step-content">
                    <strong>Complete Installation</strong>
                    Tap <strong>Install</strong>. Once installed, tap <strong>Open</strong> to begin duty.
                </div>
            </div>
        </div>
    </section>

    <!-- 3. SUPERVISOR / DESK QR CODE -->
    <section class="clean-card text-center">
        <h3 class="card-title fs-6 mb-1">Supervisor Onboarding QR Code</h3>
        <p class="card-desc mb-3">Point any guard's phone camera at this QR code to open the app setup instantly.</p>
        <div id="installQrCanvas" class="d-inline-block p-3 bg-white rounded-3 shadow-sm mb-2"></div>
        <div class="small fw-semibold mt-2" style="color: var(--accent-purple);"><?= e(url('guard/install.php')) ?></div>
    </section>

    <!-- Bottom Action -->
    <div class="bottom-nav">
        <a href="<?= url('guard/login.php') ?>" class="btn-proceed-signin">
            <span>Proceed to Guard Login</span>
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>

</main>

<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
// PWA installation trigger
let pwaPrompt;
const pwaBtn = document.getElementById('pwaTriggerBtn');
const pwaText = document.getElementById('pwaStatusText');

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
            if (outcome === 'accepted' && pwaText) {
                pwaText.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> App installed! Check your home screen.</span>';
            }
        } else {
            const isIos = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (isIos) {
                alert('On iPhone: Tap the Share button at the bottom of Safari, then choose "Add to Home Screen".');
            } else {
                alert('On Android: Tap the 3 dots (⋮) in Chrome, then tap "Install app" or "Add to Home screen".');
            }
        }
    });
}

// Generate QR Code
new QRCode(document.getElementById("installQrCanvas"), {
    text: <?= json_encode(url('guard/install.php')) ?>,
    width: 140,
    height: 140,
    colorDark : "#07070a",
    colorLight : "#ffffff",
    correctLevel : QRCode.CorrectLevel.M
});
</script>
</body>
</html>
