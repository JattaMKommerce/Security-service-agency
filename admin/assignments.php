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

$totalDeployments = (int)($counts['active'] ?? 0) + (int)($counts['completed'] ?? 0) + (int)($counts['cancelled'] ?? 0);
$activeFilterCount = 0;
if ($search !== '') $activeFilterCount++;
if ($clientId > 0) $activeFilterCount++;
if ($status !== '' && $status !== 'active') $activeFilterCount++;
?>

<!-- 1. Hero Banner (Clean SaaS Look) -->
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Deployments &amp; Hiring</h2>
        <p class="guard-hero-sub">Active bodyguard deployments, shift schedules, and client site assignments</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(37,99,235,0.18);">
        <i class="bi bi-briefcase-fill"></i>
    </div>
</div>

<!-- 2. Summary Statistics Section (4 Clean Cards) -->
<div class="stat-grid-4">
    <!-- Active -->
    <a href="?status=active" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-success bg-success-subtle">
            <span class="stat-dot-green"></span>
        </div>
        <div class="stat-box-num"><?= (int)($counts['active'] ?? 0) ?></div>
        <div class="stat-box-lbl">Active</div>
    </a>
    <!-- Completed -->
    <a href="?status=completed" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-primary bg-primary-subtle">
            <i class="bi bi-check2-all"></i>
        </div>
        <div class="stat-box-num"><?= (int)($counts['completed'] ?? 0) ?></div>
        <div class="stat-box-lbl">Completed</div>
    </a>
    <!-- Cancelled -->
    <a href="?status=cancelled" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-danger bg-danger-subtle">
            <i class="bi bi-x-circle"></i>
        </div>
        <div class="stat-box-num"><?= (int)($counts['cancelled'] ?? 0) ?></div>
        <div class="stat-box-lbl">Cancelled</div>
    </a>
    <!-- Total -->
    <a href="?status=all" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-purple bg-purple-subtle">
            <i class="bi bi-briefcase"></i>
        </div>
        <div class="stat-box-num"><?= $totalDeployments ?></div>
        <div class="stat-box-lbl">Total</div>
    </a>
</div>

<!-- 3. Clean Search & Filters Card -->
<div class="filter-card-clean">
    <form method="get" action="<?= url('admin/assignments.php') ?>">
        <div class="search-input-wrap position-relative mb-2">
            <i class="bi bi-search search-icon-left"></i>
            <input type="search" name="q" value="<?= e($search) ?>" class="form-control form-control-clean ps-5" placeholder="Search guard, client, site...">
        </div>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-building filter-select-icon"></i>
                    <select name="client_id" class="form-select form-select-clean">
                        <option value="">Any client</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-tag filter-select-icon"></i>
                    <select name="status" class="form-select form-select-clean">
                        <?php foreach (['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All statuses'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-bold rounded-3 shadow-sm" style="background:#2563eb;border-color:#2563eb;">
                <i class="bi bi-funnel me-1"></i> Apply Filters <?= $activeFilterCount ? '(' . $activeFilterCount . ')' : '' ?>
            </button>
            <a href="<?= url('admin/assignments.php') ?>" class="btn btn-outline-secondary py-2 px-3 fw-semibold rounded-3 bg-white text-dark">
                <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- 4. Section Header & Actions -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="fs-5 fw-bold m-0 text-dark">Deployments (<?= (int)$p['total'] ?>)</h2>
    <a href="<?= url('admin/assignment_form.php') ?>" class="btn btn-primary btn-sm px-3 py-1-5 fw-bold rounded-pill shadow-sm" style="background:#2563eb;border-color:#2563eb;">
        <i class="bi bi-person-plus me-1"></i> Hire Bodyguards
    </a>
</div>

<!-- 5. MOBILE DEPLOYMENT CARDS (Visible only on mobile devices) -->
<div class="d-md-none">
    <?php if (!$p['rows']): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center">
            <i class="bi bi-briefcase fs-2 text-muted mb-2 d-block"></i>
            <div class="fw-semibold text-dark">No deployments found</div>
            <div class="small text-muted mb-3">Try adjusting your search or filters.</div>
            <a href="<?= url('admin/assignment_form.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3">Hire Bodyguards</a>
        </div>
    <?php endif; ?>

    <?php foreach ($p['rows'] as $a): ?>
        <div class="guard-mobile-card mb-2">
            <div class="d-flex align-items-center gap-3">
                <!-- Avatar / Photo -->
                <a href="<?= url('admin/guard_view.php?id=' . $a['guard_id']) ?>" class="text-decoration-none flex-shrink-0">
                    <?php if ($a['photo']): ?>
                        <img src="<?= asset('uploads/' . $a['photo']) ?>" alt="<?= e($a['full_name']) ?>" class="guard-avatar-img-sm">
                    <?php else: ?>
                        <div class="guard-avatar-initials-sm"><?= e(strtoupper(substr($a['full_name'], 0, 1))) ?></div>
                    <?php endif; ?>
                </a>

                <!-- Middle Content -->
                <div class="flex-grow-1 min-w-0">
                    <div class="guard-name-row text-truncate">
                        <a href="<?= url('admin/guard_view.php?id=' . $a['guard_id']) ?>" class="text-decoration-none text-dark fw-bold">
                            <?= e($a['full_name']) ?>
                        </a>
                        <span class="guard-code-text ms-1">(<?= e($a['employee_code']) ?>)</span>
                    </div>
                    <div class="small text-muted text-truncate">
                        <i class="bi bi-building me-1"></i><strong><?= e($a['client_name']) ?></strong>
                        <?php if ($a['site_name']): ?> · <?= e($a['site_name']) ?><?php endif; ?>
                    </div>
                    <div class="small text-muted text-truncate mt-1">
                        <i class="bi bi-calendar3 me-1"></i><?= e(fmt_date($a['start_date'])) ?> → <?= $a['end_date'] ? e(fmt_date($a['end_date'])) : 'open' ?>
                    </div>
                    <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                        <?= status_badge($a['status']) ?>
                        <?php if ($a['status'] === 'active' && $a['is_on_duty']): ?>
                            <span class="status-pill-active">
                                <span class="stat-dot-green" style="width:6px;height:6px;box-shadow:none;"></span>
                                On Duty Now
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Side: Rate & Manage Button -->
                <div class="text-end flex-shrink-0">
                    <div class="fw-bold text-dark fs-6 mb-1"><?= money($a['rate']) ?></div>
                    <div class="small text-muted mb-2">/ <?= e(rate_unit($a['rate_type'])) ?></div>
                    <a href="<?= url('admin/assignment_view.php?id=' . $a['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 shadow-sm" style="font-size:0.75rem;">
                        Manage
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
                    <th>Bodyguard</th>
                    <th>Hired by</th>
                    <th>Site</th>
                    <th>Period</th>
                    <th>Shift</th>
                    <th>Rate</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
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
