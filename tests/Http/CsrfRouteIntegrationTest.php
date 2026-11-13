<?php

declare(strict_types=1);

use GNSG\Auth\Session;
use GNSG\Http\Application;
use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Router;
use GNSG\Http\Middleware\MiddlewareInterface;
use GNSG\Http\Middleware\Pipeline;
use GNSG\Http\Middleware\CsrfMiddleware;
use GNSG\Security\CsrfProtection;

$root = dirname(__DIR__, 2);

require $root . '/app/Auth/Session.php';
require $root . '/app/Security/CsrfProtection.php';
require $root . '/app/Http/Request.php';
require $root . '/app/Http/Response.php';
require $root . '/app/Http/Router.php';
require $root . '/app/Http/Middleware/MiddlewareInterface.php';
require $root . '/app/Http/Middleware/Pipeline.php';
require $root . '/app/Http/Middleware/CsrfMiddleware.php';
require $root . '/app/Http/Application.php';

ob_start();

$testDirectory = sys_get_temp_dir()
    . '/gnsg-csrf-route-test-'
    . bin2hex(random_bytes(4));

if (!mkdir($testDirectory, 0700)) {
    throw new RuntimeException('Unable to create temporary test directory.');
}

ini_set('session.save_path', $testDirectory);
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cache_limiter', '');

$passed = 0;
$failed = 0;

$assert = static function (
    bool $condition,
    string $message
) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }

    $failed++;
    echo "FAIL: {$message}\n";
};

try {
    $app = new Application();

    $app->pipe(
        new CsrfMiddleware([
            'http://ganeshasistemgemilang.net',
        ])
    );

    // Register the actual production routes.
    $registerRoutes = require $root . '/routes/web.php';

    if (!is_callable($registerRoutes)) {
        throw new RuntimeException('Production route registration is invalid.');
    }

    $registerRoutes($app->router());

    // Test-only protected endpoint.
    $app->router()->post(
        '/__csrf-integration-test',
        static fn(Request $request, array $params): Response =>
            Response::json(['status' => 'accepted'])
    );

    // 1. Obtain token through the actual production endpoint.
    $response = $app->handle(
        new Request('GET', '/dashboard/csrf-token')
    );

    $assert(
        $response->statusCode() === 200,
        'Production CSRF token endpoint returns HTTP 200.'
    );

    $payload = json_decode($response->body(), true);

    $token = is_array($payload)
        ? ($payload['csrf_token'] ?? null)
        : null;

    $assert(
        is_string($token)
        && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1,
        'Production endpoint returns a valid 256-bit hexadecimal token.'
    );

    $assert(
        $response->header('Cache-Control')
            === 'no-store, no-cache, must-revalidate',
        'Token response includes the expected Cache-Control header.'
    );

    $assert(
        $response->header('Pragma') === 'no-cache',
        'Token response includes the Pragma no-cache header.'
    );

    // 2. Missing token must be rejected.
    $response = $app->handle(
        new Request(
            'POST',
            '/__csrf-integration-test',
            [],
            [
                'Origin' => 'http://ganeshasistemgemilang.net',
            ]
        )
    );

    $assert(
        $response->statusCode() === 403,
        'Protected POST without CSRF token is rejected with HTTP 403.'
    );

    // 3. Correct token and trusted origin must pass.
    if (is_string($token)) {
        $response = $app->handle(
            new Request(
                'POST',
                '/__csrf-integration-test',
                [],
                [
                    'X-CSRF-Token' => $token,
                    'Origin' => 'http://ganeshasistemgemilang.net',
                ]
            )
        );

        $assert(
            $response->statusCode() === 200,
            'Protected POST with valid token and trusted origin is accepted.'
        );

        $assert(
            $response->body() === '{"status":"accepted"}',
            'Accepted request reaches the protected route handler.'
        );
    } else {
        $assert(false, 'Valid-token POST test skipped because token is invalid.');
        $assert(false, 'Protected route handler test skipped because token is invalid.');
    }
} catch (Throwable $exception) {
    $failed++;

    echo 'FAIL: Unexpected exception: '
        . get_class($exception)
        . "\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        Session::destroy();
    }

    foreach (glob($testDirectory . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($testDirectory);
}

echo "\nCSRF route integration tests: {$passed} passed, {$failed} failed.\n";

if (ob_get_level() > 0) {
    ob_end_flush();
}

exit($failed === 0 ? 0 : 1);
