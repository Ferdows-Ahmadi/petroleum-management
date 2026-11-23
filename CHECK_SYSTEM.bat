@echo off
setlocal
set "PSModulePath="
set "PETROLEUM_CHECK_FILE=%~f0"
set "PETROLEUM_CHECK_ROOT=%~dp0"
chcp 65001 >nul
title Company system - Support check
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoLogo -NoProfile -ExecutionPolicy Bypass -Command "$text=[IO.File]::ReadAllText($env:PETROLEUM_CHECK_FILE); $marker='# POWERSHELL_SUPPORT_CHECK'; & ([scriptblock]::Create($text.Substring($text.LastIndexOf($marker)+$marker.Length)))"
if errorlevel 1 echo The support check could not finish. Send a picture of this window to your installer.
echo.
echo Press any key to close this window.
pause >nul
exit /b
# POWERSHELL_SUPPORT_CHECK
$ErrorActionPreference = 'Stop'
$root = $env:PETROLEUM_CHECK_ROOT
$lines = New-Object 'Collections.Generic.List[string]'
function Add-Report([string]$Text) { $lines.Add($Text); Write-Host $Text }
function Run-CheckPhp([string]$Php, [string]$Arguments) {
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $Php; $info.Arguments = $Arguments; $info.WorkingDirectory = $root
    $info.UseShellExecute = $false; $info.CreateNoWindow = $true
    $info.RedirectStandardOutput = $true; $info.RedirectStandardError = $true
    $info.StandardOutputEncoding = [Text.UTF8Encoding]::new($false)
    $info.StandardErrorEncoding = [Text.UTF8Encoding]::new($false)
    $example = Join-Path $root '.env.example'
    if (Test-Path -LiteralPath $example) {
        foreach ($line in [IO.File]::ReadAllLines($example)) {
            if ($line -match '^([A-Z][A-Z0-9_]*)=') { $info.EnvironmentVariables.Remove($matches[1]) }
        }
    }
    $info.EnvironmentVariables.Remove('DB_URL')
    $info.EnvironmentVariables['PAO_DISABLE'] = '1'
    $process = [Diagnostics.Process]::Start($info)
    $output = $process.StandardOutput.ReadToEndAsync()
    $errors = $process.StandardError.ReadToEndAsync()
    if (!$process.WaitForExit(20000)) { $process.Kill(); $process.Dispose(); return 'CHECK_TIMEOUT' }
    $text = $output.GetAwaiter().GetResult()
    [void]$errors.GetAwaiter().GetResult()
    $process.Dispose()
    return $text
}
try {
    Add-Report 'Company system support report - 2026-11-07'
    Add-Report ('Checked (UTC): ' + [DateTime]::UtcNow.ToString('s'))
    Add-Report ('PowerShell: ' + $PSVersionTable.PSVersion.ToString())
    foreach ($name in @('FIRST_SETUP.bat','START_SYSTEM.bat','artisan','vendor/autoload.php','public/build/manifest.json','handoff/RUN_DAILY.ps1')) {
        Add-Report ($name + ': ' + $(if (Test-Path -LiteralPath (Join-Path $root $name)) { 'present' } else { 'MISSING' }))
    }
    $hasEnv = Test-Path -LiteralPath (Join-Path $root '.env')
    $hasSettings = Test-Path -LiteralPath (Join-Path $root '.company-runtime.json')
    Add-Report ('Installation configuration exists: ' + $hasEnv)
    Add-Report ('Completed setup settings exist: ' + $hasSettings)
    try {
        $services = @(Get-Service -ErrorAction Stop | Where-Object Name -match '^mysql')
        if (!$services.Count) { Add-Report 'MySQL service: NOT FOUND' }
        foreach ($service in $services) { Add-Report ('MySQL service: ' + $service.Status) }
    } catch { Add-Report 'MySQL service: could not inspect' }
    $mysqlFolders = @()
    foreach ($tool in @('mysql.exe','mysqldump.exe')) {
        $command = Get-Command $tool -ErrorAction SilentlyContinue
        if ($command) {
            $versionText = Run-CheckPhp $command.Source '--version'
            if ($versionText -match '\b(\d+\.\d+\.\d+)\b') { Add-Report ($tool + ' on PATH version: ' + $matches[1]) }
            else { Add-Report ($tool + ' on PATH version: could not inspect') }
            $mysqlFolders += Split-Path -Parent $command.Source
        } else { Add-Report ($tool + ' on PATH: NOT FOUND') }
    }
    foreach ($programFiles in @($env:ProgramFiles, ${env:ProgramFiles(x86)})) {
        if ($programFiles) { $mysqlFolders += Join-Path $programFiles 'MySQL\MySQL Server 8.4\bin' }
    }
    try {
        foreach ($service in @(Get-CimInstance Win32_Service -Filter "Name LIKE 'MySQL%'" -ErrorAction Stop)) {
            $binary = $null
            if ($service.PathName -match '^"([^"]+\.exe)"') { $binary = $matches[1] }
            elseif ($service.PathName -match '^(.+?\.exe)(?:\s|$)') { $binary = $matches[1] }
            if ($binary -and (Test-Path -LiteralPath $binary -PathType Leaf)) {
                $versionText = Run-CheckPhp $binary '--version'
                if ($versionText -match '\b(\d+\.\d+\.\d+)\b') { Add-Report ('MySQL service executable version: ' + $matches[1]) }
                $mysqlFolders += Split-Path -Parent $binary
            }
        }
    } catch { Add-Report 'MySQL service executable version: could not inspect' }
    $compatible = $false
    foreach ($folder in ($mysqlFolders | Select-Object -Unique)) {
        $valid = $true
        foreach ($tool in @('mysql.exe','mysqldump.exe')) {
            $path = Join-Path $folder $tool
            if (!(Test-Path -LiteralPath $path -PathType Leaf)) { $valid = $false; break }
            $versionText = Run-CheckPhp $path '--version'
            if ($versionText -notmatch '\b(?:Ver\s+)?8\.4\.\d+\b' -or $versionText -match 'MariaDB') { $valid = $false; break }
        }
        if ($valid) { Add-Report ('Compatible MySQL 8.4 tools folder: ' + $folder); $compatible = $true }
    }
    if (!$compatible) { Add-Report 'Compatible MySQL 8.4 tools: NOT FOUND. A technical installer must prepare MySQL 8.4.x.' }
    $php = $null
    if ($hasSettings) {
        try {
            $settings = [IO.File]::ReadAllText((Join-Path $root '.company-runtime.json')) | ConvertFrom-Json
            if (Test-Path -LiteralPath $settings.php -PathType Leaf) { $php = [string]$settings.php }
            Add-Report ('Configured PHP executable exists: ' + [bool]$php)
            Add-Report ('MySQL backup tool exists: ' + (Test-Path -LiteralPath (Join-Path $settings.mysqlBin 'mysqldump.exe')))
            $port = [int]$settings.port
            if ($port -ge 1024 -and $port -le 65535) {
                $listeners = @(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)
                Add-Report ('Local application port: ' + $port)
                Add-Report ('Local application port has a listener: ' + [bool]$listeners.Count)
            }
        } catch { Add-Report 'Completed setup settings: unreadable or invalid' }
    }
    if (!$php) { $command = Get-Command php.exe -ErrorAction SilentlyContinue; if ($command) { $php = $command.Source } }
    Add-Report ('PHP available: ' + [bool]$php)
    if ($php) {
        $version = Run-CheckPhp $php '-r "echo PHP_VERSION;"'
        if ($version -match '^\d+\.\d+\.\d+') { Add-Report ('PHP version: ' + $matches[0]) }
        if ((Test-Path -LiteralPath (Join-Path $root 'artisan')) -and (Test-Path -LiteralPath (Join-Path $root 'vendor/autoload.php'))) {
            $arguments = if ($hasEnv -and $hasSettings) { 'artisan app:check-system --no-interaction' } else { 'artisan app:check-system --offline --no-interaction' }
            $check = Run-CheckPhp $php $arguments
            $recognized = 0
            foreach ($line in ($check -split '\r?\n')) {
                $line = [regex]::Replace($line, '\x1B\[[0-9;]*m', '').Trim()
                # Allow only known diagnostics; never include raw PHP/SQL exceptions or logs.
                if ($line -match '^(PHP, package files, writable directories.* passed\.|PHP 8\.5\.x is required.*|Missing PHP extension: [A-Za-z0-9_]+|Compiled frontend manifest is missing\.|APP_KEY is missing\..*|MySQL must be configured\.|This package requires MySQL 8\.4\.x\.|Use a dedicated application MySQL account, not root\.|MySQL connection failed\. Check the service and local \.env credentials\.|CHECK_TIMEOUT)$') {
                    Add-Report $line; $recognized++
                } elseif ($line -match '^Directory is missing or not writable:') { Add-Report 'A required application folder is missing or not writable.'; $recognized++ }
            }
            if (!$recognized) { Add-Report 'Application check: no recognized result; technical diagnosis needed.' }
        }
    }
    $log = Join-Path $root 'storage/logs/daily-launcher.log'
    if (Test-Path -LiteralPath $log) {
        $lastError = [IO.File]::ReadAllText($log).Trim()
        Add-Report ('Last launcher error time (UTC): ' + (Get-Item -LiteralPath $log).LastWriteTimeUtc.ToString('s'))
        if ($lastError -match '^Background process creation failed \(Windows code (\d+)\)\.$') { Add-Report ('Last launcher error: Windows background process code ' + $matches[1]) }
        elseif ($lastError -in @('Installation is incomplete.','Invalid local runtime settings.','Installation check failed.','The system is unavailable.','The selected address belongs to another process.','Another start is in progress.','Operation timed out.')) { Add-Report ('Last launcher error: ' + $lastError) }
        else { Add-Report 'Last launcher error: other (raw details remain on this computer).' }
    } else { Add-Report 'Last launcher error log: not found' }
    if (!$hasEnv -and !$hasSettings) { Add-Report 'NEXT: Setup has not been completed on this computer. Run FIRST_SETUP.bat once.' }
    elseif (!$hasEnv -or !$hasSettings) { Add-Report 'NEXT: Setup is incomplete. Ask the installer to continue it; do not delete configuration or reset the database.' }
    else { Add-Report 'NEXT: Send this report to your installer to identify the startup failure.' }
} catch { Add-Report 'Some checks could not complete. Send this report to your installer.' }
$report = Join-Path $root 'SUPPORT_REPORT.txt'
try { [IO.File]::WriteAllLines($report, $lines, [Text.UTF8Encoding]::new($true)) }
catch { $report = Join-Path ([Environment]::GetFolderPath('Desktop')) 'SUPPORT_REPORT.txt'; [IO.File]::WriteAllLines($report, $lines, [Text.UTF8Encoding]::new($true)) }
Write-Host ('Report saved: ' + $report)
Write-Host 'Send SUPPORT_REPORT.txt to your installer. No passwords or business records are included.'
