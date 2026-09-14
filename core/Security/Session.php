<?php

declare(strict_types=1);

namespace Promis\Core\Security;

/**
 * Secure Session Manager.
 * Enforces HttpOnly, SameSite, Strict Mode, Fixation Protection, and Inactivity Timeouts.
 */
final class Session
{
    private static bool $started = false;
    private static array $config = [];

    /**
     * Initialize and configure session security parameters.
     */
    public static function start(array $config = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            self::checkTimeout();
            return;
        }

        self::$config = $config;

        $lifetime = $config['lifetime'] ?? 7200;
        $secure = (bool)($config['secure'] ?? false);
        $httpOnly = (bool)($config['httponly'] ?? true);
        $sameSite = $config['samesite'] ?? 'Lax';
        $cookieName = $config['name'] ?? 'promis_session';

        if (!headers_sent()) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', $httpOnly ? '1' : '0');
            ini_set('session.cookie_samesite', $sameSite);

            session_name($cookieName);

            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ]);
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        self::$started = true;

        self::checkTimeout();
    }

    /**
     * Check for session inactivity timeout.
     */
    private static function checkTimeout(): void
    {
        $timeout = self::$config['inactivity_timeout'] ?? 1800;
        $now = time();

        if (isset($_SESSION['_last_activity']) && ($now - $_SESSION['_last_activity'] > $timeout)) {
            self::destroy();
            return;
        }

        $_SESSION['_last_activity'] = $now;
    }

    /**
     * Regenerate session ID to prevent session fixation attacks.
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!headers_sent()) {
                return session_regenerate_id($deleteOldSession);
            }
            return true;
        }
        return false;
    }

    /**
     * Store a value in session.
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve a value from session.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists.
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a key from session.
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Set a flash message for the next request.
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Retrieve and clear a flash message.
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        if (isset($_SESSION['_flash'][$key])) {
            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }
        return $default;
    }

    /**
     * Check if a flash message exists.
     */
    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Terminate and purge session data completely.
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!headers_sent() && ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            @session_destroy();
        }
        self::$started = false;
    }
}
