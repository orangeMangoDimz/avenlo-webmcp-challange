<?php
/**
 * Database configuration file
 */

require_once __DIR__ . '/env.php';

return [
        'host' => config_env('DB_HOST', ''),
        'port' => config_env('DB_PORT', ''),
        'database' => config_env('DB_NAME', ''),
        'username' => config_env('DB_USER', ''),
        'password' => config_env('DB_PASS', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',

    // PDO options
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_STRINGIFY_FETCHES => false, // Do not convert numeric values to strings
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4;SET sql_mode=''",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true // Enable buffered queries to avoid issues with multiple queries inside transactions
    ],

    // Connection pool settings
    'pool' => [
        'max_connections' => 10,
        'timeout' => 30
    ]
];
