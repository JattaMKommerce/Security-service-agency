<?php
/**
 * Location history (route) for one guard: a whole day, or the current duty.
 * GET guard_id, date=Y-m-d (default today) | mode=duty (since the open shift's check-in)
 * Returns the points (for drawing + playback), stops of 10+ minutes, distance and alerts.
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_admin(true);
session_write_close();

const STOP_RADIUS_M = 40;
const STOP_MIN_SEC = 600;

$gid = get_int('guard_id');
$guard = $gid > 0 ? q_one('SELECT id, full_name, employee_code FROM guards WHERE id = ?', [$gid]) : null;
if (!$guard) {
    json_error('Invalid guard');
}

if (get_str('mode', 10) === 'duty') {
    $shift = guard_open_shift($gid);
    if (!$shift) {
        json_error($guard['full_name'] . ' is not on duty right now.');
    }
    $from = $shift['check_in_at'];
    $to = date('Y-m-d H:i:s', time() + 60);
    $label = 'Current duty since ' . fmt_datetime($from);
} else {
    $date = get_str('date', 10) ?: date('Y-m-d');
    if (!valid_date($date)) {
        json_error('Invalid date');
    }
    $from = $date . ' 00:00:00';
    $to = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
    $label = fmt_date($date, 'l, d M Y');
}

$rows = q_all('SELECT lat, lng, accuracy, speed, battery, recorded_at FROM guard_locations
                WHERE guard_id = ? AND recorded_at >= ? AND recorded_at < ?
                ORDER BY recorded_at ASC LIMIT 5000', [$gid, $from, $to]);

$points = [];
$total = 0.0;
$prev = null;
foreach ($rows as $r) {
    $p = ['lat' => (float)$r['lat'], 'lng' => (float)$r['lng'], 'accuracy' => $r['accuracy'] !== null ? (int)$r['accuracy'] : null,
          'speed_kmh' => $r['speed'] !== null ? round((float)$r['speed'] * 3.6, 1) : null, 'battery' => $r['battery'] !== null ? (int)$r['battery'] : null,
          'ts' => strtotime($r['recorded_at']), 'time' => date('h:i A', strtotime($r['recorded_at']))];
    if ($prev) {
        $d = distance_m($prev['lat'], $prev['lng'], $p['lat'], $p['lng']);
        // Ignore GPS jitter while standing still.
        if ($d > max(15, min(100, (float)($p['accuracy'] ?? 0)))) {
            $total += $d;
        }
    }
    $points[] = $p;
    $prev = $p;
}

// Stops: the guard stayed within STOP_RADIUS_M for at least 10 minutes.
$stops = [];
$i = 0;
$n = count($points);
while ($i < $n) {
    $j = $i;
    while ($j + 1 < $n && distance_m($points[$i]['lat'], $points[$i]['lng'], $points[$j + 1]['lat'], $points[$j + 1]['lng']) <= STOP_RADIUS_M) {
        $j++;
    }
    $secs = $points[$j]['ts'] - $points[$i]['ts'];
    if ($secs >= STOP_MIN_SEC) {
        $slice = array_slice($points, $i, $j - $i + 1);
        $stops[] = ['lat' => array_sum(array_column($slice, 'lat')) / count($slice), 'lng' => array_sum(array_column($slice, 'lng')) / count($slice),
                    'from' => $points[$i]['time'], 'to' => $points[$j]['time'], 'minutes' => (int)round($secs / 60)];
        $i = $j + 1;
    } else {
        $i++;
    }
}

$alerts = array_map(fn($a) => [
    'type' => $a['type'], 'title' => alert_type_meta($a['type'])[0], 'message' => $a['message'],
    'lat' => $a['lat'] !== null ? (float)$a['lat'] : null, 'lng' => $a['lng'] !== null ? (float)$a['lng'] : null,
    'time' => date('h:i A', strtotime($a['created_at'])),
], q_all('SELECT type, message, lat, lng, created_at FROM alerts WHERE guard_id = ? AND created_at >= ? AND created_at < ? ORDER BY created_at LIMIT 100', [$gid, $from, $to]));

$shifts = array_map(fn($s) => ['in' => fmt_time(substr($s['check_in_at'], 11)), 'out' => $s['check_out_at'] ? fmt_time(substr($s['check_out_at'], 11)) : null],
    q_all('SELECT check_in_at, check_out_at FROM attendance WHERE guard_id = ? AND check_in_at < ? AND (check_out_at IS NULL OR check_out_at >= ?) ORDER BY check_in_at', [$gid, $to, $from]));

json_out([
    'ok' => true, 'guard' => $guard['full_name'], 'label' => $label,
    'date' => substr($from, 0, 10), 'points' => $points, 'stops' => $stops, 'alerts' => $alerts, 'shifts' => $shifts,
    'distance_km' => round($total / 1000, 2),
    'first' => $points ? $points[0]['time'] : null, 'last' => $points ? end($points)['time'] : null,
]);
