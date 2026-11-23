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

// Dynamic fleet statistics
$guardCount = (int)(q_val("SELECT COUNT(*) FROM guards") ?: 42);
$activeGuardCount = (int)(q_val("SELECT COUNT(*) FROM guards WHERE status = 'active'") ?: 42);
$siteCount = (int)(q_val("SELECT COUNT(DISTINCT site_name) FROM assignments WHERE status = 'active'") ?: 18);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($compName) ?> · Elite Security & Guard Protection</title>
    <meta name="description" content="Professional security guards and VIP bodyguards. Dedicated unarmed officers and statutorily licensed armed personnel for corporate, residential, and private protection.">
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    
    <!-- Modern Geometric Font matching template -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #07070a;
            --bg-card: #0d0e17;
            --bg-card-inner: #121320;
            --bg-card-hover: #17192b;
            --border-subtle: rgba(255, 255, 255, 0.08);
            --border-highlight: rgba(168, 85, 247, 0.35);
            --text-primary: #ffffff;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-purple: #8b5cf6;
            --accent-purple-light: #a855f7;
            --accent-purple-dark: #6d28d9;
            --accent-gradient: linear-gradient(135deg, #7c3aed 0%, #9333ea 50%, #a855f7 100%);
            --accent-whatsapp: #10b981;
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            --radius-pill: 9999px;
            --radius-card: 26px;
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
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
            position: relative;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; height: auto; display: block; }

        .container {
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 1.25rem;
            position: relative;
            z-index: 2;
        }

        /* -------------------------------------------------------------
           AMBIENT SPOTLIGHT BEAMS (Exact visual effect from template)
        ------------------------------------------------------------- */
        .ambient-spotlights {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 980px;
            pointer-events: none;
            overflow: hidden;
            z-index: 1;
        }
        .spotlight-left {
            position: absolute;
            top: -100px;
            left: -8%;
            width: 600px;
            height: 850px;
            background: radial-gradient(ellipse at 50% 0%, rgba(139, 92, 246, 0.28) 0%, rgba(99, 102, 241, 0.1) 40%, transparent 70%);
            transform: rotate(25deg);
            filter: blur(55px);
        }
        .spotlight-right {
            position: absolute;
            top: -100px;
            right: -8%;
            width: 600px;
            height: 850px;
            background: radial-gradient(ellipse at 50% 0%, rgba(168, 85, 247, 0.28) 0%, rgba(139, 92, 246, 0.1) 40%, transparent 70%);
            transform: rotate(-25deg);
            filter: blur(55px);
        }
        /* Glowing elliptical halo behind dashboard card */
        .hero-ambient-glow {
            position: absolute;
            top: 510px;
            left: 50%;
            transform: translateX(-50%);
            width: 920px;
            max-width: 96vw;
            height: 380px;
            background: radial-gradient(ellipse at 50% 50%, rgba(147, 51, 234, 0.38) 0%, rgba(124, 58, 237, 0.18) 45%, transparent 75%);
            filter: blur(75px);
            pointer-events: none;
            z-index: 1;
        }

        /* Subtle grid background */
        .hero-grid-pattern {
            position: absolute;
            inset: 0;
            height: 980px;
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
            padding: 0.45rem 1rem;
            font-size: 0.82rem;
            text-align: center;
            font-weight: 500;
            position: relative;
            z-index: 1001;
        }
        .admin-bar a { color: #a855f7; font-weight: 600; text-decoration: underline; margin-left: 0.5rem; }

        /* -------------------------------------------------------------
           FLOATING PILL NAVBAR (Template Style)
        ------------------------------------------------------------- */
        .navbar-wrap {
            padding: 1.25rem 0 0.5rem;
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
            padding: 0.55rem 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 920px;
            margin: 0 auto;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.6);
            transition: border-color 0.2s ease;
        }
        .navbar-pill:hover {
            border-color: rgba(255, 255, 255, 0.16);
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
        }
        .nav-logo-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--accent-gradient);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.45);
            flex-shrink: 0;
        }
        .nav-brand-text {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #fff;
            white-space: nowrap;
        }

        .nav-menu {
            display: none;
            align-items: center;
            gap: 1.5rem;
            list-style: none;
        }
        @media (min-width: 768px) { .nav-menu { display: flex; } }

        .nav-link {
            font-size: 0.88rem;
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
            font-size: 0.82rem;
            font-weight: 600;
            padding: 0.45rem 1rem;
            border-radius: var(--radius-pill);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }
        .nav-cta-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            transform: translateY(-1px);
        }
        .nav-signin-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-secondary);
            font-size: 0.82rem;
            font-weight: 500;
            padding: 0.45rem 0.85rem;
            border-radius: var(--radius-pill);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .nav-signin-btn:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.18);
        }

        @media (max-width: 540px) {
            .nav-signin-btn { display: none; }
            .nav-brand-text { font-size: 0.88rem; }
            .navbar-pill { padding: 0.45rem 0.65rem; }
            .nav-cta-btn { font-size: 0.78rem; padding: 0.4rem 0.8rem; }
        }

        /* -------------------------------------------------------------
           SECTION 1: HERO (Modeled on Uploaded Image)
        ------------------------------------------------------------- */
        .hero-section {
            padding: 3.25rem 0 2rem;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.35rem 1.1rem;
            border-radius: var(--radius-pill);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #e2e8f0;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        }
        .pill-badge i {
            color: var(--accent-purple-light);
            font-size: 0.9rem;
        }

        .hero-title {
            font-size: clamp(1.8rem, 5.5vw, 3.8rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            color: #ffffff;
            line-height: 1.16;
            margin-bottom: 1.25rem;
            padding: 0 0.5rem;
        }

        .hero-desc {
            max-width: 660px;
            margin: 0 auto 2.25rem;
            font-size: 1.02rem;
            color: var(--text-secondary);
            line-height: 1.6;
            padding: 0 0.5rem;
        }

        .hero-btn-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 3.25rem;
        }
        @media (max-width: 440px) {
            .btn-purple-pill, .btn-glass-pill {
                padding: 0.75rem 1.3rem;
                font-size: 0.88rem;
            }
            .hero-btn-row {
                gap: 0.65rem;
            }
        }

        .btn-purple-pill {
            background: var(--accent-gradient);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.85rem 2.25rem;
            border-radius: var(--radius-pill);
            box-shadow: 0 8px 30px rgba(124, 58, 237, 0.45);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
        }
        .btn-purple-pill:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(147, 51, 234, 0.6);
            color: #ffffff;
        }

        .btn-glass-pill {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.14);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.85rem 2rem;
            border-radius: var(--radius-pill);
            backdrop-filter: blur(10px);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-glass-pill:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* -------------------------------------------------------------
           DASHBOARD SHOWCASE CARD (Exact Layout & Visual from Template)
        ------------------------------------------------------------- */
        .dashboard-frame-wrap {
            max-width: 1040px;
            margin: 0 auto;
            position: relative;
        }

        .dashboard-frame {
            background: #0b0c15;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-card);
            padding: 1.4rem;
            box-shadow: 0 35px 80px -20px rgba(0, 0, 0, 0.95), 0 0 50px rgba(139, 92, 246, 0.16);
            backdrop-filter: blur(20px);
            position: relative;
            text-align: left;
        }

        /* Top Bar of Dashboard */
        .dash-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1.15rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            margin-bottom: 1.25rem;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .dash-topbar-left {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            flex-wrap: wrap;
        }
        .dash-logo-badge {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.95rem;
        }
        .dash-tab-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-pill);
            padding: 0.32rem 0.85rem;
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
        .dash-search-pill {
            display: none;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-pill);
            padding: 0.32rem 0.85rem;
            font-size: 0.78rem;
            color: var(--text-muted);
            align-items: center;
            gap: 0.4rem;
        }
        @media (min-width: 640px) {
            .dash-search-pill { display: flex; }
        }
        .dash-tab-pill.desktop-only {
            display: none;
        }
        @media (min-width: 580px) {
            .dash-tab-pill.desktop-only { display: inline-block; }
        }
        .dash-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            overflow: hidden;
            border: 1.5px solid var(--accent-purple);
            position: relative;
            flex-shrink: 0;
        }
        .dash-user-avatar img { width: 100%; height: 100%; object-fit: cover; }

        /* Dashboard Body Layout: Dock + Content */
        .dash-body-layout {
            display: flex;
            gap: 1.25rem;
        }

        /* Left Vertical Dock (Modeled after the mini-dock in template) */
        .dash-dock-bar {
            display: none;
        }
        @media (min-width: 768px) {
            .dash-dock-bar {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                background: rgba(255, 255, 255, 0.035);
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: var(--radius-pill);
                padding: 0.75rem 0.45rem;
                height: fit-content;
                align-self: flex-start;
            }
        }
        .dash-dock-item {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .dash-dock-item:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }
        .dash-dock-item.active {
            background: rgba(139, 92, 246, 0.3);
            color: #c084fc;
            box-shadow: 0 0 12px rgba(168, 85, 247, 0.35);
        }

        /* Dashboard Grid: Main Metrics + Pass Card */
        .dash-content-grid {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 920px) {
            .dash-content-grid { grid-template-columns: 1.6fr 1fr; }
        }

        .dash-main-pane {
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        .dash-welcome-text h2 {
            font-size: 1.38rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #ffffff;
        }
        .dash-welcome-text p {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .dash-cards-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 580px) {
            .dash-cards-row { grid-template-columns: 1fr 1fr; }
        }

        /* Left Metric Card (like Total Balance in template) */
        .dash-metric-card {
            background: var(--bg-card-inner);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-inner);
            padding: 1.15rem;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .metric-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.4rem;
        }
        .metric-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .metric-arrow {
            font-size: 0.85rem;
            color: var(--accent-purple-light);
        }
        .metric-value {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin-bottom: 0.35rem;
        }
        .metric-value span {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-left: 0.35rem;
        }

        /* SVG Spline Wave Chart (matches the exact curve graph in template) */
        .sparkline-svg-wrap {
            width: 100%;
            height: 52px;
            margin-top: 0.5rem;
        }

        /* Middle Detail Card (like Saving Wallet in template) */
        .dash-wallet-card {
            background: var(--bg-card-inner);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-inner);
            padding: 1.15rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .dash-wallet-value {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #ffffff;
            margin: 0.2rem 0;
        }
        .dash-wallet-tags {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.4rem;
            margin-top: 0.75rem;
        }
        .dash-tag-box {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 0.45rem 0.25rem;
            text-align: center;
        }
        .dash-tag-box i { font-size: 0.85rem; color: var(--accent-purple-light); display: block; margin-bottom: 2px; }
        .dash-tag-box span { font-size: 0.68rem; color: var(--text-secondary); font-weight: 500; display: block; }

        /* Schedule / Shift Banner below */
        .dash-schedule-bar {
            background: var(--bg-card-inner);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-inner);
            padding: 0.85rem 1.15rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dash-schedule-bar strong { font-size: 0.88rem; color: #fff; }
        .dash-schedule-bar span { font-size: 0.78rem; color: var(--text-muted); display: block; }
        .pulse-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
            display: inline-block;
            margin-right: 8px;
            flex-shrink: 0;
        }

        /* -------------------------------------------------------------
           RIGHT PANE: EXECUTIVE SECURITY PASS (Like Visa Card in Template)
        ------------------------------------------------------------- */
        .dash-card-pane {
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        .security-pass-card {
            background: linear-gradient(135deg, #1c1533 0%, #11111d 60%, #0d0d17 100%);
            border: 1px solid rgba(168, 85, 247, 0.35);
            border-radius: var(--radius-inner);
            padding: 1.35rem;
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.15);
            position: relative;
            overflow: hidden;
        }
        .security-pass-card::after {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 130px;
            height: 130px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .pass-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }
        .chip-graphic {
            width: 38px;
            height: 28px;
            border-radius: 6px;
            background: linear-gradient(135deg, #e2e8f0 0%, #94a3b8 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
        }
        .chip-graphic::after {
            content: '';
            position: absolute;
            inset: 6px;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 3px;
        }
        .pass-brand-title {
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.05em;
            color: #ffffff;
        }

        .pass-number {
            font-size: 1.15rem;
            letter-spacing: 0.15em;
            font-weight: 700;
            font-family: monospace;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.65rem;
        }
        .pass-date-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.72rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .pass-action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            margin-top: 1.15rem;
        }
        .btn-card-purple {
            background: var(--accent-gradient);
            color: #fff;
            border: 0;
            border-radius: var(--radius-pill);
            padding: 0.6rem 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-card-purple:hover { transform: translateY(-1px); color: #fff; }
        .btn-card-glass {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #fff;
            border-radius: var(--radius-pill);
            padding: 0.6rem 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-card-glass:hover { background: rgba(255, 255, 255, 0.14); color: #fff; }

        /* Quick Action Bar */
        .dash-quick-actions {
            background: var(--bg-card-inner);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-inner);
            padding: 0.95rem;
        }
        .quick-actions-title {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 0.65rem;
        }
        .quick-actions-row {
            display: flex;
            justify-content: space-around;
            gap: 0.5rem;
        }
        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
            color: var(--text-secondary);
            font-size: 0.72rem;
            font-weight: 500;
            transition: color 0.15s;
        }
        .quick-action-btn i {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            color: var(--accent-purple-light);
        }
        .quick-action-btn:hover { color: #fff; }

        /* -------------------------------------------------------------
           SECTIONS BELOW HERO: REST OF WEBSITE (All Details Preserved)
        ------------------------------------------------------------- */
        .section-wrap {
            padding: 4.5rem 0;
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
            max-width: 680px;
            margin: 0 auto 3.25rem;
        }
        .section-tag-pill {
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--accent-purple-light);
            margin-bottom: 0.5rem;
        }
        .section-main-title {
            font-size: clamp(1.75rem, 3.5vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #ffffff;
            margin-bottom: 0.75rem;
        }
        .section-subtext {
            font-size: 1.02rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Trust Strip: 4 Dark Glass Cards */
        .trust-strip-dark {
            padding: 2.5rem 0;
            border-top: 1px solid var(--border-subtle);
            border-bottom: 1px solid var(--border-subtle);
            background: rgba(13, 14, 23, 0.6);
            backdrop-filter: blur(15px);
        }
        .trust-grid-dark {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.25rem;
        }
        .trust-card-dark {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-inner);
            padding: 1rem 1.15rem;
            transition: border-color 0.2s;
        }
        .trust-card-dark:hover { border-color: rgba(168, 85, 247, 0.3); }
        .trust-icon-box {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(168, 85, 247, 0.25);
            color: var(--accent-purple-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .trust-card-dark h4 {
            font-size: 0.92rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.15rem;
        }
        .trust-card-dark p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* Services Grid: 4 Dark Cards with Real Images */
        .services-grid-dark {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.35rem;
        }
        .service-card-dark {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        .service-card-dark:hover {
            transform: translateY(-4px);
            border-color: rgba(168, 85, 247, 0.4);
            box-shadow: 0 15px 40px -10px rgba(124, 58, 237, 0.25);
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
            transition: transform 0.4s ease;
        }
        .service-card-dark:hover .service-img-container img {
            transform: scale(1.05);
        }
        .service-card-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(10, 11, 18, 0.88);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.35rem 0.75rem;
            border-radius: var(--radius-pill);
        }
        .service-body-dark {
            padding: 1.35rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .service-body-dark h3 {
            font-size: 1.22rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #ffffff;
            margin-bottom: 0.55rem;
        }
        .service-body-dark p {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.55;
            margin-bottom: 1.25rem;
            flex-grow: 1;
        }
        .service-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--accent-purple-light);
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* Armed vs Unarmed Protocols */
        .protocols-grid-dark {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.75rem;
            max-width: 950px;
            margin: 0 auto;
        }
        @media (min-width: 768px) {
            .protocols-grid-dark { grid-template-columns: 1fr 1fr; }
        }
        .protocol-card-dark {
            background: var(--bg-card);
            border: 1px solid var(--border-subtle);
            border-radius: 20px;
            padding: 2.25rem;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .protocol-card-dark.armed-card {
            border-color: rgba(168, 85, 247, 0.35);
            background: linear-gradient(180deg, rgba(168, 85, 247, 0.06) 0%, #0d0e17 100%);
        }
        .protocol-tag {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.85rem;
            color: var(--accent-purple-light);
        }
        .protocol-card-dark h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.65rem;
        }
        .protocol-card-dark p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.55;
            margin-bottom: 1.5rem;
        }
        .protocol-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            font-size: 0.86rem;
            color: #cbd5e1;
            margin-top: auto;
        }
        .protocol-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
        }
        .protocol-list li i {
            color: var(--accent-purple-light);
            margin-top: 2px;
        }

        /* Booking / Quote Form */
        .booking-card-dark {
            max-width: 700px;
            margin: 0 auto;
            background: var(--bg-card);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 2.25rem;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.15rem;
            margin-bottom: 1.15rem;
        }
        @media (min-width: 580px) {
            .form-row-2 { grid-template-columns: 1fr 1fr; }
        }
        .form-group-dark {
            margin-bottom: 1.15rem;
        }
        .form-label-dark {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 0.4rem;
        }
        .form-control-dark, .form-select-dark {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 0.82rem 1rem;
            font-size: 0.92rem;
            color: #ffffff;
            font-family: inherit;
            outline: none;
            transition: all 0.2s;
        }
        .form-control-dark:focus, .form-select-dark:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-purple-light);
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.2);
        }
        .form-select-dark option {
            background: #11121d;
            color: #fff;
        }

        /* Action Emergency Strip */
        .emergency-strip-dark {
            margin-top: 3.5rem;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.12) 0%, rgba(13, 14, 23, 0.8) 100%);
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 20px;
            padding: 2rem 2.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .emergency-text h3 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.35rem;
        }
        .emergency-text p {
            color: var(--text-secondary);
            font-size: 0.92rem;
            margin: 0;
        }

        /* Footer */
        .footer-dark {
            background: #050507;
            border-top: 1px solid var(--border-subtle);
            padding: 3.5rem 0 2.25rem;
            color: var(--text-muted);
            font-size: 0.86rem;
            position: relative;
            z-index: 2;
        }
        .footer-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 2.25rem;
        }
        .footer-links-list {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            list-style: none;
            flex-wrap: wrap;
        }
        .footer-links-list a {
            color: var(--text-secondary);
            transition: color 0.15s;
        }
        .footer-links-list a:hover {
            color: #ffffff;
        }
        .footer-bottom-bar {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 1.75rem;
            font-size: 0.8rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Success / Error Alerts */
        .alert-dark-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.92rem;
        }
        .alert-dark-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.92rem;
        }
    </style>
