<?php
/**
 * Phone events from the native app: phone_off (being switched off / restarted),
 * battery_low, gps_off, gps_on. Header X-Device-Token. Body { event, battery }
 */
require __DIR__ . '/_common.php';
$guard = device_guard();
$in = request_data();
$event = (string)($in['event'] ?? '');
$battery = isset($in['battery']) && is_numeric($in['battery']) ? max(0, min(100, (int)$in['battery'])) : null;
if ($battery !== null) q('UPDATE guards SET last_battery = ? WHERE id = ?', [$battery, $guard['id']]);

if ($event === 'gps_off' || $event === 'gps_on') {
    touch_guard_ping($guard, $event === 'gps_on' ? 'ok' : 'off');
} else {
    touch_guard_ping($guard, null);
}
$onDuty = (int)$guard['is_on_duty'] === 1;
if ($onDuty && in_array($event, ['phone_off', 'battery_low'], true)) {
    $asg = guard_active_assignment((int)$guard['id']);
    $where = $asg ? ' at ' . $asg['site_name'] : '';
    $lat = $guard['last_lat'] !== null ? (float)$guard['last_lat'] : null;
    $lng = $guard['last_lng'] !== null ? (float)$guard['last_lng'] : null;
    if ($event === 'phone_off') {
        open_guard_alert('phone_off', $guard, $asg, $lat, $lng, sprintf('%s\'s phone is being switched off%s%s. Location stops until it is switched on again.',
            $guard['full_name'], $battery !== null ? ' (battery ' . $battery . '%)' : '', $where));
        log_activity('device.phone_off', 'battery ' . ($battery ?? '?') . '%', 'guard', (int)$guard['id']);
    } elseif ($battery !== null && $battery <= max(1, (int)setting('battery_alert_pct', '15'))) {
        open_guard_alert('battery_low', $guard, $asg, $lat, $lng,
            sprintf('%s\'s phone battery is at %d%%%s — it may switch off soon. Ask the guard to charge it.', $guard['full_name'], $battery, $where));
    }
}
json_out(['ok' => true, 'on_duty' => $onDuty]);
