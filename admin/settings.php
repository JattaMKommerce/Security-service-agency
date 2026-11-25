<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
$isSuper = $admin['role'] === 'superadmin';
$tab = get_str('tab', 20) ?: 'company';

$companyKeys = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_gstin',
                'currency_symbol', 'tax_label', 'tax_percent', 'invoice_prefix', 'invoice_due_days', 'invoice_terms'];
$trackingKeys = ['tracking_interval_sec', 'offline_after_min', 'location_retention_days', 'checkin_max_distance_m',
                 'require_selfie', 'selfie_retention_days', 'late_grace_min', 'default_shift_hours', 'ot_min_minutes', 'no_movement_min', 'battery_alert_pct',
                 'map_default_lat', 'map_default_lng', 'map_default_zoom'];

if (is_post()) {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['company', 'tracking'], true)) {
        if (!$isSuper) { flash('danger', 'Only a super admin can change settings.'); redirect('admin/settings.php?tab=' . $action); }
        $keys = $action === 'company' ? $companyKeys : $trackingKeys;
        $limits = [
            'tracking_interval_sec' => [10, 300], 'offline_after_min' => [1, 60], 'location_retention_days' => [7, 730],
            'checkin_max_distance_m' => [0, 50000], 'map_default_zoom' => [2, 18], 'tax_percent' => [0, 100], 'invoice_due_days' => [0, 365],
            'map_default_lat' => [-90, 90], 'map_default_lng' => [-180, 180],
            'require_selfie' => [0, 1], 'selfie_retention_days' => [7, 730], 'late_grace_min' => [0, 240],
            'default_shift_hours' => [1, 24], 'ot_min_minutes' => [0, 120], 'no_movement_min' => [0, 600], 'battery_alert_pct' => [0, 50],
        ];
        foreach ($keys as $k) {
            $val = trim((string)($_POST[$k] ?? ''));
            if (isset($limits[$k])) {
                $val = is_numeric($val) ? (string)max($limits[$k][0], min($limits[$k][1], (float)$val)) : setting($k);
            }
            if ($k === 'company_name' && $val === '') continue;
            save_setting($k, mb_substr($val, 0, 2000));
        }
        log_activity('settings.update', $action);
        flash('success', 'Settings saved.');
        redirect('admin/settings.php?tab=' . $action);
    }

    if ($action === 'password') {
        $row = q_one('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) {
            flash('danger', 'Current password is wrong.');
        } elseif (strlen($new) < 8) {
            flash('danger', 'New password must be at least 8 characters.');
        } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
            flash('danger', 'New passwords do not match.');
        } else {
            q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            log_activity('admin.password_change');
            flash('success', 'Password changed.');
        }
        redirect('admin/settings.php?tab=account');
    }

    if ($isSuper && $action === 'admin_add') {
        $name = post_str('name', 100);
        $email = mb_strtolower((string)post_str('email', 150));
        $pw = (string)($_POST['password'] ?? '');
        $role = ($_POST['role'] ?? '') === 'superadmin' ? 'superadmin' : 'admin';
        if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 8) {
            flash('danger', 'Name, valid email and a password of at least 8 characters are required.');
        } elseif (q_val('SELECT id FROM admins WHERE email = ?', [$email])) {
            flash('danger', 'An admin with that email already exists.');
        } else {
            q('INSERT INTO admins (name, email, password_hash, role) VALUES (?,?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
            log_activity('admin.create', $email . ' (' . $role . ')');
            flash('success', 'Admin user added.');
        }
        redirect('admin/settings.php?tab=admins');
    }

    if ($isSuper && $action === 'admin_toggle') {
        $tid = (int)post_int('id', 0);
        if ($tid === (int)$admin['id']) {
            flash('danger', 'You cannot disable your own account.');
        } else {
            q('UPDATE admins SET is_active = 1 - is_active WHERE id = ?', [$tid]);
            log_activity('admin.toggle', 'admin #' . $tid);
            flash('success', 'Admin user updated.');
        }
        redirect('admin/settings.php?tab=admins');
    }

    if ($isSuper && $action === 'push_config') {
        foreach (['fcm_project_id' => '/^[a-z0-9\-]{4,40}$/', 'fcm_api_key' => '/^[A-Za-z0-9_\-]{20,60}$/',
                  'fcm_app_id' => '/^\d+:\d+:android:[a-f0-9]+$/', 'fcm_sender_id' => '/^\d{5,20}$/'] as $k => $re) {
            $v = trim((string)($_POST[$k] ?? ''));
            if ($v !== '' && !preg_match($re, $v)) {
                flash('danger', 'That does not look like a valid ' . ['fcm_project_id' => 'Project ID', 'fcm_api_key' => 'API key', 'fcm_app_id' => 'App ID (it looks like 1:1234…:android:abcd…)', 'fcm_sender_id' => 'Sender ID (numbers only)'][$k] . '.');
                redirect('admin/settings.php?tab=notifications');
            }
            save_setting($k, $v);
        }
        log_activity('settings.push_config');
        flash('success', 'Firebase app settings saved.');
        redirect('admin/settings.php?tab=notifications');
    }

    if ($isSuper && $action === 'push_key') {
        $f = $_FILES['fcm_key'] ?? null;
        $j = $f && $f['error'] === UPLOAD_ERR_OK && $f['size'] < 20000 ? json_decode((string)file_get_contents($f['tmp_name']), true) : null;
        if (!is_array($j) || ($j['type'] ?? '') !== 'service_account' || empty($j['private_key']) || empty($j['client_email']) || empty($j['project_id'])) {
            flash('danger', 'Upload the service-account key file (.json) downloaded from Firebase > Project settings > Service accounts.');
        } elseif (setting('fcm_project_id') !== '' && $j['project_id'] !== setting('fcm_project_id')) {
            flash('danger', 'This key belongs to Firebase project "' . $j['project_id'] . '", but the Project ID saved above is "' . setting('fcm_project_id') . '".');
        } elseif (@file_put_contents(FCM_SA_FILE, json_encode($j)) === false) {
            flash('danger', 'Could not save the key: the storage folder is not writable.');
        } else {
            @chmod(FCM_SA_FILE, 0600);
            save_setting('fcm_access_token', '');
            log_activity('settings.push_key', $j['client_email']);
            flash('success', 'Service-account key saved (kept privately on the server).');
        }
        redirect('admin/settings.php?tab=notifications');
    }

    if ($isSuper && $action === 'push_key_remove') {
        @unlink(FCM_SA_FILE);
        save_setting('fcm_access_token', '');
        flash('success', 'Key removed. Push notifications are off.');
        redirect('admin/settings.php?tab=notifications');
    }

    if ($action === 'push_test') {
        $devices = q_all("SELECT token FROM push_devices WHERE user_type = 'admin' AND user_id = ?", [$admin['id']]);
        if (!push_ready()) {
            flash('danger', 'Push is not set up yet: fill in the Firebase values and upload the key first.');
        } elseif (!$devices) {
            flash('warning', 'No phone is registered for you yet. Open the Jatta Security app on your phone and log in as admin, then try again.');
        } else {
            $ok = 0;
            foreach ($devices as $d) {
                $ok += fcm_send($d['token'], ['title' => '✅ Test notification', 'body' => 'Push notifications are working for ' . company_name() . '.',
                    'url' => 'admin/index.php', 'channel' => 'alerts', 'tag' => 'test', 'badge' => admin_badge_count()]) === 'ok' ? 1 : 0;
            }
            flash($ok ? 'success' : 'danger', $ok ? "Sent to $ok phone(s). Check your notification bar." : 'Sending failed. Check the Firebase values and key (details are in storage/php-errors.log).');
        }
        redirect('admin/settings.php?tab=notifications');
    }

    if ($isSuper && $action === 'run_maintenance') {
        run_maintenance();
        flash('success', 'Cleanup finished: old location history purged and forgotten shifts closed.');
        redirect('admin/settings.php?tab=tracking');
    }
}