</head>
<body>

<!-- Ambient Spotlights and Ambient Glow (Template Visual) -->
<div class="ambient-spotlights">
    <div class="spotlight-left"></div>
    <div class="spotlight-right"></div>
    <div class="hero-ambient-glow"></div>
</div>
<div class="hero-grid-pattern"></div>

<?php if ($admin): ?>
    <div class="admin-bar">
        <span>Logged in as <strong><?= e($admin['name']) ?></strong> (<?= e($admin['role']) ?>)</span>
        <a href="<?= url('admin/index.php') ?>">Open Admin Command Center &rarr;</a>
    </div>
<?php endif; ?>

<!-- Floating Pill Navbar (Template Style) -->
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
                <li><a href="#protocols" class="nav-link">Protocols</a></li>
                <li><a href="#hire" class="nav-link">Book Guard</a></li>
                <li><a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="nav-link">WhatsApp</a></li>
            </ul>

            <div class="nav-actions">
                <a href="#hire" class="nav-cta-btn">
                    <i class="bi bi-shield-check"></i> Request Guard
                </a>
                <a href="<?= url('login.php') ?>" class="nav-signin-btn" title="Staff & Admin Portal">
                    <i class="bi bi-lock-fill"></i> Sign In
                </a>
            </div>
        </header>
    </div>
