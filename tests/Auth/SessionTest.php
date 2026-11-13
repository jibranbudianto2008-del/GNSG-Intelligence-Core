<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Auth/Session.php';

use GNSG\Auth\Session;

$testDir = sys_get_temp_dir()
    . '/gnsg-session-test-' . bin2hex(random_bytes(4));

if (!mkdir($testDir, 0700)) {
    throw new RuntimeException('Unable to create test directory.');
}

$results = [];
$testError = null;
$cleanupError = null;

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

    if (ini_set('session.save_path', $testDir) === false) {
        throw new RuntimeException(
            'Unable to configure temporary session path.'
        );
    }

    if (ini_set('session.use_cookies', '0') === false) {
        throw new RuntimeException(
            'Unable to disable session cookies.'
        );
    }

    if (ini_set('session.use_trans_sid', '0') === false) {
        throw new RuntimeException(
            'Unable to disable session trans sid.'
        );
    }

    $assert(
        session_save_path() === $testDir,
        'Temporary session path configured'
    );

    $assert(
        realpath(session_save_path())
            !== realpath('/var/lib/php/sessions'),
        'Production session directory is not used'
    );

    $assert(
        session_status() === PHP_SESSION_NONE,
        'No existing session is active'
    );

    // TEST 1: Session initialization.

    Session::start();

    $initialId = session_id();

    $assert(
        $initialId !== '',
        'Session starts successfully'
    );

    $assert(
        is_int($_SESSION['_created_at'] ?? null),
        'Session creation timestamp initialized'
    );

    $assert(
        is_int($_SESSION['_last_activity'] ?? null),
        'Session activity timestamp initialized'
    );

    // Verify that session data is stored in test directory.

    session_write_close();

    $sessionFiles = glob($testDir . '/sess_*') ?: [];

    $assert(
        count($sessionFiles) > 0,
        'Session file stored in temporary directory'
    );

    session_id($initialId);

    Session::start();

    // TEST 2: Idle activity timestamp.

    $_SESSION['_last_activity'] = time() - 60;

    $oldActivity = $_SESSION['_last_activity'];

    session_write_close();
    session_id($initialId);

    Session::start();

    $newActivity = $_SESSION['_last_activity'];

    $assert(
        $newActivity > $oldActivity,
        'Idle activity timestamp updates'
    );

    // TEST 3: Idle timeout.

    $_SESSION['auth_user'] = ['id' => 999];
    $_SESSION['_last_activity'] = time() - 1801;

    $expiredIdleId = session_id();

    session_write_close();
    session_id($expiredIdleId);

    Session::start();

    $assert(
        session_id() !== $expiredIdleId,
        'Idle timeout regenerates session ID'
    );

    $assert(
        !isset($_SESSION['auth_user']),
        'Expired idle session clears authenticated identity'
    );

    // TEST 4: Absolute timeout.

    $_SESSION['auth_user'] = ['id' => 999];
    $_SESSION['_created_at'] = time() - 43201;
    $_SESSION['_last_activity'] = time();

    $expiredAbsoluteId = session_id();

    session_write_close();
    session_id($expiredAbsoluteId);

    Session::start();

    $assert(
        session_id() !== $expiredAbsoluteId,
        'Absolute timeout regenerates session ID'
    );

    $assert(
        !isset($_SESSION['auth_user']),
        'Absolute timeout clears authenticated identity'
    );

    // TEST 5: Manual session ID regeneration.

    $beforeRegeneration = session_id();

    Session::regenerateId();

    $assert(
        session_id() !== $beforeRegeneration,
        'Manual session ID regeneration'
    );

    // TEST 6: Session destruction.

    Session::destroy();

    $assert(
        session_status() === PHP_SESSION_NONE,
        'Session destruction'
    );

    $remainingSessionFiles = glob($testDir . '/sess_*') ?: [];

    $assert(
        count($remainingSessionFiles) === 0,
        'Session files removed after destruction'
    );

} catch (Throwable $e) {
    $testError = $e->getMessage();

} finally {

    // Destroy any remaining active session.

    if (session_status() === PHP_SESSION_ACTIVE) {
        try {
            Session::destroy();
        } catch (Throwable $e) {
            $cleanupError = $e->getMessage();
        }
    }

    // Remove temporary session files.

    foreach (glob($testDir . '/*') ?: [] as $file) {
        if (is_file($file) || is_link($file)) {
            if (!unlink($file)) {
                $cleanupError = 'Unable to remove temporary file.';
            }
        }
    }

    // Remove temporary directory.

    if (is_dir($testDir) && !rmdir($testDir)) {
        $cleanupError = 'Unable to remove temporary directory.';
    }
}

// Display results.

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

echo PHP_EOL . 'ALL SESSION TESTS PASSED.' . PHP_EOL;
