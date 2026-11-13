<?php

declare(strict_types=1);

namespace GNSG\Auth;

use GNSG\Database\Connection;
use GNSG\Security\CsrfProtection;
use PDO;

final class AuthManager
{
    private PDO $db;

    private static ?string $dummyHash = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }

    /**
     * Verify username and password.
     * Returns safe user data on success, otherwise null.
     */
    public function authenticate(
        string $username,
        string $password
    ): ?array {
        $username = trim($username);

        if (
            $username === ''
            || strlen($username) > 64
            || $password === ''
            || strlen($password) > 4096
        ) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT
                id,
                username,
                email,
                password_hash,
                display_name,
                status
             FROM users
             WHERE username = :username
             LIMIT 1'
        );

        $stmt->execute([
            'username' => $username,
        ]);

        $user = $stmt->fetch();

        $storedHash = (
            is_array($user)
            && is_string($user['password_hash'] ?? null)
            && $user['password_hash'] !== ''
        )
            ? $user['password_hash']
            : $this->getDummyHash();

        $passwordIsValid = PasswordHasher::verify(
            $password,
            $storedHash
        );

        if (
            !$passwordIsValid
            || !is_array($user)
            || $user['status'] !== 'active'
        ) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
        ];
    }

    /**
     * Authenticate and establish an application session.
     */
    public function login(
        string $username,
        string $password
    ): bool {
        Session::start();

        // Remove any previous authenticated identity.
        Session::remove('auth_user');

        $user = $this->authenticate($username, $password);

        if ($user === null) {
            return false;
        }

        // Prevent session fixation after successful authentication.
        Session::regenerateId();

        // Invalidate the anonymous CSRF token after authentication.
        CsrfProtection::rotateToken();

        Session::set('auth_user', $user);

        return true;
    }

    /**
     * Return the authenticated user from the current session.
     */
    public function currentUser(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $user = Session::get('auth_user');

        return is_array($user) ? $user : null;
    }

    /**
     * End the current application session.
     */
    public function logout(): void
    {
        Session::destroy();
    }

    /**
     * Generate a dummy hash for failed authentication checks.
     */
    private function getDummyHash(): string
    {
        if (self::$dummyHash === null) {
            self::$dummyHash = PasswordHasher::hash(
                bin2hex(random_bytes(32))
            );
        }

        return self::$dummyHash;
    }
}
