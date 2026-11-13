<?php

declare(strict_types=1);

namespace GNSG\Http\Middleware;

use GNSG\Http\Request;
use GNSG\Http\Response;
use GNSG\Security\CsrfProtection;
use InvalidArgumentException;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = [
        'GET',
        'HEAD',
        'OPTIONS',
    ];

    private array $trustedOrigins;

    public function __construct(array $trustedOrigins)
    {
        $normalizedOrigins = [];

        foreach ($trustedOrigins as $origin) {
            if (!is_string($origin)) {
                throw new InvalidArgumentException(
                    'Trusted CSRF origins must be strings.'
                );
            }

            $normalized = self::normalizeOrigin($origin, false);

            if ($normalized === null) {
                throw new InvalidArgumentException(
                    'Invalid trusted CSRF origin configuration.'
                );
            }

            $normalizedOrigins[] = $normalized;
        }

        $this->trustedOrigins = array_values(
            array_unique($normalizedOrigins)
        );
    }

    public function process(
        Request $request,
        callable $next
    ): Response {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        if (!$this->hasTrustedRequestOrigin($request)) {
            return self::forbiddenResponse();
        }

        $candidate = $request->header('x-csrf-token');

        if (
            !is_string($candidate)
            || !CsrfProtection::validate($candidate)
        ) {
            return self::forbiddenResponse();
        }

        return $next($request);
    }

    private function hasTrustedRequestOrigin(
        Request $request
    ): bool {
        $origin = $request->header('origin');

        if ($origin !== null) {
            $normalized = self::normalizeOrigin($origin, false);

            return $normalized !== null
                && in_array(
                    $normalized,
                    $this->trustedOrigins,
                    true
                );
        }

        $referer = $request->header('referer');

        if ($referer !== null) {
            $normalized = self::normalizeOrigin($referer, true);

            return $normalized !== null
                && in_array(
                    $normalized,
                    $this->trustedOrigins,
                    true
                );
        }

        // Token validation remains mandatory when both headers are absent.
        return true;
    }

    /**
     * Normalize an HTTP(S) origin.
     *
     * When $allowPath is false, only an origin-form URL is accepted.
     * Referer URLs may contain a path, query, and fragment.
     */
    private static function normalizeOrigin(
        string $value,
        bool $allowPath
    ): ?string {
        $value = trim($value);

        if (
            $value === ''
            || preg_match('/[\x00-\x20\x7F]/', $value) === 1
        ) {
            return null;
        }

        $parts = parse_url($value);

        if (
            !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query']) && !$allowPath
            || isset($parts['fragment']) && !$allowPath
        ) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (
            !in_array($scheme, ['http', 'https'], true)
            || $host === ''
        ) {
            return null;
        }

        if (
            !$allowPath
            && isset($parts['path'])
            && $parts['path'] !== ''
            && $parts['path'] !== '/'
        ) {
            return null;
        }

        $port = $parts['port'] ?? null;

        if (
            $port !== null
            && (
                $port < 1
                || $port > 65535
            )
        ) {
            return null;
        }

        if (
            ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443)
        ) {
            $port = null;
        }

        return $scheme
            . '://'
            . $host
            . ($port !== null ? ':' . $port : '');
    }

    private static function forbiddenResponse(): Response
    {
        return Response::json(
            [
                'error' => [
                    'code' => 'csrf_validation_failed',
                    'message' => 'Request validation failed.',
                ],
            ],
            403
        );
    }
}
