<?php

declare(strict_types=1);

use Promis\Core\Support\Env;

return [
    'name' => Env::get('SESSION_COOKIE_NAME', 'promis_session'),
    'lifetime' => (int)Env::get('SESSION_LIFETIME', 7200), // 2 hours
    'secure' => (bool)Env::get('SESSION_SECURE', false),
    'httponly' => (bool)Env::get('SESSION_HTTPONLY', true),
    'samesite' => Env::get('SESSION_SAMESITE', 'Lax'),
    'inactivity_timeout' => 1800, // 30 minutes of idle time triggers logout
];
