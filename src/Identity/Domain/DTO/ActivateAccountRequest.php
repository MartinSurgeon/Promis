<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Domain\DTO;

/**
 * Account Activation Request DTO.
 */
final class ActivateAccountRequest
{
    public function __construct(
        public readonly string $identifier, // Email or Username
        public readonly string $activationCode,
        public readonly string $password,
        public readonly string $passwordConfirmation
    ) {
    }
}
