<?php
/**
 * Optional scheduled cleanup (Hostinger hPanel > Advanced > Cron Jobs):
 *   /usr/bin/php /home/<user>/public_html/cron.php
 * every 15 minutes. The app also runs this automatically during normal use.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/includes/bootstrap.php';
run_maintenance();
save_setting('last_maintenance_at', (string)time());
echo '[' . date('c') . "] maintenance done\n";
