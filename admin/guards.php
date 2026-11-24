<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

// ---- Quick weapon toggle ----------------------------------------------------
if (is_post() && ($_POST['action'] ?? '') === 'toggle_armed') {
    require_csrf();
    $gid = post_int('guard_id');
    if ($gid) {
        $cur = q_one('SELECT id, employee_code, full_name, is_armed FROM guards WHERE id = ?', [$gid]);
        if ($cur) {
            $newArmed = $cur['is_armed'] ? 0 : 1;
            q('UPDATE guards SET is_armed = ? WHERE id = ?', [$newArmed, $gid]);
            log_activity($newArmed ? 'guard.armed_enable' : 'guard.armed_disable', $cur['employee_code'] . ' ' . $cur['full_name']);
            flash('success', ($newArmed ? 'Gun authorization enabled for ' : 'Gun authorization disabled for ') . $cur['full_name'] . '.');
        }
    }
    $qs = $_GET;
    unset($qs['guard_id'], $qs['action'], $qs['_csrf']);
    redirect('admin/guards.php' . ($qs ? '?' . http_build_query($qs) : ''));
}

$search   = get_str('q');
$status   = get_str('status', 20);
$category = get_str('category', 40);
$weapon   = get_str('weapon', 20);
$avail    = get_str('availability', 20);
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
if (in_array($category, array_keys(guard_categories()), true)) {
    $where[] = 'g.category = ?';
    $params[] = $category;
}
if ($weapon === 'armed') {
    $where[] = 'g.is_armed = 1';
} elseif ($weapon === 'unarmed') {
    $where[] = 'g.is_armed = 0';
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
    'g.id, g.employee_code, g.category, g.full_name, g.phone, g.photo, g.city, g.status, g.is_on_duty, g.is_armed, g.last_seen_at,
     a.id AS assignment_id, a.site_name, c.id AS client_id, c.name AS client_name',
    'g.full_name ASC', 25);

$clients = q_all("SELECT id, name FROM clients ORDER BY name");
$counts = q_one("SELECT COUNT(*) total, SUM(status='active') active, SUM(is_on_duty) on_duty, SUM(is_armed=1) armed, SUM(is_armed=0) unarmed FROM guards");

// Calculate active filters count
$activeFilterCount = 0;
if ($search !== '') $activeFilterCount++;
if ($category !== '') $activeFilterCount++;
if ($weapon !== '') $activeFilterCount++;
if ($status !== '') $activeFilterCount++;
if ($avail !== '') $activeFilterCount++;
if ($clientId > 0) $activeFilterCount++;

$pageTitle = 'Guards & Bodyguards';
$activeNav = 'guards';
require APP_ROOT . '/includes/layout/header.php';
?>

<!-- 1. Hero Banner (Clean SaaS Look) -->
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Guards Management</h2>
        <p class="guard-hero-sub">Manage, assign and track your security staff</p>
    </div>
    <div class="guard-hero-graphic">
        <img src="<?= asset('img/guards/corporate-park-guard.jpg') ?>" alt="Security Officer" class="guard-hero-img">
    </div>
</div>

