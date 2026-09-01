@echo off
setlocal

set "SOURCE=%~dp0data\employees.json"
set "ONEDRIVE_ROOT=%OneDriveCommercial%"
if not defined ONEDRIVE_ROOT set "ONEDRIVE_ROOT=%OneDrive%"

if not defined ONEDRIVE_ROOT (
    echo ERROR: OneDrive folder could not be detected.
    exit /b 1
)

if not exist "%SOURCE%" (
    echo ERROR: Employee JSON was not found: "%SOURCE%"
    exit /b 1
)

set "OUTPUT=%ONEDRIVE_ROOT%\hrcanvas-server-output"
if not exist "%OUTPUT%" mkdir "%OUTPUT%"
if errorlevel 1 (
    echo ERROR: Could not create: "%OUTPUT%"
    exit /b 1
)

copy /Y "%SOURCE%" "%OUTPUT%\employees.json" >nul
if errorlevel 1 (
    echo ERROR: Could not update "%OUTPUT%\employees.json"
    exit /b 1
)

echo Updated "%OUTPUT%\employees.json"
exit /b 0
