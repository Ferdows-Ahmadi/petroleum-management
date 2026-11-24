[CmdletBinding()]
param(
    [string]$Commit = 'HEAD',
    [string]$OutputDirectory = (Join-Path (Split-Path -Parent $PSScriptRoot) 'release')
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$revision = & git -C $projectRoot rev-parse --verify --end-of-options "${Commit}^{commit}"
if ($LASTEXITCODE -ne 0 -or $revision -notmatch '^[0-9a-f]{40}$') { throw 'Select a valid Git commit.' }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$utf8 = [Text.UTF8Encoding]::new($false)
$output = [IO.Directory]::CreateDirectory($OutputDirectory).FullName
$name = "Naftyar-Windows-$($revision.Substring(0,12))"
$zip = Join-Path $output "$name.zip"
$checksum = "$zip.sha256"
$manifest = Join-Path $output "$name.manifest.json"
foreach ($file in @($zip, $checksum, $manifest)) {
    if (Test-Path -LiteralPath $file) { throw "Output already exists: $file. Select a fresh output directory." }
}

# Format and hash the same relative filenames on Windows PowerShell and PowerShell 7.
function Get-Digest([string]$Path) {
    $stream = [IO.File]::OpenRead($Path)
    $hasher = [Security.Cryptography.SHA256]::Create()
    try { return [BitConverter]::ToString($hasher.ComputeHash($stream)).Replace('-', '').ToLowerInvariant() }
    finally { $stream.Dispose(); $hasher.Dispose() }
}

$stage = Join-Path $output ('.build-' + [guid]::NewGuid().ToString('N').Substring(0,12))
New-Item -ItemType Directory -Path $stage | Out-Null
try {
    $tree = Join-Path $stage 'app'
    $inputs = @(
        'app','config','lang','resources','routes','tests','database/migrations','database/factories','database/seeders',
        'bootstrap/app.php','bootstrap/providers.php','bootstrap/cache/.gitignore',
        'public/index.php','public/.htaccess','public/robots.txt','public/favicon.ico','public/favicon-32x32.png','public/apple-touch-icon.png','public/images',
        'artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','phpunit.xml','phpunit.mysql.xml',
        '.env.example','.editorconfig','.gitattributes','.npmrc','scripts/build-company-release.ps1',
        'FIRST_SETUP.bat','FIRST_SETUP.ps1','START_SYSTEM.bat','BACKUP_SYSTEM.bat','CHECK_SYSTEM.bat','راهنمای_خیلی_ساده_کاربران.txt',
        'docs/COMPANY_HANDOFF_VERIFICATION_2026_10_03.md','docs/SIMPLIFIED_OPERATIONS_VERIFICATION_2026_10_03.md','docs/contact-import.md','handoff'
    )
    # Earlier releases may be rehearsed before these launchers and the checklist were introduced.
    foreach ($path in @('STOP_SYSTEM.bat','RESTART_SYSTEM.bat','docs/first-supervised-installation-fa.md','scripts/upgrade-windows.ps1','scripts/upgrade-database-identity.php','scripts/upgrade-verification.ps1','docs/windows-upgrade-fa.md')) {
        if (& git -C $projectRoot ls-tree --name-only $revision -- $path) { $inputs += $path }
    }
    $sourceArchive = Join-Path $stage 'source.zip'
    & git -C $projectRoot archive --format=zip "--output=$sourceArchive" $revision -- @inputs
    if ($LASTEXITCODE -ne 0) { throw 'git archive failed.' }
    [IO.Compression.ZipFile]::ExtractToDirectory($sourceArchive, $tree)

    # Preserve the handoff's root launchers and Dari operator instructions.
    Get-ChildItem -LiteralPath (Join-Path $tree 'handoff') -File -Force | Where-Object {
        $_.Name -notin @('INSTALL_BRIDGE.php','LOCAL_SERVER.php','OPERATIONAL_COMMON.ps1','RUN_DAILY.ps1','RUN_BACKUP.ps1')
    } | ForEach-Object { Copy-Item -LiteralPath $_.FullName -Destination $tree }
    foreach ($directory in @('storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/testing','storage/framework/views','storage/logs')) {
        $directoryPath = [IO.Directory]::CreateDirectory((Join-Path $tree $directory)).FullName
        [IO.File]::WriteAllText((Join-Path $directoryPath '.gitignore'), "*`n!.gitignore`n", $utf8)
    }

    $composerHash = Get-Digest (Join-Path $tree 'composer.lock')
    $npmHash = Get-Digest (Join-Path $tree 'package-lock.json')
    Push-Location -LiteralPath $tree
    try {
        & composer validate --no-check-publish
        if ($LASTEXITCODE -ne 0) { throw 'composer.json and composer.lock did not validate.' }
        & composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader --no-scripts --no-progress
        if ($LASTEXITCODE -ne 0) { throw 'Locked production Composer installation failed.' }
        & composer check-platform-reqs --no-dev
        if ($LASTEXITCODE -ne 0) { throw 'PHP requirements are not satisfied.' }
        & npm.cmd ci --include=dev --ignore-scripts --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) { throw 'Locked npm installation failed.' }
        & npm.cmd run build
        if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
        if ($composerHash -ne (Get-Digest (Join-Path $tree 'composer.lock')) -or $npmHash -ne (Get-Digest (Join-Path $tree 'package-lock.json'))) {
            throw 'A build step changed a lockfile.'
        }
        foreach ($required in @('vendor/autoload.php','vendor/composer/installed.json','public/build/manifest.json')) {
            if (!(Test-Path -LiteralPath $required -PathType Leaf)) { throw "Missing build artifact: $required" }
        }
        $metadata = [ordered]@{
            source_commit = $revision
            builder_sha256 = Get-Digest $PSCommandPath
            composer_lock_sha256 = $composerHash
            npm_lock_sha256 = $npmHash
            vite_manifest_sha256 = Get-Digest (Join-Path $tree 'public/build/manifest.json')
            composer_production = $true
            php = (& php -r 'echo PHP_VERSION;')
            composer = ((& composer show --platform composer --format=json --no-ansi | ConvertFrom-Json).versions -join ',')
            node = (& node --version)
            npm = (& npm.cmd --version)
        }
        [IO.File]::WriteAllText((Join-Path $tree 'BUILD.json'), ($metadata | ConvertTo-Json), $utf8)
    } finally { Pop-Location }

    $nodeModules = [IO.Path]::GetFullPath((Join-Path $tree 'node_modules'))
    if ([IO.Path]::GetDirectoryName($nodeModules) -ne $tree) { throw 'Unsafe temporary dependency path.' }
    Remove-Item -LiteralPath $nodeModules -Recurse -Force

    $hashLines = [Collections.Generic.List[string]]::new()
    $temporaryZip = Join-Path $stage "$name.zip"
    $archive = [IO.Compression.ZipFile]::Open($temporaryZip, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($file in (Get-ChildItem -LiteralPath $tree -Recurse -File -Force | Sort-Object FullName)) {
            $relative = $file.FullName.Substring($tree.Length + 1).Replace('\','/')
            $hashLines.Add("$(Get-Digest $file.FullName)  $relative")
            [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $relative)
        }
        $inventory = Join-Path $tree 'SOURCE_FILES.sha256.txt'
        [IO.File]::WriteAllLines($inventory, $hashLines, $utf8)
        [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $inventory, 'SOURCE_FILES.sha256.txt')
    } finally { $archive.Dispose() }

    $zipHash = Get-Digest $temporaryZip
    $metadata.zip_file = "$name.zip"
    $metadata.zip_bytes = (Get-Item -LiteralPath $temporaryZip).Length
    $metadata.zip_sha256 = $zipHash
    $metadata.file_count = $hashLines.Count + 1
    $metadata.inventory_sha256 = Get-Digest $inventory
    [IO.File]::WriteAllText((Join-Path $stage "$name.manifest.json"), ($metadata | ConvertTo-Json), $utf8)
    [IO.File]::WriteAllText((Join-Path $stage "$name.zip.sha256"), "$zipHash  $name.zip`n", $utf8)
    foreach ($path in @($manifest, $checksum, $zip)) {
        [IO.File]::Move((Join-Path $stage ([IO.Path]::GetFileName($path))), $path)
    }
    Write-Output "Release ZIP: $zip"
    Write-Output "SHA-256: $zipHash"
} finally {
    $resolved = [IO.Path]::GetFullPath($stage)
    if ([IO.Path]::GetDirectoryName($resolved) -ne $output) { throw 'Unsafe temporary cleanup path.' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
