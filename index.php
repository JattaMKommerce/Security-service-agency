<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_file(APP_ROOT . '/storage/installed.lock') && is_file(APP_ROOT . '/install.php')) {
    redirect('install.php');
}

$inquirySuccess = false;
$inquiryError = null;

if (is_post() && ($_POST['action'] ?? '') === 'inquiry') {
    require_csrf();
    $name     = post_str('name', 120);
    $phone    = post_str('phone', 30);
    $service  = post_str('service_type', 50) ?? 'bodyguard';
    $armed    = !empty($_POST['is_armed']) ? 1 : 0;
    $city     = post_str('city', 80);

    if (!$name || strlen($name) < 2) {
        $inquiryError = 'Please enter your name.';
    } elseif (!$phone || strlen($phone) < 8) {
        $inquiryError = 'Please enter your phone number.';
    } else {
        try {
            q('INSERT INTO inquiries (name, phone, service_type, is_armed, city, status)
               VALUES (?, ?, ?, ?, ?, "new")',
              [$name, $phone, $service, $armed, $city]);
            log_activity('website.inquiry', "Quick quote from $name ($phone) for $service", 'system');
            $inquirySuccess = true;
        } catch (Throwable $e) {
            $inquiryError = 'Could not send right now. Please call or WhatsApp us directly.';
        }
    }
}