<!-- 2. Clean Search & Filters Card -->
<div class="filter-card-clean">
    <form method="get" action="<?= url('admin/guards.php') ?>">
        <!-- Search Input 100% Width -->
        <div class="search-input-wrap position-relative mb-3">
            <i class="bi bi-search search-icon-left"></i>
            <input type="search" name="q" value="<?= e($search) ?>" class="form-control form-control-clean ps-5" placeholder="Search by name, code or phone...">
        </div>

        <!-- 2-Column Responsive Filter Grid -->
        <div class="row g-2 mb-3">
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-grid filter-select-icon"></i>
                    <select name="category" class="form-select form-select-clean">
                        <option value="">All categories</option>
                        <?php foreach (guard_categories() as $ck => $cv): ?>
                            <option value="<?= $ck ?>" <?= $category === $ck ? 'selected' : '' ?>><?= e($cv) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-shield-check filter-select-icon"></i>
                    <select name="weapon" class="form-select form-select-clean">
                        <option value="">Armed & Unarmed</option>
                        <option value="armed" <?= $weapon === 'armed' ? 'selected' : '' ?>>Armed (Gun)</option>
                        <option value="unarmed" <?= $weapon === 'unarmed' ? 'selected' : '' ?>>Unarmed</option>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-activity filter-select-icon"></i>
                    <select name="status" class="form-select form-select-clean">
                        <option value="">Any status</option>
                        <?php foreach (['active', 'on_leave', 'inactive'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= label($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-person filter-select-icon"></i>
                    <select name="availability" class="form-select form-select-clean">
                        <option value="">Hired or available</option>
                        <option value="available" <?= $avail === 'available' ? 'selected' : '' ?>>Available (not hired)</option>
                        <option value="hired" <?= $avail === 'hired' ? 'selected' : '' ?>>Hired</option>
                        <option value="on_duty" <?= $avail === 'on_duty' ? 'selected' : '' ?>>On duty now</option>
                    </select>
                </div>
            </div>
            <div class="col-12">
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
        </div>

        <!-- Filter Action Buttons -->
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-bold rounded-3 shadow-sm" style="background:#2563eb;border-color:#2563eb;">
                <i class="bi bi-funnel me-1"></i> Apply Filters <?= $activeFilterCount ? '(' . $activeFilterCount . ')' : '' ?>
            </button>
            <a href="<?= url('admin/guards.php') ?>" class="btn btn-outline-secondary py-2 px-3 fw-semibold rounded-3 bg-white text-dark">
                <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- 3. Summary Statistics Section (4 Clean Cards) -->
<div class="stat-grid-4">
    <!-- Total -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-purple bg-purple-subtle">
            <i class="bi bi-people-fill"></i>
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
    <!-- Armed -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-warning bg-warning-subtle">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 8H7.5L5 10.5V13h2v4h3v-2.5h9L22 12V8z"/>
            </svg>
        </div>
        <div class="stat-box-num"><?= (int)$counts['armed'] ?></div>
        <div class="stat-box-lbl">Armed</div>
    </div>
    <!-- Unarmed -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-primary bg-primary-subtle">
            <i class="bi bi-shield"></i>
        </div>
        <div class="stat-box-num"><?= (int)$counts['unarmed'] ?></div>
        <div class="stat-box-lbl">Unarmed</div>
    </div>
</div>

<!-- 4. Section Header & Responsive Action Buttons -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="fs-5 fw-bold m-0 text-dark">Guards (<?= (int)$p['total'] ?>)</h2>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= url('guard/install.php') ?>" target="_blank" class="btn btn-outline-secondary btn-sm px-3 py-1-5 fw-semibold rounded-pill bg-white text-dark shadow-sm">
            <i class="bi bi-phone me-1"></i> App & APK Guide
        </a>
        <a href="<?= url('admin/guard_form.php') ?>" class="btn btn-primary btn-sm px-3 py-1-5 fw-bold rounded-pill shadow-sm" style="background:#2563eb;border-color:#2563eb;">
            <i class="bi bi-plus-lg me-1"></i> Add Guard
        </a>
    </div>
</div>

<!-- 5. MOBILE GUARD CARDS (Visible only on mobile devices) -->
<div class="d-md-none">
    <?php if (!$p['rows']): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center">
            <i class="bi bi-person-badge fs-2 text-muted mb-2 d-block"></i>
            <div class="fw-semibold text-dark">No guards found</div>
            <div class="small text-muted mb-3">Try adjusting your search or filters.</div>
            <a href="<?= url('admin/guard_form.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3">Add Guard</a>
        </div>
    <?php endif; ?>

    <?php foreach ($p['rows'] as $g): ?>
        <div class="guard-mobile-card mb-2">
            <div class="d-flex align-items-center gap-3">
                <!-- Avatar / Photo -->
                <a href="<?= url('admin/guard_view.php?id=' . $g['id']) ?>" class="text-decoration-none flex-shrink-0">
                    <?php if ($g['photo']): ?>
                        <img src="<?= asset('uploads/' . $g['photo']) ?>" alt="<?= e($g['full_name']) ?>" class="guard-avatar-img-sm">
                    <?php else: ?>
                        <div class="guard-avatar-initials-sm"><?= e(strtoupper(substr($g['full_name'], 0, 1))) ?></div>
                    <?php endif; ?>
                </a>

                <!-- Middle Content: Name, Verified Badge, Code, City, Status -->
                <div class="flex-grow-1 min-w-0">
                    <a href="<?= url('admin/guard_view.php?id=' . $g['id']) ?>" class="text-decoration-none d-block">
                        <div class="guard-name-row text-truncate">
                            <span class="guard-name-text"><?= e($g['full_name']) ?></span>
                            <i class="bi bi-patch-check-fill verified-badge" title="Verified Guard"></i>
                        </div>
                        <div class="guard-code-text text-truncate">
                            <?= e($g['employee_code']) ?><?= $g['city'] ? ' · ' . e($g['city']) : '' ?>
                        </div>
                    </a>
                    <div class="mt-1">
                        <span class="status-pill-active">
                            <span class="stat-dot-green" style="width:7px;height:7px;box-shadow:none;"></span>
                            <?= label($g['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Right Side: Category Pill & Tap-to-Call Phone -->
                <div class="text-end flex-shrink-0">
                    <div class="mb-1">
                        <span class="category-pill-clean">
                            <?php if ($g['category'] === 'agency_security' || $g['is_armed']): ?>
                                <i class="bi bi-shield-check"></i>
                            <?php endif; ?>
                            <?= e(guard_categories()[$g['category']] ?? label($g['category'] ?? 'bodyguard')) ?>
                        </span>
                    </div>
                    <div>
                        <a href="tel:<?= e($g['phone']) ?>" class="phone-link-clean">
                            <i class="bi bi-telephone text-muted" style="font-size:0.75rem;"></i>
                            <span><?= e($g['phone']) ?></span>
                        </a>
                    </div>
                </div>

                <!-- Chevron Link -->
                <a href="<?= url('admin/guard_view.php?id=' . $g['id']) ?>" class="text-muted ms-1 text-decoration-none flex-shrink-0 p-1" aria-label="View Details">
                    <i class="bi bi-chevron-right fs-6"></i>
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- 6. DESKTOP TABLE (Visible on tablets and desktop screens) -->
<div class="card table-card d-none d-md-block mb-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Guard</th>
                    <th>Category</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Duty</th>
                    <th>Hired by</th>
                    <th>Weapon / Gun</th>
                    <th>Last location</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="bi bi-person-badge"></i>No guards found.
                    <div class="mt-2"><a href="<?= url('admin/guard_form.php') ?>" class="btn btn-sm btn-primary">Add the first one</a></div></div></td></tr>
            <?php endif; ?>
            <?php foreach ($p['rows'] as $g): ?>
                <tr>
                    <td>
                        <a class="person" href="<?= url('admin/guard_view.php?id=' . $g['id']) ?>">
                            <?= avatar($g['full_name'], $g['photo']) ?>
                            <span><span class="name d-block"><?= e($g['full_name']) ?>
                                <?php if ($g['is_armed']): ?><i class="bi bi-shield-fill-check text-warning ms-1" title="Armed"></i><?php endif; ?></span>
                                <span class="sub"><?= e($g['employee_code']) ?><?= $g['city'] ? ' · ' . e($g['city']) : '' ?></span></span>
                        </a>
                    </td>
                    <td><?= category_badge($g['category'] ?? 'bodyguard') ?></td>
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
                    <td>
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_armed">
                            <input type="hidden" name="guard_id" value="<?= (int)$g['id'] ?>">
                            <?php if ($g['is_armed']): ?>
                                <button type="submit" class="btn btn-sm btn-outline-warning py-1 px-2 text-nowrap" title="Armed with Gun. Click to Disable">
                                    <i class="bi bi-shield-fill-check text-warning"></i> Armed <i class="bi bi-toggle-on text-success ms-1"></i>
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2 text-nowrap" title="Unarmed. Click to Enable Gun">
                                    <i class="bi bi-shield text-muted"></i> Unarmed <i class="bi bi-toggle-off text-muted ms-1"></i>
                                </button>
                            <?php endif; ?>
                        </form>
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
</div>

<!-- 7. Pagination Links (Responsive) -->
<div class="d-flex justify-content-center mt-3 mb-4">
    <?= pagination_links($p) ?>
</div>

<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
