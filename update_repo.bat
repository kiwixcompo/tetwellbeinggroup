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
echo ║  ✅ PUSH SUCCESSFUL!                                          ║
echo ╚═══════════════════════════════════════════════════════════════╝
echo.
echo 📍 Repository: https://github.com/kiwixcompo/tetwellbeinggroup
echo.
echo 🌐 Triggering cPanel Auto-Deployment Webhook...
curl -s "https://tetwellbeinggroup.com/deploy.php?token=tet_deploy_secret_2026"
echo.
echo ╔═══════════════════════════════════════════════════════════════╗
echo ║  ✅ DEPLOYMENT FINISHED!                                      ║
echo ╚═══════════════════════════════════════════════════════════════╝
echo.
pause
