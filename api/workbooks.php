<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$connection = database();

if ($method === 'GET') {
    $statement = $connection->query(
        "SELECT id, original_name AS originalName, stored_name AS storedName,
                relative_path AS relativePath, mime_type AS mimeType, file_size AS fileSize,
                saved_at AS savedAt
         FROM files WHERE file_type = 'workbook' ORDER BY saved_at DESC, id DESC"
    );
    $workbooks = $statement->fetchAll();
    foreach ($workbooks as &$workbook) {
        $workbook['id'] = (int) $workbook['id'];
        $workbook['fileSize'] = (int) $workbook['fileSize'];
        $workbook['url'] = publicUrl($workbook['relativePath']);
    }
    respond(['ok' => true, 'workbooks' => $workbooks, 'count' => count($workbooks)]);
}

if ($method === 'DELETE') {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        fail('A valid workbook id is required.', 422);
    }
    $statement = $connection->prepare("SELECT relative_path FROM files WHERE id = ? AND file_type = 'workbook'");
    $statement->execute([$id]);
    $relativePath = $statement->fetchColumn();
    if (!$relativePath) {
        fail('Workbook not found.', 404);
    }
    $delete = $connection->prepare("DELETE FROM files WHERE id = ? AND file_type = 'workbook'");
    $delete->execute([$id]);
    deleteStoredFile($relativePath);
    respond(['ok' => true, 'id' => $id]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST, DELETE');
    fail('Method not allowed.', 405);
}

$originalName = basename((string) ($_FILES['workbook']['name'] ?? ''));
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xlsm'], true)) {
    fail('Only XLSX and XLSM workbooks are allowed.', 415);
}

$upload = uploadedFile('workbook', [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => $extension,
    'application/vnd.ms-excel.sheet.macroenabled.12' => $extension,
    'application/zip' => $extension,
    'application/octet-stream' => $extension,
], 20 * 1024 * 1024);

try {
    $employees = json_decode((string) ($_POST['employees'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    fail('employees contains invalid JSON.', 422);
}
if (!is_array($employees) || count($employees) > 10000) {
    fail('employees must be an array containing at most 10,000 records.', 422);
}

$storedName = sprintf('Employees_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $extension);
$relativePath = 'storage/workbooks/' . $storedName;
$destination = storageDirectory('workbooks') . '/' . $storedName;
if (!move_uploaded_file($upload['file']['tmp_name'], $destination)) {
    throw new RuntimeException('The server could not save the workbook.');
}

$inserted = 0;
$skipped = 0;
try {
    $connection->beginTransaction();
    $fileStatement = $connection->prepare(
        'INSERT INTO files (original_name, stored_name, file_type, relative_path, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $fileStatement->execute([$originalName, $storedName, 'workbook', $relativePath, $upload['mimeType'], $upload['size']]);
    $fileId = (int) $connection->lastInsertId();
    $employeeStatement = $connection->prepare(
        'INSERT IGNORE INTO employees (id, full_name, location, designation, email, dob, doj) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($employees as $employee) {
        if (!is_array($employee)) {
            $skipped++;
            continue;
        }
        $id = trim((string) ($employee['id'] ?? ''));
        $fullName = trim((string) ($employee['fullName'] ?? ''));
        if ($id === '' || $fullName === '' || mb_strlen($id) > 100 || mb_strlen($fullName) > 255) {
            $skipped++;
            continue;
        }
        $employeeStatement->execute([
            $id,
            $fullName,
            substr(trim((string) ($employee['location'] ?? '')), 0, 255),
            substr(trim((string) ($employee['designation'] ?? '')), 0, 255),
            substr(trim((string) ($employee['email'] ?? '')), 0, 320),
            substr(trim((string) ($employee['dob'] ?? '')), 0, 32),
            substr(trim((string) ($employee['doj'] ?? '')), 0, 32),
        ]);
        if ($employeeStatement->rowCount() === 1) {
            $inserted++;
        } else {
            $skipped++;
        }
    }
    $connection->commit();
} catch (Throwable $error) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    deleteStoredFile($relativePath);
    throw $error;
}

exportEmployeesJson($connection);

respond([
    'ok' => true,
    'id' => $fileId,
    'fileName' => $storedName,
    'relativePath' => $relativePath,
    'url' => publicUrl($relativePath),
    'inserted' => $inserted,
    'skipped' => $skipped,
], 201);
