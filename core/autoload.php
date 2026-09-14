<?php

declare(strict_types=1);

/**
 * Lightweight Zero-Dependency PSR-4 Autoloader for PROMIS.
 */
spl_autoload_register(function (string $class): void {
    $prefixMap = [
        'Promis\\Core\\' => __DIR__ . '/',
        'Promis\\Src\\' => dirname(__DIR__) . '/src/',
    ];

    foreach ($prefixMap as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
