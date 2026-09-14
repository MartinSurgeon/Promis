<?php

declare(strict_types=1);

namespace Promis\Src\Presentation\Controller;

use Promis\Core\Auth\AuthManager;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Session;
use Promis\Core\Support\View;
use Promis\Src\Identity\Domain\DTO\ActivateAccountRequest;
use Promis\Src\Identity\Domain\DTO\LoginRequest;
use Promis\Src\Identity\Domain\DTO\ResetPasswordRequest;
use Promis\Src\Identity\Service\AuthenticationService;
use Promis\Src\Identity\Service\AuthenticationServiceInterface;
use Throwable;

/**
 * Institutional Authentication and Identity Lifecycle Controller.
 * Handles Login, Account Activation, Password Recovery, and Session Termination.
 */
final class AuthController
{
    private AuthenticationServiceInterface $authService;

    public function __construct(?AuthenticationServiceInterface $authService = null)
    {
        $this->authService = $authService ?? new AuthenticationService();
    }

    /**
     * Display Login Form.
     */
    public function showLoginForm(Request $request): Response
    {
        if (AuthManager::check()) {
            return Response::redirect('/dashboard');
        }

        $html = View::render('auth/login', [
            'title' => 'PROMIS - Institutional Sign In',
            'appUrl' => $this->resolveAppUrl($request),
        ], 'guest');

        return Response::html($html);
    }

    /**
     * Process User Authentication.
     */
    public function login(Request $request): Response
    {
        $this->validateCsrf($request);

        $identifier = trim((string)$request->post('username_or_email', ''));
        $password = (string)$request->post('password', '');

        try {
            $loginReq = new LoginRequest(
                usernameOrEmail: $identifier,
                password: $password,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $user = $this->authService->authenticate($loginReq);

            Session::flash('success', "Welcome back, {$user->getFullName()}.");
            return Response::redirect('/dashboard');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/login');
        } catch (Throwable $e) {
            Session::flash('error', 'An unexpected error occurred during authentication.');
            return Response::redirect('/login');
        }
    }

    /**
     * Display First-Time Account Activation Form.
     */
    public function showActivateForm(Request $request): Response
    {
        if (AuthManager::check()) {
            return Response::redirect('/dashboard');
        }

        $html = View::render('auth/activate', [
            'title' => 'PROMIS - Account Activation',
            'appUrl' => $this->resolveAppUrl($request),
        ], 'guest');

        return Response::html($html);
    }

    /**
     * Process Account Activation / First-Time Setup.
     */
    public function activate(Request $request): Response
    {
        $this->validateCsrf($request);

        $identifier = trim((string)$request->post('username_or_email', ''));
        $activationCode = trim((string)$request->post('activation_code', ''));
        $password = (string)$request->post('password', '');
        $passwordConfirm = (string)$request->post('password_confirmation', '');

        if ($password !== $passwordConfirm) {
            Session::flash('error', 'The provided passwords do not match. Please ensure both fields are identical.');
            return Response::redirect('/activate');
        }

        try {
            $req = new ActivateAccountRequest(
                identifier: $identifier,
                activationCode: $activationCode,
                password: $password,
                passwordConfirmation: $passwordConfirm
            );

            $user = $this->authService->activateAccount($req);

            Session::flash('success', "Account successfully activated for {$user->getFullName()}. Please sign in with your credentials.");
            return Response::redirect('/login');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/activate');
        } catch (Throwable $e) {
            Session::flash('error', 'Failed to activate account. Please contact your institutional administrator.');
            return Response::redirect('/activate');
        }
    }

    /**
     * Display Password Recovery Request Form.
     */
    public function showForgotPasswordForm(Request $request): Response
    {
        $html = View::render('auth/forgot_password', [
            'title' => 'PROMIS - Password Recovery',
            'appUrl' => $this->resolveAppUrl($request),
        ], 'guest');

        return Response::html($html);
    }

    /**
     * Process Password Recovery Request.
     */
    public function forgotPassword(Request $request): Response
    {
        $this->validateCsrf($request);

        $identifier = trim((string)$request->post('username_or_email', ''));

        if ($identifier !== '') {
            $this->authService->requestPasswordReset($identifier);
        }

        // Generic confirmation to avoid account enumeration
        Session::flash('info', 'If an active institutional account was found, password recovery instructions have been logged and dispatched.');
        return Response::redirect('/login');
    }

    /**
     * Display Password Reset Form.
     */
    public function showResetPasswordForm(Request $request): Response
    {
        $token = (string)$request->query('token', '');

        $html = View::render('auth/reset_password', [
            'title' => 'PROMIS - Set New Password',
            'token' => $token,
            'appUrl' => $this->resolveAppUrl($request),
        ], 'guest');

        return Response::html($html);
    }

    /**
     * Process Password Reset.
     */
    public function resetPassword(Request $request): Response
    {
        $this->validateCsrf($request);

        $identifier = trim((string)$request->post('username_or_email', ''));
        $token = trim((string)$request->post('token', ''));
        $password = (string)$request->post('password', '');
        $passwordConfirm = (string)$request->post('password_confirmation', '');

        if ($password !== $passwordConfirm) {
            Session::flash('error', 'The passwords do not match. Please verify and re-enter.');
            return Response::redirect('/reset-password?token=' . urlencode($token));
        }

        try {
            $req = new ResetPasswordRequest(
                emailOrUsername: $identifier,
                resetToken: $token,
                newPassword: $password,
                passwordConfirmation: $passwordConfirm
            );

            $this->authService->resetPassword($req);

            Session::flash('success', 'Your password has been successfully updated. You may now sign in.');
            return Response::redirect('/login');
        } catch (ValidationException $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect('/reset-password?token=' . urlencode($token));
        } catch (Throwable $e) {
            Session::flash('error', 'Password reset failed. The link may have expired or is invalid.');
            return Response::redirect('/login');
        }
    }

    /**
     * Terminate Session and Log Out.
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout();
        Session::flash('info', 'You have been securely signed out of PROMIS.');
        return Response::redirect('/login');
    }

    /**
     * Validate CSRF token from POST body or Header.
     */
    private function validateCsrf(Request $request): void
    {
        $token = $request->post('_csrf_token') ?? $request->header('X-CSRF-TOKEN');
        if (!Csrf::validate($token)) {
            if ($request->isJson()) {
                throw new AuthorizationException('CSRF token validation failed or token expired.');
            }
            Session::flash('error', 'Your session expired or the request was invalid. Please try again.');
            $base = $this->resolveAppUrl($request);
            header("Location: {$base}/login");
            exit;
        }
    }

    /**
     * Helper to resolve base public application URL.
     */
    private function resolveAppUrl(Request $request): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = dirname($scriptName);
        if ($base === '/' || $base === '\\') {
            return '';
        }
        return rtrim($base, '/\\');
    }
}
