<?php
require __DIR__ . '/includes/bootstrap.php';

try {
    if (!empty($_SESSION['admin_id'])) {
        log_activity('admin.logout');
    }
    // This phone should stop getting the admin's notifications.
    if (!empty($_SESSION['push_token'])) q('DELETE FROM push_devices WHERE token = ?', [$_SESSION['push_token']]);
    admin_logout();
    flash('success', 'You have been logged out.');
} catch (Throwable $e) {
    error_log('admin logout failed: ' . $e->getMessage());
}
redirect('login.php');
