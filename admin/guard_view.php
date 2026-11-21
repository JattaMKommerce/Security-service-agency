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
        $pw = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 8);
        q('UPDATE guards SET password_hash = ?, remember_hash = NULL, remember_expires = NULL WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
        clear_guard_login_lock($id);
        log_activity('guard.reset_password', $g['employee_code']);
        flash('success', 'New app password for ' . $g['full_name'] . ': ' . $pw . ' — share it with the guard. It will not be shown again.');
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
        <form method="post" class="d-inline" data-confirm="Generate a new app password for this guard?">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-key"></i> Reset app password</button>
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
                    <dt>Skills</dt><dd><?= e($g['skills'] ?: '—') ?></dd>
                    <?php if ($g['is_armed']): ?><dt>Weapon licence</dt><dd><?= e($g['weapon_license_no'] ?: '—') ?></dd><?php endif; ?>
                    <dt>Joined</dt><dd><?= e(fmt_date($g['joined_on'])) ?></dd>
                    <dt>Emergency</dt><dd><?= e($g['emergency_contact_name'] ?: '—') ?> <?= $g['emergency_contact_phone'] ? '· <a href="tel:' . e($g['emergency_contact_phone']) . '">' . e($g['emergency_contact_phone']) . '</a>' : '' ?></dd>
                    <dt>This month</dt><dd><?= (int)$month['shifts'] ?> shifts · <?= fmt_minutes((int)$month['mins']) ?></dd>
                </dl>
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
$inlineScript = '<script>
document.addEventListener("DOMContentLoaded", async function () {
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
