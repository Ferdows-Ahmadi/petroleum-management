function Get-Hash([string]$Path) {
    $sha = [Security.Cryptography.SHA256]::Create()
    $stream = [IO.File]::OpenRead($Path)
    try { return [BitConverter]::ToString($sha.ComputeHash($stream)).Replace('-', '') }
    finally { $stream.Dispose(); $sha.Dispose() }
}
function Copy-AccessRules([string]$Source, [string]$Destination) {
    # A DACL copy preserves access without requiring privilege to read/write the security-audit log.
    $access = [Security.AccessControl.AccessControlSections]::Access
    if ([IO.Directory]::Exists($Source)) {
        $acl = New-Object Security.AccessControl.DirectorySecurity
        $acl.SetSecurityDescriptorSddlForm([IO.Directory]::GetAccessControl($Source, $access).GetSecurityDescriptorSddlForm($access), $access)
        [IO.Directory]::SetAccessControl($Destination, $acl)
    } else {
        $acl = New-Object Security.AccessControl.FileSecurity
        $acl.SetSecurityDescriptorSddlForm([IO.File]::GetAccessControl($Source, $access).GetSecurityDescriptorSddlForm($access), $access)
        [IO.File]::SetAccessControl($Destination, $acl)
    }
}
function Assert-Backup([string]$Path, [string]$ExpectedDatabase, [string]$SourceRoot) {
    $manifest = Get-Content -LiteralPath (Join-Path $Path 'backup.json') -Raw -Encoding UTF8 | ConvertFrom-Json
    if ($manifest.format -ne 1 -or $manifest.database -cne $ExpectedDatabase -or !$manifest.files.'database.sql' -or
        (Get-Item -LiteralPath (Join-Path $Path 'database.sql')).Length -lt 1) { throw 'Incomplete database backup.' }
    $files = @(Get-ChildItem -LiteralPath $Path -Recurse -File -Force | Where-Object FullName -ne (Join-Path $Path 'backup.json'))
    if ($files.Count -ne @($manifest.files.PSObject.Properties).Count) { throw 'Backup file count does not match manifest.' }
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($Path.Length + 1).Replace('\','/')
        $expected = $manifest.files.PSObject.Properties[$relative]
        if (!$expected -or (Get-Hash $file.FullName) -ne $expected.Value) { throw 'Backup digest mismatch.' }
    }
    foreach ($disk in @('private','public')) {
        $source = Join-Path $SourceRoot ('storage\app\' + $disk)
        $copy = Join-Path $Path ('files\' + $disk)
        if (!(Test-Path -LiteralPath $copy -PathType Container)) { throw 'Missing upload backup.' }
        foreach ($file in Get-ChildItem -LiteralPath $source -Recurse -File -Force) {
            $relative = $file.FullName.Substring($source.Length + 1)
            if ((Get-Hash $file.FullName) -ne (Get-Hash (Join-Path $copy $relative))) { throw 'Upload backup does not match source.' }
        }
    }
}
function Assert-Package([string]$Root, [string]$Expected) {
    $name = 'SOURCE_FILES.sha256.txt'
    $inventory = Join-Path $Root $name
    if ((Get-Hash $inventory) -ne $Expected) { throw 'The package inventory does not match the approved manifest.' }
    $remaining = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
    foreach ($entry in [IO.File]::ReadAllLines($inventory)) {
        if ($entry -notmatch '^([a-fA-F0-9]{64})  (.+)$') { throw 'Invalid inventory.' }
        $digest = $matches[1]
        $relative = $matches[2].Replace('/', '\')
        if ($relative -match '(^|\\)\.\.?($|\\)|^\\|:' -or !$remaining.Add($relative)) { throw 'Unsafe inventory entry.' }
        $file = Join-Path $Root $relative
        if ((Get-Hash $file) -ne $digest) { throw "Package verification failed: $relative" }
    }
    foreach ($item in Get-ChildItem -LiteralPath $Root -Force -Recurse) {
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'The package contains a link.' }
        if ($item.PSIsContainer) { continue }
        $relative = $item.FullName.Substring($Root.Length + 1)
        if ($relative -ne $name -and !$remaining.Remove($relative)) { throw "Unexpected package file: $relative" }
    }
    if ($remaining.Count) { throw 'The package is incomplete.' }
}
