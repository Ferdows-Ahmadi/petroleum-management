param(
    [ValidateSet('Start', 'Stop', 'Restart')][string]$Action = 'Start',
    [switch]$NoBrowser
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'OPERATIONAL_COMMON.ps1')
$mutex = $null
$locked = $false
# All operations share the same per-installation mutex. A stale PID or a foreign
# listener must never grant permission to terminate another Windows process.
function Get-OwnedListener($Listeners, $StateFile, $Settings, [string]$ServerArguments) {
    $mismatch = 'The selected address belongs to another process.'
    if ($Listeners.Count -ne 1 -or $Listeners[0].LocalAddress -ne '127.0.0.1' -or
        !(Test-Path -LiteralPath $StateFile -PathType Leaf)) { throw $mismatch }
    $state = Get-Content -LiteralPath $StateFile -Raw | ConvertFrom-Json
    if ($Listeners[0].OwningProcess -ne $state.pid) { throw $mismatch }
    $running = Get-Process -Id $Listeners[0].OwningProcess -ErrorAction Stop
    # Open and retain the original process handle before checking the start time.
    $null = $running.Handle
    $details = Get-CimInstance -ClassName Win32_Process -Filter ('ProcessId = ' + $running.Id)
    if ($running.HasExited -or $running.StartTime.ToUniversalTime().Ticks.ToString() -ne $state.started -or
        $running.Path -ne $Settings.php -or !$details.CommandLine -or
        !$details.CommandLine.EndsWith((' ' + $ServerArguments), [StringComparison]::OrdinalIgnoreCase)) {
        $running.Dispose()
        throw $mismatch
    }
    return $running
}
try {
    $settings = Get-SystemSettings
    $digest = [Security.Cryptography.SHA256]::Create()
    try { $token = [BitConverter]::ToString($digest.ComputeHash([Text.Encoding]::UTF8.GetBytes($script:SystemRoot.ToLowerInvariant()))).Replace('-', '') }
    finally { $digest.Dispose() }
    $mutex = New-Object Threading.Mutex($false, ('Local\PetroleumSystem-' + $token))
    $locked = $mutex.WaitOne(30000)
    if (!$locked) { throw 'Another start is in progress.' }
    $stateFile = Join-Path $script:SystemRoot 'storage\app\daily-runtime.json'
    $router = Join-Path $script:SystemRoot 'handoff\LOCAL_SERVER.php'
    $public = Join-Path $script:SystemRoot 'public'
    $log = Join-Path $script:SystemRoot 'storage\logs\daily-server-error.log'
    $arguments = '-d display_errors=Off -d log_errors=On -d error_log="' + $log + '" -S 127.0.0.1:' + $settings.port + ' -t "' + $public + '" "' + $router + '"'
    $listener = @(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object LocalPort -eq $settings.port)
    if ($listener.Count -gt 0) {
        $running = Get-OwnedListener $listener $stateFile $settings $arguments
        try {
            if ($Action -ne 'Start') {
                $running.Kill()
                if (!$running.WaitForExit(10000)) { throw 'The local server could not be stopped.' }
                Remove-Item -LiteralPath $stateFile
            }
        } finally { $running.Dispose() }
    }
    if ($Action -eq 'Stop') {
        Write-Host 'The local application server is stopped.'
        exit 0
    }
    if ($listener.Count -eq 0 -or $Action -eq 'Restart') {
        $check = Invoke-PrivatePhp $settings.php 'artisan app:check-system --no-interaction'
        if ($check.Code -ne 0) { throw 'Installation check failed.' }
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
    $listener = @(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object LocalPort -eq $settings.port)
    $verified = Get-OwnedListener $listener $stateFile $settings $arguments
    $verified.Dispose()
    if (!$NoBrowser) { Start-Process $settings.url }
    Write-Host 'سیستم آماده است. در صفحه بازشده وارد شوید.'
    exit 0
} catch {
    try { [IO.File]::WriteAllText((Join-Path $script:SystemRoot 'storage\logs\daily-launcher.log'), $_.Exception.Message, [Text.UTF8Encoding]::new($false)) } catch { }
    exit 1
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    if ($mutex) { $mutex.Dispose() }
}
