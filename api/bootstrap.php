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

function requireEmployee(PDO $connection, string $employeeId): array
{
    $statement = $connection->prepare('SELECT id, full_name AS fullName FROM employees WHERE id = ?');
    $statement->execute([$employeeId]);
    $employee = $statement->fetch();
    if (!$employee) {
        fail('Employee not found.', 404);
    }
    return $employee;
}

function safeFilePart(string $value, string $fallback = 'file'): string
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value)) ?? '';
    $value = trim($value, '._-');
    return $value !== '' ? substr($value, 0, 100) : $fallback;
}

function readableFilePart(string $value, string $fallback = 'file'): string
{
    $value = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/u', '', trim($value)) ?? '';
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    $value = trim($value, " .\t\n\r\0\x0B");
    return $value !== '' ? mb_substr($value, 0, 150) : $fallback;
}

function storageDirectory(string $name): string
{
    if (!preg_match('/^[a-z0-9-]+$/', $name)) {
        throw new InvalidArgumentException('Invalid storage directory.');
    }
    $directory = HRCANVAS_ROOT . '/storage/' . $name;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Could not create storage/$name.");
    }
    if (!is_writable($directory)) {
        throw new RuntimeException("storage/$name is not writable.");
    }
    return $directory;
}

function uploadedFile(string $field, array $allowedMimeTypes, int $maximumBytes): array
{
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['size'])) {
        fail("Upload field '$field' is required.", 422);
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        fail('The file upload failed.', 422, ['uploadError' => (int) $file['error']]);
    }
    $size = (int) $file['size'];
    if ($size < 1 || $size > $maximumBytes) {
        fail('The uploaded file size is not allowed.', 422, ['maximumBytes' => $maximumBytes]);
    }
    if (!is_uploaded_file((string) $file['tmp_name'])) {
        fail('The uploaded file is invalid.', 422);
    }
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if (!is_string($mimeType) || !isset($allowedMimeTypes[$mimeType])) {
        fail('The uploaded file type is not allowed.', 415);
    }
    return ['file' => $file, 'mimeType' => $mimeType, 'extension' => $allowedMimeTypes[$mimeType], 'size' => $size];
}

function publicUrl(string $relativePath): string
{
    $applicationPath = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')));
    return rtrim($applicationPath, '/') . '/' . ltrim($relativePath, '/');
}

function deleteStoredFile(?string $relativePath): void
{
    if (!$relativePath || !str_starts_with(str_replace('\\', '/', $relativePath), 'storage/')) {
        return;
    }
    $path = HRCANVAS_ROOT . '/' . str_replace('\\', '/', $relativePath);
    if (is_file($path) && !unlink($path)) {
        error_log("Could not delete stored file: $path");
    }
}

set_exception_handler(static function (Throwable $error): never {
    error_log($error->__toString());
    fail('The server could not complete the request.', 500);
});
