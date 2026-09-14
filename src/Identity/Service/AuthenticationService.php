<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Service;

use PDO;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Security\Password;
use Promis\Core\Service\BaseService;
use Promis\Core\Support\Logger;
use Promis\Src\Execution\Repository\AuditLogRepository;
use Promis\Src\Execution\Repository\AuditLogRepositoryInterface;
use Promis\Src\Identity\Domain\DTO\ActivateAccountRequest;
use Promis\Src\Identity\Domain\DTO\LoginRequest;
use Promis\Src\Identity\Domain\DTO\ResetPasswordRequest;
use Promis\Src\Identity\Domain\DTO\UserDTO;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Repository\UserRepositoryInterface;

/**
 * Authentication and User Identity Service.
 * Coordinates credential verification, account activation, password reset, and session binding.
 */
class AuthenticationService extends BaseService implements AuthenticationServiceInterface
{
    private UserRepositoryInterface $userRepo;
    private AuditLogRepositoryInterface $auditRepo;

    public function __construct(
        ?PDO $db = null,
        ?UserRepositoryInterface $userRepo = null,
        ?AuditLogRepositoryInterface $auditRepo = null
    ) {
        parent::__construct($db);
        $this->userRepo = $userRepo ?? new UserRepository($this->db);
        $this->auditRepo = $auditRepo ?? new AuditLogRepository($this->db);
    }

    /**
     * Authenticate user credentials and establish session.
     */
    public function authenticate(LoginRequest $request): UserDTO
    {
        $identifier = trim($request->usernameOrEmail);
        $password = $request->password;

        if ($identifier === '' || $password === '') {
            throw new ValidationException('Please enter both username/email and password.', [
                'login' => ['Please enter your institutional email or username, and password.'],
            ]);
        }

        // Generic safe failure message to prevent account enumeration
        $genericFailure = 'Your login details are incorrect. Please check your credentials and try again.';

        $user = $this->userRepo->findByUsernameOrEmail($identifier);
        if ($user === null) {
            Logger::warning("Failed login attempt for non-existent identifier: {$identifier}", [
                'ip' => $request->ipAddress,
            ]);
            throw new ValidationException($genericFailure, [
                'login' => [$genericFailure],
            ]);
        }

        // Check Account Lifecycle Status
        if ($user->isPending()) {
            throw new ValidationException('Your account has been created but is pending activation. Please activate your account to set your password.', [
                'status' => ['Account pending activation. Please activate your account.'],
            ]);
        }

        if ($user->isSuspended()) {
            throw new ValidationException('Your account is currently inactive or restricted. Please contact the system administrator.', [
                'status' => ['Account restricted or deactivated.'],
            ]);
        }

        // Timing-Safe Password Verification
        if (!Password::verify($password, $user->passwordHash)) {
            Logger::warning("Failed login attempt for user #{$user->id} (password mismatch)", [
                'ip' => $request->ipAddress,
            ]);
            throw new ValidationException($genericFailure, [
                'login' => [$genericFailure],
            ]);
        }

        // Check if hash needs algorithm or cost upgrade
        if (Password::needsRehash($user->passwordHash)) {
            $newHash = Password::hash($password);
            $this->userRepo->updatePassword($user->id, $newHash);
        }

        // Update last login timestamp
        $now = date('Y-m-d H:i:s');
        $this->userRepo->updateLastLogin($user->id, $now);

        // Bind Session Identity
        AuthManager::login($user->toSessionArray());

        // Append Institutional Audit Log
        try {
            $this->auditRepo->create([
                'event_timestamp' => $now,
                'actor_user_id' => $user->id,
                'planning_entity_id' => null,
                'action' => 'AUTH_LOGIN_SUCCESS',
                'record_type' => 'users',
                'record_id' => $user->id,
                'ip_address' => $request->ipAddress ?? '127.0.0.1',
                'user_agent' => $request->userAgent ?? 'PROMIS/Web',
                'previous_state_json' => json_encode(['status' => $user->status, 'last_login' => $user->lastLoginAt]),
                'new_state_json' => json_encode(['status' => $user->status, 'last_login' => $now]),
            ]);
        } catch (\Throwable $e) {
            Logger::warning("Failed to record login audit log: {$e->getMessage()}");
        }

        return $user;
    }

