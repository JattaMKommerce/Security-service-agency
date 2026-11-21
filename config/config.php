<?php
/**
 * Application configuration.
 *
 * On Hostinger: hPanel > Databases > MySQL Databases gives you a database name,
 * user and password like "u123456789_agency". Put those values here.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'security_agency',
        'user'    => 'root',
        'pass'    => 'root123',
        'charset' => 'utf8mb4',
    ],

    'app' => [
        // Shown before the company name is set in Admin > Settings.
        'name'     => 'Security Agency',
        'timezone' => 'Asia/Kolkata',

        // Leave empty to auto-detect. Set e.g. 'https://example.com' or
        // 'https://example.com/agency' if auto-detection is wrong.
        'base_url' => '',

        // Set true only while debugging. Never leave true on a live site.
        'debug'    => false,
    ],
];
