<?php
/**
 * One-time installer: creates the tables and the first super admin.
 * After a successful install it locks itself (storage/installed.lock).
 */
require __DIR__ . '/includes/bootstrap.php';

$lock = APP_ROOT . '/storage/installed.lock';
$errors = [];
$done = false;

if (is_file($lock)) {
    $already = true;
} else {
    $already = false;
    if (is_post()) {
        require_csrf();
        $company = post_str('company_name', 150) ?? 'Security Agency';
        $name = post_str('name', 100);
        $email = mb_strtolower((string)post_str('email', 150));
        $pass = (string)($_POST['password'] ?? '');

        if (!$name) $errors[] = 'Admin name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
        if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($pass !== ($_POST['password_confirm'] ?? '')) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            try {
                $pdo = db();
                $sql = file_get_contents(APP_ROOT . '/database/schema.sql');
                $sql = preg_replace('/^\s*--.*$/m', '', $sql);
                foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
                    if (trim($stmt) !== '') {
                        $pdo->exec($stmt);
                    }
                }
                if (q_val('SELECT COUNT(*) FROM admins') > 0) {
                    $errors[] = 'An admin account already exists in this database. Log in instead.';
                } else {
                    q('INSERT INTO admins (name, email, password_hash, role) VALUES (?,?,?,\'superadmin\')',
                      [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
                    save_setting('company_name', $company);
                    if (@file_put_contents($lock, date('c')) === false) {
                        $errors[] = 'Installed, but could not write storage/installed.lock. Make the storage folder writable, or delete install.php.';
                    }
                    log_activity('system.install', 'Installed by ' . $email, 'system');
                    $done = true;
                }
            } catch (PDOException $e) {
                $errors[] = 'Database error: ' . $e->getMessage() . ' — check config/config.php.';
            }
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install · Security Agency</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:480px">
    <div class="text-center mb-3">
        <span class="brand-mark"><i class="bi bi-shield-shaded"></i></span>
        <h1 class="h4 fw-bold mb-1">Setup</h1>
        <p class="text-muted small mb-0">Creates the database tables and your super admin login.</p>
    </div>

    <?php if ($already): ?>
        <div class="alert alert-info">The application is already installed.</div>
        <a class="btn btn-primary w-100" href="<?= url('login.php') ?>">Go to admin login</a>
    <?php elseif ($done && !$errors): ?>
        <div class="alert alert-success"><strong>Installed successfully.</strong><br>For safety, delete <code>install.php</code> from the server now.</div>
        <a class="btn btn-primary w-100" href="<?= url('login.php') ?>">Go to admin login</a>
    <?php else: ?>
        <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Company name</label>
                <input class="form-control" name="company_name" value="<?= e($_POST['company_name'] ?? '') ?>" placeholder="e.g. Shield Security Services" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Your name</label>
                <input class="form-control" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Admin email (used to log in)</label>
                <input type="email" class="form-control" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" minlength="8" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Confirm</label>
                    <input type="password" class="form-control" name="password_confirm" minlength="8" required>
                </div>
            </div>
            <button class="btn btn-primary w-100">Install</button>
        </form>
    <?php endif; ?>
</div>
<script src="<?= asset('js/password-toggle.js') ?>"></script>
</body>
</html>
