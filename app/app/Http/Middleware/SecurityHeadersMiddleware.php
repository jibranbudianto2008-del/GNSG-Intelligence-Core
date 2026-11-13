<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Http\Request;
use GNSG\Http\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(
        Request $request,
        callable $next
    ): Response {
        $response = $next($request);

        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => implode(
                ', ',
                [
                    'camera=()',
                    'microphone=()',
                    'geolocation=()',
                ]
            ),
        ];

        foreach ($securityHeaders as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }
}
