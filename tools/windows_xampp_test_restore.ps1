#requires -Version 5.1
<#
Isolated Windows/XAMPP backup+restore for FICTIONAL LOCAL test data only.
Without -Apply this only validates the plan; it does NOT connect to MySQL.
A source/target name other than repairtrack_test_* is always refused.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$TestRoot,
    [Parameter(Mandatory=$true)][string]$DocumentRoot,
    [Parameter(Mandatory=$true)][string]$SourceDatabase,
    [Parameter(Mandatory=$true)][string]$RestoreDatabase,
    [Parameter(Mandatory=$true)][string]$SourceIntake,
    [Parameter(Mandatory=$true)][string]$SourceInventory,
    [Parameter(Mandatory=$true)][string]$ClientBin,
    [Parameter(Mandatory=$true)][string]$CredentialsFile,
    [string]$PhpExecutable = 'C:\xampp\php\php.exe',
    [int]$Port = 3306,
    [string]$FictionalDataConfirmation = '',
    [switch]$Apply
)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
function Folder([string]$Path) {
    $item = Get-Item -LiteralPath $Path -Force -ErrorAction Stop
    if (!$item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw 'Directory unavailable or unsafe.'
    }
    return [IO.Path]::GetFullPath($item.FullName).TrimEnd([char[]]@('\','/'))
}
function Inside([string]$Path,[string]$Parent) {
    return $Path.Equals($Parent,[StringComparison]::OrdinalIgnoreCase) -or
        $Path.StartsWith($Parent + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)
}
function CheckedNative([string]$Stage) {
    if ($LASTEXITCODE -ne 0) { throw ('Fictional SQL stage failed: ' + $Stage) }
}
try {
    if ($FictionalDataConfirmation -cne 'I_CONFIRM_FICTIONAL_ONLY') {
        throw 'Explicit fictional-only confirmation is required.'
    }
    if ($SourceDatabase -cnotmatch '^repairtrack_test_[A-Za-z0-9_]{1,48}$' -or
        $RestoreDatabase -cnotmatch '^repairtrack_test_[A-Za-z0-9_]{1,48}$' -or
        $SourceDatabase -ceq $RestoreDatabase -or $Port -lt 1 -or $Port -gt 65535) {
        throw 'Only two distinct fictional database names are permitted.'
    }
    $project = Folder (Split-Path -Parent $PSScriptRoot)
    $root = Folder $TestRoot
    $web = Folder $DocumentRoot
    $intake = Folder $SourceIntake
    $stock = Folder $SourceInventory
    if ((Split-Path -Leaf $root) -cnotmatch '^repairtrack-test-[A-Za-z0-9_-]+$' -or
        (Inside $root $web) -or (Inside $web $root) -or
        (Inside $root $project) -or (Inside $project $root) -or
        !(Inside $intake $root) -or !(Inside $stock $root) -or
        (Inside $intake $stock) -or (Inside $stock $intake)) {
        throw 'Unsafe or overlapping test, web and evidence paths.'
    }
    foreach ($source in @($intake,$stock)) {
        $entries = @(Get-ChildItem -LiteralPath $source -Force -Recurse)
        if (@($entries | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }).Count -gt 0 -or
            @($entries | Where-Object { !$_.PSIsContainer }).Count -eq 0) {
            throw 'Evidence must be nonempty and contain no symlinks.'
        }
    }
    $secret = Get-Item -LiteralPath $CredentialsFile -Force
    $credentialPath = [IO.Path]::GetFullPath($secret.FullName)
    if ($secret.PSIsContainer -or ($secret.Attributes -band [IO.FileAttributes]::ReparsePoint) -or
        !(Inside $credentialPath $root)) {
        throw 'Use a protected test-only MySQL option file inside the isolated test root.'
    }
    $bin = Folder $ClientBin
    $mysql = Join-Path $bin 'mysql.exe'
    $dump = Join-Path $bin 'mysqldump.exe'
    if (!(Test-Path -LiteralPath $mysql -PathType Leaf) -or
        !(Test-Path -LiteralPath $dump -PathType Leaf) -or
        !(Test-Path -LiteralPath $PhpExecutable -PathType Leaf)) {
        throw 'Required XAMPP MySQL/PHP executables unavailable.'
    }
    $backupDir = Join-Path $root 'backups'
    $backupFile = Join-Path $backupDir ($RestoreDatabase + '.sql')
    $restoreIntake = Join-Path $root ('restore-intake-' + $RestoreDatabase)
    $restoreStock = Join-Path $root ('restore-stock-' + $RestoreDatabase)
    foreach ($destination in @($backupFile,$restoreIntake,$restoreStock)) {
        if (Test-Path -LiteralPath $destination) {
            throw 'A target already exists; choose a fresh restore name.'
        }
    }
    Write-Output 'PASS: fictional Windows restore prerequisites and target boundaries verified.'
    if (!$Apply) {
        Write-Output 'DRY RUN: no database connection, dump, schema creation or file copies were made.'
        exit 0
    }

    # Override any host/protocol entries from the supplied test-only credentials.
    # The option file must contain username/password; never put them on command lines.
    $flags = @("--defaults-extra-file=$credentialPath",'--protocol=TCP','--host=127.0.0.1',
       "--port=$Port",'--silent','--skip-column-names')
    $sourceFound = & $mysql @flags '--execute=SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=DATABASE()' $SourceDatabase 2>$null
    CheckedNative 'source lookup'
    if ($sourceFound -ne '1') { throw 'Fictional source was not found.' }
    $targetQuery = "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$RestoreDatabase'"
    $targetFound = & $mysql @flags "--execute=$targetQuery" 2>$null
    CheckedNative 'destination lookup'
    if ($targetFound -ne '0') { throw 'Restore target already exists and will never be overwritten.' }

    $null = New-Item -ItemType Directory -Path $backupDir -Force
    $dumpFlags = @("--defaults-extra-file=$credentialPath",'--protocol=TCP','--host=127.0.0.1',
        "--port=$Port",'--single-transaction','--quick','--routines','--triggers',
        '--events','--hex-blob',"--result-file=$backupFile",$SourceDatabase)
    & $dump @dumpFlags 2>$null
    CheckedNative 'backup'
    if (!(Test-Path -LiteralPath $backupFile) -or (Get-Item -LiteralPath $backupFile).Length -lt 1024) {
        throw 'SQL dump was unexpectedly small.'
    }
    & $mysql @flags "--execute=CREATE DATABASE $RestoreDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>$null
    CheckedNative 'new schema'
    $dumpPath = $backupFile.Replace('\','/')
    & $mysql @flags "--execute=source $dumpPath" $RestoreDatabase 2>$null
    CheckedNative 'SQL import'

    $null = New-Item -ItemType Directory -Path $restoreIntake,$restoreStock
    Get-ChildItem -LiteralPath $intake -Force | Copy-Item -Destination $restoreIntake -Force -Recurse
    Get-ChildItem -LiteralPath $stock -Force | Copy-Item -Destination $restoreStock -Force -Recurse

    $env:REPAIRTRACK_DISPOSABLE_RECONCILE = 'I_CONFIRM_DISPOSABLE_ONLY'
    try {
        & $PhpExecutable (Join-Path $project 'tools\compare_test_restore.php') $SourceDatabase $RestoreDatabase
        CheckedNative 'SQL reconciliation'
        $check = @{
            TestRoot = $root
            DocumentRoot = $web
            SourceIntake = $intake
            RestoredIntake = $restoreIntake
            SourceInventory = $stock
            RestoredInventory = $restoreStock
            FictionalDataConfirmation = 'I_CONFIRM_FICTIONAL_ONLY'
        }
        & (Join-Path $project 'tools\windows_evidence_compare.ps1') @check
        CheckedNative 'evidence reconciliation'
    } finally {
        Remove-Item Env:REPAIRTRACK_DISPOSABLE_RECONCILE -ErrorAction SilentlyContinue
    }
    Write-Output 'PASS: fictional Windows SQL and evidence restore matched the separate original test fixture.'
    exit 0
} catch {
    [Console]::Error.WriteLine('BLOCK: isolated restore refused or failed. No existing database or evidence folder was overwritten.')
    [Console]::Error.WriteLine('If -Apply was used, retain any newly created fictional target for private inspection.')
    exit 2
}
