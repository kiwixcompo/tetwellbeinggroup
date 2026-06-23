@echo off
cls
echo ╔═══════════════════════════════════════════════════════════════╗
echo ║       TET WELLBEING GROUP - UPDATE REPOSITORY                 ║
echo ╚═══════════════════════════════════════════════════════════════╝
echo.
echo 📦 Adding changes...
git add .
echo.
echo 💾 Committing...
git commit -m "Update: %date% %time%"
echo.
echo 🚀 Pushing to GitHub...
git push origin main
echo.
if errorlevel 1 (
    echo ❌ Push failed. Check your internet connection or GitHub credentials.
    pause
    exit /b 1
)
echo ╔═══════════════════════════════════════════════════════════════╗
echo ║  ✅ UPDATE SUCCESSFUL!                                        ║
echo ╚═══════════════════════════════════════════════════════════════╝
echo.
echo 📍 Repository: https://github.com/kiwixcompo/tetwellbeinggroup
echo 📍 cPanel will auto-deploy in 1-2 minutes
echo.
pause
