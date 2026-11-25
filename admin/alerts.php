<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post()) {
    require_csrf();
    $aid = (int)post_int('id', 0);
    $action = $_POST['action'] ?? '';
    $al = q_one('SELECT * FROM alerts WHERE id = ?', [$aid]);
    if ($al && $action === 'acknowledge' && $al['status'] === 'open') {
        q("UPDATE alerts SET status = 'acknowledged', acknowledged_by = ?, acknowledged_at = ? WHERE id = ?", [$admin['id'], now(), $aid]);
        log_activity('alert.acknowledge', "#$aid {$al['type']}");
        flash('success', 'Alert acknowledged. The guard sees that help is on the way.');
    } elseif ($al && $action === 'resolve' && $al['status'] !== 'resolved') {
        $note = post_str('note', 255) ?? 'Resolved';
        q("UPDATE alerts SET status = 'resolved', resolved_by = ?, resolved_at = ?, resolution_note = ?,
                  acknowledged_by = COALESCE(acknowledged_by, ?), acknowledged_at = COALESCE(acknowledged_at, ?) WHERE id = ?",
          [$admin['id'], now(), $note, $admin['id'], now(), $aid]);
        log_activity('alert.resolve', "#$aid {$al['type']}: $note");
        flash('success', 'Alert resolved.');
    }
    redirect('admin/alerts.php?id=' . $aid);
}

