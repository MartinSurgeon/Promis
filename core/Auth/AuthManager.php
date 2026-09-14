<?php

declare(strict_types=1);

namespace Promis\Core\Auth;

use Promis\Core\Security\Session;

/**
 * Authentication Foundation Manager.
 * Manages user authentication state, session bindings, and identity resolution.
 */
final class AuthManager
{
    private const SESSION_USER_KEY = '_auth_user';

    /**
     * Check if a user is currently authenticated.
     */
    public static function check(): bool
    {
        return Session::has(self::SESSION_USER_KEY) && !empty(Session::get(self::SESSION_USER_KEY));
    }

    /**
     * Check if the visitor is a guest (unauthenticated).
     */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * Retrieve the currently authenticated user record / payload.
     */
    public static function user(): ?array
    {
        $user = Session::get(self::SESSION_USER_KEY);
        return is_array($user) ? $user : null;
    }

    /**
     * Retrieve the authenticated user ID.
     */
    public static function userId(): ?int
    {
        $user = self::user();
        if ($user && isset($user['id'])) {
            return (int)$user['id'];
        }
        return null;
    }

    /**
     * Authenticate and bind a user identity into the session.
     * Regenerates session ID to protect against session fixation.
     */
    public static function login(array $userData): void
    {
        Session::regenerate(true);
        Session::set(self::SESSION_USER_KEY, $userData);
    }

    /**
     * Terminate authenticated session and purge user identity.
     */
    public static function logout(): void
    {
        Session::remove(self::SESSION_USER_KEY);
        Session::destroy();
    }
}
