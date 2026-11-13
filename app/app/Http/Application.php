<?php

declare(strict_types=1);

namespace GNSG\Http;

use GNSG\Http\Middleware\MiddlewareInterface;
use GNSG\Http\Middleware\Pipeline;

final class Application
{
    private readonly Router $router;

    private readonly Pipeline $pipeline;

    public function __construct(
        ?Router $router = null,
        ?Pipeline $pipeline = null
    ) {
        $this->router = $router ?? new Router();
        $this->pipeline = $pipeline ?? new Pipeline();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function pipe(
        MiddlewareInterface $middleware
    ): self {
        $this->pipeline->pipe($middleware);

        return $this;
    }

    public function handle(Request $request): Response
    {
        return $this->pipeline->process(
            $request,
            fn(Request $request): Response =>
                $this->router->dispatch($request)
        );
    }
}
