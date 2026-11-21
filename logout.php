<?php
require __DIR__ . '/includes/bootstrap.php';

if (is_post() && csrf_valid()) {
    if (!empty($_SESSION['admin_id'])) {
        log_activity('admin.logout');
    }
    admin_logout();
    flash('success', 'You have been logged out.');
}
redirect('login.php');
