@echo off
setlocal
set "PSModulePath="
chcp 65001 >nul
title پشتیبان سیستم شرکت
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0handoff\RUN_BACKUP.ps1" 2>nul
if errorlevel 1 (
  echo پشتیبان ساخته نشد. با مسئول نصب تماس بگیرید.
  pause >nul
  exit /b 1
)
exit /b 0
