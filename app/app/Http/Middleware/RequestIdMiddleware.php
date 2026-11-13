<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Http\Request;
use GNSG\Http\Response;
use RuntimeException;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(
        Request $request,
        callable $next
    ): Response {
        try {
            $requestId = bin2hex(random_bytes(16));
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Unable to generate request identifier.'
            );
        }

        $response = $next($request);

        $response->setHeader(
            'X-Request-ID',
            $requestId
        );

        return $response;
    }
}
