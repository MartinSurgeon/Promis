<?php

declare(strict_types=1);

namespace Promis\Core;

use Promis\Core\Database\Connection;
use Promis\Core\Exception\AppException;
use Promis\Core\Exception\AuthenticationException;
use Promis\Core\Exception\AuthorizationException;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Exception\ValidationException;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Http\Router;
use Promis\Core\Security\Session;
use Promis\Core\Support\Env;
use Promis\Core\Support\Logger;
use Promis\Core\Support\View;
use Throwable;

/**
 * Application Bootstrap & Front Controller Orchestrator.
 */
final class App
{
    private static bool $booted = false;
    private static Router $router;
    private static array $config = [];
    private static string $basePath;

    /**
     * Bootstrap the application environment and services.
     */
    public static function bootstrap(string $basePath): void
    {
        if (self::$booted) {
            return;
        }

        self::$basePath = rtrim($basePath, '/\\');

        // 1. Load .env file
        $envFile = self::$basePath . '/.env';
        if (file_exists($envFile)) {
            Env::load($envFile);
        }

        // 2. Load configurations
        $appConfig = require self::$basePath . '/config/app.php';
        $dbConfig = require self::$basePath . '/config/database.php';
        $sessionConfig = require self::$basePath . '/config/session.php';

        self::$config = [
            'app' => $appConfig,
            'database' => $dbConfig,
            'session' => $sessionConfig,
        ];

        // 3. Set timezone
        date_default_timezone_set($appConfig['timezone'] ?? 'Africa/Accra');

        // 4. Initialize Logger & Storage
        $logPath = self::$basePath . '/' . ($appConfig['log_path'] ?? 'storage/logs/app.log');
        Logger::init($logPath);

        $uploadDir = self::$basePath . '/' . ($appConfig['upload_path'] ?? 'storage/uploads');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // 5. Initialize View engine
        View::init(self::$basePath . '/views');

        // 6. Configure database factory
        Connection::setConfig($dbConfig);

        // 7. Initialize secure session (if in web environment)
        if (php_sapi_name() !== 'cli') {
            Session::start($sessionConfig);
        }

        // 8. Initialize router
        self::$router = new Router();
        self::registerRoutes();

        self::$booted = true;
    }

