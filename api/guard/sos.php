<?php
/**
 * Panic button. Body: { action: "raise"|"cancel", lat, lng, accuracy, note }
 */
require __DIR__ . '/_common.php';
$guard = guard_api_post();
$in = request_data();
$action = ($in['action'] ?? 'raise') === 'cancel' ? 'cancel' : 'raise';
[$lat, $lng, $acc] = input_coords($in);
$gid = (int)$guard['id'];

if ($action === 'raise') {
    $existing = q_one("SELECT id FROM alerts WHERE guard_id = ? AND type = 'sos' AND status <> 'resolved' LIMIT 1", [$gid]);
    if ($lat !== null) {
        record_guard_location($guard, $lat, $lng, $acc, null, null, isset($in['battery']) ? (int)$in['battery'] : null);
    }
    if ($existing) {
        // Already raised: just refresh its location.
        if ($lat !== null) q('UPDATE alerts SET lat = ?, lng = ? WHERE id = ?', [$lat, $lng, $existing['id']]);
    } else {
        $asg = guard_active_assignment($gid);
        $note = isset($in['note']) && is_string($in['note']) ? mb_substr(trim($in['note']), 0, 120) : '';
        $msg = 'SOS from ' . $guard['full_name'] . ($asg ? ' at ' . $asg['site_name'] . ' (' . $asg['client_name'] . ')' : '')
             . ($note !== '' ? ': ' . $note : '') . ($lat === null ? ' — location unavailable' : '');
        q("INSERT INTO alerts (type, guard_id, assignment_id, client_id, lat, lng, message, status, created_at)
           VALUES ('sos', ?, ?, ?, ?, ?, ?, 'open', ?)",
          [$gid, $asg['id'] ?? null, $asg['client_id'] ?? null, $lat, $lng, mb_substr($msg, 0, 255), now()]);
        log_activity('alert.sos', $msg);
    }
    json_out(guard_state($guard) + ['message' => 'SOS sent to the control room.']);
}

$n = q("UPDATE alerts SET status = 'resolved', resolved_at = ?, resolution_note = 'Cancelled by guard: safe / false alarm'
         WHERE guard_id = ? AND type = 'sos' AND status <> 'resolved'", [now(), $gid])->rowCount();
if ($n) log_activity('alert.sos_cancel', 'Guard marked safe');
json_out(guard_state($guard) + ['message' => 'SOS cancelled. Control room has been informed that you are safe.']);
