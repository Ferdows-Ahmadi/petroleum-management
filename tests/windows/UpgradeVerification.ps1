# Windows PowerShell 5.1. This test uses temporary files only.
$ErrorActionPreference = 'Stop'
. (Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'scripts\upgrade-verification.ps1')

function Must-Reject([scriptblock]$Action) {
    try { & $Action } catch { return }
    throw 'An invalid upgrade input was accepted.'
}

$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('naftyar-upgrade-test-' + [Guid]::NewGuid().ToString('N'))
$package = [IO.Directory]::CreateDirectory((Join-Path $testRoot 'package')).FullName
try {
    $sample = Join-Path $package 'example.txt'
    [IO.File]::WriteAllText($sample, 'approved bytes')
    $inventory = Join-Path $package 'SOURCE_FILES.sha256.txt'
    [IO.File]::WriteAllText($inventory, ((Get-Hash $sample) + '  example.txt'))
    $digest = Get-Hash $inventory
    Assert-Package $package $digest
    Must-Reject { Assert-Package $package ('0' * 64) }
    [IO.File]::AppendAllText($sample, 'changed')
    Must-Reject { Assert-Package $package $digest }
    [IO.File]::WriteAllText($sample, 'approved bytes')
    [IO.File]::WriteAllText((Join-Path $package 'unexpected.txt'), 'extra file')
    Must-Reject { Assert-Package $package $digest }

    $backup = Join-Path $testRoot 'backup'
    foreach ($path in @('backup\files\private','backup\files\public','source\storage\app\private','source\storage\app\public')) {
        [void][IO.Directory]::CreateDirectory((Join-Path $testRoot $path))
    }
    $source = Join-Path $testRoot 'source'
    $upload = Join-Path $source 'storage\app\private\fictional.txt'
    $copy = Join-Path $backup 'files\private\fictional.txt'
    [IO.File]::WriteAllText($upload, 'fictional attachment')
    [IO.File]::Copy($upload, $copy)
    $sql = Join-Path $backup 'database.sql'
    [IO.File]::WriteAllText($sql, 'fictional dump')
    $manifest = @{ format = 1; database = 'sample'; files = @{ 'database.sql' = (Get-Hash $sql); 'files/private/fictional.txt' = (Get-Hash $copy) } }
    [IO.File]::WriteAllText((Join-Path $backup 'backup.json'), ($manifest | ConvertTo-Json))
    Assert-Backup $backup 'sample' $source
    Must-Reject { Assert-Backup $backup 'wrong_database' $source }
    [IO.File]::AppendAllText($sql, 'unapproved')
    Must-Reject { Assert-Backup $backup 'sample' $source }
    [IO.File]::WriteAllText($sql, 'fictional dump')
    [IO.File]::WriteAllText($upload, 'newer source content')
    Must-Reject { Assert-Backup $backup 'sample' $source }
    [IO.File]::Delete($copy)
    Must-Reject { Assert-Backup $backup 'sample' $source }
    Write-Output 'Verified valid package and backup, and refused seven mismatches.'
} finally {
    $resolved = (Resolve-Path -LiteralPath $testRoot).ProviderPath
    if ([IO.Path]::GetDirectoryName($resolved) -ne [IO.Path]::GetTempPath().TrimEnd('\') -or
        [IO.Path]::GetFileName($resolved) -notmatch '^naftyar-upgrade-test-[a-fA-F0-9]{32}$') { throw 'Unexpected test directory.' }
    Remove-Item -LiteralPath $resolved -Recurse -Force
}
