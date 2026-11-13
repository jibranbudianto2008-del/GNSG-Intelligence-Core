<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Http/Request.php';
require_once dirname(__DIR__, 2) . '/app/Http/Response.php';
require_once dirname(__DIR__, 2) . '/app/Http/Middleware/MiddlewareInterface.php';
require_once dirname(__DIR__, 2) . '/app/Http/Middleware/Pipeline.php';

use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Middleware\MiddlewareInterface;
use GNSG\Http\Middleware\Pipeline;

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
    string $expectedClass = RuntimeException::class
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

$request = new Request('GET', '/test');

// 1. Empty pipeline.
$emptyPipeline = new Pipeline();

$response = $emptyPipeline->process(
    $request,
    static fn(Request $request): Response =>
        new Response('destination')
);

$assert(
    $response->body() === 'destination',
    'Empty pipeline invokes destination directly'
);

$assert(
    $emptyPipeline->count() === 0,
    'New pipeline contains no middleware'
);

// 2. Middleware execution order.
$order = [];

$firstMiddleware = new class($order) implements MiddlewareInterface {
    private array $order;

    public function __construct(array &$order)
    {
        $this->order = &$order;
    }

    public function process(Request $request, callable $next): Response
    {
        $this->order[] = 'first-before';

        $response = $next($request);

        $this->order[] = 'first-after';

        return $response;
    }
};

$secondMiddleware = new class($order) implements MiddlewareInterface {
    private array $order;

    public function __construct(array &$order)
    {
        $this->order = &$order;
    }

    public function process(Request $request, callable $next): Response
    {
        $this->order[] = 'second-before';

        $response = $next($request);

        $this->order[] = 'second-after';

        return $response;
    }
};

$pipeline = new Pipeline();

$pipeline
    ->pipe($firstMiddleware)
    ->pipe($secondMiddleware);

$response = $pipeline->process(
    $request,
    static function (Request $request) use (&$order): Response {
        $order[] = 'destination';

        return new Response('completed');
    }
);

$assert(
    $order === [
        'first-before',
        'second-before',
        'destination',
        'second-after',
        'first-after',
    ],
    'Middleware executes in registration order and unwinds in reverse'
);

$assert(
    $response->body() === 'completed',
    'Pipeline returns destination response'
);

$assert(
    $pipeline->count() === 2,
    'Pipeline middleware count is correct'
);

// 3. Middleware can short-circuit the pipeline.
$shortCircuitPipeline = new Pipeline();

$shortCircuitPipeline->pipe(
    new class implements MiddlewareInterface {
        public function process(Request $request, callable $next): Response
        {
            return Response::json(
                ['error' => 'access_denied'],
                403
            );
        }
    }
);

$destinationCalled = false;

$response = $shortCircuitPipeline->process(
    $request,
    static function (Request $request) use (&$destinationCalled): Response {
        $destinationCalled = true;

        return new Response('should not execute');
    }
);

$assert(
    $response->statusCode() === 403,
    'Middleware can return its own response'
);

$assert(
    !$destinationCalled,
    'Short-circuit middleware prevents destination execution'
);

// 4. Middleware receives the original request.
$receivedRequest = null;

$requestPipeline = new Pipeline();

$requestPipeline->pipe(
    new class($receivedRequest) implements MiddlewareInterface {
        private ?Request $receivedRequest;

        public function __construct(?Request &$receivedRequest)
        {
            $this->receivedRequest = &$receivedRequest;
        }

        public function process(Request $request, callable $next): Response
        {
            $this->receivedRequest = $request;

            return $next($request);
        }
    }
);

$requestPipeline->process(
    $request,
    static fn(Request $request): Response => new Response('ok')
);

$assert(
    $receivedRequest === $request,
    'Middleware receives the original request object'
);

// 5. Invalid destination response.
$assertThrows(
    static function () use ($request): void {
        (new Pipeline())->process(
            $request,
            static fn(Request $request): string => 'invalid'
        );
    },
    'Destination returning non-Response is rejected'
);

// 6. Invalid middleware response.
$invalidMiddlewarePipeline = new Pipeline();

$invalidMiddlewarePipeline->pipe(
    new class implements MiddlewareInterface {
        public function process(Request $request, callable $next): Response
        {
            return 'invalid';
        }
    }
);

$assertThrows(
    static function () use ($invalidMiddlewarePipeline, $request): void {
        $invalidMiddlewarePipeline->process(
            $request,
            static fn(Request $request): Response => new Response('ok')
        );
    },
    'Middleware returning non-Response is rejected',
    TypeError::class
);

// 7. Final result.
echo "\n";
echo "Middleware pipeline tests passed: {$passed}\n";
echo "Middleware pipeline tests failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}

echo "ALL MIDDLEWARE PIPELINE TESTS PASSED.\n";
