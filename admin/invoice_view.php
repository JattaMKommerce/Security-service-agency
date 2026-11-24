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

require_once APP_ROOT . '/includes/billing.php';
$items = q_all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
$balance = (float)$inv['total'] - (float)$inv['amount_paid'];
$taxLabel = setting('tax_label', 'GST');
$rbi = rbi_bank_details();
$amountInWords = amount_in_words((float)$inv['total']);
$upiPayAmount = $balance > 0 ? $balance : (float)$inv['total'];
$upiUrl = upi_payment_url($rbi['upi_id'], $rbi['company_name'], $upiPayAmount, $inv['invoice_no']);
$upiQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . rawurlencode($upiUrl);

// Split tax: CGST + SGST (9% each for 18% GST) or IGST (18%)
$taxRate = (float)$inv['tax_percent'];
$isSameState = empty($inv['state']) || strtolower($inv['state']) === 'karnataka';
if ($isSameState && $taxRate > 0) {
    $cgstRate = $taxRate / 2;
    $sgstRate = $taxRate / 2;
    $cgstAmount = round((float)$inv['tax_amount'] / 2, 2);
    $sgstAmount = (float)$inv['tax_amount'] - $cgstAmount;
    $igstRate = 0;
    $igstAmount = 0;
} else {
    $cgstRate = 0;
    $sgstRate = 0;
    $cgstAmount = 0;
    $sgstAmount = 0;
    $igstRate = $taxRate;
    $igstAmount = (float)$inv['tax_amount'];
}

$pageTitle = 'Invoice ' . $inv['invoice_no'];
$activeNav = 'invoices';
require APP_ROOT . '/includes/layout/header.php';
?>

<!-- Action Bar (no-print) -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 no-print">
    <a href="<?= url('admin/invoices.php') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3 bg-white text-dark shadow-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Invoices
    </a>
    <div class="d-flex gap-2 flex-wrap">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 bg-white text-dark shadow-sm">
            <i class="bi bi-printer me-1"></i> Print / Save PDF
        </button>
        <?php if ($inv['status'] === 'draft'): ?>
            <a href="<?= url('admin/invoice_form.php?id=' . $id) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_sent"><button class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm"><i class="bi bi-send me-1"></i> Mark Sent</button></form>
            <form method="post" data-confirm="Delete this draft invoice?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm rounded-pill px-3">Delete</button></form>
        <?php elseif ($inv['status'] !== 'cancelled' && (float)$inv['amount_paid'] == 0.0): ?>
            <form method="post" data-confirm="Cancel this invoice?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger btn-sm rounded-pill px-3">Cancel</button></form>
        <?php endif; ?>
    </div>
</div>

<!-- =========================================================================
     1. MOBILE-FRIENDLY INVOICE VIEW (Visible on small screens: d-md-none)
     ========================================================================= -->
