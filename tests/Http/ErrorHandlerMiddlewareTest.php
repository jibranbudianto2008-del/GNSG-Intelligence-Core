<?php

declare(strict_types=1);

use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Middleware\ErrorHandlerMiddleware;

require_once __DIR__ . '/../../app/Http/Request.php';
require_once __DIR__ . '/../../app/Http/Response.php';
require_once __DIR__ . '/../../app/Http/Middleware/MiddlewareInterface.php';
require_once __DIR__ . '/../../app/Http/Middleware/ErrorHandlerMiddleware.php';

$passed = 0;
$failed = 0;

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

$middleware = new ErrorHandlerMiddleware();

$request = new Request(
    'GET',
    '/health'
);

echo "===== ERROR HANDLER MIDDLEWARE TESTS =====\n\n";

runTest(
    'Middleware preserves a successful response.',
    function () use ($middleware, $request): void {
        $original = Response::json(
            ['status' => 'ok'],
            200
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

        if ($response->statusCode() !== 200) {
            throw new RuntimeException(
                'Successful response status was changed.'
            );
        }
    },
    $passed,
    $failed
);

runTest(
    'Middleware converts RuntimeException into a generic HTTP 500.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static function (Request $request): Response {
                throw new RuntimeException(
                    'Internal database connection details.'
                );
            }
        );

        if ($response->statusCode() !== 500) {
            throw new RuntimeException(
                'Expected HTTP status 500.'
            );
        }

        $expected = [
            'error' => [
                'code' => 'internal_server_error',
                'message' => 'An internal server error occurred.',
            ],
        ];

        $actual = json_decode(
            $response->body(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if ($actual !== $expected) {
            throw new RuntimeException(
                'Unexpected error response structure.'
            );
        }
    },
    $passed,
    $failed
);

runTest(
    'Middleware catches unexpected Throwable instances.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static function (Request $request): Response {
                throw new Error(
                    'Unexpected internal error.'
                );
            }
        );

        if ($response->statusCode() !== 500) {
            throw new RuntimeException(
                'Unexpected Throwable was not converted to HTTP 500.'
            );
        }
    },
    $passed,
    $failed
);

runTest(
    'Middleware does not expose exception messages.',
    function () use ($middleware, $request): void {
        $secretMessage = 'PRIVATE_DATABASE_PASSWORD_123';

        $response = $middleware->process(
            $request,
            static function (Request $request) use ($secretMessage): Response {
                throw new RuntimeException($secretMessage);
            }
        );

        if (str_contains($response->body(), $secretMessage)) {
            throw new RuntimeException(
                'Exception message leaked into HTTP response.'
            );
        }
    },
    $passed,
    $failed
);

runTest(
    'Middleware does not expose stack traces.',
    function () use ($middleware, $request): void {
        $response = $middleware->process(
            $request,
            static function (Request $request): Response {
                throw new RuntimeException(
                    'Internal failure.'
                );
            }
        );

        if (
            str_contains($response->body(), 'Stack trace')
            || str_contains($response->body(), '#0')
        ) {
            throw new RuntimeException(
                'Stack trace leaked into HTTP response.'
            );
        }
    },
    $passed,
    $failed
);

runTest(
    'Middleware preserves the original request.',
    function () use ($middleware, $request): void {
        $receivedRequest = null;

        $middleware->process(
            $request,
            static function (Request $nextRequest) use (&$receivedRequest): Response {
                $receivedRequest = $nextRequest;

                return new Response('', 204);
            }
        );

        if ($receivedRequest !== $request) {
            throw new RuntimeException(
                'Middleware changed the Request object.'
            );
        }
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

echo "\nALL ERROR HANDLER MIDDLEWARE TESTS PASSED.\n";
