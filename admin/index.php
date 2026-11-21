<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();


$today = date('Y-m-d');
$stats = [
    'guards'      => (int)q_val("SELECT COUNT(*) FROM guards WHERE status <> 'inactive'"),
    'on_duty'     => (int)q_val('SELECT COUNT(*) FROM guards WHERE is_on_duty = 1'),
    'hired'       => (int)q_val("SELECT COUNT(*) FROM assignments WHERE status = 'active'"),
    'clients'     => (int)q_val("SELECT COUNT(DISTINCT client_id) FROM assignments WHERE status = 'active'"),
    'open_alerts' => (int)q_val("SELECT COUNT(*) FROM alerts WHERE status <> 'resolved'"),
    'checkins'    => (int)q_val('SELECT COUNT(DISTINCT guard_id) FROM attendance WHERE check_in_at >= ?', [$today . ' 00:00:00']),
];
$stats['available'] = (int)q_val("SELECT COUNT(*) FROM guards g WHERE g.status = 'active'
                                   AND NOT EXISTS (SELECT 1 FROM assignments a WHERE a.guard_id = g.id AND a.status = 'active')");

$sos = q_all("SELECT al.id, al.created_at, g.full_name, c.name AS client_name
                FROM alerts al JOIN guards g ON g.id = al.guard_id LEFT JOIN clients c ON c.id = al.client_id
               WHERE al.type = 'sos' AND al.status <> 'resolved' ORDER BY al.id DESC");

$recentAlerts = q_all("SELECT al.*, g.full_name, c.name AS client_name
                         FROM alerts al JOIN guards g ON g.id = al.guard_id LEFT JOIN clients c ON c.id = al.client_id
                        ORDER BY al.id DESC LIMIT 6");

$topClients = q_all("SELECT c.id, c.name, c.client_code, COUNT(a.id) AS guards,
                            SUM(g.is_on_duty) AS on_duty
                       FROM assignments a JOIN clients c ON c.id = a.client_id JOIN guards g ON g.id = a.guard_id
                      WHERE a.status = 'active'
                      GROUP BY c.id, c.name, c.client_code ORDER BY guards DESC LIMIT 6");

// Duty hours for the last 7 days (closed + currently open shifts)
$chart = [];
for ($i = 6; $i >= 0; $i--) {
    $chart[date('Y-m-d', strtotime("-$i day"))] = 0.0;
}
$rows = q_all("SELECT DATE(check_in_at) d,
                      SUM(COALESCE(duration_minutes, TIMESTAMPDIFF(MINUTE, check_in_at, NOW()))) m
                 FROM attendance WHERE check_in_at >= ? GROUP BY DATE(check_in_at)",
              [array_key_first($chart) . ' 00:00:00']);
foreach ($rows as $r) {
    if (isset($chart[$r['d']])) $chart[$r['d']] = round($r['m'] / 60, 1);
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$useMap = true;
$useCharts = true;
$pageScripts = ['js/tracking.js'];
require APP_ROOT . '/includes/layout/header.php';
?>

<?php foreach ($sos as $s): ?>
    <a href="<?= url('admin/alerts.php?id=' . $s['id']) ?>" class="sos-banner mb-3 text-white">
        <i class="bi bi-exclamation-octagon-fill fs-4"></i>
        <div><strong>SOS from <?= e($s['full_name']) ?></strong><?= $s['client_name'] ? ' · at ' . e($s['client_name']) : '' ?>
            <div class="small opacity-75"><?= e(fmt_datetime($s['created_at'])) ?> (<?= e(time_ago($s['created_at'])) ?>) — click to respond</div></div>
        <i class="bi bi-chevron-right ms-auto"></i>
    </a>
<?php endforeach; ?>

<div class="row g-3 mb-3">
    <?php
    $cards = [
        ['Total Bodyguards', $stats['guards'], 'bi-people-fill', 'tint-blue', 'admin/guards.php'],
        ['On Duty Now', $stats['on_duty'], 'bi-broadcast', 'tint-green', 'admin/tracking.php'],
        ['Hired (Deployed)', $stats['hired'], 'bi-briefcase-fill', 'tint-violet', 'admin/assignments.php'],
        ['Available', $stats['available'], 'bi-person-check-fill', 'tint-slate', 'admin/guards.php?availability=available'],
        ['Clients Served', $stats['clients'], 'bi-building-fill', 'tint-amber', 'admin/clients.php'],
        ['Open Alerts', $stats['open_alerts'], 'bi-exclamation-triangle-fill', 'tint-red', 'admin/alerts.php'],
    ];
    foreach ($cards as [$lbl, $val, $icon, $tint, $href]): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= url($href) ?>" class="card stat-card text-reset">
                <span class="stat-icon <?= $tint ?>"><i class="bi <?= $icon ?>"></i></span>
                <div><div class="stat-value"><?= $val ?></div><div class="stat-label"><?= e($lbl) ?></div></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <span><i class="bi bi-geo-alt-fill text-primary me-1"></i> Live map</span>
                <span class="small text-muted ms-2">updated <span data-live-updated>—</span></span>
                <a href="<?= url('admin/tracking.php') ?>" class="btn btn-sm btn-outline-primary ms-auto">Full screen tracking</a>
            </div>
            <div class="card-body p-0"><div id="dashMap" style="height:420px"></div></div>
            <div class="card-footer bg-white small d-flex flex-wrap gap-3">
                <span><span class="status-dot" style="background:#16a34a"></span>Live <strong data-count="on_duty">0</strong></span>
                <span><span class="status-dot" style="background:#ca8a04"></span>No signal <strong data-count="offline_duty">0</strong></span>
                <span><span class="status-dot" style="background:#ea580c"></span>Outside site <strong data-count="outside">0</strong></span>
                <span><span class="status-dot" style="background:#dc2626"></span>SOS <strong data-count="sos">0</strong></span>
                <span><span class="status-dot" style="background:#2563eb"></span>Assigned <strong data-count="assigned">0</strong></span>
                <span><span class="status-dot" style="background:#64748b"></span>Available <strong data-count="available">0</strong></span>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex"><span>Recent alerts</span><a class="ms-auto small" href="<?= url('admin/alerts.php') ?>">View all</a></div>
            <div class="list-group list-group-flush">
                <?php if (!$recentAlerts): ?>
                    <div class="empty-state"><i class="bi bi-shield-check"></i>No alerts yet</div>
                <?php endif; ?>
                <?php foreach ($recentAlerts as $a): ?>
                    <a href="<?= url('admin/alerts.php?id=' . $a['id']) ?>" class="list-group-item list-group-item-action d-flex gap-2 py-3">
                        <span class="stat-icon <?= $a['type'] === 'sos' ? 'tint-red' : 'tint-amber' ?>" style="width:36px;height:36px;flex-basis:36px;font-size:1rem">
                            <i class="bi <?= $a['type'] === 'sos' ? 'bi-exclamation-octagon-fill' : 'bi-sign-turn-right-fill' ?>"></i></span>
                        <div class="min-w-0 flex-fill">
                            <div class="d-flex"><strong class="text-truncate"><?= e($a['full_name']) ?></strong><span class="ms-auto"><?= status_badge($a['status']) ?></span></div>
                            <div class="small text-muted text-truncate"><?= $a['type'] === 'sos' ? 'SOS' : 'Left site' ?> · <?= e($a['client_name'] ?? '—') ?> · <?= e(time_ago($a['created_at'])) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Duty hours — last 7 days <span class="small text-muted ms-2">Guards checked in today: <strong><?= $stats['checkins'] ?></strong></span></div>
            <div class="card-body"><canvas id="hoursChart" height="120"></canvas></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card table-card h-100">
            <div class="card-header d-flex"><span>Clients with most guards hired</span><a class="ms-auto small" href="<?= url('admin/assignments.php') ?>">All deployments</a></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Client</th><th class="text-end">Hired</th><th class="text-end">On duty</th></tr></thead>
                    <tbody>
                    <?php if (!$topClients): ?><tr><td colspan="3" class="text-center text-muted py-4">No guards hired yet</td></tr><?php endif; ?>
                    <?php foreach ($topClients as $c): ?>
                        <tr>
                            <td><a href="<?= url('admin/client_view.php?id=' . $c['id']) ?>" class="fw-semibold"><?= e($c['name']) ?></a><div class="small text-muted"><?= e($c['client_code']) ?></div></td>
                            <td class="text-end fw-semibold"><?= (int)$c['guards'] ?></td>
                            <td class="text-end"><?= (int)$c['on_duty'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$labels = array_map(fn($d) => date('D d', strtotime($d)), array_keys($chart));
$inlineScript = '<script>
document.addEventListener("DOMContentLoaded", function () {
    APP.liveMap({ el: "dashMap", refreshMs: 20000 });
    new Chart(document.getElementById("hoursChart"), {
        type: "bar",
        data: { labels: ' . json_encode($labels) . ', datasets: [{ label: "Duty hours", data: ' . json_encode(array_values($chart)) . ',
            backgroundColor: "#1d4ed8", borderRadius: 6, maxBarThickness: 38 }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: "#eef2f7" } }, x: { grid: { display: false } } } }
    });
});
</script>';
require APP_ROOT . '/includes/layout/footer.php';
