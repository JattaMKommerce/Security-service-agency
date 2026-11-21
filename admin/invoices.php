<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$status = get_str('status', 20);
$clientId = get_int('client_id');
$search = get_str('q');
$statuses = ['draft', 'sent', 'partially_paid', 'paid', 'cancelled'];

$where = ['1=1'];
$params = [];
if (in_array($status, $statuses, true)) { $where[] = 'i.status = ?'; $params[] = $status; }
if ($clientId) { $where[] = 'i.client_id = ?'; $params[] = $clientId; }
if ($search !== '') { $where[] = '(i.invoice_no LIKE ? OR c.name LIKE ?)'; array_push($params, "%$search%", "%$search%"); }

$fromSql = 'FROM invoices i JOIN clients c ON c.id = i.client_id WHERE ' . implode(' AND ', $where);
$p = paginate($fromSql, $params, 'i.*, c.name AS client_name', 'i.id DESC', 30);
$sum = q_one("SELECT COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total END),0) billed,
                     COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN amount_paid END),0) paid,
                     COALESCE(SUM(CASE WHEN status IN ('sent','partially_paid') AND due_on < CURDATE() THEN total - amount_paid END),0) overdue
                FROM invoices");
$clients = q_all('SELECT id, name FROM clients ORDER BY name');

$pageTitle = 'Invoices';
$activeNav = 'invoices';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card stat-card"><span class="stat-icon tint-blue"><i class="bi bi-receipt"></i></span><div><div class="stat-value fs-5"><?= money($sum['billed']) ?></div><div class="stat-label">Total billed</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><span class="stat-icon tint-green"><i class="bi bi-cash-coin"></i></span><div><div class="stat-value fs-5"><?= money($sum['paid']) ?></div><div class="stat-label">Received</div></div></div></div>
    <div class="col-md-4"><div class="card stat-card"><span class="stat-icon tint-red"><i class="bi bi-hourglass-split"></i></span><div><div class="stat-value fs-5"><?= money($sum['overdue']) ?></div><div class="stat-label">Overdue</div></div></div></div>
</div>

<div class="page-actions">
    <form class="filter-bar" method="get">
        <input type="search" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Invoice no. or client">
        <select name="client_id" class="form-select"><option value="">All clients</option>
            <?php foreach ($clients as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $clientId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        <select name="status" class="form-select"><option value="">Any status</option>
            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= label($s) ?></option><?php endforeach; ?></select>
        <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filter</button>
    </form>
    <a href="<?= url('admin/invoice_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New invoice</a>
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Invoice</th><th>Client</th><th>Date</th><th>Due</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$p['rows']): ?><tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt"></i>No invoices yet</div></td></tr><?php endif; ?>
            <?php foreach ($p['rows'] as $i):
                $overdue = in_array($i['status'], ['sent', 'partially_paid'], true) && $i['due_on'] && $i['due_on'] < date('Y-m-d'); ?>
                <tr>
                    <td><a href="<?= url('admin/invoice_view.php?id=' . $i['id']) ?>" class="fw-semibold"><?= e($i['invoice_no']) ?></a></td>
                    <td><a href="<?= url('admin/client_view.php?id=' . $i['client_id']) ?>"><?= e($i['client_name']) ?></a></td>
                    <td class="small"><?= e(fmt_date($i['issued_on'])) ?></td>
                    <td class="small <?= $overdue ? 'text-danger fw-semibold' : '' ?>"><?= e(fmt_date($i['due_on'])) ?><?= $overdue ? ' · overdue' : '' ?></td>
                    <td class="text-end fw-semibold"><?= money($i['total']) ?></td>
                    <td class="text-end"><?= $i['status'] === 'cancelled' ? '—' : money((float)$i['total'] - (float)$i['amount_paid']) ?></td>
                    <td><?= status_badge($i['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?= pagination_links($p) ?></div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
