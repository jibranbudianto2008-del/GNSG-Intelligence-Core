<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/app/Auth/PasswordHasher.php';
require $root . '/app/Auth/Session.php';
require $root . '/app/Security/CsrfProtection.php';
require $root . '/app/Auth/AuthManager.php';
require $root . '/app/Http/Request.php';
require $root . '/app/Http/Response.php';
require $root . '/app/Http/Router.php';
require $root . '/app/Http/Middleware/MiddlewareInterface.php';
require $root . '/app/Http/Middleware/Pipeline.php';
require $root . '/app/Http/Middleware/ErrorHandlerMiddleware.php';
require $root . '/app/Http/Middleware/CsrfMiddleware.php';
require $root . '/app/Http/Application.php';
require $root . '/app/Http/Controller/AuthController.php';

use GNSG\Auth\AuthManager;
use GNSG\Auth\PasswordHasher;
use GNSG\Auth\Session;
use GNSG\Http\Application;
use GNSG\Http\Request;
use GNSG\Http\Middleware\ErrorHandlerMiddleware;
use GNSG\Http\Middleware\CsrfMiddleware;
use GNSG\Security\CsrfProtection;

$pdo = null;
$tableCreated = false;
$sessionDir = null;
$passed = 0;
$failed = 0;
$testError = null;
$cleanupError = null;

$assert = static function (
    bool $condition,
    string $description
) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$description}\n";
    } else {
        $failed++;
        echo "FAIL: {$description}\n";
    }
};

ob_start();

