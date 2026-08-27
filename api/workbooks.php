<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
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

$connection = database();
$inserted = 0;
$skipped = 0;
try {
    $connection->beginTransaction();
    $fileStatement = $connection->prepare(
        'INSERT INTO files (original_name, stored_name, file_type, relative_path, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $fileStatement->execute([$originalName, $storedName, 'workbook', $relativePath, $upload['mimeType'], $upload['size']]);
    $employeeStatement = $connection->prepare(
        'INSERT IGNORE INTO employees (id, full_name, location, email, dob, doj) VALUES (?, ?, ?, ?, ?, ?)'
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

respond([
    'ok' => true,
    'fileName' => $storedName,
    'relativePath' => $relativePath,
    'inserted' => $inserted,
    'skipped' => $skipped,
], 201);

