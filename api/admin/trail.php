<?php
/**
 * Location history (route) for one guard on one day.
 * GET guard_id, date=Y-m-d (default today)
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_admin(true);
session_write_close();

$gid = get_int('guard_id');
$date = get_str('date', 10) ?: date('Y-m-d');
if ($gid <= 0 || !valid_date($date)) {
    json_error('Invalid guard or date');
}

$rows = q_all('SELECT lat, lng, accuracy, speed, recorded_at FROM guard_locations
                WHERE guard_id = ? AND recorded_at >= ? AND recorded_at < ?
                ORDER BY recorded_at ASC LIMIT 5000',
              [$gid, $date . ' 00:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00']);

$points = [];
$total = 0.0;
$prev = null;
foreach ($rows as $r) {
    $p = ['lat' => (float)$r['lat'], 'lng' => (float)$r['lng'], 'accuracy' => $r['accuracy'] !== null ? (int)$r['accuracy'] : null,
          'time' => date('h:i A', strtotime($r['recorded_at']))];
    if ($prev) {
        $total += distance_m($prev['lat'], $prev['lng'], $p['lat'], $p['lng']);
    }
    $points[] = $p;
    $prev = $p;
}

json_out(['ok' => true, 'date' => $date, 'points' => $points, 'distance_km' => round($total / 1000, 2)]);