$admins = $isSuper ? q_all('SELECT id, name, email, role, is_active, last_login_at, created_at FROM admins ORDER BY name') : [];
$log = $tab === 'activity' ? q_all("SELECT l.*, COALESCE(a.name, g.full_name) AS actor_name FROM activity_log l
                                     LEFT JOIN admins a ON l.actor_type = 'admin' AND a.id = l.actor_id
                                     LEFT JOIN guards g ON l.actor_type = 'guard' AND g.id = l.actor_id
                                     ORDER BY l.id DESC LIMIT 200") : [];
$locRows = $tab === 'tracking' ? (int)q_val('SELECT COUNT(*) FROM guard_locations') : 0;
$s = fn($k) => e(setting($k));
$dis = $isSuper ? '' : 'disabled';

$pageTitle = 'Settings';
$activeNav = 'settings';
require APP_ROOT . '/includes/layout/header.php';
?>
<ul class="nav nav-tabs mb-3">
    <?php foreach (['company' => 'Company & billing', 'tracking' => 'Tracking', 'notifications' => 'Notifications', 'admins' => 'Admin users', 'account' => 'My account', 'activity' => 'Activity log'] as $k => $lbl):
        if ($k === 'admins' && !$isSuper) continue; ?>
        <li class="nav-item"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="?tab=<?= $k ?>"><?= $lbl ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if (!$isSuper && in_array($tab, ['company', 'tracking'], true)): ?>
    <div class="alert alert-info small">Only a super admin can change these settings.</div>
<?php endif; ?>

<?php if ($tab === 'company'): ?>
<div class="card" style="max-width:900px"><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="company">
        <fieldset <?= $dis ?>>
        <div class="form-section-title">Company (shown on invoices and logins)</div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Company name</label><input name="company_name" class="form-control" value="<?= $s('company_name') ?>"></div>
            <div class="col-md-6"><label class="form-label">GSTIN / Tax ID</label><input name="company_gstin" class="form-control" value="<?= $s('company_gstin') ?>"></div>
            <div class="col-12"><label class="form-label">Address</label><textarea name="company_address" class="form-control" rows="2"><?= $s('company_address') ?></textarea></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input name="company_phone" class="form-control" value="<?= $s('company_phone') ?>"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input name="company_email" class="form-control" value="<?= $s('company_email') ?>"></div>
        </div>
        <div class="form-section-title">Billing</div>
        <div class="row g-3">
            <div class="col-md-2"><label class="form-label">Currency</label><input name="currency_symbol" class="form-control" value="<?= $s('currency_symbol') ?>" maxlength="5"></div>
            <div class="col-md-2"><label class="form-label">Tax name</label><input name="tax_label" class="form-control" value="<?= $s('tax_label') ?>"></div>
            <div class="col-md-2"><label class="form-label">Tax %</label><input type="number" step="0.01" name="tax_percent" class="form-control" value="<?= $s('tax_percent') ?>"></div>
            <div class="col-md-3"><label class="form-label">Invoice prefix</label><input name="invoice_prefix" class="form-control" value="<?= $s('invoice_prefix') ?>"></div>
            <div class="col-md-3"><label class="form-label">Due in (days)</label><input type="number" name="invoice_due_days" class="form-control" value="<?= $s('invoice_due_days') ?>"></div>
            <div class="col-12"><label class="form-label">Default invoice terms</label><textarea name="invoice_terms" class="form-control" rows="3"><?= $s('invoice_terms') ?></textarea></div>
        </div>
        <button class="btn btn-primary mt-3">Save</button>
        </fieldset>
    </form>
</div></div>

<?php elseif ($tab === 'tracking'): ?>
<div class="card" style="max-width:900px"><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="tracking">
        <fieldset <?= $dis ?>>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">GPS update every (seconds)</label><input type="number" min="10" max="300" name="tracking_interval_sec" class="form-control" value="<?= $s('tracking_interval_sec') ?>">
                <div class="form-text">How often the guard's phone sends its location while on duty. 15–30 s is a good balance with battery.</div></div>
            <div class="col-md-4"><label class="form-label">Show "No signal" after (minutes)</label><input type="number" min="1" max="60" name="offline_after_min" class="form-control" value="<?= $s('offline_after_min') ?>"></div>
            <div class="col-md-4"><label class="form-label">Keep route history (days)</label><input type="number" min="7" max="730" name="location_retention_days" class="form-control" value="<?= $s('location_retention_days') ?>">
                <div class="form-text">Currently <?= number_format($locRows) ?> location points stored.</div></div>
            <div class="col-md-4"><label class="form-label">Max check-in distance from site (m)</label><input type="number" min="0" name="checkin_max_distance_m" class="form-control" value="<?= $s('checkin_max_distance_m') ?>">
                <div class="form-text">Guards farther than this (or the site's geofence, whichever is larger) cannot check in. 0 = no limit.</div></div>
            <div class="col-12"><h3 class="fs-6 fw-bold mb-0 mt-2">Duty, late &amp; overtime</h3></div>
            <div class="col-md-4"><div class="form-check form-switch mt-md-4">
                <input type="hidden" name="require_selfie" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="reqSelfie" name="require_selfie" value="1" <?= setting('require_selfie', '1') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="reqSelfie">Selfie required to start duty</label></div></div>
            <div class="col-md-4"><label class="form-label">Keep duty selfies (days)</label><input type="number" min="7" max="730" name="selfie_retention_days" class="form-control" value="<?= $s('selfie_retention_days') ?>"></div>
            <div class="col-md-4"><label class="form-label">Late after shift start + (minutes)</label><input type="number" min="0" max="240" name="late_grace_min" class="form-control" value="<?= $s('late_grace_min') ?>">
                <div class="form-text">Grace time. After this the guard is marked Late and you get an alert if they haven't started duty.</div></div>
            <div class="col-md-4"><label class="form-label">Default shift length (hours)</label><input type="number" min="1" max="24" step="0.5" name="default_shift_hours" class="form-control" value="<?= $s('default_shift_hours') ?>">
                <div class="form-text">Used for overtime when a deployment has no shift timings.</div></div>
            <div class="col-md-4"><label class="form-label">Count overtime from (minutes)</label><input type="number" min="0" max="120" name="ot_min_minutes" class="form-control" value="<?= $s('ot_min_minutes') ?>">
                <div class="form-text">Extra time shorter than this is ignored.</div></div>
            <div class="col-md-4"><label class="form-label">"Not moving" alert after (minutes)</label><input type="number" min="0" max="600" name="no_movement_min" class="form-control" value="<?= $s('no_movement_min') ?>">
                <div class="form-text">Alert when an on-duty guard stays within <?= STILL_RADIUS_M ?> m this long. 0 = off.</div></div>
            <div class="col-md-4"><label class="form-label">"Battery low" alert at (%)</label><input type="number" min="0" max="50" name="battery_alert_pct" class="form-control" value="<?= $s('battery_alert_pct') ?>">
                <div class="form-text">Alert when an on-duty guard's phone battery drops to this level. 0 = off.</div></div>
            <div class="col-12"><h3 class="fs-6 fw-bold mb-0 mt-2">Map</h3></div>
            <div class="col-md-3"><label class="form-label">Map centre latitude</label><input name="map_default_lat" class="form-control" value="<?= $s('map_default_lat') ?>"></div>
            <div class="col-md-3"><label class="form-label">Map centre longitude</label><input name="map_default_lng" class="form-control" value="<?= $s('map_default_lng') ?>"></div>
            <div class="col-md-2"><label class="form-label">Zoom</label><input type="number" min="2" max="18" name="map_default_zoom" class="form-control" value="<?= $s('map_default_zoom') ?>"></div>
        </div>
        <button class="btn btn-primary mt-3">Save</button>
        </fieldset>
    </form>
    <?php if ($isSuper): ?>
        <hr>
        <form method="post" class="d-flex align-items-center gap-3"><?= csrf_field() ?><input type="hidden" name="action" value="run_maintenance">
            <button class="btn btn-outline-secondary btn-sm">Run cleanup now</button>
            <span class="small text-muted">Purges old route history and closes shifts guards forgot to end (also runs automatically).</span></form>
    <?php endif; ?>
</div></div>

<?php elseif ($tab === 'admins' && $isSuper): ?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card table-card"><div class="table-responsive"><table class="table table-hover">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td class="fw-semibold"><?= e($a['name']) ?></td><td class="small"><?= e($a['email']) ?></td>
                    <td><span class="badge badge-soft-<?= $a['role'] === 'superadmin' ? 'primary' : 'secondary' ?>"><?= e(label($a['role'])) ?></span></td>
                    <td class="small"><?= e(fmt_datetime($a['last_login_at'])) ?></td>
                    <td><?= status_badge($a['is_active'] ? 'active' : 'inactive') ?></td>
                    <td class="text-end"><?php if ((int)$a['id'] !== (int)$admin['id']): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-sm btn-light"><?= $a['is_active'] ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header">Add admin user</div><div class="card-body">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="admin_add">
                <div class="mb-2"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password"></div>
                <div class="mb-3"><label class="form-label">Role</label><select name="role" class="form-select"><option value="admin">Admin (operations)</option><option value="superadmin">Super admin (also settings & users)</option></select></div>
                <button class="btn btn-primary w-100">Add admin</button>
            </form>
        </div></div>
    </div>
</div>

<?php elseif ($tab === 'notifications'):
    $pc = push_public_config();
    $sa = fcm_service_account();
    $devCounts = q_one("SELECT COALESCE(SUM(user_type = 'admin'), 0) admins, COALESCE(SUM(user_type = 'guard'), 0) guards FROM push_devices");
    $mine = (int)q_val("SELECT COUNT(*) FROM push_devices WHERE user_type = 'admin' AND user_id = ?", [$admin['id']]); ?>
<div class="card mb-3" style="max-width:900px"><div class="card-body">
    <h2 class="fs-6 fw-bold">Phone notifications (Jatta Security app)</h2>
    <p class="small text-muted mb-2">SOS, late, no signal, battery low, phone switched off, leave and extra-hours requests arrive on admins' phones like WhatsApp messages — even when the app is closed — with a count on the app icon. Guards get approvals and handovers.</p>
    <div class="d-flex flex-wrap gap-2 mb-1">
        <span class="badge badge-soft-<?= $pc ? 'success' : 'secondary' ?>">App values <?= $pc ? 'saved' : 'missing' ?></span>
        <span class="badge badge-soft-<?= $sa ? 'success' : 'secondary' ?>">Server key <?= $sa ? 'uploaded' : 'missing' ?></span>
        <span class="badge badge-soft-<?= push_ready() ? 'success' : 'warning' ?>"><?= push_ready() ? 'Push is ON' : 'Push is OFF' ?></span>
        <span class="badge badge-soft-primary"><?= (int)$devCounts['admins'] ?> admin phone(s) · <?= (int)$devCounts['guards'] ?> guard phone(s)</span>
    </div>
</div></div>

<?php if ($isSuper): ?>
<div class="card mb-3" style="max-width:900px"><div class="card-body">
    <h3 class="fs-6 fw-bold">1. Firebase app values</h3>
    <p class="small text-muted">In <a href="https://console.firebase.google.com" target="_blank" rel="noopener">Firebase console</a>: create a project → <em>Project settings</em> → add an <strong>Android app</strong> with package name <code>com.jattamkommerce.security.guard</code> → copy these values (they are also inside the <code>google-services.json</code> file it offers).</p>
    <form method="post" class="row g-2"><?= csrf_field() ?><input type="hidden" name="action" value="push_config">
        <div class="col-md-6"><label class="form-label small">Project ID</label><input name="fcm_project_id" class="form-control" value="<?= $s('fcm_project_id') ?>" placeholder="jatta-security-12ab3"></div>
        <div class="col-md-6"><label class="form-label small">Sender ID (project number)</label><input name="fcm_sender_id" class="form-control" value="<?= $s('fcm_sender_id') ?>" placeholder="123456789012"></div>
        <div class="col-md-6"><label class="form-label small">App ID</label><input name="fcm_app_id" class="form-control" value="<?= $s('fcm_app_id') ?>" placeholder="1:123456789012:android:abc123…"></div>
        <div class="col-md-6"><label class="form-label small">Web API key</label><input name="fcm_api_key" class="form-control" value="<?= $s('fcm_api_key') ?>" placeholder="AIza…"></div>
        <div class="col-12"><button class="btn btn-primary">Save app values</button></div>
    </form>
</div></div>
<div class="card mb-3" style="max-width:900px"><div class="card-body">
    <h3 class="fs-6 fw-bold">2. Server key (private)</h3>
    <p class="small text-muted">Firebase → <em>Project settings</em> → <em>Service accounts</em> → <strong>Generate new private key</strong>. Upload the downloaded .json file here. It is stored privately on the server and is never shown again. Do not email it or put it on GitHub.</p>
    <?php if ($sa): ?><p class="small mb-2"><i class="bi bi-check-circle-fill text-success"></i> Key for <code><?= e($sa['client_email']) ?></code></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="push_key">
        <input type="file" name="fcm_key" accept="application/json,.json" class="form-control" style="max-width:360px" required>
        <button class="btn btn-primary"><?= $sa ? 'Replace key' : 'Upload key' ?></button>
    </form>
    <?php if ($sa): ?><form method="post" class="mt-2" data-confirm="Remove the key? Push notifications will stop."><?= csrf_field() ?><input type="hidden" name="action" value="push_key_remove"><button class="btn btn-link btn-sm text-danger p-0">Remove key</button></form><?php endif; ?>
</div></div>
<?php endif; ?>
<div class="card" style="max-width:900px"><div class="card-body">
    <h3 class="fs-6 fw-bold">3. Test</h3>
    <p class="small text-muted">Log in as admin in the Jatta Security app on your phone (it registers automatically; <?= $mine ?> phone(s) registered for you), then:</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="push_test"><button class="btn btn-success" <?= push_ready() ? '' : 'disabled' ?>><i class="bi bi-bell"></i> Send test notification to my phone</button></form>
</div></div>

<?php elseif ($tab === 'account'): ?>
<div class="card" style="max-width:520px"><div class="card-header">Change password</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="mb-2"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
        <div class="mb-2"><label class="form-label">New password</label><input type="password" name="new_password" class="form-control" minlength="8" required autocomplete="new-password"></div>
        <div class="mb-3"><label class="form-label">Confirm new password</label><input type="password" name="confirm_password" class="form-control" minlength="8" required autocomplete="new-password"></div>
        <button class="btn btn-primary">Change password</button>
    </form>
</div></div>

<?php elseif ($tab === 'activity'): ?>
<div class="card table-card"><div class="table-responsive"><table class="table table-hover table-sm">
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php if (!$log): ?><tr><td colspan="5" class="text-center text-muted py-4">No activity yet</td></tr><?php endif; ?>
    <?php foreach ($log as $l): ?>
        <tr><td class="small text-nowrap"><?= e(fmt_datetime($l['created_at'])) ?></td>
            <td class="small"><?= e(label($l['actor_type'])) ?><?= $l['actor_name'] ? ': ' . e($l['actor_name']) : '' ?></td>
            <td class="small"><code><?= e($l['action']) ?></code></td><td class="small"><?= e($l['details']) ?></td><td class="small text-muted"><?= e($l['ip']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/layout/footer.php'; ?>
