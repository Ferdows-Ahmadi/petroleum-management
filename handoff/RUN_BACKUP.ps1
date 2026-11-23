$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'OPERATIONAL_COMMON.ps1')
$mutex = $null
$locked = $false
try {
    $settings = Get-SystemSettings
    $mutex = New-Object Threading.Mutex($false, 'Local\PetroleumCompanyBackup')
    $locked = $mutex.WaitOne(0)
    if (!$locked) { throw 'A backup is already running.' }
    Write-Host 'در حال ساخت پشتیبان؛ تا پایان کمی صبر کنید و معلومات تازه ثبت نکنید.'
    $result = Invoke-PrivatePhp $settings.php 'artisan app:backup Backups --no-interaction'
    if ($result.Code -ne 0) { throw 'Backup failed.' }
    Write-Host 'پشتیبان موفقانه ساخته شد. فولدر بازشده را ببینید.'
    Start-Process explorer.exe -ArgumentList ('"' + (Join-Path $script:SystemRoot 'Backups') + '"')
    exit 0
} catch {
    try { [IO.File]::WriteAllText((Join-Path $script:SystemRoot 'storage\logs\backup-launcher.log'), $_.Exception.Message, [Text.UTF8Encoding]::new($false)) } catch { }
    exit 1
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    if ($mutex) { $mutex.Dispose() }
}
