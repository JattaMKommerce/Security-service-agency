<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_file(APP_ROOT . '/storage/installed.lock') && is_file(APP_ROOT . '/install.php')) {
    redirect('install.php');
}

$inquirySuccess = false;
$inquiryError = null;

if (is_post() && ($_POST['action'] ?? '') === 'inquiry') {
    require_csrf();
    $name     = post_str('name', 120);
    $phone    = post_str('phone', 30);
    $email    = post_str('email', 150);
    $service  = post_str('service_type', 50) ?? 'bodyguard';
    $armed    = !empty($_POST['is_armed']) ? 1 : 0;
    $count    = max(1, min(500, (int)post_int('guards_needed', 1)));
    $city     = post_str('city', 80);
    $message  = post_str('message', 2000);

    if (!$name || strlen($name) < 2) {
        $inquiryError = 'Please enter your full name.';
    } elseif (!$phone || strlen($phone) < 8) {
        $inquiryError = 'Please enter a valid contact phone number.';
    } else {
        try {
            q('INSERT INTO inquiries (name, phone, email, service_type, is_armed, guards_needed, city, message, status)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, "new")',
              [$name, $phone, $email, $service, $armed, $count, $city, $message]);
            log_activity('website.inquiry', "Quote request from $name ($phone) for $count $service guard(s)", 'system');
            $inquirySuccess = true;
        } catch (Throwable $e) {
            $inquiryError = 'Could not submit your request. Please try calling our direct dispatch hotline.';
        }
    }
}