</div>

<!-- ==============================================================
     SECTION 1: HERO & DASHBOARD SHOWCASE (Modeled on Uploaded Image)
     ============================================================== -->
<section class="hero-section">
    <div class="container">
        <!-- Top Pill Tag Badge -->
        <div class="pill-badge">
            <i class="bi bi-shield-fill-check"></i>
            <span>SMART SECURITY FLEET</span>
        </div>

        <!-- 2-Line Centered Headline -->
        <h1 class="hero-title">
            Smarter Security Control<br>
            Anytime Anywhere
        </h1>

        <!-- Centered Subtitle -->
        <p class="hero-desc">
            Take full control of your safety effortlessly. Deploy verified bodyguards, inspect live site deployments, coordinate protection, and manage safety anytime, anywhere with ease.
        </p>

        <!-- Two Pill Buttons (Exact Match to Template) -->
        <div class="hero-btn-row">
            <a href="#hire" class="btn-purple-pill">
                <i class="bi bi-lightning-charge-fill"></i> Get Started
            </a>
            <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn-glass-pill">
                <i class="bi bi-whatsapp"></i> WhatsApp Quote
            </a>
        </div>

        <!-- Large Dashboard Showcase Card (Exact Structure from Template) -->
        <div class="dashboard-frame-wrap">
            <div class="dashboard-frame">
                <!-- Top Bar with Pills -->
                <div class="dash-topbar">
                    <div class="dash-topbar-left">
                        <div class="dash-logo-badge">
                            <i class="bi bi-shield-fill"></i>
                        </div>
                        <span class="dash-tab-pill active">Command Center</span>
                        <span class="dash-tab-pill">Guard Patrol</span>
                        <span class="dash-tab-pill">Live Shifts</span>
                        <span class="dash-tab-pill desktop-only">VIP Convoy</span>
                    </div>
                    <div class="dash-topbar-right">
                        <div class="dash-search-pill">
                            <i class="bi bi-search"></i>
                            <span>Search fleet...</span>
                        </div>
                        <div class="dash-user-avatar" title="Duty Officer">
                            <img src="<?= asset('img/guards/hero-guard-sunglasses.jpg') ?>" alt="Duty Officer">
                        </div>
                    </div>
                </div>

                <!-- Dashboard Body Layout with Left Dock -->
                <div class="dash-body-layout">
                    <!-- Left Vertical Dock (Mini-sidebar from template) -->
                    <div class="dash-dock-bar">
                        <a href="#services" class="dash-dock-item active" title="Command Overview"><i class="bi bi-grid-fill"></i></a>
                        <a href="#protocols" class="dash-dock-item" title="Shift Roster"><i class="bi bi-clock-history"></i></a>
                        <a href="#hire" class="dash-dock-item" title="Deployment Sites"><i class="bi bi-geo-alt-fill"></i></a>
                        <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="dash-dock-item" title="Emergency Hotline"><i class="bi bi-shield-check"></i></a>
                    </div>

                    <!-- Main Content Grid -->
                    <div class="dash-content-grid">
                        <!-- Left Pane: Greeting + 2 Metric Cards + Schedule -->
                        <div class="dash-main-pane">
                            <div class="dash-welcome-text">
                                <h2>Hi, Commander! Operations Active</h2>
                                <p>All active deployments operating with verified real-time supervision.</p>
                            </div>

                            <div class="dash-cards-row">
                                <!-- Metric 1: Total Active Force (with smooth SVG wave spline) -->
                                <div class="dash-metric-card">
                                    <div>
                                        <div class="metric-label-row">
                                            <span class="metric-label">Total Active Fleet</span>
                                            <i class="bi bi-arrow-up-right metric-arrow"></i>
                                        </div>
                                        <div class="metric-value"><?= $guardCount ?><span>Guards On-Duty</span></div>
                                        <div style="font-size: 0.75rem; color: #34d399; font-weight: 600;">
                                            <i class="bi bi-arrow-up-short"></i> 100% Attendance Today
                                        </div>
                                    </div>
                                    <div class="sparkline-svg-wrap">
                                        <svg viewBox="0 0 240 50" style="width: 100%; height: 100%; overflow: visible;" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="waveFill" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#a855f7" stop-opacity="0.5" />
                                                    <stop offset="100%" stop-color="#7c3aed" stop-opacity="0.0" />
                                                </linearGradient>
                                            </defs>
                                            <path d="M 0,38 C 25,44 45,26 70,30 C 100,36 125,12 155,20 C 185,28 205,6 240,10 L 240,50 L 0,50 Z" fill="url(#waveFill)" />
                                            <path d="M 0,38 C 25,44 45,26 70,30 C 100,36 125,12 155,20 C 185,28 205,6 240,10" fill="none" stroke="#a855f7" stroke-width="2.5" stroke-linecap="round" />
                                            <circle cx="70" cy="30" r="3" fill="#c084fc" />
                                            <circle cx="155" cy="20" r="3" fill="#c084fc" />
                                            <circle cx="240" cy="10" r="3.5" fill="#ffffff" stroke="#9333ea" stroke-width="2" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Metric 2: Deployed Sites (styled like Saving Wallet in template) -->
                                <div class="dash-wallet-card">
                                    <div>
                                        <div class="metric-label-row">
                                            <span class="metric-label">Active Locations</span>
                                            <span class="badge" style="background: rgba(168,85,247,0.2); color: #c084fc; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 999px;">Live Monitored</span>
                                        </div>
                                        <div class="dash-wallet-value"><?= $siteCount ?> Sites</div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">Perimeter & Gatekeeping</div>
                                    </div>

                                    <div class="dash-wallet-tags">
                                        <div class="dash-tag-box">
                                            <i class="bi bi-buildings-fill"></i>
                                            <span>Corporate</span>
                                        </div>
                                        <div class="dash-tag-box">
                                            <i class="bi bi-house-door-fill"></i>
                                            <span>Society</span>
                                        </div>
                                        <div class="dash-tag-box">
                                            <i class="bi bi-person-fill-shield"></i>
                                            <span>VIP Escort</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Bar -->
                            <div class="dash-schedule-bar">
                                <div style="display:flex; align-items:center;">
                                    <span class="pulse-status-dot"></span>
                                    <div>
                                        <strong>24/7 Command Center Live</strong>
                                        <span>Continuous shift monitoring & instant response ready</span>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-right" style="color: var(--accent-purple-light); font-size: 1.1rem;"></i>
                            </div>
                        </div>

                        <!-- Right Pane: Executive Security Pass (Like Visa Card in template) -->
                        <div class="dash-card-pane">
                            <div class="security-pass-card">
                                <div class="pass-card-top">
                                    <div class="chip-graphic"></div>
                                    <span class="pass-brand-title"><i class="bi bi-shield-shaded me-1"></i>APEX</span>
                                </div>
                                <div class="pass-number">**** 8745</div>
                                <div class="pass-date-row">
                                    <span>COMMAND PASS</span>
                                    <span>ACTIVE 2026</span>
                                </div>

                                <div class="pass-action-buttons">
                                    <a href="#hire" class="btn-card-purple">
                                        <i class="bi bi-person-plus-fill me-1"></i> Assign Guard
                                    </a>
                                    <a href="tel:<?= e($compPhone) ?>" class="btn-card-glass">
                                        <i class="bi bi-telephone-fill me-1"></i> Call Ops
                                    </a>
                                </div>
                            </div>

                            <!-- Quick Actions Grid below card -->
                            <div class="dash-quick-actions">
                                <div class="quick-actions-title">Quick Operations</div>
                                <div class="quick-actions-row">
                                    <a href="#services" class="quick-action-btn">
                                        <i class="bi bi-shield-check"></i>
                                        <span>Services</span>
                                    </a>
                                    <a href="<?= url('guard/install.php') ?>" class="quick-action-btn">
                                        <i class="bi bi-phone"></i>
                                        <span>Duty App</span>
                                    </a>
                                    <a href="<?= url('login.php') ?>" class="quick-action-btn">
                                        <i class="bi bi-lock"></i>
                                        <span>Sign In</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust Strip: 4 Dark Cards -->