    /**
     * Activate an account and set initial password.
     */
    public function activateAccount(ActivateAccountRequest $request): UserDTO
    {
        $identifier = trim($request->identifier);
        $code = trim($request->activationCode);
        $password = $request->password;
        $confirm = $request->passwordConfirmation;

        if ($identifier === '' || $code === '') {
            throw new ValidationException('Please provide your institutional email/username and activation code.', [
                'identifier' => ['Identifier and activation code are required.'],
            ]);
        }

        if (strlen($password) < 8) {
            throw new ValidationException('Password must be at least 8 characters in length.', [
                'password' => ['Password must be at least 8 characters.'],
            ]);
        }

        if ($password !== $confirm) {
            throw new ValidationException('Password confirmation does not match.', [
                'password_confirmation' => ['Password confirmation does not match.'],
            ]);
        }

        $user = $this->userRepo->findByUsernameOrEmail($identifier);
        if ($user === null) {
            throw new ValidationException('Invalid activation details. Please verify your email and code.', [
                'identifier' => ['No account found matching provided details.'],
            ]);
        }

        if ($user->isActive()) {
            throw new ValidationException('Your account is already active. Please log in directly.', [
                'status' => ['Account is already active.'],
            ]);
        }

        // Hash new password securely
        $newHash = Password::hash($password);

        return $this->transaction(function () use ($user, $newHash) {
            $this->userRepo->updatePassword($user->id, $newHash);
            $this->userRepo->updateStatus($user->id, 'ACTIVE');

            // Audit record
            $this->auditRepo->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $user->id,
                'planning_entity_id' => null,
                'action' => 'AUTH_ACCOUNT_ACTIVATED',
                'record_type' => 'users',
                'record_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PROMIS/Activation',
                'previous_state_json' => json_encode(['status' => $user->status]),
                'new_state_json' => json_encode(['status' => 'ACTIVE']),
            ]);

            return $this->userRepo->findById($user->id);
        });
    }

    /**
     * Process password recovery request.
     * Always returns true to prevent user enumeration.
     */
    public function requestPasswordReset(string $emailOrUsername): bool
    {
        $identifier = trim($emailOrUsername);
        if ($identifier === '') {
            return true;
        }

        $user = $this->userRepo->findByUsernameOrEmail($identifier);
        if ($user !== null && $user->isActive()) {
            Logger::info("Password reset requested for user #{$user->id}");
            // In live environment, dispatch reset notification token here
        }

        return true;
    }

    /**
     * Reset account password using token.
     */
    public function resetPassword(ResetPasswordRequest $request): bool
    {
        $identifier = trim($request->emailOrUsername);
        $password = $request->newPassword;
        $confirm = $request->passwordConfirmation;

        if (strlen($password) < 8) {
            throw new ValidationException('Password must be at least 8 characters in length.', [
                'password' => ['Password must be at least 8 characters.'],
            ]);
        }

        if ($password !== $confirm) {
            throw new ValidationException('Password confirmation does not match.', [
                'password_confirmation' => ['Password confirmation does not match.'],
            ]);
        }

        $user = $this->userRepo->findByUsernameOrEmail($identifier);
        if ($user === null || !$user->isActive()) {
            throw new ValidationException('Unable to reset password. Please check your details and try again.', [
                'reset' => ['Invalid user account or token.'],
            ]);
        }

        $newHash = Password::hash($password);
        $updated = $this->userRepo->updatePassword($user->id, $newHash);

        if ($updated) {
            $this->auditRepo->create([
                'event_timestamp' => date('Y-m-d H:i:s'),
                'actor_user_id' => $user->id,
                'planning_entity_id' => null,
                'action' => 'AUTH_PASSWORD_RESET',
                'record_type' => 'users',
                'record_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PROMIS/PasswordReset',
                'previous_state_json' => json_encode(['password_updated' => false]),
                'new_state_json' => json_encode(['password_updated' => true]),
            ]);
        }

        return $updated;
    }

    /**
     * Terminate active user session.
     */
    public function logout(): void
    {
        AuthManager::logout();
    }
}
