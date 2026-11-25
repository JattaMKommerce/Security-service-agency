<?php
require dirname(__DIR__) . '/includes/bootstrap.php';
if (is_post() && csrf_valid()) {
    $gid = !empty($_SESSION['guard_id']) ? (int)$_SESSION['guard_id'] : null;
    if ($gid) {
        // Logging out ends an open shift so the guard is not shown "on duty" forever.
        end_shift($gid, null, null, null, 'Guard logged out of the app');
        log_activity('guard.logout', '', 'guard', $gid);
    }
    guard_clear_remember($gid);
    if ($gid) q('UPDATE guards SET device_token_hash = NULL WHERE id = ?', [$gid]);   // stops the background service
    if (!empty($_SESSION['push_token'])) q('DELETE FROM push_devices WHERE token = ?', [$_SESSION['push_token']]);
    unset($_SESSION['guard_id']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
}
redirect('guard/login.php');
