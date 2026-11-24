<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

// Handle status updates or delete
if (is_post()) {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $inqId = post_int('inquiry_id');
    if ($inqId) {
        if ($action === 'set_status') {
            $newStatus = in_array($_POST['status'] ?? '', ['new', 'contacted', 'closed'], true) ? $_POST['status'] : 'new';
            q('UPDATE inquiries SET status = ? WHERE id = ?', [$newStatus, $inqId]);
            flash('success', 'Inquiry status updated to ' . label($newStatus) . '.');
        } elseif ($action === 'delete') {
            q('DELETE FROM inquiries WHERE id = ?', [$inqId]);
            flash('success', 'Inquiry deleted.');
        }
    }
    redirect('admin/inquiries.php');
}

$status = get_str('status', 20);
$where = ['1=1'];
$params = [];
if (in_array($status, ['new', 'contacted', 'closed'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$p = paginate('FROM inquiries WHERE ' . implode(' AND ', $where), $params, '*', 'id DESC', 20);
$counts = q_one("SELECT COUNT(*) total,
                        COALESCE(SUM(status='new'),0) new_cnt,
                        COALESCE(SUM(status='contacted'),0) contacted_cnt,
                        COALESCE(SUM(status='closed'),0) closed_cnt
                 FROM inquiries");

$pageTitle = 'Web Inquiries & Quotes';
$activeNav = 'inquiries';
require APP_ROOT . '/includes/layout/header.php';
?>

<!-- 1. Hero Banner (Clean SaaS Look) -->
<div class="guard-hero-banner mb-3">
    <div class="guard-hero-content">
        <h2 class="guard-hero-title">Web Inquiries &amp; Quotes</h2>
        <p class="guard-hero-sub">Direct client quote requests from public website, leads, and conversion tracking</p>
    </div>
    <div class="guard-hero-graphic d-none d-sm-block text-end pe-3" style="font-size:3.5rem;color:rgba(37,99,235,0.18);">
        <i class="bi bi-chat-left-dots"></i>
    </div>
</div>

<!-- 2. Summary Statistics Section (4 Clean Cards) -->
<div class="stat-grid-4">
    <!-- Total -->
    <a href="<?= url('admin/inquiries.php') ?>" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-purple bg-purple-subtle">
            <i class="bi bi-chat-left-dots"></i>
        </div>
        <div class="stat-box-num"><?= (int)$counts['total'] ?></div>
        <div class="stat-box-lbl">Total</div>
    </a>
    <!-- New Leads -->
    <a href="<?= url('admin/inquiries.php?status=new') ?>" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-danger bg-danger-subtle">
            <i class="bi bi-bell-fill"></i>
        </div>
        <div class="stat-box-num text-danger"><?= (int)$counts['new_cnt'] ?></div>
        <div class="stat-box-lbl">New Leads</div>
    </a>
    <!-- Contacted -->
    <a href="<?= url('admin/inquiries.php?status=contacted') ?>" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-warning bg-warning-subtle">
            <i class="bi bi-check2-circle"></i>
        </div>
        <div class="stat-box-num"><?= (int)$counts['contacted_cnt'] ?></div>
        <div class="stat-box-lbl">Contacted</div>
    </a>
    <!-- Closed -->
    <a href="<?= url('admin/inquiries.php?status=closed') ?>" class="stat-box-modern text-decoration-none">
        <div class="stat-box-icon text-success bg-success-subtle">
            <i class="bi bi-archive"></i>
        </div>
        <div class="stat-box-num"><?= (int)$counts['closed_cnt'] ?></div>
        <div class="stat-box-lbl">Closed</div>
    </a>
</div>

<!-- 3. Clean Status Filter Pills -->
<div class="filter-card-clean mb-3 p-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex gap-1 flex-wrap">
            <a href="<?= url('admin/inquiries.php') ?>" class="btn btn-sm <?= !$status ? 'btn-primary' : 'btn-light border' ?> rounded-pill px-3 py-1">All (<?= (int)$counts['total'] ?>)</a>
            <a href="<?= url('admin/inquiries.php?status=new') ?>" class="btn btn-sm <?= $status === 'new' ? 'btn-danger' : 'btn-outline-danger' ?> rounded-pill px-3 py-1">
                <i class="bi bi-bell-fill me-1"></i> New (<?= (int)$counts['new_cnt'] ?>)
            </a>
            <a href="<?= url('admin/inquiries.php?status=contacted') ?>" class="btn btn-sm <?= $status === 'contacted' ? 'btn-warning text-dark' : 'btn-outline-warning' ?> rounded-pill px-3 py-1">Contacted (<?= (int)$counts['contacted_cnt'] ?>)</a>
            <a href="<?= url('admin/inquiries.php?status=closed') ?>" class="btn btn-sm <?= $status === 'closed' ? 'btn-secondary text-white' : 'btn-outline-secondary' ?> rounded-pill px-3 py-1">Closed</a>
        </div>
        <a href="<?= url('index.php#quote') ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 shadow-sm"><i class="bi bi-box-arrow-up-right me-1"></i> Public Quote Form</a>
    </div>
</div>

<!-- 4. Section Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h2 class="fs-5 fw-bold m-0 text-dark">Inquiry Records (<?= (int)$p['total'] ?>)</h2>
</div>

<!-- 5. MOBILE INQUIRY CARDS (Visible only on mobile devices) -->
<div class="d-md-none">
    <?php if (!$p['rows']): ?>
        <div class="empty-state bg-white rounded-4 border p-4 mb-3 text-center">
            <i class="bi bi-chat-left-dots fs-2 text-muted mb-2 d-block"></i>
            <div class="fw-semibold text-dark">No inquiries found</div>
            <div class="small text-muted">Quote requests from the website will appear here.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($p['rows'] as $r): ?>
        <div class="guard-mobile-card mb-2" style="<?= $r['status'] === 'new' ? 'border-color:#fca5a5;background:#fffaf0;' : '' ?>">
            <div class="d-flex align-items-start gap-3">
                <!-- Avatar with initial -->
                <div class="guard-avatar-initials-sm flex-shrink-0" style="background:linear-gradient(135deg, #0284c7, #2563eb);">
                    <?= e(strtoupper(substr($r['name'], 0, 1))) ?>
                </div>

                <!-- Middle Content -->
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <strong class="text-dark text-truncate" style="font-size:0.92rem;"><?= e($r['name']) ?></strong>
                        <?php if ($r['status'] === 'new'): ?>
                            <span class="badge bg-danger"><i class="bi bi-bell-fill me-1"></i>New</span>
                        <?php elseif ($r['status'] === 'contacted'): ?>
                            <span class="badge bg-warning text-dark">Contacted</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Closed</span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted text-truncate mt-1">
                        <i class="bi bi-geo-alt me-1"></i><?= e($r['city'] ?: 'City not specified') ?> ·
                        <?= category_badge($r['service_type'] ?? 'bodyguard') ?>
                    </div>
                    <div class="small fw-semibold text-dark mt-1">
                        <?= (int)$r['guards_needed'] ?> Guard(s) · <?= weapon_badge((bool)$r['is_armed']) ?>
                    </div>
                    <?php if ($r['message']): ?>
                        <div class="small text-muted mt-1 p-2 bg-white rounded-2 border" style="font-size:0.75rem;">
                            <?= e($r['message']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <a href="tel:<?= e($r['phone']) ?>" class="btn btn-sm btn-outline-success rounded-pill px-2 py-1" style="font-size:0.75rem;">
                                <i class="bi bi-telephone me-1"></i> Call
                            </a>
                            <?php if ($r['email']): ?>
                                <a href="mailto:<?= e($r['email']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-1" style="font-size:0.75rem;">
                                    <i class="bi bi-envelope me-1"></i> Email
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="inquiry_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="set_status">
                                <?php if ($r['status'] === 'new'): ?>
                                    <button type="submit" name="status" value="contacted" class="btn btn-sm btn-warning text-dark rounded-pill px-2 py-1" style="font-size:0.72rem;">Mark Contacted</button>
                                <?php elseif ($r['status'] === 'contacted'): ?>
                                    <button type="submit" name="status" value="closed" class="btn btn-sm btn-secondary text-white rounded-pill px-2 py-1" style="font-size:0.72rem;">Close</button>
                                <?php else: ?>
                                    <button type="submit" name="status" value="new" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-1" style="font-size:0.72rem;">Reopen</button>
                                <?php endif; ?>
                            </form>
                            <a href="<?= url('admin/client_form.php?name=' . urlencode($r['name']) . '&phone=' . urlencode($r['phone']) . '&city=' . urlencode($r['city'])) ?>" class="btn btn-sm btn-primary rounded-pill px-2 py-1" style="font-size:0.72rem;" title="Convert to Client">
                                + Client
                            </a>
                        </div>
                    </div>
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
                    <th>Date</th>
                    <th>Name / Client</th>
                    <th>Contact</th>
                    <th>Service / Category</th>
                    <th>Requirement</th>
                    <th>City</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$p['rows']): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="bi bi-chat-left-dots"></i>
                            No inquiries found. Quote requests submitted from the static website will appear here.
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($p['rows'] as $r): ?>
                <tr>
                    <td class="small text-muted text-nowrap"><?= fmt_datetime($r['created_at']) ?></td>
                    <td>
                        <strong class="d-block"><?= e($r['name']) ?></strong>
                        <?php if ($r['message']): ?>
                            <div class="small text-muted mt-1 text-truncate" style="max-width:280px" title="<?= e($r['message']) ?>">
                                <i class="bi bi-chat-quote me-1"></i><?= e($r['message']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div><a href="tel:<?= e($r['phone']) ?>"><i class="bi bi-telephone me-1"></i><?= e($r['phone']) ?></a></div>
                        <?php if ($r['email']): ?>
                            <div class="small text-muted"><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></div>
                        <?php endif; ?>
                    </td>
                    <td><?= category_badge($r['service_type'] ?? 'bodyguard') ?></td>
                    <td>
                        <div class="fw-semibold"><?= (int)$r['guards_needed'] ?> Guard(s)</div>
                        <div class="small"><?= weapon_badge((bool)$r['is_armed']) ?></div>
                    </td>
                    <td><?= e($r['city'] ?: '—') ?></td>
                    <td>
                        <?php if ($r['status'] === 'new'): ?>
                            <span class="badge bg-danger"><i class="bi bi-bell-fill me-1"></i>New</span>
                        <?php elseif ($r['status'] === 'contacted'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-check me-1"></i>Contacted</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Closed</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <form method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="inquiry_id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="set_status">
                            <?php if ($r['status'] === 'new'): ?>
                                <button type="submit" name="status" value="contacted" class="btn btn-sm btn-outline-success" title="Mark as contacted"><i class="bi bi-check2"></i> Mark Contacted</button>
                            <?php elseif ($r['status'] === 'contacted'): ?>
                                <button type="submit" name="status" value="closed" class="btn btn-sm btn-outline-secondary" title="Mark as closed"><i class="bi bi-archive"></i> Close</button>
                            <?php else: ?>
                                <button type="submit" name="status" value="new" class="btn btn-sm btn-outline-danger" title="Reopen as new"><i class="bi bi-arrow-counterclockwise"></i> Reopen</button>
                            <?php endif; ?>
                        </form>
                        <a href="<?= url('admin/client_form.php?name=' . urlencode($r['name']) . '&phone=' . urlencode($r['phone']) . '&city=' . urlencode($r['city'])) ?>" class="btn btn-sm btn-light" title="Convert to Client"><i class="bi bi-person-plus"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer"><?= pagination_links($p) ?></div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
