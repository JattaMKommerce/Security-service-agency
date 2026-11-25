<?php
/**
 * GPS points from the native app's background service (works with the screen off).
 * Header X-Device-Token. Body { points: [...], gps: ok|off|denied }
 * The reply tells the app whether the guard is still on duty (the control room may have ended it).
 */
require __DIR__ . '/_common.php';
$guard = device_guard();
$in = request_data();
touch_guard_ping($guard, $in['gps'] ?? null);
$r = ingest_location_points($guard, $in);
maybe_run_maintenance();
maybe_run_live_checks();
$onDuty = (bool)q_val('SELECT is_on_duty FROM guards WHERE id = ?', [$guard['id']]);
json_out(['ok' => true, 'accepted' => $r['accepted'], 'on_duty' => $onDuty,
          'interval_sec' => max(10, (int)setting('tracking_interval_sec', '20')),
          'outside' => $r['last'] ? $r['last']['inside'] === false : null]);
