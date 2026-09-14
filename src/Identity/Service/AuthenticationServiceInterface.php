<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Service;

use Promis\Src\Identity\Domain\DTO\ActivateAccountRequest;
use Promis\Src\Identity\Domain\DTO\LoginRequest;
use Promis\Src\Identity\Domain\DTO\ResetPasswordRequest;
use Promis\Src\Identity\Domain\DTO\UserDTO;

/**
 * Contract for Authentication, Account Activation, and Session Lifecycle Services.
 */
interface AuthenticationServiceInterface
{
    public function authenticate(LoginRequest $request): UserDTO;

    public function activateAccount(ActivateAccountRequest $request): UserDTO;

    public function requestPasswordReset(string $emailOrUsername): bool;

    public function resetPassword(ResetPasswordRequest $request): bool;

    public function logout(): void;
}
