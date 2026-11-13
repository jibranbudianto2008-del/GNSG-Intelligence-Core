<?php

declare(strict_types=1);


define('GNSG_ROOT', dirname(__DIR__));
define('GNSG_APP', GNSG_ROOT . '/app');
define('GNSG_CONFIG', GNSG_ROOT . '/config');
define('GNSG_STORAGE', GNSG_ROOT . '/storage');

date_default_timezone_set('Asia/Jakarta');

error_reporting(E_ALL);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set(
    'error_log',
    GNSG_STORAGE . '/logs/php-error.log'
);


if (!is_dir(GNSG_STORAGE . '/logs')) {
    throw new RuntimeException(
        'Application log directory is unavailable.'
    );
}

if (!is_writable(GNSG_STORAGE . '/logs')) {
    throw new RuntimeException(
        'Application log directory is not writable.'
    );
}

require_once GNSG_APP . '/Database/Connection.php';

$database = \GNSG\Database\Connection::getInstance();

return [
    'name' => 'GNSG Intelligence Core',
    'environment' => 'production',
    'timezone' => 'Asia/Jakarta',
    'root' => GNSG_ROOT,
    'database' => $database, 
];
