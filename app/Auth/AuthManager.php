<?php

declare(strict_types=1);

namespace GNSG\Auth;

use GNSG\Database\Connection;
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
     * Authenticate a user using username and password.
     *
     * Returns safe user data on success, or null on failure.
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

        $passwordIsValid = password_verify(
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
     * Generate a dummy hash for failed authentication checks.
     */
    private function getDummyHash(): string
    {
        if (self::$dummyHash === null) {
            self::$dummyHash = password_hash(
                random_bytes(32),
                PASSWORD_ARGON2ID
            );
        }

        return self::$dummyHash;
    }
}
