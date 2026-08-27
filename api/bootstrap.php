<?php
declare(strict_types=1);

const HRCANVAS_ROOT = __DIR__ . '/..';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400, array $details = []): never
{
    respond(['ok' => false, 'error' => ['message' => $message] + $details], $status);
}

function database(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = require HRCANVAS_ROOT . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );
    $connection = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $connection;
}

function requestBody(): array
{
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        try {
            $value = json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            fail('The request contains invalid JSON.');
        }
        if (!is_array($value)) {
            fail('The JSON body must be an object.');
        }
        return $value;
    }
    return $_POST;
}

function requireText(array $input, string $key, int $maximum = 255): string
{
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        fail("$key is required.", 422);
    }
    if (mb_strlen($value) > $maximum) {
        fail("$key exceeds the maximum length of $maximum characters.", 422);
    }
    return $value;
}

function optionalText(array $input, string $key, int $maximum = 255): string
{
    $value = trim((string) ($input[$key] ?? ''));
    if (mb_strlen($value) > $maximum) {
        fail("$key exceeds the maximum length of $maximum characters.", 422);
    }
    return $value;
}

set_exception_handler(static function (Throwable $error): never {
    error_log($error->__toString());
    fail('The server could not complete the request.', 500);
});

