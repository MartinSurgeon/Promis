<?php

declare(strict_types=1);

namespace Promis\Core\Http;

/**
 * HTTP Request Representation.
 * Encapsulates method, URI, query parameters, body inputs, headers, and client metadata.
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $bodyParams;
    private array $headers;
    private array $server;
    private array $routeParams = [];

    public function __construct(
        string $method,
        string $uri,
        array $queryParams = [],
        array $bodyParams = [],
        array $headers = [],
        array $server = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
        $this->headers = $headers;
        $this->server = $server;
    }

    /**
     * Capture the current HTTP request from PHP superglobals.
     */
    public static function capture(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Normalize URI if hosted inside subdirectories (e.g., /promis/public)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        if ($baseDir !== '/' && $baseDir !== '\\' && str_starts_with($uri, $baseDir)) {
            $uri = substr($uri, strlen($baseDir));
            if ($uri === '' || $uri === false) {
                $uri = '/';
            }
        }

        // Method override support for PUT/DELETE via POST _method field
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string)$_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        // Parse JSON body if Content-Type is application/json
        $body = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            if ($rawInput !== false && $rawInput !== '') {
                $json = json_decode($rawInput, true);
                if (is_array($json)) {
                    $body = $json;
                }
            }
        }

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        return new self($method, $uri, $_GET, $body, $headers, $_SERVER);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($this->queryParams, $this->bodyParams);
        if ($key === null) {
            return $all;
        }
        return $all[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->bodyParams;
        }
        return $this->bodyParams[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalizedKey = strtolower($key);
        foreach ($this->headers as $hKey => $hVal) {
            if (strtolower($hKey) === $normalizedKey) {
                return (string)$hVal;
            }
        }
        return $default;
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function userAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    public function isJson(): bool
    {
        $accept = $this->header('Accept', '');
        $contentType = $this->header('Content-Type', '');
        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function params(): array
    {
        return $this->routeParams;
    }
}