<div class="trust-strip-dark">
    <div class="container">
        <div class="trust-grid-dark">
            <div class="trust-card-dark">
                <div class="trust-icon-box"><i class="bi bi-shield-check"></i></div>
                <div>
                    <h4>Background Checked</h4>
                    <p>Structured identity & reference checks</p>
                </div>
            </div>
            <div class="trust-card-dark">
                <div class="trust-icon-box"><i class="bi bi-shield-lock-fill"></i></div>
                <div>
                    <h4>Armed & Unarmed Options</h4>
                    <p>Statutorily licensed protection available</p>
                </div>
            </div>
            <div class="trust-card-dark">
                <div class="trust-icon-box"><i class="bi bi-geo-alt-fill"></i></div>
                <div>
                    <h4>Active Shift Oversight</h4>
                    <p>Verified duty check-ins & reporting</p>
                </div>
            </div>
            <div class="trust-card-dark">
                <div class="trust-icon-box"><i class="bi bi-headset"></i></div>
                <div>
                    <h4>24/7 Operations Desk</h4>
                    <p>Continuous support & active coordination</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==============================================================
     SERVICES SECTION: 4 Authentic Officer Photo Cards
     ============================================================== -->
<section class="section-wrap section-wrap-alt" id="services">
    <div class="container">
        <div class="section-heading-box">
            <span class="section-tag-pill">Dedicated Security Units</span>
            <h2 class="section-main-title">Security Personnel Built For Your Safety</h2>
            <p class="section-subtext">From executive close-protection to round-the-clock perimeter guarding, every officer is trained for maximum vigilance.</p>
        </div>

        <div class="services-grid-dark">
            <!-- 1. VIP Bodyguard -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge"><i class="bi bi-person-fill-shield me-1"></i> Close Protection</span>
                    <!-- VIP Close Protection Bodyguards in dark suits -->
                    <img src="<?= asset('img/guards/vip-bodyguard-team.jpg') ?>" alt="Close Protection VIP Bodyguards in suits">
                </div>
                <div class="service-body-dark">
                    <h3>VIP Bodyguard</h3>
                    <p>Discreet executive protection for high-profile clients, dignitaries, and families. Available in sharp suit or tactical attire, armed or unarmed.</p>
                    <div class="service-card-footer">
                        <span>Personal Safety Detail</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Facility & Gatekeeping -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge"><i class="bi bi-building-lock me-1"></i> 24/7 Gatekeeping</span>
                    <!-- Uniformed security officer at residential gate and boom barrier cabin -->
                    <img src="<?= asset('img/guards/residential-gate-guard.jpg') ?>" alt="Trained security officer at residential boom barrier entrance">
                </div>
                <div class="service-body-dark">
                    <h3>Facility & Gatekeeping</h3>
                    <p>Round-the-clock manned security and gatekeeping for residential complexes, corporate campuses, gated communities, and tech parks. Visitor logging and barrier management.</p>
                    <div class="service-card-footer">
                        <span>Facility Guarding</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Event Security & Access Screening -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge"><i class="bi bi-shield-check me-1"></i> Entry Screening</span>
                    <!-- Security officer conducting metal detector screening -->
                    <img src="<?= asset('img/guards/guard-scanner-screening.jpg') ?>" alt="Security officer conducting handheld metal detector screening">
                </div>
                <div class="service-body-dark">
                    <h3>Event & Screening</h3>
                    <p>Robust entry screening with handheld metal detectors, visitor registers, and crowd control for galas, weddings, private exhibitions, and corporate venues.</p>
                    <div class="service-card-footer">
                        <span>Events & Screening</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>

            <!-- 4. Agency Security Force -->
            <div class="service-card-dark">
                <div class="service-img-container">
                    <span class="service-card-badge"><i class="bi bi-buildings-fill me-1"></i> Corporate Force</span>
                    <!-- Uniformed security officer at Eminence Corporate Park entrance -->
                    <img src="<?= asset('img/guards/corporate-park-guard.jpg') ?>" alt="Uniformed security officer at corporate park entrance">
                </div>
                <div class="service-body-dark">
                    <h3>Agency Security Force</h3>
                    <p>Comprehensive security personnel teams for commercial business parks, industrial facilities, healthcare centers, and multi-entrance properties with centralized supervisor oversight.</p>
                    <div class="service-card-footer">
                        <span>Corporate Force</span>
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==============================================================
     SECURITY & PROTECTION PROTOCOLS (ARMED VS UNARMED)
     ============================================================== -->
