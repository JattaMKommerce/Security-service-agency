<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$id = get_int('id');
$g = q_one('SELECT * FROM guards WHERE id = ?', [$id]);
if (!$g) {
    flash('danger', 'Bodyguard not found.');
    redirect('admin/guards.php');
}

if (is_post()) {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'reset_password') {
        $customPw = trim((string)post_str('new_password', 50));
        $pw = $customPw !== '' ? $customPw : '123456';
        q('UPDATE guards SET password_hash = ?, remember_hash = NULL, remember_expires = NULL WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        clear_guard_login_lock($id);
        log_activity('guard.reset_password', $g['employee_code'] . ' -> ' . $pw);
        flash('success', 'Guard App password for ' . $g['full_name'] . ' set to: ' . $pw . ' — guard can now sign in with this PIN.');
    } elseif ($action === 'toggle_armed') {
        $newArmed = $g['is_armed'] ? 0 : 1;
        q('UPDATE guards SET is_armed = ? WHERE id = ?', [$newArmed, $id]);
        log_activity($newArmed ? 'guard.armed_enable' : 'guard.armed_disable', $g['employee_code'] . ' ' . $g['full_name']);
        flash('success', $newArmed ? 'Gun / firearm authorization ENABLED for ' . $g['full_name'] . '.' : 'Gun authorization DISABLED for ' . $g['full_name'] . '.');
    } elseif ($action === 'force_checkout') {
        $r = end_shift($id, null, null, (int)$admin['id'], 'Checked out by admin ' . $admin['name']);
        log_activity('attendance.force_checkout', $g['employee_code']);
        flash($r ? 'success' : 'info', $r ? 'Shift ended (' . fmt_minutes($r['duration_minutes']) . ').' : 'The guard had no open shift.');
    }
    redirect('admin/guard_view.php?id=' . $id);
}

