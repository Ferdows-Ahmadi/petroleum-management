# End-to-end packaging regression. Requires the same tools as the Windows release build.
[CmdletBinding()]
param([string]$ScratchRoot = [IO.Path]::GetTempPath())
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$parent = [IO.Directory]::CreateDirectory($ScratchRoot).FullName.TrimEnd('\')
$work = Join-Path $parent ('pkg-' + [guid]::NewGuid().ToString('N').Substring(0,8))
New-Item -ItemType Directory -Path $work | Out-Null
function Assert-True([bool]$condition, [string]$message) {
    if (!$condition) { throw $message }
}
try {
    $checkout = Join-Path $work 'repo'
    $out = Join-Path $work 'out'
    & git clone --quiet --no-hardlinks -- $repo $checkout
    Assert-True ($LASTEXITCODE -eq 0) 'Could not create the disposable checkout.'
    $revision = & git -C $checkout rev-parse HEAD

    # Simulate a development machine with stale ignored artifacts AND dirty tracked files.
    foreach ($dir in @('vendor','public/build','node_modules','storage/app/private')) {
        $d = [IO.Directory]::CreateDirectory((Join-Path $checkout $dir))
        [IO.File]::WriteAllText((Join-Path $d.FullName 'stale-sentinel.txt'), 'stale')
    }
    [IO.File]::WriteAllText((Join-Path $checkout '.env'), 'APP_NAME=Stale')
    [IO.File]::AppendAllText((Join-Path $checkout 'app/Models/Product.php'), 'invalid stale source')
    $builder = Join-Path $checkout 'scripts/build-company-release.ps1'
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -File $builder -Commit $revision -OutputDirectory $out
    Assert-True ($LASTEXITCODE -eq 0) 'Building a tracked revision from a dirty checkout failed.'
    $zip = @(Get-ChildItem -LiteralPath $out -Filter '*.zip')
    Assert-True ($zip.Count -eq 1) 'Expected one distribution ZIP.'
    $manifest = Get-Content -Raw -Encoding UTF8 (Get-ChildItem -LiteralPath $out -Filter '*.manifest.json').FullName | ConvertFrom-Json
    Assert-True ($manifest.source_commit -eq $revision -and $manifest.zip_sha256 -eq (Get-FileHash $zip[0].FullName).Hash) 'Revision or ZIP hash mismatch.'
    $app = Join-Path $work 'unpacked'
    [IO.Compression.ZipFile]::ExtractToDirectory($zip[0].FullName, $app)
    Assert-True (!(Get-ChildItem -LiteralPath $app -Recurse -Force -Filter 'stale-sentinel.txt')) 'A stale development artifact was shipped.'
    foreach ($path in @('.env','node_modules','vendor/phpunit','.git','public/hot')) {
        Assert-True (!(Test-Path -LiteralPath (Join-Path $app $path))) "Unexpected package content: $path"
    }
    $model = & git hash-object --no-filters (Join-Path $app 'app/Models/Product.php')
    Assert-True ($model -eq (& git -C $checkout rev-parse "${revision}:app/Models/Product.php")) 'Shipped source differs from the Git object.'
    $installed = Get-Content -Raw -Encoding UTF8 (Join-Path $app 'vendor/composer/installed.json') | ConvertFrom-Json
    $lock = Get-Content -Raw -Encoding UTF8 (Join-Path $app 'composer.lock') | ConvertFrom-Json
    $expected = @($lock.packages | ForEach-Object { "$($_.name) $($_.version) $($_.source.reference)" })
    $actual = @($installed.packages | ForEach-Object { "$($_.name) $($_.version) $($_.source.reference)" })
    Assert-True (!$installed.dev -and !(Compare-Object $expected $actual)) 'Production Composer packages differ from the lock.'
    $assets = Get-Content -Raw -Encoding UTF8 (Join-Path $app 'public/build/manifest.json') | ConvertFrom-Json
    foreach ($entry in @('resources/css/app.css','resources/js/app.js')) {
        Assert-True (Test-Path -LiteralPath (Join-Path $app "public/build/$($assets.$entry.file)") -PathType Leaf) "Missing built asset for $entry"
    }
    $lines = Get-Content -Encoding UTF8 (Join-Path $app 'SOURCE_FILES.sha256.txt')
    Assert-True (($lines.Count + 1) -eq @(Get-ChildItem -LiteralPath $app -Recurse -File -Force).Count) 'Incomplete inventory.'
    $seen = @{}
    foreach ($line in $lines) {
        Assert-True ($line -match '^([a-f0-9]{64})  (.+)$') 'Invalid inventory entry.'
        $digest, $path = $Matches[1], $Matches[2]
        Assert-True (!$seen.ContainsKey($path)) "Duplicate inventory entry: $path"
        $seen[$path] = $true
        Assert-True ((Get-FileHash -LiteralPath (Join-Path $app $path)).Hash -eq $digest) "Hash mismatch: $path"
    }

    # A failed locked frontend build must not fall back to the stale public/build folder.
    [IO.File]::WriteAllText((Join-Path $checkout 'package-lock.json'), '{}')
    & git -C $checkout add -- package-lock.json
    & git -C $checkout -c user.name=Fixture -c user.email=fixture@example.invalid commit --quiet -m 'Broken lockfile fixture' -- package-lock.json
    Assert-True ($LASTEXITCODE -eq 0) 'Could not create failed-build fixture.'
    $badOut = Join-Path $work 'failed'
    $proc = Start-Process -FilePath 'powershell.exe' -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$builder`" -Commit HEAD -OutputDirectory `"$badOut`"" -WindowStyle Hidden -PassThru -Wait
    Assert-True ($proc.ExitCode -ne 0 -and !(Get-ChildItem -Force -LiteralPath $badOut)) 'Failed build left a distributable or staging files.'
    Write-Output "PASS: stale artifacts, committed source, locked production dependencies, Vite entrypoints, $($lines.Count) content hashes, fail-closed build."
} finally {
    $resolved = [IO.Path]::GetFullPath($work)
    if ([IO.Path]::GetDirectoryName($resolved) -ne $parent) { throw 'Unsafe test cleanup path.' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
