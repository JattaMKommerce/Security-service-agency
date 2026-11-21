<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$search = get_str('q');
$status = get_str('status', 20);
$avail = get_str('availability', 20);
$clientId = get_int('client_id');

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(g.full_name LIKE ? OR g.employee_code LIKE ? OR g.phone LIKE ? OR g.city LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, ['active', 'on_leave', 'inactive'], true)) {
    $where[] = 'g.status = ?';
    $params[] = $status;
}
if ($avail === 'available') {
    $where[] = "g.status = 'active' AND a.id IS NULL";
} elseif ($avail === 'hired') {
    $where[] = 'a.id IS NOT NULL';
} elseif ($avail === 'on_duty') {
    $where[] = 'g.is_on_duty = 1';
}
if ($clientId > 0) {
    $where[] = 'a.client_id = ?';
    $params[] = $clientId;
}

$from = "FROM guards g
         LEFT JOIN assignments a ON a.guard_id = g.id AND a.status = 'active'
         LEFT JOIN clients c ON c.id = a.client_id
         WHERE " . implode(' AND ', $where);
$p = paginate($from, $params,
    'g.id, g.employee_code, g.full_name, g.phone, g.photo, g.city, g.status, g.is_on_duty, g.is_armed, g.last_seen_at,
     a.id AS assignment_id, a.site_name, c.id AS client_id, c.name AS client_name',
    'g.full_name ASC', 25);

$clients = q_all("SELECT id, name FROM clients ORDER BY name");
$counts = q_one("SELECT COUNT(*) total, SUM(status='active') active, SUM(is_on_duty) on_duty FROM guards");

$pageTitle = 'Bodyguards';
$activeNav = 'guards';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions">
    <form class="filter-bar" method="get">
        <input type="search" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search name, code, phone, city">
        <select name="status" class="form-select">
            <option value="">Any status</option>
            <?php foreach (['active', 'on_leave', 'inactive'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= label($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="availability" class="form-select">
            <option value="">Hired or available</option>
            <option value="available" <?= $avail === 'available' ? 'selected' : '' ?>>Available (not hired)</option>
            <option value="hired" <?= $avail === 'hired' ? 'selected' : '' ?>>Hired</option>
            <option value="on_duty" <?= $avail === 'on_duty' ? 'selected' : '' ?>>On duty now</option>
        </select>
        <select name="client_id" class="form-select">
            <option value="">Any client</option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
        <?php if ($search || $status || $avail || $clientId): ?><a href="<?= url('admin/guards.php') ?>" class="btn btn-link">Clear</a><?php endif; ?>
    </form>
    <a href="<?= url('admin/guard_form.php') ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add bodyguard</a>
</div>

<div class="small text-muted mb-2">
    <?= (int)$counts['total'] ?> total · <?= (int)$counts['active'] ?> active · <?= (int)$counts['on_duty'] ?> on duty now
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Bodyguard</th><th>Phone</th><th>Status</th><th>Duty</th><th>Hired by</th><th>Last location</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-person-badge"></i>No bodyguards found.
                    <div class="mt-2"><a href="<?= url('admin/guard_form.php') ?>" class="btn btn-sm btn-primary">Add the first one</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($p['rows'] as $g): ?>
                <tr>
                    <td>
                        <a class="person" href="<?= url('admin/guard_view.php?id=' . $g['id']) ?>">
                            <?= avatar($g['full_name'], $g['photo']) ?>
                            <span><span class="name d-block"><?= e($g['full_name']) ?>
                                <?php if ($g['is_armed']): ?><i class="bi bi-shield-fill-check text-warning" title="Armed"></i><?php endif; ?></span>
                                <span class="sub"><?= e($g['employee_code']) ?><?= $g['city'] ? ' · ' . e($g['city']) : '' ?></span></span>
                        </a>
                    </td>
                    <td><a href="tel:<?= e($g['phone']) ?>"><?= e($g['phone']) ?></a></td>
                    <td><?= status_badge($g['status']) ?></td>
                    <td><?= $g['is_on_duty'] ? status_badge('on_duty') : '<span class="text-muted small">Off duty</span>' ?></td>
                    <td>
                        <?php if ($g['client_id']): ?>
                            <a class="hired-by" href="<?= url('admin/client_view.php?id=' . $g['client_id']) ?>"><i class="bi bi-briefcase-fill"></i><?= e($g['client_name']) ?></a>
                            <div class="small text-muted mt-1"><?= e($g['site_name']) ?></div>
                        <?php elseif ($g['status'] === 'active'): ?>
                            <span class="badge badge-soft-info">Available</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= $g['last_seen_at'] ? e(time_ago($g['last_seen_at'])) : '<span class="text-muted">never</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?= url('admin/tracking.php?guard_id=' . $g['id']) ?>" class="btn btn-sm btn-light" title="Track on map"><i class="bi bi-geo-alt"></i></a>
                        <?php if (!$g['client_id'] && $g['status'] === 'active'): ?>
                            <a href="<?= url('admin/assignment_form.php?guard_id=' . $g['id']) ?>" class="btn btn-sm btn-light" title="Hire out / assign"><i class="bi bi-briefcase"></i></a>
                        <?php endif; ?>
                        <a href="<?= url('admin/guard_form.php?id=' . $g['id']) ?>" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?= pagination_links($p) ?></div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
