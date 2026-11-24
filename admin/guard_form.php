<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$id = get_int('id');
$guard = $id ? q_one('SELECT * FROM guards WHERE id = ?', [$id]) : null;
if ($id && !$guard) {
    flash('danger', 'Bodyguard not found.');
    redirect('admin/guards.php');
}

// ---- Delete ----------------------------------------------------------------
if (is_post() && ($_POST['action'] ?? '') === 'delete' && $guard) {
    require_csrf();
    $used = (int)q_val('SELECT COUNT(*) FROM assignments WHERE guard_id = ?', [$id]);
    if ($used > 0) {
        flash('warning', 'This bodyguard has deployment history and cannot be deleted. Set the status to Inactive instead.');
        redirect('admin/guard_form.php?id=' . $id);
    }
    q('DELETE FROM guards WHERE id = ?', [$id]);
    if ($guard['photo']) @unlink(APP_ROOT . '/uploads/guards/' . basename($guard['photo']));
    log_activity('guard.delete', $guard['employee_code'] . ' ' . $guard['full_name']);
    flash('success', 'Bodyguard deleted.');
    redirect('admin/guards.php');
}

// ---- Save ------------------------------------------------------------------
$errors = [];
if (is_post() && ($_POST['action'] ?? 'save') === 'save') {
    require_csrf();
    $d = [
        'employee_code'           => strtoupper((string)post_str('employee_code', 20)),
        'category'                => in_array($_POST['category'] ?? '', array_keys(guard_categories()), true) ? $_POST['category'] : 'bodyguard',
        'full_name'               => post_str('full_name', 120),
        'phone'                   => preg_replace('/[^\d+]/', '', (string)post_str('phone', 20)),
        'email'                   => post_str('email', 150),
        'gender'                  => in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null,
        'dob'                     => post_str('dob', 10),
        'blood_group'             => post_str('blood_group', 5),
        'address'                 => post_str('address', 1000),
        'city'                    => post_str('city', 80),
        'id_proof_type'           => post_str('id_proof_type', 40),
        'id_proof_number'         => post_str('id_proof_number', 60),
        'experience_years'        => max(0, min(60, (int)post_int('experience_years', 0))),
        'is_armed'                => !empty($_POST['is_armed']) ? 1 : 0,
        'weapon_license_no'       => post_str('weapon_license_no', 60),
        'skills'                  => post_str('skills', 255),
        'emergency_contact_name'  => post_str('emergency_contact_name', 120),
        'emergency_contact_phone' => post_str('emergency_contact_phone', 20),
        'joined_on'               => post_str('joined_on', 10),
        'status'                  => in_array($_POST['status'] ?? '', ['active', 'on_leave', 'inactive'], true) ? $_POST['status'] : 'active',
    ];
    // Spaces at the start/end are almost always typing mistakes: drop them.
    $password = trim((string)($_POST['password'] ?? ''));

    if (!$d['full_name']) $errors[] = 'Full name is required.';
    if (!preg_match('/^\+?\d{10,15}$/', $d['phone'])) $errors[] = 'Enter a valid phone number (10–15 digits).';
    if ($d['employee_code'] === '') $d['employee_code'] = next_code('guards', 'employee_code', 'GRD-');
    if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $d['employee_code'])) $errors[] = 'Employee code may contain only letters, numbers and dashes.';
    if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email address is not valid.';
    foreach (['dob' => 'Date of birth', 'joined_on' => 'Joining date'] as $k => $lbl) {
        if ($d[$k] && !valid_date($d[$k])) $errors[] = "$lbl is not a valid date.";
    }
    if ($password !== '' && strlen($password) < 6) $errors[] = 'App password must be at least 6 characters.';
    if (q_val('SELECT id FROM guards WHERE employee_code = ? AND id <> ?', [$d['employee_code'], $id])) $errors[] = 'That employee code is already used.';
    if (q_val('SELECT id FROM guards WHERE phone = ? AND id <> ?', [$d['phone'], $id])) $errors[] = 'Another bodyguard already has this phone number.';
    if ($guard && $d['status'] !== 'active' && q_val("SELECT id FROM assignments WHERE guard_id = ? AND status = 'active'", [$id])) {
        $errors[] = 'This bodyguard is currently hired. End the deployment before changing status to ' . label($d['status']) . '.';
    }
    if (!$d['is_armed']) $d['weapon_license_no'] = null;

    $newPhoto = null;
    if (!$errors) {
        try {
            $newPhoto = store_image_upload('photo', APP_ROOT . '/uploads/guards');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $generated = null;
        if ($newPhoto) $d['photo'] = $newPhoto;
        if ($password !== '') {
            $d['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $d['remember_hash'] = null; // sign the guard out of their phone
        } elseif (!$guard) {
            $generated = '123456';
            $d['password_hash'] = password_hash($generated, PASSWORD_DEFAULT);
        }

        $cols = array_keys($d);
        if ($guard) {
            q('UPDATE guards SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($d), $id]);
            if ($newPhoto && $guard['photo']) @unlink(APP_ROOT . '/uploads/guards/' . basename($guard['photo']));
            if ($password !== '') clear_guard_login_lock($id);
            log_activity('guard.update', $d['employee_code'] . ' ' . $d['full_name'] . ($password !== '' ? ' (password changed)' : ''));
            flash('success', 'Bodyguard updated.' . ($password !== '' ? ' New app login: ' . $d['employee_code'] . ' or ' . $d['phone'] . ' with the password you just set. The guard is signed out of their old phone.' : ''));
        } else {
            q('INSERT INTO guards (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($d));
            $id = (int)db()->lastInsertId();
            log_activity('guard.create', $d['employee_code'] . ' ' . $d['full_name']);
            flash('success', 'Bodyguard added. Guard app login: ' . $d['employee_code'] . ' (or ' . $d['phone'] . ')'
                . ($generated ? ' · Password: ' . $generated . ' — note it down, it will not be shown again.' : '.'));
        }
        redirect('admin/guard_view.php?id=' . $id);
    }
    $guard = array_merge($guard ?? [], $d);
}

$g = $guard ?? ['status' => 'active', 'category' => 'bodyguard', 'employee_code' => next_code('guards', 'employee_code', 'GRD-'), 'experience_years' => 0, 'is_armed' => 0, 'joined_on' => date('Y-m-d')];
$v = fn($k) => e($g[$k] ?? '');

$pageTitle = $id ? 'Edit Bodyguard' : 'Add Bodyguard';
$activeNav = 'guards';
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="mb-3"><a href="<?= url($id ? 'admin/guard_view.php?id=' . $id : 'admin/guards.php') ?>" class="small"><i class="bi bi-arrow-left"></i> Back</a></div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="form-section-title">Personal details</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label required">Full name</label><input name="full_name" class="form-control" value="<?= $v('full_name') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Employee code</label><input name="employee_code" class="form-control text-uppercase" value="<?= $v('employee_code') ?>"></div>
                    <div class="col-md-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select"><?php foreach (['active', 'on_leave', 'inactive'] as $s): ?><option value="<?= $s ?>" <?= ($g['status'] ?? '') === $s ? 'selected' : '' ?>><?= label($s) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label required">Phone (login)</label><input name="phone" class="form-control" value="<?= $v('phone') ?>" inputmode="tel" required></div>
                    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Gender</label>
                        <select name="gender" class="form-select"><option value="">—</option><?php foreach (['male', 'female', 'other'] as $s): ?><option value="<?= $s ?>" <?= ($g['gender'] ?? '') === $s ? 'selected' : '' ?>><?= label($s) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">Date of birth</label><input type="date" name="dob" class="form-control" value="<?= $v('dob') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Blood group</label>
                        <select name="blood_group" class="form-select"><option value="">—</option><?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $s): ?><option <?= ($g['blood_group'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= $v('city') ?>"></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= $v('address') ?></textarea></div>
                </div>

                <div class="form-section-title">Identity & professional</div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label required">Security Category / Role</label>
                        <select name="category" class="form-select" required>
                            <?php foreach (guard_categories() as $ck => $cv): ?>
                                <option value="<?= $ck ?>" <?= ($g['category'] ?? 'bodyguard') === $ck ? 'selected' : '' ?>><?= e($cv) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">ID proof type</label>
                        <select name="id_proof_type" class="form-select"><option value="">—</option><?php foreach (['Aadhaar', 'PAN', 'Voter ID', 'Driving Licence', 'Passport', 'Other'] as $s): ?><option <?= ($g['id_proof_type'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label class="form-label">ID proof number</label><input name="id_proof_number" class="form-control" value="<?= $v('id_proof_number') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Joined on</label><input type="date" name="joined_on" class="form-control" value="<?= $v('joined_on') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Experience (years)</label><input type="number" min="0" max="60" name="experience_years" class="form-control" value="<?= $v('experience_years') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Guard Duties & Specialization</label><input name="skills" class="form-control" value="<?= $v('skills') ?>" placeholder="e.g. VIP Bodyguard, Gate Security, Night Patrol, Driving"><div class="form-text text-muted" style="font-size: 0.75rem;">Assigned duties (e.g. VIP escort, gate security, CCTV patrol).</div></div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_armed" value="1" id="isArmed" <?= !empty($g['is_armed']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="isArmed">Armed with Gun (Firearm)</label></div>
                    </div>
                    <div class="col-md-8" id="weaponWrap"><label class="form-label">Weapon licence no.</label><input name="weapon_license_no" class="form-control" value="<?= $v('weapon_license_no') ?>" placeholder="e.g. LIC-DL-2024-998"></div>
                </div>

                <div class="form-section-title">Emergency contact</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Name</label><input name="emergency_contact_name" class="form-control" value="<?= $v('emergency_contact_name') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input name="emergency_contact_phone" class="form-control" value="<?= $v('emergency_contact_phone') ?>"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <div class="mb-3" id="photoPreview"><?= avatar($g['full_name'] ?? 'New', $g['photo'] ?? null, 'xl') ?></div>
                <label class="btn btn-outline-primary btn-sm"><i class="bi bi-camera"></i> Upload photo
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden id="photoInput"></label>
                <div class="small text-muted mt-2">JPG, PNG or WEBP, up to 3 MB</div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <div class="form-section-title">Guard app login</div>
                <p class="small text-muted">The guard signs in to the mobile app with their <strong>employee code or phone</strong> and this password.</p>
                <label class="form-label"><?= $id ? 'Set new password' : 'Password' ?></label>
                <input type="password" name="password" class="form-control" minlength="6" autocomplete="new-password"
                       placeholder="<?= $id ? 'Leave blank to keep current' : 'Leave blank to auto-generate' ?>">
            </div>
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-check2"></i> <?= $id ? 'Save changes' : 'Add bodyguard' ?></button>
        <?php if ($id): ?>
            <button form="deleteGuardForm" class="btn btn-outline-danger w-100 btn-sm mt-3"><i class="bi bi-trash"></i> Delete bodyguard</button>
        <?php endif; ?>
    </div>
</form>
<?php if ($id): ?>
    <form method="post" id="deleteGuardForm" data-confirm="Delete this bodyguard permanently? This cannot be undone.">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    </form>
<?php endif; ?>

<?php
$inlineScript = '<script>
(function () {
    const armed = document.getElementById("isArmed"), wrap = document.getElementById("weaponWrap");
    const sync = () => wrap.style.display = armed.checked ? "" : "none";
    armed.addEventListener("change", sync); sync();
    document.getElementById("photoInput").addEventListener("change", function () {
        if (!this.files[0]) return;
        const url = URL.createObjectURL(this.files[0]);
        document.getElementById("photoPreview").innerHTML = "<img src=\"" + url + "\" class=\"avatar avatar-xl\">";
    });
})();
</script>';
require APP_ROOT . '/includes/layout/footer.php';
