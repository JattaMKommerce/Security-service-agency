<?php
require __DIR__ . '/_common.php';
$guard = guard_api_post();
$in = request_data();
[$lat, $lng, $acc] = input_coords($in);
if ($lat === null) {
    json_error('Your location is needed to start duty. Turn on GPS / location and allow it for this app.');
}
try {
    $r = start_shift($guard, $lat, $lng, $acc);
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 422);
}
$g = q_one('SELECT * FROM guards WHERE id = ?', [$guard['id']]);
record_guard_location($g, $lat, $lng, $acc, null, null, isset($in['battery']) ? (int)$in['battery'] : null);
log_activity('attendance.check_in', ($r['assignment']['client_name'] ?? 'no client') . ($r['distance_m'] !== null ? ', ' . $r['distance_m'] . ' m from site' : ''));
json_out(guard_state($g) + ['message' => 'Duty started. Keep this app open while on duty.']);
