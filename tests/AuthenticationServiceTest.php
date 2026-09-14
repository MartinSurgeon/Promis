<?php

declare(strict_types=1);

namespace Promis\Tests;

require_once dirname(__DIR__) . '/core/autoload.php';

use PDO;
use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Password;
use Promis\Core\Security\Session;
use Promis\Src\Identity\Domain\DTO\ActivateAccountRequest;
use Promis\Src\Identity\Domain\DTO\LoginRequest;
use Promis\Src\Identity\Domain\DTO\ResetPasswordRequest;
use Promis\Src\Identity\Repository\UserRepository;
use Promis\Src\Identity\Service\AuthenticationService;

/**
 * PROMIS Phase 2 Stage 2.4 Authentication & Identity Test Suite.
 * Validates Login, Account Activation, Status Constraints, Password Reset,
 * Session Hydration, CSRF Defense, and Clean Relational Teardown.
 */
final class AuthenticationServiceTest
{
    private PDO $db;
    private AuthenticationService $authService;
    private UserRepository $userRepo;
    private int $assertions = 0;

    // Tracked IDs for teardown
    private array $userIds = [];

    public function __construct()
    {
        App::bootstrap(dirname(__DIR__));
        $this->db = Connection::get();
        $this->userRepo = new UserRepository($this->db);
        $this->authService = new AuthenticationService($this->db, $this->userRepo);
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Phase 2 Stage 2.4 Authentication Test Suite\n";
        echo " University of Skills Training and Entrepreneurial Development\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        try {
            // Clean any leftover test_suite_* accounts
            $st = $this->db->query("SELECT id FROM users WHERE username LIKE 'test_suite_%'");
            $oldIds = $st->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($oldIds)) {
                $ph = implode(',', array_fill(0, count($oldIds), '?'));
                $this->db->prepare("DELETE FROM audit_logs WHERE actor_user_id IN ({$ph})")->execute($oldIds);
                $this->db->prepare("DELETE FROM users WHERE id IN ({$ph})")->execute($oldIds);
            }
            $this->testCsrfDefense();
            $this->testPasswordSecurity();
            $this->testValidLogin();
            $this->testInvalidPasswordLogin();
            $this->testNonExistentUserLogin();
            $this->testEmptyCredentialsValidation();
            $this->testPendingAccountLogin();
            $this->testSuspendedAccountLogin();
            $this->testAccountActivationJourney();
            $this->testPasswordResetJourney();
            $this->testLogoutSessionPurge();
        } finally {
            $this->teardown();
        }

        echo "\n===============================================================\n";
        echo " AUTHENTICATION SERVICE TEST SUMMARY\n";
        echo " Passed: {$this->assertions} / {$this->assertions}\n";
        echo " Failed: 0\n";
        echo "===============================================================\n\n";
        echo "ALL AUTHENTICATION & IDENTITY TESTS PASSED SUCCESSFULLY.\n";
    }

    private function assert(bool $condition, string $description): void
    {
        if (!$condition) {
            echo " [FAIL] {$description}\n";
            throw new \RuntimeException("Assertion failed: {$description}");
        }
        $this->assertions++;
        echo " [PASS] {$description}\n";
    }

    private function testCsrfDefense(): void
    {
        $token = Csrf::token();
        $this->assert(is_string($token) && strlen($token) === 64, 'Csrf::token() generates 64-char hex token');
        $this->assert(Csrf::validate($token), 'Csrf::validate() accepts authentic session token');
        $this->assert(!Csrf::validate('invalid-forged-token-12345'), 'Csrf::validate() rejects forged token');
        $this->assert(!Csrf::validate(null), 'Csrf::validate() rejects null token');
        $this->assert(!Csrf::validate(''), 'Csrf::validate() rejects empty token');
    }

    private function testPasswordSecurity(): void
    {
        $raw = 'InstitutionalP@ssw0rd2026';
        $hash = Password::hash($raw);
        $this->assert(Password::verify($raw, $hash), 'Password::verify() succeeds on matching plaintext');
        $this->assert(!Password::verify('WrongPassword123', $hash), 'Password::verify() rejects mismatching plaintext');
        $this->assert(!str_contains($hash, $raw), 'Password hash is completely opaque and does not disclose plaintext');
    }

    private function testValidLogin(): void
    {
        $userId = $this->createTestUser('test_suite_user', 'ts_user@usted.edu.gh', 'Test', 'User', 'ActivePass123!', 'ACTIVE');
        $req = new LoginRequest('test_suite_user', 'ActivePass123!', '192.168.1.10', 'Mozilla/5.0');

        $user = $this->authService->authenticate($req);

        $this->assert($user->id === $userId, 'authenticate() returns correct UserDTO id');
        $this->assert($user->username === 'test_suite_user', 'UserDTO username matches');
        $this->assert($user->email === 'ts_user@usted.edu.gh', 'UserDTO email matches');
        $this->assert($user->isActive(), 'UserDTO isActive() returns true');
        $this->assert(AuthManager::check(), 'AuthManager::check() returns true after successful login');
        $this->assert(AuthManager::userId() === $userId, 'AuthManager::userId() matches authenticated user id');

        $sessionUser = AuthManager::user();
        $this->assert($sessionUser !== null && $sessionUser['username'] === 'test_suite_user', 'Session user is correctly hydrated');

        // Verify last_login update in database
        $dbUser = $this->userRepo->findById($userId);
        $this->assert($dbUser->lastLoginAt !== null, 'User last_login_at timestamp is populated in DB');
    }

    private function testInvalidPasswordLogin(): void
    {
        AuthManager::logout();
        $this->createTestUser('test_suite_ama', 'ts_ama@usted.edu.gh', 'Ama', 'Asante', 'CorrectPass123!', 'ACTIVE');
        $req = new LoginRequest('test_suite_ama', 'WrongPassword!', '192.168.1.11', 'Mozilla/5.0');

        $caught = false;
        try {
            $this->authService->authenticate($req);
        } catch (ValidationException $e) {
            $caught = true;
            $this->assert(
                str_contains($e->getMessage(), 'login details are incorrect'),
                'Invalid password throws generic safe error message'
            );
        }
        $this->assert($caught, 'authenticate() throws ValidationException on invalid password');
        $this->assert(AuthManager::guest(), 'AuthManager::guest() remains true after failed login attempt');
    }

    private function testNonExistentUserLogin(): void
    {
        AuthManager::logout();
        $req = new LoginRequest('test_suite_nonexistent', 'SomePassword123!', '192.168.1.12', 'Mozilla/5.0');

        $caught = false;
        try {
            $this->authService->authenticate($req);
        } catch (ValidationException $e) {
            $caught = true;
            $this->assert(
                str_contains($e->getMessage(), 'login details are incorrect'),
                'Non-existent user receives identical generic error (anti-enumeration)'
            );
        }
        $this->assert($caught, 'authenticate() throws ValidationException for non-existent user');
    }

    private function testEmptyCredentialsValidation(): void
    {
        $caught = false;
        try {
            $this->authService->authenticate(new LoginRequest('', 'password'));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, 'authenticate() rejects empty username');

        $caught = false;
        try {
            $this->authService->authenticate(new LoginRequest('user', ''));
        } catch (ValidationException $e) {
            $caught = true;
        }
        $this->assert($caught, 'authenticate() rejects empty password');
    }

    private function testPendingAccountLogin(): void
    {
        AuthManager::logout();
        $this->createTestUser('test_suite_pending', 'ts_pending@usted.edu.gh', 'Pending', 'Staff', 'SomePass123!', 'PENDING');
        $req = new LoginRequest('test_suite_pending', 'SomePass123!', '192.168.1.13', 'Mozilla/5.0');

        $caught = false;
        try {
            $this->authService->authenticate($req);
        } catch (ValidationException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'pending activation'), 'Pending account error advises activation');
        }
        $this->assert($caught, 'authenticate() rejects pending account login');
    }

    private function testSuspendedAccountLogin(): void
    {
        AuthManager::logout();
        $this->createTestUser('test_suite_suspended', 'ts_suspended@usted.edu.gh', 'Suspended', 'Staff', 'SomePass123!', 'SUSPENDED');
        $req = new LoginRequest('test_suite_suspended', 'SomePass123!', '192.168.1.14', 'Mozilla/5.0');

        $caught = false;
        try {
            $this->authService->authenticate($req);
        } catch (ValidationException $e) {
            $caught = true;
            $this->assert(str_contains($e->getMessage(), 'inactive or restricted'), 'Suspended account error cites restriction');
        }
        $this->assert($caught, 'authenticate() rejects suspended account login');
    }

    private function testAccountActivationJourney(): void
    {
        AuthManager::logout();
        $userId = $this->createTestUser('test_suite_activation', 'ts_activation@usted.edu.gh', 'New', 'Lecturer', 'TemporaryInitialHash', 'PENDING');

        // Activate account
        $activateReq = new ActivateAccountRequest(
            identifier: 'test_suite_activation',
            activationCode: 'ACT-2026',
            password: 'BrandNewSecurePassword2026!',
            passwordConfirmation: 'BrandNewSecurePassword2026!'
        );

        $activatedUser = $this->authService->activateAccount($activateReq);

        $this->assert($activatedUser->id === $userId, 'activateAccount() returns correct user ID');
        $this->assert($activatedUser->status === 'ACTIVE', 'Activated user status is now ACTIVE');

        // Confirm database status
        $dbUser = $this->userRepo->findById($userId);
        $this->assert($dbUser->isActive(), 'Database confirms user status is ACTIVE');

        // Now test login with the newly set password
        $loginReq = new LoginRequest('test_suite_activation', 'BrandNewSecurePassword2026!');
        $loggedInUser = $this->authService->authenticate($loginReq);
        $this->assert($loggedInUser->id === $userId, 'Subsequent login with newly activated password succeeds');
        $this->assert(AuthManager::check(), 'AuthManager session is active after activation login');
    }

    private function testPasswordResetJourney(): void
    {
        AuthManager::logout();
        $userId = $this->createTestUser('test_suite_reset', 'ts_reset@usted.edu.gh', 'Reset', 'Officer', 'OldPassword123!', 'ACTIVE');

        // 1. Request Password Reset
        $requested = $this->authService->requestPasswordReset('ts_reset@usted.edu.gh');
        $this->assert($requested, 'requestPasswordReset() returns true for active user');

        // 2. Perform Reset
        $resetReq = new ResetPasswordRequest(
            emailOrUsername: 'ts_reset@usted.edu.gh',
            resetToken: 'RESET-TOKEN-SAMPLE',
            newPassword: 'BrandNewResetPassword2026!',
            passwordConfirmation: 'BrandNewResetPassword2026!'
        );
        $resetSuccess = $this->authService->resetPassword($resetReq);
        $this->assert($resetSuccess, 'resetPassword() returns true on successful reset');

        // 3. Login with New Password
        $loginReq = new LoginRequest('test_suite_reset', 'BrandNewResetPassword2026!');
        $loggedIn = $this->authService->authenticate($loginReq);
        $this->assert($loggedIn->id === $userId, 'Login succeeds with new reset password');
    }

    private function testLogoutSessionPurge(): void
    {
        $this->assert(AuthManager::check(), 'AuthManager is logged in before logout test');
        $this->authService->logout();
        $this->assert(AuthManager::guest(), 'AuthManager::guest() is true after authService->logout()');
        $this->assert(AuthManager::user() === null, 'AuthManager::user() is null after logout');
    }

    private function createTestUser(string $username, string $email, string $firstName, string $lastName, string $password, string $status): int
    {
        $hash = Password::hash($password);
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, status, created_at, created_by)
            VALUES (:u, :e, :p, :fn, :ln, :st, NOW(), NULL)
        ");
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':p' => $hash,
            ':fn' => $firstName,
            ':ln' => $lastName,
            ':st' => $status,
        ]);
        $id = (int)$this->db->lastInsertId();
        $this->userIds[] = $id;
        return $id;
    }

    private function teardown(): void
    {
        AuthManager::logout();

        if (!empty($this->userIds)) {
            $placeholders = implode(',', array_fill(0, count($this->userIds), '?'));

            // Clean audit logs
            $this->db->prepare("DELETE FROM audit_logs WHERE actor_user_id IN ({$placeholders})")->execute($this->userIds);

            // Clean users
            $this->db->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($this->userIds);
        }
    }
}

// Execute test suite
$test = new AuthenticationServiceTest();
$test->run();
