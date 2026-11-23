@echo off
setlocal
set "PSModulePath="
chcp 65001 >nul
title سیستم مدیریت شرکت
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0handoff\RUN_DAILY.ps1" 2>nul
if errorlevel 1 (
  echo سیستم باز نشد. با مسئول نصب تماس بگیرید.
  pause >nul
  exit /b 1
)
exit /b 0
