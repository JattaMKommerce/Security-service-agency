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
                <span class="small text-success ms-2" title="Guards sharing live location now"><i class="bi bi-broadcast"></i> <span data-count="sharing">0</span> sharing</span>
                <span class="small text-muted ms-auto"><i class="bi bi-arrow-repeat"></i> <span data-live-updated>—</span></span>
            </div>
            <input type="search" class="form-control form-control-sm mb-2" id="trkSearch" placeholder="Search name, code, client, site…">
            <div class="row g-1 mb-2">
                <div class="col-6">
                    <select class="form-select form-select-sm" id="trkCategory">
                        <option value="">All roles</option>
                        <?php foreach (guard_categories() as $ck => $cv): ?>
                            <option value="<?= $ck ?>"><?= e($cv) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select class="form-select form-select-sm" id="trkWeapon">
                        <option value="">Armed/Unarmed</option>
                        <option value="armed">Armed (Gun)</option>
                        <option value="unarmed">Unarmed</option>
                    </select>
                </div>
            </div>
            <select class="form-select form-select-sm mb-2" id="trkClient">
                <option value="">All clients</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $selClient === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="chip-filter" id="trkChips">
                <button class="btn btn-outline-secondary active" data-filter="all">All</button>
                <button class="btn btn-outline-danger" data-filter="emergency"><i class="bi bi-circle-fill" style="color:#dc2626"></i> Emergency <span data-count="emergency">0</span></button>
                <button class="btn btn-outline-warning" data-filter="late"><i class="bi bi-circle-fill" style="color:#ea580c"></i> Late <span data-count="late">0</span></button>
                <button class="btn btn-outline-success" data-filter="on_duty"><i class="bi bi-circle-fill" style="color:#16a34a"></i> On duty <span data-count="on_duty">0</span></button>
                <button class="btn btn-outline-primary" data-filter="patrol"><i class="bi bi-circle-fill" style="color:#2563eb"></i> On patrol <span data-count="patrol">0</span></button>
                <button class="btn btn-outline-secondary" data-filter="off_duty"><i class="bi bi-circle-fill" style="color:#94a3b8"></i> Off duty <span data-count="off_duty">0</span></button>
                <button class="btn btn-outline-danger" data-filter="issues" title="Outside site, no signal, GPS off or not moving"><i class="bi bi-exclamation-triangle"></i> Problems <span data-count="issues">0</span></button>
            </div>
        </div>
        <div class="tracking-list" id="trkList"><div class="p-3 small text-muted">Loading…</div></div>
    </div>
    <div class="tracking-map-wrap">
        <div id="trkMap" class="tracking-map"></div>
        <div class="hist-panel" id="histPanel">
            <button class="hist-toggle" data-h="toggle" type="button"><i class="bi bi-clock-history"></i> <span>Location history</span></button>
            <div class="hist-body">
                <select class="form-select form-select-sm mb-2" data-h="guard" aria-label="Guard"><option value="">Select guard…</option></select>
                <div class="d-flex gap-2 mb-2">
                    <input type="date" class="form-control form-control-sm" data-h="date" aria-label="Date">
                    <button class="btn btn-sm btn-primary text-nowrap" data-h="load" type="button">Show route</button>
                </div>
                <button class="btn btn-sm btn-outline-primary w-100 mb-2" data-h="duty" type="button"><i class="bi bi-person-walking"></i> Movement during current duty</button>
                <div data-h="result" class="small"></div>
                <div data-h="player" class="hist-player d-none">
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-dark" data-h="play" type="button" aria-label="Play"><i class="bi bi-play-fill"></i></button>
                        <input type="range" class="form-range flex-fill" min="0" max="0" value="0" data-h="slider" aria-label="Playback position">
                    </div>
                    <div class="small fw-semibold text-center" data-h="clock">—</div>
                    <button class="btn btn-link btn-sm w-100 js-clear-trail" type="button">Clear route</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$inlineScript = '<script>
document.addEventListener("DOMContentLoaded", function () {
    APP.liveMap({ el: "trkMap", list: "#trkList", search: "#trkSearch", categoryFilter: "#trkCategory", weaponFilter: "#trkWeapon", clientFilter: "#trkClient", chips: "#trkChips",
                  history: "#histPanel", refreshMs: 10000, focusGuard: ' . ($focus ?: 'null') . ' });
});
</script>';
require APP_ROOT . '/includes/layout/footer.php';
