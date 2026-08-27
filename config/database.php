<?php
declare(strict_types=1);

$config = [
    'host' => getenv('HRCANVAS_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('HRCANVAS_DB_PORT') ?: '3306',
    'database' => getenv('HRCANVAS_DB_NAME') ?: 'hrcanvas',
    'username' => getenv('HRCANVAS_DB_USER') ?: 'root',
    'password' => getenv('HRCANVAS_DB_PASSWORD') ?: '',
];

$localConfig = __DIR__ . '/database.local.php';
if (is_file($localConfig)) {
    $overrides = require $localConfig;
    if (!is_array($overrides)) {
        throw new RuntimeException('database.local.php must return an array.');
    }
    $config = array_replace($config, $overrides);
}

return $config;

