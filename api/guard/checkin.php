<?php
require __DIR__ . '/_common.php';
$guard = guard_api_post();
$in = request_data();
[$lat, $lng, $acc] = input_coords($in);
if ($lat === null) {
    json_error('Your location is needed to start duty. Turn on GPS / location and allow it for this app.');
}

// Duty selfie: proves the right guard is starting the duty.
$selfie = null;
$sent = isset($in['selfie']) && is_string($in['selfie']) && $in['selfie'] !== '';
if (setting('require_selfie', '1') === '1' && !$sent) {
    json_error('Please take a selfie to start your duty.', 422);
}
if ($sent) {
    try {
        $selfie = store_selfie($in['selfie']);
    } catch (RuntimeException $e) {
        json_error($e->getMessage(), 422);
    }
}

try {
    $r = start_shift($guard, $lat, $lng, $acc, $selfie);
} catch (RuntimeException $e) {
    if ($selfie && ($p = selfie_file($selfie))) @unlink($p);
    json_error($e->getMessage(), 422);
}
$g = q_one('SELECT * FROM guards WHERE id = ?', [$guard['id']]);
record_guard_location($g, $lat, $lng, $acc, null, null, isset($in['battery']) ? (int)$in['battery'] : null);
log_activity('attendance.check_in', ($r['assignment']['client_name'] ?? 'no client') . ($r['distance_m'] !== null ? ', ' . $r['distance_m'] . ' m from site' : '')
    . ($r['late_minutes'] ? ', ' . $r['late_minutes'] . ' min late' : ''));
$msg = 'Duty started. Keep this app open while on duty.';
if ($r['late_minutes']) {
    $msg = 'Duty started (' . fmt_minutes($r['late_minutes']) . ' late). Keep this app open while on duty.';
}
json_out(guard_state($g) + ['message' => $msg]);
