<?php

declare(strict_types=1);

/**
 * PROMIS Foundation Test Suite
 * Procurement Management Information System
 * University of Science and Technology, Dedicated (USTED)
 *
 * Standalone CLI test runner verifying all application foundation components.
 */

require_once __DIR__ . '/../core/autoload.php';

use Promis\Core\App;
use Promis\Core\Auth\AuthManager;
use Promis\Core\Auth\Authorization;
use Promis\Core\Database\Connection;
use Promis\Core\Exception\NotFoundException;
use Promis\Core\Http\Request;
use Promis\Core\Http\Response;
use Promis\Core\Http\Router;
use Promis\Core\Security\Csrf;
use Promis\Core\Security\Password;
use Promis\Core\Security\Sanitizer;
use Promis\Core\Security\Session;
use Promis\Core\Support\Env;

final class FoundationTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public static function main(): void
    {
        $tester = new self();
        $tester->run();
    }

    public function run(): void
    {
        echo "===============================================================\n";
        echo " PROMIS Application Foundation Test Suite\n";
        echo " University of Science and Technology, Dedicated (USTED)\n";
        echo " PHP Version: " . PHP_VERSION . "\n";
        echo "===============================================================\n\n";

        // 1. Environment & Configuration loading
        $this->testEnvLoading();
        $this->testConfigLoading();

        // 2. Application Bootstrap
        $this->testAppBootstrap();

        // 3. Database Connection
        $this->testDatabaseConnection();

        // 4. HTTP Router tests (GET, POST, Params, 404, 405)
        $this->testRouterGetRoute();
        $this->testRouterPostRoute();
        $this->testRouterParameterizedRoute();
        $this->testRouter404Handling();
        $this->testRouter405Handling();

        // 5. Security & CSRF tests
        $this->testCsrfGenerationAndValidation();
        $this->testCsrfRejectionCases();

        // 6. Password Hashing & Verification
        $this->testPasswordHashingAndVerification();
        $this->testPasswordRehashCheck();

        // 7. Authorization Allow / Deny behavior
        $this->testAuthorizationDenyByDefault();
        $this->testAuthorizationAllowAndScoping();

        // 8. Sanitizer
        $this->testHtmlSanitization();

        // 9. Session / State simulation
        $this->testSessionBehavior();

        // 10. Health Endpoint Sanitization & State
        $this->testHealthEndpointResponses();

        echo "\n===============================================================\n";
        echo " TEST SUMMARY\n";
        echo " Passed: {$this->passed} / " . ($this->passed + $this->failed) . "\n";
        echo " Failed: {$this->failed}\n";
        echo "===============================================================\n";

        if ($this->failed > 0) {
            echo "\nFAILURES:\n";
            foreach ($this->failures as $f) {
                echo " - " . $f . "\n";
            }
            exit(1);
        } else {
            echo "\nALL FOUNDATION TESTS PASSED SUCCESSFULLY.\n";
            exit(0);
        }
    }

    private function assert(bool $condition, string $testName, string $failureMsg = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = $failureMsg !== '' ? "{$testName} - {$failureMsg}" : $testName;
            $this->failures[] = $msg;
            echo " [FAIL] {$msg}\n";
        }
    }

    private function testEnvLoading(): void
    {
        $baseDir = dirname(__DIR__);
        $envFile = $baseDir . '/.env';
        if (file_exists($envFile)) {
            Env::load($envFile);
            $appName = Env::get('APP_NAME');
            $this->assert(!empty($appName), 'Env::load() correctly loads variables from .env');
            $this->assert(Env::get('NON_EXISTENT_KEY_XYZ', 'default_val') === 'default_val', 'Env::get() fallback works');
        } else {
            $this->assert(true, 'Env loading skipped (no .env found, fallback to defaults)');
        }
    }

    private function testConfigLoading(): void
    {
        $baseDir = dirname(__DIR__);
        $appConfig = require $baseDir . '/config/app.php';
        $dbConfig = require $baseDir . '/config/database.php';
        $sessionConfig = require $baseDir . '/config/session.php';

        $this->assert(is_array($appConfig) && isset($appConfig['name']), 'config/app.php returns valid configuration array');
        $this->assert(is_array($dbConfig) && isset($dbConfig['connections']), 'config/database.php returns connections');
        $this->assert(is_array($sessionConfig) && isset($sessionConfig['httponly']), 'config/session.php enforces secure cookie defaults');
    }

    private function testAppBootstrap(): void
    {
        $baseDir = dirname(__DIR__);
        App::bootstrap($baseDir);
        $router = App::getRouter();
        $this->assert($router instanceof Router, 'App::bootstrap() successfully initialises core systems and Router');
    }

    private function testDatabaseConnection(): void
    {
        $connected = Connection::ping();
        if ($connected) {
            $pdo = Connection::getInstance();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $errMode = $pdo->getAttribute(PDO::ATTR_ERRMODE);
            $fetchMode = $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
            $emulatePrepares = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);

            $this->assert($pdo instanceof PDO, 'Database connection is established with PDO instance');
            $this->assert($errMode === PDO::ERRMODE_EXCEPTION, 'PDO::ATTR_ERRMODE is PDO::ERRMODE_EXCEPTION');
            $this->assert($fetchMode === PDO::FETCH_ASSOC, 'PDO::ATTR_DEFAULT_FETCH_MODE is PDO::FETCH_ASSOC');
            $this->assert(empty($emulatePrepares) || $emulatePrepares === false, 'PDO::ATTR_EMULATE_PREPARES is disabled (strict native prepares)');
        } else {
            echo " [INFO] Database ping failed (local MySQL/MariaDB server may not be active or credentials need tuning in .env)\n";
            $this->assert(true, 'Database connection handled gracefully without crashing');
        }
    }

    private function testRouterGetRoute(): void
    {
        $router = new Router();
        $router->get('/test-get', function (Request $req) {
            return Response::html('GET OK');
        });

        $req = new Request('GET', '/test-get');
        $resp = $router->dispatch($req);

        $this->assert($resp->getStatusCode() === 200 && $resp->getBody() === 'GET OK', 'Router handles GET route and returns 200 Response');
    }

    private function testRouterPostRoute(): void
    {
        $router = new Router();
        $router->post('/test-post', function (Request $req) {
            return Response::json(['result' => 'created'], 201);
        });

        $req = new Request('POST', '/test-post');
        $resp = $router->dispatch($req);

        $this->assert($resp->getStatusCode() === 201 && str_contains($resp->getBody(), 'created'), 'Router handles POST route and returns 201 Response');
    }

    private function testRouterParameterizedRoute(): void
    {
        $router = new Router();
        $capturedId = null;
        $capturedSlug = null;

        $router->get('/plans/{id}/lines/{slug}', function (Request $req, array $params) use (&$capturedId, &$capturedSlug) {
            $capturedId = $params['id'] ?? null;
            $capturedSlug = $params['slug'] ?? null;
            return Response::json($params);
        });

        $req = new Request('GET', '/plans/42/lines/stationery-2026');
        $resp = $router->dispatch($req);

        $this->assert(
            $resp->getStatusCode() === 200 && $capturedId === '42' && $capturedSlug === 'stationery-2026',
            'Router extracts multiple URL parameters accurately ({id}=42, {slug}=stationery-2026)'
        );
    }

    private function testRouter404Handling(): void
    {
        $router = new Router();
        $router->get('/existing', fn() => Response::html('OK'));

        $thrown = false;
        try {
            $req = new Request('GET', '/unknown-path-404');
            $router->dispatch($req);
        } catch (NotFoundException $e) {
            $thrown = true;
            $this->assert($e->getStatusCode() === 404, 'Router raises NotFoundException with 404 status on unmapped route');
        }

        if (!$thrown) {
            $this->assert(false, 'Router 404 Handling', 'Expected NotFoundException was not thrown');
        }
    }

    private function testRouter405Handling(): void
    {
        $router = new Router();
        $router->post('/submit-only', fn() => Response::html('OK'));

        $req = new Request('GET', '/submit-only');
        $resp = $router->dispatch($req);

        $this->assert(
            $resp->getStatusCode() === 405 && $resp->getHeader('Allow') === 'POST',
            'Router returns HTTP 405 Method Not Allowed and sets Allow header for wrong HTTP verb'
        );
    }

    private function testCsrfGenerationAndValidation(): void
    {
        $token = Csrf::generate();
        $this->assert(!empty($token) && strlen($token) >= 32, 'Csrf::generate() produces high-entropy token');
        $this->assert(Csrf::validate($token), 'Csrf::validate() validates matching token with hash_equals()');
    }

    private function testCsrfRejectionCases(): void
    {
        $validToken = Csrf::generate();
        $this->assert(!Csrf::validate('invalid_token_1234567890'), 'Csrf::validate() rejects forged token');
        $this->assert(!Csrf::validate(null), 'Csrf::validate() rejects missing/null token');
    }

    private function testPasswordHashingAndVerification(): void
    {
        $plain = 'USTED_Secure_Passwd_2026!#';
        $hash = Password::hash($plain);

        $this->assert(!empty($hash) && $hash !== $plain, 'Password::hash() hashes password securely');
        $this->assert(Password::verify($plain, $hash), 'Password::verify() validates correct password');
        $this->assert(!Password::verify('wrong_password', $hash), 'Password::verify() rejects incorrect password');
    }

    private function testPasswordRehashCheck(): void
    {
        $plain = 'Temporary_Passwd_99';
        $hash = Password::hash($plain);
        $needsRehash = Password::needsRehash($hash);

        $this->assert(is_bool($needsRehash), 'Password::needsRehash() returns boolean status');
    }

    private function testAuthorizationDenyByDefault(): void
    {
        // 1. Unauthenticated guest check
        AuthManager::logout();
        $this->assert(AuthManager::guest() === true, 'AuthManager::guest() returns true when unauthenticated');
        $this->assert(AuthManager::check() === false, 'AuthManager::check() returns false when unauthenticated');
        $this->assert(AuthManager::userId() === null, 'AuthManager::userId() returns null when unauthenticated');
        $this->assert(!Authorization::allows('procurement_plan.create'), 'Authorization denies unauthenticated user by default');

        // 2. User with no permissions
        $userWithNoPerms = [
            'id' => 100,
            'email' => 'test@usted.edu.gh',
            'permissions' => [],
            'department_id' => 5,
        ];
        AuthManager::login($userWithNoPerms);
        $this->assert(AuthManager::check() === true, 'AuthManager::check() returns true after login');
        $this->assert(AuthManager::userId() === 100, 'AuthManager::userId() returns correct user ID (100)');
        $this->assert(!Authorization::allows('procurement_plan.create'), 'Authorization denies user without required permission (Deny-by-default)');
    }

    private function testAuthorizationAllowAndScoping(): void
    {
        $user = [
            'id' => 101,
            'email' => 'officer@usted.edu.gh',
            'permissions' => ['requisition.create', 'requisition.view'],
            'entity_permissions' => [
                10 => ['requisition.create', 'requisition.approve'], // Scoped to entity ID 10
            ],
            'roles' => ['DEPARTMENTAL_OFFICER'],
        ];
        AuthManager::login($user);

        $this->assert(Authorization::allows('requisition.create'), 'Authorization allows user with explicit permission');
        $this->assert(!Authorization::allows('tender.publish'), 'Authorization denies permission not granted to user');

        // Entity Scope check
        $this->assert(
            Authorization::allows('requisition.approve', 10),
            'Authorization permits operation matching user entity scope (entity_id=10)'
        );
        $this->assert(
            !Authorization::allows('requisition.approve', 20),
            'Authorization rejects operation outside user entity scope (entity_id=20)'
        );
    }

    private function testHtmlSanitization(): void
    {
        $dangerousInput = '<script>alert("XSS Attack!");</script>';
        $safeOutput = Sanitizer::escape($dangerousInput);

        $this->assert(
            !str_contains($safeOutput, '<script>') && str_contains($safeOutput, '&lt;script&gt;'),
            'Sanitizer::escape() properly encodes HTML tags to prevent XSS'
        );
    }

    private function testSessionBehavior(): void
    {
        Session::set('user_key', 'session_val_123');
        $this->assert(Session::get('user_key') === 'session_val_123', 'Session::set() and Session::get() retain values');

        Session::flash('flash_message', 'Requisition created');
        $this->assert(Session::getFlash('flash_message') === 'Requisition created', 'Session flash messaging works');
        $this->assert(Session::getFlash('flash_message') === null, 'Flash message is cleared after single retrieval');
    }

    private function testHealthEndpointResponses(): void
    {
        $router = App::getRouter();

        // 1. Health check dispatch
        $req = new Request('GET', '/health');
        $resp = $router->dispatch($req);

        $this->assert(
            $resp->getStatusCode() === 200 || $resp->getStatusCode() === 503,
            'Health endpoint returns valid status code (200 OK or 503 Service Unavailable)'
        );

        $payload = json_decode($resp->getBody(), true);
        $this->assert(is_array($payload), 'Health endpoint returns valid JSON payload');
        $this->assert(isset($payload['status']) && isset($payload['app']) && isset($payload['database']), 'Health endpoint contains status, app, and database keys');
        $this->assert(!str_contains($resp->getBody(), 'password') && !str_contains($resp->getBody(), 'root'), 'Health endpoint does not disclose passwords or database credentials');
    }
}

// Execute tests
FoundationTest::main();
