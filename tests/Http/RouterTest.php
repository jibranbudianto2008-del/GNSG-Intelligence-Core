<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Http/Request.php';
require_once dirname(__DIR__, 2) . '/app/Http/Response.php';
require_once dirname(__DIR__, 2) . '/app/Http/Router.php';

use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Router;

$passed = 0;
$failed = 0;

$assert = static function (
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

$assertThrows = static function (
    callable $callback,
    string $description,
    string $expectedClass = InvalidArgumentException::class
) use ($assert): void {
    try {
        $callback();
        $assert(false, $description);
    } catch (Throwable $e) {
        $assert(
            $e instanceof $expectedClass,
            $description
        );
    }
};

// 1. Static route.
$router = new Router();

$router->get(
    '/health',
    static fn(Request $request, array $params): Response =>
        Response::json(['status' => 'ok'])
);

$response = $router->dispatch(
    new Request('GET', '/health')
);

$assert(
    $response->statusCode() === 200,
    'Static GET route returns HTTP 200'
);

$assert(
    json_decode($response->body(), true) === ['status' => 'ok'],
    'Static route returns handler response'
);

// 2. Dynamic route parameters.
$router->get(
    '/users/{userId}',
    static fn(Request $request, array $params): Response =>
        Response::json(['user_id' => $params['userId']])
);

$response = $router->dispatch(
    new Request('GET', '/users/42')
);

$assert(
    $response->statusCode() === 200,
    'Dynamic route matches URL parameter'
);

$assert(
    json_decode($response->body(), true) === ['user_id' => '42'],
    'Dynamic route passes named parameter to handler'
);

// 3. Static route takes priority over dynamic route.
$priorityRouter = new Router();

$priorityRouter->get(
    '/users/{userId}',
    static fn(Request $request, array $params): Response =>
        Response::json(['matched' => 'dynamic'])
);

$priorityRouter->get(
    '/users/me',
    static fn(Request $request, array $params): Response =>
        Response::json(['matched' => 'static'])
);

$response = $priorityRouter->dispatch(
    new Request('GET', '/users/me')
);

$assert(
    json_decode($response->body(), true)['matched'] === 'static',
    'Static route takes priority over dynamic route'
);

// 4. HTTP methods.
$methodRouter = new Router();

$methodRouter
    ->post(
        '/records',
        static fn(Request $request, array $params): Response =>
            Response::json(['method' => 'POST'], 201)
    )
    ->put(
        '/records',
        static fn(Request $request, array $params): Response =>
            Response::json(['method' => 'PUT'])
    )
    ->patch(
        '/records',
        static fn(Request $request, array $params): Response =>
            Response::json(['method' => 'PATCH'])
    )
    ->delete(
        '/records',
        static fn(Request $request, array $params): Response =>
            Response::json(['method' => 'DELETE'])
    );

foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
    $response = $methodRouter->dispatch(
        new Request($method, '/records')
    );

    $assert(
        $response->statusCode() === (
            $method === 'POST' ? 201 : 200
        ),
        "{$method} route is dispatched correctly"
    );
}

// 5. Route not found.
$response = $router->dispatch(
    new Request('GET', '/missing')
);

$assert(
    $response->statusCode() === 404,
    'Unknown route returns HTTP 404'
);

$assert(
    json_decode($response->body(), true)['error']['code']
        === 'not_found',
    '404 response contains stable error code'
);

// 6. Method not allowed.
$response = $methodRouter->dispatch(
    new Request('GET', '/records')
);

$assert(
    $response->statusCode() === 405,
    'Unsupported method returns HTTP 405'
);

$assert(
    $response->header('Allow') === 'DELETE, OPTIONS, PATCH, POST, PUT',
    '405 response includes allowed methods'
);

// 7. Automatic OPTIONS response.
$response = $methodRouter->dispatch(
    new Request('OPTIONS', '/records')
);

$assert(
    $response->statusCode() === 204,
    'Automatic OPTIONS returns HTTP 204'
);

$assert(
    $response->header('Allow') === 'DELETE, OPTIONS, PATCH, POST, PUT',
    'Automatic OPTIONS includes allowed methods'
);

$assert(
    $response->body() === '',
    'Automatic OPTIONS response has an empty body'
);

// 8. Explicit OPTIONS route.
$optionsRouter = new Router();

$optionsRouter->options(
    '/custom',
    static fn(Request $request, array $params): Response =>
        new Response('custom options', 200)
);

$response = $optionsRouter->dispatch(
    new Request('OPTIONS', '/custom')
);

$assert(
    $response->body() === 'custom options',
    'Explicit OPTIONS route takes precedence'
);

// 9. Duplicate route registration.
$assertThrows(
    static function (): void {
        $router = new Router();

        $handler = static fn(): Response => new Response();

        $router->get('/duplicate', $handler);
        $router->get('/duplicate', $handler);
    },
    'Duplicate route registration is rejected'
);

// 10. Route validation.
$assertThrows(
    static fn() => (new Router())->add(
        'INVALID METHOD',
        '/',
        static fn(): Response => new Response()
    ),
    'Invalid route method is rejected'
);

$assertThrows(
    static fn() => (new Router())->get(
        'relative/path',
        static fn(): Response => new Response()
    ),
    'Relative route path is rejected'
);

$assertThrows(
    static fn() => (new Router())->get(
        '/users/{id',
        static fn(): Response => new Response()
    ),
    'Malformed route parameter is rejected'
);

$assertThrows(
    static fn() => (new Router())->get(
        '/users/{id}/{id}',
        static fn(): Response => new Response()
    ),
    'Duplicate parameter name is rejected'
);

$assertThrows(
    static fn() => (new Router())->get(
        '/users//profile',
        static fn(): Response => new Response()
    ),
    'Empty route segment is rejected'
);

// 11. Handler return type enforcement.
$invalidHandlerRouter = new Router();

$invalidHandlerRouter->get(
    '/invalid-handler',
    static fn(): string => 'not a response'
);

$assertThrows(
    static fn() => $invalidHandlerRouter->dispatch(
        new Request('GET', '/invalid-handler')
    ),
    'Handler returning non-Response is rejected',
    RuntimeException::class
);

// 12. Final result.
echo "\n";
echo "Router tests passed: {$passed}\n";
echo "Router tests failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "ALL ROUTER TESTS PASSED.\n";
