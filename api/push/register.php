<?php
/**
 * The native app registers its push token for the logged-in admin or guard.
 * POST { token, as: "admin"|"guard", app_version }
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (!is_post()) json_error('POST required', 405);
require_csrf(true);
$in = request_data();
$token = isset($in['token']) && is_string($in['token']) ? trim($in['token']) : '';
if ($token === '' || strlen($token) > 255 || !preg_match('/^[A-Za-z0-9_:\-\.]+$/', $token)) json_error('Invalid token');

if (($in['as'] ?? '') === 'admin' && ($admin = current_admin())) {
    [$type, $uid] = ['admin', (int)$admin['id']];
} elseif ($guard = current_guard()) {
    [$type, $uid] = ['guard', (int)$guard['id']];
} else {
    json_error('Not logged in', 401);
}
$ver = isset($in['app_version']) && is_string($in['app_version']) ? mb_substr($in['app_version'], 0, 20) : null;
// One token belongs to one person: whoever is logged in on that phone now.
q('INSERT INTO push_devices (user_type, user_id, token, platform, app_version, created_at, last_seen_at) VALUES (?, ?, ?, \'android\', ?, ?, ?)
   ON DUPLICATE KEY UPDATE user_type = VALUES(user_type), user_id = VALUES(user_id), app_version = VALUES(app_version), last_seen_at = VALUES(last_seen_at)',
  [$type, $uid, $token, $ver, now(), now()]);
$_SESSION['push_token'] = $token;
json_out(['ok' => true, 'registered_as' => $type, 'push_ready' => push_ready()]);
