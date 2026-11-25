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

<main class="g-main" id="viewDuty">
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

    <div id="offBanner" class="g-banner g-banner-info d-none"><i class="bi bi-cup-hot"></i> <span id="offBannerText"></span></div>

    <!-- Incoming handover waiting for this guard -->
    <div id="handInBox"></div>

    <!-- Assignment -->
    <section class="g-card">
        <div class="g-label">Current duty</div>
        <div id="asgBox"><div class="text-muted small">Loading…</div></div>
    </section>

    <!-- Duty control -->
    <section class="g-card text-center">
        <div id="dutyState" class="g-duty-state off">OFF DUTY</div>
        <div id="dutyTimer" class="g-timer d-none">00:00:00</div>
        <div id="dutySince" class="small text-muted mb-2"></div>
        <div id="shiftInfo" class="g-shift-info d-none"></div>
        <button id="dutyBtn" class="btn btn-lg w-100 g-duty-btn start mt-2" disabled><i class="bi bi-camera-fill"></i> <span>Start Duty</span></button>
        <div id="dutyActions" class="g-duty-actions d-none">
            <button class="btn btn-outline-primary" id="extBtn"><i class="bi bi-hourglass-split"></i> Extra hours</button>
            <button class="btn btn-outline-dark" id="handBtn"><i class="bi bi-arrow-left-right"></i> Hand over</button>
        </div>
        <div id="extBox"></div>
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
        <button class="btn btn-sm btn-light w-100 mt-2 fw-semibold" data-goto="history"><i class="bi bi-calendar3"></i> Full attendance history</button>
    </section>

    <section class="g-card small text-muted" id="tips">
        <div class="fw-semibold text-body mb-1"><i class="bi bi-info-circle"></i> Duty Guidelines</div>
        <ul class="mb-0 ps-3">
            <li>Keep this app open while on duty (screen stays awake automatically).</li>
            <li>Turn on high-accuracy location so your post coordinates sync to control room.</li>
        </ul>
    </section>
</main>

<!-- Attendance history -->
<main class="g-main d-none" id="viewHistory">
    <section class="g-card">
        <div class="d-flex align-items-center justify-content-between">
            <button class="btn btn-light btn-sm" id="histPrev" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
            <div class="fw-bold" id="histLabel">—</div>
            <button class="btn btn-light btn-sm" id="histNext" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="g-stats mt-3" id="histStats"></div>
    </section>
    <section class="g-card">
        <div class="g-cal-head"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
        <div class="g-cal" id="histCal"></div>
        <div class="g-cal-legend">
            <span><i class="present"></i>Present</span><span><i class="late"></i>Late</span><span><i class="absent"></i>Absent</span>
            <span><i class="weekly_off"></i>Weekly off</span><span><i class="leave"></i>Leave</span><span><i class="none"></i>No duty</span>
        </div>
    </section>
    <section class="g-card">
        <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="g-label mb-0">My leave</div>
            <button class="btn btn-sm btn-primary fw-semibold" id="leaveBtn"><i class="bi bi-calendar-plus"></i> Request leave</button>
        </div>
        <div class="small text-muted mb-2">Weekly off: <strong id="weeklyOffText">—</strong></div>
        <div id="leaveList" class="small"></div>
    </section>
    <section class="g-card">
        <div class="g-label" id="histDayLabel">Daily attendance</div>
        <div id="histDays" class="small"><div class="text-muted">Loading…</div></div>
    </section>
</main>

<nav class="g-tabs">
    <button class="active" data-goto="duty"><i class="bi bi-shield-check"></i><span>Duty</span></button>
    <button data-goto="history"><i class="bi bi-calendar3"></i><span>Attendance</span></button>
</nav>

