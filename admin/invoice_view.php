<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$id = get_int('id');
$inv = q_one('SELECT i.*, c.name AS client_name, c.contact_person, c.address, c.city, c.state, c.pincode, c.phone, c.email, c.gstin
                FROM invoices i JOIN clients c ON c.id = i.client_id WHERE i.id = ?', [$id]);
if (!$inv) {
    flash('danger', 'Invoice not found.');
    redirect('admin/invoices.php');
}

if (is_post()) {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_sent' && $inv['status'] === 'draft') {
        q("UPDATE invoices SET status = 'sent' WHERE id = ?", [$id]);
        flash('success', 'Invoice marked as sent.');
    } elseif ($action === 'payment' && in_array($inv['status'], ['draft', 'sent', 'partially_paid'], true)) {
        $amt = round((float)(post_float('amount') ?? 0), 2);
        $balance = round((float)$inv['total'] - (float)$inv['amount_paid'], 2);
        if ($amt <= 0 || $amt > $balance) {
            flash('danger', 'Enter a payment between 0.01 and the balance due (' . money($balance) . ').');
        } else {
            $paid = round((float)$inv['amount_paid'] + $amt, 2);
            $st = $paid >= (float)$inv['total'] ? 'paid' : 'partially_paid';
            q('UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?', [$paid, $st, $id]);
            log_activity('invoice.payment', $inv['invoice_no'] . ' +' . $amt);
            flash('success', 'Payment of ' . money($amt) . ' recorded.');
        }
    } elseif ($action === 'cancel' && $inv['status'] !== 'cancelled' && (float)$inv['amount_paid'] == 0.0) {
        q("UPDATE invoices SET status = 'cancelled' WHERE id = ?", [$id]);
        log_activity('invoice.cancel', $inv['invoice_no']);
        flash('success', 'Invoice cancelled.');
    } elseif ($action === 'delete' && $inv['status'] === 'draft') {
        q('DELETE FROM invoices WHERE id = ?', [$id]);
        log_activity('invoice.delete', $inv['invoice_no']);
        flash('success', 'Draft invoice deleted.');
        redirect('admin/invoices.php');
    }
    redirect('admin/invoice_view.php?id=' . $id);
}

$items = q_all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
$balance = (float)$inv['total'] - (float)$inv['amount_paid'];
$taxLabel = setting('tax_label', 'GST');

