<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = trim((string) ($_GET['id'] ?? ''));

if ($method === 'GET') {
    if ($id !== '') {
        $statement = database()->prepare(
            'SELECT e.id, e.full_name AS fullName, e.location, e.email, e.dob, e.doj,
                    (p.employee_id IS NOT NULL) AS photo,
                    (c.employee_id IS NOT NULL) AS birthdayCard,
                    p.relative_path AS photoPath, c.relative_path AS birthdayCardPath,
                    e.created_at AS createdAt, e.updated_at AS updatedAt
             FROM employees e
             LEFT JOIN photos p ON p.employee_id = e.id
             LEFT JOIN birthday_cards c ON c.employee_id = e.id
             WHERE e.id = ?'
        );
        $statement->execute([$id]);
        $employee = $statement->fetch();
        if (!$employee) {
            fail('Employee not found.', 404);
        }
        $employee['photo'] = (bool) $employee['photo'];
        $employee['birthdayCard'] = (bool) $employee['birthdayCard'];
        respond(['ok' => true, 'employee' => $employee]);
    }

    $statement = database()->query(
        'SELECT e.id, e.full_name AS fullName, e.location, e.email, e.dob, e.doj,
                (p.employee_id IS NOT NULL) AS photo,
                (c.employee_id IS NOT NULL) AS birthdayCard,
                p.relative_path AS photoPath, c.relative_path AS birthdayCardPath,
                e.created_at AS createdAt, e.updated_at AS updatedAt
         FROM employees e
         LEFT JOIN photos p ON p.employee_id = e.id
         LEFT JOIN birthday_cards c ON c.employee_id = e.id
         ORDER BY e.full_name, e.id'
    );
    $employees = $statement->fetchAll();
    foreach ($employees as &$employee) {
        $employee['photo'] = (bool) $employee['photo'];
        $employee['birthdayCard'] = (bool) $employee['birthdayCard'];
    }
    respond(['ok' => true, 'employees' => $employees, 'count' => count($employees)]);
}

if ($method === 'POST') {
    $input = requestBody();
    $employeeId = requireText($input, 'id', 100);
    $statement = database()->prepare(
        'INSERT INTO employees (id, full_name, location, email, dob, doj)
         VALUES (:id, :full_name, :location, :email, :dob, :doj)'
    );
    try {
        $statement->execute([
            'id' => $employeeId,
            'full_name' => requireText($input, 'fullName'),
            'location' => optionalText($input, 'location'),
            'email' => optionalText($input, 'email', 320),
            'dob' => optionalText($input, 'dob', 32),
            'doj' => optionalText($input, 'doj', 32),
        ]);
    } catch (PDOException $error) {
        if ((string) $error->getCode() === '23000') {
            fail('An employee with this ID already exists.', 409);
        }
        throw $error;
    }
    exportEmployeesJson(database());
    respond(['ok' => true, 'id' => $employeeId], 201);
}

if ($method === 'PUT') {
    if ($id === '') {
        fail('Employee id is required in the query string.', 422);
    }
    $input = requestBody();
    $statement = database()->prepare(
        'UPDATE employees
         SET full_name = :full_name, location = :location, email = :email, dob = :dob, doj = :doj
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $id,
        'full_name' => requireText($input, 'fullName'),
        'location' => optionalText($input, 'location'),
        'email' => optionalText($input, 'email', 320),
        'dob' => optionalText($input, 'dob', 32),
        'doj' => optionalText($input, 'doj', 32),
    ]);
    if ($statement->rowCount() === 0) {
        $exists = database()->prepare('SELECT 1 FROM employees WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            fail('Employee not found.', 404);
        }
    }
    exportEmployeesJson(database());
    respond(['ok' => true, 'id' => $id]);
}

if ($method === 'DELETE') {
    if ($id === '') {
        fail('Employee id is required in the query string.', 422);
    }
    $paths = database()->prepare(
        'SELECT relative_path FROM photos WHERE employee_id = ?
         UNION ALL SELECT relative_path FROM birthday_cards WHERE employee_id = ?
         UNION ALL SELECT pdf_path FROM certificates WHERE employee_id = ?
         UNION ALL SELECT thumbnail_path FROM certificates WHERE employee_id = ? AND thumbnail_path IS NOT NULL'
    );
    $paths->execute([$id, $id, $id, $id]);
    $storedPaths = $paths->fetchAll(PDO::FETCH_COLUMN);
    $statement = database()->prepare('DELETE FROM employees WHERE id = ?');
    $statement->execute([$id]);
    if ($statement->rowCount() === 0) {
        fail('Employee not found.', 404);
    }
    foreach ($storedPaths as $storedPath) {
        deleteStoredFile($storedPath);
    }
    exportEmployeesJson(database());
    respond(['ok' => true, 'id' => $id]);
}

header('Allow: GET, POST, PUT, DELETE');
fail('Method not allowed.', 405);
