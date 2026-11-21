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
$counts = q_one("SELECT COUNT(*) total, SUM(status='new') new_cnt, SUM(status='contacted') contacted_cnt FROM inquiries");

$pageTitle = 'Web Inquiries & Quotes';
$activeNav = 'inquiries';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="page-actions">
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <a href="<?= url('admin/inquiries.php') ?>" class="btn btn-sm <?= !$status ? 'btn-primary' : 'btn-outline-secondary' ?>">All (<?= (int)$counts['total'] ?>)</a>
        <a href="<?= url('admin/inquiries.php?status=new') ?>" class="btn btn-sm <?= $status === 'new' ? 'btn-danger' : 'btn-outline-danger' ?>">
            <i class="bi bi-bell-fill me-1"></i> New (<?= (int)$counts['new_cnt'] ?>)
        </a>
        <a href="<?= url('admin/inquiries.php?status=contacted') ?>" class="btn btn-sm <?= $status === 'contacted' ? 'btn-warning' : 'btn-outline-warning' ?>">Contacted (<?= (int)$counts['contacted_cnt'] ?>)</a>
        <a href="<?= url('admin/inquiries.php?status=closed') ?>" class="btn btn-sm <?= $status === 'closed' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Closed</a>
    </div>
    <a href="<?= url('index.php#quote') ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i> View Website Form</a>
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
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
