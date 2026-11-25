<?php
/**
 * Issues the token the native app's background location service uses to send GPS
 * while the screen is off (no browser session there). One phone per guard:
 * a new token replaces the old one.
 */
require __DIR__ . '/_common.php';
$guard = guard_api_post();
$token = bin2hex(random_bytes(32));
q('UPDATE guards SET device_token_hash = ? WHERE id = ?', [hash('sha256', $token), $guard['id']]);
json_out([
    'ok'           => true,
    'token'        => $token,
    'base'         => BASE_URL,
    'interval_sec' => max(10, (int)setting('tracking_interval_sec', '20')),
    'name'         => $guard['full_name'],
]);
