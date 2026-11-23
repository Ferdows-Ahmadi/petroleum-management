$ErrorActionPreference = 'Stop'
$script:SystemRoot = if (Test-Path -LiteralPath (Join-Path $PSScriptRoot 'artisan')) { $PSScriptRoot } else { Split-Path -Parent $PSScriptRoot }
Set-Location -LiteralPath $script:SystemRoot
# A parent shell must never redirect this installation to another database.
Get-Content -LiteralPath (Join-Path $script:SystemRoot '.env.example') | ForEach-Object {
    if ($_ -match '^([A-Z][A-Z0-9_]*)=') { [Environment]::SetEnvironmentVariable($matches[1], [NullString]::Value, 'Process') }
}
[Environment]::SetEnvironmentVariable('DB_URL', [NullString]::Value, 'Process')

function Read-PrivateValue([string]$Prompt) {
    $secret = Read-Host $Prompt -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer); $secret.Dispose() }
}

function Invoke-PrivatePhp([string]$Php, [string]$Arguments, [string]$InputText = '') {
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $Php
    $info.Arguments = $Arguments
    $info.WorkingDirectory = $script:SystemRoot
    $info.UseShellExecute = $false
    $info.CreateNoWindow = $true
    $info.RedirectStandardInput = $true
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.StandardOutputEncoding = New-Object Text.UTF8Encoding($false)
    $info.StandardErrorEncoding = New-Object Text.UTF8Encoding($false)
    $info.EnvironmentVariables.Remove('DB_URL')
    $process = New-Object Diagnostics.Process
    $process.StartInfo = $info
    if (!$process.Start()) { throw 'Could not start the runtime.' }
    $outputTask = $process.StandardOutput.ReadToEndAsync()
    $errorTask = $process.StandardError.ReadToEndAsync()
    $bytes = [Text.Encoding]::UTF8.GetBytes($InputText)
    $process.StandardInput.BaseStream.Write($bytes, 0, $bytes.Length)
    # Closing the unused StreamWriter can append an encoding preamble after JSON
    # when the parent console is UTF-8. Close the raw pipe after the exact bytes.
    $process.StandardInput.BaseStream.Close()
    if (!$process.WaitForExit(660000)) { $process.Kill(); throw 'Operation timed out.' }
    $result = [pscustomobject]@{ Code = $process.ExitCode; Output = $outputTask.GetAwaiter().GetResult(); Error = $errorTask.GetAwaiter().GetResult() }
    $process.Dispose()
    return $result
}

function Get-SystemSettings {
    $file = Join-Path $script:SystemRoot '.company-runtime.json'
    if (!(Test-Path -LiteralPath $file) -or !(Test-Path -LiteralPath (Join-Path $script:SystemRoot '.env'))) { throw 'Installation is incomplete.' }
    $settings = Get-Content -LiteralPath $file -Raw -Encoding UTF8 | ConvertFrom-Json
    if (!(Test-Path -LiteralPath $settings.php) -or $settings.port -lt 1024 -or $settings.port -gt 65535 -or $settings.url -ne ('http://127.0.0.1:' + $settings.port)) { throw 'Invalid local runtime settings.' }
    $env:PATH = $settings.mysqlBin + ';' + $env:PATH
    return $settings
}

function New-CompanyShortcuts([switch]$CheckOnly) {
    $desktop = [Environment]::GetFolderPath('Desktop')
    if (!('PetroleumDesktopShortcut' -as [type])) {
        Add-Type -TypeDefinition @'
using System;
using System.Text;
using System.Runtime.InteropServices;
using System.Runtime.InteropServices.ComTypes;
[ComImport, Guid("00021401-0000-0000-C000-000000000046")]
internal class PetroleumShellLink { }
[ComImport, Guid("000214F9-0000-0000-C000-000000000046"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
internal interface PetroleumShellLinkW {
    void GetPath([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder path, int size, IntPtr data, uint flags);
    void GetIDList(out IntPtr list);
    void SetIDList(IntPtr list);
    void GetDescription([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder text, int size);
    void SetDescription([MarshalAs(UnmanagedType.LPWStr)] string text);
    void GetWorkingDirectory([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder path, int size);
    void SetWorkingDirectory([MarshalAs(UnmanagedType.LPWStr)] string path);
    void GetArguments([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder text, int size);
    void SetArguments([MarshalAs(UnmanagedType.LPWStr)] string text);
    void GetHotkey(out short key);
    void SetHotkey(short key);
    void GetShowCmd(out int value);
    void SetShowCmd(int value);
    void GetIconLocation([Out, MarshalAs(UnmanagedType.LPWStr)] StringBuilder path, int size, out int index);
    void SetIconLocation([MarshalAs(UnmanagedType.LPWStr)] string path, int index);
    void SetRelativePath([MarshalAs(UnmanagedType.LPWStr)] string path, uint reserved);
    void Resolve(IntPtr window, uint flags);
    void SetPath([MarshalAs(UnmanagedType.LPWStr)] string path);
}
public static class PetroleumDesktopShortcut {
    public static string Target(string path) {
        object link = new PetroleumShellLink();
        try {
            ((IPersistFile)link).Load(path, 0);
            var target = new StringBuilder(32768);
            ((PetroleumShellLinkW)link).GetPath(target, target.Capacity, IntPtr.Zero, 0);
            return target.ToString();
        } finally { Marshal.FinalReleaseComObject(link); }
    }
    public static void Write(string path, string target, string directory, string icon, string description) {
        object link = new PetroleumShellLink();
        try {
            var shortcut = (PetroleumShellLinkW)link;
            shortcut.SetPath(target);
            shortcut.SetWorkingDirectory(directory);
            shortcut.SetDescription(description);
            shortcut.SetIconLocation(icon, 0);
            shortcut.SetShowCmd(1);
            ((IPersistFile)link).Save(path, true);
        } finally { Marshal.FinalReleaseComObject(link); }
    }
}
'@
    }
    foreach ($item in @(@('سیستم مدیریت شرکت', 'START_SYSTEM.bat'), @('پشتیبان سیستم شرکت', 'BACKUP_SYSTEM.bat'))) {
        $path = Join-Path $desktop ($item[0] + '.lnk')
        $target = [string](Join-Path $script:SystemRoot $item[1])
        if (Test-Path -LiteralPath $path) {
            if ([PetroleumDesktopShortcut]::Target($path) -ne $target) { throw 'An existing desktop shortcut belongs to another installation. Rename it before setting up this installation.' }
        }
        if ($CheckOnly) { continue }
        # Native wide-character ShellLink supports both Dari filenames and installation paths.
        [PetroleumDesktopShortcut]::Write($path, $target, $script:SystemRoot, (Join-Path $script:SystemRoot 'public\favicon.ico'), $item[0])
    }
}