<section class="section-wrap" id="protocols">
    <div class="container">
        <div class="section-heading-box">
            <span class="section-tag-pill">Security & Protection Protocols</span>
            <h2 class="section-main-title">Unarmed Guarding & Licensed Armed Security</h2>
            <p class="section-subtext">Services are configured based on your operational environment. Armed protection is provided exclusively by personnel holding valid, verified statutory arms licenses in compliance with governing laws.</p>
        </div>

        <div class="protocols-grid-dark">
            <div class="protocol-card-dark">
                <div class="protocol-tag">
                    <i class="bi bi-shield-check"></i> Standard Security
                </div>
                <h3>Trained Unarmed Officers</h3>
                <p>The standard choice for corporate offices, apartment societies, retail showrooms, and routine access control. Focus on de-escalation, visitor screening, and perimeter presence.</p>
                <ul class="protocol-list">
                    <li><i class="bi bi-check2-circle"></i> Conflict de-escalation & situational awareness</li>
                    <li><i class="bi bi-check2-circle"></i> Gatekeeping, visitor registers & access surveillance</li>
                    <li><i class="bi bi-check2-circle"></i> Emergency evacuation & first response trained</li>
                    <li><i class="bi bi-check2-circle"></i> Digital shift check-in & verified reporting</li>
                </ul>
            </div>

            <div class="protocol-card-dark armed-card">
                <div class="protocol-tag">
                    <i class="bi bi-shield-lock-fill"></i> Statutorily Licensed
                </div>
                <h3>Licensed Armed Security Personnel</h3>
                <p>Armed security personnel deployed exclusively for high-risk assets, executive escorts, and secure transit, operating strictly with verified, government-issued arms licenses in compliance with statutory regulations.</p>
                <ul class="protocol-list">
                    <li><i class="bi bi-check2-circle"></i> Deployed strictly with valid, verified statutory arms licenses</li>
                    <li><i class="bi bi-check2-circle"></i> Firearm safety, retention, and legal compliance trained</li>
                    <li><i class="bi bi-check2-circle"></i> Executive escort & high-value transit protection</li>
                    <li><i class="bi bi-check2-circle"></i> Subject to internal command verification and authorization</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ==============================================================
     REQUEST GUARD / BOOKING FORM
     ============================================================== -->