$admin = current_admin();
$compName = company_name();
$compPhone = setting('company_phone', '+91 98200 12345');
$phoneClean = preg_replace('/\D/', '', $compPhone);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($compName) ?> · Elite Security & Bodyguard Protection</title>
    <meta name="description" content="Professional, well-trained security guards and VIP bodyguards. Dedicated unarmed officers and statutorily licensed armed personnel for corporate, residential, and private protection.">
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-white: #ffffff;
            --bg-subtle: #f8fafc;
            --bg-card: #ffffff;
            --border-subtle: #e2e8f0;
            --border-hover: #cbd5e1;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --accent-navy: #0f172a;
            --accent-blue: #0284c7;
            --accent-whatsapp: #16a34a;
            --accent-whatsapp-hover: #15803d;
            --accent-gold: #b45309;
            --radius-sm: 10px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.04), 0 6px 16px rgba(0, 0, 0, 0.03);
            --shadow-hover: 0 12px 30px -4px rgba(0, 0, 0, 0.08), 0 4px 12px rgba(0, 0, 0, 0.03);
            /* Apple iOS System Font */
            --font-ios: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-ios);
            background-color: var(--bg-white);
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }

        /* Admin indicator */
        .admin-bar {
            background: #0f172a;
            color: #f8fafc;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            text-align: center;
            font-weight: 500;
        }
        .admin-bar a { color: #38bdf8; font-weight: 600; text-decoration: underline; margin-left: 0.5rem; }

        /* Classy iOS Navbar */
        .navbar {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-subtle);
            padding: 0.85rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--text-primary);
        }
        .brand-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #ffffff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.15);
        }
        .brand-title {
            display: flex;
            flex-direction: column;
            line-height: 1.15;
        }
        .brand-title strong { font-size: 1.15rem; }
        .brand-title span {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }

        /* Classy iOS Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            font-family: var(--font-ios);
            font-size: 0.92rem;
            font-weight: 600;
            padding: 0.65rem 1.15rem;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            cursor: pointer;
            text-align: center;
            white-space: nowrap;
            letter-spacing: -0.01em;
            transition: all 0.18s ease;
        }
        .btn-whatsapp {
            background: var(--accent-whatsapp);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
        }
        .btn-whatsapp:hover {
            background: var(--accent-whatsapp-hover);
            transform: translateY(-1px);
        }
        .btn-navy {
            background: var(--accent-navy);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }
        .btn-navy:hover {
            background: #1e293b;
            transform: translateY(-1px);
        }
        .btn-outline {
            background: #ffffff;
            border-color: var(--border-subtle);
            color: var(--text-primary);
        }
        .btn-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .btn-lg {
            padding: 0.85rem 1.6rem;
            font-size: 1rem;
            border-radius: 12px;
            font-weight: 600;
        }
        .btn-sm {
            padding: 0.45rem 0.85rem;
            font-size: 0.84rem;
        }
        .discreet-login {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 500;
            text-decoration: none;
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            transition: color 0.15s ease, background 0.15s ease;
        }
        .discreet-login:hover {
            color: var(--text-primary);
            background: #f1f5f9;
        }

        /* Classy Hero Section */
        .hero {
            padding: 4.5rem 0 3.5rem;
            background: radial-gradient(circle at 50% 0%, #f8fafc 0%, #ffffff 80%);
            border-bottom: 1px solid var(--border-subtle);
        }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 3.5rem;
            align-items: center;
        }
        .ios-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #f1f5f9;
            color: #0f172a;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.4rem 0.9rem;
            border-radius: 9999px;
            margin-bottom: 1.25rem;
            border: 1px solid #e2e8f0;
            letter-spacing: -0.01em;
        }
        .ios-badge i { color: #0284c7; }
        .hero-title {
            font-size: clamp(2.3rem, 4.5vw, 3.4rem);
            font-weight: 800;
            line-height: 1.14;
            color: var(--text-primary);
            letter-spacing: -0.035em;
            margin-bottom: 1.25rem;
        }
        .hero-desc {
            font-size: 1.15rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.55;
            letter-spacing: -0.01em;
        }
        .hero-actions {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }

        /* Hero Image Showcase */
        .hero-visual {
            position: relative;
        }
        .hero-photo-wrap {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(226, 232, 240, 0.8);
            background: #ffffff;
        }
        .hero-photo-wrap img {
            width: 100%;
            height: 440px;
            object-fit: cover;
            display: block;
        }
        .floating-safety-card {
            position: absolute;
            bottom: -1.25rem;
            left: 1.25rem;
            right: 1.25rem;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.1);
        }
        .floating-safety-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #ecfdf5;
            color: var(--accent-whatsapp);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .floating-safety-text strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .floating-safety-text span {
            font-size: 0.82rem;
            color: var(--text-secondary);
        }

        /* Trust Strip */
        .trust-strip {
            padding: 2.25rem 0;
            background: #ffffff;
            border-bottom: 1px solid var(--border-subtle);
        }
        .trust-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }
        .trust-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .trust-item h4 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.01em;
            line-height: 1.2;
        }
        .trust-item p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 0.15rem;
        }

        /* Section Layout */
        .section {
            padding: 4.5rem 0;
            border-bottom: 1px solid var(--border-subtle);
        }
        .section-alt {
            background: var(--bg-subtle);
        }
        .section-head {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 3rem;
        }
        .section-tag {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--accent-blue);
            margin-bottom: 0.5rem;
            display: block;
        }
        .section-title {
            font-size: clamp(1.8rem, 3.5vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-primary);
            line-height: 1.2;
            margin-bottom: 0.65rem;
        }
        .section-desc {
            font-size: 1.05rem;
            color: var(--text-secondary);
            line-height: 1.55;
        }

        /* Classy Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }
        .service-card {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-subtle);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        .service-img-wrap {
            position: relative;
            overflow: hidden;
            height: 220px;
        }
        .service-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }
        .service-card:hover .service-img-wrap img {
            transform: scale(1.04);
        }
        .service-badge {
            position: absolute;
            top: 0.85rem;
            left: 0.85rem;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            letter-spacing: -0.01em;
        }
        .service-body {
            padding: 1.35rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .service-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            margin-bottom: 0.45rem;
        }
        .service-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.55;
            margin-bottom: 1.25rem;
            flex-grow: 1;
        }
        .service-footer {
            border-top: 1px solid var(--border-subtle);
            padding-top: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--accent-navy);
        }

        /* Classy Armed vs Unarmed Box */
        .weapon-box {
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            box-shadow: var(--shadow-subtle);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        .weapon-card {
            border-radius: var(--radius-md);
            padding: 2rem;
            border: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            background: #ffffff;
        }
        .weapon-card.armed {
            border-color: #fde68a;
            background: linear-gradient(180deg, #fffbeb 0%, #ffffff 100%);
        }
        .weapon-card.unarmed {
            border-color: #bae6fd;
            background: linear-gradient(180deg, #f0f9ff 0%, #ffffff 100%);
        }
        .weapon-card-tag {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .weapon-card.armed .weapon-card-tag { color: var(--accent-gold); }
        .weapon-card.unarmed .weapon-card-tag { color: var(--accent-blue); }
        .weapon-card h3 {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 0.6rem;
        }
        .weapon-card p {
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.55;
            margin-bottom: 1.25rem;
        }
        .weapon-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            font-size: 0.88rem;
            color: #334155;
            margin-top: auto;
        }
        .weapon-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .weapon-card.armed .weapon-list li i { color: var(--accent-gold); }
        .weapon-card.unarmed .weapon-list li i { color: var(--accent-blue); }

        /* Classy Quick Booking Form */
        .booking-card {
            max-width: 680px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.06);
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
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
            letter-spacing: -0.01em;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            font-family: var(--font-ios);
            font-size: 0.95rem;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            outline: none;
            transition: all 0.15s ease;
        }
        .form-control:focus, .form-select:focus {
            background: #ffffff;
            border-color: var(--accent-navy);
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
        }
        .alert-success {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 1rem 1.25rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 1rem 1.25rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
        }

        /* Dispatch Action Strip */
        .action-strip {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #ffffff;
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            margin-top: 2.5rem;
            box-shadow: 0 20px 35px -5px rgba(15, 23, 42, 0.25);
        }
        .action-strip h3 {
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.35rem;
        }
        .action-strip p {
            font-size: 0.95rem;
            color: #cbd5e1;
            line-height: 1.5;
        }
        .action-buttons {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        /* Classy Footer */
        .footer {
            background: #ffffff;
            border-top: 1px solid var(--border-subtle);
            padding: 3rem 0 1.75rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--border-subtle);
            margin-bottom: 1.5rem;
        }
        .footer-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            list-style: none;
            font-weight: 600;
            color: var(--text-primary);
        }
        .footer-links a:hover { color: var(--accent-blue); }
        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.85rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-grid { grid-template-columns: 1fr; gap: 2.5rem; }
            .hero-photo-wrap img { height: 340px; }
            .trust-grid { grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
            .services-grid { grid-template-columns: repeat(2, 1fr); }
            .weapon-box { grid-template-columns: 1fr; }
            .action-strip { flex-direction: column; text-align: center; }
            .action-buttons { justify-content: center; width: 100%; }
        }

        @media (max-width: 600px) {
            .hero { padding: 3rem 0 2rem; }
            .hero-title { font-size: 2.2rem; }
            .services-grid { grid-template-columns: 1fr; }
            .trust-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; gap: 0.85rem; }
            .hero-actions { flex-direction: column; width: 100%; }
            .hero-actions .btn { width: 100%; }
            .action-buttons .btn { width: 100%; }
            .nav-actions { width: 100%; justify-content: space-between; }
            .footer-inner { flex-direction: column; text-align: center; }
            .footer-links { flex-direction: column; gap: 0.85rem; }
            .footer-bottom { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<?php if ($admin): ?>
    <div class="admin-bar">
        <i class="bi bi-shield-lock-fill"></i> Logged in as <strong><?= e($admin['name']) ?></strong>
        <a href="<?= url('admin/index.php') ?>">Open Admin Command Center &rarr;</a>
    </div>
<?php endif; ?>

<!-- Classy iOS Navigation -->
<header class="navbar">
    <div class="container nav-inner">
        <a href="<?= url('index.php') ?>" class="brand">
            <span class="brand-icon"><i class="bi bi-shield-shaded"></i></span>
            <div class="brand-title">
                <strong><?= e($compName) ?></strong>
                <span>Certified Protection</span>
            </div>
        </a>

        <div class="nav-actions">
            <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn btn-whatsapp btn-sm">
                <i class="bi bi-whatsapp"></i> WhatsApp Us
            </a>
            <a href="tel:<?= e($compPhone) ?>" class="btn btn-navy btn-sm">
                <i class="bi bi-telephone-fill"></i> Call <?= e($compPhone) ?>
            </a>
            <a href="#hire" class="btn btn-outline btn-sm">
                <i class="bi bi-shield-check"></i> Request Guard
            </a>
            <a href="<?= url('login.php') ?>" class="discreet-login" title="Staff & Admin Portal">
                <i class="bi bi-lock-fill"></i> Sign In
            </a>
        </div>
    </div>
</header>

<!-- Classy Hero Section with Well-Trained Security Guard Picture -->
<section class="hero">
    <div class="container hero-grid">
        <div>
            <div class="ios-badge">
                <i class="bi bi-shield-check"></i>
                <span>Professional Security & Guard Services</span>
            </div>
            <h1 class="hero-title">
                Well-Trained Security Guards & VIP Bodyguard Protection
            </h1>
            <p class="hero-desc">
                Reliable security protection for your premises, corporate facility, executive detail, or private event. Disciplined unarmed guarding officers and statutorily licensed armed security personnel deployed with continuous field supervision.
            </p>

            <div class="hero-actions">
                <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn btn-whatsapp btn-lg">
                    <i class="bi bi-whatsapp"></i> Instant WhatsApp Quote
                </a>
                <a href="tel:<?= e($compPhone) ?>" class="btn btn-navy btn-lg">
                    <i class="bi bi-telephone-fill"></i> Direct Hotline: <?= e($compPhone) ?>
                </a>
                <a href="#hire" class="btn btn-outline btn-lg">
                    <i class="bi bi-shield-check"></i> Request a Guard
                </a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-photo-wrap">
                <!-- Commanding uniformed security officer with walkie-talkie on site -->
                <img src="<?= asset('img/guards/hero-guard-sunglasses.jpg') ?>" alt="Commanding uniformed security officer on active watch">
            </div>
            <div class="floating-safety-card">
                <div class="floating-safety-icon">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div class="floating-safety-text">
                    <strong>Trained & Disciplined Personnel</strong>
                    <span>Continuous Supervision & 24/7 Operations Room</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust & Standards Strip -->
<div class="trust-strip">
    <div class="container trust-grid">
        <div class="trust-item">
            <div class="trust-icon"><i class="bi bi-shield-check"></i></div>
            <div>
                <h4>Background Checked</h4>
                <p>Structured identity & reference checks</p>
            </div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <div>
                <h4>Armed & Unarmed Options</h4>
                <p>Statutorily licensed protection available</p>
            </div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="bi bi-geo-alt-fill"></i></div>
            <div>
                <h4>Active Shift Oversight</h4>
                <p>Verified duty check-ins & reporting</p>
            </div>
        </div>
        <div class="trust-item">
            <div class="trust-icon"><i class="bi bi-headset"></i></div>
            <div>
                <h4>24/7 Operations Desk</h4>
                <p>Continuous support & active coordination</p>
            </div>
        </div>
    </div>
</div>

<!-- Classy Services Grid with Pictures of Guards & Bodyguards -->
<section class="section section-alt" id="services">
    <div class="container">
        <div class="section-head">
            <span class="section-tag">Dedicated Security Units</span>
            <h2 class="section-title">Security Personnel Built For Your Safety</h2>
            <p class="section-desc">From executive close-protection to round-the-clock perimeter guarding, every officer is trained for maximum vigilance.</p>
        </div>

        <div class="services-grid">
            <!-- 1. VIP Bodyguard -->
            <div class="service-card">
                <div class="service-img-wrap">
                    <span class="service-badge"><i class="bi bi-person-fill-shield me-1"></i> Close Protection</span>
                    <!-- VIP Close Protection Bodyguards in dark suits -->
                    <img src="<?= asset('img/guards/vip-bodyguard-team.jpg') ?>" alt="Close Protection VIP Bodyguards in suits">
                </div>
                <div class="service-body">
                    <h3 class="service-title">VIP Bodyguard</h3>
                    <p class="service-text">Discreet executive protection for high-profile clients, dignitaries, and families. Available in sharp suit or tactical attire, armed or unarmed.</p>
                    <div class="service-footer">
                        <span>Personal Safety</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Facility & Gatekeeping -->
            <div class="service-card">
                <div class="service-img-wrap">
                    <span class="service-badge"><i class="bi bi-building-lock me-1"></i> 24/7 Gatekeeping</span>
                    <!-- Uniformed security officer at residential gate and boom barrier cabin -->
                    <img src="<?= asset('img/guards/residential-gate-guard.jpg') ?>" alt="Trained security officer at residential boom barrier entrance">
                </div>
                <div class="service-body">
                    <h3 class="service-title">Facility & Gatekeeping</h3>
                    <p class="service-text">Round-the-clock manned security and gatekeeping for residential complexes, corporate campuses, gated communities, and tech parks. Visitor logging and barrier management.</p>
                    <div class="service-footer">
                        <span>Facility Guarding</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Event Security & Access Screening -->
            <div class="service-card">
                <div class="service-img-wrap">
                    <span class="service-badge"><i class="bi bi-shield-check me-1"></i> Entry Screening</span>
                    <!-- Security officer conducting metal detector screening -->
                    <img src="<?= asset('img/guards/guard-scanner-screening.jpg') ?>" alt="Security officer conducting handheld metal detector screening">
                </div>
                <div class="service-body">
                    <h3 class="service-title">Event & Screening</h3>
                    <p class="service-text">Robust entry screening with handheld metal detectors, visitor registers, and crowd control for galas, weddings, private exhibitions, and corporate venues.</p>
                    <div class="service-footer">
                        <span>Events & Screening</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>

            <!-- 4. Agency Security Force -->
            <div class="service-card">
                <div class="service-img-wrap">
                    <span class="service-badge"><i class="bi bi-buildings-fill me-1"></i> Corporate Force</span>
                    <!-- Uniformed security officer at Eminence Corporate Park entrance -->
                    <img src="<?= asset('img/guards/corporate-park-guard.jpg') ?>" alt="Uniformed security officer at corporate park entrance">
                </div>
                <div class="service-body">
                    <h3 class="service-title">Agency Security Force</h3>
                    <p class="service-text">Comprehensive security personnel teams for commercial business parks, industrial facilities, healthcare centers, and multi-entrance properties with centralized supervisor oversight.</p>
                    <div class="service-footer">
                        <span>Corporate Force</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Security & Protection Protocols -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="section-tag">Security & Protection Protocols</span>
            <h2 class="section-title">Unarmed Guarding & Licensed Armed Security</h2>
            <p class="section-desc">Services are configured based on your operational environment. Armed protection is provided exclusively by personnel holding valid, verified statutory arms licenses in compliance with governing laws.</p>
        </div>

        <div class="weapon-box">
            <div class="weapon-card unarmed">
                <div class="weapon-card-tag">
                    <i class="bi bi-shield-check"></i> Standard Security
                </div>
                <h3>Trained Unarmed Officers</h3>
                <p>The standard choice for corporate offices, apartment societies, retail showrooms, and routine access control. Focus on de-escalation, visitor screening, and perimeter presence.</p>
                <ul class="weapon-list">
                    <li><i class="bi bi-check2-circle"></i> Conflict de-escalation & situational awareness</li>
                    <li><i class="bi bi-check2-circle"></i> Gatekeeping, visitor registers & access surveillance</li>
                    <li><i class="bi bi-check2-circle"></i> Emergency evacuation & first response trained</li>
                    <li><i class="bi bi-check2-circle"></i> Digital shift check-in & verified reporting</li>
                </ul>
            </div>

            <div class="weapon-card armed">
                <div class="weapon-card-tag">
                    <i class="bi bi-shield-lock-fill"></i> Statutorily Licensed
                </div>
                <h3>Licensed Armed Security Personnel</h3>
                <p>Armed security personnel deployed exclusively for high-risk assets, executive escorts, and secure transit, operating strictly with verified, government-issued arms licenses in compliance with statutory regulations.</p>
                <ul class="weapon-list">
                    <li><i class="bi bi-check2-circle"></i> Deployed strictly with valid, verified statutory arms licenses</li>
                    <li><i class="bi bi-check2-circle"></i> Firearm safety, retention, and legal compliance trained</li>
                    <li><i class="bi bi-check2-circle"></i> Executive escort & high-value transit protection</li>
                    <li><i class="bi bi-check2-circle"></i> Subject to internal command verification and authorization</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- Classy Booking / Quote Request Form -->
<section class="section section-alt" id="hire">
    <div class="container">
        <div class="section-head">
            <span class="section-tag">Direct Guard Dispatch</span>
            <h2 class="section-title">Request Your Security Detail</h2>
            <p class="section-desc">Submit your requirement below. Our operations team will call you back promptly to arrange your security deployment.</p>
        </div>

        <div class="booking-card">
            <?php if ($inquirySuccess): ?>
                <div class="alert-success">
                    <i class="bi bi-check-circle-fill me-1"></i> Thank you! Your security request has been received. Our operations team will call you within 10 minutes.
                </div>
            <?php endif; ?>

            <?php if ($inquiryError): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($inquiryError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="#hire">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="inquiry">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name / Organization</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Vikram Malhotra" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="tel" name="phone" class="form-control" placeholder="e.g. +91 98200 12345" required value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Security Category</label>
                        <select name="service_type" class="form-select">
                            <option value="bodyguard">Personal VIP Bodyguard</option>
                            <option value="building_security">Facility & Gated Society Security</option>
                            <option value="event_security">Event Security & Screening</option>
                            <option value="agency_security">Agency Security Force (Team Deployment)</option>
                            <option value="patrol">Mobile Patrol & Escort</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Weapon Protocol</label>
                        <select name="is_armed" class="form-select">
                            <option value="0">Unarmed Security (Standard)</option>
                            <option value="1">Licensed Armed Security (Subject to statutory licensing)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:1.5rem">
                    <label class="form-label">City / Deployment Area</label>
                    <input type="text" name="city" class="form-control" placeholder="e.g. Bandra Kurla Complex, Mumbai" value="<?= e($_POST['city'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-navy btn-lg w-100">
                    <i class="bi bi-shield-check"></i> Request Security Guard (Instant Callback)
                </button>
            </form>
        </div>

        <!-- Direct Dispatch Strip -->
        <div class="action-strip">
            <div>
                <h3>Need immediate emergency deployment?</h3>
                <p>Our 24/7 Operations Control Room coordinates urgent bodyguards and manned guarding across all locations.</p>
            </div>
            <div class="action-buttons">
                <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn btn-whatsapp btn-lg">
                    <i class="bi bi-whatsapp"></i> WhatsApp Dispatch
                </a>
                <a href="tel:<?= e($compPhone) ?>" class="btn btn-outline btn-lg">
                    <i class="bi bi-telephone-fill"></i> Direct Hotline: <?= e($compPhone) ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Classy Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-inner">
            <div class="brand">
                <span class="brand-icon"><i class="bi bi-shield-shaded"></i></span>
                <div class="brand-title">
                    <strong><?= e($compName) ?></strong>
                    <span>Private Security & Close Protection</span>
                </div>
            </div>

            <ul class="footer-links">
                <li><a href="tel:<?= e($compPhone) ?>"><i class="bi bi-telephone me-1"></i> <?= e($compPhone) ?></a></li>
                <li><a href="https://wa.me/<?= $phoneClean ?>" target="_blank"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a></li>
                <li><a href="<?= url('login.php') ?>"><i class="bi bi-shield-lock me-1"></i> Staff & Admin Command Center</a></li>
                <li><a href="<?= url('guard/login.php') ?>"><i class="bi bi-phone me-1"></i> Guard Mobile App</a></li>
                <li><a href="<?= url('guard/install.php') ?>"><i class="bi bi-download me-1"></i> Mobile App Guide</a></li>
            </ul>
        </div>

        <div class="footer-bottom">
            <div>&copy; <?= date('Y') ?> <?= e($compName) ?>. All rights reserved. Professional Security & Protection Services.</div>
            <div>Dedicated On-Site Security & Close Protection Services</div>
        </div>
    </div>
</footer>

</body>
</html>
