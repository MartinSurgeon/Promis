<?php

declare(strict_types=1);

namespace Promis\Core\Support;

use Promis\Core\Auth\AuthManager;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Sanitizer;
use Promis\Core\Security\Session;

/**
 * Safe Server-Side View Template Renderer.
 * Enforces layout inheritance, path traversal prevention, and output escaping helpers.
 */
final class View
{
    private static string $viewsPath;

    public static function init(string $viewsPath): void
    {
        self::$viewsPath = rtrim($viewsPath, '/\\');
    }

    /**
     * Render a view file with optional layout wrapping.
     */
    public static function render(string $template, array $data = [], ?string $layout = 'main'): string
    {
        if (empty(self::$viewsPath)) {
            self::$viewsPath = dirname(__DIR__, 2) . '/views';
        }

        $templatePath = self::resolvePath($template);
        if (!file_exists($templatePath)) {
            throw new NotFoundException("View template '{$template}' not found.");
        }

        // Render template content inside isolated scope
        $content = self::renderFile($templatePath, $data);

        // If layout is requested, wrap content inside the layout
        if ($layout !== null) {
            $layoutPath = self::resolvePath("layouts/{$layout}");
            if (!file_exists($layoutPath)) {
                throw new NotFoundException("View layout '{$layout}' not found.");
            }
            $layoutData = array_merge($data, ['content' => $content]);
            return self::renderFile($layoutPath, $layoutData);
        }

        return $content;
    }

    /**
     * Resolve template name to filesystem path with path-traversal protection.
     */
    private static function resolvePath(string $template): string
    {
        $normalized = str_replace(['.', '/'], DIRECTORY_SEPARATOR, $template);
        $fullPath = self::$viewsPath . DIRECTORY_SEPARATOR . $normalized . '.php';

        $realBase = realpath(self::$viewsPath);
        $realDir = realpath(dirname($fullPath));

        // Path traversal defense
        if ($realDir !== false && $realBase !== false && !str_starts_with($realDir, $realBase)) {
            throw new \SecurityException("Access to template outside view directory is denied.");
        }

        return $fullPath;
    }

    /**
     * Execute template file inside isolated buffer with helper bindings.
     */
    private static function renderFile(string $filePath, array $data): string
    {
        if (!isset($data['appUrl'])) {
            $baseDir = '';
            if (php_sapi_name() !== 'cli' && isset($_SERVER['SCRIPT_NAME'])) {
                $rawBase = dirname($_SERVER['SCRIPT_NAME']);
                if ($rawBase !== '/' && $rawBase !== '\\') {
                    $baseDir = rtrim(str_replace('\\', '/', $rawBase), '/');
                }
            }
            $data['appUrl'] = $baseDir;
        }

        extract($data, EXTR_SKIP);

        // Convenient closure helpers available in all templates
        $e = fn(mixed $val): string => Sanitizer::escape($val);
        $csrf = fn(): string => Csrf::field();
        $csrfToken = fn(): string => Csrf::token();
        $user = fn(): ?array => AuthManager::user();
        $hasFlash = fn(string $k): bool => Session::hasFlash($k);
        $getFlash = fn(string $k): mixed => Session::getFlash($k);
        $old = fn(string $k, mixed $default = ''): mixed => Session::getFlash('old_' . $k) ?? $default;

        ob_start();
        try {
            require $filePath;
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
