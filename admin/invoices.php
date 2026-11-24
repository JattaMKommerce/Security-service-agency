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

$activeFilterCount = 0;
if ($search !== '') $activeFilterCount++;
if ($clientId > 0) $activeFilterCount++;
if ($status !== '') $activeFilterCount++;
$balanceTotal = max(0, (float)$sum['billed'] - (float)$sum['paid']);
?>

<!-- 1. Hero Banner (Clean SaaS Look) -->
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Invoices &amp; Billing</h2>
        <p class="guard-hero-sub">RBI &amp; GST compliant invoicing, payment settlements, and tracking</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(37,99,235,0.18);">
        <i class="bi bi-receipt"></i>
    </div>
</div>

<!-- 2. Summary Statistics Section (4 Clean Cards) -->
<div class="stat-grid-4">
    <!-- Total Billed -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-purple bg-purple-subtle">
            <i class="bi bi-receipt"></i>
        </div>
        <div class="stat-box-num fs-6"><?= money($sum['billed']) ?></div>
        <div class="stat-box-lbl">Billed</div>
    </div>
    <!-- Received -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-success bg-success-subtle">
            <i class="bi bi-check2-circle"></i>
        </div>
        <div class="stat-box-num fs-6"><?= money($sum['paid']) ?></div>
        <div class="stat-box-lbl">Received</div>
    </div>
    <!-- Overdue -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-danger bg-danger-subtle">
            <i class="bi bi-exclamation-octagon"></i>
        </div>
        <div class="stat-box-num fs-6"><?= money($sum['overdue']) ?></div>
        <div class="stat-box-lbl">Overdue</div>
    </div>
    <!-- Balance Due -->
    <div class="stat-box-modern">
        <div class="stat-box-icon text-warning bg-warning-subtle">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <div class="stat-box-num fs-6"><?= money($balanceTotal) ?></div>
        <div class="stat-box-lbl">Balance</div>
    </div>
</div>

<!-- 3. Clean Search & Filters Card -->
<div class="filter-card-clean">
    <form method="get" action="<?= url('admin/invoices.php') ?>">
        <div class="search-input-wrap position-relative mb-2">
            <i class="bi bi-search search-icon-left"></i>
            <input type="search" name="q" value="<?= e($search) ?>" class="form-control form-control-clean ps-5" placeholder="Search invoice no. or client...">
        </div>
        <div class="row g-2 mb-2">
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
            <div class="col-6">
                <div class="filter-select-wrap">
                    <i class="bi bi-tag filter-select-icon"></i>
                    <select name="status" class="form-select form-select-clean">
                        <option value="">Any status</option>
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= label($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1 py-2 fw-bold rounded-3 shadow-sm" style="background:#2563eb;border-color:#2563eb;">
                <i class="bi bi-funnel me-1"></i> Apply Filters <?= $activeFilterCount ? '(' . $activeFilterCount . ')' : '' ?>
            </button>
            <a href="<?= url('admin/invoices.php') ?>" class="btn btn-outline-secondary py-2 px-3 fw-semibold rounded-3 bg-white text-dark">
                <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- 4. Section Header & Actions -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h2 class="fs-5 fw-bold m-0 text-dark">Invoices (<?= (int)$p['total'] ?>)</h2>
    <a href="<?= url('admin/invoice_form.php') ?>" class="btn btn-primary btn-sm px-3 py-1-5 fw-bold rounded-pill shadow-sm" style="background:#2563eb;border-color:#2563eb;">
        <i class="bi bi-plus-lg me-1"></i> New Invoice
    </a>
</div>

<!-- 5. MOBILE INVOICE CARDS (Visible only on mobile devices) -->
<div class="d-md-none">
    <?php if (!$p['rows']): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center">
            <i class="bi bi-receipt fs-2 text-muted mb-2 d-block"></i>
            <div class="fw-semibold text-dark">No invoices found</div>
            <div class="small text-muted mb-3">Try adjusting your search or filters.</div>
            <a href="<?= url('admin/invoice_form.php') ?>" class="btn btn-sm btn-primary rounded-pill px-3">Create Invoice</a>
        </div>
    <?php endif; ?>

    <?php foreach ($p['rows'] as $i):
        $overdue = in_array($i['status'], ['sent', 'partially_paid'], true) && $i['due_on'] && $i['due_on'] < date('Y-m-d');
        $bal = (float)$i['total'] - (float)$i['amount_paid'];
    ?>
        <div class="guard-mobile-card mb-2">
            <a href="<?= url('admin/invoice_view.php?id=' . $i['id']) ?>" class="text-decoration-none text-reset d-block">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fw-bold text-primary fs-6"><?= e($i['invoice_no']) ?></span>
                    <?= status_badge($i['status']) ?>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="min-w-0 flex-grow-1 pe-2">
                        <div class="fw-bold text-dark text-truncate" style="font-size:0.92rem;"><?= e($i['client_name']) ?></div>
                        <div class="small text-muted">
                            Issued: <?= e(fmt_date($i['issued_on'])) ?>
                            <?php if ($i['due_on']): ?>
                                · Due: <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>"><?= e(fmt_date($i['due_on'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0">
                        <div class="fw-bold text-dark fs-6"><?= money($i['total']) ?></div>
                        <?php if ($i['status'] !== 'paid' && $i['status'] !== 'cancelled' && $bal > 0): ?>
                            <div class="small text-danger fw-semibold">Bal: <?= money($bal) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <span class="badge bg-light text-secondary border">SAC 998525 · GST <?= rtrim(rtrim(number_format((float)$i['tax_percent'], 2), '0'), '.') ?>%</span>
                    <span class="small text-primary fw-semibold d-inline-flex align-items-center gap-1">
                        View Tax Invoice <i class="bi bi-chevron-right"></i>
                    </span>
                </div>
            </a>
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
                    <th>Invoice</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Due</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-receipt"></i>No invoices yet</div></td></tr>
            <?php endif; ?>
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
