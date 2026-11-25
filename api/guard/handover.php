<?php
/**
 * Shift handover between the outgoing and the incoming guard.
 *  GET  ?action=candidates                      guards who can take over
 *  POST { action: "create", incoming_guard_id, items: [], notes, lat, lng }   hand over and end duty
 *  POST { action: "respond", id, decision: "accept"|"dispute", note }         incoming guard confirms
 */
require __DIR__ . '/_common.php';

const HANDOVER_ITEMS = ['Keys', 'Walkie-talkie / Radio', 'Visitor register / Logbook', 'Torch', 'Access cards', 'Weapon & ammunition', 'CCTV / Control panel'];

if (!is_post()) {
    $guard = require_guard(true);
    $asg = guard_active_assignment((int)$guard['id']);
    $rows = q_all("SELECT g.id, g.full_name, g.employee_code, g.is_on_duty, a.client_id, a.site_name, c.name AS client_name
                     FROM guards g LEFT JOIN assignments a ON a.guard_id = g.id AND a.status = 'active'
                     LEFT JOIN clients c ON c.id = a.client_id
                    WHERE g.status = 'active' AND g.id <> ?", [$guard['id']]);
    $myClient = $asg ? (int)$asg['client_id'] : 0;
    usort($rows, fn($a, $b) => [(int)$b['client_id'] === $myClient && $myClient, (int)$b['is_on_duty'], $a['full_name']]
                           <=> [(int)$a['client_id'] === $myClient && $myClient, (int)$a['is_on_duty'], $b['full_name']]);
    json_out(['ok' => true, 'items' => HANDOVER_ITEMS, 'armed' => (bool)$guard['is_armed'], 'guards' => array_map(fn($r) => [
        'id' => (int)$r['id'], 'name' => $r['full_name'], 'code' => $r['employee_code'], 'on_duty' => (bool)$r['is_on_duty'],
        'same_site' => $myClient && (int)$r['client_id'] === $myClient, 'where' => $r['client_name'] ? $r['client_name'] . ' · ' . $r['site_name'] : null,
    ], $rows)]);
}

$guard = guard_api_post();
$in = request_data();
$gid = (int)$guard['id'];
$action = $in['action'] ?? '';

if ($action === 'create') {
    $shift = guard_open_shift($gid);
    if (!$shift) json_error('You are not on duty.', 422);
    $inId = isset($in['incoming_guard_id']) && is_numeric($in['incoming_guard_id']) ? (int)$in['incoming_guard_id'] : 0;
    $incoming = $inId && $inId !== $gid ? q_one("SELECT id, full_name FROM guards WHERE id = ? AND status = 'active'", [$inId]) : null;
    if (!$incoming) json_error('Select the guard who is taking over from you.', 422);
    $items = array_values(array_intersect(HANDOVER_ITEMS, is_array($in['items'] ?? null) ? $in['items'] : []));
    $other = isset($in['other_items']) && is_string($in['other_items']) ? trim($in['other_items']) : '';
    if ($other !== '') $items[] = mb_substr($other, 0, 150);
    $notes = isset($in['notes']) && is_string($in['notes']) ? mb_substr(trim($in['notes']), 0, 1000) : '';
    [$lat, $lng, $acc] = input_coords($in);
    $asg = $shift['assignment_id'] ? q_one('SELECT client_id, site_name FROM assignments WHERE id = ?', [$shift['assignment_id']]) : null;

    q("INSERT INTO shift_handovers (client_id, site_name, outgoing_guard_id, outgoing_attendance_id, incoming_guard_id, items, notes, lat, lng, status, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
      [$asg['client_id'] ?? null, $asg['site_name'] ?? null, $gid, $shift['id'], $incoming['id'],
       $items ? implode(', ', $items) : null, $notes !== '' ? $notes : null, $lat, $lng, now()]);
    if ($lat !== null) {
        record_guard_location($guard, $lat, $lng, $acc, null, null, isset($in['battery']) ? (int)$in['battery'] : null);
    }
    $r = end_shift($gid, $lat, $lng, null, 'Handed over to ' . $incoming['full_name']);
    log_activity('duty.handover', 'to ' . $incoming['full_name'] . ($items ? ': ' . implode(', ', $items) : ''));
    $g = q_one('SELECT * FROM guards WHERE id = ?', [$gid]);
    json_out(guard_state($g) + ['message' => 'Duty handed over to ' . $incoming['full_name'] . '. Duty ended' . ($r ? ' (' . fmt_minutes($r['duration_minutes']) . ')' : '') . '.']);
}

if ($action === 'respond') {
    $hid = isset($in['id']) && is_numeric($in['id']) ? (int)$in['id'] : 0;
    $h = q_one("SELECT * FROM shift_handovers WHERE id = ? AND incoming_guard_id = ? AND status = 'pending'", [$hid, $gid]);
    if (!$h) json_error('This handover was already answered.', 422);
    $dispute = ($in['decision'] ?? '') === 'dispute';
    $note = isset($in['note']) && is_string($in['note']) ? mb_substr(trim($in['note']), 0, 500) : '';
    if ($dispute && mb_strlen($note) < 3) json_error('Write what is missing or wrong.', 422);
    $shift = guard_open_shift($gid);
    q('UPDATE shift_handovers SET status = ?, responded_at = ?, incoming_note = ?, incoming_attendance_id = ? WHERE id = ?',
      [$dispute ? 'disputed' : 'accepted', now(), $note !== '' ? $note : null, $shift['id'] ?? null, $hid]);
    log_activity('duty.handover_' . ($dispute ? 'dispute' : 'accept'), 'handover #' . $hid . ($note !== '' ? ': ' . $note : ''));
    json_out(guard_state($guard) + ['message' => $dispute ? 'Reported to the control room.' : 'Handover accepted.']);
}

json_error('Unknown action');
