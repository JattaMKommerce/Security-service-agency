<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (current_guard()) {
    redirect('guard/index.php');
}
$error = null;
$login = (string)post_str('login', 40);
if ($login === '') {
    $login = (string)($_GET['code'] ?? ($_GET['login'] ?? ''));
}
if (is_post()) {
    require_csrf();
    $login = (string)post_str('login', 40);
    $error = guard_attempt_login($login, (string)($_POST['password'] ?? ''));
    if ($error === null) {
        redirect('guard/index.php');
    }
}
$title = 'Guard Login';
?><!doctype html>
<html lang="en">
<head><?php require __DIR__ . '/_head.php'; ?></head>
<body class="g-auth">
<div class="g-auth-card">
    <div class="text-center mb-3">
        <div class="g-logo"><i class="bi bi-shield-shaded"></i></div>
        <h1 class="h4 fw-bold mb-1"><?= e(company_name()) ?></h1>
        <div class="text-white-50 small">Guard & Bodyguard Duty App</div>
    </div>

    <!-- Quick PWA Install Callout -->
    <div id="pwaBanner" class="p-2 mb-3 rounded-3 text-center" style="background: rgba(245, 179, 1, 0.15); border: 1px solid rgba(245, 179, 1, 0.35);">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div class="text-start">
                <div class="small fw-bold text-warning"><i class="bi bi-phone me-1"></i>Install App on Phone</div>
                <div class="text-white-50" style="font-size: .75rem;">1-tap install for Android & iPhone</div>
            </div>
            <a href="<?= url('guard/install.php') ?>" class="btn btn-warning btn-sm fw-bold px-3" style="font-size: .8rem;">
                <i class="bi bi-download me-1"></i>Install
            </a>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div><?php endif; ?>
    <?php if (!IS_HTTPS && !in_array(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0], ['localhost', '127.0.0.1'], true)): ?>
        <div class="alert alert-warning py-2 small">This site is not using HTTPS. Phone GPS will not work until SSL is enabled.</div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <label class="form-label">Employee Code or Phone Number</label>
        <input name="login" class="form-control form-control-lg mb-3" value="<?= e($login) ?>" autocomplete="username" autocapitalize="characters" placeholder="e.g. GRD-0002 or 8310979654" required autofocus>
        <label class="form-label">Password / PIN</label>
        <input type="password" name="password" class="form-control form-control-lg mb-2" autocomplete="current-password" placeholder="Enter password (e.g. 123456)" required>
        <div class="small text-warning opacity-75 mb-4"><i class="bi bi-info-circle me-1"></i>Default guard PIN is <strong>123456</strong></div>
        <button class="btn btn-warning btn-lg w-100 fw-bold"><i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Duty</button>
    </form>

    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-white border-opacity-10">
        <a href="<?= url('guard/install.php') ?>" class="text-warning text-decoration-none small fw-semibold">
            <i class="bi bi-question-circle me-1"></i>App & APK Guide
        </a>
        <a href="<?= url('index.php') ?>" class="text-white-50 small text-decoration-none">
            <i class="bi bi-house me-1"></i>Agency Website
        </a>
    </div>

    <p class="text-white-50 small text-center mt-3 mb-0" style="font-size: .78rem;">Forgot password? Contact control room or supervisor.</p>
</div>
<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register(<?= json_encode(url('guard/sw.js')) ?>).catch(() => {});</script>
<script src="<?= asset('js/password-toggle.js') ?>"></script>
</body>
</html>