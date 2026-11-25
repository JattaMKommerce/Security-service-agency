<?php
/**
 * Start-up settings for the native Android app (public: contains no secrets).
 * The Firebase values here are the public app identifiers Google intends to ship inside apps.
 */
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
session_write_close();
json_out([
    'ok'       => true,
    'firebase' => push_public_config(),
    'company'  => company_name(),
    'latest_version_code' => (int)setting('app_latest_version_code', '1'),
    'apk_url'  => url('downloads/apk/Jatta-Security.apk'),
]);
