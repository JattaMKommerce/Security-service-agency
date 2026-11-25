<?php
/** Shared setup for the native app's background service endpoints (token auth, no cookies). */
require_once dirname(__DIR__) . '/guard/_common.php';

function device_guard(): array
{
    if (!is_post()) json_error('POST required', 405);
    $token = (string)($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) json_error('Not authorised', 401);
    $g = q_one("SELECT * FROM guards WHERE device_token_hash = ? AND status = 'active'", [hash('sha256', $token)]);
    if (!$g) json_error('Not authorised', 401);   // logged out, signed in on another phone, or deactivated
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    return $g;
}