$admin = current_admin();
$compName = company_name();
$compPhone = setting('company_phone', '+91 98200 12345');
$compEmail = setting('company_email', 'dispatch@' . ($_SERVER['HTTP_HOST'] ?? 'securityagency.com'));
$compAddress = setting('company_address', 'Corporate Command Center, Metro City');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($compName) ?> · Elite Security & Guard Force Management</title>
    <meta name="description" content="Certified Personal Bodyguards, Building Security & Agency Guard Force. Armed & unarmed personnel with 24/7 Live OpenStreetMap GPS tracking and rapid dispatch.">
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #090d16;
            --bg-card: #111726;
            --bg-card-hover: #161f33;
            --border-color: #1f293d;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --accent: #f59e0b;
            --accent-glow: rgba(245, 158, 11, 0.15);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            background-color: var(--bg-dark);
            color: var(--text-main);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }

        /* Top Admin Bar when logged in */
        .admin-bar {
            background: #1e1b4b;
            color: #c7d2fe;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            text-align: center;
            border-bottom: 1px solid #312e81;
        }
        .admin-bar a { color: #fff; font-weight: 600; text-decoration: underline; margin-left: 0.5rem; }

        /* Navigation */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(9, 13, 22, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.85rem 0;
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #fff;
        }
        .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }
        .brand-badge {
            font-size: 0.68rem;
            text-transform: uppercase;
            font-weight: 700;
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            padding: 0.15rem 0.45rem;
            border-radius: 4px;
            border: 1px solid rgba(245, 158, 11, 0.3);
            margin-left: 0.35rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            list-style: none;
        }
        .nav-links a {
            font-size: 0.92rem;
            font-weight: 500;
            color: var(--text-muted);
            transition: color 0.15s ease;
        }
        .nav-links a:hover { color: #fff; }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            font-family: var(--font);
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.18s ease;
            text-align: center;
            white-space: nowrap;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .btn-accent {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #0f172a;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(245, 158, 11, 0.25);
        }
        .btn-accent:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            border-color: var(--border-color);
            color: var(--text-main);
        }
        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: #334155;
            color: #fff;
        }
        .btn-sm {
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            border-radius: 6px;
        }

        /* Mobile Menu Toggle */
        .mobile-toggle {
            display: none;
            background: transparent;
            border: 1px solid var(--border-color);
            color: #fff;
            font-size: 1.35rem;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        /* Hero Section */
        .hero {
            padding: 5rem 0 3.5rem;
            position: relative;
            background: radial-gradient(circle at 50% 20%, rgba(37, 99, 235, 0.12) 0%, rgba(9, 13, 22, 0) 70%);
            border-bottom: 1px solid var(--border-color);
        }
        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #60a5fa;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            margin-bottom: 1.5rem;
        }
        .hero-pill .dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 8px #10b981;
        }
        .hero-title {
            font-size: clamp(2.2rem, 5vw, 3.6rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-bottom: 1.25rem;
            max-width: 900px;
        }
        .hero-title span {
            background: linear-gradient(135deg, #60a5fa, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: var(--text-muted);
            max-width: 720px;
            margin-bottom: 2.25rem;
        }
        .hero-cta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 3.5rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
        }
        .stat-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .stat-box:hover {
            transform: translateY(-2px);
            border-color: #2e3d5b;
        }
        .stat-num {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }
        .stat-num.highlight { color: #f59e0b; }
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Section Layouts */
        .section {
            padding: 4.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 3rem;
        }
        .section-tag {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: 0.08em;
            margin-bottom: 0.6rem;
            display: block;
        }
        .section-title {
            font-size: clamp(1.8rem, 3.5vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 0.75rem;
        }
        .section-desc {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* Roles / Categories Grid */
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }
        .role-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .role-card:hover {
            border-color: var(--primary);
            background: var(--bg-card-hover);
            transform: translateY(-3px);
        }
        .role-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .role-icon.blue { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .role-icon.amber { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .role-icon.green { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .role-icon.purple { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        .role-icon.red { background: rgba(239, 68, 68, 0.15); color: #f87171; }
        .role-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.6rem;
            color: #fff;
        }
        .role-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.55;
            margin-bottom: 1.25rem;
            flex-grow: 1;
        }
        .role-tags {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
        .badge-pill {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Armed vs Unarmed Showcase */
        .firearm-section {
            background: linear-gradient(180deg, rgba(17, 23, 38, 0.7) 0%, var(--bg-dark) 100%);
        }
        .firearm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        .firearm-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.25rem;
            position: relative;
        }
        .firearm-card.armed {
            border-color: rgba(245, 158, 11, 0.35);
            background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.06), var(--bg-card) 60%);
        }
        .firearm-card.unarmed {
            border-color: rgba(59, 130, 246, 0.35);
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.06), var(--bg-card) 60%);
        }
        .firearm-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .firearm-badge.armed { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
        .firearm-badge.unarmed { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
        .firearm-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            color: #fff;
        }
        .firearm-features {
            list-style: none;
            margin: 1.25rem 0;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .firearm-features li {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            font-size: 0.92rem;
            color: #cbd5e1;
        }
        .firearm-features li i {
            font-size: 1.1rem;
            margin-top: 0.1rem;
        }
        .firearm-card.armed .firearm-features li i { color: #f59e0b; }
        .firearm-card.unarmed .firearm-features li i { color: #60a5fa; }

        /* Tech / OpenStreetMap Feature */
        .tech-box {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2.5rem;
            align-items: center;
        }
        .tech-points {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .tech-point {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }
        .tech-point-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .tech-point-content h4 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
        }
        .tech-point-content p {
            font-size: 0.88rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .map-mockup {
            background: #0b111e;
            border: 1px solid #1f293d;
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
        }
        .map-mockup-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .live-pulse {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: #10b981;
            font-size: 0.78rem;
        }
        .live-pulse::before {
            content: '';
            width: 7px;
            height: 7px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px #10b981;
        }
        .map-guard-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 8px;
            padding: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.65rem;
            font-size: 0.85rem;
        }
        .map-guard-item:last-child { margin-bottom: 0; }

        /* Inquiry Form */
        .inquiry-wrap {
            max-width: 780px;
            margin: 0 auto;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #cbd5e1;
        }
        .form-label span.req { color: var(--danger); margin-left: 0.2rem; }
        .form-control, .form-select {
            width: 100%;
            padding: 0.7rem 0.95rem;
            font-family: var(--font);
            font-size: 0.92rem;
            background: #090d16;
            border: 1px solid #27354f;
            border-radius: 8px;
            color: #fff;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        textarea.form-control { resize: vertical; min-height: 90px; }

        .alert-box {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #6ee7b7; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; }

        /* Direct Contact Bar */
        .dispatch-bar {
            background: linear-gradient(135deg, #1e3a8a, #0f172a);
            border-radius: 14px;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            margin-top: 2.5rem;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .dispatch-bar h3 { font-size: 1.3rem; margin-bottom: 0.25rem; }
        .dispatch-bar p { font-size: 0.88rem; color: #cbd5e1; }
        .dispatch-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            padding: 3.5rem 0 2rem;
            background: #060910;
            border-top: 1px solid var(--border-color);
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.5fr;
            gap: 2.5rem;
            margin-bottom: 2.5rem;
        }
        .footer-col h4 {
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }
        .footer-links a:hover { color: #fff; }
        .footer-bottom {
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.82rem;
        }

        /* Responsive Breakpoints */
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .roles-grid { grid-template-columns: repeat(2, 1fr); }
            .tech-box { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #0d1322;
                border-bottom: 1px solid var(--border-color);
                flex-direction: column;
                padding: 1.25rem;
                gap: 1rem;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            }
            .nav-links.open { display: flex; }
            .hero { padding: 3.5rem 0 2.5rem; }
            .roles-grid { grid-template-columns: 1fr; }
            .firearm-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .dispatch-bar { flex-direction: column; text-align: center; }
            .dispatch-actions { justify-content: center; }
            .footer-grid { grid-template-columns: 1fr; gap: 1.75rem; }
            .footer-bottom { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<?php if ($admin): ?>
    <div class="admin-bar">
        <i class="bi bi-shield-lock-fill"></i> Logged in as <strong><?= e($admin['name']) ?></strong> (<?= e($admin['email']) ?>)
        <a href="<?= url('admin/index.php') ?>">Open Admin Command Center &rarr;</a>
    </div>
<?php endif; ?>

<!-- Top Navigation -->
<header class="navbar">
    <div class="container nav-inner">
        <a href="<?= url('index.php') ?>" class="brand">
            <span class="brand-icon"><i class="bi bi-shield-shaded"></i></span>
            <span><?= e($compName) ?></span>
            <span class="brand-badge">24/7 Ops</span>
        </a>

        <ul class="nav-links" id="navLinks">
            <li><a href="#services" class="js-nav">Guard Roles</a></li>
            <li><a href="#firearm-protocol" class="js-nav">Armed & Unarmed</a></li>
            <li><a href="#tracking" class="js-nav">GPS Dispatch</a></li>
            <li><a href="#quote" class="js-nav">Hire Guards</a></li>
            <li class="d-md-none"><a href="<?= url('login.php') ?>" style="color:#60a5fa"><i class="bi bi-box-arrow-in-right"></i> Admin Portal</a></li>
            <li class="d-md-none"><a href="<?= url('guard/login.php') ?>" style="color:#f59e0b"><i class="bi bi-phone"></i> Guard Mobile App</a></li>
        </ul>

        <div class="nav-actions">
            <a href="<?= url('login.php') ?>" class="btn btn-outline btn-sm"><i class="bi bi-shield-lock"></i> Admin Portal</a>
            <a href="<?= url('guard/login.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-phone"></i> Guard App</a>
            <button class="mobile-toggle" id="menuToggle" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>
</header>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-pill">
            <span class="dot"></span>
            <span>Active Guard Force & Live GPS Tracking</span>
        </div>
        <h1 class="hero-title">
            Elite <span>Bodyguards</span>, Building Security & Agency Guard Force
        </h1>
        <p class="hero-desc">
            Deploy 1 to 200+ police-verified security personnel on demand. Whether you need executive personal bodyguards, 24/7 corporate building security, or commercial agency protection—fully managed with OpenStreetMap live GPS dispatch.
        </p>

        <div class="hero-cta">
            <a href="#quote" class="btn btn-accent"><i class="bi bi-shield-check"></i> Request Security / Hire Guards</a>
            <a href="<?= url('login.php') ?>" class="btn btn-outline"><i class="bi bi-speedometer2"></i> Admin Command Center</a>
            <a href="tel:<?= e($compPhone) ?>" class="btn btn-outline"><i class="bi bi-telephone"></i> Call <?= e($compPhone) ?></a>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-num highlight">100–200+</div>
                <div class="stat-label">Guard Fleet Capacity</div>
            </div>
            <div class="stat-box">
                <div class="stat-num">24/7</div>
                <div class="stat-label">Live OpenStreetMap GPS Tracking</div>
            </div>
            <div class="stat-box">
                <div class="stat-num">100%</div>
                <div class="stat-label">Verified & Licensed Personnel</div>
            </div>
            <div class="stat-box">
                <div class="stat-num highlight">&lt; 15 min</div>
                <div class="stat-label">Emergency SOS Response</div>
            </div>
        </div>
    </div>
</section>

<!-- Guard Categories / Roles Section -->
<section class="section" id="services">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Specialized Security Personnel</span>
            <h2 class="section-title">Dedicated Guard Categories</h2>
            <p class="section-desc">Not every security requirement is the same. We field certified specialists trained precisely for your operating environment.</p>
        </div>

        <div class="roles-grid">
            <div class="role-card">
                <div class="role-icon amber"><i class="bi bi-person-shield"></i></div>
                <h3 class="role-title">Personal Bodyguard (VIP)</h3>
                <p class="role-desc">Close-protection officers for high-net-worth individuals, executives, dignitaries, and celebrities. Trained in threat assessment, evasive transit, and physical defense.</p>
                <div class="role-tags">
                    <span class="badge-pill">Close Protection</span>
                    <span class="badge-pill">Armed / Unarmed</span>
                    <span class="badge-pill">Executive Transit</span>
                </div>
            </div>

            <div class="role-card">
                <div class="role-icon blue"><i class="bi bi-building-lock"></i></div>
                <h3 class="role-title">Building & Facility Security</h3>
                <p class="role-desc">Round-the-clock protection for commercial towers, tech parks, gated residential communities, and retail complexes. Strict access control and CCTV monitoring.</p>
                <div class="role-tags">
                    <span class="badge-pill">Access Control</span>
                    <span class="badge-pill">Visitor Registry</span>
                    <span class="badge-pill">24/7 Gatekeeping</span>
                </div>
            </div>

            <div class="role-card">
                <div class="role-icon green"><i class="bi bi-shield-check"></i></div>
                <h3 class="role-title">Agency & Commercial Force</h3>
                <p class="role-desc">Scalable guard force deployments (up to 100 or 200+ guards) for large-scale enterprise contracts, industrial plants, warehouses, banks, and hospital chains.</p>
                <div class="role-tags">
                    <span class="badge-pill">Scalable Force (100+)</span>
                    <span class="badge-pill">Shift Management</span>
                    <span class="badge-pill">Automated Invoicing</span>
                </div>
            </div>

            <div class="role-card">
                <div class="role-icon purple"><i class="bi bi-people"></i></div>
                <h3 class="role-title">Event & Venue Security</h3>
                <p class="role-desc">High-visibility crowd management, concert security, red-carpet VIP screening, private galas, and venue perimeter control with instant conflict resolution.</p>
                <div class="role-tags">
                    <span class="badge-pill">Crowd Control</span>
                    <span class="badge-pill">Bouncer Teams</span>
                    <span class="badge-pill">Screening & Entry</span>
                </div>
            </div>

            <div class="role-card">
                <div class="role-icon red"><i class="bi bi-car-front"></i></div>
                <h3 class="role-title">Mobile Patrol & Armed Escort</h3>
                <p class="role-desc">Rapid motorized response units, routine perimeter patrols, high-value asset transit escorts, and emergency alarm response with live GPS beaconing.</p>
                <div class="role-tags">
                    <span class="badge-pill">Motorized Patrol</span>
                    <span class="badge-pill">Asset Transport</span>
                    <span class="badge-pill">Geofence Check</span>
                </div>
            </div>

            <div class="role-card">
                <div class="role-icon amber"><i class="bi bi-broadcast-pin"></i></div>
                <h3 class="role-title">Live Dispatch & Command</h3>
                <p class="role-desc">Every single guard is tracked on our OpenStreetMap command center. Clients and administrators receive real-time visibility into patrol trails and site presence.</p>
                <div class="role-tags">
                    <span class="badge-pill">OpenStreetMap</span>
                    <span class="badge-pill">Geofence Alerts</span>
                    <span class="badge-pill">SOS Panic Response</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Armed vs Unarmed Breakdown -->
<section class="section firearm-section" id="firearm-protocol">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Firearm & Equipment Protocol</span>
            <h2 class="section-title">Armed vs. Unarmed Guarding</h2>
            <p class="section-desc">Not every post requires a firearm. We clearly categorize, license, and configure guard equipment based on your operational risk level.</p>
        </div>

        <div class="firearm-grid">
            <div class="firearm-card armed">
                <span class="firearm-badge armed"><i class="bi bi-shield-fill-check"></i> Armed Security (With Gun)</span>
                <h3 class="firearm-title">Licensed Firearm Specialists</h3>
                <p class="role-desc">Trained and certified firearm operators holding valid government weapon licenses. Equipped with handguns or shotguns for high-threat, high-value protection.</p>
                <ul class="firearm-features">
                    <li><i class="bi bi-check-circle-fill"></i> Government-registered weapon license verification</li>
                    <li><i class="bi bi-check-circle-fill"></i> Tactical marksmanship and firearm retention certified</li>
                    <li><i class="bi bi-check-circle-fill"></i> High-risk VIP transit, cash escort & bullion security</li>
                    <li><i class="bi bi-check-circle-fill"></i> Strict lethal force engagement rules and background vetting</li>
                </ul>
                <div class="small text-warning mt-2"><i class="bi bi-info-circle"></i> Enabled selectively via Admin Portal toggle per client mission.</div>
            </div>

            <div class="firearm-card unarmed">
                <span class="firearm-badge unarmed"><i class="bi bi-shield"></i> Unarmed Security (No Gun)</span>
                <h3 class="firearm-title">Professional Deterrence & De-escalation</h3>
                <p class="role-desc">Ideal for corporate lobbies, residential gates, retail storefronts, and school campuses where professional physical deterrence and polite authority are required.</p>
                <ul class="firearm-features">
                    <li><i class="bi bi-check-circle-fill"></i> Conflict resolution and non-violent de-escalation mastery</li>
                    <li><i class="bi bi-check-circle-fill"></i> Front-desk concierge, visitor scanning and gatekeeping</li>
                    <li><i class="bi bi-check-circle-fill"></i> First-aid CPR and emergency evacuation trained</li>
                    <li><i class="bi bi-check-circle-fill"></i> Mobile app GPS check-in and geofence verification</li>
                </ul>
                <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Cost-effective, friendly, and non-intimidating for everyday operations.</div>
            </div>
        </div>
    </div>
</section>

<!-- Tracking Technology -->
<section class="section" id="tracking">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">24/7 Operational Visibility</span>
            <h2 class="section-title">OpenStreetMap GPS Live Tracking</h2>
            <p class="section-desc">Total transparency into your security detail. See exactly which guard is on site, their battery levels, and route history.</p>
        </div>

        <div class="tech-box">
            <div class="tech-points">
                <div class="tech-point">
                    <div class="tech-point-icon"><i class="bi bi-geo-alt-fill"></i></div>
                    <div class="tech-point-content">
                        <h4>Real-Time Coordinate Streaming</h4>
                        <p>Guard mobile apps broadcast high-precision GPS fixes directly into our OpenStreetMap tracking engine every 20 seconds during active shifts.</p>
                    </div>
                </div>

                <div class="tech-point">
                    <div class="tech-point-icon"><i class="bi bi-bounding-box"></i></div>
                    <div class="tech-point-content">
                        <h4>Automated Geofence Monitoring</h4>
                        <p>Custom site radii (50m to 500m) monitor guard presence. If an on-duty guard leaves the designated perimeter, supervisors receive instant alerts.</p>
                    </div>
                </div>

                <div class="tech-point">
                    <div class="tech-point-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
                    <div class="tech-point-content">
                        <h4>One-Tap Guard SOS Panic System</h4>
                        <p>Guards in distress trigger an emergency SOS with coordinate locks, siren alerts on admin dashboards, and rapid backup dispatch.</p>
                    </div>
                </div>
            </div>

            <div class="map-mockup">
                <div class="map-mockup-header">
                    <span><i class="bi bi-map me-1"></i> Live Deployment Map</span>
                    <span class="live-pulse">Connected</span>
                </div>
                <div class="map-guard-item">
                    <div>
                        <strong>DEMO-001 · Arjun Singh</strong>
                        <div class="text-muted small">Bodyguard · Mehra Group BKC</div>
                    </div>
                    <span class="badge-pill" style="background:#166534;color:#86efac;border-color:#22c55e">On Duty · Live</span>
                </div>
                <div class="map-guard-item">
                    <div>
                        <strong>DEMO-004 · Sanjay Patil</strong>
                        <div class="text-muted small">Building Security · Star Events</div>
                    </div>
                    <span class="badge-pill" style="background:#1e3a8a;color:#93c5fd;border-color:#3b82f6">Armed (Gun)</span>
                </div>
                <div class="map-guard-item">
                    <div>
                        <strong>DEMO-007 · Deepak Yadav</strong>
                        <div class="text-muted small">Agency Security · Horizon Tech</div>
                    </div>
                    <span class="badge-pill" style="background:#166534;color:#86efac;border-color:#22c55e">In Perimeter</span>
                </div>
                <div class="mt-3 text-center">
                    <a href="<?= url('login.php') ?>" class="btn btn-outline btn-sm w-100"><i class="bi bi-box-arrow-in-right"></i> Open Live Radar in Admin Portal</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quote / Inquiry Form -->
<section class="section" id="quote">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Rapid Guard Hiring</span>
            <h2 class="section-title">Request Security Personnel</h2>
            <p class="section-desc">Tell us your requirements and our dispatch operations center will prepare your security assignment within 15 minutes.</p>
        </div>

        <div class="inquiry-wrap">
            <?php if ($inquirySuccess): ?>
                <div class="alert-box alert-success">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                    <div>
                        <strong>Security inquiry received successfully!</strong><br>
                        Our dispatch commander will call your phone number shortly to confirm guard availability and deployment details.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($inquiryError): ?>
                <div class="alert-box alert-error">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    <div><?= e($inquiryError) ?></div>
                </div>
            <?php endif; ?>

            <form method="post" action="#quote">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="inquiry">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Your Name / Organization <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Rahul Sharma / ABC Corp" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number (Direct / WhatsApp) <span class="req">*</span></label>
                        <input type="tel" name="phone" class="form-control" placeholder="e.g. +91 98200 12345" required value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. security@company.com" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City / Deployment Location <span class="req">*</span></label>
                        <input type="text" name="city" class="form-control" placeholder="e.g. Mumbai, BKC / Pune / Delhi" required value="<?= e($_POST['city'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Security Role / Category <span class="req">*</span></label>
                        <select name="service_type" class="form-select">
                            <option value="bodyguard">Personal Bodyguard / VIP Protection</option>
                            <option value="building_security">Building & Facility Security</option>
                            <option value="agency_security">Agency Security / Commercial Guard Force</option>
                            <option value="event_security">Event & Venue Security</option>
                            <option value="patrol">Mobile Patrol & Escort</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Personnel Requirement</label>
                        <select name="is_armed" class="form-select">
                            <option value="0">Unarmed Personnel (Standard)</option>
                            <option value="1">Armed Personnel (Licensed Gun / Firearm)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Number of Guards Needed</label>
                        <select name="guards_needed" class="form-select">
                            <option value="1">1 Guard (Solo Assignment)</option>
                            <option value="2">2 - 5 Guards</option>
                            <option value="10">6 - 20 Guards</option>
                            <option value="50">20 - 50 Guards</option>
                            <option value="100">100 - 200+ Guards (Large Facility / Fleet)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deployment Type</label>
                        <select class="form-select" name="duration_type">
                            <option value="continuous">Long-Term Contract (Monthly / Annual)</option>
                            <option value="short">Short-Term Assignment (1 - 7 Days)</option>
                            <option value="event">Single Event / Shift</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.5rem">
                    <label class="form-label">Additional Instructions / Site Details</label>
                    <textarea name="message" class="form-control" rows="3" placeholder="Tell us about the site location, shift timings, threat concerns, or specific officer qualifications..."><?= e($_POST['message'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-accent w-100 py-3" style="font-size:1rem">
                    <i class="bi bi-shield-lock-fill"></i> Submit Security Request (Instant Dispatch)
                </button>
            </form>
        </div>

        <!-- Direct Dispatch Callout -->
        <div class="dispatch-bar">
            <div>
                <h3>Need immediate armed or unarmed response?</h3>
                <p>Our 24/7 Operations Desk coordinates urgent guard deployments and VIP close protection details.</p>
            </div>
            <div class="dispatch-actions">
                <a href="tel:<?= e($compPhone) ?>" class="btn btn-accent"><i class="bi bi-telephone-fill"></i> Call <?= e($compPhone) ?></a>
                <a href="https://wa.me/<?= preg_replace('/\D/', '', $compPhone) ?>" target="_blank" class="btn btn-outline"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <div class="brand mb-3">
                    <span class="brand-icon"><i class="bi bi-shield-shaded"></i></span>
                    <span><?= e($compName) ?></span>
                </div>
                <p style="margin-bottom:1rem;color:var(--text-muted)">
                    Authorized private security agency offering verified personal bodyguards, commercial building security, and large-scale guard forces with OpenStreetMap GPS dispatch.
                </p>
                <div style="font-size:0.82rem;color:var(--text-dim)">
                    <?= e($compAddress) ?>
                </div>
            </div>

            <div class="footer-col">
                <h4>Guard Categories</h4>
                <ul class="footer-links">
                    <li><a href="#services">Personal Bodyguards</a></li>
                    <li><a href="#services">Building Security</a></li>
                    <li><a href="#services">Agency Commercial Force</a></li>
                    <li><a href="#services">Event & Venue Security</a></li>
                    <li><a href="#services">Mobile Escort & Patrol</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Portals & App</h4>
                <ul class="footer-links">
                    <li><a href="<?= url('login.php') ?>"><i class="bi bi-speedometer2 me-1"></i> Admin Command Center</a></li>
                    <li><a href="<?= url('guard/login.php') ?>"><i class="bi bi-phone me-1"></i> Guard Mobile App</a></li>
                    <li><a href="<?= url('admin/tracking.php') ?>"><i class="bi bi-geo-alt me-1"></i> Live Tracking Map</a></li>
                    <li><a href="#quote"><i class="bi bi-pencil-square me-1"></i> Request a Quote</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>24/7 Operations</h4>
                <p style="margin-bottom:0.75rem;color:#cbd5e1">Emergency hotline:</p>
                <a href="tel:<?= e($compPhone) ?>" style="font-size:1.15rem;font-weight:800;color:#f59e0b;display:block;margin-bottom:0.75rem">
                    <?= e($compPhone) ?>
                </a>
                <div class="small text-muted mb-2">Direct email: <a href="mailto:<?= e($compEmail) ?>"><?= e($compEmail) ?></a></div>
                <div class="small text-muted"><i class="bi bi-patch-check-fill text-primary"></i> Licensed Security Agency</div>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; <?= date('Y') ?> <?= e($compName) ?>. All rights reserved.</div>
            <div style="display:flex;gap:1.5rem">
                <a href="<?= url('login.php') ?>">Admin Sign In</a>
                <a href="<?= url('guard/login.php') ?>">Guard App</a>
                <a href="#services">Services</a>
            </div>
        </div>
    </div>
</footer>

<script>
(function() {
    // Mobile navigation toggle
    const toggle = document.getElementById('menuToggle');
    const links = document.getElementById('navLinks');
    if (toggle && links) {
        toggle.addEventListener('click', () => {
            links.classList.toggle('open');
        });
        document.querySelectorAll('.js-nav').forEach(a => {
            a.addEventListener('click', () => links.classList.remove('open'));
        });
    }
})();
</script>

</body>
</html>