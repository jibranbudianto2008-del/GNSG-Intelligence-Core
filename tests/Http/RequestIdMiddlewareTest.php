<?php

declare(strict_types=1);

use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Middleware\RequestIdMiddleware;

require_once __DIR__ . '/../../app/Http/Request.php';
require_once __DIR__ . '/../../app/Http/Response.php';
require_once __DIR__ . '/../../app/Http/Middleware/MiddlewareInterface.php';
require_once __DIR__ . '/../../app/Http/Middleware/RequestIdMiddleware.php';

$passed = 0;
$failed = 0;

$check = static function (
    bool $condition,
    string $description
) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "[PASS] {$description}\n";
        return;
    }

    $failed++;
    echo "[FAIL] {$description}\n";
};

echo "===== REQUEST ID MIDDLEWARE TESTS =====\n\n";

$middleware = new RequestIdMiddleware();
$request = new Request('GET', '/health');

// Test 1: Middleware preserves the response.
$response = $middleware->process(
    $request,
    static fn(Request $request): Response =>
        Response::json(['status' => 'ok'])
);

$check(
    $response->statusCode() === 200,
    'Middleware preserves the original response status.'
);

// Test 2: Middleware generates a valid request ID.
$requestId = $response->header('X-Request-ID');

$check(
    is_string($requestId)
    && preg_match('/\A[a-f0-9]{32}\z/', $requestId) === 1,
    'Middleware generates a 32-character hexadecimal request ID.'
);

// Test 3: Different requests receive different IDs.
$secondResponse = $middleware->process(
    $request,
    static fn(Request $request): Response =>
        new Response('ok')
);

$secondRequestId = $secondResponse->header('X-Request-ID');

$check(
    is_string($secondRequestId)
    && $secondRequestId !== $requestId,
    'Separate requests receive different request IDs.'
);

// Test 4: Middleware overrides a route-supplied request ID.
$spoofedResponse = $middleware->process(
    $request,
    static function (Request $request): Response {
        return new Response(
            'ok',
            200,
            ['X-Request-ID' => 'client-controlled-value']
        );
    }
);

$actualId = $spoofedResponse->header('X-Request-ID');

$check(
    is_string($actualId)
    && preg_match('/\A[a-f0-9]{32}\z/', $actualId) === 1
    && $actualId !== 'client-controlled-value',
    'Middleware replaces any route-supplied request ID.'
);

// Test 5: Middleware passes the original Request to the next handler.
$receivedRequest = null;

$middleware->process(
    $request,
    static function (Request $nextRequest) use (
        &$receivedRequest
    ): Response {
        $receivedRequest = $nextRequest;

        return new Response('ok');
    }
);

$check(
    $receivedRequest === $request,
    'Middleware passes the original Request to the next handler.'
);

echo "\n===== TEST SUMMARY =====\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "\nALL REQUEST ID MIDDLEWARE TESTS PASSED.\n";
