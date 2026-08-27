<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$statement = database()->query('SELECT DATABASE() AS database_name, VERSION() AS database_version');
$databaseStatus = $statement->fetch();

respond([
    'ok' => true,
    'service' => 'HR Canvas API',
    'phpVersion' => PHP_VERSION,
    'database' => $databaseStatus['database_name'],
    'databaseVersion' => $databaseStatus['database_version'],
]);

