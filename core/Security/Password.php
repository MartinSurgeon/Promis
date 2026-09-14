<?php

declare(strict_types=1);

namespace Promis\Core\Security;

/**
 * Modern Cryptographic Password Hashing and Verification Handler.
 */
final class Password
{
    /**
     * Determine the strongest available password hashing algorithm.
     */
    public static function defaultAlgo(): string|int|null
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return PASSWORD_ARGON2ID;
        }

        return PASSWORD_DEFAULT;
    }

    /**
     * Hash a plaintext password.
     */
    public static function hash(string $password, array $options = []): string
    {
        $algo = self::defaultAlgo();
        $hash = password_hash($password, $algo, $options);

        if ($hash === false) {
            throw new \RuntimeException('Failed to hash password with cryptographic algorithm.');
        }

        return $hash;
    }

    /**
     * Verify a plaintext password against a stored hash using timing-safe comparison.
     */
    public static function verify(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Check if a password hash needs rehashing based on algorithm or cost upgrades.
     */
    public static function needsRehash(string $hash, array $options = []): bool
    {
        $algo = self::defaultAlgo();
        return password_needs_rehash($hash, $algo, $options);
    }
}
