<?php
/** Streams a guard's duty check-in selfie (admins only). GET ?id=<attendance id> */
require dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
session_write_close();
$name = q_val('SELECT check_in_selfie FROM attendance WHERE id = ?', [get_int('id')]);
$path = selfie_file($name ? (string)$name : null);
if (!$path) {
    http_response_code(404);
    exit('Selfie not found (it may have been deleted after the retention period).');
}
$ext = pathinfo($path, PATHINFO_EXTENSION);
header('Content-Type: ' . (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