<section class="section-wrap section-wrap-alt" id="hire">
    <div class="container">
        <div class="section-heading-box">
            <span class="section-tag-pill">Direct Guard Dispatch</span>
            <h2 class="section-main-title">Request Your Security Detail</h2>
            <p class="section-subtext">Submit your requirement below. Our operations team will call you back promptly to arrange your security deployment.</p>
        </div>

        <div class="booking-card-dark">
            <?php if ($inquirySuccess): ?>
                <div class="alert-dark-success">
                    <i class="bi bi-check-circle-fill me-1"></i> Thank you! Your security request has been received. Our operations team will call you within 10 minutes.
                </div>
            <?php endif; ?>

            <?php if ($inquiryError): ?>
                <div class="alert-dark-error">
                    <i class="bi bi-exclamation-circle-fill me-1"></i> <?= e($inquiryError) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="#hire">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="inquiry">

                <div class="form-row-2">
                    <div class="form-group-dark">
                        <label class="form-label-dark">Full Name / Organization</label>
                        <input type="text" name="name" class="form-control-dark" placeholder="e.g. Vikram Malhotra" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div class="form-group-dark">
                        <label class="form-label-dark">Mobile Number</label>
                        <input type="tel" name="phone" class="form-control-dark" placeholder="e.g. +91 98200 12345" required value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group-dark">
                        <label class="form-label-dark">Security Category</label>
                        <select name="service_type" class="form-select-dark">
                            <option value="bodyguard">Personal VIP Bodyguard</option>
                            <option value="building_security">Facility & Gated Society Security</option>
                            <option value="event_security">Event Security & Screening</option>
                            <option value="agency_security">Agency Security Force (Team Deployment)</option>
                            <option value="patrol">Mobile Patrol & Escort</option>
                        </select>
                    </div>
                    <div class="form-group-dark">
                        <label class="form-label-dark">Weapon Protocol</label>
                        <select name="is_armed" class="form-select-dark">
                            <option value="0">Unarmed Security (Standard)</option>
                            <option value="1">Licensed Armed Security (Subject to statutory licensing)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-dark" style="margin-bottom: 1.5rem;">
                    <label class="form-label-dark">City / Deployment Area</label>
                    <input type="text" name="city" class="form-control-dark" placeholder="e.g. Bandra Kurla Complex, Mumbai" value="<?= e($_POST['city'] ?? '') ?>">
                </div>

                <button type="submit" class="btn-purple-pill" style="width: 100%; justify-content: center; padding: 1rem;">
                    <i class="bi bi-shield-check"></i> Submit Guard Request (Instant Callback)
                </button>
            </form>
        </div>

        <!-- Emergency Dispatch Strip -->
        <div class="emergency-strip-dark">
            <div class="emergency-text">
                <h3>Need immediate emergency deployment?</h3>
                <p>Our 24/7 Operations Control Room coordinates urgent bodyguards and manned guarding across all locations.</p>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn-purple-pill" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);">
                    <i class="bi bi-whatsapp"></i> WhatsApp Dispatch
                </a>
                <a href="tel:<?= e($compPhone) ?>" class="btn-glass-pill">
                    <i class="bi bi-telephone-fill"></i> Direct Hotline: <?= e($compPhone) ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ==============================================================
     FOOTER
     ============================================================== -->
