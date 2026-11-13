<?php

declare(strict_types=1);

use GNSG\Auth\AuthManager;
use GNSG\Auth\Session;
use GNSG\Http\Middleware\AuthMiddleware;
use GNSG\Http\Middleware\MiddlewareInterface;
use GNSG\Http\Request;
use GNSG\Http\Response;

require_once __DIR__ . '/../../app/Auth/Session.php';
require_once __DIR__ . '/../../app/Auth/AuthManager.php';
require_once __DIR__ . '/../../app/Http/Request.php';
require_once __DIR__ . '/../../app/Http/Response.php';
require_once __DIR__ . '/../../app/Http/Middleware/MiddlewareInterface.php';
require_once __DIR__ . '/../../app/Http/Middleware/AuthMiddleware.php';

$passed = 0;
$failed = 0;

function check(bool $condition, string $description): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "[PASS] {$description}\n";
        return;
    }

    $failed++;
    echo "[FAIL] {$description}\n";
}

function responseStatus(Response $response): int
{
    return $response->statusCode();
}

ob_start();

$testDirectory = sys_get_temp_dir()
    . '/gnsg-auth-middleware-test-'
    . bin2hex(random_bytes(4));

mkdir($testDirectory, 0700);

ini_set('session.save_path', $testDirectory);
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cache_limiter', '');

try {
    $auth = (new ReflectionClass(AuthManager::class))
        ->newInstanceWithoutConstructor();

    $middleware = new AuthMiddleware($auth);

    $nextCalls = 0;

    $next = static function (Request $request) use (&$nextCalls): Response {
        $nextCalls++;

        return Response::json(['status' => 'passed']);
    };

    $request = new Request('GET', '/protected');

    $response = $middleware->process($request, $next);

    check(
        responseStatus($response) === 401,
        'Inactive session is rejected with HTTP 401'
    );

    check(
        $nextCalls === 0,
        'Next handler is not called when session is inactive'
    );

    Session::start();

    $response = $middleware->process($request, $next);

    check(
        responseStatus($response) === 401,
        'Active session without authenticated identity is rejected'
    );

    $invalidIdentities = [
        [
            'id' => '1',
            'username' => 'testuser',
            'email' => null,
            'display_name' => 'Test User',
        ],
        [
            'id' => 1,
            'email' => null,
            'display_name' => 'Test User',
        ],
        [
            'id' => 1,
            'username' => 'testuser',
            'email' => [],
            'display_name' => 'Test User',
        ],
        [
            'id' => 1,
            'username' => 'testuser',
            'display_name' => 'Test User',
        ],
        [
            'id' => 1,
            'username' => 'testuser',
            'email' => null,
            'display_name' => null,
        ],
    ];

    foreach ($invalidIdentities as $index => $identity) {
        Session::set('auth_user', $identity);

        $response = $middleware->process($request, $next);

        check(
            responseStatus($response) === 401,
            'Malformed identity #' . ($index + 1) . ' is rejected'
        );
    }

    Session::set('auth_user', [
        'id' => 1,
        'username' => 'testuser',
        'email' => null,
        'display_name' => 'Test User',
    ]);

    $response = $middleware->process($request, $next);

    check(
        responseStatus($response) === 200,
        'Valid authenticated identity is accepted'
    );

    check(
        $nextCalls === 1,
        'Next handler is called exactly once for valid identity'
    );

    Session::set('auth_user', [
        'id' => 2,
        'username' => 'anotheruser',
        'email' => 'user@example.test',
        'display_name' => 'Another User',
    ]);

    $response = $middleware->process($request, $next);

    check(
        responseStatus($response) === 200,
        'Valid identity with email string is accepted'
    );

    check(
        $nextCalls === 2,
        'Next handler is called for each valid identity'
    );
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        Session::destroy();
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    foreach (glob($testDirectory . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($testDirectory);
}

echo "\nResults: {$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);
