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
            log_activity('website.inquiry', "Inquiry from $name ($phone) for $service", 'system');
            $inquirySuccess = true;
        } catch (Throwable $e) {
            $inquiryError = 'Could not send. Please call or WhatsApp us directly.';
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
    <title><?= e($compName) ?> · Security Guard & Bodyguard Services</title>
    <meta name="description" content="Trained security guards and bodyguards for buildings, offices, events, and personal safety. Available unarmed and licensed armed.">
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    
    <!-- Modern Geometric Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #07070a;
            --bg-card: #0d0e17;
            --bg-card-inner: #121320;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(168, 85, 247, 0.35);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-purple: #8b5cf6;
            --accent-purple-light: #a855f7;
            --accent-gradient: linear-gradient(135deg, #7c3aed 0%, #9333ea 50%, #a855f7 100%);
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --radius-pill: 9999px;
            --radius-card: 24px;
            --radius-inner: 16px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-main);
            background-color: var(--bg-dark);
            color: var(--text-primary);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
            position: relative;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.25rem;
            position: relative;
            z-index: 2;
        }

        /* Ambient Spotlights & Background Glow */
        .ambient-spotlights {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 900px;
            pointer-events: none;
            overflow: hidden;
            z-index: 1;
        }
        .spotlight-left {
            position: absolute;
            top: -100px;
            left: -8%;
            width: 550px;
            height: 800px;
            background: radial-gradient(ellipse at 50% 0%, rgba(139, 92, 246, 0.28) 0%, rgba(99, 102, 241, 0.1) 40%, transparent 70%);
            transform: rotate(25deg);
            filter: blur(55px);
        }
        .spotlight-right {
            position: absolute;
            top: -100px;
            right: -8%;
            width: 550px;
            height: 800px;
            background: radial-gradient(ellipse at 50% 0%, rgba(168, 85, 247, 0.28) 0%, rgba(139, 92, 246, 0.1) 40%, transparent 70%);
            transform: rotate(-25deg);
            filter: blur(55px);
        }
        .hero-ambient-glow {
            position: absolute;
            top: 480px;
            left: 50%;
            transform: translateX(-50%);
            width: 850px;
            max-width: 95vw;
            height: 320px;
            background: radial-gradient(ellipse at 50% 50%, rgba(147, 51, 234, 0.35) 0%, rgba(124, 58, 237, 0.15) 45%, transparent 75%);
            filter: blur(70px);
            pointer-events: none;
            z-index: 1;
        }
        .hero-grid-pattern {
            position: absolute;
            inset: 0;
            height: 900px;
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: 55px 55px;
            mask-image: radial-gradient(ellipse at 50% 35%, black 40%, transparent 80%);
            -webkit-mask-image: radial-gradient(ellipse at 50% 35%, black 40%, transparent 80%);
            pointer-events: none;
            z-index: 1;
        }

        /* Admin bar */
        .admin-bar {
            background: #11121d;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8;
            padding: 0.4rem 1rem;
            font-size: 0.8rem;
            text-align: center;
            font-weight: 500;
            position: relative;
            z-index: 1001;
        }
        .admin-bar a { color: #a855f7; font-weight: 600; text-decoration: underline; margin-left: 0.5rem; }

        /* Floating Pill Navbar */
        .navbar-wrap {
            padding: 1rem 0 0.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-pill {
            background: rgba(13, 14, 23, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-pill);
            padding: 0.5rem 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 860px;
            margin: 0 auto;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.6);
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
        }
        .nav-logo-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--accent-gradient);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
            flex-shrink: 0;
        }
        .nav-brand-text {
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
            white-space: nowrap;
        }

        .nav-menu {
            display: none;
            align-items: center;
            gap: 1.25rem;
            list-style: none;
        }
        @media (min-width: 768px) { .nav-menu { display: flex; } }

        .nav-link {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
            transition: color 0.15s ease;
        }
        .nav-link:hover { color: #ffffff; }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-cta-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.4rem 0.9rem;
            border-radius: var(--radius-pill);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }
        .nav-cta-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }
        .nav-signin-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.4rem 0.75rem;
            border-radius: var(--radius-pill);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .nav-signin-btn:hover { color: #fff; border-color: rgba(255, 255, 255, 0.2); }

        @media (max-width: 540px) {
            .nav-signin-btn { display: none; }
            .nav-brand-text { font-size: 0.8rem; }
            .navbar-pill { padding: 0.4rem 0.6rem; }
            .nav-cta-btn { font-size: 0.72rem; padding: 0.35rem 0.65rem; }
        }

        /* -------------------------------------------------------------
           SECTION 1: HERO & SIMPLE SERVICES SHOWCASE
        ------------------------------------------------------------- */
        .hero-section {
            padding: 2.75rem 0 1.75rem;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.32rem 1rem;
            border-radius: var(--radius-pill);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #e2e8f0;
            margin-bottom: 1.25rem;
            backdrop-filter: blur(10px);
        }
        .pill-badge i { color: var(--accent-purple-light); }

        .hero-title {
            font-size: clamp(1.45rem, 4.8vw, 3rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #ffffff;
            line-height: 1.22;
            margin-bottom: 1rem;
            padding: 0 0.5rem;
            word-break: normal;
        }

        .hero-desc {
            max-width: 620px;
            margin: 0 auto 2rem;
            font-size: 1rem;
            color: var(--text-secondary);
            line-height: 1.6;
            padding: 0 0.5rem;
        }

        .hero-btn-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.85rem;
            flex-wrap: wrap;
            margin-bottom: 2.75rem;
        }

        .btn-purple-pill {
            background: var(--accent-gradient);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.92rem;
            padding: 0.8rem 2rem;
            border-radius: var(--radius-pill);
            box-shadow: 0 8px 30px rgba(124, 58, 237, 0.4);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
        }
        .btn-purple-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(147, 51, 234, 0.55);
            color: #ffffff;
        }

        .btn-glass-pill {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.92rem;
            padding: 0.8rem 1.75rem;
            border-radius: var(--radius-pill);
            backdrop-filter: blur(10px);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
        }
        .btn-glass-pill:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            transform: translateY(-2px);
        }

        @media (max-width: 440px) {
            .btn-purple-pill, .btn-glass-pill {
                padding: 0.7rem 1.25rem;
                font-size: 0.85rem;
            }
        }

        /* -------------------------------------------------------------
           SHOWCASE CARD (Template Style - Simple, No Fake Data)
        ------------------------------------------------------------- */
        .dashboard-frame-wrap {
            max-width: 980px;
            margin: 0 auto;
            position: relative;
        }
        .dashboard-frame {
            background: #0b0c15;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-card);
            padding: 1.35rem;
            box-shadow: 0 35px 80px -20px rgba(0, 0, 0, 0.95), 0 0 50px rgba(139, 92, 246, 0.15);
            backdrop-filter: blur(20px);
            text-align: left;
        }

        .dash-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 1.15rem;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .dash-topbar-left {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .dash-logo-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.9rem;
        }
        .dash-tab-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-pill);
            padding: 0.3rem 0.8rem;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .dash-tab-pill.active {
            background: rgba(139, 92, 246, 0.22);
            border-color: rgba(168, 85, 247, 0.45);
            color: #ffffff;
            font-weight: 600;
        }
        .dash-topbar-right {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .dash-phone-tag {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .dash-phone-tag a { color: #c084fc; font-weight: 600; }
        .dash-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            overflow: hidden;
            border: 1.5px solid var(--accent-purple);
            flex-shrink: 0;
        }
        .dash-user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .dash-content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.15rem;
        }
        @media (min-width: 860px) {
            .dash-content-grid { grid-template-columns: 1.5fr 1fr; }
        }

        /* Left Pane: Services Overview */
        .dash-main-pane {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .dash-welcome-text h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
        }
        .dash-welcome-text p {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .dash-hero-image-box {
            position: relative;
            border-radius: var(--radius-inner);
            overflow: hidden;
            height: 235px;
            background: #0d0e17;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .dash-hero-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 35%;
            display: block;
            transition: transform 0.4s ease;
        }
        .dash-hero-image-box:hover img {
            transform: scale(1.02);
        }
        .dash-hero-image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0.85rem 1rem;
            background: linear-gradient(180deg, transparent 0%, rgba(7, 7, 10, 0.92) 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dash-image-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(13, 14, 23, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-pill);
            padding: 0.3rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #e2e8f0;
        }
        .dash-image-tag i {
            color: var(--accent-purple-light);
        }
        @media (max-width: 580px) {
            .dash-hero-image-box { height: 195px; }
            .dash-image-tag { font-size: 0.7rem; padding: 0.25rem 0.6rem; }
        }

        .dash-info-strip {
            background: var(--bg-card-inner);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-inner);
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }
        @media (max-width: 540px) {
            .dash-info-strip {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.4rem;
            }
        }
        .dash-info-strip strong { color: #fff; }

        /* Right Pane: Direct Contact Pass (Card in template) */
        .dash-card-pane {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .security-pass-card {
            background: linear-gradient(135deg, #1c1533 0%, #11111d 60%, #0d0d17 100%);
            border: 1px solid rgba(168, 85, 247, 0.35);
            border-radius: var(--radius-inner);
            padding: 1.25rem;
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        .security-pass-card::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.25) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .pass-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        .chip-graphic {
            width: 34px;
            height: 25px;
            border-radius: 5px;
            background: linear-gradient(135deg, #e2e8f0 0%, #94a3b8 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            flex-shrink: 0;
        }
        .pass-brand-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: #ffffff;
            text-align: right;
        }
        .pass-options {
            font-size: 0.85rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.5rem;
        }
        .pass-subtext {
            font-size: 0.72rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .pass-action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
        }
        .btn-card-purple {
            background: var(--accent-gradient);
            color: #fff;
            border: 0;
            border-radius: var(--radius-pill);
            padding: 0.55rem 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .btn-card-glass {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            border-radius: var(--radius-pill);
            padding: 0.55rem 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }

        .dash-quick-links {
            background: var(--bg-card-inner);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-inner);
            padding: 0.85rem;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .quick-link-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.72rem;
            color: var(--text-secondary);
        }
        .quick-link-item i {
            font-size: 1.1rem;
            color: var(--accent-purple-light);
        }

        /* -------------------------------------------------------------
           SECTIONS: SERVICES & ACTUAL GUARD PHOTOS
        ------------------------------------------------------------- */
        .section-wrap {
            padding: 3.5rem 0;
            position: relative;
            z-index: 2;
        }
        .section-wrap-alt {
            background: #090a12;
            border-top: 1px solid var(--border-subtle);
            border-bottom: 1px solid var(--border-subtle);
        }

        .section-heading-box {
            text-align: center;
            max-width: 620px;
            margin: 0 auto 2.5rem;
        }
        .section-tag-pill {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--accent-purple-light);
            margin-bottom: 0.4rem;
        }
        .section-main-title {
            font-size: clamp(1.6rem, 3.2vw, 2.2rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }
        .section-subtext {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .services-grid-dark {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }
        .service-card-dark {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 18px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        .service-img-container {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: #111;
        }
        .service-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 20%;
            transition: transform 0.3s ease;
        }
        .service-card-dark:hover .service-img-container img {
            transform: scale(1.04);
        }
        .service-card-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(10, 11, 18, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.3rem 0.65rem;
            border-radius: var(--radius-pill);
        }
        .service-body-dark {
            padding: 1.15rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .service-body-dark h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.4rem;
        }
        .service-body-dark p {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0;
        }

        /* -------------------------------------------------------------
           ARMED & UNARMED PROTOCOLS
        ------------------------------------------------------------- */
        .protocols-grid-dark {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            max-width: 860px;
            margin: 0 auto;
        }
        @media (min-width: 680px) {
            .protocols-grid-dark { grid-template-columns: 1fr 1fr; }
        }
        .protocol-card-dark {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 18px;
            padding: 1.5rem;
        }
        .protocol-card-dark.armed-card {
            border-color: rgba(168, 85, 247, 0.35);
        }
        .protocol-tag {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 0.6rem;
            color: var(--accent-purple-light);
        }
        .protocol-card-dark h3 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }
        .protocol-card-dark p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        .protocol-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.82rem;
            color: #cbd5e1;
        }
        .protocol-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .protocol-list li i {
            color: var(--accent-purple-light);
        }

        /* -------------------------------------------------------------
           CONTACT / INQUIRY FORM
        ------------------------------------------------------------- */
        .booking-card-dark {
            max-width: 620px;
            margin: 0 auto;
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-card);
            padding: 2rem;
            box-shadow: 0 20px 50px -15px rgba(0, 0, 0, 0.7);
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        @media (min-width: 520px) {
            .form-row-2 { grid-template-columns: 1fr 1fr; }
        }
        .form-group-dark {
            margin-bottom: 1rem;
        }
        .form-label-dark {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 0.35rem;
        }
        .form-control-dark, .form-select-dark {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            padding: 0.75rem 0.9rem;
            font-size: 0.9rem;
            color: #ffffff;
            font-family: inherit;
            outline: none;
        }
        .form-control-dark:focus, .form-select-dark:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-purple-light);
        }
        .form-select-dark option {
            background: #11121d;
            color: #fff;
        }

        .alert-dark-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
        }
        .alert-dark-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
        }

        /* Direct Contact Strip */
        .direct-contact-strip {
            margin-top: 2rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .direct-contact-strip a {
            color: #fff;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Footer */
        .footer-dark {
            background: #050507;
            border-top: 1px solid var(--border-subtle);
            padding: 2.5rem 0 2rem;
            color: var(--text-muted);
            font-size: 0.82rem;
            position: relative;
            z-index: 2;
        }
        .footer-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .footer-links-list {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            list-style: none;
            flex-wrap: wrap;
        }
        .footer-links-list a {
            color: var(--text-secondary);
        }
        .footer-links-list a:hover {
            color: #ffffff;
        }
        .footer-bottom-bar {
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 1.25rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
    </style>
</head>
<body>

<!-- Ambient Spotlights -->
<div class="ambient-spotlights">
    <div class="spotlight-left"></div>
    <div class="spotlight-right"></div>
    <div class="hero-ambient-glow"></div>
</div>
<div class="hero-grid-pattern"></div>

<?php if ($admin): ?>
    <div class="admin-bar">
        <span>Logged in as <strong><?= e($admin['name']) ?></strong> (<?= e($admin['role']) ?>)</span>
        <a href="<?= url('admin/index.php') ?>">Admin Portal &rarr;</a>
    </div>
<?php endif; ?>

<!-- Floating Pill Navbar -->
<div class="navbar-wrap">
    <div class="container">
        <header class="navbar-pill">
            <a href="<?= url('index.php') ?>" class="nav-brand">
                <div class="nav-logo-circle">
                    <i class="bi bi-shield-shaded"></i>
                </div>
                <span class="nav-brand-text"><?= e($compName) ?></span>
            </a>

            <ul class="nav-menu">
                <li><a href="#services" class="nav-link">Services</a></li>
                <li><a href="#protocols" class="nav-link">Armed & Unarmed</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
                <li><a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="nav-link">WhatsApp</a></li>
            </ul>

            <div class="nav-actions">
                <a href="#contact" class="nav-cta-btn">
                    <i class="bi bi-shield-check"></i> Hire Guard
                </a>
                <a href="<?= url('login.php') ?>" class="nav-signin-btn" title="Staff Portal">
                    <i class="bi bi-lock-fill"></i> Sign In
                </a>
            </div>
        </header>
    </div>
</div>

<!-- ==============================================================
     SECTION 1: HERO & SHOWCASE CARD (Template Style)
     ============================================================== -->
<section class="hero-section">
    <div class="container">
        <!-- Pill Tag Badge -->
        <div class="pill-badge">
            <i class="bi bi-shield-fill-check"></i>
            <span>SECURITY SERVICES</span>
        </div>

        <!-- Headline -->
        <h1 class="hero-title">
            Security & Guard Services<br>
            For Your Safety & Premises
        </h1>

        <!-- Subtitle -->
        <p class="hero-desc">
            We provide trained security personnel for residential buildings, corporate offices, events, and personal safety. Available in unarmed and licensed armed options.
        </p>

        <!-- Two Pill Buttons -->
        <div class="hero-btn-row">
            <a href="#contact" class="btn-purple-pill">
                <i class="bi bi-shield-check"></i> Hire Guard
            </a>
            <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn-glass-pill">
                <i class="bi bi-whatsapp"></i> WhatsApp
            </a>
        </div>

        <!-- Showcase Card (Template Layout with Real Info) -->
        <div class="dashboard-frame-wrap">
            <div class="dashboard-frame">
                <!-- Card Top Bar -->
                <div class="dash-topbar">
                    <div class="dash-topbar-left">
                        <div class="dash-logo-badge">
                            <i class="bi bi-shield-fill"></i>
                        </div>
                        <span class="dash-tab-pill active">Security Detail</span>
                        <span class="dash-tab-pill">Unarmed & Armed</span>
                    </div>
                    <div class="dash-topbar-right">
                        <span class="dash-phone-tag">
                            Call: <a href="tel:<?= e($compPhone) ?>"><?= e($compPhone) ?></a>
                        </span>
                        <div class="dash-user-avatar" title="Duty Guard">
                            <img src="<?= asset('img/guards/hero-guard-sunglasses.jpg') ?>" alt="Security Guard">
                        </div>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="dash-content-grid">
                    <!-- Left: Security Force Showcase -->
                    <div class="dash-main-pane">
                        <div class="dash-hero-image-box">
                            <img src="<?= asset('img/guards/agency-force-team.jpg') ?>" alt="Security Force Deployment Team">
                            <div class="dash-hero-image-overlay">
                                <div class="dash-image-tag">
                                    <i class="bi bi-shield-check"></i>
                                    <span>Trained Uniformed Security Force</span>
                                </div>
                            </div>
                        </div>

                        <div class="dash-info-strip">
                            <div>
                                <i class="bi bi-check-circle-fill" style="color: #34d399; margin-right: 6px;"></i>
                                <span><strong>Services:</strong> VIP Bodyguards · Building Security · Events · Corporate</span>
                            </div>
                            <a href="#services" style="color: var(--accent-purple-light); font-weight: 600;">Details &rarr;</a>
                        </div>
                    </div>

                    <!-- Right: Direct Pass Card -->
                    <div class="dash-card-pane">
                        <div class="security-pass-card">
                            <div class="pass-card-top">
                                <div class="chip-graphic"></div>
                                <span class="pass-brand-title"><?= e($compName) ?></span>
                            </div>
                            <div class="pass-options">Security Deployment</div>
                            <div class="pass-subtext">Unarmed Guards · Licensed Armed Personnel</div>

                            <div class="pass-action-buttons">
                                <a href="tel:<?= e($compPhone) ?>" class="btn-card-purple">
                                    <i class="bi bi-telephone-fill"></i> Call Now
                                </a>
                                <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn-card-glass">
                                    <i class="bi bi-whatsapp"></i> WhatsApp
                                </a>
                            </div>
                        </div>

                        <!-- Quick App & Login Shortcuts -->
                        <div class="dash-quick-links">
                            <a href="<?= url('guard/install.php') ?>" class="quick-link-item">
                                <i class="bi bi-phone"></i>
                                <span>Guard Duty App</span>
                            </a>
                            <a href="<?= url('login.php') ?>" class="quick-link-item">
                                <i class="bi bi-lock"></i>
                                <span>Staff Login</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==============================================================
     SERVICES SECTION (With Real Guard Photos)
     ============================================================== -->
<section class="section-wrap section-wrap-alt" id="services">
    <div class="container">
        <div class="section-heading-box">
            <span class="section-tag-pill">Services</span>
            <h2 class="section-main-title">Our Security Services</h2>
            <p class="section-subtext">Security guards and personal protection personnel tailored to your requirements.</p>
        </div>

        <div class="services-grid-dark">
            <!-- 1. VIP Bodyguard -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge">Personal Protection</span>
                    <img src="<?= asset('img/guards/vip-bodyguard-team.jpg') ?>" alt="Personal VIP bodyguards">
                </div>
                <div class="service-body-dark">
                    <h3>Personal VIP Bodyguard</h3>
                    <p>Close protection for individuals and families. Available armed or unarmed in formal suit or casual attire.</p>
                </div>
            </div>

            <!-- 2. Facility & Gatekeeping -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge">Gatekeeping</span>
                    <img src="<?= asset('img/guards/residential-gate-guard.jpg') ?>" alt="Residential gate security guard">
                </div>
                <div class="service-body-dark">
                    <h3>Building & Society Security</h3>
                    <p>Manned gatekeeping for residential societies, apartment complexes, and gated communities. Visitor check-in and entry monitoring.</p>
                </div>
            </div>

            <!-- 3. Event Security -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge">Access Screening</span>
                    <img src="<?= asset('img/guards/guard-scanner-screening.jpg') ?>" alt="Security guard screening with metal detector">
                </div>
                <div class="service-body-dark">
                    <h3>Event Security & Screening</h3>
                    <p>Entry screening with handheld metal detectors, crowd management, and access monitoring for events, functions, and venues.</p>
                </div>
            </div>

            <!-- 4. Corporate Force -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge">Commercial Guarding</span>
                    <img src="<?= asset('img/guards/corporate-park-guard.jpg') ?>" alt="Security guard at corporate park entrance">
                </div>
                <div class="service-body-dark">
                    <h3>Agency Security Force</h3>
                    <p>Uniformed security personnel for offices, commercial buildings, corporate parks, and premises.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==============================================================
     ARMED & UNARMED PROTOCOLS
     ============================================================== -->
<section class="section-wrap" id="protocols">
    <div class="container">
        <div class="section-heading-box">
            <span class="section-tag-pill">Guard Options</span>
            <h2 class="section-main-title">Unarmed & Armed Guarding</h2>
            <p class="section-subtext">Choose the protection level suitable for your site or requirement.</p>
        </div>

        <div class="protocols-grid-dark">
            <div class="protocol-card-dark">
                <div class="protocol-tag">
                    <i class="bi bi-shield-check"></i> Standard Security
                </div>
                <h3>Unarmed Security Guards</h3>
                <p>The standard security option for residential societies, commercial offices, and events. Guards focus on access control, gatekeeping, and visitor logs.</p>
                <ul class="protocol-list">
                    <li><i class="bi bi-check2"></i> Gatekeeping & visitor management</li>
                    <li><i class="bi bi-check2"></i> Entry and perimeter presence</li>
                    <li><i class="bi bi-check2"></i> Conflict de-escalation</li>
                </ul>
            </div>

            <div class="protocol-card-dark armed-card">
                <div class="protocol-tag">
                    <i class="bi bi-shield-lock-fill"></i> Authorized Requirement
                </div>
                <h3>Licensed Armed Guards</h3>
                <p>Armed security personnel deployed exclusively for high-risk assets or authorized escort requirements. Provided strictly with verified government-issued arms licenses in compliance with law.</p>
                <ul class="protocol-list">
                    <li><i class="bi bi-check2"></i> Deployed strictly with valid statutory arms licenses</li>
                    <li><i class="bi bi-check2"></i> Specialized high-risk & transit escort</li>
                    <li><i class="bi bi-check2"></i> In compliance with statutory regulations</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ==============================================================
     CONTACT / INQUIRY FORM
     ============================================================== -->
<section class="section-wrap section-wrap-alt" id="contact">
    <div class="container">
        <div class="section-heading-box">
            <span class="section-tag-pill">Contact</span>
            <h2 class="section-main-title">Hire Security Personnel</h2>
            <p class="section-subtext">Submit your requirement below or contact us directly.</p>
        </div>

        <div class="booking-card-dark">
            <?php if ($inquirySuccess): ?>
                <div class="alert-dark-success">
                    <i class="bi bi-check-circle-fill me-1"></i> Thank you. Your inquiry has been received. We will contact you soon.
                </div>
            <?php endif; ?>

            <?php if ($inquiryError): ?>
                <div class="alert-dark-error">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($inquiryError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="#contact">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="inquiry">

                <div class="form-row-2">
                    <div class="form-group-dark">
                        <label class="form-label-dark">Full Name</label>
                        <input type="text" name="name" class="form-control-dark" placeholder="Your name" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group-dark">
                        <label class="form-label-dark">Phone Number</label>
                        <input type="tel" name="phone" class="form-control-dark" placeholder="Your phone number" required value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group-dark">
                        <label class="form-label-dark">Service Needed</label>
                        <select name="service_type" class="form-select-dark">
                            <option value="bodyguard">Personal VIP Bodyguard</option>
                            <option value="building_security">Building & Society Security</option>
                            <option value="event_security">Event Security & Screening</option>
                            <option value="agency_security">Agency Security Force (Corporate / Office)</option>
                        </select>
                    </div>
                    <div class="form-group-dark">
                        <label class="form-label-dark">Guard Type</label>
                        <select name="is_armed" class="form-select-dark">
                            <option value="0">Unarmed Security</option>
                            <option value="1">Licensed Armed Security</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-dark" style="margin-bottom: 1.25rem;">
                    <label class="form-label-dark">City / Area</label>
                    <input type="text" name="city" class="form-control-dark" placeholder="e.g. Mumbai, Andheri" value="<?= e($_POST['city'] ?? '') ?>">
                </div>

                <button type="submit" class="btn-purple-pill" style="width: 100%; justify-content: center; padding: 0.85rem;">
                    <i class="bi bi-send-fill"></i> Send Inquiry
                </button>
            </form>
        </div>

        <div class="direct-contact-strip">
            <span>Direct Call: <a href="tel:<?= e($compPhone) ?>"><i class="bi bi-telephone-fill"></i> <?= e($compPhone) ?></a></span>
            <span>WhatsApp: <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" style="color: #34d399;"><i class="bi bi-whatsapp"></i> Chat on WhatsApp</a></span>
        </div>
    </div>
</section>

<!-- ==============================================================
     FOOTER
     ============================================================== -->
<footer class="footer-dark">
    <div class="container">
        <div class="footer-flex">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div class="nav-logo-circle" style="width: 30px; height: 30px; font-size: 0.85rem;">
                    <i class="bi bi-shield-shaded"></i>
                </div>
                <div>
                    <strong style="color: #fff; font-size: 0.95rem; display: block;"><?= e($compName) ?></strong>
                    <span style="color: var(--text-muted); font-size: 0.78rem;">Security Guard & Protection Services</span>
                </div>
            </div>

            <ul class="footer-links-list">
                <li><a href="tel:<?= e($compPhone) ?>"><i class="bi bi-telephone me-1"></i> <?= e($compPhone) ?></a></li>
                <li><a href="https://wa.me/<?= $phoneClean ?>" target="_blank"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a></li>
                <li><a href="<?= url('guard/install.php') ?>"><i class="bi bi-phone me-1"></i> Guard App</a></li>
                <li><a href="<?= url('login.php') ?>"><i class="bi bi-lock me-1"></i> Staff Login</a></li>
            </ul>
        </div>

        <div class="footer-bottom-bar">
            <div>&copy; <?= date('Y') ?> <?= e($compName) ?>. All rights reserved.</div>
            <div>Professional Guard Services</div>
        </div>
    </div>
</footer>

</body>
</html>
