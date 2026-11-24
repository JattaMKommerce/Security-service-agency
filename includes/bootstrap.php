<?php
/**
 * Loaded by every page. Sets up config, errors, session, DB and helpers.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

$GLOBALS['config'] = require APP_ROOT . '/config/config.php';
$cfg = $GLOBALS['config'];

date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Kolkata');
mb_internal_encoding('UTF-8');

// ---- Error handling -------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', !empty($cfg['app']['debug']) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/php-errors.log');

set_exception_handler(function (Throwable $e) use ($cfg) {
    error_log('[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isApi = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
    $msg = !empty($cfg['app']['debug']) ? $e->getMessage() : 'Something went wrong. Please try again.';
    if ($isApi) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $msg]);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
           . '<div style="font-family:system-ui;max-width:560px;margin:80px auto;padding:24px;border:1px solid #e5e7eb;border-radius:12px">'
           . '<h2 style="margin-top:0">Something went wrong</h2><p>' . htmlspecialchars($msg) . '</p>'
           . '<p><a href="javascript:history.back()">Go back</a></p></div>';
    }
    exit;
});

// ---- Base URL -------------------------------------------------------------
function detect_base_url(): string
{
    $configured = rtrim((string)($GLOBALS['config']['app']['base_url'] ?? ''), '/');
    if ($configured !== '') {
        return $configured;
    }
    if (PHP_SAPI === 'cli') {
        return '';
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $path = '';
    if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
        $path = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        $path = implode('/', array_map('rawurlencode', explode('/', $path)));
    }
    return $scheme . '://' . $host . rtrim($path, '/');
}
define('BASE_URL', detect_base_url());
define('IS_HTTPS', str_starts_with(BASE_URL, 'https://'));

// ---- Session --------------------------------------------------------------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('SAGSESS');
    // Persistent session (1 year) so mobile guard app never randomly logs out
    session_set_cookie_params([
        'lifetime' => 31536000,
        'path'     => '/',
        'secure'   => IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '31536000'); // 1 year persistence
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/geo.php';
