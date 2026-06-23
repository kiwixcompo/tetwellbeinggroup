@echo off
:: Tet Wellbeing Group - Git Sync Tool
echo ==============================================
echo   Tet Wellbeing Group - Git Backup ^& Sync Tool
echo ==============================================
echo.

:: Check if git is installed
where git >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Git is not installed or not in PATH. Please install Git first.
    pause
    exit /b
)

:: Check if WAMP repository directory is initialized
if not exist ".git" (
    echo [INFO] Git repository not initialized. Initializing now...
    git init
    git branch -M main
    echo.
)

:: Check remote origin configuration
git remote get-url origin >nul 2>nul
if %errorlevel% neq 0 (
    echo [INFO] GitHub Remote URL is not configured.
    echo Please create an empty repository named 'tetwellbeinggroup' on your GitHub profile first.
    echo.
    set /p username="Enter your GitHub username: "
    
    :: Clean whitespaces if any
    setlocal enabledelayedexpansion
    set "username=!username: =!"
    
    echo [INFO] Configuring remote origin to: https://github.com/!username!/tetwellbeinggroup.git
    git remote add origin https://github.com/!username!/tetwellbeinggroup.git
    endlocal
    echo.
)

:: Backup and push changes
echo [INFO] Adding all changes to commit stage...
git add .

echo [INFO] Creating backup commit...
git commit -m "Auto backup: %date% %time%"

echo [INFO] Pushing changes to main branch...
git push -u origin main

if %errorlevel% equ 0 (
    echo.
    echo ==============================================
    echo [SUCCESS] Your work has been pushed and backed up!
    echo ==============================================
) else (
    echo.
    echo [WARNING] Push failed. Make sure you created the repo on GitHub and have authorization.
)
echo.
pause
