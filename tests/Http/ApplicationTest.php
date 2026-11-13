<?php

declare(strict_types=1);

use GNSG\Http\Application;
use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Router;
use GNSG\Http\Middleware\MiddlewareInterface;
use GNSG\Http\Middleware\Pipeline;

require_once __DIR__ . '/../../app/Http/Request.php';
require_once __DIR__ . '/../../app/Http/Response.php';
require_once __DIR__ . '/../../app/Http/Router.php';
require_once __DIR__ . '/../../app/Http/Middleware/MiddlewareInterface.php';
require_once __DIR__ . '/../../app/Http/Middleware/Pipeline.php';
require_once __DIR__ . '/../../app/Http/Application.php';

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

echo "===== GNSG Application Integration Tests =====\n\n";

// Test 1: Application menyediakan Router secara default.
$app = new Application();

$check(
    $app->router() instanceof Router,
    'Application provides a default Router.'
);

// Test 2: Route yang didaftarkan melalui router dapat diproses.
$app->router()->get(
    '/health',
    static fn(Request $request, array $params): Response =>
        Response::json(['status' => 'ok'])
);

$response = $app->handle(
    new Request('GET', '/health')
);

$check(
    $response->statusCode() === 200
    && $response->body() === '{"status":"ok"}',
    'Application dispatches a registered route.'
);

// Test 3: Middleware berjalan sebelum route handler.
$events = [];

$app = new Application();

$app->pipe(
    new class($events) implements MiddlewareInterface {
        private array $events;

        public function __construct(array &$events)
        {
            $this->events = &$events;
        }

        public function process(
            Request $request,
            callable $next
        ): Response {
            $this->events[] = 'middleware';

            return $next($request);
        }
    }
);

$app->router()->get(
    '/ordered',
    static function (
        Request $request,
        array $params
    ) use (&$events): Response {
        $events[] = 'handler';

        return new Response('ok');
    }
);

$app->handle(new Request('GET', '/ordered'));

$check(
    $events === ['middleware', 'handler'],
    'Middleware executes before the route handler.'
);

// Test 4: Middleware dapat menghentikan request.
$handlerExecuted = false;

$app = new Application();

$app->pipe(
    new class implements MiddlewareInterface {
        public function process(
            Request $request,
            callable $next
        ): Response {
            return Response::json(
                ['error' => 'forbidden'],
                403
            );
        }
    }
);

$app->router()->get(
    '/protected',
    static function (
        Request $request,
        array $params
    ) use (&$handlerExecuted): Response {
        $handlerExecuted = true;

        return new Response('should not execute');
    }
);

$response = $app->handle(
    new Request('GET', '/protected')
);

$check(
    $response->statusCode() === 403
    && !$handlerExecuted,
    'Middleware can short-circuit route execution.'
);

// Test 5: Urutan middleware saat request dan response.
$events = [];

$app = new Application();

$app->pipe(
    new class($events, 'first') implements MiddlewareInterface {
        private array $events;
        private string $name;

        public function __construct(
            array &$events,
            string $name
        ) {
            $this->events = &$events;
            $this->name = $name;
        }

        public function process(
            Request $request,
            callable $next
        ): Response {
            $this->events[] = $this->name . ':before';

            $response = $next($request);

            $this->events[] = $this->name . ':after';

            return $response;
        }
    }
);

$app->pipe(
    new class($events, 'second') implements MiddlewareInterface {
        private array $events;
        private string $name;

        public function __construct(
            array &$events,
            string $name
        ) {
            $this->events = &$events;
            $this->name = $name;
        }

        public function process(
            Request $request,
            callable $next
        ): Response {
            $this->events[] = $this->name . ':before';

            $response = $next($request);

            $this->events[] = $this->name . ':after';

            return $response;
        }
    }
);

$app->router()->get(
    '/sequence',
    static function (
        Request $request,
        array $params
    ) use (&$events): Response {
        $events[] = 'handler';

        return new Response('ok');
    }
);

$app->handle(new Request('GET', '/sequence'));

$check(
    $events === [
        'first:before',
        'second:before',
        'handler',
        'second:after',
        'first:after',
    ],
    'Middleware executes in registration order and unwinds in reverse.'
);

// Test 6: Application menerima Router dan Pipeline dari luar.
$customRouter = new Router();

$customRouter->get(
    '/custom',
    static fn(Request $request, array $params): Response =>
        new Response('custom-router')
);

$customPipeline = new Pipeline();

$customPipeline->pipe(
    new class implements MiddlewareInterface {
        public function process(
            Request $request,
            callable $next
        ): Response {
            return $next($request);
        }
    }
);

$app = new Application(
    $customRouter,
    $customPipeline
);

$response = $app->handle(
    new Request('GET', '/custom')
);

$check(
    $response->body() === 'custom-router',
    'Application supports custom Router and Pipeline injection.'
);

// Test 7: Route yang tidak tersedia menghasilkan 404.
$app = new Application();

$response = $app->handle(
    new Request('GET', '/missing')
);

$check(
    $response->statusCode() === 404,
    'Application returns 404 for an unknown route.'
);

// Summary.
echo "\n===== TEST SUMMARY =====\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "\nALL APPLICATION TESTS PASSED.\n";
