<?php
/**
 * Admin layout header.
 * Variables: $pageTitle (string), $activeNav (string), $useMap (bool), $useCharts (bool)
 */
$admin = current_admin();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeNav = $activeNav ?? '';
$useMap = $useMap ?? false;
$useCharts = $useCharts ?? false;
$openAlerts = (int)q_val("SELECT COUNT(*) FROM alerts WHERE status <> 'resolved'");
$newInquiries = (int)q_val("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");

$nav = [
    ['dashboard',   'admin/index.php',       'bi-speedometer2',      'Dashboard'],
    ['tracking',    'admin/tracking.php',    'bi-geo-alt-fill',      'Live Tracking'],
    ['guards',      'admin/guards.php',      'bi-person-badge',      'Guards & Personnel'],
    ['clients',     'admin/clients.php',     'bi-building',          'Clients'],
    ['assignments', 'admin/assignments.php', 'bi-briefcase',         'Hiring / Deployments'],
    ['inquiries',   'admin/inquiries.php',   'bi-chat-left-dots',    'Web Inquiries'],
    ['attendance',  'admin/attendance.php',  'bi-calendar-check',    'Attendance'],
    ['alerts',      'admin/alerts.php',      'bi-exclamation-octagon', 'Alerts'],
    ['invoices',    'admin/invoices.php',    'bi-receipt',           'Invoices'],
    ['settings',    'admin/settings.php',    'bi-gear',              'Settings'],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · <?= e(company_name()) ?></title>
    <link rel="icon" href="<?= asset('img/icon.svg') ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <?php if ($useMap): ?>
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
</head>
<body class="admin-body">
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand" href="<?= url('admin/index.php') ?>">
            <span class="brand-mark"><i class="bi bi-shield-shaded"></i></span>
            <span class="brand-text"><?= e(company_name()) ?><small>Command Center</small></span>
        </a>
        <nav class="sidebar-nav">
            <?php foreach ($nav as [$key, $href, $icon, $text]): ?>
                <a href="<?= url($href) ?>" class="nav-item-link<?= $activeNav === $key ? ' active' : '' ?>">
                    <i class="bi <?= $icon ?>"></i><span><?= e($text) ?></span>
                    <?php if ($key === 'alerts'): ?>
                        <span class="badge rounded-pill bg-danger ms-auto js-alert-count<?= $openAlerts ? '' : ' d-none' ?>"><?= $openAlerts ?></span>
                    <?php elseif ($key === 'inquiries' && $newInquiries > 0): ?>
                        <span class="badge rounded-pill bg-danger ms-auto"><?= $newInquiries ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer d-flex flex-column gap-1">
            <a href="<?= url('index.php') ?>" target="_blank" class="small"><i class="bi bi-globe"></i> View Public Website</a>
            <a href="<?= url('guard/') ?>" target="_blank" class="small"><i class="bi bi-phone"></i> Open Guard App</a>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
        <header class="topbar">
            <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                <button class="btn btn-icon d-lg-none flex-shrink-0 topbar-burger-btn" id="sidebarToggle" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
                <div class="topbar-divider d-lg-none"></div>
                <div class="topbar-brand-wrap d-flex align-items-center gap-2 min-w-0">
                    <span class="topbar-shield-icon d-lg-none flex-shrink-0"><i class="bi bi-shield-check"></i></span>
                    <div class="min-w-0 lh-1">
                        <?php if ($pageTitle === 'Guards & Bodyguards'): ?>
                            <div class="topbar-title-multiline d-lg-none">
                                <span class="d-block topbar-title-line">Guards &amp;</span>
                                <span class="d-block topbar-title-line">Bodyguards</span>
                            </div>
                            <h1 class="topbar-title d-none d-lg-block text-truncate"><?= e($pageTitle) ?></h1>
                        <?php else: ?>
                            <h1 class="topbar-title text-truncate"><?= e($pageTitle) ?></h1>
                        <?php endif; ?>
                        <div class="topbar-subtitle d-lg-none text-truncate">Jatta Security</div>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <div class="dropdown">
                    <button class="btn btn-icon position-relative topbar-bell-btn" data-bs-toggle="dropdown" aria-label="Alerts" id="alertBell">
                        <i class="bi bi-bell fs-5"></i>
                        <span class="bell-dot js-alert-count<?= $openAlerts ? '' : ' d-none' ?>"><?= $openAlerts ?></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end alert-menu p-0">
                        <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                            <strong>Open alerts</strong>
                            <a href="<?= url('admin/alerts.php') ?>" class="small">View all</a>
                        </div>
                        <div id="alertMenuList" class="alert-menu-list"><div class="p-3 small text-muted">Loading…</div></div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-user dropdown-toggle topbar-user-pill" data-bs-toggle="dropdown">
                        <span class="avatar avatar-sm avatar-initials"><?= e(initials($admin['name'])) ?></span>
                        <span class="d-none d-md-inline ms-1"><?= e($admin['name']) ?></span>
                        <i class="bi bi-caret-down-fill topbar-caret d-md-none ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted"><?= e($admin['email']) ?><br><?= e(label($admin['role'])) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('admin/settings.php?tab=account') ?>"><i class="bi bi-key me-2"></i>Change password</a></li>
                        <li>
                            <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?>
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content">
            <?php foreach (flashes() as $__flash): ?>
                <div class="alert alert-<?= e($__flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($__flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