    /**
     * Register core foundation, authentication, dashboard, and requisition routes.
     */
    private static function registerRoutes(): void
    {
        // 1. Landing / Foundation Overview Route
        self::$router->get('/', function (Request $request): Response {
            $dbConnected = Connection::ping();
            $dbEngine = self::$config['database']['default'] ?? 'mariadb';

            return Response::html(View::render('home/index', [
                'title' => 'PROMIS - Foundation Overview',
                'dbConnected' => $dbConnected,
                'dbEngine' => $dbEngine,
                'phpVersion' => PHP_VERSION,
                'appEnv' => self::$config['app']['env'] ?? 'local',
            ], 'main'));
        });

        // 2. Health Endpoint (Strictly sanitized, zero credential disclosure)
        self::$router->get('/health', function (Request $request): Response {
            $dbConnected = Connection::ping();

            if ($dbConnected) {
                return Response::json([
                    'status' => 'ok',
                    'app' => 'booted',
                    'database' => 'connected',
                ], 200);
            }

            // Database failure returns HTTP 503 Service Unavailable with sanitized response
            return Response::json([
                'status' => 'error',
                'app' => 'booted',
                'database' => 'unavailable',
                'message' => 'Service temporarily unavailable. Database connection is not established.',
            ], 503);
        });

        // 3. Authentication & Account Lifecycle Routes
        self::$router->get('/login', [\Promis\Src\Presentation\Controller\AuthController::class, 'showLoginForm']);
        self::$router->post('/login', [\Promis\Src\Presentation\Controller\AuthController::class, 'login']);
        self::$router->get('/activate', [\Promis\Src\Presentation\Controller\AuthController::class, 'showActivateForm']);
        self::$router->post('/activate', [\Promis\Src\Presentation\Controller\AuthController::class, 'activate']);
        self::$router->get('/forgot-password', [\Promis\Src\Presentation\Controller\AuthController::class, 'showForgotPasswordForm']);
        self::$router->post('/forgot-password', [\Promis\Src\Presentation\Controller\AuthController::class, 'forgotPassword']);
        self::$router->get('/reset-password', [\Promis\Src\Presentation\Controller\AuthController::class, 'showResetPasswordForm']);
        self::$router->post('/reset-password', [\Promis\Src\Presentation\Controller\AuthController::class, 'resetPassword']);
        self::$router->post('/logout', [\Promis\Src\Presentation\Controller\AuthController::class, 'logout']);
        self::$router->get('/logout', [\Promis\Src\Presentation\Controller\AuthController::class, 'logout']);

        // 4. Institutional Dashboard Route
        self::$router->get('/dashboard', [\Promis\Src\Presentation\Controller\DashboardController::class, 'index']);

        // 5. Requisition Explorer, Details, Creation & Workflow Actions
        self::$router->get('/requisitions', [\Promis\Src\Presentation\Controller\RequisitionViewController::class, 'index']);
        self::$router->get('/requisitions/create', [\Promis\Src\Presentation\Controller\RequisitionViewController::class, 'create']);
        self::$router->post('/requisitions', [\Promis\Src\Presentation\Controller\RequisitionViewController::class, 'store']);
        self::$router->get('/requisitions/{id}', [\Promis\Src\Presentation\Controller\RequisitionViewController::class, 'show']);
        self::$router->post('/requisitions/{id}/action', [\Promis\Src\Presentation\Controller\RequisitionViewController::class, 'handleAction']);

        // 6. Annual Procurement Plan Formulation, Inquiries, Workflow & Printable Output
        self::$router->get('/procurement-plans', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'index']);
        self::$router->get('/procurement-plans/create', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'create']);
        self::$router->post('/procurement-plans', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'store']);
        self::$router->get('/procurement-plans/{id}', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'show']);
        self::$router->get('/procurement-plans/{id}/edit', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'edit']);
        self::$router->post('/procurement-plans/{id}/edit', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'update']);
        self::$router->post('/procurement-plans/{id}/action', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'handleAction']);
        self::$router->get('/procurement-plans/{id}/print', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'print']);
        self::$router->get('/procurement-plans/{id}/versions', [\Promis\Src\Presentation\Controller\ProcurementPlanViewController::class, 'versions']);

        // 7. Administrative User & Entity-Scoped Role Management
        self::$router->get('/admin/users', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'index']);
        self::$router->post('/admin/users', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'store']);
        self::$router->post('/admin/users/{id}/edit', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'update']);
        self::$router->post('/admin/users/{id}/status', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'toggleStatus']);
        self::$router->post('/admin/users/{id}/roles', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'assignRole']);
        self::$router->post('/admin/users/{id}/roles/{assignment_id}/delete', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'revokeRole']);
        self::$router->post('/admin/users/{id}/roles/{assignment_id}/primary', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'setPrimaryRole']);
        self::$router->get('/admin/users/{id}/json', [\Promis\Src\Presentation\Controller\AdminUserViewController::class, 'getUserJson']);

        // 8. Hierarchical Planning Entity Management
        self::$router->get('/admin/entities', [\Promis\Src\Presentation\Controller\AdminEntityViewController::class, 'index']);
        self::$router->post('/admin/entities', [\Promis\Src\Presentation\Controller\AdminEntityViewController::class, 'store']);
        self::$router->post('/admin/entities/{id}/edit', [\Promis\Src\Presentation\Controller\AdminEntityViewController::class, 'update']);
        self::$router->post('/admin/entities/{id}/status', [\Promis\Src\Presentation\Controller\AdminEntityViewController::class, 'toggleStatus']);
        self::$router->post('/admin/entities/{id}/toggle-status', [\Promis\Src\Presentation\Controller\AdminEntityViewController::class, 'toggleStatus']);
        self::$router->get('/admin/entities/{id}/json', [\Promis\Src\Presentation\Controller\AdminEntityViewController::class, 'getEntityJson']);
        self::$router->get('/admin/entities/tree', [\Promis\Src\Presentation\Controller\AdminEntityViewController::class, 'getEntityTreeJson']);
    }

    /**
     * Run the application and emit the HTTP response.
     */
    public static function run(): void
    {
        try {
            $request = Request::capture();
            $response = self::$router->dispatch($request);
            $response->send();
        } catch (Throwable $e) {
            $response = self::handleException($e, $request ?? Request::capture());
            $response->send();
        }
    }

    /**
     * Centralized Exception Handler.
     * Logs technical stack traces to storage/logs and renders sanitized user-friendly screens.
     */
    public static function handleException(Throwable $e, Request $request): Response
    {
        $statusCode = match (true) {
            $e instanceof NotFoundException => 404,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ValidationException => 422,
            $e instanceof AppException => $e->getStatusCode(),
            default => 500,
        };

        // Log error with masked context
        Logger::error("Uncaught exception: " . $e->getMessage(), [
            'type' => get_class($e),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'uri' => $request->uri(),
            'method' => $request->method(),
        ]);

        if ($request->isJson()) {
            $payload = [
                'error' => true,
                'status' => $statusCode,
                'message' => $statusCode === 500 && !(self::$config['app']['debug'] ?? false)
                    ? 'An unexpected error occurred. Please contact the system administrator.'
                    : $e->getMessage(),
            ];

            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->getErrors();
            }

            return Response::json($payload, $statusCode);
        }

        // Render appropriate HTML error view
        $viewName = match ($statusCode) {
            401 => 'errors/401',
            403 => 'errors/403',
            404 => 'errors/404',
            default => 'errors/500',
        };

        try {
            $html = View::render($viewName, [
                'title' => "Error {$statusCode}",
                'statusCode' => $statusCode,
                'message' => $statusCode === 500 && !(self::$config['app']['debug'] ?? false)
                    ? 'A system error occurred. Details have been logged securely.'
                    : $e->getMessage(),
            ], 'guest');
            return Response::html($html, $statusCode);
        } catch (Throwable $viewError) {
            // Fallback emergency HTML
            return Response::html("<h1>Error {$statusCode}</h1><p>An error occurred.</p>", $statusCode);
        }
    }

    public static function getRouter(): Router
    {
        return self::$router;
    }

    public static function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }
        return self::$config[$key] ?? null;
    }
}
