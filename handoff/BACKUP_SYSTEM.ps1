param([string]$Destination)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (!$Destination) { $Destination = Read-Host 'مسیر فولدر محافظت‌شده پشتیبان، بیرون از public' }
if (!$Destination) { throw 'مسیر پشتیبان لازم است.' }
Write-Host 'از کاربران بخواهید ثبت معلومات را تمام کنند. هنگام گرفتن پشتیبان، ورود موقتاً بسته می‌شود.'
& php artisan app:backup $Destination
exit $LASTEXITCODE
