<?php

declare(strict_types=1);

namespace GNSG\Auth;

use RuntimeException;

final class Session
{
    private const DEFAULT_IDLE_TIMEOUT = 1800;
    private const DEFAULT_ABSOLUTE_TIMEOUT = 43200;

    private function __construct()
    {
    }

    public static function start(
        int $idleTimeout = self::DEFAULT_IDLE_TIMEOUT,
        int $absoluteTimeout = self::DEFAULT_ABSOLUTE_TIMEOUT
    ): void {
        if ($idleTimeout < 1 || $absoluteTimeout < 1) {
            throw new RuntimeException('Invalid session timeout.');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            throw new RuntimeException(
                'Cannot start session after headers have been sent.'
            );
        }

        $isHttps = isset($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== ''
            && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name('GNSGSESSID');

        if ((bool) ini_get('session.use_cookies')) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        if (!session_start()) {
            throw new RuntimeException('Unable to start session.');
        }

        $now = time();

        $createdAt = $_SESSION['_created_at'] ?? null;
        $lastActivity = $_SESSION['_last_activity'] ?? null;

        $sessionExpired = (
            !is_int($createdAt)
            || !is_int($lastActivity)
            || ($now - $lastActivity) > $idleTimeout
            || ($now - $createdAt) > $absoluteTimeout
        );

        if ($sessionExpired) {
            $_SESSION = [];

            if (!session_regenerate_id(true)) {
                throw new RuntimeException(
                    'Unable to regenerate expired session ID.'
                );
            }

            $_SESSION['_created_at'] = $now;
            $_SESSION['_last_activity'] = $now;

            return;
        }

        // Update idle timeout without extending absolute timeout.
        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerateId(): void
    {
        self::requireActive();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'Unable to regenerate session ID.'
            );
        }
    }

    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        self::requireActive();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::requireActive();

        if (str_starts_with($key, '_')) {
            throw new RuntimeException('Reserved session key.');
        }

        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        self::requireActive();

        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        $params = session_get_cookie_params();

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    private static function requireActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session is not active.');
        }
    }
}
