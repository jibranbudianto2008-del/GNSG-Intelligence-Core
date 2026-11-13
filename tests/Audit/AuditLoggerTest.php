<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Audit/AuditLogger.php';

use GNSG\Audit\AuditLogger;
use InvalidArgumentException;
use PDO;
use RuntimeException;

$results = [];
$testError = null;
$cleanupError = null;

$pdo = null;
$usersTableCreated = false;
$auditTableCreated = false;

$assert = static function (
    bool $condition,
    string $description
) use (&$results): void {
    if (!$condition) {
        throw new RuntimeException("FAILED: {$description}");
    }

    $results[] = "PASSED: {$description}";
};

$assertThrows = static function (
    callable $callback,
    string $exceptionClass,
    string $description
) use (&$results): void {
    try {
        $callback();
    } catch (Throwable $e) {
        if ($e instanceof $exceptionClass) {
            $results[] = "PASSED: {$description}";
            return;
        }

        throw new RuntimeException(
            "FAILED: {$description} (unexpected exception)"
        );
    }

    throw new RuntimeException(
        "FAILED: {$description} (no exception thrown)"
    );
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
        || !preg_match('/\A[a-f0-9]{64}\z/', $config['TEST_DB_PASSWORD'])
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

    // SAFETY CHECK

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

    // CREATE PARENT TABLE

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

    $usersTableCreated = true;

    // CREATE AUDIT TABLE

    $pdo->exec(
        "CREATE TABLE audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            resource_type VARCHAR(100) NULL,
            resource_id VARCHAR(100) NULL,
            status ENUM('success', 'failed', 'denied', 'pending')
                NOT NULL,
            details JSON NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            KEY idx_audit_user_created (user_id, created_at),
            KEY idx_audit_action_created (action, created_at),
            KEY idx_audit_status_created (status, created_at),

            CONSTRAINT fk_audit_user
                FOREIGN KEY (user_id)
                REFERENCES users(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ) ENGINE=InnoDB
        DEFAULT CHARSET=utf8mb4
        COLLATE=utf8mb4_general_ci"
    );

    $auditTableCreated = true;

    $assert(
        $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
            === ['audit_logs', 'users'],
        'Isolated audit tables created'
    );

    // CREATE TEST USER

    $pdo->exec(
        "INSERT INTO users (
            username,
            email,
            display_name,
            status
        ) VALUES (
            'audit_test_user',
            'audit@example.test',
            'Audit Test User',
            'active'
        )"
    );

    $userId = (int) $pdo->lastInsertId();

    $logger = new AuditLogger($pdo);

    // TEST 1: VALID EVENT

    $logger->log([
        'user_id' => $userId,
        'action' => 'user.login',
        'resource_type' => 'user',
        'resource_id' => (string) $userId,
        'status' => 'success',
        'details' => [
            'method' => 'password',
            'source' => 'test',
        ],
        'ip_address' => '192.0.2.10',
    ]);

    $record = $pdo->query(
        'SELECT * FROM audit_logs WHERE id = 1'
    )->fetch();

    $assert(
        is_array($record),
        'Valid audit event is inserted'
    );

    $assert(
        (int) $record['user_id'] === $userId,
        'Audit event stores the correct user ID'
    );

    $assert(
        $record['action'] === 'user.login'
            && $record['status'] === 'success',
        'Audit action and status are stored correctly'
    );

    $assert(
        $record['resource_type'] === 'user'
            && $record['resource_id'] === (string) $userId,
        'Audit resource information is stored correctly'
    );

    $assert(
        $record['ip_address'] === '192.0.2.10',
        'Valid IP address is stored'
    );

    $decodedDetails = json_decode(
        $record['details'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $assert(
        $decodedDetails === [
            'method' => 'password',
            'source' => 'test',
        ],
        'Non-sensitive details are preserved'
    );

    // TEST 2: NULLABLE FIELDS

    $logger->log([
        'action' => 'system.health_check',
        'status' => 'pending',
    ]);

    $nullableRecord = $pdo->query(
        'SELECT * FROM audit_logs WHERE id = 2'
    )->fetch();

    $assert(
        is_array($nullableRecord)
            && $nullableRecord['user_id'] === null
            && $nullableRecord['details'] === null
            && $nullableRecord['ip_address'] === null,
        'Optional audit fields accept null values'
    );

    // TEST 3: NESTED SENSITIVE DATA REDACTION

    $logger->log([
        'action' => 'security.redaction_test',
        'status' => 'success',
        'details' => [
            'username' => 'audit_test_user',
            'password' => 'must-not-be-stored',
            'api_key' => 'must-not-be-stored',
            'nested' => [
                'token' => 'must-not-be-stored',
            ],
        ],
    ]);

    $redactedRecord = $pdo->query(
        'SELECT details FROM audit_logs WHERE id = 3'
    )->fetch();

    $redactedDetails = json_decode(
        $redactedRecord['details'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $assert(
        $redactedDetails['username'] === 'audit_test_user',
        'Non-sensitive nested details remain available'
    );

    $assert(
        $redactedDetails['password'] === '[REDACTED]'
            && $redactedDetails['api_key'] === '[REDACTED]'
            && $redactedDetails['nested']['token'] === '[REDACTED]',
        'Known sensitive values are redacted recursively'
    );

    // TEST 4: EXTENDED SENSITIVE KEY REDACTION

    $logger->log([
        'action' => 'security.extended_redaction_test',
        'status' => 'success',
        'details' => [
            'password_hash' => 'must-not-be-stored',
            'client_secret' => 'must-not-be-stored',
            'session_id' => 'must-not-be-stored',
            'authorization_header' => 'must-not-be-stored',
            'privateKey' => 'must-not-be-stored',
        ],
    ]);

    $extendedRecord = $pdo->query(
        'SELECT details FROM audit_logs WHERE id = 4'
    )->fetch();

    $extendedDetails = json_decode(
        $extendedRecord['details'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $assert(
        count(array_filter(
            $extendedDetails,
            static fn ($value): bool => $value === '[REDACTED]'
        )) === 5,
        'Extended sensitive key variants are redacted'
    );

    // TEST 5: INPUT VALIDATION

    $assertThrows(
        static fn () => $logger->log([
            'action' => '',
            'status' => 'success',
        ]),
        InvalidArgumentException::class,
        'Empty action is rejected'
    );

    $assertThrows(
        static fn () => $logger->log([
            'action' => '   ',
            'status' => 'success',
        ]),
        InvalidArgumentException::class,
        'Whitespace-only action is rejected'
    );

    $assertThrows(
        static fn () => $logger->log([
            'action' => "invalid\n action",
            'status' => 'success',
        ]),
        InvalidArgumentException::class,
        'Action containing control characters is rejected'
    );

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.invalid_status',
            'status' => 'unknown',
        ]),
        InvalidArgumentException::class,
        'Unsupported status is rejected'
    );

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.invalid_user',
            'status' => 'success',
            'user_id' => 0,
        ]),
        InvalidArgumentException::class,
        'Invalid user ID is rejected'
    );

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.invalid_resource',
            'status' => 'success',
            'resource_type' => str_repeat('a', 101),
        ]),
        InvalidArgumentException::class,
        'Resource type exceeding 100 bytes is rejected'
    );

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.invalid_ip',
            'status' => 'success',
            'ip_address' => 'not-an-ip',
        ]),
        InvalidArgumentException::class,
        'Invalid IP address is rejected'
    );

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.invalid_details',
            'status' => 'success',
            'details' => 'not-an-array',
        ]),
        InvalidArgumentException::class,
        'Non-array details are rejected'
    );

    // TEST 6: AUDIT DETAILS PAYLOAD LIMITS

    // TEST 6A: MAXIMUM NESTING DEPTH

    $deepDetails = ['value' => 'test'];

    for ($i = 0; $i < 9; $i++) {
        $deepDetails = ['nested' => $deepDetails];
    }

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.details_depth_limit',
            'status' => 'success',
            'details' => $deepDetails,
        ]),
        InvalidArgumentException::class,
        'Details exceeding maximum nesting depth are rejected'
    );

    // TEST 6B: MAXIMUM TOTAL ELEMENTS

    $tooManyItems = [];

    for ($i = 0; $i < 201; $i++) {
        $tooManyItems['item_' . $i] = 'value';
    }

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.details_item_limit',
            'status' => 'success',
            'details' => $tooManyItems,
        ]),
        InvalidArgumentException::class,
        'Details exceeding maximum element count are rejected'
    );

    // TEST 6C: MAXIMUM STRING VALUE SIZE

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.details_string_limit',
            'status' => 'success',
            'details' => [
                'payload' => str_repeat('a', 4097),
            ],
        ]),
        InvalidArgumentException::class,
        'Details string exceeding 4096 bytes is rejected'
    );

    // TEST 6D: MAXIMUM ENCODED JSON SIZE

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.details_json_limit',
            'status' => 'success',
            'details' => [
                'payload_a' => str_repeat('a', 4000),
                'payload_b' => str_repeat('b', 4000),
                'payload_c' => str_repeat('c', 4000),
                'payload_d' => str_repeat('d', 4000),
                'payload_e' => str_repeat('e', 4000),
            ],
        ]),
        InvalidArgumentException::class,
        'Encoded details exceeding 16384 bytes are rejected'
    );

    // TEST 6: UNSUPPORTED DATA TYPES

    $unsupportedObject = new stdClass();
    $unsupportedObject->password = 'must-not-be-stored';

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.object_rejection',
            'status' => 'success',
            'details' => [
                'payload' => $unsupportedObject,
            ],
        ]),
        InvalidArgumentException::class,
        'Object values inside details are rejected'
    );

    $unsupportedResource = fopen('php://memory', 'r');

    if ($unsupportedResource === false) {
        throw new RuntimeException(
            'Unable to create temporary test resource.'
        );
    }

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.resource_rejection',
            'status' => 'success',
            'details' => [
                'payload' => $unsupportedResource,
            ],
        ]),
        InvalidArgumentException::class,
        'Resource values inside details are rejected'
    );

    fclose($unsupportedResource);

    // TEST 7: JSON ENCODING FAILURE

    // TEST 6: JSON ENCODING FAILURE

    $assertThrows(
        static fn () => $logger->log([
            'action' => 'test.invalid_utf8',
            'status' => 'success',
            'details' => [
                'invalid' => "\xB1\x31",
            ],
        ]),
        RuntimeException::class,
        'Invalid UTF-8 details fail safely'
    );

} catch (Throwable $e) {
    $testError = $e->getMessage();

} finally {
    // DROP ONLY TABLES CREATED BY THIS TEST

    if ($pdo instanceof PDO) {
        if ($auditTableCreated) {
            try {
                $pdo->exec('DROP TABLE audit_logs');
            } catch (Throwable $e) {
                $cleanupError = 'Unable to remove test audit_logs table.';
            }
        }

        if ($usersTableCreated) {
            try {
                $pdo->exec('DROP TABLE users');
            } catch (Throwable $e) {
                $cleanupError = 'Unable to remove test users table.';
            }
        }
    }

    $pdo = null;
}

// DISPLAY RESULTS

foreach ($results as $result) {
    echo $result . PHP_EOL;
}

if ($testError !== null) {
    fwrite(STDERR, 'TEST ERROR: ' . $testError . PHP_EOL);
}

if ($cleanupError !== null) {
    fwrite(STDERR, 'CLEANUP ERROR: ' . $cleanupError . PHP_EOL);
}

if ($testError !== null || $cleanupError !== null) {
    exit(1);
}

echo PHP_EOL . 'ALL AUDIT LOGGER TESTS PASSED.' . PHP_EOL;
