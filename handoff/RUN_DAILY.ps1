$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'OPERATIONAL_COMMON.ps1')
$mutex = $null
$locked = $false
try {
    $settings = Get-SystemSettings
    $digest = [Security.Cryptography.SHA256]::Create()
    try { $token = [BitConverter]::ToString($digest.ComputeHash([Text.Encoding]::UTF8.GetBytes($script:SystemRoot.ToLowerInvariant()))).Replace('-', '') }
    finally { $digest.Dispose() }
    $mutex = New-Object Threading.Mutex($false, ('Local\PetroleumSystem-' + $token))
    $locked = $mutex.WaitOne(30000)
    if (!$locked) { throw 'Another start is in progress.' }
    $stateFile = Join-Path $script:SystemRoot 'storage\app\daily-runtime.json'
    $listener = @(Get-NetTCPConnection -LocalPort $settings.port -State Listen -ErrorAction SilentlyContinue)
    if ($listener.Count -gt 0) {
        if (!(Test-Path -LiteralPath $stateFile)) { throw 'The selected address belongs to another process.' }
        $state = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
        $running = Get-Process -Id $state.pid -ErrorAction SilentlyContinue
        if (!$running -or $running.StartTime.ToUniversalTime().Ticks.ToString() -ne $state.started -or
            $running.Path -ne $settings.php -or !($listener | Where-Object { $_.OwningProcess -eq $state.pid })) { throw 'The selected address belongs to another process.' }
    } else {
        $check = Invoke-PrivatePhp $settings.php 'artisan app:check-system --no-interaction'
        if ($check.Code -ne 0) { throw 'Installation check failed.' }
        $router = Join-Path $script:SystemRoot 'handoff\LOCAL_SERVER.php'
        $public = Join-Path $script:SystemRoot 'public'
        # Launch directly: the recorded PID is the listener, with no console window to keep open.
        $log = Join-Path $script:SystemRoot 'storage\logs\daily-server-error.log'
        $arguments = '-d display_errors=Off -d log_errors=On -d error_log="' + $log + '" -S 127.0.0.1:' + $settings.port + ' -t "' + $public + '" "' + $router + '"'
        $command = '"' + $settings.php + '" ' + $arguments
        # WMI starts under the current user's identity, independently of the launcher
        # console/job lifetime. No administrator elevation or scheduled task is required.
        $process = $null
        try {
            $startup = New-CimInstance -ClassName Win32_ProcessStartup -ClientOnly -Property @{ ShowWindow = [uint16]0; CreateFlags = [uint32]8 }
            $created = Invoke-CimMethod -ClassName Win32_Process -MethodName Create -Arguments @{ CommandLine = $command; CurrentDirectory = $public; ProcessStartupInformation = $startup }
            if ($created.ReturnValue -eq 0) { $process = Get-Process -Id $created.ProcessId -ErrorAction SilentlyContinue }
        } catch { }
        if (!$process) {
            # Some Windows installations reject WMI process creation (for example code 8).
            # Shell execution without redirected handles also survives the launcher closing.
            $process = Start-Process -FilePath $settings.php -ArgumentList $arguments -WorkingDirectory $public -WindowStyle Hidden -PassThru
        }
        $state = @{ pid = $process.Id; started = $process.StartTime.ToUniversalTime().Ticks.ToString() }
        [IO.File]::WriteAllText($stateFile, ($state | ConvertTo-Json), [Text.UTF8Encoding]::new($false))
    }
    $ready = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri ($settings.url + '/login') -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200 -and $response.Content -match 'name="email"') { $ready = $true; break }
        } catch { }
        Start-Sleep -Milliseconds 300
    }
    if (!$ready) { throw 'The system is unavailable.' }
    Start-Process $settings.url
    Write-Host 'سیستم آماده است. در صفحه بازشده وارد شوید.'
    exit 0
} catch {
    try { [IO.File]::WriteAllText((Join-Path $script:SystemRoot 'storage\logs\daily-launcher.log'), $_.Exception.Message, [Text.UTF8Encoding]::new($false)) } catch { }
    exit 1
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    if ($mutex) { $mutex.Dispose() }
}
