<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Http\Request;
use GNSG\Http\Response;
use Throwable;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function process(
        Request $request,
        callable $next
    ): Response {
        try {
            return $next($request);
        } catch (Throwable $exception) {
            error_log(
                sprintf(
                    'Unhandled application exception: %s',
                    get_class($exception)
                )
            );

            return Response::json(
                [
                    'error' => [
                        'code' => 'internal_server_error',
                        'message' => 'An internal server error occurred.',
                    ],
                ],
                500
            );
        }
    }
}
