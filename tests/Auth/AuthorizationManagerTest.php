<?php

declare(strict_types=1);

use GNSG\Auth\AuthorizationManager;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once GNSG_APP . '/Auth/AuthorizationManager.php';

$results = [];
$testError = null;
$cleanupError = null;
$pdo = null;
$tablesCreated = false;

$assert = static function (
    bool $condition,
    string $message
) use (&$results): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }

    $results[] = 'PASS: ' . $message;
};

try {
    // LOAD DEDICATED TEST DATABASE CONFIGURATION.

    $envFile = '/etc/gnsg/gnsg-core-test.env';

    if (!is_readable($envFile)) {
        throw new RuntimeException(
            'Test database environment is unavailable.'
        );
    }

    $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if (
        !is_array($env)
        || !isset($env['TEST_DB_PASSWORD'])
        || !is_string($env['TEST_DB_PASSWORD'])
        || preg_match(
            '/\A[a-f0-9]{64}\z/',
            $env['TEST_DB_PASSWORD']
        ) !== 1
    ) {
        throw new RuntimeException(
            'Invalid test database configuration.'
        );
    }

    $pdo = new PDO(
        'mysql:host=localhost;dbname=gnsg_core_test;charset=utf8mb4',
        'gnsg_core_test',
        $env['TEST_DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ]
    );

    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

    $assert(
        $database === 'gnsg_core_test',
        'Connected to dedicated test database'
    );

    // NEVER MODIFY A NON-EMPTY TEST DATABASE.

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    if ($tables !== []) {
        throw new RuntimeException(
            'Test database is not empty. Refusing to modify existing tables.'
        );
    }

    // ENABLE CLEANUP BEFORE CREATING ANY TABLE.

    $tablesCreated = true;

    // CREATE ISOLATED RBAC TEST TABLES.

    $pdo->exec("
        CREATE TABLE roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(64) NOT NULL UNIQUE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            permission_key VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE user_roles (
            user_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (user_id, role_id),
            FOREIGN KEY (role_id)
                REFERENCES roles(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE role_permissions (
            role_id BIGINT UNSIGNED NOT NULL,
            permission_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (role_id, permission_id),
            FOREIGN KEY (role_id)
                REFERENCES roles(id)
                ON DELETE CASCADE,
            FOREIGN KEY (permission_id)
                REFERENCES permissions(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    // INSERT TEST ROLES.

    $pdo->exec("
        INSERT INTO roles (id, role_key) VALUES
            (1, 'operator'),
            (2, 'admin')
    ");

    // INSERT TEST PERMISSIONS.

    $pdo->exec("
        INSERT INTO permissions (id, permission_key) VALUES
            (1, 'system.health.read'),
            (2, 'users.manage'),
            (3, 'database.backup.create')
    ");

    // USER 1001 HAS TWO ROLES.
    // USER 1002 HAS NO ROLE.
    // USER 1003 HAS ONE ROLE.

    $pdo->exec("
        INSERT INTO user_roles (user_id, role_id) VALUES
            (1001, 1),
            (1001, 2),
            (1003, 1)
    ");

    $pdo->exec("
        INSERT INTO role_permissions (role_id, permission_id) VALUES
            (1, 1),
            (2, 2),
            (2, 3)
    ");

    $manager = new AuthorizationManager($pdo);

    // TEST 1: SINGLE-ROLE PERMISSION.

    $assert(
        $manager->hasPermission(1003, 'system.health.read'),
        'Single-role permission is granted'
    );

    // TEST 2: MULTI-ROLE PERMISSION UNION.

    $assert(
        $manager->hasPermission(1001, 'system.health.read'),
        'Multi-role user receives first role permission'
    );

    $assert(
        $manager->hasPermission(1001, 'users.manage'),
        'Multi-role user receives second role permission'
    );

    $assert(
        $manager->hasPermission(1001, 'database.backup.create'),
        'Multi-role user receives all assigned permissions'
    );

    // TEST 3: USER WITHOUT ROLE.

    $assert(
        !$manager->hasPermission(1002, 'system.health.read'),
        'User without role receives no permission'
    );

    // TEST 4: USER WITHOUT PERMISSION.

    $assert(
        !$manager->hasPermission(1003, 'users.manage'),
        'User without requested permission is denied'
    );

    // TEST 5: UNKNOWN PERMISSION.

    $assert(
        !$manager->hasPermission(1001, 'unknown.permission.read'),
        'Unknown permission returns false'
    );

    // TEST 6: REVOKED ROLE.

    $pdo->exec("
        DELETE FROM user_roles
        WHERE user_id = 1001 AND role_id = 2
    ");

    $assert(
        !$manager->hasPermission(1001, 'users.manage'),
        'Revoked role immediately removes its permissions'
    );

    // TEST 7: REVOKED PERMISSION.

    $pdo->exec("
        DELETE FROM role_permissions
        WHERE role_id = 1 AND permission_id = 1
    ");

    $assert(
        !$manager->hasPermission(1003, 'system.health.read'),
        'Revoked permission is immediately denied'
    );

    // TEST 8: INVALID USER ID.

    $assert(
        !$manager->hasPermission(0, 'system.health.read'),
        'Zero user ID is rejected'
    );

    $assert(
        !$manager->hasPermission(-1, 'system.health.read'),
        'Negative user ID is rejected'
    );

    // TEST 9: INVALID PERMISSION KEY.

    $assert(
        !$manager->hasPermission(1001, ''),
        'Empty permission key is rejected'
    );

    $assert(
        !$manager->hasPermission(1001, 'Invalid Permission'),
        'Permission key with spaces is rejected'
    );

    $assert(
        !$manager->hasPermission(1001, str_repeat('a', 101)),
        'Permission key exceeding database limit is rejected'
    );

    // TEST 10: SQL INJECTION ATTEMPT.

    $assert(
        !$manager->hasPermission(1001, "' OR 1=1 --"),
        'SQL injection permission input is rejected'
    );

    // TEST 11: DATABASE EXCEPTION MUST PROPAGATE.

    $pdo->exec('DROP TABLE role_permissions');
    $pdo->exec('DROP TABLE permissions');

    $databaseExceptionPropagated = false;

    try {
        $manager->hasPermission(1001, 'system.health.read');
    } catch (PDOException $e) {
        $databaseExceptionPropagated = true;
    }

    $assert(
        $databaseExceptionPropagated,
        'Database exception propagates without default allow'
    );

} catch (Throwable $e) {
    $testError = $e->getMessage();

} finally {

    // DROP ONLY TABLES CREATED BY THIS TEST.

    if ($tablesCreated && $pdo instanceof PDO) {
        foreach (
            [
                'role_permissions',
                'user_roles',
                'permissions',
                'roles',
            ] as $table
        ) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
            } catch (Throwable $e) {
                $cleanupError = 'Unable to remove test tables.';
            }
        }
    }

    $pdo = null;
}

// DISPLAY RESULTS.

foreach ($results as $result) {
    echo $result . PHP_EOL;
}

if ($testError !== null) {
    fwrite(
        STDERR,
        'TEST ERROR: ' . $testError . PHP_EOL
    );
}

if ($cleanupError !== null) {
    fwrite(
        STDERR,
        'CLEANUP ERROR: ' . $cleanupError . PHP_EOL
    );
}

if ($testError !== null || $cleanupError !== null) {
    exit(1);
}

echo PHP_EOL . 'ALL AUTHORIZATION MANAGER TESTS PASSED.' . PHP_EOL;
