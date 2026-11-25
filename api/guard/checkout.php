<?php
require __DIR__ . '/_common.php';
$guard = guard_api_post();
$in = request_data();
[$lat, $lng, $acc] = input_coords($in);
if ($lat !== null) {
    record_guard_location($guard, $lat, $lng, $acc, null, null, isset($in['battery']) ? (int)$in['battery'] : null);
}
$r = end_shift((int)$guard['id'], $lat, $lng);
if ($r) {
    log_activity('attendance.check_out', 'shift ' . fmt_minutes($r['duration_minutes']));
}
$g = q_one('SELECT * FROM guards WHERE id = ?', [$guard['id']]);
$msg = 'You were not on duty.';
if ($r) {
    $msg = 'Duty ended. Total: ' . fmt_minutes($r['duration_minutes']) . '.';
    if ($r['overtime_minutes']) {
        $msg .= ' Overtime: ' . fmt_minutes($r['overtime_minutes']) . ($r['approved_ot_minutes'] < $r['overtime_minutes'] ? ' (' . fmt_minutes($r['overtime_minutes'] - $r['approved_ot_minutes']) . ' not approved)' : ' (approved)') . '.';
    }
}
json_out(guard_state($g) + ['message' => $msg]);
