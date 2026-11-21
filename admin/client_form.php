<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$id = get_int('id');
$client = $id ? q_one('SELECT * FROM clients WHERE id = ?', [$id]) : null;
if ($id && !$client) {
    flash('danger', 'Client not found.');
    redirect('admin/clients.php');
}
$types = ['individual', 'corporate', 'event', 'residential', 'other'];

if (is_post() && ($_POST['action'] ?? '') === 'delete' && $client) {
    require_csrf();
    $used = (int)q_val('SELECT COUNT(*) FROM assignments WHERE client_id = ?', [$id])
          + (int)q_val('SELECT COUNT(*) FROM invoices WHERE client_id = ?', [$id]);
    if ($used > 0) {
        flash('warning', 'This client has deployments or invoices and cannot be deleted. Set the status to Inactive instead.');
        redirect('admin/client_form.php?id=' . $id);
    }
    q('DELETE FROM clients WHERE id = ?', [$id]);
    log_activity('client.delete', $client['client_code'] . ' ' . $client['name']);
    flash('success', 'Client deleted.');
    redirect('admin/clients.php');
}

$errors = [];
if (is_post() && ($_POST['action'] ?? 'save') === 'save') {
    require_csrf();
    $d = [
        'client_code'    => strtoupper((string)post_str('client_code', 20)),
        'name'           => post_str('name', 150),
        'client_type'    => in_array($_POST['client_type'] ?? '', $types, true) ? $_POST['client_type'] : 'individual',
        'contact_person' => post_str('contact_person', 120),
        'phone'          => preg_replace('/[^\d+]/', '', (string)post_str('phone', 20)),
        'alt_phone'      => post_str('alt_phone', 20),
        'email'          => post_str('email', 150),
        'address'        => post_str('address', 1000),
        'city'           => post_str('city', 80),
        'state'          => post_str('state', 80),
        'pincode'        => post_str('pincode', 12),
        'gstin'          => post_str('gstin', 20) !== null ? strtoupper(post_str('gstin', 20)) : null,
        'notes'          => post_str('notes', 2000),
        'status'         => ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active',
    ];
    if (!$d['name']) $errors[] = 'Client name is required.';
    if (!preg_match('/^\+?\d{10,15}$/', $d['phone'])) $errors[] = 'Enter a valid phone number (10–15 digits).';
    if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email address is not valid.';
    if ($d['client_code'] === '') $d['client_code'] = next_code('clients', 'client_code', 'CLT-');
    if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $d['client_code'])) $errors[] = 'Client code may contain only letters, numbers and dashes.';
    if (q_val('SELECT id FROM clients WHERE client_code = ? AND id <> ?', [$d['client_code'], $id])) $errors[] = 'That client code is already used.';
    if ($client && $d['status'] === 'inactive' && q_val("SELECT id FROM assignments WHERE client_id = ? AND status = 'active'", [$id])) {
        $errors[] = 'This client still has guards deployed. End those deployments before marking the client inactive.';
    }

    if (!$errors) {
        $cols = array_keys($d);
        if ($client) {
            q('UPDATE clients SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($d), $id]);
            log_activity('client.update', $d['client_code'] . ' ' . $d['name']);
            flash('success', 'Client updated.');
        } else {
            q('INSERT INTO clients (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($d));
            $id = (int)db()->lastInsertId();
            log_activity('client.create', $d['client_code'] . ' ' . $d['name']);
            flash('success', 'Client added. You can now hire guards for them.');
        }
        redirect('admin/client_view.php?id=' . $id);
    }
    $client = array_merge($client ?? [], $d);
}

$c = $client ?? ['status' => 'active', 'client_type' => 'individual', 'client_code' => next_code('clients', 'client_code', 'CLT-')];
$v = fn($k) => e($c[$k] ?? '');

$pageTitle = $id ? 'Edit Client' : 'Add Client';
$activeNav = 'clients';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="mb-3"><a href="<?= url($id ? 'admin/client_view.php?id=' . $id : 'admin/clients.php') ?>" class="small"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card" style="max-width:960px">
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="save">
            <div class="form-section-title">Client</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label required">Client / company name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
                <div class="col-md-3"><label class="form-label">Client code</label><input name="client_code" class="form-control text-uppercase" value="<?= $v('client_code') ?>"></div>
                <div class="col-md-3"><label class="form-label">Type</label>
                    <select name="client_type" class="form-select"><?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= ($c['client_type'] ?? '') === $t ? 'selected' : '' ?>><?= label($t) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Contact person</label><input name="contact_person" class="form-control" value="<?= $v('contact_person') ?>"></div>
                <div class="col-md-4"><label class="form-label required">Phone</label><input name="phone" class="form-control" value="<?= $v('phone') ?>" required></div>
                <div class="col-md-4"><label class="form-label">Alternate phone</label><input name="alt_phone" class="form-control" value="<?= $v('alt_phone') ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
                <div class="col-md-3"><label class="form-label">GSTIN</label><input name="gstin" class="form-control text-uppercase" value="<?= $v('gstin') ?>"></div>
                <div class="col-md-3"><label class="form-label">Status</label>
                    <select name="status" class="form-select"><option value="active">Active</option><option value="inactive" <?= ($c['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
            </div>
            <div class="form-section-title">Address (used on invoices)</div>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= $v('address') ?></textarea></div>
                <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= $v('city') ?>"></div>
                <div class="col-md-4"><label class="form-label">State</label><input name="state" class="form-control" value="<?= $v('state') ?>"></div>
                <div class="col-md-4"><label class="form-label">PIN code</label><input name="pincode" class="form-control" value="<?= $v('pincode') ?>"></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Special instructions, VIP preferences, etc."><?= $v('notes') ?></textarea></div>
            </div>
            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary"><i class="bi bi-check2"></i> <?= $id ? 'Save changes' : 'Add client' ?></button>
                <?php if ($id): ?><button form="deleteClientForm" class="btn btn-outline-danger ms-auto"><i class="bi bi-trash"></i> Delete</button><?php endif; ?>
            </div>
        </form>
        <?php if ($id): ?>
            <form method="post" id="deleteClientForm" data-confirm="Delete this client permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"></form>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
