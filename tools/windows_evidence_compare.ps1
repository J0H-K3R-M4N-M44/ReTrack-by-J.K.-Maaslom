#requires -Version 5.1
<#
Read-only file-integrity comparison for a FICTIONAL Windows/XAMPP recovery rehearsal.
This script does NOT back up, restore, connect to MySQL, access HTTP, or change files.
Do not use with real production evidence. Never print paths, file names or hashes.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$TestRoot,
    [Parameter(Mandatory = $true)][string]$DocumentRoot,
    [Parameter(Mandatory = $true)][string]$SourceIntake,
    [Parameter(Mandatory = $true)][string]$RestoredIntake,
    [Parameter(Mandatory = $true)][string]$SourceInventory,
    [Parameter(Mandatory = $true)][string]$RestoredInventory,
    [Parameter(Mandatory = $true)][string]$FictionalDataConfirmation
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function CanonicalFolder([string]$Value) {
    $item = Get-Item -LiteralPath $Value -Force -ErrorAction Stop
    if (-not $item.PSIsContainer) { throw 'Directory check failed.' }
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
        throw 'Directory check failed: reparse point.'
    }
    return [IO.Path]::GetFullPath($item.FullName).TrimEnd([char[]]@('\','/'))
}
function IsContained([string]$Child, [string]$Parent) {
    return $Child.Equals($Parent, [StringComparison]::OrdinalIgnoreCase) -or
        $Child.StartsWith($Parent + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)
}
function CheckNoLinks([string]$Folder) {
    $entries = @(Get-ChildItem -LiteralPath $Folder -Recurse -Force -ErrorAction Stop)
    foreach ($entry in $entries) {
        if (($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw 'Evidence contains a symbolic link or reparse point.'
        }
    }
}
function EvidenceManifest([string]$Folder) {
    CheckNoLinks $Folder
    $files = @(Get-ChildItem -LiteralPath $Folder -File -Recurse -Force -ErrorAction Stop)
    $manifest = [Collections.Generic.Dictionary[string,string]]::new([StringComparer]::OrdinalIgnoreCase)
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($Folder.Length).TrimStart([char[]]@('\','/'))
        if ($manifest.ContainsKey($relative)) { throw 'Duplicate evidence path.' }
        $digest = (Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256 -ErrorAction Stop).Hash
        $manifest.Add($relative, $digest)
    }
    return ,$manifest
}
try {
    if ($FictionalDataConfirmation -cne 'I_CONFIRM_FICTIONAL_ONLY') {
        throw 'Explicit fictional-data confirmation is required.'
    }
    $test = CanonicalFolder $TestRoot
    $web = CanonicalFolder $DocumentRoot
    if ((Split-Path -Path $test -Leaf) -notmatch '^repairtrack-test-[a-zA-Z0-9_-]+$') {
        throw 'Test root must use the explicit repairtrack-test-* folder naming.'
    }
    if (IsContained $test $web -or IsContained $web $test) {
        throw 'Test root must be separate from the document root.'
    }
    $folders = @(
        (CanonicalFolder $SourceIntake),
        (CanonicalFolder $RestoredIntake),
        (CanonicalFolder $SourceInventory),
        (CanonicalFolder $RestoredInventory)
    )
    foreach ($folder in $folders) {
        if (-not (IsContained $folder $test) -or (IsContained $folder $web)) {
            throw 'All evidence must be under the isolated test root and outside the web root.'
        }
    }
    for ($i = 0; $i -lt $folders.Count; $i++) {
        for ($j = $i + 1; $j -lt $folders.Count; $j++) {
            if ((IsContained $folders[$i] $folders[$j]) -or (IsContained $folders[$j] $folders[$i])) {
                throw 'Evidence directories must be four separate non-overlapping folders.'
            }
        }
    }
    $intakeOriginal = EvidenceManifest $folders[0]
    $intakeRestored = EvidenceManifest $folders[1]
    $stockOriginal = EvidenceManifest $folders[2]
    $stockRestored = EvidenceManifest $folders[3]
    if ($intakeOriginal.Count -eq 0 -or $stockOriginal.Count -eq 0) {
        throw 'Test source needs fictional evidence in both categories.'
    }
    foreach ($category in @('intake', 'inventory')) {
        if ($category -eq 'intake') {
            $original = $intakeOriginal
            $restored = $intakeRestored
        } else {
            $original = $stockOriginal
            $restored = $stockRestored
        }
        if ($original.Count -ne $restored.Count) { throw 'Evidence file count differs.' }
        foreach ($key in $original.Keys) {
            if (-not $restored.ContainsKey($key) -or
                $restored[$key] -cne $original[$key]) {
                throw 'Evidence files are missing, renamed or byte-different.'
            }
        }
    }
    Write-Output ('PASS: fictional intake and stock evidence verified byte-for-byte (' +
        $intakeOriginal.Count + ' intake, ' + $stockOriginal.Count + ' inventory files).')
    Write-Output 'SQL/custody/cash reconciliation and human Windows/XAMPP restoration are separate gates.'
    exit 0
} catch {
    [Console]::Error.WriteLine('BLOCK: fictional evidence comparison failed. Verify isolation, nonempty folders, exact names and hashes. No private file details were emitted.')
    exit 2
}