$pageTitle = 'Invoice ' . $inv['invoice_no'];
$activeNav = 'invoices';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions no-print">
    <a href="<?= url('admin/invoices.php') ?>" class="small"><i class="bi bi-arrow-left"></i> All invoices</a>
    <div class="d-flex gap-2 flex-wrap">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer"></i> Print / Save PDF</button>
        <?php if ($inv['status'] === 'draft'): ?>
            <a href="<?= url('admin/invoice_form.php?id=' . $id) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_sent"><button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Mark as sent</button></form>
            <form method="post" data-confirm="Delete this draft invoice?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm">Delete</button></form>
        <?php elseif ($inv['status'] !== 'cancelled' && (float)$inv['amount_paid'] == 0.0): ?>
            <form method="post" data-confirm="Cancel this invoice?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger btn-sm">Cancel invoice</button></form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="brand-mark" style="width:44px;height:44px"><i class="bi bi-shield-shaded"></i></span>
                            <span class="fs-4 fw-bold"><?= e(company_name()) ?></span>
                        </div>
                        <div class="small text-muted" style="white-space:pre-line"><?= e(setting('company_address')) ?></div>
                        <div class="small text-muted"><?= e(implode(' · ', array_filter([setting('company_phone'), setting('company_email')]))) ?></div>
                        <?php if (setting('company_gstin')): ?><div class="small"><?= e($taxLabel) ?>IN: <?= e(setting('company_gstin')) ?></div><?php endif; ?>
                    </div>
                    <div class="text-md-end">
                        <div class="fs-3 fw-bold text-uppercase" style="letter-spacing:.08em">Invoice</div>
                        <div class="fw-semibold"><?= e($inv['invoice_no']) ?></div>
                        <div class="small">Date: <?= e(fmt_date($inv['issued_on'])) ?></div>
                        <?php if ($inv['due_on']): ?><div class="small">Due: <?= e(fmt_date($inv['due_on'])) ?></div><?php endif; ?>
                        <div class="mt-1 no-print"><?= status_badge($inv['status']) ?></div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-sm-7">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Bill to</div>
                        <div class="fw-bold"><?= e($inv['client_name']) ?></div>
                        <?php if ($inv['contact_person']): ?><div class="small">Attn: <?= e($inv['contact_person']) ?></div><?php endif; ?>
                        <div class="small" style="white-space:pre-line"><?= e(trim(($inv['address'] ?? '') . "\n" . implode(', ', array_filter([$inv['city'], $inv['state'], $inv['pincode']])))) ?></div>
                        <div class="small"><?= e($inv['phone']) ?><?= $inv['email'] ? ' · ' . e($inv['email']) : '' ?></div>
                        <?php if ($inv['gstin']): ?><div class="small"><?= e($taxLabel) ?>IN: <?= e($inv['gstin']) ?></div><?php endif; ?>
                    </div>
                    <?php if ($inv['period_from']): ?>
                    <div class="col-sm-5 text-sm-end">
                        <div class="text-muted small text-uppercase fw-semibold mb-1">Service period</div>
                        <div><?= e(fmt_date($inv['period_from'])) ?> – <?= e(fmt_date($inv['period_to'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>#</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $n => $it): ?>
                            <tr><td><?= $n + 1 ?></td><td><?= e($it['description']) ?></td>
                                <td class="text-end text-nowrap"><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?> <?= e($it['unit']) ?></td>
                                <td class="text-end text-nowrap"><?= money($it['rate']) ?></td><td class="text-end text-nowrap"><?= money($it['amount']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="row justify-content-end mt-3">
                    <div class="col-sm-6 col-lg-5">
                        <div class="d-flex justify-content-between py-1"><span>Subtotal</span><span><?= money($inv['subtotal']) ?></span></div>
                        <?php if ((float)$inv['discount'] > 0): ?><div class="d-flex justify-content-between py-1"><span>Discount</span><span>− <?= money($inv['discount']) ?></span></div><?php endif; ?>
                        <div class="d-flex justify-content-between py-1"><span><?= e($taxLabel) ?> (<?= rtrim(rtrim(number_format((float)$inv['tax_percent'], 2), '0'), '.') ?>%)</span><span><?= money($inv['tax_amount']) ?></span></div>
                        <div class="d-flex justify-content-between py-2 border-top fw-bold fs-5"><span>Total</span><span><?= money($inv['total']) ?></span></div>
                        <?php if ((float)$inv['amount_paid'] > 0): ?>
                            <div class="d-flex justify-content-between py-1 text-success"><span>Paid</span><span><?= money($inv['amount_paid']) ?></span></div>
                            <div class="d-flex justify-content-between py-1 fw-semibold"><span>Balance due</span><span><?= money($balance) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($inv['notes']): ?><div class="mt-4 small text-muted" style="white-space:pre-line"><?= e($inv['notes']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-3 no-print">
        <?php if (in_array($inv['status'], ['draft', 'sent', 'partially_paid'], true) && $balance > 0): ?>
        <div class="card">
            <div class="card-header">Record payment</div>
            <div class="card-body">
                <div class="small text-muted mb-2">Balance due: <strong><?= money($balance) ?></strong></div>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="payment">
                    <input type="number" step="0.01" min="0.01" max="<?= e(number_format($balance, 2, '.', '')) ?>" name="amount" class="form-control mb-2" value="<?= e(number_format($balance, 2, '.', '')) ?>">
                    <button class="btn btn-success w-100"><i class="bi bi-cash"></i> Record payment</button></form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
