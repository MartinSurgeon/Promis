<?php

declare(strict_types=1);

namespace Promis\Core\Http;

use Promis\Core\Exception\NotFoundException;

/**
 * Fast Lightweight HTTP Router.
 * Supports HTTP verbs, parameterized paths, middleware chaining, and strict 404/405 handling.
 */
final class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];

    /**
     * Register a GET route.
     */
    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a route for a specific HTTP method.
     */
    public function add(string $method, string $path, mixed $handler, array $middleware = []): self
    {
        $normalizedPath = $this->normalizePath($path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $normalizedPath,
            'pattern' => $this->compilePattern($normalizedPath),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
        return $this;
    }

    /**
     * Add a global middleware to all routes.
     */
    public function use(callable $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Dispatch an incoming HTTP Request against registered routes.
     */
    public function dispatch(Request $request): Response
    {
        $requestMethod = $request->method();
        $requestPath = $this->normalizePath($request->path());

        $matchedRoutesForPath = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $requestPath, $matches)) {
                $matchedRoutesForPath[] = $route['method'];

                if ($route['method'] === $requestMethod) {
                    // Extract named parameters
                    $params = [];
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = urldecode($value);
                        }
                    }
                    $request->setRouteParams($params);

                    // Execute middleware chain
                    $allMiddleware = array_merge($this->globalMiddleware, $route['middleware']);
                    foreach ($allMiddleware as $mw) {
                        if (is_callable($mw)) {
                            $result = $mw($request);
                            if ($result instanceof Response) {
                                return $result;
                            }
                        }
                    }

                    // Execute route handler
                    return $this->executeHandler($route['handler'], $request, $params);
                }
            }
        }

        // Check if route exists with different method -> 405 Method Not Allowed
        if (!empty($matchedRoutesForPath)) {
            $allowed = implode(', ', array_unique($matchedRoutesForPath));
            if ($request->isJson()) {
                return Response::json(['error' => 'Method Not Allowed', 'allowed_methods' => $allowed], 405, ['Allow' => $allowed]);
            }
            return Response::html("<h1>405 Method Not Allowed</h1><p>Allowed methods: {$allowed}</p>", 405, ['Allow' => $allowed]);
        }

        // 404 Not Found
        throw new NotFoundException("Route not found for URI: {$request->uri()}");
    }

    /**
     * Normalize URI path to standard form.
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    /**
     * Compile parameterized route path into regex.
     */
    private function compilePattern(string $path): string
    {
        // Convert {param} to (?P<param>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#u';
    }

    /**
     * Execute route handler (Closure, [Class, method], or Class@method).
     */
    private function executeHandler(mixed $handler, Request $request, array $params = []): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request, $params);
            return $this->wrapResponse($result);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = is_string($class) ? new $class() : $class;
            $result = $instance->$method($request, $params);
            return $this->wrapResponse($result);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $instance = new $class();
            $result = $instance->$method($request, $params);
            return $this->wrapResponse($result);
        }

        return Response::html("<h1>500 Internal Server Error</h1><p>Invalid route handler configuration.</p>", 500);
    }

    /**
     * Ensure return value is wrapped in a Response object.
     */
    private function wrapResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }

        return Response::html((string)$result);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
