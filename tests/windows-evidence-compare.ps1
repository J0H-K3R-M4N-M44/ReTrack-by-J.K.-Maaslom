#requires -Version 5.1
# Synthetic Windows-only tests. Never access real customer evidence.
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$fixtureRoot = Join-Path ([IO.Path]::GetTempPath()) ('repairtrack-test-' + [Guid]::NewGuid().ToString('N'))
$root = Join-Path $fixtureRoot 'evidence'
$web = Join-Path ([IO.Path]::GetTempPath()) ('repairtrack-web-' + [Guid]::NewGuid().ToString('N'))
$paths = @('original-intake', 'restored-intake', 'original-inventory', 'restored-inventory')
$source = Join-Path $root $paths[0]
$restored = Join-Path $root $paths[1]
$stock = Join-Path $root $paths[2]
$stockRestored = Join-Path $root $paths[3]
$script = Join-Path $PSScriptRoot '..\tools\windows_evidence_compare.ps1'
$done = 0
function Invoke-Check([bool]$Success, [string]$Label) {
    $params = @{
        TestRoot = $fixtureRoot
        DocumentRoot = $web
        SourceIntake = $source
        RestoredIntake = $restored
        SourceInventory = $stock
        RestoredInventory = $stockRestored
        FictionalDataConfirmation = 'I_CONFIRM_FICTIONAL_ONLY'
    }
    & $script @params *> $null
    $result = $LASTEXITCODE
    if ($Success -and $result -ne 0) { throw "Expected success: $Label" }
    if (-not $Success -and $result -eq 0) { throw "Expected rejection: $Label" }
}
try {
    $null = New-Item -ItemType Directory -Path $root,$web -Force
    foreach ($p in $paths) { $null = New-Item -ItemType Directory -Path (Join-Path $root $p) -Force }
    foreach ($folder in @($source,$restored,$stock,$stockRestored)) {
        $nested = Join-Path $folder 'nested'
        $null = New-Item -ItemType Directory -Path $nested -Force
        [IO.File]::WriteAllBytes((Join-Path $nested 'fictional.bin'), [byte[]](1,2,3,4,5))
    }
    Invoke-Check $true 'identical fictional evidence'
    [IO.File]::WriteAllBytes((Join-Path $restored 'nested\fictional.bin'), [byte[]](1,2,3,4,6))
    Invoke-Check $false 'corrupted intake evidence'
    [IO.File]::WriteAllBytes((Join-Path $restored 'nested\fictional.bin'), [byte[]](1,2,3,4,5))
    Remove-Item -LiteralPath (Join-Path $stockRestored 'nested\fictional.bin') -Force
    Invoke-Check $false 'missing inventory evidence'
    [IO.File]::WriteAllBytes((Join-Path $stockRestored 'nested\fictional.bin'), [byte[]](1,2,3,4,5))
    [IO.File]::WriteAllBytes((Join-Path $stockRestored 'nested\extra.bin'), [byte[]](10,11))
    Invoke-Check $false 'extra restored inventory evidence'
    Remove-Item -LiteralPath (Join-Path $stockRestored 'nested\extra.bin') -Force

    $originalSource = $source
    $source = $web
    Invoke-Check $false 'source inside publicly served directory'
    $source = $originalSource

    $originalRestored = $restored
    $restored = $source
    Invoke-Check $false 'overlapping evidence folders'
    $restored = $originalRestored

    $confirmationParams = @{
        TestRoot = $fixtureRoot
        DocumentRoot = $web
        SourceIntake = $source
        RestoredIntake = $restored
        SourceInventory = $stock
        RestoredInventory = $stockRestored
        FictionalDataConfirmation = 'INVALID'
    }
    & $script @confirmationParams *> $null
    if ($LASTEXITCODE -eq 0) { throw 'Missing explicit fictional confirmation was accepted.' }
    $done++
    Write-Output 'PASS: isolated Windows synthetic hashes, corruption/missing/extra files, boundary and confirmation denials.'
} finally {
    foreach ($folder in @($fixtureRoot, $web)) {
        if (Test-Path -LiteralPath $folder) { Remove-Item -LiteralPath $folder -Recurse -Force }
    }
}
