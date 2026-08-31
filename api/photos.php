<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$employeeId = trim((string) ($_GET['employeeId'] ?? $_POST['employeeId'] ?? ''));
$connection = database();

if ($method === 'GET') {
    $sql = 'SELECT p.employee_id AS employeeId, e.full_name AS fullName, p.file_name AS fileName,
                   p.relative_path AS relativePath, p.mime_type AS mimeType, p.file_size AS fileSize,
                   p.saved_at AS savedAt
            FROM photos p JOIN employees e ON e.id = p.employee_id';
    $parameters = [];
    if ($employeeId !== '') {
        $sql .= ' WHERE p.employee_id = ?';
        $parameters[] = $employeeId;
    }
    $sql .= ' ORDER BY e.full_name, p.employee_id';
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $photos = $statement->fetchAll();
    foreach ($photos as &$photo) {
        $photo['fileSize'] = (int) $photo['fileSize'];
        $photo['url'] = publicUrl($photo['relativePath']);
    }
    if ($employeeId !== '' && !$photos) {
        fail('Photo not found.', 404);
    }
    respond(['ok' => true, 'photos' => $photos, 'count' => count($photos)]);
}

if ($method === 'POST') {
    if ($employeeId === '') {
        fail('employeeId is required.', 422);
    }
    $employee = requireEmployee($connection, $employeeId);
    $upload = uploadedFile('photo', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ], 10 * 1024 * 1024);
    $namePart = readableFilePart($employee['fullName'], 'Photo');
    $storedName = sprintf('%s.%s', $namePart, $upload['extension']);
    $relativePath = 'storage/photos/' . $storedName;
    $collision = $connection->prepare('SELECT 1 FROM photos WHERE relative_path = ? AND employee_id <> ? LIMIT 1');
    $collision->execute([$relativePath, $employeeId]);
    if ($collision->fetchColumn()) {
        $storedName = sprintf('%s (%s).%s', $namePart, safeFilePart($employeeId, 'employee'), $upload['extension']);
        $relativePath = 'storage/photos/' . $storedName;
    }
    $destination = storageDirectory('photos') . '/' . $storedName;
    if (!move_uploaded_file($upload['file']['tmp_name'], $destination)) {
        throw new RuntimeException('The server could not save the uploaded photo.');
    }

    $oldPath = null;
    try {
        $connection->beginTransaction();
        $existing = $connection->prepare('SELECT relative_path FROM photos WHERE employee_id = ? FOR UPDATE');
        $existing->execute([$employeeId]);
        $oldPath = $existing->fetchColumn() ?: null;
        $statement = $connection->prepare(
            'INSERT INTO photos (employee_id, file_name, relative_path, mime_type, file_size)
             VALUES (:employee_id, :file_name, :relative_path, :mime_type, :file_size)
             ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), relative_path = VALUES(relative_path),
                 mime_type = VALUES(mime_type), file_size = VALUES(file_size), saved_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'file_name' => $storedName,
            'relative_path' => $relativePath,
            'mime_type' => $upload['mimeType'],
            'file_size' => $upload['size'],
        ]);
        $connection->commit();
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        deleteStoredFile($relativePath);
        throw $error;
    }
    if ($oldPath && $oldPath !== $relativePath) {
        deleteStoredFile($oldPath);
    }
    respond(['ok' => true, 'employeeId' => $employeeId, 'fileName' => $storedName, 'relativePath' => $relativePath, 'url' => publicUrl($relativePath)], 201);
}

if ($method === 'DELETE') {
    if ($employeeId === '') {
        fail('employeeId is required.', 422);
    }
    $statement = $connection->prepare('SELECT relative_path FROM photos WHERE employee_id = ?');
    $statement->execute([$employeeId]);
    $relativePath = $statement->fetchColumn();
    if (!$relativePath) {
        fail('Photo not found.', 404);
    }
    $delete = $connection->prepare('DELETE FROM photos WHERE employee_id = ?');
    $delete->execute([$employeeId]);
    deleteStoredFile($relativePath);
    respond(['ok' => true, 'employeeId' => $employeeId]);
}

header('Allow: GET, POST, DELETE');
fail('Method not allowed.', 405);
