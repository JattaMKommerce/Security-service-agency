<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_file(APP_ROOT . '/storage/installed.lock') && is_file(APP_ROOT . '/install.php')) {
    redirect('install.php');
}
if (current_admin()) {
    redirect('admin/index.php');
}
if (current_guard()) {
    redirect('guard/index.php');
}

$error = null;
$login = (string)($_GET['code'] ?? ($_GET['login'] ?? ''));
if (is_post()) {
    require_csrf();
    $login = (string)post_str('login', 150);
    if ($login === '') {
        $login = (string)post_str('email', 150);
    }
    $password = (string)($_POST['password'] ?? '');

    $result = universal_attempt_login($login, $password);
    if ($result['error'] === null) {
        if ($result['role'] === 'guard') {
            redirect('guard/index.php');
        }

        $to = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
        // Only follow internal admin paths after login.
        if (is_string($to) && preg_match('#^' . preg_quote($basePath, '#') . '/admin/[\w\-]+\.php(\?[^\s]*)?$#', $to)) {
            header('Location: ' . $to);
            exit;
        }
        redirect('admin/index.php');
    } else {
        $error = $result['error'];
    }
}
$flashes = flashes();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In · <?= e(company_name()) ?></title>
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
        <p class="text-muted small mb-0">Command Center & Guard Sign In</p>
    </div>
    <?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div><?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="login">Email, Phone Number, or Guard Code</label>
            <input type="text" class="form-control form-control-lg" id="login" name="login" value="<?= e($login) ?>" autocomplete="username" placeholder="e.g. a@gmail.com, 8310979654, or GRD-0002" required autofocus>
            <div class="form-text small text-muted">Admins use email; Guards use mobile number or employee badge code.</div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold" for="password">Password / PIN</label>
            <input type="password" class="form-control form-control-lg" id="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
        </div>
        <button class="btn btn-primary btn-lg w-100 py-2 fw-semibold"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In</button>
    </form>

    <div class="card bg-light border-0 p-3 mt-4 rounded-3 small">
        <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1 text-primary"></i> Quick Login Credentials:</div>
        <div class="d-flex justify-content-between text-muted mb-1">
            <span>Admin Command Center:</span>
            <span class="font-monospace text-dark"><code>a@gmail.com</code> / <code>admin123</code></span>
        </div>
        <div class="d-flex justify-content-between text-muted">
            <span>Guard Duty App:</span>
            <span class="font-monospace text-dark">Enter phone or code / PIN: <code>123456</code></span>
        </div>
    </div>

    <div class="text-center mt-3 small d-flex flex-column gap-2">
        <a href="<?= url('guard/login.php') ?>" class="text-primary fw-semibold"><i class="bi bi-phone me-1"></i>Open Dedicated Mobile Guard App</a>
        <a href="<?= url('index.php') ?>" class="text-muted"><i class="bi bi-arrow-left me-1"></i>Back to Public Website</a>
    </div>
</div>
<script src="<?= asset('js/password-toggle.js') ?>"></script>
</body>
</html>