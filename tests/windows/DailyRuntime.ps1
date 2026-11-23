# Windows integration test. Uses a scratch installation and a loopback PHP fixture.
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('naftyar launcher ' + [guid]::NewGuid())
$php = (Get-Command php.exe).Source
$ps5 = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$stateFile = Join-Path $scratch 'storage\app\daily-runtime.json'

function Invoke-Launcher([string]$Mode, [int]$Expected) {
    & $ps5 -NoProfile -ExecutionPolicy Bypass -File (Join-Path $scratch 'handoff\RUN_DAILY.ps1') -Action $Mode -NoBrowser
    if ($LASTEXITCODE -ne $Expected) {
        throw ("{0}: expected {1}, got {2}. See scratch launcher log." -f $Mode,$Expected,$LASTEXITCODE)
    }
}
function Assert-Login([string]$Url) {
    if ((Invoke-WebRequest -Uri ($Url + '/login') -UseBasicParsing -TimeoutSec 5).StatusCode -ne 200) {
        throw 'The expected HTTP listener is unavailable.'
    }
}

$foreign = $null
try {
    foreach ($dir in @('handoff','public','storage\logs','storage\app','shortcuts')) {
        New-Item -ItemType Directory -Path (Join-Path $scratch $dir) -Force | Out-Null
    }
    foreach ($name in @('RUN_DAILY.ps1','OPERATIONAL_COMMON.ps1')) {
        Copy-Item -LiteralPath (Join-Path $root ('handoff\' + $name)) -Destination (Join-Path $scratch 'handoff')
    }
    foreach ($name in @('artisan','.env','.env.example','START_SYSTEM.bat','BACKUP_SYSTEM.bat')) {
        [IO.File]::WriteAllText((Join-Path $scratch $name),'')
    }
    [IO.File]::WriteAllText((Join-Path $scratch 'handoff\LOCAL_SERVER.php'), '<?php echo ''<input name="email">'';')
    $socket = New-Object Net.Sockets.TcpListener([Net.IPAddress]::Loopback, 0)
    $socket.Start()
    $port = $socket.LocalEndpoint.Port
    $socket.Stop()
    $url = 'http://127.0.0.1:' + $port
    $settings = @{php=$php; port=$port; url=$url; mysqlBin=(Split-Path -Parent $php)}
    [IO.File]::WriteAllText((Join-Path $scratch '.company-runtime.json'), ($settings | ConvertTo-Json))

    Push-Location
    try {
        . (Join-Path $scratch 'handoff\OPERATIONAL_COMMON.ps1')
        $folder = Join-Path $scratch 'shortcuts'
        New-CompanyShortcuts -Desktop $folder -CheckOnly
        if (@(Get-ChildItem $folder).Count) { throw 'Shortcut preflight wrote a link.' }
        New-CompanyShortcuts -Desktop $folder
        $links = @(Get-ChildItem $folder -Filter '*.lnk')
        $targets = @($links | ForEach-Object { [PetroleumDesktopShortcut]::Target($_.FullName) })
        if ($links.Count -ne 2 -or $targets -notcontains (Join-Path $scratch 'START_SYSTEM.bat') -or
            $targets -notcontains (Join-Path $scratch 'BACKUP_SYSTEM.bat')) { throw 'Unexpected shortcut targets.' }
        [PetroleumDesktopShortcut]::Write($links[0].FullName, $php, $scratch, $php, 'foreign link')
        $denied = $false
        try { New-CompanyShortcuts -Desktop $folder -CheckOnly } catch { $denied = $true }
        if (!$denied -or [PetroleumDesktopShortcut]::Target($links[0].FullName) -ne $php) {
            throw 'Foreign desktop link was not preserved.'
        }
    } finally { Pop-Location }

    Invoke-Launcher Start 0
    $first = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
    Invoke-Launcher Start 0
    if ((Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json).started -ne $first.started) { throw 'Start replaced a healthy listener.' }
    Invoke-Launcher Restart 0
    if ((Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json).started -eq $first.started) { throw 'Restart did not replace the listener.' }
    Assert-Login $url
    Invoke-Launcher Stop 0
    Invoke-Launcher Stop 0
    if (Test-Path -LiteralPath $stateFile) { throw 'Stopped server still owns the state file.' }

    # The same PHP executable, port, valid PID and start time from a different
    # application must still be refused by every destructive maintenance action.
    $foreign = Start-Process $php -ArgumentList ('-S 127.0.0.1:' + $port + ' "' + (Join-Path $scratch 'handoff\LOCAL_SERVER.php') + '"') -WindowStyle Hidden -PassThru
    for ($i=0; $i -lt 30; $i++) {
        try { Assert-Login $url; break } catch { Start-Sleep -Milliseconds 200 }
    }
    Assert-Login $url
    Invoke-Launcher Stop 1
    $state = @{pid=$foreign.Id; started=$foreign.StartTime.ToUniversalTime().Ticks.ToString()}
    [IO.File]::WriteAllText($stateFile, ($state | ConvertTo-Json))
    Invoke-Launcher Stop 1
    Invoke-Launcher Restart 1
    Assert-Login $url
    Write-Output 'PASS: shortcuts, collision protection, start/reuse/restart/stop, foreign listener preserved.'
} finally {
    if ($foreign) { if (!$foreign.HasExited) { $foreign.Kill(); $foreign.WaitForExit() }; $foreign.Dispose() }
    if (Test-Path -LiteralPath $stateFile) {
        & $ps5 -NoProfile -ExecutionPolicy Bypass -File (Join-Path $scratch 'handoff\RUN_DAILY.ps1') -Action Stop
    }
    # Only the generated, locally allocated temp fixture is eligible for cleanup.
    if ((Test-Path -LiteralPath $scratch) -and (Split-Path -Parent (Resolve-Path -LiteralPath $scratch).Path) -eq [IO.Path]::GetTempPath().TrimEnd('\')) {
        Remove-Item -LiteralPath $scratch -Recurse -Force
    }
}
