<?php

declare(strict_types=1);

use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Middleware\SecurityHeadersMiddleware;

require_once __DIR__ . '/../../app/Http/Request.php';
require_once __DIR__ . '/../../app/Http/Response.php';
require_once __DIR__ . '/../../app/Http/Middleware/MiddlewareInterface.php';
require_once __DIR__ . '/../../app/Http/Middleware/SecurityHeadersMiddleware.php';

$passed = 0;
$failed = 0;

function assertHeader(
    Response $response,
    string $name,
    string $expected
): void {
    $actual = $response->header($name);

    if ($actual !== $expected) {
        throw new RuntimeException(
            sprintf(
                'Expected header %s to be "%s", got "%s".',
                $name,
                $expected,
                $actual ?? 'null'
            )
        );
    }
}

function runTest(
    string $name,
    callable $test,
    int &$passed,
    int &$failed
): void {
    try {
        $test();
        $passed++;

        echo "[PASS] {$name}\n";
    } catch (Throwable $exception) {
        $failed++;

        echo "[FAIL] {$name}\n";
        echo "       {$exception->getMessage()}\n";
    }
}

$middleware = new SecurityHeadersMiddleware();

$request = new Request(
    'GET',
    '/health'
);

echo "===== SECURITY HEADERS MIDDLEWARE TESTS =====\n\n";

runTest(
    'Middleware adds X-Content-Type-Options nosniff.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static fn(Request $request): Response =>
                new Response('', 200)
        );

        assertHeader(
            $response,
            'X-Content-Type-Options',
            'nosniff'
        );
    },
    $passed,
    $failed
);

runTest(
    'Middleware adds X-Frame-Options DENY.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static fn(Request $request): Response =>
                new Response('', 200)
        );

        assertHeader(
            $response,
            'X-Frame-Options',
            'DENY'
        );
    },
    $passed,
    $failed
);

runTest(
    'Middleware adds the expected Referrer-Policy.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static fn(Request $request): Response =>
                new Response('', 200)
        );

        assertHeader(
            $response,
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );
    },
    $passed,
    $failed
);

runTest(
    'Middleware disables camera, microphone, and geolocation.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static fn(Request $request): Response =>
                new Response('', 200)
        );

        assertHeader(
            $response,
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        );
    },
    $passed,
    $failed
);

runTest(
    'Middleware preserves the original response status and body.',
    function () use ($middleware, $request): void {
        $original = Response::json(
            ['status' => 'ok'],
            201
        );

        $response = $middleware->process(
            $request,
            static fn(Request $request): Response => $original
        );

        if ($response !== $original) {
            throw new RuntimeException(
                'Middleware replaced the original response.'
            );
        }

        if ($response->statusCode() !== 201) {
            throw new RuntimeException(
                'Response status was not preserved.'
            );
        }

        if ($response->body() !== '{"status":"ok"}') {
            throw new RuntimeException(
                'Response body was not preserved.'
            );
        }
    },
    $passed,
    $failed
);

runTest(
    'Middleware applies security headers to error responses.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static fn(Request $request): Response =>
                Response::json(
                    ['error' => 'not_found'],
                    404
                )
        );

        if ($response->statusCode() !== 404) {
            throw new RuntimeException(
                'Original error status was not preserved.'
            );
        }

        assertHeader(
            $response,
            'X-Content-Type-Options',
            'nosniff'
        );

        assertHeader(
            $response,
            'X-Frame-Options',
            'DENY'
        );
    },
    $passed,
    $failed
);

runTest(
    'Middleware replaces weaker route-supplied security headers.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static function (Request $request): Response {
                return new Response(
                    '',
                    200,
                    [
                        'X-Frame-Options' => 'SAMEORIGIN',
                        'X-Content-Type-Options' => 'unsafe-value',
                    ]
                );
            }
        );

        assertHeader(
            $response,
            'X-Frame-Options',
            'DENY'
        );

        assertHeader(
            $response,
            'X-Content-Type-Options',
            'nosniff'
        );
    },
    $passed,
    $failed
);

echo "\n===== TEST SUMMARY =====\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "\nALL SECURITY HEADERS MIDDLEWARE TESTS PASSED.\n";
