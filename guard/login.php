<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

if (current_guard()) {
    redirect('guard/index.php');
}
$error = null;
$login = '';
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
    <div class="text-center mb-4">
        <div class="g-logo"><i class="bi bi-shield-shaded"></i></div>
        <h1 class="h4 fw-bold mb-1"><?= e(company_name()) ?></h1>
        <div class="text-white-50 small">Bodyguard Duty App</div>
    </div>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div><?php endif; ?>
    <?php if (!IS_HTTPS && !in_array(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0], ['localhost', '127.0.0.1'], true)): ?>
        <div class="alert alert-warning py-2 small">This site is not using HTTPS. Phone GPS will not work until SSL is enabled.</div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label class="form-label">Employee code or phone</label>
        <input name="login" class="form-control form-control-lg mb-3" value="<?= e($login) ?>" autocomplete="username" autocapitalize="characters" required autofocus>
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control form-control-lg mb-4" autocomplete="current-password" required>
        <button class="btn btn-warning btn-lg w-100 fw-bold"><i class="bi bi-box-arrow-in-right me-1"></i> Sign in</button>
    </form>
    <p class="text-white-50 small text-center mt-4 mb-2">Forgot your password? Ask your supervisor to reset it.</p>
    <p class="text-center mb-0"><a href="<?= url('index.php') ?>" class="text-white-50 small"><i class="bi bi-arrow-left me-1"></i>Back to Website</a></p>
</div>
<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register(<?= json_encode(url('guard/sw.js')) ?>).catch(() => {});</script>
<script src="<?= asset('js/password-toggle.js') ?>"></script>
</body>
</html>
