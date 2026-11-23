@echo off
setlocal
chcp 65001 >nul
title First setup - Company system
if not exist "%~dp0FIRST_SETUP.ps1" (
  echo Setup files are missing. Extract the complete handoff ZIP before running setup.
  pause
  exit /b 1
)
if not exist "%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" (
  echo Windows PowerShell is unavailable. Contact the technical installer.
  pause
  exit /b 1
)
pushd "%~dp0"
if errorlevel 1 (
  echo The installation folder could not be opened.
  pause
  exit /b 1
)
rem Let Windows PowerShell load its own modules if launched from PowerShell 7.
set "PSModulePath="
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0FIRST_SETUP.ps1"
set "setup_exit=%errorlevel%"
popd
echo.
echo Press any key to close this window.
pause >nul
exit /b %setup_exit%
