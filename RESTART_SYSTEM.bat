@echo off
setlocal
set "PSModulePath="
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0handoff\RUN_DAILY.ps1" -Action Restart
if errorlevel 1 (
  echo The operation failed. Run CHECK_SYSTEM.bat for a support report.
  pause
  exit /b 1
)
exit /b 0
