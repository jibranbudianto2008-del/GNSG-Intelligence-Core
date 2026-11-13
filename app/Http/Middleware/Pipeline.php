<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Http\Request;
use GNSG\Http\Response;
use InvalidArgumentException;
use RuntimeException;

final class Pipeline
{
    /**
     * @var MiddlewareInterface[]
     */
    private array $middlewares = [];

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function process(
        Request $request,
        callable $destination
    ): Response {
        $next = static function (Request $request) use (
            $destination
        ): Response {
            $response = $destination($request);

            if (!$response instanceof Response) {
                throw new RuntimeException(
                    'Pipeline destination must return a Response instance.'
                );
            }

            return $response;
        };

        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = static function (Request $request) use (
                $middleware,
                $next
            ): Response {
                $response = $middleware->process(
                    $request,
                    $next
                );

                if (!$response instanceof Response) {
                    throw new RuntimeException(
                        'Middleware must return a Response instance.'
                    );
                }

                return $response;
            };
        }

        return $next($request);
    }

    public function count(): int
    {
        return count($this->middlewares);
    }
}
