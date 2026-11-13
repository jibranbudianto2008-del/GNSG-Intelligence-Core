<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Http\Request;
use GNSG\Http\Response;

interface MiddlewareInterface
{
    public function process(
        Request $request,
        callable $next
    ): Response;
}
