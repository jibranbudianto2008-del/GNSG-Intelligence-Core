<?php

declare(strict_types=1);

namespace GNSG\Security;

use GNSG\Auth\Session;
use RuntimeException;

final class CsrfProtection
{
    private const SESSION_KEY = 'csrf_token';
    private const TOKEN_PATTERN = '/\A[a-f0-9]{64}\z/D';

    private function __construct()
    {
    }

    /**
     * Return the current session CSRF token.
     * Generate a new token when none exists or the stored value is invalid.
     */
    public static function getToken(): string
    {
        self::requireActiveSession();

        $token = Session::get(self::SESSION_KEY);

        if (
            !is_string($token)
            || preg_match(self::TOKEN_PATTERN, $token) !== 1
        ) {
            return self::rotateToken();
        }

        return $token;
    }

    /**
     * Generate and store a new cryptographically secure CSRF token.
     */
    public static function rotateToken(): string
    {
        self::requireActiveSession();

        $token = bin2hex(random_bytes(32));

        Session::set(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Validate a candidate token against the current session token.
     */
    public static function validate(string $candidate): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        if (
            preg_match(self::TOKEN_PATTERN, $candidate) !== 1
        ) {
            return false;
        }

        $expected = Session::get(self::SESSION_KEY);

        if (
            !is_string($expected)
            || preg_match(self::TOKEN_PATTERN, $expected) !== 1
        ) {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    private static function requireActiveSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException(
                'An active session is required for CSRF protection.'
            );
        }
    }
}
