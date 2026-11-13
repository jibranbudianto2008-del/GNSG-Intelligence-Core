<?php

declare(strict_types=1);

use GNSG\Auth\Session;
use GNSG\Security\CsrfProtection;

require dirname(__DIR__, 2) . '/app/Auth/Session.php';
require dirname(__DIR__, 2) . '/app/Security/CsrfProtection.php';

$testDirectory = sys_get_temp_dir()
    . '/gnsg-csrf-test-'
    . bin2hex(random_bytes(4));

if (!mkdir($testDirectory, 0700)) {
    throw new RuntimeException('Unable to create temporary test directory.');
}

ini_set('session.save_path', $testDirectory);
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cache_limiter', '');

$passed = 0;
$failed = 0;

$assert = static function (bool $condition, string $message) use (
    &$passed,
    &$failed
): void {
    if (!$condition) {
        $failed++;
        echo "FAIL: {$message}\n";
        return;
    }

    $passed++;
    echo "PASS: {$message}\n";
};

try {
    Session::start();

    $token = CsrfProtection::getToken();

    $assert(
        preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1,
        'Generated token has 256-bit hexadecimal format.'
    );

    $assert(
        CsrfProtection::getToken() === $token,
        'Existing token remains stable within the session.'
    );

    $assert(
        CsrfProtection::validate($token),
        'Correct token is accepted.'
    );

    $assert(
        !CsrfProtection::validate(str_repeat('0', 64)),
        'Incorrect token is rejected.'
    );

    $assert(
        !CsrfProtection::validate('invalid-token'),
        'Malformed token is rejected.'
    );

    $rotatedToken = CsrfProtection::rotateToken();

    $assert(
        $rotatedToken !== $token,
        'Token rotation generates a different token.'
    );

    $assert(
        !CsrfProtection::validate($token),
        'Previous token is rejected after rotation.'
    );

    $assert(
        CsrfProtection::validate($rotatedToken),
        'Rotated token is accepted.'
    );

    $assert(
        CsrfProtection::getToken() === $rotatedToken,
        'Rotated token remains stable in the session.'
    );

    Session::destroy();

    $assert(
        !CsrfProtection::validate($rotatedToken),
        'Token validation fails without an active session.'
    );
} catch (Throwable $exception) {
    $failed++;
    echo 'FAIL: Unexpected exception: '
        . get_class($exception)
        . "\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        Session::destroy();
    }

    foreach (glob($testDirectory . '/*') ?: [] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    rmdir($testDirectory);
}

echo "\nCSRF protection tests: {$passed} passed, {$failed} failed.\n";

exit($failed === 0 ? 0 : 1);
