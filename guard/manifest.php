<?php
/** Web app manifest (PHP so paths work in any folder). */
require dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name'             => company_name() . ' — Guard',
    'short_name'       => 'Guard',
    'description'      => 'Duty check-in, live location and SOS for bodyguards',
    'start_url'        => url('guard/index.php'),
    'scope'            => url('guard/'),
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#07070a',
    'theme_color'      => '#07070a',
    'icons'            => [
        ['src' => url('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => url('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => url('assets/img/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
