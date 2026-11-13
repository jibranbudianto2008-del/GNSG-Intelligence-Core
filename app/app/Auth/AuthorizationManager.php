<?php

declare(strict_types=1);

namespace GNSG\Auth;

use GNSG\Database\Connection;
use PDO;

final class AuthorizationManager
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }

    public function hasPermission(
        int $userId,
        string $permissionKey
    ): bool {
        if ($userId <= 0) {
            return false;
        }

        if (
            strlen($permissionKey) > 100
            || preg_match(
                '/\A[a-z][a-z0-9]*(?:[._][a-z0-9]+)*\z/',
                $permissionKey
            ) !== 1
        ) {
            return false;
        }

        $sql = '
            SELECT EXISTS (
                SELECT 1
                FROM user_roles ur
                INNER JOIN role_permissions rp
                    ON rp.role_id = ur.role_id
                INNER JOIN permissions p
                    ON p.id = rp.permission_id
                WHERE ur.user_id = :user_id
                  AND p.permission_key = :permission_key
            )
        ';

        $statement = $this->db->prepare($sql);

        $statement->execute([
            'user_id' => $userId,
            'permission_key' => $permissionKey,
        ]);

        return (int) $statement->fetchColumn() === 1;
    }
}
