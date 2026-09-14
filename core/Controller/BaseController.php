<?php

declare(strict_types=1);

namespace Promis\Core\Controller;

use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Security\Csrf;
use Promis\Core\Support\View;

/**
 * Base Controller Class.
 * Handles HTTP requests, input validation, CSRF verification, and response generation.
 * Controllers must NEVER contain SQL queries or execute direct database statements.
 */
abstract class BaseController
{
    /**
     * Render a view template wrapped in a layout.
     */
    protected function view(string $template, array $data = [], ?string $layout = 'main', int $statusCode = 200): Response
    {
        $html = View::render($template, $data, $layout);
        return Response::html($html, $statusCode);
    }

    /**
     * Return a JSON response envelope.
     */
    protected function json(mixed $data, int $statusCode = 200, array $headers = []): Response
    {
        return Response::json($data, $statusCode, $headers);
    }

    /**
     * Return an HTTP redirect response.
     */
    protected function redirect(string $url, int $statusCode = 302): Response
    {
        return Response::redirect($url, $statusCode);
    }

    /**
     * Enforce CSRF token verification on state-changing requests.
     */
    protected function validateCsrf(Request $request): void
    {
        $token = $request->input('_csrf_token') ?? $request->header('X-CSRF-TOKEN');

        if (!Csrf::validate($token)) {
            throw new AuthorizationException('CSRF token validation failed or token expired.');
        }
    }

    /**
     * Basic input presence validation helper.
     */
    protected function validate(Request $request, array $rules): array
    {
        $errors = [];
        $data = [];

        foreach ($rules as $field => $ruleString) {
            $value = $request->input($field);
            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = "The {$field} field is required.";
                }
                if ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address.";
                }
                if (str_starts_with($rule, 'min:') && is_string($value)) {
                    $min = (int)substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                }
            }

            $data[$field] = $value;
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed.', $errors);
        }

        return $data;
    }
}
