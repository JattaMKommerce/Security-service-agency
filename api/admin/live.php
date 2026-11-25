<?php
/**
 * Live positions + status of all guards for the admin maps.
 * GET ?client_id= (optional) ?guard_id= (optional)
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_admin(true);

$where = ["g.status <> 'inactive'"];
$params = [];
if (($cid = get_int('client_id')) > 0) {
    $where[] = 'c.id = ?';
    $params[] = $cid;
}
if (($gid = get_int('guard_id')) > 0) {
    $where = ['g.id = ?'];
    $params = [$gid];
}

$rows = q_all(guards_live_sql(implode(' AND ', $where)) . ' ORDER BY g.full_name', $params);
maybe_run_live_checks();
$counts = array_fill_keys(['emergency', 'late', 'patrol', 'on_duty', 'off_duty', 'issues', 'sharing'], 0);

$guards = [];
foreach ($rows as $g) {
    $ms = guard_map_status($g);
    $status = $ms['status'];
    $counts[$status]++;
    if ($ms['flags']) $counts['issues']++;
    if ($ms['sharing'] === 'live') $counts['sharing']++;
    $guards[] = [
        'id'            => (int)$g['id'],
        'code'          => $g['employee_code'],
        'category'      => $g['category'] ?? 'bodyguard',
        'category_label'=> guard_category_label($g['category'] ?? 'bodyguard'),
        'name'          => $g['full_name'],
        'phone'         => $g['phone'],
        'photo'         => guard_photo_url($g['photo']),
        'armed'         => (bool)$g['is_armed'],
        'status'        => $status,
        'status_label'  => map_status_meta($status)[0],
        'flags'         => $ms['flags'],
        'sharing'       => $ms['sharing'],
        'sharing_label' => $ms['sharing_label'],
        'last_ping_ago' => time_ago($g['last_ping_at'] ?? null),
        'on_duty'       => (bool)$g['is_on_duty'],
        'duty_since'    => $g['duty_since'] ? fmt_datetime($g['duty_since']) : null,
        'lat'           => $g['last_lat'] !== null ? (float)$g['last_lat'] : null,
        'lng'           => $g['last_lng'] !== null ? (float)$g['last_lng'] : null,
        'accuracy'      => $g['last_accuracy'] !== null ? (int)round((float)$g['last_accuracy']) : null,
        'speed_kmh'     => $g['last_speed'] !== null ? round((float)$g['last_speed'] * 3.6, 1) : null,
        'battery'       => $g['last_battery'] !== null ? (int)$g['last_battery'] : null,
        'last_seen'     => $g['last_seen_at'],
        'last_seen_ago' => time_ago($g['last_seen_at']),
        'client_id'     => $g['client_id'] ? (int)$g['client_id'] : null,
        'client_name'   => $g['client_name'],
        'site_name'     => $g['site_name'],
        'site_lat'      => $g['site_lat'] !== null ? (float)$g['site_lat'] : null,
        'site_lng'      => $g['site_lng'] !== null ? (float)$g['site_lng'] : null,
        'radius'        => $g['geofence_radius_m'] ? (int)$g['geofence_radius_m'] : null,
        'shift'         => $g['shift_start'] ? fmt_time($g['shift_start']) . ' – ' . fmt_time($g['shift_end']) : null,
        'distance_m'    => ($g['site_lat'] !== null && $g['last_lat'] !== null)
            ? (int)round(distance_m((float)$g['site_lat'], (float)$g['site_lng'], (float)$g['last_lat'], (float)$g['last_lng']))
            : null,
    ];
}

json_out(['ok' => true, 'server_time' => now(), 'counts' => $counts, 'guards' => $guards]);
