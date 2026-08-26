$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'verify_governance.ps1'
for ($run = 1; $run -le 2; $run++) {
    $env:GOVERNANCE_SESSION_STABILITY = 'true'
    $env:GOVERNANCE_STABILITY_ONLY = 'true'
    & powershell -NoProfile -ExecutionPolicy Bypass -File $runner
    $exit = $LASTEXITCODE
    Write-Output ("RUNTIME_{0}_SESSION_STABILITY={1}" -f $run, ($(if ($exit -eq 0) {'PASS'} else {'FAIL'})))
    if ($exit -ne 0) { exit 1 }
}
Write-Output 'AUTH_SESSION_STABILITY=PASS'
Write-Output 'AUTH_FAILURE_REPRODUCED=NO'
exit 0
