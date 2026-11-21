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
    return $guard;
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
    $month = q_one('SELECT COUNT(*) shifts, COALESCE(SUM(duration_minutes),0) mins FROM attendance WHERE guard_id = ? AND check_in_at >= ?', [$gid, date('Y-m-01 00:00:00')]);

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
        'shift'       => $shift ? ['id' => (int)$shift['id'], 'started' => strtotime($shift['check_in_at']), 'check_in_at' => fmt_datetime($shift['check_in_at'])] : null,
        'sos'         => $sos ? ['id' => (int)$sos['id'], 'status' => $sos['status'], 'raised' => fmt_datetime($sos['created_at'])] : null,
        'outside'     => $outside ? ['distance_m' => (int)$outside['distance_m']] : null,
        'month'       => ['shifts' => (int)$month['shifts'], 'hours' => round((int)$month['mins'] / 60, 1)],
        'recent'      => array_map(fn($r) => [
            'in' => fmt_datetime($r['check_in_at']), 'out' => $r['check_out_at'] ? fmt_time(substr($r['check_out_at'], 11)) : null,
            'duration' => $r['status'] === 'open' ? 'On duty' : fmt_minutes($r['duration_minutes'] !== null ? (int)$r['duration_minutes'] : null),
            'client' => $r['client_name'], 'status' => $r['status'],
        ], $recent),
        'settings'    => [
            'interval_sec' => max(10, (int)setting('tracking_interval_sec', '20')),
            'checkin_max_m' => (int)setting('checkin_max_distance_m', '500'),
        ],
    ];
}