<!-- Selfie sheet -->
<div class="g-sheet" id="sheetSelfie" aria-hidden="true">
    <div class="g-sheet-box">
        <div class="g-sheet-title">Duty selfie <button class="g-sheet-x" data-close aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <p class="small text-muted mb-2">Look at the camera in your uniform. Your selfie is sent to the control room with your check-in.</p>
        <div class="g-cam">
            <video id="camVideo" playsinline autoplay muted></video>
            <img id="camShot" alt="Your selfie" class="d-none">
            <div id="camMsg" class="g-cam-msg d-none"></div>
        </div>
        <input type="file" accept="image/*" capture="user" id="camFile" class="d-none">
        <div class="d-grid gap-2 mt-3">
            <button class="btn btn-lg btn-dark fw-bold" id="camSnap"><i class="bi bi-camera-fill"></i> Take selfie</button>
            <button class="btn btn-lg g-duty-btn start d-none" id="camUse"><i class="bi bi-play-circle-fill"></i> Use photo &amp; Start Duty</button>
            <button class="btn btn-light d-none" id="camRetake"><i class="bi bi-arrow-counterclockwise"></i> Retake</button>
        </div>
    </div>
</div>

<!-- Extra hours sheet -->
<div class="g-sheet" id="sheetExt" aria-hidden="true">
    <div class="g-sheet-box">
        <div class="g-sheet-title">Request extra hours <button class="g-sheet-x" data-close aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <p class="small text-muted mb-2" id="extShiftText"></p>
        <label class="form-label small fw-semibold">How much extra time?</label>
        <div class="g-pills mb-3" id="extMinutes">
            <button type="button" data-min="30">30 min</button><button type="button" data-min="60" class="active">1 hr</button>
            <button type="button" data-min="120">2 hrs</button><button type="button" data-min="180">3 hrs</button>
            <button type="button" data-min="240">4 hrs</button><button type="button" data-min="480">8 hrs</button>
        </div>
        <label class="form-label small fw-semibold" for="extReason">Reason</label>
        <textarea class="form-control mb-3" id="extReason" rows="2" maxlength="255" placeholder="e.g. Relief guard has not arrived / client asked me to stay"></textarea>
        <button class="btn btn-lg btn-primary w-100 fw-bold" id="extSend"><i class="bi bi-send-fill"></i> Send request</button>
    </div>
</div>

<!-- Leave request sheet -->
<div class="g-sheet" id="sheetLeave" aria-hidden="true">
    <div class="g-sheet-box">
        <div class="g-sheet-title">Request leave <button class="g-sheet-x" data-close aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <p class="small text-muted mb-2">The control room approves leave. Approved leave days are not counted as absent.</p>
        <label class="form-label small fw-semibold">Type of leave</label>
        <div class="g-pills mb-3" id="leaveTypes"></div>
        <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label small fw-semibold" for="leaveFrom">From</label><input type="date" class="form-control" id="leaveFrom"></div>
            <div class="col-6"><label class="form-label small fw-semibold" for="leaveTo">To (last day)</label><input type="date" class="form-control" id="leaveTo"></div>
        </div>
        <div class="small text-muted mb-2" id="leaveDays"></div>
        <label class="form-label small fw-semibold" for="leaveReason">Reason</label>
        <textarea class="form-control mb-3" id="leaveReason" rows="2" maxlength="255" placeholder="e.g. Family function in village"></textarea>
        <button class="btn btn-lg btn-primary w-100 fw-bold" id="leaveSend"><i class="bi bi-send-fill"></i> Send leave request</button>
    </div>
</div>

<!-- Handover sheet -->
<div class="g-sheet" id="sheetHand" aria-hidden="true">
    <div class="g-sheet-box">
        <div class="g-sheet-title">Shift handover <button class="g-sheet-x" data-close aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
        <p class="small text-muted mb-2">Hand over your post to the next guard. Your duty ends when you submit.</p>
        <label class="form-label small fw-semibold" for="handGuard">Incoming guard (taking over)</label>
        <select class="form-select mb-3" id="handGuard"><option value="">Loading…</option></select>
        <label class="form-label small fw-semibold">Items handed over</label>
        <div class="g-checks mb-2" id="handItems"></div>
        <input class="form-control mb-3" id="handOther" maxlength="150" placeholder="Other items (optional)">
        <label class="form-label small fw-semibold" for="handNotes">Notes for the next guard</label>
        <textarea class="form-control mb-3" id="handNotes" rows="3" maxlength="1000" placeholder="e.g. Gate 2 lock is faulty. Expecting a delivery at 9 PM."></textarea>
        <button class="btn btn-lg btn-dark w-100 fw-bold" id="handSend"><i class="bi bi-arrow-left-right"></i> Hand over &amp; End Duty</button>
    </div>
</div>

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