<footer class="footer-dark">
    <div class="container">
        <div class="footer-flex">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="nav-logo-circle" style="width: 32px; height: 32px; font-size: 0.95rem;">
                    <i class="bi bi-shield-shaded"></i>
                </div>
                <div>
                    <strong style="color: #fff; font-size: 1rem; display: block;"><?= e($compName) ?></strong>
                    <span style="color: var(--text-muted); font-size: 0.8rem;">Private Security & Close Protection Detail</span>
                </div>
            </div>

            <ul class="footer-links-list">
                <li><a href="tel:<?= e($compPhone) ?>"><i class="bi bi-telephone me-1"></i> <?= e($compPhone) ?></a></li>
                <li><a href="https://wa.me/<?= $phoneClean ?>" target="_blank"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a></li>
                <li><a href="<?= url('login.php') ?>"><i class="bi bi-shield-lock me-1"></i> Staff & Admin Command Center</a></li>
                <li><a href="<?= url('guard/login.php') ?>"><i class="bi bi-phone me-1"></i> Guard Duty App</a></li>
                <li><a href="<?= url('guard/install.php') ?>"><i class="bi bi-download me-1"></i> Mobile App Guide</a></li>
            </ul>
        </div>

        <div class="footer-bottom-bar">
            <div>&copy; <?= date('Y') ?> <?= e($compName) ?>. All rights reserved. Professional Security & Protection Services.</div>
            <div>Dedicated On-Site Security & Close Protection Services</div>
        </div>
    </div>
</footer>

</body>
</html>
