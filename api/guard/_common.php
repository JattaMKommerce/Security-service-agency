<?php
/**
 * Shared setup for guard app API endpoints.
 */
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

/** Authenticated guard for a POST endpoint (CSRF checked). */
function guard_api_post(): array
{
    $guard = require_guard(true);
    if (!is_post()) {
        json_error('POST required', 405);
    }
    require_csrf(true);
    touch_guard_ping($guard, request_data()['gps'] ?? null);
    return $guard;
}

/**
 * Records that the guard's phone is online (and its GPS state). This is what
 * lets the control room tell "no internet" apart from "GPS turned off".
 */
function touch_guard_ping(array $guard, $gps): void
{
    $gps = in_array($gps, ['ok', 'off', 'denied'], true) ? $gps : null;
    if ($gps === null) {
        q('UPDATE guards SET last_ping_at = ? WHERE id = ?', [now(), $guard['id']]);
        return;
    }
    q('UPDATE guards SET last_ping_at = ?, gps_state_at = IF(gps_state = ? AND gps_state_at IS NOT NULL, gps_state_at, ?), gps_state = ? WHERE id = ?',
      [now(), $gps, now(), $gps, $guard['id']]);
}

/** Validated coordinates from the request (or nulls). */
function input_coords(array $in): array
{
    $lat = $in['lat'] ?? null;
    $lng = $in['lng'] ?? null;
    if (!valid_coords($lat, $lng)) {
        return [null, null, null];
    }
    $acc = isset($in['accuracy']) && is_numeric($in['accuracy']) ? max(0, min(100000, (float)$in['accuracy'])) : null;
    return [(float)$lat, (float)$lng, $acc];
}

