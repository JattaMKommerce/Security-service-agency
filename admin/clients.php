<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$search = get_str('q');
$type = get_str('type', 20);
$status = get_str('status', 20);
$types = ['individual', 'corporate', 'event', 'residential', 'other'];

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.client_code LIKE ? OR c.phone LIKE ? OR c.contact_person LIKE ? OR c.city LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if (in_array($type, $types, true)) {
    $where[] = 'c.client_type = ?';
    $params[] = $type;
}
if (in_array($status, ['active', 'inactive'], true)) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

$p = paginate('FROM clients c WHERE ' . implode(' AND ', $where), $params,
    "c.*, (SELECT COUNT(*) FROM assignments a WHERE a.client_id = c.id AND a.status = 'active') AS hired,
          (SELECT COUNT(*) FROM assignments a JOIN guards g ON g.id = a.guard_id WHERE a.client_id = c.id AND a.status = 'active' AND g.is_on_duty = 1) AS on_duty",
    'c.name ASC', 25);

$pageTitle = 'Clients';
$activeNav = 'clients';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions">
    <form class="filter-bar" method="get">
        <input type="search" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Search name, code, phone, city">
        <select name="type" class="form-select">
            <option value="">Any type</option>
            <?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= label($t) ?></option><?php endforeach; ?>
        </select>
        <select name="status" class="form-select">
            <option value="">Any status</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
        <?php if ($search || $type || $status): ?><a href="<?= url('admin/clients.php') ?>" class="btn btn-link">Clear</a><?php endif; ?>
    </form>
    <a href="<?= url('admin/client_form.php') ?>" class="btn btn-primary"><i class="bi bi-building-add"></i> Add client</a>
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Client</th><th>Type</th><th>Contact</th><th>City</th><th class="text-center">Guards hired</th><th class="text-center">On duty</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-building"></i>No clients found.
                    <div class="mt-2"><a href="<?= url('admin/client_form.php') ?>" class="btn btn-sm btn-primary">Add a client</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($p['rows'] as $c): ?>
                <tr>
                    <td><a href="<?= url('admin/client_view.php?id=' . $c['id']) ?>" class="fw-semibold"><?= e($c['name']) ?></a><div class="small text-muted"><?= e($c['client_code']) ?></div></td>
                    <td><span class="badge badge-soft-secondary"><?= e(label($c['client_type'])) ?></span></td>
                    <td class="small"><?= e($c['contact_person'] ?: '—') ?><div><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a></div></td>
                    <td class="small"><?= e($c['city'] ?: '—') ?></td>
                    <td class="text-center fw-semibold"><?= (int)$c['hired'] ?></td>
                    <td class="text-center"><?= (int)$c['on_duty'] ?></td>
                    <td><?= status_badge($c['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php if ($c['status'] === 'active'): ?>
                            <a href="<?= url('admin/assignment_form.php?client_id=' . $c['id']) ?>" class="btn btn-sm btn-light" title="Hire guards"><i class="bi bi-person-plus"></i></a>
                        <?php endif; ?>
                        <?php if ($c['hired']): ?>
                            <a href="<?= url('admin/tracking.php?client_id=' . $c['id']) ?>" class="btn btn-sm btn-light" title="Track this client's guards"><i class="bi bi-geo-alt"></i></a>
                        <?php endif; ?>
                        <a href="<?= url('admin/client_form.php?id=' . $c['id']) ?>" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?= pagination_links($p) ?></div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
