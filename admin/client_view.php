<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$id = get_int('id');
$c = q_one('SELECT * FROM clients WHERE id = ?', [$id]);
if (!$c) {
    flash('danger', 'Client not found.');
    redirect('admin/clients.php');
}

$hired = q_all(guards_live_sql('a.client_id = ?') . ' ORDER BY a.site_name, g.full_name', [$id]);
$history = q_all("SELECT a.*, g.full_name, g.employee_code FROM assignments a JOIN guards g ON g.id = a.guard_id
                   WHERE a.client_id = ? AND a.status <> 'active' ORDER BY a.id DESC LIMIT 30", [$id]);
$invoices = q_all('SELECT * FROM invoices WHERE client_id = ? ORDER BY id DESC LIMIT 10', [$id]);
$billing = q_one("SELECT COALESCE(SUM(total),0) billed, COALESCE(SUM(amount_paid),0) paid FROM invoices WHERE client_id = ? AND status <> 'cancelled'", [$id]);

$pageTitle = $c['name'];
$activeNav = 'clients';
$useMap = true;
$pageScripts = ['js/tracking.js'];
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions">
    <a href="<?= url('admin/clients.php') ?>" class="small"><i class="bi bi-arrow-left"></i> All clients</a>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($c['status'] === 'active'): ?>
            <a href="<?= url('admin/assignment_form.php?client_id=' . $id) ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Hire guards for this client</a>
        <?php endif; ?>
        <a href="<?= url('admin/invoice_form.php?client_id=' . $id) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt"></i> Create invoice</a>
        <a href="<?= url('admin/client_form.php?id=' . $id) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="stat-icon tint-amber"><i class="bi bi-building-fill"></i></span>
                    <div><h2 class="h5 fw-bold mb-0"><?= e($c['name']) ?></h2><div class="small text-muted"><?= e($c['client_code']) ?> · <?= e(label($c['client_type'])) ?></div></div>
                    <span class="ms-auto"><?= status_badge($c['status']) ?></span>
                </div>
                <dl class="kv">
                    <dt>Contact</dt><dd><?= e($c['contact_person'] ?: '—') ?></dd>
                    <dt>Phone</dt><dd><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a><?= $c['alt_phone'] ? ' / ' . e($c['alt_phone']) : '' ?></dd>
                    <dt>Email</dt><dd><?= e($c['email'] ?: '—') ?></dd>
                    <dt>Address</dt><dd><?= nl2br(e(trim(($c['address'] ?? '') . "\n" . implode(', ', array_filter([$c['city'], $c['state'], $c['pincode']]))) ?: '—')) ?></dd>
                    <dt>GSTIN</dt><dd><?= e($c['gstin'] ?: '—') ?></dd>
                    <dt>Notes</dt><dd><?= nl2br(e($c['notes'] ?: '—')) ?></dd>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="row text-center g-0">
                    <div class="col-4"><div class="stat-value"><?= count($hired) ?></div><div class="stat-label">Guards hired</div></div>
                    <div class="col-4"><div class="stat-value fs-6 pt-2"><?= money($billing['billed']) ?></div><div class="stat-label">Billed</div></div>
                    <div class="col-4"><div class="stat-value fs-6 pt-2"><?= money((float)$billing['billed'] - (float)$billing['paid']) ?></div><div class="stat-label">Outstanding</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">Live location of this client's guards
                <a href="<?= url('admin/tracking.php?client_id=' . $id) ?>" class="btn btn-sm btn-outline-primary ms-auto">Full screen</a></div>
            <div class="card-body p-0"><div id="clientMap" style="height:340px"></div></div>
        </div>

        <div class="card table-card mb-3">
            <div class="card-header">Guards currently hired (<?= count($hired) ?>)</div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Bodyguard</th><th>Site</th><th>Shift</th><th>Live status</th><th>Last update</th><th></th></tr></thead>
                <tbody>
                <?php if (!$hired): ?><tr><td colspan="6" class="text-center text-muted py-4">No guards hired right now</td></tr><?php endif; ?>
                <?php foreach ($hired as $g):
                    $st = guard_live_status($g);
                    [$stLabel, $stColor] = live_status_meta($st); ?>
                    <tr>
                        <td><a class="person" href="<?= url('admin/guard_view.php?id=' . $g['id']) ?>"><?= avatar($g['full_name'], $g['photo'], 'sm') ?>
                            <span><span class="name d-block"><?= e($g['full_name']) ?></span><span class="sub"><?= e($g['employee_code']) ?></span></span></a></td>
                        <td class="small"><?= e($g['site_name']) ?></td>
                        <td class="small"><?= $g['shift_start'] ? e(fmt_time($g['shift_start']) . ' – ' . fmt_time($g['shift_end'])) : '—' ?></td>
                        <td><span class="status-dot" style="background:<?= $stColor ?>"></span><span class="small"><?= e($stLabel) ?></span></td>
                        <td class="small"><?= e(time_ago($g['last_seen_at'])) ?></td>
                        <td class="text-end"><a href="<?= url('admin/assignment_view.php?id=' . $g['assignment_id']) ?>" class="btn btn-sm btn-light">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>

        <div class="card table-card mb-3">
            <div class="card-header d-flex">Invoices <a class="ms-auto small" href="<?= url('admin/invoices.php?client_id=' . $id) ?>">All invoices</a></div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Invoice</th><th>Date</th><th>Period</th><th class="text-end">Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$invoices): ?><tr><td colspan="5" class="text-center text-muted py-4">No invoices yet</td></tr><?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="<?= url('admin/invoice_view.php?id=' . $inv['id']) ?>" class="fw-semibold"><?= e($inv['invoice_no']) ?></a></td>
                        <td class="small"><?= e(fmt_date($inv['issued_on'])) ?></td>
                        <td class="small"><?= e(fmt_date($inv['period_from'])) ?> – <?= e(fmt_date($inv['period_to'])) ?></td>
                        <td class="text-end fw-semibold"><?= money($inv['total']) ?></td>
                        <td><?= status_badge($inv['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>

        <div class="card table-card">
            <div class="card-header">Past deployments</div>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Bodyguard</th><th>Site</th><th>Period</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$history): ?><tr><td colspan="4" class="text-center text-muted py-4">No past deployments</td></tr><?php endif; ?>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><a href="<?= url('admin/guard_view.php?id=' . $h['guard_id']) ?>"><?= e($h['full_name']) ?></a> <span class="small text-muted"><?= e($h['employee_code']) ?></span></td>
                        <td class="small"><a href="<?= url('admin/assignment_view.php?id=' . $h['id']) ?>"><?= e($h['site_name']) ?></a></td>
                        <td class="small"><?= e(fmt_date($h['start_date'])) ?> → <?= e(fmt_date($h['end_date'] ?? $h['ended_at'])) ?></td>
                        <td><?= status_badge($h['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </div>
    </div>
</div>
<?php
$inlineScript = '<script>document.addEventListener("DOMContentLoaded", function () {
    APP.liveMap({ el: "clientMap", query: "client_id=' . $id . '", refreshMs: 15000 });
});</script>';
require APP_ROOT . '/includes/layout/footer.php';
