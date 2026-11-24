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

$counts = q_one("SELECT COUNT(*) total,
                        COALESCE(SUM(status = 'active'),0) active,
                        (SELECT COUNT(*) FROM assignments WHERE status = 'active') hired_guards,
                        (SELECT COUNT(*) FROM assignments a JOIN guards g ON g.id = a.guard_id WHERE a.status = 'active' AND g.is_on_duty = 1) on_duty
                 FROM clients");

$pageTitle = 'Clients';
$activeNav = 'clients';
require APP_ROOT . '/includes/layout/header.php';

$activeFilterCount = 0;
if ($search !== '') $activeFilterCount++;
if ($type !== '') $activeFilterCount++;
if ($status !== '') $activeFilterCount++;
?>

<!-- 1. Hero Banner (Clean SaaS Look) -->
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Clients Management</h2>
        <p class="guard-hero-sub">Manage corporate accounts, VIP personal clients, and assigned sites</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(37,99,235,0.18);">
        <i class="bi bi-building"></i>
    </div>
</div>

<!-- 2. Summary Statistics Section (4 Clean Cards) -->
<div class="stat-grid-4">
    <!-- Total Clients -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-purple bg-purple-subtle">
            <i class="bi bi-building"></i>
        </div>
        <div class="stat-box-num"><?= (int)$counts['total'] ?></div>
        <div class="stat-box-lbl">Total</div>
    </div>
    <!-- Active -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-success bg-success-subtle">
            <span class="stat-dot-green"></span>
        </div>
        <div class="stat-box-num"><?= (int)$counts['active'] ?></div>
        <div class="stat-box-lbl">Active</div>
    </div>
    <!-- Hired Guards -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-primary bg-primary-subtle">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-box-num"><?= (int)$counts['hired_guards'] ?></div>
        <div class="stat-box-lbl">Hired</div>
    </div>
    <!-- On Duty -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-warning bg-warning-subtle">
            <i class="bi bi-broadcast"></i>
        </div>
        <div class="stat-box-num"><?= (int)$counts['on_duty'] ?></div>
        <div class="stat-box-lbl">On Duty</div>
    </div>
</div>

<!-- 3. Clean Search & Filters Card -->
<div class="filter-card-clean">
    <form method="get" action="<?= url('admin/clients.php') ?>">
        <div class="search-input-wrap position-relative mb-2">
            <i class="bi bi-search search-icon-left"></i>
            <input type="search" name="q" value="<?= e($search) ?>" class="form-control form-control-clean ps-5" placeholder="Search name, code, phone, city...">
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-grid filter-select-icon"></i>
                    <select name="type" class="form-select form-select-clean">
                        <option value="">Any type</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= label($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-activity filter-select-icon"></i>
                    <select name="status" class="form-select form-select-clean">
                        <option value="">Any status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-bold rounded-3 shadow-sm" style="background:#2563eb;border-color:#2563eb;">
                <i class="bi bi-funnel me-1"></i> Apply Filters <?= $activeFilterCount ? '(' . $activeFilterCount . ')' : '' ?>
            </button>
            <a href="<?= url('admin/clients.php') ?>" class="btn btn-outline-secondary py-2 px-3 fw-semibold rounded-3 bg-white text-dark">
                <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- 4. Section Header & Actions -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="fs-5 fw-bold m-0 text-dark">Clients (<?= (int)$p['total'] ?>)</h2>
    <a href="<?= url('admin/client_form.php') ?>" class="btn btn-primary btn-sm px-3 py-1-5 fw-bold rounded-pill shadow-sm" style="background:#2563eb;border-color:#2563eb;">
        <i class="bi bi-building-add me-1"></i> Add Client
    </a>
</div>

<!-- 5. MOBILE CLIENT CARDS (Visible only on mobile devices) -->
<div class="d-md-none">
    <?php if (!$p['rows']): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center">
            <i class="bi bi-building fs-2 text-muted mb-2 d-block"></i>
            <div class="fw-semibold text-dark">No clients found</div>
            <div class="small text-muted mb-3">Try adjusting your search or filters.</div>
            <a href="<?= url('admin/client_form.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3">Add a Client</a>
        </div>
    <?php endif; ?>

    <?php foreach ($p['rows'] as $c): ?>
        <div class="guard-mobile-card mb-2">
            <div class="d-flex align-items-center gap-3">
                <!-- Avatar / Logo -->
                <a href="<?= url('admin/client_view.php?id=' . $c['id']) ?>" class="text-decoration-none flex-shrink-0">
                    <div class="guard-avatar-initials-sm" style="background:linear-gradient(135deg, #0284c7, #0369a1);">
                        <?= e(strtoupper(substr($c['name'], 0, 1))) ?>
                    </div>
                </a>

                <!-- Middle Content -->
                <div class="flex-grow-1 min-w-0">
                    <a href="<?= url('admin/client_view.php?id=' . $c['id']) ?>" class="text-decoration-none d-block">
                        <div class="guard-name-row text-truncate">
                            <span class="guard-name-text"><?= e($c['name']) ?></span>
                        </div>
                        <div class="guard-code-text text-truncate">
                            <?= e($c['client_code']) ?><?= $c['city'] ? ' · ' . e($c['city']) : '' ?>
                        </div>
                    </a>
                    <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                        <span class="status-pill-active" style="<?= $c['status'] === 'active' ? '' : 'background:#f1f5f9;color:#64748b;' ?>">
                            <span class="stat-dot-green" style="<?= $c['status'] === 'active' ? '' : 'background:#94a3b8;' ?>width:7px;height:7px;box-shadow:none;"></span>
                            <?= label($c['status']) ?>
                        </span>
                        <span class="badge bg-light text-secondary border" style="font-size:0.68rem;">
                            <?= e(label($c['client_type'])) ?>
                        </span>
                        <span class="badge bg-primary-subtle text-primary fw-semibold" style="font-size:0.68rem;">
                            <i class="bi bi-shield me-1"></i><?= (int)$c['hired'] ?> hired
                        </span>
                    </div>
                </div>

                <!-- Right Side: Phone & Chevron Link -->
                <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-1">
                    <?php if ($c['phone']): ?>
                        <a href="tel:<?= e($c['phone']) ?>" class="btn btn-sm btn-light rounded-circle text-primary border" style="width:32px;height:32px;display:inline-grid;place-items:center;" title="Call Client">
                            <i class="bi bi-telephone-fill" style="font-size:0.75rem;"></i>
                        </a>
                    <?php endif; ?>
                    <a href="<?= url('admin/client_view.php?id=' . $c['id']) ?>" class="text-muted text-decoration-none p-1" aria-label="View Details">
                        <i class="bi bi-chevron-right fs-6"></i>
                    </a>
                </div>
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
                    <th>Client</th>
                    <th>Type</th>
                    <th>Contact</th>
                    <th>City</th>
                    <th class="text-center">Guards hired</th>
                    <th class="text-center">On duty</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
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