$current = guard_active_assignment($id);
$shift = guard_open_shift($id);
$history = q_all('SELECT a.*, c.name AS client_name FROM assignments a JOIN clients c ON c.id = a.client_id
                   WHERE a.guard_id = ? ORDER BY a.id DESC LIMIT 25', [$id]);
$attendance = q_all('SELECT at.*, c.name AS client_name FROM attendance at LEFT JOIN clients c ON c.id = at.client_id
                      WHERE at.guard_id = ? ORDER BY at.check_in_at DESC LIMIT 15', [$id]);
$alerts = q_all('SELECT al.*, c.name AS client_name FROM alerts al LEFT JOIN clients c ON c.id = al.client_id
                  WHERE al.guard_id = ? ORDER BY al.id DESC LIMIT 10', [$id]);
$month = q_one('SELECT COUNT(*) shifts, COALESCE(SUM(duration_minutes),0) mins FROM attendance WHERE guard_id = ? AND check_in_at >= ?',
               [$id, date('Y-m-01 00:00:00')]);
$liveRow = q_one(guards_live_sql('g.id = ?'), [$id]);
$live = guard_live_status($liveRow);
[$liveLabel, $liveColor] = live_status_meta($live);
$trailDate = get_str('date', 10);
if (!valid_date($trailDate)) $trailDate = date('Y-m-d');

$pageTitle = $g['full_name'];
$activeNav = 'guards';
$useMap = true;
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions">
    <a href="<?= url('admin/guards.php') ?>" class="small"><i class="bi bi-arrow-left"></i> All bodyguards</a>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!$current && $g['status'] === 'active'): ?>
            <a href="<?= url('admin/assignment_form.php?guard_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="bi bi-briefcase"></i> Hire out / Assign to client</a>
        <?php endif; ?>
        <a href="<?= url('admin/tracking.php?guard_id=' . $id) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-geo-alt"></i> Track live</a>
        <a href="<?= url('admin/guard_form.php?id=' . $id) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <form method="post" class="d-inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle_armed">
            <?php if ($g['is_armed']): ?>
                <button class="btn btn-outline-danger btn-sm" title="Click to disable firearm authorization"><i class="bi bi-toggle-on text-success"></i> Gun: Enabled (Disable)</button>
            <?php else: ?>
                <button class="btn btn-outline-success btn-sm" title="Click to authorize firearm"><i class="bi bi-toggle-off text-muted"></i> Gun: Disabled (Enable)</button>
            <?php endif; ?>
        </form>
        <form method="post" class="d-inline" onsubmit="var p = prompt('Enter new password for <?= e($g['full_name']) ?> (or click OK for 123456):', '123456'); if (p === null) return false; this.new_password.value = p; return true;">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="new_password" value="">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-key"></i> Set / Reset PIN</button>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <?= avatar($g['full_name'], $g['photo'], 'xl') ?>
                <h2 class="h5 fw-bold mt-3 mb-0"><?= e($g['full_name']) ?></h2>
                <div class="text-muted small mb-2"><?= e($g['employee_code']) ?> · <?= category_badge($g['category'] ?? 'bodyguard') ?></div>
                <div class="d-flex justify-content-center gap-2 flex-wrap mb-2">
                    <?= status_badge($g['status']) ?>
                    <?= weapon_badge((bool)$g['is_armed']) ?>
                    <span class="badge" style="background:<?= $liveColor ?>"><?= e($liveLabel) ?></span>
                </div>
            </div>
            <div class="card-body border-top">
                <dl class="kv">
                    <dt>App login</dt><dd><code><?= e($g['employee_code']) ?></code> or <code><?= e($g['phone']) ?></code></dd>
                    <dt>Phone</dt><dd><a href="tel:<?= e($g['phone']) ?>"><?= e($g['phone']) ?></a></dd>
                    <dt>Email</dt><dd><?= e($g['email'] ?: '—') ?></dd>
                    <dt>Gender / DOB</dt><dd><?= e($g['gender'] ? label($g['gender']) : '—') ?> · <?= e(fmt_date($g['dob'])) ?></dd>
                    <dt>Blood group</dt><dd><?= e($g['blood_group'] ?: '—') ?></dd>
                    <dt>City</dt><dd><?= e($g['city'] ?: '—') ?></dd>
                    <dt>Address</dt><dd><?= nl2br(e($g['address'] ?: '—')) ?></dd>
                    <dt>ID proof</dt><dd><?= e($g['id_proof_type'] ?: '—') ?> <?= e($g['id_proof_number'] ?? '') ?></dd>
                    <dt>Experience</dt><dd><?= (int)$g['experience_years'] ?> years</dd>
                    <dt>Duties & Specialization</dt><dd><?= e($g['skills'] ?: '—') ?></dd>
                    <?php if ($g['is_armed']): ?><dt>Weapon licence</dt><dd><?= e($g['weapon_license_no'] ?: '—') ?></dd><?php endif; ?>
                    <dt>Joined</dt><dd><?= e(fmt_date($g['joined_on'])) ?></dd>
                    <dt>Weekly off</dt><dd><?= e(weekly_off_label($g['weekly_off'] ?? null)) ?></dd>
                    <?php $gl = q_all("SELECT from_date, to_date, leave_type, status FROM guard_leaves WHERE guard_id = ? AND status IN ('approved','pending') AND to_date >= CURDATE() ORDER BY from_date LIMIT 3", [$g['id']]); ?>
                    <dt>Upcoming leave</dt><dd><?php if (!$gl): ?>—<?php endif; foreach ($gl as $l): ?>
                        <div><?= e(fmt_date($l['from_date'], 'd M')) ?><?= $l['to_date'] !== $l['from_date'] ? ' – ' . e(fmt_date($l['to_date'], 'd M')) : '' ?> · <?= e(leave_type_label($l['leave_type'])) ?>
                            <?= $l['status'] === 'pending' ? '<span class="badge badge-soft-warning">Waiting approval</span>' : '' ?></div><?php endforeach; ?>
                        <a class="small" href="<?= url('admin/duty.php?tab=leave&guard_id=' . (int)$g['id']) ?>">Add / manage leave</a></dd>
                    <dt>Emergency</dt><dd><?= e($g['emergency_contact_name'] ?: '—') ?> <?= $g['emergency_contact_phone'] ? '· <a href="tel:' . e($g['emergency_contact_phone']) . '">' . e($g['emergency_contact_phone']) . '</a>' : '' ?></dd>
                    <dt>This month</dt><dd><?= (int)$month['shifts'] ?> shifts · <?= fmt_minutes((int)$month['mins']) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Guard Mobile App Onboarding & Installation Kit -->
        <div class="card mb-3 border-warning border-opacity-50 shadow-sm" id="onboardingKit">
            <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between py-2">
                <span class="fw-bold small"><i class="bi bi-phone-fill text-warning me-1"></i> Mobile App Onboarding Kit</span>
                <span class="badge bg-warning text-dark fw-bold">Active</span>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div id="guardQrCode" class="d-inline-block p-2 bg-white rounded border shadow-sm mb-1"></div>
                    <div class="small text-muted" style="font-size: .8rem;">Scan with guard's phone to open Duty App</div>
                </div>

                <div class="bg-light p-2 rounded mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">App Login ID:</span>
                        <span class="fw-bold"><code><?= e($g['employee_code']) ?></code></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Phone Login:</span>
                        <span class="fw-bold"><code><?= e($g['phone']) ?></code></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Duty PIN / Pass:</span>
                        <span class="fw-bold text-success"><code>123456</code> (or your custom PIN)</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Duty App:</span>
                        <a href="<?= url('guard/login.php?code=' . urlencode($g['employee_code'])) ?>" target="_blank" class="fw-semibold text-truncate" style="max-width: 170px;">
                            Open App <i class="bi bi-box-arrow-up-right small"></i>
                        </a>
                    </div>
                </div>

                <?php
                $guardAppUrl = url('guard/login.php?code=' . urlencode($g['employee_code']));
                $cleanPhone = preg_replace('/\D/', '', $g['phone']);
                $waMsg = "🛡️ *" . company_name() . " - Guard Duty App*\n"
                    . "Hello *" . $g['full_name'] . "*,\n"
                    . "You have been onboarded as a *" . guard_category_label($g['category'] ?? 'bodyguard') . "* (" . ($g['is_armed'] ? 'Armed' : 'Unarmed') . ").\n\n"
                    . "📲 *Step 1: Install your Guard App:*\n"
                    . $guardAppUrl . "\n"
                    . "(Open link in Chrome or Safari, then tap 'Install App' / 'Add to Home Screen')\n\n"
                    . "🔑 *Step 2: Sign In:*\n"
                    . "Login ID: *" . $g['employee_code'] . "* (or your phone " . $g['phone'] . ")\n"
                    . "Password / PIN: *123456* (or your assigned PIN)\n\n"
                    . "📍 *Step 3:* Tap 'Start Duty' when on site to begin shift tracking.";
                $waLink = "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($waMsg);
                ?>

                <div class="d-grid gap-2">
                    <a href="<?= $waLink ?>" target="_blank" class="btn btn-success btn-sm fw-semibold">
                        <i class="bi bi-whatsapp me-1"></i> Send App & Login via WhatsApp
                    </a>
                    <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-1"></i> Print Onboarding ID Pass
                    </button>
                    <a href="<?= url('guard/install.php') ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-question-circle me-1"></i> App Install & APK Guide
                    </a>
                </div>
            </div>
        </div>

        <!-- Printable Onboarding ID Pass (Hidden on screen, visible on Print) -->
        <div class="d-none d-print-block p-4" id="printDutyPass">
            <div style="border: 2px solid #0b1324; border-radius: 12px; padding: 18px; max-width: 360px; font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #fff;">
                <div style="text-align: center; border-bottom: 2px solid #f5b301; padding-bottom: 8px; margin-bottom: 12px;">
                    <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0b1324;"><?= e(company_name()) ?></h3>
                    <div style="font-size: 11px; letter-spacing: 1px; color: #64748b; font-weight: 700;">SECURITY GUARD DUTY PASS</div>
                </div>
                <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                    <div id="printQrCode"></div>
                    <div>
                        <div style="font-size: 15px; font-weight: 800; color: #0b1324;"><?= e($g['full_name']) ?></div>
                        <div style="font-size: 12px; color: #334155;">Badge ID: <strong><?= e($g['employee_code']) ?></strong></div>
                        <div style="font-size: 12px; color: #334155;">Role: <?= e(guard_category_label($g['category'] ?? 'bodyguard')) ?></div>
                        <div style="font-size: 12px; color: <?= $g['is_armed'] ? '#b91c1c' : '#475569' ?>; font-weight: 600;">
                            Status: <?= $g['is_armed'] ? 'ARMED WITH FIREARM' : 'UNARMED' ?>
                        </div>
                    </div>
                </div>
                <div style="font-size: 11px; line-height: 1.4; border-top: 1px dashed #cbd5e1; padding-top: 8px; color: #475569;">
                    <strong>Instructions:</strong><br>
                    1. Scan QR code or visit: <?= e(url('guard/login.php')) ?><br>
                    2. Tap "Install App" / "Add to Home Screen"<br>
                    3. Sign in with Badge ID: <strong><?= e($g['employee_code']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header">Current deployment</div>
            <div class="card-body">
                <?php if ($current): ?>
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        <div class="flex-fill">
                            <div class="hired-by mb-2 fs-6"><i class="bi bi-briefcase-fill"></i> Hired by <?= e($current['client_name']) ?></div>
                            <dl class="kv">
                                <dt>Site</dt><dd><?= e($current['site_name']) ?><div class="small text-muted"><?= e($current['site_address'] ?? '') ?></div></dd>
                                <dt>Period</dt><dd><?= e(fmt_date($current['start_date'])) ?> → <?= $current['end_date'] ? e(fmt_date($current['end_date'])) : 'Until further notice' ?></dd>
                                <dt>Shift</dt><dd><?= $current['shift_start'] ? e(fmt_time($current['shift_start']) . ' – ' . fmt_time($current['shift_end'])) : 'Not fixed' ?></dd>
                                <dt>Rate</dt><dd><?= money($current['rate']) ?> / <?= e(rate_unit($current['rate_type'])) ?></dd>
                                <dt>Geofence</dt><dd><?= (int)$current['geofence_radius_m'] ?> m radius</dd>
                            </dl>
                        </div>
                        <div class="d-flex flex-column gap-2">
                            <a href="<?= url('admin/client_view.php?id=' . $current['client_id']) ?>" class="btn btn-sm btn-outline-primary">View client</a>
                            <a href="<?= url('admin/assignment_view.php?id=' . $current['id']) ?>" class="btn btn-sm btn-outline-secondary">Manage deployment</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-muted">Not hired at the moment<?= $g['status'] === 'active' ? ' — available for deployment.' : '.' ?></div>
                <?php endif; ?>
                <?php if ($shift): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 mt-3 mb-0 py-2">
                        <i class="bi bi-broadcast"></i>
                        <div>On duty since <strong><?= e(fmt_datetime($shift['check_in_at'])) ?></strong> (<?= fmt_minutes((int)round((time() - strtotime($shift['check_in_at'])) / 60)) ?>)</div>
                        <form method="post" class="ms-auto" data-confirm="End this guard's shift now?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="force_checkout">
                            <button class="btn btn-sm btn-outline-success">Force check-out</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <span>Location & route</span>
                <span class="small text-muted">Last update: <?= e($g['last_seen_at'] ? fmt_datetime($g['last_seen_at']) . ' (' . time_ago($g['last_seen_at']) . ')' : 'never') ?></span>
                <form class="ms-auto d-flex gap-2" method="get">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="date" name="date" value="<?= e($trailDate) ?>" max="<?= date('Y-m-d') ?>" class="form-control form-control-sm">
                    <button class="btn btn-sm btn-outline-primary">Show route</button>
                </form>
            </div>
            <div class="card-body p-0"><div id="guardMap" style="height:360px"></div></div>
            <div class="card-footer bg-white small text-muted" id="trailInfo">Loading route…</div>
        </div>

        <div class="card table-card mb-3">
            <div class="card-header">Deployment history</div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Client</th><th>Site</th><th>Period</th><th>Rate</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$history): ?><tr><td colspan="5" class="text-center text-muted py-4">Never deployed yet</td></tr><?php endif; ?>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><a href="<?= url('admin/client_view.php?id=' . $h['client_id']) ?>" class="fw-semibold"><?= e($h['client_name']) ?></a></td>
                        <td><a href="<?= url('admin/assignment_view.php?id=' . $h['id']) ?>"><?= e($h['site_name']) ?></a></td>
                        <td class="small"><?= e(fmt_date($h['start_date'])) ?> → <?= $h['end_date'] ? e(fmt_date($h['end_date'])) : 'open' ?></td>
                        <td class="small"><?= money($h['rate']) ?> / <?= e(rate_unit($h['rate_type'])) ?></td>
                        <td><?= status_badge($h['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>

        <div class="card table-card mb-3">
            <div class="card-header d-flex">Recent attendance <a class="ms-auto small" href="<?= url('admin/attendance.php?guard_id=' . $id) ?>">All attendance</a></div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Check in</th><th>Check out</th><th>Duration</th><th>Client</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$attendance): ?><tr><td colspan="5" class="text-center text-muted py-4">No shifts recorded yet</td></tr><?php endif; ?>
                <?php foreach ($attendance as $a): ?>
                    <tr>
                        <td class="small"><?= e(fmt_datetime($a['check_in_at'])) ?><?= $a['check_in_distance_m'] !== null ? '<div class="text-muted">' . (int)$a['check_in_distance_m'] . ' m from site</div>' : '' ?></td>
                        <td class="small"><?= e(fmt_datetime($a['check_out_at'])) ?></td>
                        <td><?= $a['status'] === 'open' ? '<span class="text-success fw-semibold">On duty</span>' : fmt_minutes($a['duration_minutes'] !== null ? (int)$a['duration_minutes'] : null) ?></td>
                        <td class="small"><?= e($a['client_name'] ?? '—') ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>

        <?php if ($alerts): ?>
        <div class="card table-card">
            <div class="card-header">Alerts</div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Type</th><th>When</th><th>Client</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($alerts as $a): ?>
                    <tr>
                        <td><?= status_badge($a['type']) ?></td>
                        <td class="small"><?= e(fmt_datetime($a['created_at'])) ?></td>
                        <td class="small"><?= e($a['client_name'] ?? '—') ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td class="text-end"><a href="<?= url('admin/alerts.php?id=' . $a['id']) ?>" class="btn btn-sm btn-light">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$mapData = [
    'guard' => ['id' => $id, 'name' => $g['full_name'], 'lat' => $g['last_lat'] !== null ? (float)$g['last_lat'] : null,
                'lng' => $g['last_lng'] !== null ? (float)$g['last_lng'] : null, 'status' => $live, 'accuracy' => $g['last_accuracy']],
    'site'  => $current && $current['site_lat'] !== null ? ['lat' => (float)$current['site_lat'], 'lng' => (float)$current['site_lng'],
                'radius' => (int)$current['geofence_radius_m'], 'name' => $current['site_name']] : null,
    'date'  => $trailDate,
];
$inlineScript = '<script src="' . asset('js/qrcode.min.js') . '"></script><script>
document.addEventListener("DOMContentLoaded", async function () {
    const appUrl = ' . json_encode($guardAppUrl) . ';
    const qrEl = document.getElementById("guardQrCode");
    if (qrEl && typeof QRCode !== "undefined") {
        new QRCode(qrEl, { text: appUrl, width: 130, height: 130, colorDark: "#0b1324", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.M });
    }
    const printQrEl = document.getElementById("printQrCode");
    if (printQrEl && typeof QRCode !== "undefined") {
        new QRCode(printQrEl, { text: appUrl, width: 85, height: 85, colorDark: "#0b1324", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.M });
    }

    const D = ' . json_encode($mapData) . ';
    const map = APP.map.create("guardMap");
    const pts = [];
    if (D.site) {
        L.marker([D.site.lat, D.site.lng], { icon: APP.map.siteIcon() }).bindTooltip(APP.esc(D.site.name)).addTo(map);
        L.circle([D.site.lat, D.site.lng], { radius: D.site.radius, color: "#1d4ed8", weight: 1.5, dashArray: "4 4", fillOpacity: .06 }).addTo(map);
        pts.push([D.site.lat, D.site.lng]);
    }
    if (D.guard.lat !== null) {
        L.marker([D.guard.lat, D.guard.lng], { icon: APP.map.guardIcon(D.guard.status, D.guard.name), zIndexOffset: 1000 }).addTo(map)
            .bindPopup("<strong>" + APP.esc(D.guard.name) + "</strong><br>Last known position");
        if (D.guard.accuracy) L.circle([D.guard.lat, D.guard.lng], { radius: D.guard.accuracy, color: "#16a34a", weight: 1, fillOpacity: .08 }).addTo(map);
        pts.push([D.guard.lat, D.guard.lng]);
    }
    const info = document.getElementById("trailInfo");
    try {
        const t = await APP.api("api/admin/trail.php?guard_id=" + D.guard.id + "&date=" + D.date);
        if (t.points.length > 1) {
            const ll = t.points.map(p => [p.lat, p.lng]);
            L.polyline(ll, { color: "#7c3aed", weight: 4, opacity: .8 }).addTo(map);
            L.circleMarker(ll[0], { radius: 6, color: "#16a34a", fillOpacity: 1 }).bindTooltip("Start " + t.points[0].time).addTo(map);
            L.circleMarker(ll[ll.length - 1], { radius: 6, color: "#dc2626", fillOpacity: 1 }).bindTooltip("Last " + t.points[t.points.length - 1].time).addTo(map);
            pts.push(...ll);
            info.textContent = "Route on " + t.date + ": " + t.points.length + " points, " + t.points[0].time + " – " + t.points[t.points.length - 1].time + ", about " + t.distance_km + " km travelled.";
        } else {
            info.textContent = "No route recorded on " + t.date + ". Routes are recorded while the guard is on duty with the app open.";
        }
    } catch (e) { info.textContent = "Could not load route."; }
    if (pts.length) map.fitBounds(pts, { padding: [40, 40], maxZoom: 16 });
});
</script>';
require APP_ROOT . '/includes/layout/footer.php';
