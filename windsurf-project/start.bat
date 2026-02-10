@echo off
title School Management System
color 0A

echo ========================================
echo    School Management System
echo    Timetable Management System
echo ========================================
echo.

:: Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP is not installed or not in PATH
    echo Please install PHP 8.0 or higher
    echo Download from: https://www.php.net/downloads.php
    pause
    exit /b 1
)

echo [INFO] PHP detected successfully
php --version | findstr "PHP"
echo.

:: Check if install.php exists (first time setup)
if exist "install.php" (
    echo [INFO] First-time setup detected
    echo [INFO] Starting web server for installation...
    echo.
    echo Please open your browser and go to:
    echo http://localhost:8000/install.php
    echo.
    echo Press Ctrl+C to stop the server
    echo ========================================
    echo.
    php -S localhost:8000
) else (
    echo [INFO] Starting School Management System...
    echo.
    echo Please open your browser and go to:
    echo http://localhost:8000
    echo.
    echo Default Login Credentials:
    echo   Admin: admin / password
    echo   Teacher: teacher1 / password
    echo   Student: student1 / password
    echo.
    echo Press Ctrl+C to stop the server
    echo ========================================
    echo.
    php -S localhost:8000
)

pause
