<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$guard = require_guard();
$title = 'Duty';
?><!doctype html>
<html lang="en">
<head>
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="g-body">
<header class="g-top">
    <div class="d-flex align-items-center gap-2 min-w-0">
        <?= avatar($guard['full_name'], $guard['photo'], 'md') ?>
        <div class="min-w-0">
            <div class="fw-bold text-truncate"><?= e($guard['full_name']) ?></div>
            <div class="small opacity-75"><?= e($guard['employee_code']) ?> · <?= e(company_name()) ?></div>
        </div>
    </div>
    <form method="post" action="<?= url('guard/logout.php') ?>" class="ms-auto" id="logoutForm">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-light" aria-label="Log out"><i class="bi bi-box-arrow-right"></i></button>
    </form>
</header>

<main class="g-main">
    <div id="netBanner" class="g-banner g-banner-warn d-none"><i class="bi bi-wifi-off"></i> <span>No internet — locations are saved and will be sent automatically.</span></div>
    <div id="gpsBanner" class="g-banner g-banner-danger d-none"><i class="bi bi-geo-alt"></i> <span id="gpsBannerText"></span></div>

    <!-- SOS state -->
    <section id="sosActive" class="g-card g-sos-active d-none">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-octagon-fill fs-1"></i>
            <div><div class="fw-bold fs-5">SOS SENT</div><div id="sosStatusText" class="small">Control room has been alerted. Stay safe.</div></div>
        </div>
        <button class="btn btn-light w-100 mt-3 fw-semibold" id="sosCancel"><i class="bi bi-check-circle"></i> I am safe — cancel SOS</button>
    </section>

    <!-- Assignment -->
    <section class="g-card">
        <div class="g-label">Current duty</div>
        <div id="asgBox"><div class="text-muted small">Loading…</div></div>
    </section>

    <!-- Duty control -->
    <section class="g-card text-center">
        <div id="dutyState" class="g-duty-state off">OFF DUTY</div>
        <div id="dutyTimer" class="g-timer d-none">00:00:00</div>
        <div id="dutySince" class="small text-muted mb-3"></div>
        <button id="dutyBtn" class="btn btn-lg w-100 g-duty-btn start" disabled><i class="bi bi-play-circle-fill"></i> <span>Start Duty</span></button>
        <div id="trackInfo" class="g-track small mt-3 d-none">
            <span class="g-pulse"></span>
            <span id="trackText">Sharing live location…</span>
        </div>
        <div id="outsideWarn" class="alert alert-warning small mt-3 mb-0 d-none"><i class="bi bi-sign-turn-right"></i> You are outside the duty area. Return to the site.</div>
    </section>

    <!-- SOS -->
    <section class="g-card text-center" id="sosCard">
        <div class="g-label">Emergency</div>
        <button id="sosBtn" class="g-sos-btn" aria-label="Press and hold for SOS">
            <svg viewBox="0 0 120 120" class="g-sos-ring"><circle cx="60" cy="60" r="56" id="sosProgress"/></svg>
            <span>SOS</span>
        </button>
        <div class="small text-muted mt-2">Press and hold for 2 seconds to alert the control room with your location.</div>
    </section>

    <!-- Live Location Status (Clean & Battery-Saving) -->
    <section class="g-card">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div style="width: 44px; height: 44px; border-radius: 14px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6; display: grid; place-items: center; font-size: 1.3rem;">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <div>
                    <div class="g-label mb-1">Live GPS Duty Status</div>
                    <div id="myPos" class="fw-semibold small text-dark">Detecting GPS location…</div>
                </div>
            </div>
            <div id="myDist" class="small fw-bold text-end"></div>
        </div>
    </section>

    <!-- Stats / history -->
    <section class="g-card">
        <div class="d-flex justify-content-between mb-2"><span class="g-label mb-0">This month</span><span class="small fw-semibold" id="monthStats">—</span></div>
        <div id="recentList" class="small"></div>
    </section>

    <section class="g-card small text-muted" id="tips">
        <div class="fw-semibold text-body mb-1"><i class="bi bi-info-circle"></i> Duty Guidelines</div>
        <ul class="mb-0 ps-3">
            <li>Keep this app open while on duty (screen stays awake automatically).</li>
            <li>Turn on high-accuracy location so your post coordinates sync to control room.</li>
        </ul>
    </section>
</main>

<div class="g-toast" id="gToast"></div>

<script>
    window.GUARD = {
        base: <?= json_encode(BASE_URL) ?>,
        csrf: <?= json_encode(csrf_token()) ?>,
        sw: <?= json_encode(url('guard/sw.js')) ?>,
        login: <?= json_encode(url('guard/login.php')) ?>,
        mapDefault: [<?= (float)setting('map_default_lat', '20.5937') ?>, <?= (float)setting('map_default_lng', '78.9629') ?>, <?= (int)setting('map_default_zoom', '5') ?>]
    };
</script>
<script src="<?= asset('js/guard.js') ?>"></script>
</body>
</html>
