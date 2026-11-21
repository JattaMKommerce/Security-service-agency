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
    unset($_SESSION['guard_id']);
    session_regenerate_id(true);
}
redirect('guard/login.php');