try {
    // CONNECT ONLY TO THE DEDICATED TEST DATABASE.

    $envFile = '/etc/gnsg/gnsg-core-test.env';

    if (!is_readable($envFile)) {
        throw new RuntimeException('Test credentials are unavailable.');
    }

    $config = parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if (
        !is_array($config)
        || !isset($config['TEST_DB_PASSWORD'])
        || !is_string($config['TEST_DB_PASSWORD'])
        || !preg_match('/\A[a-f0-9]{64}\z/', $config['TEST_DB_PASSWORD'])
    ) {
        throw new RuntimeException('Invalid test database credentials.');
    }

    $pdo = new PDO(
        'mysql:host=localhost;dbname=gnsg_core_test;charset=utf8mb4',
        'gnsg_core_test',
        $config['TEST_DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ]
    );

    unset($config);

    $assert(
        $pdo->query('SELECT DATABASE()')->fetchColumn() === 'gnsg_core_test',
        'Connected to dedicated test database'
    );

    $assert(
        $pdo->query('SELECT CURRENT_USER()')->fetchColumn()
            === 'gnsg_core_test@localhost',
        'Connected using dedicated test account'
    );

    if ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) !== []) {
        throw new RuntimeException(
            'Test database is not empty. No tables were modified.'
        );
    }

    $pdo->exec(
        "CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL UNIQUE,
            email VARCHAR(255) UNIQUE,
            password_hash VARCHAR(255) NULL,
            display_name VARCHAR(120) NOT NULL,
            status ENUM('active', 'disabled', 'locked')
                NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_general_ci"
    );

    $tableCreated = true;

    $password = 'GNSG-HTTP-Test-Password-2026';
    $passwordHash = PasswordHasher::hash($password);

    $insert = $pdo->prepare(
        'INSERT INTO users (
            username, email, password_hash, display_name, status
        ) VALUES (
            :username, :email, :password_hash, :display_name, :status
        )'
    );

    $insert->execute([
        'username' => 'http_active',
        'email' => 'http-active@example.test',
        'password_hash' => $passwordHash,
        'display_name' => 'HTTP Test User',
        'status' => 'active',
    ]);

    // ISOLATED SESSION STORAGE.

    $sessionDir = sys_get_temp_dir()
        . '/gnsg-auth-route-test-' . bin2hex(random_bytes(4));

    if (!mkdir($sessionDir, 0700)) {
        throw new RuntimeException('Unable to create test session directory.');
    }

    foreach ([
        'session.save_path' => $sessionDir,
        'session.use_cookies' => '0',
        'session.use_trans_sid' => '0',
        'session.cache_limiter' => '',
    ] as $setting => $value) {
        if (ini_set($setting, $value) === false) {
            throw new RuntimeException("Unable to configure {$setting}.");
        }
    }

    if (session_status() !== PHP_SESSION_NONE) {
        throw new RuntimeException('A session is already active.');
    }

    // ACTUAL PRODUCTION ROUTES AND GLOBAL CSRF MIDDLEWARE.

    $manager = new AuthManager($pdo);
    $app = new Application();

    $app->pipe(new ErrorHandlerMiddleware());

    $app->pipe(
        new CsrfMiddleware([
            'http://ganeshasistemgemilang.net',
        ])
    );

    $registerRoutes = require $root . '/routes/web.php';
    $registerRoutes($app->router(), $manager);

    $origin = 'http://ganeshasistemgemilang.net';

    // OBTAIN CSRF TOKEN THROUGH THE PRODUCTION ENDPOINT.

    $response = $app->handle(
        new Request('GET', '/dashboard/csrf-token')
    );

    $payload = json_decode($response->body(), true);
    $oldToken = is_array($payload) ? ($payload['csrf_token'] ?? null) : null;

    $assert($response->statusCode() === 200, 'CSRF endpoint returns HTTP 200');

    $assert(
        is_string($oldToken)
        && preg_match('/\A[a-f0-9]{64}\z/D', $oldToken) === 1,
        'CSRF endpoint returns a valid token'
    );

    $headers = static function (
        ?string $token,
        string $contentType = 'application/json'
    ) use ($origin): array {
        $result = [
            'Origin' => $origin,
            'Content-Type' => $contentType,
        ];

        if ($token !== null) {
            $result['X-CSRF-Token'] = $token;
        }

        return $result;
    };

    // HTTP INPUT VALIDATION.

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($oldToken, 'text/plain'),
        '{"username":"http_active","password":"GNSG-HTTP-Test-Password-2026"}'
    ));

    $assert($response->statusCode() === 415, 'Wrong Content-Type returns HTTP 415');

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($oldToken),
        '{"username":'
    ));

    $assert($response->statusCode() === 400, 'Malformed JSON returns HTTP 400');

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($oldToken),
        '["http_active","password"]'
    ));

    $assert($response->statusCode() === 400, 'JSON array payload is rejected');

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($oldToken),
        str_repeat('x', 8193)
    ));

    $assert($response->statusCode() === 413, 'Oversized login body returns HTTP 413');

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($oldToken),
        '{"username":123,"password":"password"}'
    ));

    $assert($response->statusCode() === 422, 'Incorrect input types are rejected');

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers(null),
        '{"username":"http_active","password":"GNSG-HTTP-Test-Password-2026"}'
    ));

    $assert($response->statusCode() === 403, 'Missing CSRF token is rejected');

    // FAILED LOGIN MUST REMOVE A PREVIOUS IDENTITY.

    Session::start();
    Session::set('auth_user', [
        'id' => 999,
        'username' => 'stale_identity',
    ]);

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($oldToken),
        '{"username":"http_active","password":"Wrong-Password"}'
    ));

    $assert($response->statusCode() === 401, 'Invalid credentials return HTTP 401');

    $assert(
        $response->body() === '{"error":"Invalid username or password."}',
        'Authentication failure uses a generic response'
    );

    $assert(
        $manager->currentUser() === null,
        'Failed login removes the previous authenticated identity'
    );

    $assert(
        CsrfProtection::validate($oldToken),
        'Failed login does not rotate the existing CSRF token'
    );

    // SUCCESSFUL LOGIN: SESSION REGENERATION AND CSRF ROTATION.

    $sessionIdBeforeLogin = session_id();

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($oldToken),
        '{"username":"http_active","password":"GNSG-HTTP-Test-Password-2026"}'
    ));

    $successPayload = json_decode($response->body(), true);

    $assert($response->statusCode() === 200, 'Valid login returns HTTP 200');

    $assert(
        is_array($successPayload)
        && ($successPayload['authenticated'] ?? false) === true,
        'Successful response confirms authentication'
    );

    $assert(
        session_id() !== $sessionIdBeforeLogin,
        'Successful login regenerates the session ID'
    );

    $newToken = is_array($successPayload)
        ? ($successPayload['csrf_token'] ?? null)
        : null;

    $assert(
        is_string($newToken)
        && preg_match('/\A[a-f0-9]{64}\z/D', $newToken) === 1,
        'Successful response provides a valid new CSRF token'
    );

    $assert(
        is_string($newToken) && $newToken !== $oldToken,
        'Successful login rotates the CSRF token'
    );

    $assert(
        !CsrfProtection::validate($oldToken),
        'Old CSRF token is invalid after successful login'
    );

    $assert(
        is_string($newToken) && CsrfProtection::validate($newToken),
        'New CSRF token is valid after successful login'
    );

    $assert(
        !str_contains($response->body(), 'password_hash')
        && !str_contains($response->body(), $passwordHash),
        'Login response does not disclose password hash'
    );

    $assert(
        $response->header('Cache-Control')
            === 'no-store, no-cache, must-revalidate',
        'Successful login response disables caching'
    );


    // LOGOUT METHOD AND CSRF ENFORCEMENT.

    $response = $app->handle(new Request('GET', '/auth/logout'));
    $assert($response->statusCode() !== 200, 'GET logout cannot execute logout');
    $assert($manager->currentUser() !== null, 'GET logout preserves authenticated identity');

    $response = $app->handle(new Request(
        'POST', '/auth/logout', [],
        ['Origin' => $origin, 'Content-Type' => 'application/json'],
        '{}'
    ));
    $assert($response->statusCode() === 403, 'Logout without CSRF token is rejected');
    $assert($manager->currentUser() !== null, 'Missing CSRF preserves authenticated identity');

    $response = $app->handle(new Request(
        'POST', '/auth/logout', [],
        $headers(str_repeat('0', 64)),
        '{}'
    ));
    $assert($response->statusCode() === 403, 'Logout with invalid CSRF token is rejected');
    $assert($manager->currentUser() !== null, 'Invalid CSRF preserves authenticated identity');

    // AUTHENTICATED LOGOUT.

    $response = $app->handle(new Request(
        'POST', '/auth/logout', [], $headers($newToken), '{}'
    ));
    $logoutPayload = json_decode($response->body(), true);

    $assert($response->statusCode() === 200, 'Valid logout returns HTTP 200');
    $assert(
        is_array($logoutPayload)
        && ($logoutPayload['success'] ?? false) === true,
        'Logout response confirms success'
    );
    $assert(
        $response->header('Cache-Control') === 'no-store',
        'Logout success response disables caching'
    );
    $assert(session_status() === PHP_SESSION_NONE, 'Logout destroys the active PHP session');
    $assert($manager->currentUser() === null, 'Authenticated identity is unavailable after logout');

    // ANONYMOUS LOGOUT AND IDEMPOTENCY.

    $csrfResponse = $app->handle(new Request('GET', '/dashboard/csrf-token'));
    $anonymousPayload = json_decode($csrfResponse->body(), true);
    $anonymousToken = is_array($anonymousPayload)
        ? ($anonymousPayload['csrf_token'] ?? null)
        : null;

    $assert(
        is_string($anonymousToken) && CsrfProtection::validate($anonymousToken),
        'Anonymous session can obtain a valid CSRF token'
    );

    $response = $app->handle(new Request(
        'POST', '/auth/logout', [], $headers($anonymousToken), '{}'
    ));
    $assert($response->statusCode() === 200, 'Logout without authenticated identity succeeds');
    $assert(session_status() === PHP_SESSION_NONE, 'Anonymous logout destroys its session');

    // LOGIN REMAINS FUNCTIONAL AFTER LOGOUT.

    $csrfResponse = $app->handle(new Request('GET', '/dashboard/csrf-token'));
    $freshPayload = json_decode($csrfResponse->body(), true);
    $freshToken = is_array($freshPayload)
        ? ($freshPayload['csrf_token'] ?? null)
        : null;

    $response = $app->handle(new Request(
        'POST', '/auth/login', [], $headers($freshToken),
        '{"username":"http_active","password":"GNSG-HTTP-Test-Password-2026"}'
    ));
    $freshLoginPayload = json_decode($response->body(), true);
    $newToken = is_array($freshLoginPayload)
        ? ($freshLoginPayload['csrf_token'] ?? null)
        : null;

    $assert($response->statusCode() === 200, 'Login still works after logout');
    $assert(
        is_string($newToken) && CsrfProtection::validate($newToken),
        'Login after logout establishes a fresh CSRF token'
    );

    // FAILED LOGIN AFTER A PREVIOUS SUCCESSFUL AUTHENTICATION.

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($newToken),
        '{"username":"http_active","password":"Wrong-Password-After-Success"}'
    ));

    $assert(
        $response->statusCode() === 401,
        'Failed re-login after successful authentication returns HTTP 401'
    );

    $assert(
        $manager->currentUser() === null,
        'Failed re-login removes the previously authenticated identity'
    );

    $assert(
        CsrfProtection::validate($newToken),
        'Failed re-login preserves the current CSRF token'
    );

    // DATABASE FAILURE MUST BE HANDLED BY THE GLOBAL ERROR HANDLER.

    $pdo->exec('DROP TABLE users');

    $response = $app->handle(new Request(
        'POST',
        '/auth/login',
        [],
        $headers($newToken),
        '{"username":"http_active","password":"GNSG-HTTP-Test-Password-2026"}'
    ));

    $errorPayload = json_decode($response->body(), true);

    $assert(
        $response->statusCode() === 500,
        'Database exception returns HTTP 500, not authentication failure'
    );

    $assert(
        is_array($errorPayload)
        && ($errorPayload['error']['code'] ?? null) === 'internal_server_error',
        'Database exception returns the generic internal error response'
    );

    $assert(
        !str_contains($response->body(), 'PDOException')
        && !str_contains($response->body(), 'users')
        && !str_contains($response->body(), 'SQLSTATE'),
        'Database exception details are not disclosed to the client'
    );

} catch (Throwable $exception) {
    $testError = get_class($exception) . ': ' . $exception->getMessage();

} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        try {
            Session::destroy();
        } catch (Throwable $exception) {
            $cleanupError = 'Unable to destroy test session.';
        }
    }

    if ($sessionDir !== null && is_dir($sessionDir)) {
        foreach (glob($sessionDir . '/*') ?: [] as $file) {
            if (is_file($file) || is_link($file)) {
                if (!unlink($file)) {
                    $cleanupError = 'Unable to remove test session file.';
                }
            }
        }

        if (is_dir($sessionDir) && !rmdir($sessionDir)) {
            $cleanupError = 'Unable to remove test session directory.';
        }
    }

    if ($tableCreated && $pdo instanceof PDO) {
        try {
            $pdo->exec('DROP TABLE IF EXISTS users');
        } catch (Throwable $exception) {
            $cleanupError = 'Unable to remove test users table.';
        }
    }

    $pdo = null;
}

if ($testError !== null) {
    fwrite(STDERR, "TEST ERROR: {$testError}\n");
}

if ($cleanupError !== null) {
    fwrite(STDERR, "CLEANUP ERROR: {$cleanupError}\n");
}

echo "\nAuth route integration tests: {$passed} passed, {$failed} failed.\n";

if ($testError !== null || $cleanupError !== null || $failed > 0) {
    exit(1);
}

echo "ALL AUTH ROUTE INTEGRATION TESTS PASSED.\n";

if (ob_get_level() > 0) {
    ob_end_flush();
}
