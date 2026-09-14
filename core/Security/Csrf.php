<?php

declare(strict_types=1);

namespace Promis\Core\Security;

/**
 * Cross-Site Request Forgery (CSRF) Protection Handler.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Get the active CSRF token or generate a new cryptographically secure token.
     */
    public static function token(): string
    {
        $existing = Session::get(self::SESSION_KEY);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return self::regenerate();
    }

    /**
     * Generate and store a new cryptographically secure token.
     */
    public static function generate(): string
    {
        return self::token();
    }

    /**
     * Force regenerate a fresh CSRF token.
     */
    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        return $token;
    }

    /**
     * Validate an incoming CSRF token using timing-safe comparison.
     */
    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $storedToken = Session::get(self::SESSION_KEY);
        if (!is_string($storedToken) || $storedToken === '') {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    /**
     * Render an HTML hidden input containing the CSRF token.
     */
    public static function field(): string
    {
        $token = self::token();
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            Sanitizer::escape($token)
        );
    }
}
