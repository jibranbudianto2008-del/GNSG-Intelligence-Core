<?php

declare(strict_types=1);

use GNSG\Http\Middleware\MiddlewareInterface;
use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Router;

require_once __DIR__ . '/../../app/Http/Request.php';
require_once __DIR__ . '/../../app/Http/Response.php';
require_once __DIR__ . '/../../app/Http/Middleware/MiddlewareInterface.php';
require_once __DIR__ . '/../../app/Http/Middleware/Pipeline.php';
require_once __DIR__ . '/../../app/Http/Router.php';

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

$router = new Router();
$handlerCalls = 0;

$blockingMiddleware = new class implements MiddlewareInterface {
    public function process(Request $request, callable $next): Response
    {
        return Response::json(['error' => 'blocked'], 401);
    }
};

$passingMiddleware = new class implements MiddlewareInterface {
    public function process(Request $request, callable $next): Response
    {
        return $next($request);
    }
};

$router->get(
    '/protected',
    static function (Request $request, array $parameters) use (&$handlerCalls): Response {
        $handlerCalls++;

        return Response::json(['status' => 'protected']);
    },
    [$blockingMiddleware]
);

$router->get(
    '/allowed',
    static function (Request $request, array $parameters) use (&$handlerCalls): Response {
        $handlerCalls++;

        return Response::json(['status' => 'allowed']);
    },
    [$passingMiddleware]
);

$router->get(
    '/public',
    static function (Request $request, array $parameters) use (&$handlerCalls): Response {
        $handlerCalls++;

        return Response::json(['status' => 'public']);
    }
);

$response = $router->dispatch(new Request('GET', '/protected'));

check(
    responseStatus($response) === 401,
    'Route middleware can reject a request'
);

check(
    $handlerCalls === 0,
    'Blocked route handler is not executed'
);

$response = $router->dispatch(new Request('GET', '/allowed'));

check(
    responseStatus($response) === 200,
    'Passing middleware allows route execution'
);

check(
    $handlerCalls === 1,
    'Allowed route handler executes exactly once'
);

$response = $router->dispatch(new Request('GET', '/public'));

check(
    responseStatus($response) === 200,
    'Route without middleware remains accessible'
);

check(
    $handlerCalls === 2,
    'Public route handler executes normally'
);

$invalidMiddlewareRejected = false;

try {
    $router->get(
        '/invalid',
        static fn (Request $request, array $parameters): Response =>
            Response::json(['status' => 'invalid']),
        [new stdClass()]
    );
} catch (InvalidArgumentException) {
    $invalidMiddlewareRejected = true;
}

check(
    $invalidMiddlewareRejected,
    'Router rejects invalid route middleware'
);

echo "\nResults: {$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);
