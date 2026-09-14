<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Authentication Login Request DTO.
 */
final class LoginRequest
{
    public function __construct(
        public readonly string $usernameOrEmail,
        public readonly string $password,
        public readonly ?string $ipAddress = '127.0.0.1',
        public readonly ?string $userAgent = null
    ) {
    }
}