<div class="d-md-none no-print mb-4">
    <!-- Top Summary Card -->
    <div class="guard-mobile-card mb-3 p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1">TAX INVOICE</span>
                <span class="fw-bold fs-6 ms-1 text-dark"><?= e($inv['invoice_no']) ?></span>
            </div>
            <?= status_badge($inv['status']) ?>
        </div>
        <div class="my-2 text-center py-2 bg-light rounded-3">
            <div class="text-muted small fw-semibold">TOTAL AMOUNT DUE</div>
            <div class="fs-2 fw-bold text-dark"><?= money($inv['total']) ?></div>
            <?php if ($balance > 0 && $inv['status'] !== 'paid'): ?>
                <div class="small text-danger fw-bold">Balance Due: <?= money($balance) ?></div>
            <?php endif; ?>
        </div>
        <div class="row g-2 text-center small mt-1">
            <div class="col-6">
                <div class="p-2 border rounded-2 bg-white">
                    <div class="text-muted" style="font-size:0.7rem;">DATE</div>
                    <div class="fw-bold"><?= e(fmt_date($inv['issued_on'])) ?></div>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 border rounded-2 bg-white">
                    <div class="text-muted" style="font-size:0.7rem;">DUE DATE</div>
                    <div class="fw-bold text-danger"><?= e(fmt_date($inv['due_on'])) ?></div>
                </div>
            </div>
        </div>

        <!-- Mobile Quick Action Buttons -->
        <div class="mt-3 d-flex flex-column gap-2">
            <?php if ($balance > 0 && $inv['status'] !== 'cancelled'): ?>
                <a href="<?= e($upiUrl) ?>" class="btn btn-success py-2 fw-bold rounded-pill shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-qr-code-scan"></i> Pay <?= money($balance) ?> via UPI
                </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-outline-secondary py-2 fw-semibold rounded-pill bg-white text-dark shadow-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i> Download Official Tax Invoice
            </button>
        </div>
    </div>

    <!-- Client / Bill To Card -->
    <div class="guard-mobile-card mb-3 p-3">
        <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1 border-bottom pb-2">
            <i class="bi bi-building text-primary"></i> Billed To Client
        </div>
        <div class="fw-bold text-dark fs-6"><?= e($inv['client_name']) ?></div>
        <?php if ($inv['contact_person']): ?><div class="small text-muted">Attn: <?= e($inv['contact_person']) ?></div><?php endif; ?>
        <div class="small text-muted" style="white-space:pre-line"><?= e(trim(($inv['address'] ?? '') . "\n" . implode(', ', array_filter([$inv['city'], $inv['state'], $inv['pincode']])))) ?></div>
        <div class="small mt-1">
            <?php if ($inv['phone']): ?><div><i class="bi bi-telephone text-muted me-1"></i><a href="tel:<?= e($inv['phone']) ?>"><?= e($inv['phone']) ?></a></div><?php endif; ?>
            <?php if ($inv['email']): ?><div><i class="bi bi-envelope text-muted me-1"></i><?= e($inv['email']) ?></div><?php endif; ?>
            <div><i class="bi bi-file-text text-muted me-1"></i>GSTIN: <strong><?= e($inv['gstin'] ?: 'Unregistered / Consumer') ?></strong></div>
            <div><i class="bi bi-geo-alt text-muted me-1"></i>Place of Supply: <strong>Karnataka (State Code 29)</strong></div>
        </div>
        <?php if ($inv['period_from']): ?>
            <div class="mt-2 pt-2 border-top small">
                <span class="text-muted">Service Period:</span> <strong><?= e(fmt_date($inv['period_from'])) ?> – <?= e(fmt_date($inv['period_to'])) ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- Services & Items Mobile Cards -->
    <div class="guard-mobile-card mb-3 p-3">
        <div class="fw-bold text-dark mb-2 d-flex align-items-center justify-content-between border-bottom pb-2">
            <span><i class="bi bi-shield-check text-primary me-1"></i> Security Services (SAC 998525)</span>
            <span class="badge bg-light text-secondary border">Items (<?= count($items) ?>)</span>
        </div>
        <?php foreach ($items as $n => $it): ?>
            <div class="p-2 border rounded-3 mb-2 bg-light">
                <div class="fw-bold text-dark mb-1" style="font-size:0.88rem;"><?= e($it['description']) ?></div>
                <div class="d-flex justify-content-between align-items-center small text-muted">
                    <span>Qty: <strong><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?> <?= e($it['unit']) ?></strong> × <?= money($it['rate']) ?></span>
                    <span class="fw-bold text-dark fs-6"><?= money($it['amount']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Tax Breakdown -->
        <div class="pt-2 border-top small">
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">Taxable Value:</span>
                <span class="fw-semibold"><?= money($inv['subtotal'] - (float)$inv['discount']) ?></span>
            </div>
            <?php if ((float)$inv['discount'] > 0): ?>
                <div class="d-flex justify-content-between py-1 text-danger">
                    <span>Discount:</span>
                    <span>− <?= money($inv['discount']) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($cgstAmount > 0): ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">CGST (<?= rtrim(rtrim(number_format($cgstRate, 2), '0'), '.') ?>%):</span>
                    <span><?= money($cgstAmount) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">SGST (<?= rtrim(rtrim(number_format($sgstRate, 2), '0'), '.') ?>%):</span>
                    <span><?= money($sgstAmount) ?></span>
                </div>
            <?php elseif ($igstAmount > 0): ?>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">IGST (<?= rtrim(rtrim(number_format($igstRate, 2), '0'), '.') ?>%):</span>
                    <span><?= money($igstAmount) ?></span>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between py-2 border-top border-bottom fw-bold text-dark fs-6">
                <span>Total Invoice Value:</span>
                <span><?= money($inv['total']) ?></span>
            </div>
            <div class="mt-2 text-muted" style="font-size:0.74rem;">
                <strong>Amount in Words:</strong><br><?= e($amountInWords) ?>
            </div>
        </div>
    </div>

    <!-- RBI Bank Settlement & UPI QR Card -->
    <div class="guard-mobile-card mb-3 p-3">
        <div class="fw-bold text-dark mb-2 d-flex align-items-center gap-1 border-bottom pb-2">
            <i class="bi bi-bank text-primary"></i> RBI Settlement &amp; Bank Details
        </div>
        <div class="row g-2 align-items-center">
            <div class="col-8 small">
                <div>Bank: <strong><?= e($rbi['bank_name']) ?></strong></div>
                <div>A/C Name: <strong><?= e($rbi['account_name']) ?></strong></div>
                <div>A/C No: <strong><?= e($rbi['account_no']) ?></strong> (<?= e($rbi['account_type']) ?>)</div>
                <div>IFSC: <strong><?= e($rbi['ifsc_code']) ?></strong></div>
                <div>Branch: <?= e($rbi['branch']) ?></div>
                <div class="mt-1">UPI ID: <strong><?= e($rbi['upi_id']) ?></strong></div>
                <div class="mt-1"><span class="badge bg-light text-secondary border">Reverse Charge (RCM): No</span></div>
            </div>
            <div class="col-4 text-center">
                <img src="<?= e($upiQrUrl) ?>" alt="Scan to Pay" class="img-fluid rounded border p-1 bg-white shadow-sm" style="max-width:100px;">
                <div class="text-muted" style="font-size:0.65rem;">Scan to Pay</div>
            </div>
        </div>
    </div>

    <!-- Record Payment Mobile Form -->
    <?php if (in_array($inv['status'], ['draft', 'sent', 'partially_paid'], true) && $balance > 0): ?>
        <div class="guard-mobile-card p-3 mb-3">
            <div class="fw-bold text-dark mb-2"><i class="bi bi-cash-coin text-success me-1"></i> Record Payment Received</div>
            <div class="small text-muted mb-2">Pending Balance: <strong><?= money($balance) ?></strong></div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="payment">
                <div class="input-group mb-2">
                    <span class="input-group-text">₹</span>
                    <input type="number" step="0.01" min="0.01" max="<?= e(number_format($balance, 2, '.', '')) ?>" name="amount" class="form-control" value="<?= e(number_format($balance, 2, '.', '')) ?>">
                </div>
                <button class="btn btn-success w-100 fw-bold rounded-pill"><i class="bi bi-check2-circle me-1"></i> Record Payment</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- =========================================================================
     2. OFFICIAL DESKTOP & PRINT TAX INVOICE (d-none d-md-block & @media print)
     ========================================================================= -->
<div class="row g-3">
    <div class="col-xl-9 col-12 d-none d-md-block print-full-width">
        <div class="card shadow-sm border rounded-4 bg-white">
            <div class="card-body p-4 p-md-5">
                <!-- TAX INVOICE Header Badge -->
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <span class="brand-mark bg-primary text-white p-2 rounded-3 d-inline-flex align-items-center justify-content-center" style="width:48px;height:48px;font-size:1.5rem;">
                            <i class="bi bi-shield-check"></i>
                        </span>
                        <div>
                            <h3 class="fs-4 fw-bold m-0 text-dark"><?= e($rbi['company_name']) ?></h3>
                            <div class="small text-muted"><?= e($rbi['tagline']) ?></div>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold text-uppercase text-primary" style="letter-spacing:.08em">TAX INVOICE</div>
                        <div class="small text-muted">Original for Recipient · RBI &amp; GST Compliant</div>
                    </div>
                </div>

                <!-- Provider & Invoice Metadata Grid -->
                <div class="row mb-4 g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Service Provider / Agency Details</div>
                        <div class="fw-bold text-dark"><?= e($rbi['company_name']) ?></div>
                        <div class="small text-muted" style="white-space:pre-line"><?= e($rbi['address']) ?></div>
                        <div class="small text-muted mt-1">
                            Phone: <?= e($rbi['phone']) ?> · Email: <?= e($rbi['email']) ?>
                        </div>
                        <div class="small fw-semibold mt-1">
                            GSTIN: <span class="font-monospace text-primary"><?= e($rbi['gstin']) ?></span> · PAN: <span class="font-monospace"><?= e($rbi['pan']) ?></span><br>
                            State: <?= e($rbi['state']) ?> (State Code: <?= e($rbi['state_code']) ?>)
                        </div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div class="p-3 bg-light rounded-3 border d-inline-block text-start" style="min-width:260px;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Invoice No:</span>
                                <strong class="font-monospace fs-6"><?= e($inv['invoice_no']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Invoice Date:</span>
                                <strong><?= e(fmt_date($inv['issued_on'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">Due Date:</span>
                                <strong class="text-danger"><?= e(fmt_date($inv['due_on'])) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">SAC Code:</span>
                                <strong>998525</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Reverse Charge:</span>
                                <strong>No</strong>
                            </div>
                            <div class="mt-2 text-center no-print">
                                <?= status_badge($inv['status']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Billed To & Service Period Grid -->
                <div class="row mb-4 p-3 bg-light rounded-3 border mx-0">
                    <div class="col-sm-7 ps-0">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Billed To (Client / Consignee)</div>
                        <div class="fw-bold fs-6 text-dark"><?= e($inv['client_name']) ?></div>
                        <?php if ($inv['contact_person']): ?><div class="small">Attn: <?= e($inv['contact_person']) ?></div><?php endif; ?>
                        <div class="small text-muted" style="white-space:pre-line"><?= e(trim(($inv['address'] ?? '') . "\n" . implode(', ', array_filter([$inv['city'], $inv['state'], $inv['pincode']])))) ?></div>
                        <div class="small text-muted mt-1"><?= e($inv['phone']) ?><?= $inv['email'] ? ' · ' . e($inv['email']) : '' ?></div>
                        <div class="small fw-semibold mt-1">
                            GSTIN: <?= e($inv['gstin'] ?: 'Unregistered') ?> · State: <?= e($inv['state'] ?: 'Karnataka') ?> (Code 29)<br>
                            Place of Supply: <?= e($inv['state'] ?: 'Karnataka (29)') ?>
                        </div>
                    </div>
                    <?php if ($inv['period_from']): ?>
                    <div class="col-sm-5 text-sm-end pe-0 mt-3 mt-sm-0">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Service / Deployment Period</div>
                        <div class="fw-bold"><?= e(fmt_date($inv['period_from'])) ?> – <?= e(fmt_date($inv['period_to'])) ?></div>
                        <div class="small text-muted mt-1">Services: Guarding &amp; Armed Escort Services</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Itemized Services Table -->
                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:45px">#</th>
                                <th>Description of Security Services</th>
                                <th class="text-center" style="width:100px">SAC Code</th>
                                <th class="text-end" style="width:110px">Quantity</th>
                                <th class="text-end" style="width:120px">Rate (₹)</th>
                                <th class="text-end" style="width:140px">Taxable Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $n => $it): ?>
                            <tr>
                                <td class="text-center text-muted"><?= $n + 1 ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($it['description']) ?></div>
                                    <div class="small text-muted">Guarding, VIP bodyguard, patrol &amp; security services</div>
                                </td>
                                <td class="text-center font-monospace small">998525</td>
                                <td class="text-end text-nowrap"><?= rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.') ?> <?= e($it['unit']) ?></td>
                                <td class="text-end text-nowrap"><?= money($it['rate']) ?></td>
                                <td class="text-end text-nowrap fw-semibold"><?= money($it['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Tax & Financial Summary -->
                <div class="row justify-content-between mb-4">
                    <div class="col-sm-6">
                        <div class="p-3 border rounded-3 bg-light h-100">
                            <div class="text-muted small fw-bold text-uppercase mb-1">Invoice Value in Words</div>
                            <div class="fw-bold text-dark mb-2" style="font-size:0.92rem;"><?= e($amountInWords) ?></div>
                            <div class="small text-muted">
                                <strong>Declaration:</strong> We declare that this invoice shows the actual price of the security services described and that all particulars are true and correct.
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-5 mt-3 mt-sm-0">
                        <div class="border rounded-3 p-3 bg-light">
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted">Taxable Subtotal</span>
                                <span class="fw-semibold"><?= money($inv['subtotal']) ?></span>
                            </div>
                            <?php if ((float)$inv['discount'] > 0): ?>
                                <div class="d-flex justify-content-between py-1 text-danger">
                                    <span>Discount</span>
                                    <span>− <?= money($inv['discount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($cgstAmount > 0): ?>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">CGST @ <?= rtrim(rtrim(number_format($cgstRate, 2), '0'), '.') ?>%</span>
                                    <span><?= money($cgstAmount) ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">SGST @ <?= rtrim(rtrim(number_format($sgstRate, 2), '0'), '.') ?>%</span>
                                    <span><?= money($sgstAmount) ?></span>
                                </div>
                            <?php elseif ($igstAmount > 0): ?>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">IGST @ <?= rtrim(rtrim(number_format($igstRate, 2), '0'), '.') ?>%</span>
                                    <span><?= money($igstAmount) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between py-2 border-top border-bottom fw-bold fs-5 text-dark mt-1">
                                <span>Total Amount (₹)</span>
                                <span><?= money($inv['total']) ?></span>
                            </div>
                            <?php if ((float)$inv['amount_paid'] > 0): ?>
                                <div class="d-flex justify-content-between py-1 text-success mt-1">
                                    <span>Amount Received</span>
                                    <span><?= money($inv['amount_paid']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between py-1 fw-bold text-danger">
                                    <span>Balance Due</span>
                                    <span><?= money($balance) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RBI Banking Settlement Box & Signatory -->
                <div class="row pt-3 border-top g-3 align-items-end">
                    <div class="col-sm-8">
                        <div class="p-3 border rounded-3 bg-light">
                            <div class="row g-2 align-items-center">
                                <div class="col-sm-8 small">
                                    <div class="fw-bold text-primary text-uppercase mb-1">
                                        <i class="bi bi-bank me-1"></i> RBI Compliant Bank Settlement Details
                                    </div>
                                    <div>Bank Name: <strong><?= e($rbi['bank_name']) ?></strong></div>
                                    <div>Beneficiary: <strong><?= e($rbi['account_name']) ?></strong></div>
                                    <div>Account Number: <strong class="font-monospace fs-6"><?= e($rbi['account_no']) ?></strong> (<?= e($rbi['account_type']) ?>)</div>
                                    <div>IFSC Code: <strong class="font-monospace"><?= e($rbi['ifsc_code']) ?></strong> · Branch: <?= e($rbi['branch']) ?></div>
                                    <div>UPI ID: <strong class="text-primary font-monospace"><?= e($rbi['upi_id']) ?></strong></div>
                                </div>
                                <div class="col-sm-4 text-center">
                                    <img src="<?= e($upiQrUrl) ?>" alt="UPI QR Code" class="img-fluid border rounded p-1 bg-white shadow-sm" style="max-width:115px;">
                                    <div class="text-muted small mt-1" style="font-size:0.7rem;">Scan &amp; Pay via UPI</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-4 text-center text-sm-end">
                        <div class="small text-muted mb-4">For <strong><?= e($rbi['company_name']) ?></strong></div>
                        <div class="border-bottom d-inline-block" style="width:160px;height:45px;"></div>
                        <div class="fw-semibold small mt-1">Authorized Signatory</div>
                        <div class="text-muted" style="font-size:0.7rem;">Security Operations &amp; Accounts</div>
                    </div>
                </div>

                <?php if ($inv['notes']): ?>
                    <div class="mt-4 pt-3 border-top small text-muted">
                        <strong>Notes &amp; Terms:</strong> <?= e($inv['notes']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Desktop Side Actions: Record Payment -->
    <div class="col-xl-3 col-12 no-print d-none d-md-block">
        <?php if (in_array($inv['status'], ['draft', 'sent', 'partially_paid'], true) && $balance > 0): ?>
        <div class="card shadow-sm border rounded-4 mb-3">
            <div class="card-header bg-white fw-bold">Record Payment</div>
            <div class="card-body">
                <div class="small text-muted mb-2">Balance due: <strong class="text-danger"><?= money($balance) ?></strong></div>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="payment">
                    <div class="input-group mb-2">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" min="0.01" max="<?= e(number_format($balance, 2, '.', '')) ?>" name="amount" class="form-control" value="<?= e(number_format($balance, 2, '.', '')) ?>">
                    </div>
                    <button class="btn btn-success w-100 fw-bold rounded-pill"><i class="bi bi-cash me-1"></i> Record Payment</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Summary Box -->
        <div class="card shadow-sm border rounded-4 p-3 bg-light">
            <div class="fw-bold mb-2 small text-uppercase text-muted">Invoice Summary</div>
            <div class="d-flex justify-content-between small py-1 border-bottom">
                <span>Status:</span>
                <?= status_badge($inv['status']) ?>
            </div>
            <div class="d-flex justify-content-between small py-1 border-bottom">
                <span>Total Amount:</span>
                <strong class="text-dark"><?= money($inv['total']) ?></strong>
            </div>
            <div class="d-flex justify-content-between small py-1 border-bottom">
                <span>Amount Paid:</span>
                <span class="text-success fw-bold"><?= money($inv['amount_paid']) ?></span>
            </div>
            <div class="d-flex justify-content-between small py-1">
                <span>Balance:</span>
                <span class="text-danger fw-bold"><?= money($balance) ?></span>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
