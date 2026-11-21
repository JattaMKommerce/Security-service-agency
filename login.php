<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_file(APP_ROOT . '/storage/installed.lock') && is_file(APP_ROOT . '/install.php')) {
    redirect('install.php');
}
if (current_admin()) {
    redirect('admin/index.php');
}

$error = null;
$email = '';
if (is_post()) {
    require_csrf();
    $email = (string)post_str('email', 150);
    $error = admin_attempt_login($email, (string)($_POST['password'] ?? ''));
    if ($error === null) {
        $to = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
        // Only follow internal admin paths after login.
        if (is_string($to) && preg_match('#^' . preg_quote($basePath, '#') . '/admin/[\w\-]+\.php(\?[^\s]*)?$#', $to)) {
            header('Location: ' . $to);
            exit;
        }
        redirect('admin/index.php');
    }
}
$flashes = flashes();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login · <?= e(company_name()) ?></title>
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-card">
    <div class="text-center mb-4">
        <span class="brand-mark"><i class="bi bi-shield-shaded"></i></span>
        <h1 class="h4 fw-bold mb-1"><?= e(company_name()) ?></h1>
        <p class="text-muted small mb-0">Admin Command Center</p>
    </div>
    <?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
    </form>
    <div class="text-center mt-4 small">
        <a href="<?= url('guard/login.php') ?>"><i class="bi bi-phone me-1"></i>Bodyguard? Open the guard app</a>
    </div>
</div>
<script src="<?= asset('js/password-toggle.js') ?>"></script>
</body>
</html>
