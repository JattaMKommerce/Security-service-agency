<?php
/** Monthly attendance register (included by admin/attendance.php). */
if (!defined('APP_ROOT')) { http_response_code(404); exit; }

$ym = get_str('month', 7);
if (!valid_month($ym) || $ym > date('Y-m')) $ym = date('Y-m');
$regClient = get_int('client_id');
$regGuards = q_all("SELECT g.id, g.full_name, g.employee_code, g.photo FROM guards g
                     WHERE (g.status <> 'inactive' OR EXISTS (SELECT 1 FROM attendance x WHERE x.guard_id = g.id AND x.check_in_at >= ?))
                       " . ($regClient ? 'AND EXISTS (SELECT 1 FROM assignments y WHERE y.guard_id = g.id AND y.client_id = ? AND y.start_date <= ? AND (y.end_date IS NULL OR y.end_date >= ?))' : '') . "
                     ORDER BY g.full_name",
                   $regClient ? [$ym . '-01', $regClient, date('Y-m-t', strtotime($ym . '-01')), $ym . '-01'] : [$ym . '-01']);
$sheet = [];
$tot = ['present' => 0, 'late' => 0, 'absent' => 0, 'minutes' => 0, 'ot' => 0, 'ot_approved' => 0];
foreach ($regGuards as $g) {
    $m = attendance_month((int)$g['id'], $ym);
    $sheet[] = ['g' => $g, 'm' => $m];
    foreach ($tot as $k => $_) $tot[$k] += $m['summary'][$k];
}
$daysIn = (int)date('t', strtotime($ym . '-01'));
$cell = ['present' => ['P', 'reg-p'], 'late' => ['L', 'reg-l'], 'absent' => ['A', 'reg-a'], 'pending' => ['·', 'reg-n'], 'upcoming' => ['', 'reg-n'], 'none' => ['–', 'reg-n']];
$prev = date('Y-m', strtotime($ym . '-01 -1 month'));
$next = $ym < date('Y-m') ? date('Y-m', strtotime($ym . '-01 +1 month')) : null;
$qs = fn(string $m) => url('admin/attendance.php?view=register&month=' . $m . ($regClient ? '&client_id=' . $regClient : ''));
?>
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Monthly Attendance Register</h2>
        <p class="guard-hero-sub">Present, late and absent days with duty hours and overtime for every guard</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(37,99,235,0.18);"><i class="bi bi-calendar3"></i></div>
</div>
<?= $viewTabs ?>

<div class="filter-card-clean">
    <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="view" value="register">
        <div class="col-12 d-flex align-items-center justify-content-between gap-2">
            <a class="btn btn-light" href="<?= e($qs($prev)) ?>" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
            <input type="month" name="month" value="<?= e($ym) ?>" max="<?= date('Y-m') ?>" class="form-control form-control-clean text-center fw-semibold" onchange="this.form.submit()">
            <?php if ($next): ?><a class="btn btn-light" href="<?= e($qs($next)) ?>" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
            <?php else: ?><span class="btn btn-light disabled"><i class="bi bi-chevron-right"></i></span><?php endif; ?>
        </div>
        <div class="col-12">
            <div class="filter-select-wrap">
                <i class="bi bi-building filter-select-icon"></i>
                <select name="client_id" class="form-select form-select-clean" onchange="this.form.submit()">
                    <option value="">All clients</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $regClient === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
</div>

