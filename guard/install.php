<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$title = 'Install Guard Duty App';
?><!doctype html>
<html lang="en">
<head>
    <?php require __DIR__ . '/_head.php'; ?>
    <style>
        .install-wrap { max-width: 640px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .install-card { background: #ffffff; border-radius: 20px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,.08); color: #0f172a; }
        .step-num { width: 32px; height: 32px; border-radius: 50%; background: #0b1324; color: #f5b301; display: inline-grid; place-items: center; font-weight: 800; font-size: .95rem; flex-shrink: 0; }
        .step-row { display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
        .step-row:last-child { margin-bottom: 0; }
        .tab-btn { border-radius: 12px; font-weight: 600; padding: .6rem 1rem; font-size: .9rem; }
        .tab-btn.active { background: #0b1324; color: #f5b301; }
        .guide-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; }
        .benefit-pill { display: inline-flex; align-items: center; gap: .4rem; background: #ecfdf5; color: #047857; font-size: .8rem; font-weight: 600; padding: .25rem .6rem; border-radius: 999px; }
    </style>
</head>
<body class="g-body">
<header class="g-top">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= url('guard/login.php') ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i></a>
        <div class="fw-bold fs-6">Guard App Installation</div>
    </div>
    <div class="ms-auto">
        <a href="<?= url('guard/login.php') ?>" class="btn btn-sm btn-warning fw-bold">Sign In</a>
    </div>
</header>

<main class="install-wrap">
    <!-- Top Hero Banner -->
    <div class="text-center mb-4">
        <div class="mb-3">
            <img src="<?= asset('img/guards/hero-guard-sunglasses.jpg') ?>" alt="Officer" style="width: 84px; height: 84px; border-radius: 50%; object-fit: cover; border: 3px solid #f5b301; box-shadow: 0 8px 24px rgba(0,0,0,.15); display: inline-block;">
        </div>
        <h1 class="h4 fw-bold mb-1"><?= e(company_name()) ?></h1>
        <p class="text-muted small">Guard & Bodyguard Duty Mobile App Installation</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <span class="benefit-pill"><i class="bi bi-check-circle-fill"></i> 100% Free</span>
            <span class="benefit-pill"><i class="bi bi-geo-alt-fill"></i> Live GPS Tracking</span>
            <span class="benefit-pill"><i class="bi bi-shield-lock-fill"></i> Instant SOS</span>
        </div>
    </div>

    <!-- Quick 1-Tap Install Button for Mobile Browsers -->
    <div class="install-card border-warning border-2 bg-warning-subtle text-center">
        <h2 class="h5 fw-bold mb-2 text-dark">Install on This Phone (1 Tap)</h2>
        <p class="small text-muted mb-3">Install directly without opening the Google Play Store or Apple App Store.</p>
        <button id="pwaTriggerBtn" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm">
            <i class="bi bi-download me-2"></i> Tap to Install Guard App
        </button>
        <div id="pwaStatusText" class="small text-muted mt-2">
            Works instantly on all Android (Chrome) and iPhone (Safari) devices.
        </div>
    </div>

    <!-- Navigation Tabs for Instructions -->
    <ul class="nav nav-pills nav-fill gap-2 mb-3" id="installTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link tab-btn active" id="android-tab" data-bs-toggle="pill" data-bs-target="#android-pane" type="button">
                <i class="bi bi-android2 me-1"></i> Android (Fastest)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link tab-btn" id="ios-tab" data-bs-toggle="pill" data-bs-target="#ios-pane" type="button">
                <i class="bi bi-apple me-1"></i> iPhone (iOS)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link tab-btn" id="apk-tab" data-bs-toggle="pill" data-bs-target="#apk-pane" type="button">
                <i class="bi bi-file-earmark-arrow-down me-1"></i> APK File
            </button>
        </li>
    </ul>

    <div class="tab-content" id="installTabsContent">
        <!-- 1. ANDROID PWA (WebAPK) -->
        <div class="tab-pane fade show active" id="android-pane" role="tabpanel">
            <div class="install-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-android2 text-success me-1"></i> Android Installation (Chrome / Samsung Internet)</h3>
                    <span class="badge bg-success-subtle text-success">Recommended</span>
                </div>
                <p class="small text-muted">Android natively installs this app as a full standalone WebAPK application directly on your phone with its own launcher icon.</p>
                
                <div class="guide-box">
                    <div class="step-row">
                        <div class="step-num">1</div>
                        <div>
                            <strong>Open Google Chrome</strong>
                            <p class="small text-muted mb-0">Open Chrome on your phone and visit this duty app link.</p>
                        </div>
                    </div>
                    <div class="step-row">
                        <div class="step-num">2</div>
                        <div>
                            <strong>Tap Menu (3 Dots) or Install Button</strong>
                            <p class="small text-muted mb-0">Tap the three vertical dots (<code>⋮</code>) at the top-right corner of Chrome, then select <strong>"Install app"</strong> or <strong>"Add to Home screen"</strong>.</p>
                        </div>
                    </div>
                    <div class="step-row">
                        <div class="step-num">3</div>
                        <div>
                            <strong>Tap "Install"</strong>
                            <p class="small text-muted mb-0">Android will create the <strong>Guard</strong> icon on your home screen. Tap it anytime to sign in, check in for duty, and enable live tracking!</p>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info py-2 small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i> <strong>Automatic Updates:</strong> Whenever the agency updates assignments or features on Hostinger, the app updates automatically without needing manual reinstall.
                </div>
            </div>
        </div>

        <!-- 2. IPHONE (iOS Safari) -->
        <div class="tab-pane fade" id="ios-pane" role="tabpanel">
            <div class="install-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-apple text-dark me-1"></i> iPhone & iPad Installation (Safari)</h3>
                    <span class="badge bg-secondary-subtle text-secondary">Apple iOS</span>
                </div>
                <p class="small text-muted">On Apple devices, Apple Safari installs the app directly to your home screen.</p>
                
                <div class="guide-box">
                    <div class="step-row">
                        <div class="step-num">1</div>
                        <div>
                            <strong>Open in Safari</strong>
                            <p class="small text-muted mb-0">Open Safari browser on your iPhone and navigate to this link.</p>
                        </div>
                    </div>
                    <div class="step-row">
                        <div class="step-num">2</div>
                        <div>
                            <strong>Tap the Share Button</strong>
                            <p class="small text-muted mb-0">Tap the <strong>Share</strong> icon (the square with an arrow pointing up <i class="bi bi-box-arrow-up"></i>) at the bottom toolbar.</p>
                        </div>
                    </div>
                    <div class="step-row">
                        <div class="step-num">3</div>
                        <div>
                            <strong>Choose "Add to Home Screen"</strong>
                            <p class="small text-muted mb-0">Scroll down and tap <strong>"Add to Home Screen"</strong> <i class="bi bi-plus-square"></i>, then tap <strong>"Add"</strong> at the top right.</p>
                        </div>
                    </div>
                </div>

                <div class="alert alert-secondary py-2 small mt-3 mb-0">
                    <i class="bi bi-apple me-1"></i> The Guard app icon will appear alongside your normal iPhone apps.
                </div>
            </div>
        </div>

        <!-- 3. RAW APK DOWNLOAD -->
        <div class="tab-pane fade" id="apk-pane" role="tabpanel">
            <div class="install-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-file-earmark-arrow-down-fill text-warning me-1"></i> Direct APK File (.apk)</h3>
                    <span class="badge bg-warning-subtle text-warning">Android Sideload</span>
                </div>
                <p class="small text-muted">For devices without Google Chrome or for distributing the installer directly over WhatsApp or Bluetooth.</p>

                <div class="d-grid gap-2 mb-3">
                    <a href="<?= url('downloads/guard-duty-app.apk') ?>" class="btn btn-navy btn-lg fw-bold" download>
                        <i class="bi bi-download me-2"></i> Download Guard APK (.apk)
                    </a>
                </div>

                <div class="guide-box">
                    <h4 class="small fw-bold text-uppercase text-muted mb-2">How to install the downloaded APK:</h4>
                    <div class="step-row">
                        <div class="step-num">1</div>
                        <div>
                            <strong>Download the APK file</strong>
                            <p class="small text-muted mb-0">Tap the button above. If Chrome warns <em>"File might be harmful"</em>, tap <strong>"Download anyway"</strong>.</p>
                        </div>
                    </div>
                    <div class="step-row">
                        <div class="step-num">2</div>
                        <div>
                            <strong>Allow installation</strong>
                            <p class="small text-muted mb-0">Open the file. If Android prompts <em>"For your security, your phone is not allowed to install unknown apps"</em>, tap <strong>Settings</strong> and turn ON <strong>"Allow from this source"</strong>.</p>
                        </div>
                    </div>
                    <div class="step-row">
                        <div class="step-num">3</div>
                        <div>
                            <strong>Tap "Install"</strong>
                            <p class="small text-muted mb-0">Tap Install. Once complete, open the app and log in with your Employee ID.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-3 p-3 bg-light rounded-3 small">
                    <strong>For Agency IT / Administrators:</strong>
                    <p class="text-muted mb-1 mt-1">You can build a signed production APK or Google Play Store bundle for Hostinger in 1 minute using the official Google-backed PWA tool <strong>PWABuilder</strong> (<a href="https://www.pwabuilder.com" target="_blank" rel="noopener">pwabuilder.com</a>). Simply enter your Hostinger domain URL, click <em>Package for Android</em>, and upload the generated APK to your <code>/downloads/</code> folder.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Onboarding QR Code for Desk / Supervisor -->
    <div class="install-card text-center">
        <h3 class="h6 fw-bold mb-2">Scan QR Code to Open on Phone</h3>
        <p class="small text-muted mb-3">Point any phone camera to instantly open the Guard Duty App on that phone.</p>
        <div id="installQrCanvas" class="d-inline-block p-2 bg-white rounded border shadow-sm mb-2"></div>
        <div class="small fw-semibold text-primary mt-1"><?= e(url('guard/login.php')) ?></div>
    </div>

    <!-- Back to Login -->
    <div class="text-center mt-3">
        <a href="<?= url('guard/login.php') ?>" class="btn btn-warning btn-lg px-4 fw-bold">
            <i class="bi bi-box-arrow-in-right me-1"></i> Proceed to Guard Login
        </a>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/qrcode.min.js') ?>"></script>
<script>
// PWA installation trigger
let pwaPrompt;
const pwaBtn = document.getElementById('pwaTriggerBtn');
const pwaText = document.getElementById('pwaStatusText');

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    pwaPrompt = e;
    if (pwaBtn) {
        pwaBtn.classList.remove('btn-outline-warning');
        pwaBtn.classList.add('btn-warning');
    }
});

if (pwaBtn) {
    pwaBtn.addEventListener('click', async () => {
        if (pwaPrompt) {
            pwaPrompt.prompt();
            const { outcome } = await pwaPrompt.userChoice;
            pwaPrompt = null;
            if (outcome === 'accepted' && pwaText) {
                pwaText.innerHTML = '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> App installed successfully! Check your phone home screen.</span>';
            }
        } else {
            // Check if iOS
            const isIos = /iPhone|iPad|iPod/i.test(navigator.userAgent);
            if (isIos) {
                const iosTab = new bootstrap.Tab(document.getElementById('ios-tab'));
                iosTab.show();
                alert('On iPhone: Tap the Share button at the bottom of Safari, then tap "Add to Home Screen".');
            } else {
                alert('To install on Android: Tap the 3 dots (⋮) in Chrome, then tap "Install app" or "Add to Home screen".');
            }
        }
    });
}

// Generate QR Code
new QRCode(document.getElementById("installQrCanvas"), {
    text: <?= json_encode(url('guard/login.php')) ?>,
    width: 150,
    height: 150,
    colorDark : "#0b1324",
    colorLight : "#ffffff",
    correctLevel : QRCode.CorrectLevel.M
});
</script>
</body>
</html>
