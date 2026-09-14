<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Password Reset Request DTO.
 */
final class ResetPasswordRequest
{
    public function __construct(
        public readonly string $emailOrUsername,
        public readonly string $resetToken,
        public readonly string $newPassword,
        public readonly string $passwordConfirmation
    ) {
    }
}
