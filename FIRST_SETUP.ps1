param([string]$ShortcutDirectory = [Environment]::GetFolderPath('Desktop'))
# One-time technical installation. No existing configuration/database is reset.
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'handoff\OPERATIONAL_COMMON.ps1')
function Read-Default([string]$Prompt, [string]$Default) {
    $value = Read-Host ($Prompt + ' [' + $Default + ']')
    if ([string]::IsNullOrWhiteSpace($value)) { return $Default }
    return $value.Trim()
}
function Test-MySql84Tools([string]$Folder) {
    if ([string]::IsNullOrWhiteSpace($Folder)) { return $false }
    foreach ($tool in @('mysql.exe', 'mysqldump.exe')) {
        $path = Join-Path $Folder $tool
        if (!(Test-Path -LiteralPath $path -PathType Leaf)) { return $false }
        try {
            $version = (& $path --version 2>&1 | Out-String).Trim()
            if ($LASTEXITCODE -ne 0 -or $version -notmatch '\b(?:Ver\s+)?8\.4\.\d+\b' -or $version -match 'MariaDB') { return $false }
        } catch { return $false }
    }
    return $true
}
function Select-MySql84Tools([string[]]$Candidates) {
    foreach ($folder in ($Candidates | Select-Object -Unique)) {
        if (Test-MySql84Tools $folder) { Write-Host ('MySQL 8.4 tools selected: ' + $folder); return $folder }
    }
    Write-Host 'Compatible MySQL 8.4 tools were not found automatically.' -ForegroundColor Yellow
    Write-Host 'The verified release requires MySQL Server 8.4.x and its tools. Keep existing installations and data.'
    while ($true) {
        $folder = (Read-Host 'Folder containing MySQL 8.4 mysql.exe and mysqldump.exe (or Q to close setup)').Trim().Trim('"')
        if ($folder -eq 'Q' -or !$folder) { throw 'MySQL 8.4 setup is required. No database was changed.' }
        if (Test-MySql84Tools $folder) { return $folder }
        Write-Host 'That folder does not contain both MySQL 8.4 tools. Choose the 8.4 bin folder, or type Q.' -ForegroundColor Yellow
    }
}
try {
    Write-Host 'FIRST SETUP - Technical installer only'
    if (Test-Path -LiteralPath '.env') { throw 'This installation is already configured. Use the desktop shortcut to start the system.' }
    New-CompanyShortcuts -Desktop $ShortcutDirectory -CheckOnly
    if (!(Test-Path 'vendor\autoload.php') -or !(Test-Path 'public\build\manifest.json')) { throw 'Use the complete company handoff package.' }
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    $php = if ($phpCommand) { $phpCommand.Source } else { Read-Host 'Full path to php.exe' }
    $check = Invoke-PrivatePhp $php 'artisan app:check-system --offline --no-interaction'
    if ($check.Code -ne 0) { Write-Host $check.Output; throw 'Required PHP components or package files are missing.' }
    $mysqlCommand = Get-Command mysqldump -ErrorAction SilentlyContinue
    $mysqlFolders = @()
    if ($mysqlCommand) { $mysqlFolders += Split-Path -Parent $mysqlCommand.Source }
    foreach ($programFiles in @($env:ProgramFiles, ${env:ProgramFiles(x86)})) {
        if ($programFiles) { $mysqlFolders += Join-Path $programFiles 'MySQL\MySQL Server 8.4\bin' }
    }
    $mysqlBin = Select-MySql84Tools $mysqlFolders
    $hostName = Read-Default 'MySQL host' '127.0.0.1'
    $dbPort = [int](Read-Default 'MySQL port' '3306')
    $database = Read-Default 'New empty database name (do not select the existing company database)' 'petroleum_company'
    $dbUser = Read-Default 'Dedicated database username (not root)' 'petroleum_company_app'
    $dbPassword = Read-PrivateValue 'Database user password'
    $port = [int](Read-Default 'Local application port' '8080')
    if ($port -lt 1024 -or $port -gt 65535 -or (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue)) { throw 'The application port is invalid or already in use. Choose an available port.' }
    $inputData = @{ action = 'probe'; host = $hostName; port = $dbPort; database = $database; user = $dbUser; password = $dbPassword; url = ('http://127.0.0.1:' + $port) }
    $probe = Invoke-PrivatePhp $php 'handoff/INSTALL_BRIDGE.php' ($inputData | ConvertTo-Json -Depth 5 -Compress)
    if ($probe.Code -eq 20) {
        Write-Host 'Database access is not ready. Enter local MySQL administrator credentials to create the empty database and grant access only to it.'
        $inputData.admin = @{ user = (Read-Default 'MySQL administrator username' 'root'); password = (Read-PrivateValue 'MySQL administrator password') }
        $probe = Invoke-PrivatePhp $php 'handoff/INSTALL_BRIDGE.php' ($inputData | ConvertTo-Json -Depth 5 -Compress)
        $inputData.Remove('admin')
    }
    if ($probe.Code -ne 0) { $detail = $probe.Output | ConvertFrom-Json; throw ($detail.phase + ': ' + $detail.message) }
    Write-Host 'The separate empty database is verified. Now create the first General Manager.'
    $managerName = Read-Host 'General Manager name'
    $managerEmail = Read-Host 'General Manager login email'
    $managerPassword = Read-PrivateValue 'Manager password (at least 12 characters, uppercase and lowercase letters, and a number)'
    $confirmation = Read-PrivateValue 'Confirm manager password'
    if ($managerPassword -cne $confirmation) { throw 'Manager passwords do not match. No business data was installed.' }
    $inputData.action = 'install'
    $inputData.manager = @{ name = $managerName; email = $managerEmail; password = $managerPassword }
    Write-Host 'Preparing and verifying the system. Please wait...'
    $result = Invoke-PrivatePhp $php 'handoff/INSTALL_BRIDGE.php' ($inputData | ConvertTo-Json -Depth 5 -Compress)
    $inputData.Clear(); $dbPassword = $null; $managerPassword = $null; $confirmation = $null
    if ($result.Code -ne 0) { $detail = $result.Output | ConvertFrom-Json; throw ($detail.phase + ': ' + $detail.message) }
    $settings = @{ php = $php; mysqlBin = $mysqlBin; port = $port; url = ('http://127.0.0.1:' + $port) }
    [IO.File]::WriteAllText((Join-Path $script:SystemRoot '.company-runtime.json'), ($settings | ConvertTo-Json), [Text.UTF8Encoding]::new($false))
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    & icacls.exe (Join-Path $script:SystemRoot '.env') '/inheritance:r' '/grant:r' ($identity + ':(F)') '*S-1-5-18:(F)' '*S-1-5-32-544:(F)' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'The installer must check configuration file permissions.' }
    New-Item -ItemType Directory -Force -Path (Join-Path $script:SystemRoot 'Backups') | Out-Null
    & icacls.exe (Join-Path $script:SystemRoot 'Backups') '/inheritance:r' '/grant:r' ($identity + ':(OI)(CI)(F)') '*S-1-5-18:(OI)(CI)(F)' '*S-1-5-32-544:(OI)(CI)(F)' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'The installer must check backup folder permissions.' }
    New-CompanyShortcuts -Desktop $ShortcutDirectory
    Write-Host 'Installation and verification succeeded. No demo deals or accounts were installed.'
    Write-Host 'Double-click the company system desktop shortcut and sign in with the manager email and password.'
    exit 0
} catch {
    Write-Host ('Installation did not complete: ' + $_.Exception.Message) -ForegroundColor Red
    Write-Host 'Do not delete existing data. If configuration was created, follow the technical maintenance guide to continue.'
    exit 1
}
