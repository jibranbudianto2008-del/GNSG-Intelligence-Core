<?php

declare(strict_types=1);

use GNSG\Auth\Session;
use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Middleware\CsrfMiddleware;
use GNSG\Security\CsrfProtection;

require dirname(__DIR__, 2) . '/app/Auth/Session.php';
require dirname(__DIR__, 2) . '/app/Security/CsrfProtection.php';
require dirname(__DIR__, 2) . '/app/Http/Request.php';
require dirname(__DIR__, 2) . '/app/Http/Response.php';
require dirname(__DIR__, 2) . '/app/Http/Middleware/MiddlewareInterface.php';
require dirname(__DIR__, 2) . '/app/Http/Middleware/CsrfMiddleware.php';

ob_start();
$testDirectory = sys_get_temp_dir()
    . '/gnsg-csrf-middleware-test-'
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

$assert = static function (bool $condition, string $message) use (
    &$passed,
    &$failed
): void {
    if (!$condition) {
        $failed++;
        echo "FAIL: {$message}\n";
        return;
    }

    $passed++;
    echo "PASS: {$message}\n";
};

$middleware = new CsrfMiddleware([
    'http://ganeshasistemgemilang.net',
]);

$nextCalls = 0;

$next = static function (Request $request) use (&$nextCalls): Response {
    $nextCalls++;

    return Response::json(['status' => 'passed']);
};

try {
    // Safe methods must pass without starting a session.
    foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
        $response = $middleware->process(
            new Request($method, '/dashboard/health'),
            $next
        );

        $assert(
            $response->statusCode() === 200,
            "{$method} passes without CSRF validation."
        );

        $assert(
            session_status() !== PHP_SESSION_ACTIVE,
            "{$method} does not start a session."
        );
    }

    // Unsafe methods must reject requests without an active session.
    $response = $middleware->process(
        new Request('POST', '/dashboard/example'),
        $next
    );

    $assert(
        $response->statusCode() === 403,
        'Unsafe request without an active session is rejected.'
    );

    $assert(
        !str_contains($response->body(), 'csrf_token'),
        'Forbidden response does not expose a CSRF token.'
    );

    foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
        $response = $middleware->process(
            new Request($method, '/dashboard/example'),
            $next
        );

        $assert(
            $response->statusCode() === 403,
            "{$method} is rejected without an active session."
        );
    }

    // Start an isolated session and generate a valid token.
    Session::start();
    $token = CsrfProtection::getToken();

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'X-CSRF-Token' => $token,
                'Origin' => 'http://ganeshasistemgemilang.net',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 200,
        'Valid token and trusted Origin are accepted.'
    );

    foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
        $response = $middleware->process(
            new Request(
                $method,
                '/dashboard/example',
                [],
                [
                    'X-CSRF-Token' => $token,
                    'Origin' => 'http://ganeshasistemgemilang.net',
                ]
            ),
            $next
        );

        $assert(
            $response->statusCode() === 200,
            "{$method} accepts a valid token and trusted Origin."
        );
    }

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'X-CSRF-Token' => $token,
                'Origin' => 'http://attacker.example',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 403,
        'Untrusted Origin is rejected even with a valid token.'
    );

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'X-CSRF-Token' => $token,
                'Origin' => 'http://ganeshasistemgemilang.net.attacker.example',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 403,
        'Origin hostname suffix spoofing is rejected.'
    );

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'X-CSRF-Token' => $token,
                'Referer' => 'http://ganeshasistemgemilang.net/dashboard/login',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 200,
        'Trusted Referer is accepted when Origin is absent.'
    );

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'X-CSRF-Token' => $token,
                'Referer' => 'http://attacker.example/dashboard/login',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 403,
        'Untrusted Referer is rejected.'
    );

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'X-CSRF-Token' => $token,
                'Origin' => 'null',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 403,
        'Opaque null Origin is rejected.'
    );

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'X-CSRF-Token' => 'invalid-token',
                'Origin' => 'http://ganeshasistemgemilang.net',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 403,
        'Invalid CSRF token is rejected.'
    );

    $response = $middleware->process(
        new Request(
            'POST',
            '/dashboard/example',
            [],
            [
                'Origin' => 'http://ganeshasistemgemilang.net',
            ]
        ),
        $next
    );

    $assert(
        $response->statusCode() === 403,
        'Missing CSRF token is rejected.'
    );

    $assert(
        $nextCalls === 8,
        'Only requests passing CSRF checks reach the next handler.'
    );
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

echo "\nCSRF middleware tests: {$passed} passed, {$failed} failed.\n";

ob_end_flush();
exit($failed === 0 ? 0 : 1);
