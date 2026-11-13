<?php

declare(strict_types=1);

$envFile = '/etc/gnsg/gnsg-core.env';

if (!is_file($envFile) || !is_readable($envFile)) {
    throw new RuntimeException(
        'Database configuration is unavailable.'
    );
}

$config = [];

$lines = file($envFile, FILE_IGNORE_NEW_LINES);

if ($lines === false) {
    throw new RuntimeException(
        'Database configuration could not be read.'
    );
}

foreach ($lines as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    $parts = explode('=', $line, 2);

    if (count($parts) !== 2) {
        throw new RuntimeException(
            'Invalid database configuration format.'
        );
    }

    $key = trim($parts[0]);
    $value = trim($parts[1]);

    if (array_key_exists($key, $config)) {
        throw new RuntimeException(
            'Duplicate database configuration key.'
        );
    }

    $config[$key] = $value;
}

$requiredKeys = [
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
];

foreach ($requiredKeys as $key) {
    if (!isset($config[$key]) || $config[$key] === '') {
        throw new RuntimeException(
            'Required database configuration is missing.'
        );
    }
}

if (
    $config['DB_HOST'] !== 'localhost' ||
    $config['DB_NAME'] !== 'ganeshasistemgemilang' ||
    $config['DB_USER'] !== 'gnsg_core_runtime'
) {
    throw new RuntimeException(
        'Database configuration identity is invalid.'
    );
}

return [
    'host' => $config['DB_HOST'],
    'name' => $config['DB_NAME'],
    'username' => $config['DB_USER'],
    'password' => $config['DB_PASSWORD'],
    'charset' => 'utf8mb4',
];
