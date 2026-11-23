param([string]$BackupFolder, [string]$Database)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (!$BackupFolder) { $BackupFolder = Read-Host 'مسیر کامل پشتیبان' }
if (!$Database) { $Database = Read-Host 'نام دقیق دیتابیس خالی بازیابی که در .env تنظیم شده است' }
if (!$BackupFolder -or !$Database) { throw 'فولدر پشتیبان و دیتابیس خالی لازم است.' }
Write-Host 'بازیابی فقط در دیتابیس و فولدر اسناد خالی انجام می‌شود؛ دیتابیس موجود پاک نمی‌شود.'
& php artisan app:restore $BackupFolder "--database=$Database"
exit $LASTEXITCODE
