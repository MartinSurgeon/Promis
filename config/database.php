<?php

declare(strict_types=1);

use Promis\Core\Support\Env;

return [
    'default' => Env::get('DB_ENGINE', 'mariadb'), // 'mariadb' or 'mysql'

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => (int)Env::get('DB_PORT', 3306),
            'database' => Env::get('DB_DATABASE', 'promis'),
            'username' => Env::get('DB_USERNAME', 'root'),
            'password' => Env::get('DB_PASSWORD', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
            'collation' => Env::get('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_0900_ai_ci'",
            ],
        ],

        'mariadb' => [
            'driver' => 'mysql',
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => (int)Env::get('DB_PORT', 3306),
            'database' => Env::get('DB_DATABASE', 'promis'),
            'username' => Env::get('DB_USERNAME', 'root'),
            'password' => Env::get('DB_PASSWORD', ''),
            'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
            'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
            ],
        ],
    ],
];
