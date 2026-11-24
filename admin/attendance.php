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

$activeFilterCount = 0;
if ($guardId > 0) $activeFilterCount++;
if ($clientId > 0) $activeFilterCount++;
if ($status !== '') $activeFilterCount++;
?>

<!-- 1. Hero Banner (Clean SaaS Look) -->
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Attendance &amp; Duty Logs</h2>
        <p class="guard-hero-sub">Real-time check-ins, shift duration tracking, and geofenced site logs</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(37,99,235,0.18);">
        <i class="bi bi-calendar-check"></i>
    </div>
</div>

<!-- 2. Summary Statistics Section (4 Clean Cards) -->
<div class="stat-grid-4">
    <!-- Shifts -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-purple bg-purple-subtle">
            <i class="bi bi-calendar-check"></i>
        </div>
        <div class="stat-box-num"><?= (int)$summary['shifts'] ?></div>
        <div class="stat-box-lbl">Shifts</div>
    </div>
    <!-- Guards -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-primary bg-primary-subtle">
            <i class="bi bi-people-fill"></i>
        </div>
        <div class="stat-box-num"><?= (int)$summary['guards'] ?></div>
        <div class="stat-box-lbl">Guards</div>
    </div>
    <!-- Hours on duty -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-success bg-success-subtle">
            <i class="bi bi-clock-history"></i>
        </div>
        <div class="stat-box-num"><?= round((int)$summary['mins'] / 60, 1) ?>h</div>
        <div class="stat-box-lbl">Total Hours</div>
    </div>
    <!-- On duty now -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-warning bg-warning-subtle">
            <i class="bi bi-broadcast"></i>
        </div>
        <div class="stat-box-num"><?= (int)$summary['open_now'] ?></div>
        <div class="stat-box-lbl">On Duty Now</div>
    </div>
</div>

<!-- 3. Clean Search & Filters Card -->
<div class="filter-card-clean">
    <form method="get" action="<?= url('admin/attendance.php') ?>">
        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-clean">
            </div>
            <div class="col-6">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-clean">
            </div>
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-person filter-select-icon"></i>
                    <select name="guard_id" class="form-select form-select-clean">
                        <option value="">All bodyguards</option>
                        <?php foreach ($guards as $g): ?>
                            <option value="<?= (int)$g['id'] ?>" <?= $guardId === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-building filter-select-icon"></i>
                    <select name="client_id" class="form-select form-select-clean">
                        <option value="">All clients</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <div class="filter-select-wrap">
                    <i class="bi bi-activity filter-select-icon"></i>
                    <select name="status" class="form-select form-select-clean">
                        <option value="">Any status</option>
                        <?php foreach (['open' => 'On duty now', 'closed' => 'Closed', 'auto_closed' => 'Auto-closed'] as $k => $lbl): ?>
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
            <a href="<?= url('admin/attendance.php') ?>" class="btn btn-outline-secondary py-2 px-3 fw-semibold rounded-3 bg-white text-dark">
                <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- 4. Section Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fs-5 fw-bold m-0 text-dark">Shift Records (<?= (int)$p['total'] ?>)</h2>
</div>

<!-- 5. MOBILE ATTENDANCE CARDS (Visible only on mobile devices) -->
<div class="d-md-none">
    <?php if (!$p['rows']): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center">
            <i class="bi bi-calendar-x fs-2 text-muted mb-2 d-block"></i>
            <div class="fw-semibold text-dark">No shifts in this period</div>
            <div class="small text-muted mb-3">Try adjusting your date range or filters.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($p['rows'] as $r):
        $late = $r['shift_start'] && strtotime(date('H:i:s', strtotime($r['check_in_at']))) > strtotime($r['shift_start']) + 900;
        $openDuration = (int)round((time() - strtotime($r['check_in_at'])) / 60);
    ?>
        <div class="guard-mobile-card mb-2">
            <div class="d-flex align-items-center gap-3">
                <!-- Avatar / Photo -->
                <a href="<?= url('admin/guard_view.php?id=' . $r['guard_id']) ?>" class="text-decoration-none flex-shrink-0">
                    <?php if ($r['photo']): ?>
                        <img src="<?= asset('uploads/' . $r['photo']) ?>" alt="<?= e($r['full_name']) ?>" class="guard-avatar-img-sm">
                    <?php else: ?>
                        <div class="guard-avatar-initials-sm"><?= e(strtoupper(substr($r['full_name'], 0, 1))) ?></div>
                    <?php endif; ?>
                </a>

                <!-- Middle Content -->
                <div class="flex-grow-1 min-w-0">
                    <div class="guard-name-row text-truncate">
                        <a href="<?= url('admin/guard_view.php?id=' . $r['guard_id']) ?>" class="text-decoration-none text-dark fw-bold">
                            <?= e($r['full_name']) ?>
                        </a>
                        <span class="guard-code-text ms-1">(<?= e($r['employee_code']) ?>)</span>
                    </div>
                    <div class="small text-muted text-truncate">
                        <i class="bi bi-geo-alt me-1"></i><?= e($r['client_name'] ?? 'No client') ?><?= $r['site_name'] ? ' · ' . e($r['site_name']) : '' ?>
                    </div>
                    <div class="small text-muted text-truncate mt-1">
                        <i class="bi bi-box-arrow-in-right text-success me-1"></i>In: <?= e(fmt_datetime($r['check_in_at'])) ?>
                        <?php if ($late): ?><span class="badge bg-warning-subtle text-warning ms-1">Late</span><?php endif; ?>
                    </div>
                    <?php if ($r['check_out_at']): ?>
                        <div class="small text-muted text-truncate">
                            <i class="bi bi-box-arrow-right text-secondary me-1"></i>Out: <?= e(fmt_datetime($r['check_out_at'])) ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-1 d-flex align-items-center gap-1 flex-wrap">
                        <?= status_badge($r['status']) ?>
                        <?php if ($r['check_in_distance_m'] !== null): ?>
                            <span class="badge bg-light text-secondary border" style="font-size:0.68rem;">
                                <?= (int)$r['check_in_distance_m'] ?>m from site
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Side: Duration & Check out -->
                <div class="text-end flex-shrink-0">
                    <div class="fw-bold fs-6 mb-1 <?= $r['status'] === 'open' ? 'text-success' : 'text-dark' ?>">
                        <?= $r['status'] === 'open' ? fmt_minutes($openDuration) : fmt_minutes($r['duration_minutes'] !== null ? (int)$r['duration_minutes'] : null) ?>
                    </div>
                    <?php if ($r['status'] === 'open'): ?>
                        <form method="post" data-confirm="Check this guard out now?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="close">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1 shadow-sm" style="font-size:0.72rem;">
                                Check out
                            </button>
                        </form>
                    <?php endif; ?>
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
                    <th>Client / site</th>
                    <th>Check in</th>
                    <th>Check out</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calendar-x"></i>No shifts in this period</div></td></tr>
            <?php endif; ?>
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
