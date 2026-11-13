<?php

declare(strict_types=1);

use GNSG\Auth\AuthManager;
use GNSG\Auth\AuthorizationManager;
use GNSG\Auth\Session;
use GNSG\Http\Middleware\PermissionMiddleware;
use GNSG\Http\Request;
use GNSG\Http\Response;

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

$results = [];
$testError = null;
$cleanupError = null;
$pdo = null;
$tablesCreated = false;
$sessionDir = null;

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
    // TEST DATABASE CONFIGURATION.

    $envFile = '/etc/gnsg/gnsg-core-test.env';

    if (!is_readable($envFile)) {
        throw new RuntimeException(
            'Test database environment is unavailable.'
        );
    }

    $env = parse_ini_file(
        $envFile,
        false,
        INI_SCANNER_RAW
    );

    if (
        !is_array($env)
        || !isset($env['TEST_DB_PASSWORD'])
        || !is_string($env['TEST_DB_PASSWORD'])
        || preg_match('/\A[a-f0-9]{64}\z/', $env['TEST_DB_PASSWORD']) !== 1
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

    $assert(
        $pdo->query('SELECT DATABASE()')->fetchColumn()
            === 'gnsg_core_test',
        'Connected to dedicated test database'
    );

    if ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) !== []) {
        throw new RuntimeException(
            'Test database is not empty. Refusing to modify existing tables.'
        );
    }

    // CREATE ISOLATED RBAC TABLES.

    $tablesCreated = true;

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
            PRIMARY KEY (user_id, role_id)
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE role_permissions (
            role_id BIGINT UNSIGNED NOT NULL,
            permission_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (role_id, permission_id)
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        INSERT INTO permissions (permission_key)
        VALUES ('system.health.read')
    ");

    $permissionId = (int) $pdo->lastInsertId();

    $pdo->exec("
        INSERT INTO user_roles (user_id, role_id)
        VALUES (1001, 10)
    ");

    $statement = $pdo->prepare("
        INSERT INTO role_permissions (role_id, permission_id)
        VALUES (10, :permission_id)
    ");

    $statement->execute([
        'permission_id' => $permissionId,
    ]);

    // CONFIGURE TEMPORARY SESSION.

    $sessionDir = sys_get_temp_dir()
        . '/gnsg-permission-test-' . bin2hex(random_bytes(4));

    if (!mkdir($sessionDir, 0700)) {
        throw new RuntimeException(
            'Unable to create temporary session directory.'
        );
    }

    foreach ([
        'session.save_path' => $sessionDir,
        'session.use_trans_sid' => '0',
    ] as $setting => $value) {
        if (ini_set($setting, $value) === false) {
            throw new RuntimeException(
                "Unable to configure {$setting}."
            );
        }
    }

    $assert(
        session_save_path() === $sessionDir,
        'Temporary session directory configured'
    );

    $assert(
        session_status() === PHP_SESSION_NONE,
        'No session active before anonymous test'
    );

    // INITIALIZE DEPENDENCIES.

    $auth = new AuthManager($pdo);
    $authorization = new AuthorizationManager($pdo);

    $middleware = new PermissionMiddleware(
        $auth,
        $authorization,
        'system.health.read'
    );

    $request = new Request('GET', '/protected');

    $nextCalled = false;

    $next = static function (
        Request $request
    ) use (&$nextCalled): Response {
        $nextCalled = true;

        return new Response('handler reached', 200);
    };

    // TEST 1: ANONYMOUS USER MUST RECEIVE 401.

    $response = $middleware->process($request, $next);

    $assert(
        $response->statusCode() === 401,
        'Anonymous user receives HTTP 401'
    );

    $assert(
        !$nextCalled,
        'Anonymous request does not reach handler'
    );

    // ACTIVATE SESSION.

    Session::start();

    // TEST 2: INVALID USER ID TYPE MUST RECEIVE 401.

    Session::set('auth_user', [
        'id' => '1001',
        'username' => 'invalid_identity',
    ]);

    $response = $middleware->process($request, $next);

    $assert(
        $response->statusCode() === 401,
        'Invalid user ID type receives HTTP 401'
    );

    $assert(
        !$nextCalled,
        'Invalid identity does not reach handler'
    );

    // TEST 3: AUTHENTICATED USER WITHOUT PERMISSION MUST RECEIVE 403.

    Session::set('auth_user', [
        'id' => 1002,
        'username' => 'no_permission',
    ]);

    $response = $middleware->process($request, $next);

    $assert(
        $response->statusCode() === 403,
        'Authenticated user without permission receives HTTP 403'
    );

    $assert(
        !$nextCalled,
        'Denied request does not reach handler'
    );

    // TEST 4: AUTHORIZED USER MUST REACH HANDLER.

    Session::set('auth_user', [
        'id' => 1001,
        'username' => 'authorized_user',
    ]);

    $response = $middleware->process($request, $next);

    $assert(
        $response->statusCode() === 200,
        'Authorized user receives handler response'
    );

    $assert(
        $response->body() === 'handler reached',
        'Authorized request reaches handler'
    );

    $assert(
        $nextCalled,
        'Next middleware callback is executed'
    );

    // TEST 5: DATABASE EXCEPTION MUST PROPAGATE.

    $pdo->exec('DROP TABLE permissions');

    $databaseExceptionPropagated = false;

    try {
        $middleware->process($request, $next);
    } catch (PDOException $e) {
        $databaseExceptionPropagated = true;
    }

    $assert(
        $databaseExceptionPropagated,
        'Database exception propagates through middleware'
    );

} catch (Throwable $e) {
    $testError = $e->getMessage();

} finally {
    // SESSION CLEANUP.

    if (session_status() === PHP_SESSION_ACTIVE) {
        try {
            Session::destroy();
        } catch (Throwable $e) {
            $cleanupError = 'Unable to destroy test session.';
        }
    }

    if ($sessionDir !== null && is_dir($sessionDir)) {
        foreach (glob($sessionDir . '/*') ?: [] as $file) {
            if (is_file($file) || is_link($file)) {
                if (!unlink($file)) {
                    $cleanupError = 'Unable to remove session file.';
                }
            }
        }

        if (is_dir($sessionDir) && !rmdir($sessionDir)) {
            $cleanupError = 'Unable to remove session directory.';
        }
    }

    // DATABASE CLEANUP.

    if ($tablesCreated && $pdo instanceof PDO) {
        foreach ([
            'role_permissions',
            'user_roles',
            'permissions',
        ] as $table) {
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
    echo 'TEST ERROR: ' . $testError . PHP_EOL;
    exit(1);
}

if ($cleanupError !== null) {
    echo 'CLEANUP ERROR: ' . $cleanupError . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'ALL PERMISSION MIDDLEWARE TESTS PASSED.' . PHP_EOL;
