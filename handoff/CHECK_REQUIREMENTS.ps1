param([switch]$Offline)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (!(Get-Command php -ErrorAction SilentlyContinue)) { throw 'PHP در PATH پیدا نشد. راهنمای نصب را ببینید.' }
$arguments = @('artisan','app:check-system')
if ($Offline) { $arguments += '--offline' }
& php @arguments
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
foreach ($tool in @('mysql','mysqldump')) {
    if (!(Get-Command $tool -ErrorAction SilentlyContinue)) {
        Write-Warning "$tool برای پشتیبان‌گیری و بازیابی باید در PATH باشد."
    }
}
exit 0
