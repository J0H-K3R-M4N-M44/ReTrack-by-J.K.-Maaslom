#requires -Version 5.1
# Isolated Windows test: only synthetic files and fake executable placeholders.
# Deliberately NEVER passes -Apply, connects to MySQL or starts XAMPP.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$root = Join-Path ([IO.Path]::GetTempPath()) ('repairtrack-test-' + [Guid]::NewGuid().ToString('N'))
$web = Join-Path ([IO.Path]::GetTempPath()) ('rt-web-' + [Guid]::NewGuid().ToString('N'))
$intake = Join-Path $root 'intake'
$stock = Join-Path $root 'stock'
$bin = Join-Path $root 'fake-client'
$creds = Join-Path $root 'test-only.cnf'
$php = Join-Path $root 'fake-php.exe'
$script = Join-Path $PSScriptRoot '..\tools\windows_xampp_test_restore.ps1'
function Invoke-Scenario([bool]$ShouldPass,[string]$Label,[hashtable]$Override) {
    $params = @{
        TestRoot = $root
        DocumentRoot = $web
        SourceDatabase = 'repairtrack_test_source'
        RestoreDatabase = 'repairtrack_test_newrestore'
        SourceIntake = $intake
        SourceInventory = $stock
        ClientBin = $bin
        CredentialsFile = $creds
        PhpExecutable = $php
        FictionalDataConfirmation = 'I_CONFIRM_FICTIONAL_ONLY'
    }
    foreach ($entry in $Override.GetEnumerator()) { $params[$entry.Key] = $entry.Value }
    & $script @params *> $null
    $exitCode = $LASTEXITCODE
    if ($ShouldPass -and $exitCode -ne 0) { throw ('Expected safe dry-run PASS: ' + $Label) }
    if (!$ShouldPass -and $exitCode -eq 0) { throw ('Expected safe refusal: ' + $Label) }
}
try {
    foreach ($dir in @($root,$web,$intake,$stock,$bin)) {
        $null = New-Item -ItemType Directory -Path $dir -Force
    }
    [IO.File]::WriteAllBytes((Join-Path $intake 'fictional.png'),[byte[]](1,2,3))
    [IO.File]::WriteAllBytes((Join-Path $stock 'fictional.png'),[byte[]](4,5,6))
    foreach ($name in @('mysql.exe','mysqldump.exe')) {
        [IO.File]::WriteAllBytes((Join-Path $bin $name),[byte[]](1))
    }
    [IO.File]::WriteAllBytes($php,[byte[]](1))
    [IO.File]::WriteAllText($creds,('[client]' + [Environment]::NewLine + 'user=fictional' + [Environment]::NewLine + 'password=unused' + [Environment]::NewLine))

    Invoke-Scenario $true 'valid read-only preflight' @{}
    Invoke-Scenario $false 'live schema refused' @{ SourceDatabase='repairtrack' }
    Invoke-Scenario $false 'identical source and restore' @{ RestoreDatabase='repairtrack_test_source' }
    Invoke-Scenario $false 'missing confirmation' @{ FictionalDataConfirmation='' }
    Invoke-Scenario $false 'public evidence path' @{ SourceIntake=$web }
    Invoke-Scenario $false 'overlapping evidence sources' @{ SourceInventory=$intake }
    Invoke-Scenario $false 'option file outside test area' @{ CredentialsFile=$script }
    $occupied = Join-Path $root 'restore-intake-repairtrack_test_newrestore'
    $null = New-Item -ItemType Directory -Path $occupied
    Invoke-Scenario $false 'existing restore destination' @{}
    Remove-Item -LiteralPath $occupied -Force
    Write-Output 'PASS: Windows/XAMPP restore preflight accepts isolated fictional setup and rejects unsafe paths, targets and names.'
    Write-Output 'No backup, SQL connection, schema restore or human XAMPP operation occurred.'
} finally {
    foreach ($dir in @($root,$web)) {
        if (Test-Path -LiteralPath $dir) { Remove-Item -LiteralPath $dir -Recurse -Force }
    }
}

# Negative dry-run subprocesses deliberately exit nonzero. Clear the last
# expected denial only after all assertions and cleanup have succeeded.
exit 0
