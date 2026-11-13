<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/PasswordHasher.php';
require_once dirname(__DIR__, 2) . '/app/Auth/Session.php';
require_once dirname(__DIR__, 2) . '/app/Security/CsrfProtection.php';
require_once dirname(__DIR__, 2) . '/app/Auth/AuthManager.php';

use GNSG\Auth\AuthManager;
use GNSG\Auth\PasswordHasher;
use GNSG\Auth\Session;
use GNSG\Security\CsrfProtection;

$results = [];
$testError = null;
$cleanupError = null;

$pdo = null;
$tableCreated = false;
$sessionDir = null;

$assert = static function (
    bool $condition,
    string $description
) use (&$results): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: {$description}");
    }

    $results[] = "PASSED: {$description}";
};

try {
    // TEST CONFIGURATION

    $envFile = '/etc/gnsg/gnsg-core-test.env';

    if (!is_readable($envFile)) {
        throw new RuntimeException(
            'Test database credentials are not readable.'
        );
    }

    $config = parse_ini_file(
        $envFile,
        false,
        INI_SCANNER_RAW
    );

    if (
        !is_array($config)
        || !isset($config['TEST_DB_PASSWORD'])
        || !is_string($config['TEST_DB_PASSWORD'])
        || !preg_match(
            '/\A[a-f0-9]{64}\z/',
            $config['TEST_DB_PASSWORD']
        )
    ) {
        throw new RuntimeException(
            'Invalid test database credentials.'
        );
    }

    $pdo = new PDO(
        'mysql:host=localhost;dbname=gnsg_core_test;charset=utf8mb4',
        'gnsg_core_test',
        $config['TEST_DB_PASSWORD'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ]
    );

    unset($config);

    $assert(
        $pdo->query('SELECT DATABASE()')->fetchColumn()
            === 'gnsg_core_test',
        'Connected to isolated test database'
    );

    $assert(
        $pdo->query('SELECT CURRENT_USER()')->fetchColumn()
            === 'gnsg_core_test@localhost',
        'Connected using dedicated test account'
    );

    // SAFETY CHECK: TEST DATABASE MUST BE EMPTY.

    $existingTables = $pdo
        ->query('SHOW TABLES')
        ->fetchAll(PDO::FETCH_COLUMN);

    if ($existingTables !== []) {
        throw new RuntimeException(
            'Test database is not empty. No tables were modified.'
        );
    }

    $assert(
        $existingTables === [],
        'Test database is empty before setup'
    );

    // CREATE ISOLATED USERS TABLE.

    $pdo->exec(
        "CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) NOT NULL UNIQUE,
            email VARCHAR(255) UNIQUE,
            password_hash VARCHAR(255) NULL,
            display_name VARCHAR(120) NOT NULL,
            status ENUM('active', 'disabled', 'locked')
                NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_general_ci"
    );

    $tableCreated = true;

    $assert(
        $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
            === ['users'],
        'Isolated users table created'
    );

    // CREATE DUMMY USERS.

    $password = 'GNSG-Test-Password-2026';

    $passwordHash = PasswordHasher::hash($password);

    $insertUser = $pdo->prepare(
        'INSERT INTO users (
            username,
            email,
            password_hash,
            display_name,
            status
        ) VALUES (
            :username,
            :email,
            :password_hash,
            :display_name,
            :status
        )'
    );

    $insertUser->execute([
        'username' => 'auth_active',
        'email' => 'active@example.test',
        'password_hash' => $passwordHash,
        'display_name' => 'Active Test User',
        'status' => 'active',
    ]);

    $insertUser->execute([
        'username' => 'auth_disabled',
        'email' => 'disabled@example.test',
        'password_hash' => $passwordHash,
        'display_name' => 'Disabled Test User',
        'status' => 'disabled',
    ]);

    $insertUser->execute([
        'username' => 'auth_locked',
        'email' => 'locked@example.test',
        'password_hash' => $passwordHash,
        'display_name' => 'Locked Test User',
        'status' => 'locked',
    ]);

    $insertUser->execute([
        'username' => 'auth_null_hash',
        'email' => 'nullhash@example.test',
        'password_hash' => null,
        'display_name' => 'Null Hash Test User',
        'status' => 'active',
    ]);

    $assert(
        (int) $pdo->query(
            'SELECT COUNT(*) FROM users'
        )->fetchColumn() === 4,
        'Four isolated test users created'
    );

    // TEST SESSION CONFIGURATION.

    $sessionDir = sys_get_temp_dir()
        . '/gnsg-auth-test-' . bin2hex(random_bytes(4));

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
        'No existing session is active'
    );

    $manager = new AuthManager($pdo);

    // TEST 1: AUTHENTICATION.

    $user = $manager->authenticate(
        'auth_active',
        $password
    );

    $assert(
        is_array($user),
        'Valid credentials authenticate successfully'
    );

    $assert(
        $manager->authenticate(
            'auth_active',
            'Wrong-Password'
        ) === null,
        'Incorrect password is rejected'
    );

    $assert(
        $manager->authenticate(
            'unknown_user',
            $password
        ) === null,
        'Unknown username is rejected'
    );

    $assert(
        $manager->authenticate(
            'auth_disabled',
            $password
        ) === null,
        'Disabled account is rejected'
    );

    $assert(
        $manager->authenticate(
            'auth_locked',
            $password
        ) === null,
        'Locked account is rejected'
    );

    $assert(
        $manager->authenticate(
            'auth_null_hash',
            $password
        ) === null,
        'Null password hash is rejected safely'
    );

    // TEST 2: INPUT VALIDATION.

    $assert(
        $manager->authenticate('', $password) === null,
        'Empty username is rejected'
    );

    $assert(
        $manager->authenticate('auth_active', '') === null,
        'Empty password is rejected'
    );

    $assert(
        $manager->authenticate(
            str_repeat('u', 65),
            $password
        ) === null,
        'Username exceeding 64 bytes is rejected'
    );

    $assert(
        $manager->authenticate(
            'auth_active',
            str_repeat('p', 4097)
        ) === null,
        'Password exceeding 4096 bytes is rejected'
    );

    // TEST 3: SAFE USER DATA.

    $expectedKeys = [
        'id',
        'username',
        'email',
        'display_name',
    ];

    $assert(
        is_array($user)
        && array_keys($user) === $expectedKeys,
        'Authenticated user contains only safe fields'
    );

    $assert(
        is_array($user)
        && !array_key_exists('password_hash', $user),
        'Password hash is never exposed'
    );

    // TEST 4: CURRENT USER WITHOUT ACTIVE SESSION.

    $assert(
        $manager->currentUser() === null,
        'Current user is null without active session'
    );

    // TEST 5: SUCCESSFUL LOGIN AND SESSION REGENERATION.

    Session::start();

    $previousSessionId = session_id();
    $anonymousCsrfToken = CsrfProtection::getToken();

    $loginResult = $manager->login(
        'auth_active',
        $password
    );

    $assert(
        $loginResult === true,
        'Login succeeds with valid credentials'
    );

    $assert(
        session_id() !== $previousSessionId,
        'Session ID regenerates after successful login'
    );

    $assert(
        $manager->currentUser() === $user,
        'Authenticated identity is stored in session'
    );

    $authenticatedCsrfToken = CsrfProtection::getToken();

    $assert(
        $authenticatedCsrfToken !== $anonymousCsrfToken,
        'CSRF token rotates after successful login'
    );

    $assert(
        !CsrfProtection::validate($anonymousCsrfToken),
        'Anonymous CSRF token is rejected after login'
    );

    $assert(
        CsrfProtection::validate($authenticatedCsrfToken),
        'New authenticated CSRF token is valid'
    );

    // TEST 6: FAILED LOGIN REMOVES PREVIOUS IDENTITY.

    Session::set('auth_user', [
        'id' => 999,
        'username' => 'stale_identity',
    ]);

    $csrfTokenBeforeFailedLogin = CsrfProtection::getToken();

    $failedLogin = $manager->login(
        'auth_active',
        'Incorrect-Password'
    );

    $assert(
        $failedLogin === false,
        'Login fails with incorrect password'
    );

    $assert(
        $manager->currentUser() === null,
        'Failed login removes previous authenticated identity'
    );

    $assert(
        CsrfProtection::getToken() === $csrfTokenBeforeFailedLogin,
        'Failed login does not rotate the CSRF token'
    );

    $assert(
        CsrfProtection::validate($csrfTokenBeforeFailedLogin),
        'Existing CSRF token remains valid after failed login'
    );

    // TEST 7: LOGOUT.

    $manager->logout();

    $assert(
        session_status() === PHP_SESSION_NONE,
        'Logout destroys active session'
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

    // REMOVE TEMPORARY SESSION FILES.

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

    // DROP ONLY THE TABLE CREATED BY THIS TEST.

    if ($tableCreated && $pdo instanceof PDO) {
        try {
            $pdo->exec('DROP TABLE IF EXISTS users');
        } catch (Throwable $e) {
            $cleanupError = 'Unable to remove test users table.';
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

echo PHP_EOL . 'ALL AUTH MANAGER TESTS PASSED.' . PHP_EOL;
