<?php
/**
 * Leave requests from the guard app.
 * Body: { action: "request", from, to, type, reason } | { action: "cancel", id }
 */
require __DIR__ . '/_common.php';
$guard = guard_api_post();
$in = request_data();
$gid = (int)$guard['id'];

if (($in['action'] ?? '') === 'cancel') {
    $id = isset($in['id']) && is_numeric($in['id']) ? (int)$in['id'] : 0;
    $n = q("UPDATE guard_leaves SET status = 'cancelled' WHERE id = ? AND guard_id = ? AND status = 'pending'", [$id, $gid])->rowCount();
    if ($n) log_activity('leave.cancel', 'leave #' . $id);
    json_out(guard_state($guard) + ['message' => $n ? 'Leave request cancelled.' : 'Only a request that is still waiting can be cancelled. Call the control room to change approved leave.']);
}

$from = isset($in['from']) && is_string($in['from']) ? $in['from'] : '';
$to = isset($in['to']) && is_string($in['to']) && $in['to'] !== '' ? $in['to'] : $from;
$type = isset($in['type']) && is_string($in['type']) && isset(LEAVE_TYPES[$in['type']]) ? $in['type'] : null;
$reason = isset($in['reason']) && is_string($in['reason']) ? trim(preg_replace('/\s+/', ' ', $in['reason'])) : '';

if (!valid_date($from) || !valid_date($to)) json_error('Choose the leave dates.', 422);
if ($from < date('Y-m-d')) json_error('Leave can only be requested from today onwards.', 422);
if ($to < $from) json_error('The last day of leave cannot be before the first day.', 422);
if ((strtotime($to) - strtotime($from)) / 86400 >= 30) json_error('Leave can be at most 30 days at a time. Call the control room for longer leave.', 422);
if ($from > date('Y-m-d', strtotime('+180 days'))) json_error('Leave can be requested up to 6 months ahead.', 422);
if (!$type) json_error('Choose the type of leave.', 422);
if (mb_strlen($reason) < 3) json_error('Please write the reason for your leave.', 422);
if (q_val("SELECT id FROM guard_leaves WHERE guard_id = ? AND status IN ('pending','approved') AND from_date <= ? AND to_date >= ? LIMIT 1", [$gid, $to, $from])) {
    json_error('You already have leave on some of these days.', 422);
}

q("INSERT INTO guard_leaves (guard_id, from_date, to_date, leave_type, reason, status, requested_by, created_at)
   VALUES (?, ?, ?, ?, ?, 'pending', 'guard', ?)", [$gid, $from, $to, $type, mb_substr($reason, 0, 255), now()]);
$leaveId = (int)db()->lastInsertId();
log_activity('leave.request', leave_type_label($type) . ' ' . $from . ($to !== $from ? ' to ' . $to : '') . ': ' . $reason);
notify_admins_request('📅 Leave request: ' . $guard['full_name'],
    leave_type_label($type) . ' · ' . fmt_date($from, 'd M') . ($to !== $from ? ' – ' . fmt_date($to, 'd M') : '') . ' — ' . $reason, 'admin/duty.php?tab=leave', 'leave-' . $leaveId);
json_out(guard_state($guard) + ['message' => 'Leave request sent. You will see the decision in the Attendance tab.']);
