<?php

declare(strict_types=1);

use GNSG\Http\Application;
use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Http\Middleware\RequestIdMiddleware;
use GNSG\Http\Middleware\SecurityHeadersMiddleware;
use GNSG\Http\Middleware\ErrorHandlerMiddleware;
use GNSG\Http\Middleware\CsrfMiddleware;

try {
    $config = require dirname(__DIR__) . '/bootstrap/app.php';

    $application = new Application();

    // Generate a unique ID for every incoming request.
    $application->pipe(new RequestIdMiddleware());

    // Apply baseline HTTP security headers.
    $application->pipe(new SecurityHeadersMiddleware());

    // Catch exceptions from the router and downstream middleware.
    $application->pipe(new ErrorHandlerMiddleware());

    // Load and validate CSRF security configuration.
    $securityConfig = require $config['config'] . '/security.php';
    $csrfConfig = $securityConfig['csrf'] ?? null;

    if (
        !is_array($csrfConfig)
        || !isset($csrfConfig['trusted_origins'])
        || !is_array($csrfConfig['trusted_origins'])
    ) {
        throw new RuntimeException(
            'CSRF security configuration is invalid.'
        );
    }

    // Validate unsafe HTTP requests before they reach route handlers.
    $application->pipe(
        new CsrfMiddleware($csrfConfig['trusted_origins'])
    );

    $registerRoutes = require $config['root'] . '/routes/web.php';

    if (!is_callable($registerRoutes)) {
        throw new RuntimeException(
            'Route registration configuration is invalid.'
        );
    }

    $registerRoutes($application->router());

    $request = Request::fromGlobals();

    $response = $application->handle($request);

    $response->send();

} catch (Throwable $exception) {
    error_log(
        'Unhandled HTTP exception: ' . get_class($exception)
    );

    if (headers_sent()) {
        exit(1);
    }

    try {
        Response::json(
            [
                'error' => [
                    'code' => 'internal_server_error',
                    'message' => 'An internal server error occurred.',
                ],
            ],
            500
        )->send();
    } catch (Throwable $responseException) {
        error_log(
            'HTTP error response failure: '
            . get_class($responseException)
        );

        http_response_code(500);
    }
}
