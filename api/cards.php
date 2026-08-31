<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$employeeId = trim((string) ($_GET['employeeId'] ?? $_POST['employeeId'] ?? ''));
$connection = database();

if ($method === 'GET') {
    $sql = 'SELECT c.employee_id AS employeeId, e.full_name AS fullName, c.template_number AS templateNumber,
                   c.file_name AS fileName, c.relative_path AS relativePath, c.created_at AS createdAt
            FROM birthday_cards c JOIN employees e ON e.id = c.employee_id';
    $parameters = [];
    if ($employeeId !== '') {
        $sql .= ' WHERE c.employee_id = ?';
        $parameters[] = $employeeId;
    }
    $sql .= ' ORDER BY c.created_at DESC';
    $statement = $connection->prepare($sql);
    $statement->execute($parameters);
    $cards = $statement->fetchAll();
    foreach ($cards as &$card) {
        $card['templateNumber'] = (int) $card['templateNumber'];
        $card['url'] = publicUrl($card['relativePath']);
    }
    if ($employeeId !== '' && !$cards) {
        fail('Birthday card not found.', 404);
    }
    respond(['ok' => true, 'cards' => $cards, 'count' => count($cards)]);
}

if ($method === 'POST') {
    if ($employeeId === '') {
        fail('employeeId is required.', 422);
    }
    $employee = requireEmployee($connection, $employeeId);
    $templateNumber = filter_var($_POST['templateNumber'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
    if ($templateNumber === false) {
        fail('templateNumber must be a positive integer.', 422);
    }
    $upload = uploadedFile('card', ['image/jpeg' => 'jpg'], 15 * 1024 * 1024);
    $namePart = readableFilePart($employee['fullName'], 'Birthday Card');
    $storedName = $namePart . '.jpg';
    $relativePath = 'storage/birthday-cards/' . $storedName;
    $collision = $connection->prepare('SELECT 1 FROM birthday_cards WHERE relative_path = ? AND employee_id <> ? LIMIT 1');
    $collision->execute([$relativePath, $employeeId]);
    if ($collision->fetchColumn()) {
        $storedName = sprintf('%s (%s).jpg', $namePart, safeFilePart($employeeId, 'employee'));
        $relativePath = 'storage/birthday-cards/' . $storedName;
    }
    $destination = storageDirectory('birthday-cards') . '/' . $storedName;
    if (!move_uploaded_file($upload['file']['tmp_name'], $destination)) {
        throw new RuntimeException('The server could not save the birthday card.');
    }

    $oldPath = null;
    try {
        $connection->beginTransaction();
        $existing = $connection->prepare('SELECT relative_path FROM birthday_cards WHERE employee_id = ? FOR UPDATE');
        $existing->execute([$employeeId]);
        $oldPath = $existing->fetchColumn() ?: null;
        $statement = $connection->prepare(
            'INSERT INTO birthday_cards (employee_id, template_number, file_name, relative_path)
             VALUES (:employee_id, :template_number, :file_name, :relative_path)
             ON DUPLICATE KEY UPDATE template_number = VALUES(template_number), file_name = VALUES(file_name),
                 relative_path = VALUES(relative_path), created_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'employee_id' => $employeeId,
            'template_number' => $templateNumber,
            'file_name' => $storedName,
            'relative_path' => $relativePath,
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
    respond(['ok' => true, 'employeeId' => $employeeId, 'templateNumber' => $templateNumber, 'fileName' => $storedName, 'relativePath' => $relativePath, 'url' => publicUrl($relativePath)], 201);
}

if ($method === 'DELETE') {
    if ($employeeId === '') {
        fail('employeeId is required.', 422);
    }
    $statement = $connection->prepare('SELECT relative_path FROM birthday_cards WHERE employee_id = ?');
    $statement->execute([$employeeId]);
    $relativePath = $statement->fetchColumn();
    if (!$relativePath) {
        fail('Birthday card not found.', 404);
    }
    $delete = $connection->prepare('DELETE FROM birthday_cards WHERE employee_id = ?');
    $delete->execute([$employeeId]);
    deleteStoredFile($relativePath);
    respond(['ok' => true, 'employeeId' => $employeeId]);
}

header('Allow: GET, POST, DELETE');
fail('Method not allowed.', 405);
