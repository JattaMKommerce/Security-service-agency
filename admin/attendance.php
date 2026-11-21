<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

if (is_post() && ($_POST['action'] ?? '') === 'close') {
    require_csrf();
    $att = q_one("SELECT guard_id FROM attendance WHERE id = ? AND status = 'open'", [(int)post_int('id', 0)]);
    if ($att) {
        $r = end_shift((int)$att['guard_id'], null, null, (int)$admin['id'], 'Checked out by admin ' . $admin['name']);
        log_activity('attendance.force_checkout', 'attendance #' . (int)post_int('id', 0));
        flash('success', 'Shift closed' . ($r ? ' (' . fmt_minutes($r['duration_minutes']) . ').' : '.'));
    }
    redirect(safe_back_url('admin/attendance.php'));
}

$guardId = get_int('guard_id');
$clientId = get_int('client_id');
$from = get_str('from', 10);
$to = get_str('to', 10);
// For one guard or client show the last 30 days; otherwise today.
if (!valid_date($from)) $from = ($guardId || $clientId) ? date('Y-m-d', strtotime('-30 days')) : date('Y-m-d');
if (!valid_date($to)) $to = date('Y-m-d');
if ($to < $from) [$from, $to] = [$to, $from];
$status = get_str('status', 20);

$where = ['at.check_in_at >= ?', 'at.check_in_at < ?'];
$params = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
if ($guardId) { $where[] = 'at.guard_id = ?'; $params[] = $guardId; }
if ($clientId) { $where[] = 'at.client_id = ?'; $params[] = $clientId; }
if (in_array($status, ['open', 'closed', 'auto_closed'], true)) { $where[] = 'at.status = ?'; $params[] = $status; }
$fromSql = 'FROM attendance at JOIN guards g ON g.id = at.guard_id LEFT JOIN clients c ON c.id = at.client_id
            LEFT JOIN assignments a ON a.id = at.assignment_id WHERE ' . implode(' AND ', $where);

$summary = q_one("SELECT COUNT(*) shifts, COUNT(DISTINCT at.guard_id) guards,
                         COALESCE(SUM(COALESCE(at.duration_minutes, TIMESTAMPDIFF(MINUTE, at.check_in_at, NOW()))),0) mins,
                         SUM(at.status = 'open') open_now $fromSql", $params);
$p = paginate($fromSql, $params,
    'at.*, g.full_name, g.employee_code, g.photo, c.name AS client_name, a.site_name, a.shift_start',
    'at.check_in_at DESC', 40);

$guards = q_all('SELECT id, full_name, employee_code FROM guards ORDER BY full_name');
$clients = q_all('SELECT id, name FROM clients ORDER BY name');

$pageTitle = 'Attendance';
$activeNav = 'attendance';
require APP_ROOT . '/includes/layout/header.php';
?>
<form class="filter-bar mb-3" method="get">
    <div class="input-group" style="width:auto">
        <span class="input-group-text">From</span><input type="date" name="from" value="<?= e($from) ?>" class="form-control">
        <span class="input-group-text">To</span><input type="date" name="to" value="<?= e($to) ?>" class="form-control">
    </div>
    <select name="guard_id" class="form-select">
        <option value="">All bodyguards</option>
        <?php foreach ($guards as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $guardId === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['full_name']) ?> (<?= e($g['employee_code']) ?>)</option><?php endforeach; ?>
    </select>
    <select name="client_id" class="form-select">
        <option value="">All clients</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="status" class="form-select">
        <option value="">Any status</option>
        <?php foreach (['open' => 'On duty now', 'closed' => 'Closed', 'auto_closed' => 'Auto-closed'] as $k => $lbl): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Show</button>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card stat-card"><span class="stat-icon tint-blue"><i class="bi bi-calendar-check"></i></span><div><div class="stat-value"><?= (int)$summary['shifts'] ?></div><div class="stat-label">Shifts</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><span class="stat-icon tint-violet"><i class="bi bi-people"></i></span><div><div class="stat-value"><?= (int)$summary['guards'] ?></div><div class="stat-label">Guards</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><span class="stat-icon tint-green"><i class="bi bi-clock-history"></i></span><div><div class="stat-value"><?= round((int)$summary['mins'] / 60, 1) ?></div><div class="stat-label">Hours on duty</div></div></div></div>
    <div class="col-6 col-md-3"><div class="card stat-card"><span class="stat-icon tint-amber"><i class="bi bi-broadcast"></i></span><div><div class="stat-value"><?= (int)$summary['open_now'] ?></div><div class="stat-label">Still on duty</div></div></div></div>
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Bodyguard</th><th>Client / site</th><th>Check in</th><th>Check out</th><th>Duration</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$p['rows']): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i>No shifts in this period</div></td></tr><?php endif; ?>
            <?php foreach ($p['rows'] as $r):
                $late = $r['shift_start'] && strtotime(date('H:i:s', strtotime($r['check_in_at']))) > strtotime($r['shift_start']) + 900; ?>
                <tr>
                    <td><a class="person" href="<?= url('admin/guard_view.php?id=' . $r['guard_id']) ?>"><?= avatar($r['full_name'], $r['photo'], 'sm') ?>
                        <span><span class="name d-block"><?= e($r['full_name']) ?></span><span class="sub"><?= e($r['employee_code']) ?></span></span></a></td>
                    <td class="small"><?= e($r['client_name'] ?? 'No client') ?><div class="text-muted"><?= e($r['site_name'] ?? '') ?></div></td>
                    <td class="small text-nowrap"><?= e(fmt_datetime($r['check_in_at'])) ?>
                        <?= $late ? '<span class="badge badge-soft-warning ms-1">Late</span>' : '' ?>
                        <?php if ($r['check_in_distance_m'] !== null): ?><div class="text-muted"><?= (int)$r['check_in_distance_m'] ?> m from site</div><?php endif; ?></td>
                    <td class="small text-nowrap"><?= e(fmt_datetime($r['check_out_at'])) ?><?= $r['notes'] ? '<div class="text-muted">' . e($r['notes']) . '</div>' : '' ?></td>
                    <td class="fw-semibold"><?= $r['status'] === 'open' ? '<span class="text-success">' . fmt_minutes((int)round((time() - strtotime($r['check_in_at'])) / 60)) . '…</span>' : fmt_minutes($r['duration_minutes'] !== null ? (int)$r['duration_minutes'] : null) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="text-end">
                        <?php if ($r['status'] === 'open'): ?>
                            <form method="post" data-confirm="Check this guard out now?"><?= csrf_field() ?><input type="hidden" name="action" value="close"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-light">Check out</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?= pagination_links($p) ?></div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