<div class="stat-grid-4">
    <div class="stat-box-modern"><div class="stat-box-icon text-success bg-success-subtle"><i class="bi bi-person-check-fill"></i></div>
        <div class="stat-box-num"><?= $tot['present'] ?></div><div class="stat-box-lbl">Present days</div></div>
    <div class="stat-box-modern"><div class="stat-box-icon text-warning bg-warning-subtle"><i class="bi bi-alarm-fill"></i></div>
        <div class="stat-box-num"><?= $tot['late'] ?></div><div class="stat-box-lbl">Late days</div></div>
    <div class="stat-box-modern"><div class="stat-box-icon text-danger bg-danger-subtle"><i class="bi bi-person-x-fill"></i></div>
        <div class="stat-box-num"><?= $tot['absent'] ?></div><div class="stat-box-lbl">Absent days</div></div>
    <div class="stat-box-modern"><div class="stat-box-icon text-purple bg-purple-subtle"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-box-num"><?= round($tot['ot'] / 60, 1) ?>h</div><div class="stat-box-lbl">Overtime (<?= round(($tot['ot'] - $tot['ot_approved']) / 60, 1) ?>h unapproved)</div></div>
</div>

<div class="card table-card mb-3">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 reg-table">
            <thead>
                <tr>
                    <th class="reg-sticky">Guard</th>
                    <?php for ($d = 1; $d <= $daysIn; $d++): $dow = (int)date('w', strtotime($ym . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT))); ?>
                        <th class="text-center<?= $dow === 0 ? ' reg-sun' : '' ?>"><?= $d ?><div class="reg-dow"><?= ['S', 'M', 'T', 'W', 'T', 'F', 'S'][$dow] ?></div></th>
                    <?php endfor; ?>
                    <th class="text-center">P</th><th class="text-center">L</th><th class="text-center">A</th>
                    <th class="text-end">Hours</th><th class="text-end">OT</th><th class="text-end">OT approved</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$sheet): ?>
                <tr><td colspan="<?= $daysIn + 7 ?>"><div class="empty-state"><i class="bi bi-calendar-x"></i>No guards for this month</div></td></tr>
            <?php endif; ?>
            <?php foreach ($sheet as ['g' => $g, 'm' => $m]): $s = $m['summary']; ?>
                <tr>
                    <td class="reg-sticky"><a class="person" href="<?= url('admin/attendance.php?guard_id=' . $g['id'] . '&from=' . $ym . '-01&to=' . min(date('Y-m-d'), date('Y-m-t', strtotime($ym . '-01')))) ?>">
                        <?= avatar($g['full_name'], $g['photo'], 'sm') ?><span><span class="name d-block text-nowrap"><?= e($g['full_name']) ?></span><span class="sub"><?= e($g['employee_code']) ?></span></span></a></td>
                    <?php foreach ($m['days'] as $day): [$txt, $cls] = $cell[$day['status']];
                        $tip = $day['shifts'] ? fmt_minutes($day['minutes']) . ($day['ot'] ? ' · OT ' . fmt_minutes($day['ot']) : '') : label($day['status']); ?>
                        <td class="text-center <?= $cls ?><?= $day['ot'] ? ' reg-ot' : '' ?>" title="<?= e(fmt_date($day['date']) . ': ' . $tip) ?>"><?= $txt ?></td>
                    <?php endforeach; ?>
                    <td class="text-center fw-semibold text-success"><?= $s['present'] ?></td>
                    <td class="text-center fw-semibold text-warning"><?= $s['late'] ?></td>
                    <td class="text-center fw-semibold text-danger"><?= $s['absent'] ?></td>
                    <td class="text-end text-nowrap"><?= round($s['minutes'] / 60, 1) ?>h</td>
                    <td class="text-end text-nowrap fw-semibold" style="color:#7c3aed"><?= $s['ot'] ? fmt_minutes($s['ot']) : '—' ?></td>
                    <td class="text-end text-nowrap"><?= $s['ot'] ? fmt_minutes($s['ot_approved']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        <span class="reg-key reg-p">P</span> Present · <span class="reg-key reg-l">L</span> Late · <span class="reg-key reg-a">A</span> Absent (deployed, no check-in) ·
        <span class="reg-key reg-n">–</span> Not deployed · <span class="reg-key reg-p reg-ot">P</span> with overtime. Tap a guard for their shift log.
    </div>
</div>
