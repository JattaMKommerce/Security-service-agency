<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$guard = require_guard();
$title = 'Duty';
?><!doctype html>
<html lang="en">
<head>
    <?php require __DIR__ . '/_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
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

    <!-- Map -->
    <section class="g-card p-0 overflow-hidden">
        <div id="gMap" style="height:240px"></div>
        <div class="p-3 small d-flex justify-content-between"><span id="myPos" class="text-muted">Waiting for GPS…</span><span id="myDist" class="fw-semibold"></span></div>
    </section>

    <!-- Stats / history -->
    <section class="g-card">
        <div class="d-flex justify-content-between mb-2"><span class="g-label mb-0">This month</span><span class="small fw-semibold" id="monthStats">—</span></div>
        <div id="recentList" class="small"></div>
    </section>

    <section class="g-card small text-muted" id="tips">
        <div class="fw-semibold text-body mb-1"><i class="bi bi-info-circle"></i> For accurate tracking</div>
        <ul class="mb-0 ps-3">
            <li>Keep this app open with the screen on while on duty (the app keeps the screen awake).</li>
            <li>Turn on GPS / high-accuracy location and allow location for this app.</li>
            <li>Install the app: browser menu → <em>Add to Home screen</em>.</li>
        </ul>
        <button class="btn btn-sm btn-outline-primary mt-2 d-none" id="installBtn"><i class="bi bi-download"></i> Install app</button>
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
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= asset('js/guard.js') ?>"></script>
</body>
</html>
