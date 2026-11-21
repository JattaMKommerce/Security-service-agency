<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$id = get_int('id');
$a = q_one('SELECT a.*, g.full_name, g.employee_code, g.phone AS guard_phone, g.photo, g.is_on_duty,
                   c.name AS client_name, c.client_code, c.phone AS client_phone, c.contact_person,
                   ad.name AS created_by_name
              FROM assignments a JOIN guards g ON g.id = a.guard_id JOIN clients c ON c.id = a.client_id
              LEFT JOIN admins ad ON ad.id = a.created_by WHERE a.id = ?', [$id]);
if (!$a) {
    flash('danger', 'Deployment not found.');
    redirect('admin/assignments.php');
}

if (is_post()) {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($a['status'] === 'active' && in_array($action, ['complete', 'cancel'], true)) {
        db_tx(function () use ($a, $action) {
            $endDate = $action === 'complete' ? (($a['end_date'] && $a['end_date'] < date('Y-m-d')) ? $a['end_date'] : date('Y-m-d')) : $a['end_date'];
            q("UPDATE assignments SET status = ?, ended_at = ?, end_date = ? WHERE id = ? AND status = 'active'",
              [$action === 'complete' ? 'completed' : 'cancelled', now(), $endDate, $a['id']]);
        });
        // If the guard is on duty at this client, close their shift too.
        $open = guard_open_shift((int)$a['guard_id']);
        if ($open && (int)$open['assignment_id'] === (int)$a['id']) {
            end_shift((int)$a['guard_id'], null, null, (int)$admin['id'], 'Deployment ended by admin');
        }
        q("UPDATE alerts SET status = 'resolved', resolved_at = ?, resolved_by = ?, resolution_note = 'Deployment ended'
            WHERE assignment_id = ? AND type = 'geofence_exit' AND status <> 'resolved'", [now(), $admin['id'], $a['id']]);
        log_activity('assignment.' . $action, "#{$a['id']} {$a['employee_code']} @ {$a['client_name']}");
        flash('success', $action === 'complete'
            ? $a['full_name'] . ' is released from ' . $a['client_name'] . ' and is available again.'
            : 'Deployment cancelled. ' . $a['full_name'] . ' is available again.');
        redirect('admin/assignment_view.php?id=' . $id);
    }
}

$att = q_one('SELECT COUNT(*) shifts, COUNT(DISTINCT DATE(check_in_at)) days, COALESCE(SUM(duration_minutes),0) mins
                FROM attendance WHERE assignment_id = ?', [$id]);
$shifts = q_all('SELECT * FROM attendance WHERE assignment_id = ? ORDER BY check_in_at DESC LIMIT 20', [$id]);
$alerts = q_all('SELECT * FROM alerts WHERE assignment_id = ? ORDER BY id DESC LIMIT 10', [$id]);

$pageTitle = 'Deployment #' . $id;
$activeNav = 'assignments';
$useMap = true;
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions">
    <a href="<?= url('admin/assignments.php') ?>" class="small"><i class="bi bi-arrow-left"></i> All deployments</a>
    <?php if ($a['status'] === 'active'): ?>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('admin/assignment_form.php?id=' . $id) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit site / shift / rate</a>
            <form method="post" data-confirm="End this deployment? The guard will be released and become available."><?= csrf_field() ?>
                <input type="hidden" name="action" value="complete"><button class="btn btn-primary btn-sm"><i class="bi bi-flag"></i> End deployment</button></form>
            <form method="post" data-confirm="Cancel this deployment (e.g. created by mistake)?"><?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger btn-sm">Cancel</button></form>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="hired-by fs-6"><i class="bi bi-briefcase-fill"></i> Hired by <?= e($a['client_name']) ?></div>
                    <span class="ms-auto"><?= status_badge($a['status']) ?></span>
                </div>
                <a class="person mb-3" href="<?= url('admin/guard_view.php?id=' . $a['guard_id']) ?>"><?= avatar($a['full_name'], $a['photo'], 'lg') ?>
                    <span><span class="name d-block fs-6"><?= e($a['full_name']) ?></span><span class="sub"><?= e($a['employee_code']) ?> · <?= e($a['guard_phone']) ?></span></span></a>
                <dl class="kv">
                    <dt>Client contact</dt><dd><?= e($a['contact_person'] ?: '—') ?> · <a href="tel:<?= e($a['client_phone']) ?>"><?= e($a['client_phone']) ?></a></dd>
                    <dt>Site</dt><dd><?= e($a['site_name']) ?><div class="small text-muted"><?= e($a['site_address'] ?? '') ?></div></dd>
                    <dt>Period</dt><dd><?= e(fmt_date($a['start_date'])) ?> → <?= $a['end_date'] ? e(fmt_date($a['end_date'])) : 'Until further notice' ?></dd>
                    <dt>Shift</dt><dd><?= $a['shift_start'] ? e(fmt_time($a['shift_start']) . ' – ' . fmt_time($a['shift_end'])) : 'Not fixed' ?></dd>
                    <dt>Rate</dt><dd><?= money($a['rate']) ?> / <?= e(rate_unit($a['rate_type'])) ?></dd>
                    <dt>Geofence</dt><dd><?= (int)$a['geofence_radius_m'] ?> m</dd>
                    <dt>Worked</dt><dd><?= (int)$att['days'] ?> days · <?= (int)$att['shifts'] ?> shifts · <?= fmt_minutes((int)$att['mins']) ?></dd>
                    <dt>Created</dt><dd><?= e(fmt_datetime($a['created_at'])) ?><?= $a['created_by_name'] ? ' by ' . e($a['created_by_name']) : '' ?></dd>
                    <?php if ($a['ended_at']): ?><dt>Ended</dt><dd><?= e(fmt_datetime($a['ended_at'])) ?></dd><?php endif; ?>
                    <?php if ($a['notes']): ?><dt>Notes</dt><dd><?= nl2br(e($a['notes'])) ?></dd><?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card mb-3"><div class="card-body p-0"><div id="asgMap" class="map-box border-0"></div></div></div>
        <div class="card table-card mb-3">
            <div class="card-header">Shifts at this deployment</div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Check in</th><th>Check out</th><th>Duration</th><th>Distance at check-in</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$shifts): ?><tr><td colspan="5" class="text-center text-muted py-4">No shifts yet</td></tr><?php endif; ?>
                <?php foreach ($shifts as $s): ?>
                    <tr>
                        <td class="small"><?= e(fmt_datetime($s['check_in_at'])) ?></td>
                        <td class="small"><?= e(fmt_datetime($s['check_out_at'])) ?></td>
                        <td><?= $s['status'] === 'open' ? '<span class="text-success fw-semibold">On duty</span>' : fmt_minutes($s['duration_minutes'] !== null ? (int)$s['duration_minutes'] : null) ?></td>
                        <td class="small"><?= $s['check_in_distance_m'] !== null ? (int)$s['check_in_distance_m'] . ' m' : '—' ?></td>
                        <td><?= status_badge($s['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
        <?php if ($alerts): ?>
            <div class="card table-card">
                <div class="card-header">Alerts</div>
                <div class="table-responsive"><table class="table table-hover"><tbody>
                    <?php foreach ($alerts as $al): ?>
                        <tr><td><?= status_badge($al['type']) ?></td><td class="small"><?= e($al['message']) ?></td><td class="small"><?= e(fmt_datetime($al['created_at'])) ?></td><td><?= status_badge($al['status']) ?></td>
                            <td><a class="btn btn-sm btn-light" href="<?= url('admin/alerts.php?id=' . $al['id']) ?>">Open</a></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$inlineScript = '<script>document.addEventListener("DOMContentLoaded", function () {
    const s = ' . json_encode(['lat' => $a['site_lat'] !== null ? (float)$a['site_lat'] : null, 'lng' => $a['site_lng'] !== null ? (float)$a['site_lng'] : null,
                               'r' => (int)$a['geofence_radius_m'], 'name' => $a['site_name']]) . ';
    if (s.lat === null) { document.getElementById("asgMap").innerHTML = "<div class=\"empty-state\">No site location set</div>"; return; }
    const map = APP.map.create("asgMap", { lat: s.lat, lng: s.lng, zoom: 16 });
    L.marker([s.lat, s.lng], { icon: APP.map.siteIcon() }).bindTooltip(APP.esc(s.name), { permanent: true, direction: "top", offset: [0, -12] }).addTo(map);
    const c = L.circle([s.lat, s.lng], { radius: s.r, color: "#1d4ed8", weight: 2, fillOpacity: .08 }).addTo(map);
    map.fitBounds(c.getBounds(), { padding: [30, 30] });
});</script>';
require APP_ROOT . '/includes/layout/footer.php';
