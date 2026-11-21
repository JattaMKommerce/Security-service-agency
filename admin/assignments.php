<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$search = get_str('q');
$status = get_str('status', 20) ?: 'active';
$clientId = get_int('client_id');

$where = ['1=1'];
$params = [];
if (in_array($status, ['active', 'completed', 'cancelled'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $where[] = '(g.full_name LIKE ? OR g.employee_code LIKE ? OR c.name LIKE ? OR a.site_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($clientId > 0) {
    $where[] = 'a.client_id = ?';
    $params[] = $clientId;
}

$p = paginate('FROM assignments a JOIN guards g ON g.id = a.guard_id JOIN clients c ON c.id = a.client_id WHERE ' . implode(' AND ', $where), $params,
    'a.*, g.full_name, g.employee_code, g.photo, g.is_on_duty, g.last_seen_at, c.name AS client_name, c.client_code',
    'c.name ASC, a.site_name ASC, g.full_name ASC', 30);
$clients = q_all('SELECT id, name FROM clients ORDER BY name');
$counts = q_one("SELECT SUM(status='active') active, SUM(status='completed') completed, SUM(status='cancelled') cancelled FROM assignments");

$pageTitle = 'Hiring / Deployments';
$activeNav = 'assignments';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions">
    <ul class="nav nav-tabs border-0">
        <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $lbl): ?>
            <li class="nav-item"><a class="nav-link<?= $status === $k ? ' active' : '' ?>" href="?status=<?= $k ?>"><?= $lbl ?>
                <?php if (isset($counts[$k])): ?><span class="badge badge-soft-secondary ms-1"><?= (int)$counts[$k] ?></span><?php endif; ?></a></li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= url('admin/assignment_form.php') ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Hire bodyguards</a>
</div>

<form class="filter-bar mb-3" method="get">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <input type="search" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search guard, client, site">
    <select name="client_id" class="form-select">
        <option value="">Any client</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Bodyguard</th><th>Hired by</th><th>Site</th><th>Period</th><th>Shift</th><th>Rate</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-briefcase"></i>No deployments found.</div></td></tr>
            <?php endif; ?>
            <?php foreach ($p['rows'] as $a): ?>
                <tr>
                    <td><a class="person" href="<?= url('admin/guard_view.php?id=' . $a['guard_id']) ?>"><?= avatar($a['full_name'], $a['photo'], 'sm') ?>
                        <span><span class="name d-block"><?= e($a['full_name']) ?></span><span class="sub"><?= e($a['employee_code']) ?><?= $a['status'] === 'active' && $a['is_on_duty'] ? ' · <span class="text-success">on duty</span>' : '' ?></span></span></a></td>
                    <td><a class="hired-by" href="<?= url('admin/client_view.php?id=' . $a['client_id']) ?>"><i class="bi bi-briefcase-fill"></i><?= e($a['client_name']) ?></a></td>
                    <td class="small"><?= e($a['site_name']) ?></td>
                    <td class="small text-nowrap"><?= e(fmt_date($a['start_date'])) ?> → <?= $a['end_date'] ? e(fmt_date($a['end_date'])) : 'open' ?></td>
                    <td class="small text-nowrap"><?= $a['shift_start'] ? e(fmt_time($a['shift_start']) . ' – ' . fmt_time($a['shift_end'])) : '—' ?></td>
                    <td class="small text-nowrap"><?= money($a['rate']) ?> / <?= e(rate_unit($a['rate_type'])) ?></td>
                    <td><?= status_badge($a['status']) ?></td>
                    <td class="text-end"><a href="<?= url('admin/assignment_view.php?id=' . $a['id']) ?>" class="btn btn-sm btn-light">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?= pagination_links($p) ?></div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
