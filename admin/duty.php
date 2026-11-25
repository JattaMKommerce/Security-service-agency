<?php
/**
 * Overtime & Handover: approve duty extension requests, review overtime,
 * and see the shift handover log.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
$tab = in_array(get_str('tab', 12), ['requests', 'overtime', 'handovers'], true) ? get_str('tab', 12) : 'requests';

if (is_post()) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'ext_decide') {
        $id = (int)post_int('id', 0);
        $x = q_one("SELECT x.*, g.full_name FROM duty_extensions x JOIN guards g ON g.id = x.guard_id WHERE x.id = ?", [$id]);
        $approve = ($_POST['decision'] ?? '') === 'approve';
        $note = post_str('note', 255);
        if (!$x || $x['status'] !== 'pending') {
            flash('warning', 'This request was already handled (or the guard ended duty / cancelled it).');
        } else {
            $mins = $approve ? max(15, min(480, (int)post_int('minutes', (int)$x['requested_minutes']))) : null;
            q('UPDATE duty_extensions SET status = ?, approved_minutes = ?, decided_by = ?, decided_at = ?, admin_note = ? WHERE id = ? AND status = \'pending\'',
              [$approve ? 'approved' : 'rejected', $mins, $admin['id'], now(), $note, $id]);
            log_activity('duty.extension_' . ($approve ? 'approve' : 'reject'), $x['full_name'] . ($approve ? ' +' . fmt_minutes($mins) : '') . ($note ? ': ' . $note : ''));
            flash('success', $approve ? 'Extra ' . fmt_minutes($mins) . ' approved for ' . $x['full_name'] . '. The guard is notified in the app.' : 'Request rejected. The guard is notified in the app.');
        }
        redirect('admin/duty.php?tab=requests');
    }

    if ($action === 'ot_approve') {
        $id = (int)post_int('id', 0);
        $n = q("UPDATE attendance SET approved_ot_minutes = overtime_minutes WHERE id = ? AND status <> 'open' AND overtime_minutes > COALESCE(approved_ot_minutes, 0)", [$id])->rowCount();
        if ($n) log_activity('duty.ot_approve', 'attendance #' . $id);
        flash($n ? 'success' : 'warning', $n ? 'Overtime approved.' : 'Nothing to approve.');
        redirect(safe_back_url('admin/duty.php?tab=overtime'));
    }
}

$pendingCount = (int)q_val("SELECT COUNT(*) FROM duty_extensions WHERE status = 'pending'");

$pageTitle = 'Overtime & Handover';
$activeNav = 'duty';
require APP_ROOT . '/includes/layout/header.php';
$tabLink = fn(string $t, string $icon, string $text, int $badge = 0) => '<a href="' . url('admin/duty.php?tab=' . $t) . '" class="btn ' . ($tab === $t ? 'btn-primary' : 'btn-outline-primary bg-white') . ' fw-semibold">'
    . '<i class="bi ' . $icon . ' me-1"></i>' . $text . ($badge ? ' <span class="badge rounded-pill bg-danger">' . $badge . '</span>' : '') . '</a>';
?>
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Overtime, Extensions &amp; Handovers</h2>
        <p class="guard-hero-sub">Approve extra working hours, review overtime and see who handed over duty to whom</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(124,58,237,0.2);"><i class="bi bi-hourglass-split"></i></div>
</div>
<div class="btn-group w-100 mb-3 flex-wrap" role="group">
    <?= $tabLink('requests', 'bi-hourglass-split', 'Extra hours', $pendingCount) ?>
    <?= $tabLink('overtime', 'bi-clock-history', 'Overtime') ?>
    <?= $tabLink('handovers', 'bi-arrow-left-right', 'Handovers') ?>
</div>

<?php if ($tab === 'requests'):
    $pending = q_all("SELECT x.*, g.full_name, g.employee_code, g.photo, g.phone, at.check_in_at, at.shift_end_at, c.name AS client_name, a.site_name
                        FROM duty_extensions x JOIN guards g ON g.id = x.guard_id JOIN attendance at ON at.id = x.attendance_id
                        LEFT JOIN assignments a ON a.id = x.assignment_id LEFT JOIN clients c ON c.id = a.client_id
                       WHERE x.status = 'pending' ORDER BY x.requested_at");
    $recent = q_all("SELECT x.*, g.full_name, g.employee_code, d.name AS decided_name
                       FROM duty_extensions x JOIN guards g ON g.id = x.guard_id LEFT JOIN admins d ON d.id = x.decided_by
                      WHERE x.status <> 'pending' AND x.requested_at >= ? ORDER BY x.requested_at DESC LIMIT 50", [date('Y-m-d H:i:s', strtotime('-30 days'))]);
?>
    <h2 class="fs-5 fw-bold mb-3">Waiting for approval (<?= count($pending) ?>)</h2>
    <?php if (!$pending): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center"><i class="bi bi-check2-circle fs-2 text-success d-block mb-2"></i>
            <div class="fw-semibold">No pending requests</div><div class="small text-muted">Guards request extra hours from the app while on duty.</div></div>
    <?php endif; ?>
    <div class="row g-3 mb-4">
    <?php foreach ($pending as $x): ?>
        <div class="col-lg-6">
            <div class="card h-100 border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <?= avatar($x['full_name'], $x['photo'], 'md') ?>
                        <div class="min-w-0"><div class="fw-bold"><?= e($x['full_name']) ?> <span class="small text-muted fw-normal"><?= e($x['employee_code']) ?></span></div>
                            <div class="small text-muted text-truncate"><?= e($x['client_name'] ?? 'No client') ?><?= $x['site_name'] ? ' · ' . e($x['site_name']) : '' ?></div></div>
                        <a href="tel:<?= e($x['phone']) ?>" class="btn btn-sm btn-light ms-auto" aria-label="Call"><i class="bi bi-telephone"></i></a>
                    </div>
                    <div class="fs-5 fw-bold" style="color:#7c3aed">+<?= fmt_minutes((int)$x['requested_minutes']) ?> requested</div>
                    <div class="small text-muted mb-2">On duty since <?= e(fmt_datetime($x['check_in_at'])) ?><?= $x['shift_end_at'] ? ' · shift ends ' . e(fmt_date($x['shift_end_at'], 'h:i A')) : '' ?> · asked <?= e(time_ago($x['requested_at'])) ?></div>
                    <div class="g-note-admin mb-3"><i class="bi bi-chat-left-quote me-1"></i><?= e($x['reason']) ?></div>
                    <form method="post" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="ext_decide">
                        <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
                        <div class="col-5">
                            <select name="minutes" class="form-select form-select-sm" aria-label="Approve how much">
                                <?php foreach ([30, 60, 90, 120, 180, 240, 300, 360, 480] as $m): ?>
                                    <option value="<?= $m ?>" <?= $m === (int)$x['requested_minutes'] ? 'selected' : '' ?>><?= fmt_minutes($m) ?></option>
                                <?php endforeach; ?>
                                <?php if (!in_array((int)$x['requested_minutes'], [30, 60, 90, 120, 180, 240, 300, 360, 480], true)): ?>
                                    <option value="<?= (int)$x['requested_minutes'] ?>" selected><?= fmt_minutes((int)$x['requested_minutes']) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-7"><input name="note" class="form-control form-control-sm" maxlength="255" placeholder="Note to guard (optional)"></div>
                        <div class="col-6"><button name="decision" value="approve" class="btn btn-success w-100 fw-semibold"><i class="bi bi-check2-circle"></i> Approve</button></div>
                        <div class="col-6"><button name="decision" value="reject" class="btn btn-outline-danger w-100" data-confirm="Reject this request?"><i class="bi bi-x-circle"></i> Reject</button></div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <h2 class="fs-6 fw-bold mb-2 text-muted">Last 30 days</h2>
    <div class="card table-card mb-3"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Guard</th><th>Requested</th><th>Reason</th><th>Decision</th><th>By</th></tr></thead>
            <tbody>
            <?php if (!$recent): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i>No requests yet</div></td></tr><?php endif; ?>
            <?php foreach ($recent as $x): ?>
                <tr>
                    <td class="small"><span class="fw-semibold"><?= e($x['full_name']) ?></span><div class="text-muted"><?= e($x['employee_code']) ?></div></td>
                    <td class="small text-nowrap"><?= fmt_minutes((int)$x['requested_minutes']) ?><div class="text-muted"><?= e(fmt_datetime($x['requested_at'])) ?></div></td>
                    <td class="small"><?= e($x['reason']) ?></td>
                    <td class="small"><?php
                        $cls = ['approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'][$x['status']] ?? 'secondary'; ?>
                        <span class="badge badge-soft-<?= $cls ?>"><?= e(label($x['status'])) ?><?= $x['status'] === 'approved' ? ' ' . fmt_minutes((int)$x['approved_minutes']) : '' ?></span>
                        <?= $x['admin_note'] ? '<div class="text-muted">' . e($x['admin_note']) . '</div>' : '' ?></td>
                    <td class="small text-muted"><?= e($x['decided_name'] ?? ($x['status'] === 'cancelled' ? 'Guard / system' : '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>

<?php elseif ($tab === 'overtime'):
    $ym = get_str('month', 7);
    if (!valid_month($ym) || $ym > date('Y-m')) $ym = date('Y-m');
    $from = $ym . '-01 00:00:00';
    $to = date('Y-m-d', strtotime($ym . '-01 +1 month')) . ' 00:00:00';
    $byGuard = q_all("SELECT g.id, g.full_name, g.employee_code, g.photo, COUNT(*) shifts,
                             SUM(at.overtime_minutes) ot, SUM(COALESCE(at.approved_ot_minutes, 0)) ok
                        FROM attendance at JOIN guards g ON g.id = at.guard_id
                       WHERE at.check_in_at >= ? AND at.check_in_at < ? AND at.overtime_minutes > 0
                       GROUP BY g.id ORDER BY ot DESC", [$from, $to]);
    $shifts = q_all("SELECT at.*, g.full_name, g.employee_code, c.name AS client_name, a.site_name,
                            (SELECT GROUP_CONCAT(CONCAT(x.reason) SEPARATOR ' | ') FROM duty_extensions x WHERE x.attendance_id = at.id AND x.status = 'approved') AS ext_reasons
                       FROM attendance at JOIN guards g ON g.id = at.guard_id
                       LEFT JOIN clients c ON c.id = at.client_id LEFT JOIN assignments a ON a.id = at.assignment_id
                      WHERE at.check_in_at >= ? AND at.check_in_at < ? AND at.overtime_minutes > 0
                      ORDER BY (at.overtime_minutes > COALESCE(at.approved_ot_minutes, 0)) DESC, at.check_in_at DESC LIMIT 300", [$from, $to]);
    $totOt = array_sum(array_column($byGuard, 'ot'));
    $totOk = array_sum(array_column($byGuard, 'ok'));
?>
    <div class="filter-card-clean">
        <form method="get" class="d-flex align-items-center gap-2">
            <input type="hidden" name="tab" value="overtime">
            <a class="btn btn-light" href="<?= url('admin/duty.php?tab=overtime&month=' . date('Y-m', strtotime($ym . '-01 -1 month'))) ?>" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
            <input type="month" name="month" value="<?= e($ym) ?>" max="<?= date('Y-m') ?>" class="form-control form-control-clean text-center fw-semibold" onchange="this.form.submit()">
            <?php if ($ym < date('Y-m')): ?><a class="btn btn-light" href="<?= url('admin/duty.php?tab=overtime&month=' . date('Y-m', strtotime($ym . '-01 +1 month'))) ?>" aria-label="Next month"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
        </form>
    </div>
    <div class="stat-grid-4">
        <div class="stat-box-modern"><div class="stat-box-icon text-purple bg-purple-subtle"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-box-num"><?= round($totOt / 60, 1) ?>h</div><div class="stat-box-lbl">Total overtime</div></div>
        <div class="stat-box-modern"><div class="stat-box-icon text-success bg-success-subtle"><i class="bi bi-check2-circle"></i></div>
            <div class="stat-box-num"><?= round($totOk / 60, 1) ?>h</div><div class="stat-box-lbl">Approved</div></div>
        <div class="stat-box-modern"><div class="stat-box-icon text-warning bg-warning-subtle"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-box-num"><?= round(($totOt - $totOk) / 60, 1) ?>h</div><div class="stat-box-lbl">Unapproved</div></div>
        <div class="stat-box-modern"><div class="stat-box-icon text-primary bg-primary-subtle"><i class="bi bi-people-fill"></i></div>
            <div class="stat-box-num"><?= count($byGuard) ?></div><div class="stat-box-lbl">Guards with OT</div></div>
    </div>
    <p class="small text-muted">Overtime = time worked after the deployment's shift end time<?= ' (deployments without shift times use ' . e(setting('default_shift_hours', '8')) . ' h)' ?>. Less than <?= (int)setting('ot_min_minutes', '15') ?> min is not counted. Only hours approved through an extra-hours request count as approved — you can approve the rest below after checking.</p>

    <div class="card table-card mb-3"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Guard</th><th class="text-end">Shifts with OT</th><th class="text-end">Overtime</th><th class="text-end">Approved</th><th class="text-end">Unapproved</th></tr></thead>
            <tbody>
            <?php if (!$byGuard): ?><tr><td colspan="5"><div class="empty-state"><i class="bi bi-hourglass"></i>No overtime this month</div></td></tr><?php endif; ?>
            <?php foreach ($byGuard as $g): ?>
                <tr>
                    <td><a class="person" href="<?= url('admin/attendance.php?guard_id=' . $g['id'] . '&from=' . $ym . '-01&to=' . min(date('Y-m-d'), date('Y-m-t', strtotime($ym . '-01')))) ?>"><?= avatar($g['full_name'], $g['photo'], 'sm') ?>
                        <span><span class="name d-block"><?= e($g['full_name']) ?></span><span class="sub"><?= e($g['employee_code']) ?></span></span></a></td>
                    <td class="text-end"><?= (int)$g['shifts'] ?></td>
                    <td class="text-end fw-semibold" style="color:#7c3aed"><?= fmt_minutes((int)$g['ot']) ?></td>
                    <td class="text-end text-success"><?= fmt_minutes((int)$g['ok']) ?></td>
                    <td class="text-end <?= $g['ot'] > $g['ok'] ? 'text-warning fw-semibold' : 'text-muted' ?>"><?= fmt_minutes((int)$g['ot'] - (int)$g['ok']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>

    <h2 class="fs-6 fw-bold mb-2">Overtime shifts</h2>
    <div class="card table-card mb-3"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Guard</th><th>Shift</th><th>Scheduled end</th><th class="text-end">Overtime</th><th>Approval</th><th></th></tr></thead>
            <tbody>
            <?php if (!$shifts): ?><tr><td colspan="6"><div class="empty-state"><i class="bi bi-hourglass"></i>No overtime shifts</div></td></tr><?php endif; ?>
            <?php foreach ($shifts as $r): $un = (int)$r['overtime_minutes'] - (int)$r['approved_ot_minutes']; ?>
                <tr>
                    <td class="small"><span class="fw-semibold"><?= e($r['full_name']) ?></span><div class="text-muted"><?= e($r['client_name'] ?? 'No client') ?><?= $r['site_name'] ? ' · ' . e($r['site_name']) : '' ?></div></td>
                    <td class="small text-nowrap"><?= e(fmt_datetime($r['check_in_at'])) ?><div class="text-muted">→ <?= e(fmt_datetime($r['check_out_at'])) ?></div></td>
                    <td class="small text-nowrap"><?= e(fmt_datetime($r['shift_end_at'])) ?></td>
                    <td class="text-end fw-semibold" style="color:#7c3aed"><?= fmt_minutes((int)$r['overtime_minutes']) ?></td>
                    <td class="small"><?= $un > 0 ? '<span class="badge badge-soft-warning">' . fmt_minutes($un) . ' unapproved</span>' : '<span class="badge badge-soft-success">Approved</span>' ?>
                        <?= $r['ext_reasons'] ? '<div class="text-muted">' . e($r['ext_reasons']) . '</div>' : '' ?></td>
                    <td class="text-end">
                        <?php if ($un > 0 && $r['status'] !== 'open'): ?>
                            <form method="post" data-confirm="Approve all <?= e(fmt_minutes((int)$r['overtime_minutes'])) ?> of overtime for this shift?"><?= csrf_field() ?>
                                <input type="hidden" name="action" value="ot_approve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-sm btn-outline-success text-nowrap">Approve OT</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>

<?php else:
    $hfrom = get_str('from', 10);
    $hto = get_str('to', 10);
    if (!valid_date($hfrom)) $hfrom = date('Y-m-d', strtotime('-7 days'));
    if (!valid_date($hto)) $hto = date('Y-m-d');
    $hs = get_str('status', 10);
    $where = ['h.created_at >= ?', 'h.created_at < ?'];
    $params = [$hfrom . ' 00:00:00', date('Y-m-d', strtotime($hto . ' +1 day')) . ' 00:00:00'];
    if (in_array($hs, ['pending', 'accepted', 'disputed'], true)) { $where[] = 'h.status = ?'; $params[] = $hs; }
    $rows = q_all('SELECT h.*, og.full_name AS out_name, og.employee_code AS out_code, ig.full_name AS in_name, ig.employee_code AS in_code, c.name AS client_name
                     FROM shift_handovers h JOIN guards og ON og.id = h.outgoing_guard_id JOIN guards ig ON ig.id = h.incoming_guard_id
                     LEFT JOIN clients c ON c.id = h.client_id
                    WHERE ' . implode(' AND ', $where) . ' ORDER BY h.created_at DESC LIMIT 300', $params);
?>
    <div class="filter-card-clean">
        <form method="get" class="row g-2">
            <input type="hidden" name="tab" value="handovers">
            <div class="col-6"><label class="form-label small text-muted mb-1">From</label><input type="date" name="from" value="<?= e($hfrom) ?>" class="form-control form-control-clean"></div>
            <div class="col-6"><label class="form-label small text-muted mb-1">To</label><input type="date" name="to" value="<?= e($hto) ?>" class="form-control form-control-clean"></div>
            <div class="col-8">
                <select name="status" class="form-select form-select-clean">
                    <option value="">Any status</option>
                    <?php foreach (['pending' => 'Waiting for incoming guard', 'accepted' => 'Accepted', 'disputed' => 'Problem reported'] as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $hs === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4"><button class="btn btn-primary w-100 fw-semibold"><i class="bi bi-funnel"></i> Filter</button></div>
        </form>
    </div>
    <h2 class="fs-5 fw-bold mb-3">Handovers (<?= count($rows) ?>)</h2>
    <?php if (!$rows): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center"><i class="bi bi-arrow-left-right fs-2 text-muted d-block mb-2"></i>
            <div class="fw-semibold">No handovers in this period</div><div class="small text-muted">Guards use “Hand over” in the app when the next guard takes over.</div></div>
    <?php endif; ?>
    <div class="row g-3 mb-3">
    <?php foreach ($rows as $h):
        $hcls = ['pending' => 'warning', 'accepted' => 'success', 'disputed' => 'danger'][$h['status']];
        $hlbl = ['pending' => 'Waiting for incoming guard', 'accepted' => 'Accepted', 'disputed' => 'Problem reported'][$h['status']]; ?>
        <div class="col-lg-6">
            <div class="card h-100<?= $h['status'] === 'disputed' ? ' border-danger' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="small text-muted"><i class="bi bi-geo-alt"></i> <?= e($h['client_name'] ?? 'No client') ?><?= $h['site_name'] ? ' · ' . e($h['site_name']) : '' ?></div>
                        <span class="badge badge-soft-<?= $hcls ?>"><?= $hlbl ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2 fw-semibold mb-2">
                        <a href="<?= url('admin/guard_view.php?id=' . $h['outgoing_guard_id']) ?>"><?= e($h['out_name']) ?></a>
                        <i class="bi bi-arrow-right text-muted"></i>
                        <a href="<?= url('admin/guard_view.php?id=' . $h['incoming_guard_id']) ?>"><?= e($h['in_name']) ?></a>
                    </div>
                    <div class="small text-muted mb-2"><?= e(fmt_datetime($h['created_at'])) ?><?= $h['responded_at'] ? ' · answered ' . e(fmt_datetime($h['responded_at'])) : '' ?></div>
                    <?php if ($h['items']): ?><div class="small mb-1"><strong>Items:</strong> <?= e($h['items']) ?></div><?php endif; ?>
                    <?php if ($h['notes']): ?><div class="g-note-admin small mb-1"><i class="bi bi-chat-left-text me-1"></i><?= e($h['notes']) ?></div><?php endif; ?>
                    <?php if ($h['incoming_note']): ?><div class="small text-<?= $h['status'] === 'disputed' ? 'danger' : 'muted' ?>"><strong><?= e($h['in_name']) ?>:</strong> <?= e($h['incoming_note']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
