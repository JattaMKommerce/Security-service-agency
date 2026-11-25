<?php
/**
 * Receives GPS fixes from the guard's phone.
 * Body: { points: [ {lat, lng, accuracy, speed, heading, battery, ts} ], gps: ok|off|denied }  (ts = ms since epoch)
 * Points queued while offline are accepted for up to 12 hours. An empty
 * points list is a heartbeat (phone online, e.g. while GPS is off).
 */
require __DIR__ . '/_common.php';
$guard = guard_api_post();
session_write_close();

$in = request_data();
$points = isset($in['points']) && is_array($in['points']) ? array_slice($in['points'], 0, 60) : [$in];
usort($points, fn($a, $b) => (float)($a['ts'] ?? 0) <=> (float)($b['ts'] ?? 0));

$accepted = 0;
$last = null;
foreach ($points as $pt) {
    if (!is_array($pt)) continue;
    [$lat, $lng, $acc] = input_coords($pt);
    if ($lat === null) continue;
    // Ignore wildly inaccurate fixes (e.g. cell-tower only) for history.
    if ($acc !== null && $acc > 2000) continue;
    $ts = null;
    if (isset($pt['ts']) && is_numeric($pt['ts'])) {
        $sec = (int)floor((float)$pt['ts'] / 1000);
        if ($sec <= time() + 60 && $sec >= time() - 12 * 3600) {
            $ts = date('Y-m-d H:i:s', min($sec, time()));
        }
    }
    $speed = isset($pt['speed']) && is_numeric($pt['speed']) && $pt['speed'] >= 0 ? min(150.0, (float)$pt['speed']) : null;
    $heading = isset($pt['heading']) && is_numeric($pt['heading']) ? fmod((float)$pt['heading'], 360) : null;
    $battery = isset($pt['battery']) && is_numeric($pt['battery']) ? max(0, min(100, (int)$pt['battery'])) : null;
    // Re-read duty state each time (it can change between queued points).
    $g = q_one('SELECT * FROM guards WHERE id = ?', [$guard['id']]);
    $last = record_guard_location($g, $lat, $lng, $acc, $speed, $heading, $battery, $ts);
    $accepted++;
}

maybe_run_maintenance();
maybe_run_live_checks();
json_out(['ok' => true, 'accepted' => $accepted, 'geofence' => $last ? ['inside' => $last['inside'], 'distance_m' => $last['distance_m'], 'alert' => $last['alert']] : null]);
