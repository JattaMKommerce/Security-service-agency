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
    <title><?= e($compName) ?> · Security Guards & Bodyguards</title>
    <meta name="description" content="Hire trained security guards and bodyguards. Available with gun or without gun. Fast deployment and 24/7 service.">
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-white: #ffffff;
            --bg-light: #f8fafc;
            --bg-card: #ffffff;
            --text-dark: #0f172a;
            --text-body: #334155;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --whatsapp: #16a34a;
            --whatsapp-hover: #15803d;
            --amber: #d97706;
            --font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            background-color: var(--bg-white);
            color: var(--text-dark);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }

        /* Top Admin Bar if logged in */
        .admin-bar {
            background: #1e293b;
            color: #f1f5f9;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            text-align: center;
        }
        .admin-bar a { color: #60a5fa; font-weight: 600; text-decoration: underline; margin-left: 0.5rem; }

        /* Basic Clean Navbar */
        .navbar {
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            padding: 0.9rem 0;
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
            gap: 0.6rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
        }
        .brand-icon {
            width: 38px;
            height: 38px;
            background: var(--primary);
            color: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            font-family: var(--font);
            font-size: 0.92rem;
            font-weight: 700;
            padding: 0.65rem 1.2rem;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            text-align: center;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .btn-whatsapp {
            background: var(--whatsapp);
            color: #ffffff;
        }
        .btn-whatsapp:hover { background: var(--whatsapp-hover); }
        .btn-call {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-call:hover { background: var(--primary-hover); }
        .btn-outline {
            background: #ffffff;
            border-color: #cbd5e1;
            color: var(--text-dark);
        }
        .btn-outline:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        .btn-lg {
            padding: 0.9rem 1.6rem;
            font-size: 1.05rem;
            border-radius: 10px;
        }
        .btn-sm {
            padding: 0.45rem 0.85rem;
            font-size: 0.82rem;
            border-radius: 6px;
        }

        /* Hero Section */
        .hero {
            padding: 3.5rem 0 2.5rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid var(--border);
        }
        .hero-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 2.5rem;
            align-items: center;
        }
        .trust-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #ecfdf5;
            color: #065f46;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 0.35rem 0.8rem;
            border-radius: 9999px;
            margin-bottom: 1.25rem;
            border: 1px solid #a7f3d0;
        }
        .hero-title {
            font-size: clamp(2rem, 4.5vw, 3rem);
            font-weight: 800;
            line-height: 1.2;
            color: #0f172a;
            margin-bottom: 1rem;
            letter-spacing: -0.02em;
        }
        .hero-desc {
            font-size: 1.1rem;
            color: var(--text-body);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .hero-buttons {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .hero-card {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            position: relative;
        }
        .hero-card img {
            width: 100%;
            height: 380px;
            object-fit: cover;
        }
        .hero-card-badge {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            padding: 0.85rem 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .hero-card-badge i {
            font-size: 1.6rem;
            color: var(--whatsapp);
        }
        .hero-card-badge strong {
            display: block;
            font-size: 0.92rem;
            color: #0f172a;
        }
        .hero-card-badge span {
            font-size: 0.78rem;
            color: #64748b;
        }

        /* 3 Quick Facts */
        .facts-strip {
            padding: 1.5rem 0;
            background: #ffffff;
            border-bottom: 1px solid var(--border);
        }
        .facts-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            text-align: center;
        }
        .fact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
        }
        .fact-item i {
            font-size: 1.4rem;
            color: var(--primary);
        }

        /* Section Styling */
        .section {
            padding: 3.5rem 0;
            border-bottom: 1px solid var(--border);
        }
        .section.light { background: var(--bg-light); }
        .section-header {
            text-align: center;
            max-width: 650px;
            margin: 0 auto 2.5rem;
        }
        .section-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }
        .section-desc {
            font-size: 1rem;
            color: var(--text-muted);
        }

        /* Services Grid (Happy Human Photos) */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
        }
        .service-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
        }
        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }
        .service-img {
            width: 100%;
            height: 190px;
            object-fit: cover;
        }
        .service-body {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .service-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.4rem;
        }
        .service-text {
            font-size: 0.88rem;
            color: var(--text-body);
            line-height: 1.5;
            margin-bottom: 1rem;
            flex-grow: 1;
        }
        .service-badge {
            align-self: flex-start;
            font-size: 0.75rem;
            font-weight: 700;
            background: #eff6ff;
            color: var(--primary);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
        }

        /* Gun vs No Gun Banner */
        .gun-choice-box {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        .gun-option {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .gun-option.armed {
            border-color: #fcd34d;
            background: #fffbeb;
        }
        .gun-option h3 {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .gun-option p {
            font-size: 0.92rem;
            color: var(--text-body);
            line-height: 1.5;
        }

        /* Booking Form */
        .booking-box {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.4rem;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            font-family: var(--font);
            font-size: 0.95rem;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            color: #0f172a;
            outline: none;
            background: #ffffff;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
        }
        .alert-success {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #ef4444;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* Call/WhatsApp Contact Strip */
        .callout-strip {
            background: #0f172a;
            color: #ffffff;
            border-radius: 14px;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .callout-strip h3 {
            font-size: 1.35rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }
        .callout-strip p {
            font-size: 0.92rem;
            color: #cbd5e1;
        }
        .callout-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Basic Footer */
        .footer {
            background: #ffffff;
            border-top: 1px solid var(--border);
            padding: 2.5rem 0 1.5rem;
            font-size: 0.88rem;
            color: var(--text-muted);
        }
        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        .footer-links {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            list-style: none;
            font-weight: 600;
            color: #334155;
        }
        .footer-links a:hover { color: var(--primary); }
        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.82rem;
        }

        /* Mobile Responsive */
        @media (max-width: 900px) {
            .hero-grid { grid-template-columns: 1fr; }
            .hero-card img { height: 280px; }
            .services-grid { grid-template-columns: repeat(2, 1fr); }
            .gun-choice-box { grid-template-columns: 1fr; }
            .facts-grid { grid-template-columns: 1fr; gap: 0.75rem; }
            .callout-strip { flex-direction: column; text-align: center; }
            .callout-actions { justify-content: center; width: 100%; }
            .callout-actions .btn { width: 100%; }
        }

        @media (max-width: 600px) {
            .services-grid { grid-template-columns: 1fr; }
            .hero-buttons { flex-direction: column; width: 100%; }
            .hero-buttons .btn { width: 100%; }
            .nav-actions { width: 100%; justify-content: space-between; }
            .footer-inner { flex-direction: column; text-align: center; }
            .footer-links { flex-direction: column; gap: 0.75rem; }
            .footer-bottom { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<?php if ($admin): ?>
    <div class="admin-bar">
        <i class="bi bi-shield-lock-fill"></i> Logged in as <strong><?= e($admin['name']) ?></strong>
        <a href="<?= url('admin/index.php') ?>">Go to Admin Command Center &rarr;</a>
    </div>
<?php endif; ?>

<!-- Basic Navbar -->
<header class="navbar">
    <div class="container nav-inner">
        <a href="<?= url('index.php') ?>" class="brand">
            <span class="brand-icon"><i class="bi bi-shield-shaded"></i></span>
            <span><?= e($compName) ?></span>
        </a>

        <div class="nav-actions">
            <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn btn-whatsapp btn-sm">
                <i class="bi bi-whatsapp"></i> WhatsApp
            </a>
            <a href="tel:<?= e($compPhone) ?>" class="btn btn-call btn-sm">
                <i class="bi bi-telephone-fill"></i> Call Now
            </a>
            <a href="<?= url('login.php') ?>" class="btn btn-outline btn-sm">
                <i class="bi bi-lock-fill"></i> Admin Login
            </a>
        </div>
    </div>
</header>

<!-- Hero Section with Happy Human Security Guard Picture -->
<section class="hero">
    <div class="container hero-grid">
        <div>
            <div class="trust-tag">
                <i class="bi bi-check-circle-fill"></i> Police Verified & Certified Guards
            </div>
            <h1 class="hero-title">
                Reliable Security Guards & Bodyguards For You
            </h1>
            <p class="hero-desc">
                Need security for your building, shop, event, or personal bodyguard? We have 100+ trained, polite, and strong security personnel ready 24/7. Available with gun or without gun.
            </p>

            <div class="hero-buttons">
                <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn btn-whatsapp btn-lg">
                    <i class="bi bi-whatsapp"></i> Chat on WhatsApp
                </a>
                <a href="tel:<?= e($compPhone) ?>" class="btn btn-call btn-lg">
                    <i class="bi bi-telephone-fill"></i> Call <?= e($compPhone) ?>
                </a>
                <a href="#book" class="btn btn-outline btn-lg">
                    <i class="bi bi-pencil-square"></i> Book a Guard
                </a>
            </div>
        </div>

        <div class="hero-card">
            <!-- Confident, smiling human security guard -->
            <img src="https://images.unsplash.com/photo-1541872703-74c5e44368f9?auto=format&fit=crop&w=800&q=80" alt="Professional friendly security guard on duty">
            <div class="hero-card-badge">
                <i class="bi bi-patch-check-fill"></i>
                <div>
                    <strong>100% Background Checked Guards</strong>
                    <span>Live GPS Tracking • 24/7 Fast Help</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Quick Facts -->
<div class="facts-strip">
    <div class="container facts-grid">
        <div class="fact-item">
            <i class="bi bi-people-fill"></i>
            <span>1 to 200+ Guards Available</span>
        </div>
        <div class="fact-item">
            <i class="bi bi-geo-alt-fill"></i>
            <span>Live Map GPS Tracking</span>
        </div>
        <div class="fact-item">
            <i class="bi bi-clock-fill"></i>
            <span>Ready Within Minutes</span>
        </div>
    </div>
</div>

<!-- Simple Services with Happy Human Photos -->
<section class="section light" id="services">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">What Security Do You Need?</h2>
            <p class="section-desc">Pick the security service that fits your requirement. All guards are disciplined, attentive, and polite.</p>
        </div>

        <div class="services-grid">
            <!-- 1. VIP Bodyguard -->
            <div class="service-card">
                <img class="service-img" src="https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=600&q=80" alt="Personal VIP bodyguard in suit">
                <div class="service-body">
                    <h3 class="service-title">VIP Bodyguard</h3>
                    <p class="service-text">Personal safety for you and your family. In suit or black uniform. Trained in personal defense and travel safety.</p>
                    <span class="service-badge">Personal Safety</span>
                </div>
            </div>

            <!-- 2. Building Security -->
            <div class="service-card">
                <img class="service-img" src="https://images.unsplash.com/photo-1577962917302-cd874c4e31d2?auto=format&fit=crop&w=600&q=80" alt="Friendly building security guard greeting people">
                <div class="service-body">
                    <h3 class="service-title">Building Security</h3>
                    <p class="service-text">Gatekeepers for apartments, offices, shops, and societies. Friendly with residents, strict with strangers.</p>
                    <span class="service-badge">Apartment & Office</span>
                </div>
            </div>

            <!-- 3. Event Security -->
            <div class="service-card">
                <img class="service-img" src="https://images.unsplash.com/photo-1582139329536-e7284fece509?auto=format&fit=crop&w=600&q=80" alt="Event security guard bouncer team">
                <div class="service-body">
                    <h3 class="service-title">Event & Bouncers</h3>
                    <p class="service-text">Strong bouncers and guards for weddings, parties, celebrations, and gatherings. Keeps crowds peaceful.</p>
                    <span class="service-badge">Events & Parties</span>
                </div>
            </div>

            <!-- 4. Agency Guard Force -->
            <div class="service-card">
                <img class="service-img" src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=600&q=80" alt="Happy clients and security team">
                <div class="service-body">
                    <h3 class="service-title">Agency Guard Force</h3>
                    <p class="service-text">10 to 100+ guards for factories, hospitals, schools, and large company campuses. Complete shift management.</p>
                    <span class="service-badge">Large Fleet (100+)</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Gun or No Gun Choice -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Gun or Without Gun? You Choose</h2>
            <p class="section-desc">Not all guards have guns. You can choose exactly what your site needs.</p>
        </div>

        <div class="gun-choice-box">
            <div class="gun-option">
                <h3><i class="bi bi-shield-check text-primary"></i> Guard Without Gun (Normal)</h3>
                <p>
                    Most common choice. Polite and attentive guards for gates, offices, homes, and events. They check visitors, watch gates, and keep everyone safe peacefully.
                </p>
            </div>

            <div class="gun-option armed">
                <h3><i class="bi bi-shield-fill-check text-warning"></i> Guard With Gun (Armed)</h3>
                <p>
                    Armed guards with official government gun licenses. Recommended for VIP security, cash transport, jewelry shops, or high-security areas.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Simple Booking Form -->
<section class="section light" id="book">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Need a Guard? Send Your Details</h2>
            <p class="section-desc">Enter your name and number below. Our team will call you within 10 minutes to help you.</p>
        </div>

        <div class="booking-box">
            <?php if ($inquirySuccess): ?>
                <div class="alert-success">
                    <i class="bi bi-check-circle-fill me-1"></i> Thank you! We received your request. We will call you within 10 minutes.
                </div>
            <?php endif; ?>

            <?php if ($inquiryError): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($inquiryError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="#book">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="inquiry">

                <div class="form-group">
                    <label class="form-label">Your Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Ramesh Kumar" required value="<?= e($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Mobile Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="e.g. 98200 12345" required value="<?= e($_POST['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Which Guard Do You Need?</label>
                    <select name="service_type" class="form-select">
                        <option value="bodyguard">Personal Bodyguard (VIP)</option>
                        <option value="building_security">Building & Society Security</option>
                        <option value="event_security">Event Security & Bouncers</option>
                        <option value="agency_security">Factory / Office Security (Team)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Do You Need a Gun?</label>
                    <select name="is_armed" class="form-select">
                        <option value="0">No (Without Gun - Normal)</option>
                        <option value="1">Yes (With Gun - Armed)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">City / Area</label>
                    <input type="text" name="city" class="form-control" placeholder="e.g. Mumbai, BKC / Pune" value="<?= e($_POST['city'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-call w-100 btn-lg" style="margin-top:0.5rem">
                    <i class="bi bi-telephone-outbound-fill"></i> Call Me / Book Guard
                </button>
            </form>
        </div>

        <!-- Direct Action Strip -->
        <div class="callout-strip">
            <div>
                <h3>Need a guard immediately?</h3>
                <p>Call our 24/7 emergency dispatch helpline or message on WhatsApp.</p>
            </div>
            <div class="callout-actions">
                <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn btn-whatsapp btn-lg">
                    <i class="bi bi-whatsapp"></i> WhatsApp Now
                </a>
                <a href="tel:<?= e($compPhone) ?>" class="btn btn-call btn-lg">
                    <i class="bi bi-telephone-fill"></i> Call <?= e($compPhone) ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Basic Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-inner">
            <div class="brand">
                <span class="brand-icon"><i class="bi bi-shield-shaded"></i></span>
                <span><?= e($compName) ?></span>
            </div>

            <ul class="footer-links">
                <li><a href="tel:<?= e($compPhone) ?>"><i class="bi bi-telephone me-1"></i> <?= e($compPhone) ?></a></li>
                <li><a href="https://wa.me/<?= $phoneClean ?>" target="_blank"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a></li>
                <li><a href="<?= url('login.php') ?>"><i class="bi bi-lock me-1"></i> Admin Portal</a></li>
                <li><a href="<?= url('guard/login.php') ?>"><i class="bi bi-phone me-1"></i> Guard App</a></li>
            </ul>
        </div>

        <div class="footer-bottom">
            <div>&copy; <?= date('Y') ?> <?= e($compName) ?>. All rights reserved. Certified Security Agency.</div>
            <div>24/7 Security Operations & Guard Dispatch</div>
        </div>
    </div>
</footer>

</body>
</html>