$detail = null;
if (($id = get_int('id')) > 0) {
    $detail = q_one('SELECT al.*, g.full_name, g.employee_code, g.phone, g.photo, g.last_lat, g.last_lng, g.last_seen_at,
                            g.emergency_contact_name, g.emergency_contact_phone,
                            c.name AS client_name, c.phone AS client_phone, c.contact_person,
                            a.site_name, a.site_lat, a.site_lng, a.geofence_radius_m,
                            ak.name AS ack_name, rs.name AS res_name
                       FROM alerts al JOIN guards g ON g.id = al.guard_id
                       LEFT JOIN clients c ON c.id = al.client_id LEFT JOIN assignments a ON a.id = al.assignment_id
                       LEFT JOIN admins ak ON ak.id = al.acknowledged_by LEFT JOIN admins rs ON rs.id = al.resolved_by
                      WHERE al.id = ?', [$id]);
}

$status = get_str('status', 20) ?: 'unresolved';
$type = get_str('type', 20);
$where = ['1=1'];
$params = [];
if ($status === 'unresolved') $where[] = "al.status <> 'resolved'";
elseif (in_array($status, ['open', 'acknowledged', 'resolved'], true)) { $where[] = 'al.status = ?'; $params[] = $status; }
if (in_array($type, ALERT_TYPES, true)) { $where[] = 'al.type = ?'; $params[] = $type; }
$p = paginate('FROM alerts al JOIN guards g ON g.id = al.guard_id LEFT JOIN clients c ON c.id = al.client_id WHERE ' . implode(' AND ', $where),
    $params, 'al.*, g.full_name, g.employee_code, c.name AS client_name', "(al.status = 'open') DESC, al.id DESC", 30);

$alertStats = q_one("SELECT
    COUNT(*) total,
    COALESCE(SUM(status <> 'resolved'), 0) unresolved_cnt,
    COALESCE(SUM(type = 'sos' AND status <> 'resolved'), 0) sos_cnt,
    COALESCE(SUM(type <> 'sos' AND status <> 'resolved'), 0) geo_cnt,
    COALESCE(SUM(status = 'resolved'), 0) resolved_cnt
FROM alerts");

$pageTitle = 'Alerts';
$activeNav = 'alerts';
$useMap = (bool)$detail;
require APP_ROOT . '/includes/layout/header.php';
?>

<!-- 1. Hero Banner (Clean SaaS Look) -->
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Security &amp; SOS Alerts</h2>
        <p class="guard-hero-sub">Emergency panic signals, duty perimeter geofence exits, and incident response</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(220,38,38,0.2);">
        <i class="bi bi-exclamation-octagon-fill"></i>
    </div>
</div>

<!-- 2. Summary Statistics Section (4 Clean Cards) -->
<div class="stat-grid-4">
    <!-- Unresolved -->
    <a href="?status=unresolved" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-danger bg-danger-subtle">
            <i class="bi bi-bell-fill"></i>
        </div>
        <div class="stat-box-num"><?= (int)$alertStats['unresolved_cnt'] ?></div>
        <div class="stat-box-lbl">Unresolved</div>
    </a>
    <!-- SOS Alarms -->
    <a href="?status=unresolved&type=sos" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-danger" style="background:rgba(220,38,38,0.18);">
            <i class="bi bi-exclamation-octagon-fill"></i>
        </div>
        <div class="stat-box-num text-danger"><?= (int)$alertStats['sos_cnt'] ?></div>
        <div class="stat-box-lbl">SOS Alarms</div>
    </a>
    <!-- Left Site -->
    <a href="?status=unresolved" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-warning bg-warning-subtle">
            <i class="bi bi-sign-turn-right-fill"></i>
        </div>
        <div class="stat-box-num"><?= (int)$alertStats['geo_cnt'] ?></div>
        <div class="stat-box-lbl">Site &amp; Tracking</div>
    </a>
    <!-- Resolved -->
    <a href="?status=resolved" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-success bg-success-subtle">
            <i class="bi bi-check2-circle"></i>
        </div>
        <div class="stat-box-num"><?= (int)$alertStats['resolved_cnt'] ?></div>
        <div class="stat-box-lbl">Resolved</div>
    </a>
</div>

<?php if ($detail): $isSos = $detail['type'] === 'sos'; [$dTitle, $dIcon] = alert_type_meta($detail['type']); ?>
<div class="card mb-3 border-<?= $isSos ? 'danger' : 'warning' ?>">
    <div class="card-header d-flex align-items-center gap-2 <?= $isSos && $detail['status'] !== 'resolved' ? 'text-bg-danger' : '' ?>">
        <i class="bi <?= $dIcon ?>"></i>
        <strong><?= $isSos ? 'SOS / Panic alert' : ($detail['type'] === 'geofence_exit' ? 'Guard left the duty site' : e($dTitle)) ?> #<?= (int)$detail['id'] ?></strong>
        <span class="ms-auto"><?= status_badge($detail['status']) ?></span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-5">
                <a class="person mb-3" href="<?= url('admin/guard_view.php?id=' . $detail['guard_id']) ?>"><?= avatar($detail['full_name'], $detail['photo'], 'lg') ?>
                    <span><span class="name d-block fs-6"><?= e($detail['full_name']) ?></span><span class="sub"><?= e($detail['employee_code']) ?></span></span></a>
                <dl class="kv mb-3">
                    <dt>Raised</dt><dd><?= e(fmt_datetime($detail['created_at'])) ?> (<?= e(time_ago($detail['created_at'])) ?>)</dd>
                    <dt>Message</dt><dd><?= e($detail['message'] ?: '—') ?></dd>
                    <dt>Hired by</dt><dd><?= e($detail['client_name'] ?? '—') ?><?= $detail['site_name'] ? ' · ' . e($detail['site_name']) : '' ?></dd>
                    <?php if ($detail['distance_m'] !== null): ?><dt>Distance</dt><dd><?= (int)$detail['distance_m'] ?> m from site (allowed <?= (int)$detail['geofence_radius_m'] ?> m)</dd><?php endif; ?>
                    <dt>Alert location</dt><dd><?= $detail['lat'] !== null ? e($detail['lat'] . ', ' . $detail['lng']) . ' · <a target="_blank" rel="noopener" href="https://www.openstreetmap.org/?mlat=' . e($detail['lat']) . '&mlon=' . e($detail['lng']) . '#map=18/' . e($detail['lat']) . '/' . e($detail['lng']) . '">open map</a>' : 'Not available' ?></dd>
                    <dt>Live now</dt><dd><?= e(time_ago($detail['last_seen_at'])) ?></dd>
                    <?php if ($detail['ack_name']): ?><dt>Acknowledged</dt><dd><?= e($detail['ack_name']) ?> · <?= e(fmt_datetime($detail['acknowledged_at'])) ?></dd><?php endif; ?>
                    <?php if ($detail['status'] === 'resolved'): ?><dt>Resolved</dt><dd><?= e($detail['res_name'] ?? 'System') ?> · <?= e(fmt_datetime($detail['resolved_at'])) ?><div class="small text-muted"><?= e($detail['resolution_note']) ?></div></dd><?php endif; ?>
                </dl>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="tel:<?= e($detail['phone']) ?>" class="btn btn-success btn-sm"><i class="bi bi-telephone"></i> Call guard</a>
                    <?php if ($detail['client_phone']): ?><a href="tel:<?= e($detail['client_phone']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-telephone"></i> Call client</a><?php endif; ?>
                    <?php if ($detail['emergency_contact_phone']): ?><a href="tel:<?= e($detail['emergency_contact_phone']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-telephone"></i> Emergency contact</a><?php endif; ?>
                    <a href="<?= url('admin/tracking.php?guard_id=' . $detail['guard_id']) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-geo-alt"></i> Track live</a>
                </div>
                <?php if ($detail['status'] !== 'resolved'): ?>
                    <?php if ($detail['status'] === 'open'): ?>
                        <form method="post" class="mb-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><input type="hidden" name="action" value="acknowledge">
                            <button class="btn btn-warning w-100"><i class="bi bi-hand-thumbs-up"></i> Acknowledge — help is on the way</button></form>
                    <?php endif; ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><input type="hidden" name="action" value="resolve">
                        <div class="input-group"><input name="note" class="form-control" placeholder="What happened / action taken" maxlength="255">
                            <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Resolve</button></div></form>
                <?php endif; ?>
            </div>
            <div class="col-lg-7"><div id="alertMap" class="map-box"></div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 3. Clean Filter Tabs & Status Pills -->
<div class="filter-card-clean mb-3 p-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex gap-1 flex-wrap">
            <?php foreach (['unresolved' => 'Unresolved', 'resolved' => 'Resolved', 'all' => 'All'] as $k => $lbl): ?>
                <a class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3 py-1" href="?status=<?= $k ?>&type=<?= e($type) ?>">
                    <?= $lbl ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="btn-group btn-group-sm flex-wrap">
            <a href="?status=<?= e($status) ?>" class="btn <?= $type === '' ? 'btn-secondary text-white' : 'btn-outline-secondary' ?>">All</a>
            <a href="?status=<?= e($status) ?>&type=sos" class="btn <?= $type === 'sos' ? 'btn-danger text-white' : 'btn-outline-danger' ?>">SOS</a>
            <a href="?status=<?= e($status) ?>&type=geofence_exit" class="btn <?= $type === 'geofence_exit' ? 'btn-warning text-dark' : 'btn-outline-warning' ?>">Left site</a>
            <a href="?status=<?= e($status) ?>&type=late" class="btn <?= $type === 'late' ? 'btn-warning text-dark' : 'btn-outline-warning' ?>">Late</a>
            <a href="?status=<?= e($status) ?>&type=no_movement" class="btn <?= $type === 'no_movement' ? 'btn-warning text-dark' : 'btn-outline-warning' ?>">Not moving</a>
            <a href="?status=<?= e($status) ?>&type=gps_off" class="btn <?= $type === 'gps_off' ? 'btn-danger text-white' : 'btn-outline-danger' ?>">GPS off</a>
            <a href="?status=<?= e($status) ?>&type=offline" class="btn <?= $type === 'offline' ? 'btn-danger text-white' : 'btn-outline-danger' ?>">No signal</a>
        </div>
    </div>
</div>

<!-- 4. Section Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fs-5 fw-bold m-0 text-dark">Alert Records (<?= (int)$p['total'] ?>)</h2>
</div>

<!-- 5. MOBILE ALERT CARDS (Visible only on mobile devices) -->
<div class="d-md-none">
    <?php if (!$p['rows']): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center">
            <i class="bi bi-shield-check fs-2 text-success mb-2 d-block"></i>
            <div class="fw-semibold text-dark">No alerts found</div>
            <div class="small text-muted">All posts are normal and secure.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($p['rows'] as $a):
        $isSosAlert = $a['type'] === 'sos';
    ?>
        <div class="guard-mobile-card mb-2" style="<?= $a['status'] === 'open' && $isSosAlert ? 'border-color:#fca5a5;background:#fff5f5;' : '' ?>">
            <div class="d-flex align-items-center gap-3">
                <!-- Icon -->
                <div class="flex-shrink-0">
                    <span class="guard-avatar-initials-sm" style="background:<?= $isSosAlert ? '#dc2626' : '#d97706' ?>;font-size:1.2rem;">
                        <i class="bi <?= alert_type_meta($a['type'])[1] ?>"></i>
                    </span>
                </div>

                <!-- Middle Content -->
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <strong class="text-truncate text-dark" style="font-size:0.92rem;"><?= e($a['full_name']) ?></strong>
                        <?= status_badge($a['status']) ?>
                    </div>
                    <div class="small text-muted text-truncate">
                        <?= e($a['employee_code']) ?><?= $a['client_name'] ? ' · ' . e($a['client_name']) : '' ?>
                    </div>
                    <div class="small fw-semibold text-truncate mt-1 <?= $isSosAlert ? 'text-danger' : 'text-dark' ?>">
                        <?= e($a['message'] ?: ($isSosAlert ? 'SOS panic alert triggered' : alert_type_meta($a['type'])[0])) ?>
                    </div>
                    <div class="small text-muted mt-1" style="font-size:0.72rem;">
                        <i class="bi bi-clock me-1"></i><?= e(time_ago($a['created_at'])) ?> (<?= e(fmt_datetime($a['created_at'])) ?>)
                    </div>
                </div>

                <!-- Right Side: Open Link -->
                <a href="<?= url('admin/alerts.php?id=' . $a['id'] . '&status=' . urlencode($status)) ?>" class="btn btn-sm <?= $isSosAlert && $a['status'] === 'open' ? 'btn-danger' : 'btn-outline-primary' ?> rounded-pill px-3 py-1 flex-shrink-0 shadow-sm" style="font-size:0.75rem;">
                    View
                </a>
            </div>
        </div>
    <?php endforeach; ?>
    <?= pagination_links($p) ?>
</div>

<!-- 6. DESKTOP TABLE (Visible on tablets and desktop screens) -->
<div class="card table-card d-none d-md-block mb-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Bodyguard</th>
                    <th>Client</th>
                    <th>Details</th>
                    <th>Raised</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-shield-check"></i>No alerts here</div></td></tr>
            <?php endif; ?>
            <?php foreach ($p['rows'] as $a): ?>
                <tr class="<?= $a['status'] === 'open' && $a['type'] === 'sos' ? 'table-danger' : '' ?>">
                    <td><?= status_badge($a['type']) ?></td>
                    <td><a href="<?= url('admin/guard_view.php?id=' . $a['guard_id']) ?>" class="fw-semibold"><?= e($a['full_name']) ?></a><div class="small text-muted"><?= e($a['employee_code']) ?></div></td>
                    <td class="small"><?= e($a['client_name'] ?? '—') ?></td>
                    <td class="small"><?= e($a['message']) ?></td>
                    <td class="small text-nowrap"><?= e(fmt_datetime($a['created_at'])) ?><div class="text-muted"><?= e(time_ago($a['created_at'])) ?></div></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="text-end"><a href="<?= url('admin/alerts.php?id=' . $a['id'] . '&status=' . urlencode($status)) ?>" class="btn btn-sm btn-light">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?= pagination_links($p) ?></div>
</div>
<?php
if ($detail) {
    $d = [
        'alert' => $detail['lat'] !== null ? [(float)$detail['lat'], (float)$detail['lng']] : null,
        'live'  => $detail['last_lat'] !== null ? [(float)$detail['last_lat'], (float)$detail['last_lng']] : null,
        'site'  => $detail['site_lat'] !== null ? [(float)$detail['site_lat'], (float)$detail['site_lng'], (int)$detail['geofence_radius_m'], $detail['site_name']] : null,
        'name'  => $detail['full_name'],
        'sos'   => $detail['type'] === 'sos',
    ];
    $inlineScript = '<script>document.addEventListener("DOMContentLoaded", function () {
        const D = ' . json_encode($d) . ';
        const map = APP.map.create("alertMap");
        const pts = [];
        if (D.site) { L.marker([D.site[0], D.site[1]], { icon: APP.map.siteIcon() }).bindTooltip(APP.esc(D.site[3])).addTo(map);
            L.circle([D.site[0], D.site[1]], { radius: D.site[2], color: "#1d4ed8", weight: 1.5, dashArray: "4 4", fillOpacity: .06 }).addTo(map); pts.push([D.site[0], D.site[1]]); }
        if (D.alert) { L.marker(D.alert, { icon: APP.map.guardIcon(D.sos ? "sos" : "outside", D.name), zIndexOffset: 1000 }).bindPopup("Alert location").addTo(map); pts.push(D.alert); }
        if (D.live) { L.circleMarker(D.live, { radius: 8, color: "#16a34a", fillColor: "#22c55e", fillOpacity: .9 }).bindTooltip("Current position").addTo(map); pts.push(D.live); }
        if (pts.length) map.fitBounds(pts, { padding: [50, 50], maxZoom: 17 });
    });</script>';
}
require APP_ROOT . '/includes/layout/footer.php';
