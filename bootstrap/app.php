<?php

declare(strict_types=1);


if (!defined('GNSG_ROOT')) {
    define('GNSG_ROOT', dirname(__DIR__));
}

if (!defined('GNSG_APP')) {
    define('GNSG_APP', GNSG_ROOT . '/app');
}

if (!defined('GNSG_CONFIG')) {
    define('GNSG_CONFIG', GNSG_ROOT . '/config');
}

if (!defined('GNSG_STORAGE')) {
    define('GNSG_STORAGE', GNSG_ROOT . '/storage');
}

date_default_timezone_set('Asia/Jakarta');

error_reporting(E_ALL);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$logDirectory = GNSG_STORAGE . '/logs';
$logFile = $logDirectory . '/php-error.log';

if (
    !is_dir($logDirectory)
    || !is_writable($logDirectory)
) {
    throw new RuntimeException(
        'Application log directory is unavailable.'
    );
}

ini_set('error_log', $logFile);

/*
 * Lightweight PSR-4-style autoloader.
 * Namespace: GNSG\
 * Directory: app/
 */
spl_autoload_register(
    static function (string $class): void {
        $prefix = 'GNSG\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr(
            $class,
            strlen($prefix)
        );

        if (
            preg_match(
                '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/',
                $relativeClass
            ) !== 1
        ) {
            return;
        }

        $file = GNSG_APP
            . '/'
            . str_replace('\\', '/', $relativeClass)
            . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
);

return [
    'name' => 'GNSG Intelligence Core',
    'environment' => 'production',
    'timezone' => 'Asia/Jakarta',
    'root' => GNSG_ROOT,
    'app' => GNSG_APP,
    'config' => GNSG_CONFIG,
    'storage' => GNSG_STORAGE,
];
