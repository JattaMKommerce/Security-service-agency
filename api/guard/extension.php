<?php
/**
 * Duty extension: a guard on duty asks the control room for extra working hours.
 * Body: { action: "request", minutes, reason } | { action: "cancel" }
 */
require __DIR__ . '/_common.php';
$guard = guard_api_post();
$in = request_data();
$gid = (int)$guard['id'];
$shift = guard_open_shift($gid);
if (!$shift) {
    json_error('Start your duty first. Extra hours can only be requested while on duty.', 422);
}

if (($in['action'] ?? '') === 'cancel') {
    $n = q("UPDATE duty_extensions SET status = 'cancelled' WHERE attendance_id = ? AND guard_id = ? AND status = 'pending'", [$shift['id'], $gid])->rowCount();
    if ($n) log_activity('duty.extension_cancel', 'shift #' . $shift['id']);
    json_out(guard_state($guard) + ['message' => $n ? 'Request cancelled.' : 'No pending request.']);
}

$minutes = isset($in['minutes']) && is_numeric($in['minutes']) ? (int)$in['minutes'] : 0;
$reason = isset($in['reason']) && is_string($in['reason']) ? trim(preg_replace('/\s+/', ' ', $in['reason'])) : '';
if ($minutes < 15 || $minutes > 480 || $minutes % 15 !== 0) {
    json_error('Choose how many extra hours you need (up to 8 hours).', 422);
}
if (mb_strlen($reason) < 3) {
    json_error('Please write the reason for the extra hours.', 422);
}
if (q_val("SELECT id FROM duty_extensions WHERE attendance_id = ? AND status = 'pending'", [$shift['id']])) {
    json_error('You already have a request waiting for approval.', 422);
}
q("INSERT INTO duty_extensions (guard_id, attendance_id, assignment_id, requested_minutes, reason, status, requested_at)
   VALUES (?, ?, ?, ?, ?, 'pending', ?)", [$gid, $shift['id'], $shift['assignment_id'], $minutes, mb_substr($reason, 0, 255), now()]);
log_activity('duty.extension_request', fmt_minutes($minutes) . ': ' . $reason);
json_out(guard_state($guard) + ['message' => 'Request sent. You will see the decision here.']);
