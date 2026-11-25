<?php
/**
 * Receives GPS fixes from the guard's phone.
 * Body: { points: [ {lat, lng, accuracy, speed, heading, battery, ts} ], gps: ok|off|denied }  (ts = ms since epoch)
 * Points queued while offline are accepted for up to 12 hours. An empty
 * points list is a heartbeat (phone online, e.g. while GPS is off).
 */
require __DIR__ . '/_common.php';
$guard = guard_api_post();
session_write_close();

$in = request_data();
$r = ingest_location_points($guard, $in);
$last = $r['last'];
$accepted = $r['accepted'];

maybe_run_maintenance();
maybe_run_live_checks();
json_out(['ok' => true, 'accepted' => $accepted, 'geofence' => $last ? ['inside' => $last['inside'], 'distance_m' => $last['distance_m'], 'alert' => $last['alert']] : null]);
