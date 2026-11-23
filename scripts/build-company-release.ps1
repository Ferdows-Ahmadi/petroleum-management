param([string]$Commit)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$releaseRoot = Join-Path $projectRoot 'release'
$target = [IO.Path]::GetFullPath((Join-Path $releaseRoot 'petroleum-management-company-handoff'))
$allowedRoot = [IO.Path]::GetFullPath($releaseRoot) + [IO.Path]::DirectorySeparatorChar
if (!$target.StartsWith($allowedRoot, [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe release path.' }
New-Item -ItemType Directory -Force -Path $releaseRoot | Out-Null
if (Test-Path -LiteralPath $target) { Remove-Item -LiteralPath $target -Recurse -Force }
New-Item -ItemType Directory -Path $target | Out-Null
$sourceRoot = $projectRoot
$temporaryRoot = $null
try {
    if ($Commit) {
        $temporaryRoot = Join-Path $releaseRoot ('source-' + [guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Path $temporaryRoot | Out-Null
        $archive = Join-Path $temporaryRoot 'source.zip'
        & git -C $projectRoot archive "--output=$archive" $Commit
        if ($LASTEXITCODE -ne 0) { throw 'git archive failed.' }
        Expand-Archive -LiteralPath $archive -DestinationPath (Join-Path $temporaryRoot 'tree')
        $sourceRoot = Join-Path $temporaryRoot 'tree'
    }
    foreach ($directory in @('app','config','lang','resources','routes','tests')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $directory) -Destination $target -Recurse
    }
    New-Item -ItemType Directory -Path (Join-Path $target 'database') | Out-Null
    foreach ($directory in @('migrations','factories','seeders')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot ('database/'+$directory)) -Destination (Join-Path $target 'database') -Recurse
    }
    New-Item -ItemType Directory -Path (Join-Path $target 'bootstrap'),(Join-Path $target 'public'),(Join-Path $target 'docs'),(Join-Path $target 'scripts') | Out-Null
    foreach ($file in @('bootstrap/app.php','bootstrap/providers.php')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination (Join-Path $target $file)
    }
    foreach ($file in @('artisan','composer.json','composer.lock','package.json','package-lock.json','vite.config.js','phpunit.xml','phpunit.mysql.xml','.env.example','.editorconfig','.gitattributes','.npmrc')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination (Join-Path $target $file)
    }
    Copy-Item -LiteralPath (Join-Path $sourceRoot 'scripts/build-company-release.ps1') -Destination (Join-Path $target 'scripts')
    foreach ($file in @('FIRST_SETUP.bat','FIRST_SETUP.ps1','START_SYSTEM.bat','BACKUP_SYSTEM.bat','CHECK_SYSTEM.bat','راهنمای_خیلی_ساده_کاربران.txt')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination (Join-Path $target $file)
    }
    Copy-Item -LiteralPath (Join-Path $sourceRoot 'docs/COMPANY_HANDOFF_VERIFICATION_2026_10_03.md') -Destination (Join-Path $target 'docs')
    Copy-Item -LiteralPath (Join-Path $sourceRoot 'docs/contact-import.md') -Destination (Join-Path $target 'docs')
    Copy-Item -LiteralPath (Join-Path $sourceRoot 'handoff') -Destination $target -Recurse
    Get-ChildItem -LiteralPath (Join-Path $sourceRoot 'handoff') -File -Force | Where-Object {
        $_.Name -notin @('INSTALL_BRIDGE.php','LOCAL_SERVER.php','OPERATIONAL_COMMON.ps1','RUN_DAILY.ps1','RUN_BACKUP.ps1')
    } | ForEach-Object {
        Copy-Item -LiteralPath $_.FullName -Destination $target
    }
    Copy-Item -LiteralPath (Join-Path $sourceRoot 'docs/SIMPLIFIED_OPERATIONS_VERIFICATION_2026_10_03.md') -Destination (Join-Path $target 'docs')
    foreach ($file in @('public/index.php','public/.htaccess','public/robots.txt','public/favicon.ico','public/favicon-32x32.png','public/apple-touch-icon.png')) {
        Copy-Item -LiteralPath (Join-Path $sourceRoot $file) -Destination (Join-Path $target $file)
    }
    Copy-Item -LiteralPath (Join-Path $sourceRoot 'public/images') -Destination (Join-Path $target 'public') -Recurse
    Copy-Item -LiteralPath (Join-Path $projectRoot 'public/build') -Destination (Join-Path $target 'public') -Recurse
    Copy-Item -LiteralPath (Join-Path $projectRoot 'vendor') -Destination $target -Recurse
    foreach ($directory in @('bootstrap/cache','storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/testing','storage/framework/views','storage/logs')) {
        New-Item -ItemType Directory -Force -Path (Join-Path $target $directory) | Out-Null
        [IO.File]::WriteAllText((Join-Path $target ($directory+'/.gitignore')),("*"+[Environment]::NewLine+"!.gitignore"+[Environment]::NewLine),[Text.UTF8Encoding]::new($false))
    }
    $hashLines = Get-ChildItem -LiteralPath $target -File -Recurse | Where-Object {
        !$_.FullName.StartsWith((Join-Path $target 'vendor') + '\') -and
        !$_.FullName.StartsWith((Join-Path $target 'public/build') + '\') -and
        !$_.FullName.StartsWith((Join-Path $target 'storage') + '\') -and
        !$_.FullName.StartsWith((Join-Path $target 'bootstrap/cache') + '\')
    } | Sort-Object FullName | ForEach-Object {
        $relative = $_.FullName.Substring($target.Length + 1).Replace('\','/')
        (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLower() + '  ' + $relative
    }
    [IO.File]::WriteAllLines((Join-Path $target 'SOURCE_FILES.sha256.txt'),$hashLines,[Text.UTF8Encoding]::new($false))
    Write-Output "Clean release generated: $target"
    Write-Output "Source revision: $(if ($Commit) {$Commit} else {'working tree, pending copied-package verification'})"
} finally {
    if ($temporaryRoot) {
        $resolved = [IO.Path]::GetFullPath($temporaryRoot)
        if (!$resolved.StartsWith($allowedRoot,[StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe temporary cleanup path.' }
        Remove-Item -LiteralPath $resolved -Recurse -Force
    }
}
