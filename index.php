<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_file(APP_ROOT . '/storage/installed.lock') && is_file(APP_ROOT . '/install.php')) {
    redirect('install.php');
}
redirect(current_admin() ? 'admin/index.php' : 'login.php');
