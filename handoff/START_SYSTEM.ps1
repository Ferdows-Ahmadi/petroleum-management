param(
    [string]$BindAddress = '127.0.0.1',
    [ValidateRange(1024,65535)][int]$Port = 8080
)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (!(Test-Path -LiteralPath 'artisan') -or !(Test-Path -LiteralPath '.env')) {
    throw 'ابتدا نصب سیستم و تنظیم فایل .env را تکمیل کنید.'
}
& php artisan app:check-system
if ($LASTEXITCODE -ne 0) { throw 'بررسی پیش‌نیازها موفق نشد.' }
Write-Host 'این پنجره را باز نگه دارید. برای توقف امن، در همین پنجره Ctrl+C را فشار دهید.'
Write-Host 'این راه شروع ساده برای شبکه داخلی محدود است. برای استفاده دوامدار، وب‌سرور با HTTPS نصب شود.'
& php artisan serve "--host=$BindAddress" "--port=$Port"
exit $LASTEXITCODE
