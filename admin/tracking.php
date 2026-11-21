<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$clients = q_all("SELECT DISTINCT c.id, c.name FROM clients c JOIN assignments a ON a.client_id = c.id AND a.status = 'active' ORDER BY c.name");
$selClient = get_int('client_id');
$focus = get_int('guard_id');

$pageTitle = 'Live Tracking';
$activeNav = 'tracking';
$useMap = true;
$pageScripts = ['js/tracking.js'];
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="tracking-wrap">
    <div class="tracking-side">
        <div class="side-head">
            <div class="d-flex align-items-center mb-2">
                <strong><span data-count="total">0</span> guards</strong>
                <span class="small text-muted ms-auto"><i class="bi bi-arrow-repeat"></i> <span data-live-updated>—</span></span>
            </div>
            <input type="search" class="form-control form-control-sm mb-2" id="trkSearch" placeholder="Search name, code, client, site…">
            <select class="form-select form-select-sm mb-2" id="trkClient">
                <option value="">All clients</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selClient === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="chip-filter" id="trkChips">
                <button class="btn btn-outline-secondary active" data-filter="all">All</button>
                <button class="btn btn-outline-danger" data-filter="sos">SOS <span data-count="sos">0</span></button>
                <button class="btn btn-outline-warning" data-filter="outside">Outside <span data-count="outside">0</span></button>
                <button class="btn btn-outline-success" data-filter="on_duty">Live <span data-count="on_duty">0</span></button>
                <button class="btn btn-outline-warning" data-filter="offline_duty">No signal <span data-count="offline_duty">0</span></button>
                <button class="btn btn-outline-primary" data-filter="assigned">Assigned <span data-count="assigned">0</span></button>
                <button class="btn btn-outline-secondary" data-filter="available">Available <span data-count="available">0</span></button>
            </div>
        </div>
        <div class="tracking-list" id="trkList"><div class="p-3 small text-muted">Loading…</div></div>
    </div>
    <div id="trkMap" class="tracking-map"></div>
</div>
<?php
$inlineScript = '<script>
document.addEventListener("DOMContentLoaded", function () {
    APP.liveMap({ el: "trkMap", list: "#trkList", search: "#trkSearch", clientFilter: "#trkClient", chips: "#trkChips",
                  refreshMs: 10000, focusGuard: ' . ($focus ?: 'null') . ' });
});
</script>';
require APP_ROOT . '/includes/layout/footer.php';
