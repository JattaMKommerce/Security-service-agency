<?php
/**
 * Hire out guards to a client (create) or edit an existing deployment.
 * One deployment row is created per selected guard.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();

$id = get_int('id');
$asg = $id ? q_one('SELECT a.*, g.full_name, g.employee_code, c.name AS client_name FROM assignments a
                    JOIN guards g ON g.id = a.guard_id JOIN clients c ON c.id = a.client_id WHERE a.id = ?', [$id]) : null;
if ($id && !$asg) {
    flash('danger', 'Deployment not found.');
    redirect('admin/assignments.php');
}
if ($asg && $asg['status'] !== 'active') {
    flash('warning', 'Only active deployments can be edited.');
    redirect('admin/assignment_view.php?id=' . $id);
}

$errors = [];
if (is_post()) {
    require_csrf();
    $d = [
        'site_name'         => post_str('site_name', 150),
        'site_address'      => post_str('site_address', 255),
        'site_lat'          => post_float('site_lat'),
        'site_lng'          => post_float('site_lng'),
        'geofence_radius_m' => max(50, min(20000, (int)post_int('geofence_radius_m', 300))),
        'start_date'        => post_str('start_date', 10),
        'end_date'          => post_str('end_date', 10),
        'shift_start'       => post_str('shift_start', 8),
        'shift_end'         => post_str('shift_end', 8),
        'rate_type'         => in_array($_POST['rate_type'] ?? '', ['daily', 'hourly', 'monthly'], true) ? $_POST['rate_type'] : 'daily',
        'rate'              => max(0, (float)(post_float('rate') ?? 0)),
        'notes'             => post_str('notes', 2000),
    ];
    $clientId = $asg ? (int)$asg['client_id'] : (int)post_int('client_id', 0);
    $guardIds = $asg ? [] : array_values(array_unique(array_filter(array_map('intval', (array)($_POST['guard_ids'] ?? [])))));

    if (!$asg) {
        $client = q_one("SELECT id, name, status FROM clients WHERE id = ?", [$clientId]);
        if (!$client) $errors[] = 'Choose the client who is hiring.';
        elseif ($client['status'] !== 'active') $errors[] = 'This client is inactive.';
        if (!$guardIds) $errors[] = 'Select at least one bodyguard.';
    }
    if (!$d['site_name']) $errors[] = 'Duty location / site name is required.';
    if (($d['site_lat'] === null) !== ($d['site_lng'] === null) || ($d['site_lat'] !== null && !valid_coords($d['site_lat'], $d['site_lng']))) {
        $errors[] = 'Pick a valid site location on the map.';
    }
    if ($d['site_lat'] === null) $errors[] = 'Pick the duty location on the map — it is needed for tracking and geofence alerts.';
    if (!valid_date($d['start_date'])) $errors[] = 'Start date is required.';
    if ($d['end_date'] && !valid_date($d['end_date'])) $errors[] = 'End date is not valid.';
    if ($d['end_date'] && valid_date($d['start_date']) && $d['end_date'] < $d['start_date']) $errors[] = 'End date cannot be before the start date.';
    foreach (['shift_start', 'shift_end'] as $k) {
        if ($d[$k] && !valid_time($d[$k])) $errors[] = 'Shift time is not valid.';
    }
    if (($d['shift_start'] === null) !== ($d['shift_end'] === null)) $errors[] = 'Enter both shift start and end, or leave both empty.';

    if (!$errors) {
        if ($asg) {
            $cols = array_keys($d);
            q('UPDATE assignments SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . " WHERE id = ? AND status = 'active'", [...array_values($d), $id]);
            log_activity('assignment.update', "#$id {$asg['employee_code']} @ {$asg['client_name']}");
            flash('success', 'Deployment updated.');
            redirect('admin/assignment_view.php?id=' . $id);
        }
        try {
            $created = db_tx(function () use ($d, $clientId, $guardIds, $admin) {
                $names = [];
                foreach ($guardIds as $gid) {
                    $g = q_one('SELECT id, full_name, status FROM guards WHERE id = ? FOR UPDATE', [$gid]);
                    if (!$g) throw new RuntimeException('A selected bodyguard no longer exists.');
                    if ($g['status'] !== 'active') throw new RuntimeException($g['full_name'] . ' is not active.');
                    $busy = q_one("SELECT c.name FROM assignments a JOIN clients c ON c.id = a.client_id WHERE a.guard_id = ? AND a.status = 'active'", [$gid]);
                    if ($busy) throw new RuntimeException($g['full_name'] . ' is already hired by ' . $busy['name'] . '.');
                    $row = $d + ['client_id' => $clientId, 'guard_id' => $gid, 'status' => 'active', 'created_by' => $admin['id']];
                    q('INSERT INTO assignments (' . implode(', ', array_keys($row)) . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')', array_values($row));
                    $names[] = $g['full_name'];
                }
                return $names;
            });
            $clientName = q_val('SELECT name FROM clients WHERE id = ?', [$clientId]);
            log_activity('assignment.create', count($created) . " guard(s) to $clientName: " . implode(', ', $created));
            flash('success', count($created) . ' bodyguard(s) hired by ' . $clientName . ': ' . implode(', ', $created) . '.');
            redirect('admin/client_view.php?id=' . $clientId);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors[] = 'One of the selected guards was just hired by someone else. Please review the list.';
            } else {
                throw $ex;
            }
        }
    }
    $form = $d + ['client_id' => $clientId, 'guard_ids' => $guardIds];
} else {
    $form = $asg ?: [
        'client_id' => get_int('client_id'), 'guard_ids' => get_int('guard_id') ? [get_int('guard_id')] : [],
        'start_date' => date('Y-m-d'), 'geofence_radius_m' => 300, 'rate_type' => 'daily', 'rate' => '',
    ];
    if (!$asg && $form['client_id']) {
        // Pre-fill the site from the client's latest deployment.
        $prev = q_one('SELECT site_name, site_address, site_lat, site_lng, geofence_radius_m, shift_start, shift_end, rate_type, rate
                         FROM assignments WHERE client_id = ? ORDER BY id DESC LIMIT 1', [$form['client_id']]);
        if ($prev) $form = array_merge($form, $prev);
        elseif ($cl = q_one('SELECT name, address, city FROM clients WHERE id = ?', [$form['client_id']])) {
            $form['site_address'] = trim(($cl['address'] ?? '') . ' ' . ($cl['city'] ?? ''));
        }
    }
}
$f = fn($k) => e($form[$k] ?? '');

$clients = $asg ? [] : q_all("SELECT id, name, client_code FROM clients WHERE status = 'active' ORDER BY name");
$available = $asg ? [] : q_all("SELECT g.id, g.employee_code, g.full_name, g.phone, g.photo, g.is_armed, g.city, g.experience_years, g.skills
                                  FROM guards g WHERE g.status = 'active'
                                   AND NOT EXISTS (SELECT 1 FROM assignments a WHERE a.guard_id = g.id AND a.status = 'active')
                                 ORDER BY g.full_name");
$selected = array_map('intval', (array)($form['guard_ids'] ?? []));

$pageTitle = $asg ? 'Edit Deployment' : 'Hire Bodyguards';
$activeNav = 'assignments';
$useMap = true;
require APP_ROOT . '/includes/layout/header.php';
?>
<div class="mb-3"><a href="<?= url($asg ? 'admin/assignment_view.php?id=' . $id : 'admin/assignments.php') ?>" class="small"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($errors): ?>
    <div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="form-section-title">Who is hiring</div>
                <?php if ($asg): ?>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="hired-by fs-6"><i class="bi bi-briefcase-fill"></i> <?= e($asg['client_name']) ?></div>
                        <div class="fw-semibold"><i class="bi bi-person-badge"></i> <?= e($asg['full_name']) ?> (<?= e($asg['employee_code']) ?>)</div>
                    </div>
                <?php else: ?>
                    <label class="form-label required">Client</label>
                    <div class="d-flex gap-2">
                        <select name="client_id" class="form-select" required id="clientSel">
                            <option value="">Select client…</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$form['client_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> (<?= e($c['client_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <a href="<?= url('admin/client_form.php') ?>" class="btn btn-outline-secondary text-nowrap"><i class="bi bi-plus"></i> New</a>
                    </div>
                <?php endif; ?>

                <div class="form-section-title">Duty location (client site)</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label required">Site name</label><input name="site_name" class="form-control" value="<?= $f('site_name') ?>" placeholder="e.g. Residence, Office HQ, Wedding venue" required></div>
                    <div class="col-md-6"><label class="form-label">Site address</label><input name="site_address" id="siteAddress" class="form-control" value="<?= $f('site_address') ?>"></div>
                    <div class="col-12">
                        <div class="input-group">
                            <input type="text" class="form-control" id="placeSearch" placeholder="Search a place or address on OpenStreetMap…">
                            <button type="button" class="btn btn-outline-primary" id="placeSearchBtn"><i class="bi bi-search"></i> Search</button>
                            <button type="button" class="btn btn-outline-secondary" id="locateBtn" title="Use my current location"><i class="bi bi-crosshair"></i></button>
                        </div>
                        <div class="list-group mt-1" id="placeResults"></div>
                    </div>
                    <div class="col-12">
                        <div id="sitePicker" class="map-picker"></div>
                        <div class="small text-muted mt-1"><i class="bi bi-info-circle"></i> Click the map or drag the pin to the exact duty point. The circle is the allowed area (geofence).</div>
                    </div>
                    <div class="col-md-4"><label class="form-label required">Latitude</label><input name="site_lat" id="siteLat" class="form-control" value="<?= $f('site_lat') ?>" required></div>
                    <div class="col-md-4"><label class="form-label required">Longitude</label><input name="site_lng" id="siteLng" class="form-control" value="<?= $f('site_lng') ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Geofence radius (m)</label><input type="number" min="50" max="20000" step="10" name="geofence_radius_m" id="siteRadius" class="form-control" value="<?= $f('geofence_radius_m') ?>"></div>
                </div>

                <div class="form-section-title">Period, shift & rate</div>
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label required">Start date</label><input type="date" name="start_date" class="form-control" value="<?= $f('start_date') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">End date</label><input type="date" name="end_date" class="form-control" value="<?= $f('end_date') ?>"><div class="form-text">Empty = until further notice</div></div>
                    <div class="col-md-3"><label class="form-label">Shift start</label><input type="time" name="shift_start" class="form-control" value="<?= e(substr((string)($form['shift_start'] ?? ''), 0, 5)) ?>"></div>
                    <div class="col-md-3"><label class="form-label">Shift end</label><input type="time" name="shift_end" class="form-control" value="<?= e(substr((string)($form['shift_end'] ?? ''), 0, 5)) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Billing basis</label>
                        <select name="rate_type" class="form-select">
                            <?php foreach (['daily' => 'Per day', 'hourly' => 'Per hour (from attendance)', 'monthly' => 'Per month'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= ($form['rate_type'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Rate per guard (<?= e(setting('currency_symbol', '₹')) ?>)</label><input type="number" step="0.01" min="0" name="rate" class="form-control" value="<?= $f('rate') ?>"></div>
                    <div class="col-12"><label class="form-label">Notes / instructions</label><textarea name="notes" class="form-control" rows="2"><?= $f('notes') ?></textarea></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <?php if (!$asg): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">Select bodyguards <span class="badge bg-primary ms-2" id="selCount">0</span>
                <span class="small text-muted ms-auto"><?= count($available) ?> available</span></div>
            <div class="card-body pb-2">
                <input type="search" class="form-control form-control-sm mb-2" id="guardFilter" placeholder="Filter by name, code, city, skill…">
                <div class="d-flex gap-2 small">
                    <a href="#" id="selAll">Select visible</a> · <a href="#" id="selNone">Clear</a>
                    <label class="ms-auto"><input type="checkbox" id="armedOnly" class="form-check-input"> Armed only</label>
                </div>
            </div>
            <div class="list-group list-group-flush" style="max-height:560px;overflow-y:auto" id="guardList">
                <?php if (!$available): ?>
                    <div class="empty-state"><i class="bi bi-people"></i>No bodyguards are available right now.<br>All active guards are already hired.</div>
                <?php endif; ?>
                <?php foreach ($available as $g): ?>
                    <label class="list-group-item d-flex gap-2 align-items-center guard-opt" data-armed="<?= (int)$g['is_armed'] ?>"
                           data-text="<?= e(mb_strtolower($g['full_name'] . ' ' . $g['employee_code'] . ' ' . $g['city'] . ' ' . $g['skills'])) ?>">
                        <input class="form-check-input mt-0" type="checkbox" name="guard_ids[]" value="<?= (int)$g['id'] ?>" <?= in_array((int)$g['id'], $selected, true) ? 'checked' : '' ?>>
                        <?= avatar($g['full_name'], $g['photo'], 'sm') ?>
                        <span class="min-w-0 flex-fill"><span class="fw-semibold d-block text-truncate"><?= e($g['full_name']) ?>
                            <?= $g['is_armed'] ? '<i class="bi bi-shield-fill-check text-warning" title="Armed"></i>' : '' ?></span>
                            <span class="small text-muted"><?= e($g['employee_code']) ?> · <?= (int)$g['experience_years'] ?> yrs<?= $g['city'] ? ' · ' . e($g['city']) : '' ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-check2-circle"></i> <?= $asg ? 'Save deployment' : 'Hire selected bodyguards' ?></button>
    </div>
</form>

<?php
$inlineScript = '<script>
document.addEventListener("DOMContentLoaded", function () {
    APP.map.picker({ el: "sitePicker", lat: "#siteLat", lng: "#siteLng", radius: "#siteRadius",
        search: "#placeSearch", searchBtn: "#placeSearchBtn", results: "#placeResults", locateBtn: "#locateBtn", address: "#siteAddress" });
    const list = document.getElementById("guardList");
    if (!list) return;
    const opts = [...list.querySelectorAll(".guard-opt")];
    const count = () => document.getElementById("selCount").textContent = list.querySelectorAll("input:checked").length;
    const filter = () => {
        const t = document.getElementById("guardFilter").value.trim().toLowerCase();
        const armed = document.getElementById("armedOnly").checked;
        opts.forEach(o => o.classList.toggle("d-none", (t && !o.dataset.text.includes(t)) || (armed && o.dataset.armed !== "1")));
    };
    document.getElementById("guardFilter").addEventListener("input", filter);
    document.getElementById("armedOnly").addEventListener("change", filter);
    list.addEventListener("change", count);
    document.getElementById("selAll").addEventListener("click", e => { e.preventDefault(); opts.filter(o => !o.classList.contains("d-none")).forEach(o => o.querySelector("input").checked = true); count(); });
    document.getElementById("selNone").addEventListener("click", e => { e.preventDefault(); opts.forEach(o => o.querySelector("input").checked = false); count(); });
    count();
    const cs = document.getElementById("clientSel");
    if (cs) cs.addEventListener("change", () => { if (!document.querySelector("[name=site_name]").value && cs.value) {
        const u = new URL(location.href); u.searchParams.set("client_id", cs.value);
        list.querySelectorAll("input:checked").length || (location.href = u.toString()); } });
});
</script>';
require APP_ROOT . '/includes/layout/footer.php';
