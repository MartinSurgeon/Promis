<?php

declare(strict_types=1);

namespace Promis\Core\Http;

/**
 * HTTP Response Representation.
 * Emits HTTP status codes, security headers, HTML payloads, JSON envelopes, and redirections.
 */
final class Response
{
    private int $statusCode;
    private array $headers = [];
    private string $content;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create a standard HTML/text response.
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $defaultHeaders = ['Content-Type' => 'text/html; charset=UTF-8'];
        return new self($html, $statusCode, array_merge($defaultHeaders, $headers));
    }

    /**
     * Create a JSON response envelope.
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"error":"JSON encoding failure"}';
            $statusCode = 500;
        }

        $defaultHeaders = ['Content-Type' => 'application/json; charset=UTF-8'];
        return new self($json, $statusCode, array_merge($defaultHeaders, $headers));
    }

    /**
     * Create an HTTP redirect response.
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        if (php_sapi_name() !== 'cli' && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $rawBase = dirname($scriptName);
            if ($rawBase !== '/' && $rawBase !== '\\') {
                $baseDir = rtrim(str_replace('\\', '/', $rawBase), '/');
                if ($baseDir !== '' && !str_starts_with($url, $baseDir . '/')) {
                    $url = $baseDir . $url;
                }
            }
        }

        return new self('', $statusCode, ['Location' => $url]);
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $val) {
            if (strcasecmp($key, $name) === 0) {
                return (string)$val;
            }
        }
        return null;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getBody(): string
    {
        return $this->content;
    }

    /**
     * Send headers and body to the output buffer.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            // Default security headers
            $securityHeaders = [
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'SAMEORIGIN',
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
            ];

            foreach ($securityHeaders as $name => $val) {
                if (!isset($this->headers[$name])) {
                    header("{$name}: {$val}");
                }
            }

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->content;
    }
}
