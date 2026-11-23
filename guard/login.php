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
$title = 'Guard Sign In';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07070a">
    <title><?= e($title) ?> · <?= e(company_name()) ?></title>
    
    <link rel="manifest" href="<?= url('guard/manifest.php') ?>">
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= asset('img/icon-192.png') ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #07070a;
            --bg-card: #0f1019;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-focus: rgba(168, 85, 247, 0.45);
            --accent-purple: #8b5cf6;
            --accent-gradient: linear-gradient(135deg, #7c3aed 0%, #9333ea 50%, #a855f7 100%);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: var(--font-main);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1.1rem;
            -webkit-tap-highlight-color: transparent;
        }

        .ambient-glow {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 500px;
            max-width: 100vw;
            height: 300px;
            background: radial-gradient(ellipse at 50% 0%, rgba(139, 92, 246, 0.25) 0%, transparent 70%);
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
        }

        .auth-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 24px;
            padding: 2.2rem 1.8rem;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.55);
        }

        .auth-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.2), rgba(124, 58, 237, 0.3));
            border: 1px solid rgba(168, 85, 247, 0.4);
            color: #d8b4fe;
            display: grid;
            place-items: center;
            font-size: 1.8rem;
            margin: 0 auto 1.25rem;
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.25);
        }

        .auth-title {
            font-size: 1.35rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 0.25rem;
            letter-spacing: -0.02em;
        }

        .auth-subtitle {
            text-align: center;
            font-size: 0.88rem;
            color: var(--text-secondary);
            margin-bottom: 1.75rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 0.45rem;
        }

        .form-control-custom {
            width: 100%;
            height: 52px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            color: #ffffff;
            padding: 0 1.1rem;
            font-size: 0.95rem;
            font-family: inherit;
            margin-bottom: 1.2rem;
            transition: all 0.2s;
        }
        .form-control-custom:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--border-focus);
            box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.15);
            color: #ffffff;
        }

        .btn-signin {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            height: 52px;
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 1rem;
            font-weight: 800;
            border-radius: 14px;
            border: none;
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.4);
            cursor: pointer;
            margin-top: 0.5rem;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-signin:active {
            transform: scale(0.98);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            font-size: 0.86rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bottom-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-subtle);
            font-size: 0.84rem;
        }
        .bottom-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }
        .bottom-links a:hover {
            color: #fff;
        }
        .link-install {
            color: #c084fc !important;
            font-weight: 700;
        }
    </style>
</head>
<body>

<div class="ambient-glow"></div>

<div class="auth-card">
    <div class="auth-icon">
        <i class="bi bi-shield-shaded"></i>
    </div>
    
    <h1 class="auth-title"><?= e(company_name()) ?></h1>
    <p class="auth-subtitle">Guard & Bodyguard Duty Sign In</p>

    <?php if ($error): ?>
        <div class="alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        
        <label class="form-label">Employee Code or Phone</label>
        <input name="login" class="form-control-custom" value="<?= e($login) ?>" autocomplete="username" autocapitalize="characters" placeholder="e.g. SEC-001 or 9876543210" required autofocus>

        <label class="form-label">Password / PIN</label>
        <input type="password" name="password" class="form-control-custom" autocomplete="current-password" placeholder="Enter PIN (e.g. 123456)" required>

        <button type="submit" class="btn-signin">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>Sign In to Duty</span>
        </button>
    </form>

    <div class="bottom-links">
        <a href="<?= url('guard/install.php') ?>" class="link-install">
            <i class="bi bi-download me-1"></i>Install App & APK
        </a>
        <a href="<?= url('index.php') ?>">
            <i class="bi bi-house me-1"></i>Website
        </a>
    </div>
</div>

<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(<?= json_encode(url('guard/sw.js')) ?>).catch(() => {});
}
</script>
</body>
</html>