function guard_state(array $guard): array
{
    $gid = (int)$guard['id'];
    $asg = guard_active_assignment($gid);
    $shift = guard_open_shift($gid);
    $sos = q_one("SELECT id, status, created_at, acknowledged_at FROM alerts WHERE guard_id = ? AND type = 'sos' AND status <> 'resolved' ORDER BY id DESC LIMIT 1", [$gid]);
    $outside = $shift ? q_one("SELECT id, distance_m FROM alerts WHERE guard_id = ? AND type = 'geofence_exit' AND status <> 'resolved' ORDER BY id DESC LIMIT 1", [$gid]) : null;
    $recent = q_all('SELECT at.check_in_at, at.check_out_at, at.duration_minutes, at.status, c.name AS client_name
                       FROM attendance at LEFT JOIN clients c ON c.id = at.client_id
                      WHERE at.guard_id = ? ORDER BY at.check_in_at DESC LIMIT 5', [$gid]);
    $month = q_one('SELECT COUNT(*) shifts, COALESCE(SUM(duration_minutes),0) mins, COALESCE(SUM(overtime_minutes),0) ot
                      FROM attendance WHERE guard_id = ? AND check_in_at >= ?', [$gid, date('Y-m-01 00:00:00')]);
    $ext = $shift ? q_one('SELECT id, status, requested_minutes, approved_minutes, reason, admin_note, requested_at, decided_at
                             FROM duty_extensions WHERE attendance_id = ? ORDER BY id DESC LIMIT 1', [$shift['id']]) : null;
    $approvedExt = $shift ? approved_extension_minutes((int)$shift['id']) : 0;
    $myLeaves = q_all("SELECT id, from_date, to_date, leave_type, reason, status, requested_by, admin_note FROM guard_leaves
                        WHERE guard_id = ? AND to_date >= ? ORDER BY from_date DESC LIMIT 8", [$gid, date('Y-m-d', strtotime('-45 days'))]);
    $offToday = guard_day_off($guard['weekly_off'] ?? null, array_values(array_filter($myLeaves, fn($l) => $l['status'] === 'approved')), date('Y-m-d'));
    $handIn = q_all("SELECT h.id, h.site_name, h.items, h.notes, h.created_at, g.full_name AS from_name, g.employee_code AS from_code
                       FROM shift_handovers h JOIN guards g ON g.id = h.outgoing_guard_id
                      WHERE h.incoming_guard_id = ? AND h.status = 'pending' AND h.created_at >= ? ORDER BY h.id DESC LIMIT 3",
                    [$gid, date('Y-m-d H:i:s', time() - 86400)]);

    return [
        'ok'          => true,
        'csrf'        => csrf_token(),
        'server_time' => time(),
        'guard'       => [
            'id' => $gid, 'name' => $guard['full_name'], 'code' => $guard['employee_code'],
            'photo' => guard_photo_url($guard['photo']), 'armed' => (bool)$guard['is_armed'],
        ],
        'assignment'  => $asg ? [
            'id'             => (int)$asg['id'],
            'client_name'    => $asg['client_name'],
            'contact_person' => $asg['contact_person'],
            'client_phone'   => $asg['client_phone'],
            'site_name'      => $asg['site_name'],
            'site_address'   => $asg['site_address'],
            'lat'            => $asg['site_lat'] !== null ? (float)$asg['site_lat'] : null,
            'lng'            => $asg['site_lng'] !== null ? (float)$asg['site_lng'] : null,
            'radius'         => (int)$asg['geofence_radius_m'],
            'shift'          => $asg['shift_start'] ? fmt_time($asg['shift_start']) . ' – ' . fmt_time($asg['shift_end']) : null,
            'period'         => fmt_date($asg['start_date']) . ' → ' . ($asg['end_date'] ? fmt_date($asg['end_date']) : 'until further notice'),
            'notes'          => $asg['notes'],
        ] : null,
        'shift'       => $shift ? [
            'id'            => (int)$shift['id'],
            'started'       => strtotime($shift['check_in_at']),
            'check_in_at'   => fmt_datetime($shift['check_in_at']),
            'late_minutes'  => (int)$shift['late_minutes'],
            'end_ts'        => $shift['shift_end_at'] ? strtotime($shift['shift_end_at']) : null,
            'end_label'     => $shift['shift_end_at'] ? fmt_date($shift['shift_end_at'], 'h:i A') : null,
            'approved_ext'  => $approvedExt,
            'ext_end_label' => $shift['shift_end_at'] && $approvedExt ? fmt_date(date('Y-m-d H:i:s', strtotime($shift['shift_end_at']) + $approvedExt * 60), 'h:i A') : null,
        ] : null,
        'extension'   => $ext ? [
            'id' => (int)$ext['id'], 'status' => $ext['status'], 'minutes' => (int)$ext['requested_minutes'],
            'approved_minutes' => $ext['approved_minutes'] !== null ? (int)$ext['approved_minutes'] : null,
            'reason' => $ext['reason'], 'note' => $ext['admin_note'], 'requested' => fmt_time(substr($ext['requested_at'], 11)),
        ] : null,
        'today_off'   => $offToday ? ($offToday[0] === 'leave' ? 'You are on ' . strtolower(leave_type_label((string)$offToday[1])) . ' today.' : 'Today is your weekly off.') : null,
        'weekly_off'  => weekly_off_label($guard['weekly_off'] ?? null),
        'leaves'      => array_map(fn($l) => [
            'id' => (int)$l['id'], 'type' => leave_type_label($l['leave_type']), 'status' => $l['status'],
            'from' => fmt_date($l['from_date'], 'd M'), 'to' => fmt_date($l['to_date'], 'd M'),
            'days' => (int)((strtotime($l['to_date']) - strtotime($l['from_date'])) / 86400) + 1,
            'reason' => $l['reason'], 'note' => $l['admin_note'], 'by_admin' => $l['requested_by'] === 'admin',
        ], $myLeaves),
        'leave_types' => LEAVE_TYPES,
        'handover_in' => array_map(fn($h) => [
            'id' => (int)$h['id'], 'from' => $h['from_name'], 'from_code' => $h['from_code'], 'site' => $h['site_name'],
            'items' => $h['items'], 'notes' => $h['notes'], 'at' => fmt_datetime($h['created_at']),
        ], $handIn),
        'sos'         => $sos ? ['id' => (int)$sos['id'], 'status' => $sos['status'], 'raised' => fmt_datetime($sos['created_at'])] : null,
        'outside'     => $outside ? ['distance_m' => (int)$outside['distance_m']] : null,
        'month'       => ['shifts' => (int)$month['shifts'], 'hours' => round((int)$month['mins'] / 60, 1), 'ot_hours' => round((int)$month['ot'] / 60, 1)],
        'recent'      => array_map(fn($r) => [
            'in' => fmt_datetime($r['check_in_at']), 'out' => $r['check_out_at'] ? fmt_time(substr($r['check_out_at'], 11)) : null,
            'duration' => $r['status'] === 'open' ? 'On duty' : fmt_minutes($r['duration_minutes'] !== null ? (int)$r['duration_minutes'] : null),
            'client' => $r['client_name'], 'status' => $r['status'],
        ], $recent),
        'settings'    => [
            'interval_sec' => max(10, (int)setting('tracking_interval_sec', '20')),
            'checkin_max_m' => (int)setting('checkin_max_distance_m', '500'),
            'require_selfie' => setting('require_selfie', '1') === '1',
            'ot_min' => (int)setting('ot_min_minutes', '15'),
        ],
    ];
}
