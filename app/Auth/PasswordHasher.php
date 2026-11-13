<?php

declare(strict_types=1);

namespace GNSG\Auth;

use InvalidArgumentException;
use RuntimeException;

final class PasswordHasher
{
    private const ALGORITHM = PASSWORD_ARGON2ID;

    public static function hash(string $password): string
    {
        if ($password === '' || strlen($password) > 4096) {
            throw new InvalidArgumentException(
                'Invalid password input.'
            );
        }

        $hash = password_hash(
            $password,
            self::ALGORITHM
        );

        if (!is_string($hash)) {
            throw new RuntimeException(
                'Password hashing failed.'
            );
        }

        return $hash;
    }

    public static function verify(
        string $password,
        string $hash
    ): bool {
        if (
            $password === ''
            || strlen($password) > 4096
            || $hash === ''
        ) {
            return false;
        }

        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        if ($hash === '') {
            return true;
        }

        return password_needs_rehash(
            $hash,
            self::ALGORITHM
        );
    }
}
