<?php
require __DIR__ . '/includes/bootstrap.php';

try {
    if (!empty($_SESSION['admin_id'])) {
        log_activity('admin.logout');
    }
    admin_logout();
    flash('success', 'You have been logged out.');
} catch (Throwable $e) {
    error_log('admin logout failed: ' . $e->getMessage());
}
redirect('login.php');
