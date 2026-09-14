<?php

declare(strict_types=1);

use Promis\Core\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'PROMIS - USTED'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool)Env::get('APP_DEBUG', false),
    'url' => Env::get('APP_URL', 'http://localhost/promis/public'),
    'timezone' => Env::get('APP_TIMEZONE', 'Africa/Accra'),
    'key' => Env::get('APP_KEY', ''),
    'log_path' => Env::get('STORAGE_LOG_PATH', 'storage/logs/app.log'),
    'upload_path' => Env::get('STORAGE_UPLOAD_PATH', 'storage/uploads'),
];
