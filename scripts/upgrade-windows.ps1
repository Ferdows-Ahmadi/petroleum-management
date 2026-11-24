# Run in Windows PowerShell after independently verifying and extracting the approved ZIP.
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Installation,
    [Parameter(Mandatory)][string]$StagedDirectory,
    [Parameter(Mandatory)][string]$ExpectedDatabaseHost,
    [Parameter(Mandatory)][ValidateRange(1,65535)][int]$ExpectedDatabasePort,
    [Parameter(Mandatory)][ValidatePattern('^[a-zA-Z0-9_]{1,64}$')][string]$ExpectedDatabase,
    [Parameter(Mandatory)][ValidatePattern('^[0-9A-Fa-f]{64}$')][string]$InventorySha256
)
$ErrorActionPreference = 'Stop'

function Get-PlainDirectory([string]$Path) {
    $item = Get-Item -Force -LiteralPath $Path
    if (!$item.PSIsContainer -or !$item.Parent -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw 'Expected a regular installation folder, not a drive root or link.'
    }
    return $item.FullName.TrimEnd('\')
}
. (Join-Path $PSScriptRoot 'upgrade-verification.ps1')

$installed = Get-PlainDirectory $Installation
$stage = Get-PlainDirectory $StagedDirectory
$parent = [IO.Path]::GetDirectoryName($installed)
if ($installed -eq $stage -or $parent -ne [IO.Path]::GetDirectoryName($stage)) {
    throw 'Stage the clean package in a separate sibling folder on the same volume.'
}
$previousName = [IO.Path]::GetFileName($installed) + '.before-' + [Guid]::NewGuid().ToString('N')
$previous = Join-Path $parent $previousName
foreach ($name in @('.env','.company-runtime.json','artisan','handoff\RUN_DAILY.ps1')) {
    if (!(Test-Path -LiteralPath (Join-Path $installed $name) -PathType Leaf)) { throw 'Existing Windows installation was not recognized.' }
}
foreach ($name in @('.env','.company-runtime.json','Backups','bootstrap\cache\config.php')) {
    if (Test-Path -LiteralPath (Join-Path $stage $name)) { throw 'Staging folder already contains installation data.' }
}
$publicStorage = Join-Path $installed 'public\storage'
$publicLink = $false
if (Test-Path -LiteralPath $publicStorage) {
    $link = Get-Item -LiteralPath $publicStorage -Force
    if ($link.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        if ([IO.Path]::GetFullPath([string]$link.Target[0]).TrimEnd('\') -ne (Join-Path $installed 'storage\app\public')) {
            throw 'The custom public storage link needs review before upgrading.'
        }
        $publicLink = $true
    }
}
Assert-Package $stage $InventorySha256
if (!(Test-Path -LiteralPath (Join-Path $stage 'database\migrations\2026_11_24_000001_create_deal_notes_table.php'))) {
    throw 'This updater requires the reviewed owner-refinement release.'
}
$settings = Get-Content -LiteralPath (Join-Path $installed '.company-runtime.json') -Raw -Encoding UTF8 | ConvertFrom-Json
$env:PATH = $settings.mysqlBin + ';' + $env:PATH
# Never take database, config cache or storage overrides from a technician's shell.
foreach ($item in @(Get-ChildItem Env:)) {
    if ($item.Name -match '^(APP_|DB_|DATABASE_URL$|LARAVEL_|FILESYSTEM_|CACHE_|SESSION_|VIEW_COMPILED_PATH$)') {
        [Environment]::SetEnvironmentVariable($item.Name, [NullString]::Value, 'Process')
    }
}
$env:PAO_DISABLE = '1'
function Get-DatabaseIdentity([string]$Root) {
    $uuid = & $settings.php (Join-Path $PSScriptRoot 'upgrade-database-identity.php') $Root $ExpectedDatabaseHost $ExpectedDatabasePort $ExpectedDatabase
    if ($LASTEXITCODE -ne 0 -or $uuid -notmatch '^[0-9a-fA-F-]{36}$') { throw 'Database identity does not match the supervised upgrade target.' }
    return $uuid
}
$originalDatabase = Get-DatabaseIdentity $installed
function Invoke-App([string]$Root, [string[]]$Arguments) {
    Push-Location -LiteralPath $Root
    try {
        & $settings.php artisan @Arguments
        if ($LASTEXITCODE -ne 0) { throw ('Application command failed: ' + $Arguments[0]) }
    } finally { Pop-Location }
}
function Copy-State([string]$Relative) {
    $source = Join-Path $installed $Relative
    if (!(Test-Path -LiteralPath $source)) { return }
    $dest = Join-Path $stage $Relative
    & robocopy.exe $source $dest /E /COPY:DATS /DCOPY:DAT /XJ /R:1 /W:1 /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Could not preserve $Relative." }
}

# The normal stop command verifies process ownership before acting.
$ps51 = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
& $ps51 -NoProfile -ExecutionPolicy Bypass -File (Join-Path $installed 'handoff\RUN_DAILY.ps1') -Action Stop -NoBrowser
if ($LASTEXITCODE -ne 0) { throw 'Could not stop the selected installation safely.' }
$sha = [Security.Cryptography.SHA256]::Create()
try { $token = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($installed.ToLowerInvariant()))).Replace('-','') }
finally { $sha.Dispose() }
$lifecycle = New-Object Threading.Mutex($false, ('Local\PetroleumSystem-' + $token))
$backup = New-Object Threading.Mutex($false, 'Local\PetroleumCompanyBackup')
$ownsLifecycle = $ownsBackup = $false
try {
    $ownsLifecycle = $lifecycle.WaitOne(30000)
    $ownsBackup = $backup.WaitOne(0)
    if (!$ownsLifecycle -or !$ownsBackup) { throw 'Another operation is in progress.' }
    if (Get-NetTCPConnection -State Listen -LocalPort $settings.port -ErrorAction SilentlyContinue) { throw 'The application port is still in use.' }
    if (Test-Path -LiteralPath (Join-Path $installed 'storage\framework\down')) { throw 'Installation is already under maintenance. Resolve its existing maintenance state first.' }
    Invoke-App $installed @('app:check-system','--no-interaction')
    Invoke-App $installed @('down','--no-interaction')
    $backupRoot = Join-Path $installed 'Backups'
    $before = @(Get-ChildItem -LiteralPath $backupRoot -Directory -Filter 'backup-*' | ForEach-Object Name)
    Invoke-App $installed @('app:backup','Backups','--no-interaction')
    $created = @(Get-ChildItem -LiteralPath $backupRoot -Directory -Filter 'backup-*' | Where-Object { $_.Name -notin $before })
    if ($created.Count -ne 1) { throw 'Could not identify a unique new pre-upgrade backup.' }
    Assert-Backup $created[0].FullName $ExpectedDatabase $installed
    # Apply the existing root and private-file ACLs. Keep a complete original tree for rollback.
    Copy-AccessRules $installed $stage
    foreach ($name in @('.env','.company-runtime.json')) {
        $source = Join-Path $installed $name
        $dest = Join-Path $stage $name
        [IO.File]::WriteAllBytes($dest, [byte[]]@())
        Copy-AccessRules $source $dest
        [IO.File]::WriteAllBytes($dest, [IO.File]::ReadAllBytes($source))
        if ((Get-Hash $source) -ne (Get-Hash $dest)) { throw "State copy failed: $name" }
    }
    Copy-State 'storage\app'
    Copy-State 'storage\logs'
    Copy-State 'storage\framework\sessions'
    Copy-State 'Backups'
    Copy-AccessRules (Join-Path $installed 'Backups') (Join-Path $stage 'Backups')
    if ($publicLink) {
        New-Item -ItemType Junction -Path (Join-Path $stage 'public\storage') -Target (Join-Path $installed 'storage\app\public') | Out-Null
    } else {
        Copy-State 'public\storage'
    }
    Invoke-App $stage @('down','--no-interaction')
    if ((Get-DatabaseIdentity $stage) -ne $originalDatabase) { throw 'Staged database identity mismatch.' }
    Invoke-App $stage @('migrate','--force','--no-interaction','--path=database/migrations/2026_11_24_000001_create_deal_notes_table.php')
    Invoke-App $stage @('app:initialize-products','--no-interaction')
    Invoke-App $stage @('app:check-system','--no-interaction')
    Set-Location -LiteralPath $parent
    [Environment]::CurrentDirectory = $parent
    # Paths were resolved above and checked to be separate sibling directories, never a drive root.
    if ((Test-Path -LiteralPath $previous) -or [IO.Path]::GetDirectoryName($previous) -ne $parent) { throw 'Unsafe previous-installation path.' }
    Rename-Item -LiteralPath $installed -NewName $previousName
    try { Rename-Item -LiteralPath $stage -NewName ([IO.Path]::GetFileName($installed)) }
    catch { Rename-Item -LiteralPath $previous -NewName ([IO.Path]::GetFileName($installed)); throw }
    Invoke-App $installed @('app:check-system','--no-interaction')
    Invoke-App $installed @('up','--no-interaction')
    Write-Output "Application verified. Previous installation: $previous"
    Write-Output 'Start with the existing desktop shortcut. Check the login page and balances before resuming work.'
} finally {
    if ($ownsBackup) { $backup.ReleaseMutex() }
    if ($ownsLifecycle) { $lifecycle.ReleaseMutex() }
    $backup.Dispose()
    $lifecycle.Dispose()
}
