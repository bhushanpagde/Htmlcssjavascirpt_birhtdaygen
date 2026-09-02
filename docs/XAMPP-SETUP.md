# XAMPP Development Setup

These commands assume XAMPP is installed at `D:\xampp` and this repository is at `D:\xampp\htdocs\hrcanvas`.

## Update the migration branch

```powershell
Set-Location D:\xampp\htdocs\hrcanvas
git switch xampp-migration
git pull --ff-only origin xampp-migration
```

## Create/update the database schema

Start Apache and MySQL in the XAMPP Control Panel, then run:

```powershell
Get-Content D:\xampp\htdocs\hrcanvas\database\schema.sql -Raw |
    D:\xampp\mysql\bin\mysql.exe -u root
```

If the MariaDB root account has a password, use `-p` and enter it when prompted:

```powershell
Get-Content D:\xampp\htdocs\hrcanvas\database\schema.sql -Raw |
    D:\xampp\mysql\bin\mysql.exe -u root -p
```

Do not put a real password in a Git command, committed file, screenshot, or chat message.

## Local database override (only if needed)

The default development connection is `127.0.0.1:3306`, database `hrcanvas`, user `root`, with an empty password. To override it:

```powershell
Copy-Item config\database.local.example.php config\database.local.php
```

Edit `config\database.local.php`. Git ignores this local credentials file.

## Test the server

Open these URLs:

```text
http://localhost/hrcanvas/
http://localhost/hrcanvas/api/health.php
http://localhost/hrcanvas/api/employees.php
```

The health response should contain `"ok":true`, `"service":"HR Canvas API"`, and `"database":"hrcanvas"`.

## Test the employee API

Create a temporary test employee:

```powershell
$body = @{
    id = 'TEST001'
    fullName = 'API Test Employee'
    location = 'Test'
    designation = 'Tester'
    email = 'test@example.invalid'
    dob = '2000-01-01'
    doj = '2026-01-01'
} | ConvertTo-Json

Invoke-RestMethod `
    -Uri 'http://localhost/hrcanvas/api/employees.php' `
    -Method Post `
    -ContentType 'application/json' `
    -Body $body
```

Read the employee:

```powershell
Invoke-RestMethod 'http://localhost/hrcanvas/api/employees.php?id=TEST001'
```

Delete the temporary test employee:

```powershell
Invoke-RestMethod `
    -Uri 'http://localhost/hrcanvas/api/employees.php?id=TEST001' `
    -Method Delete
```

The frontend still uses IndexedDB during this foundation phase. It will be switched to these APIs only after the server endpoints pass their tests.
