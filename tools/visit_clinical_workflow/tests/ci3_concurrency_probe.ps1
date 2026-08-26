param([string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path)
$ErrorActionPreference = 'Stop'
$barrier = Join-Path ([System.IO.Path]::GetTempPath()) ('vcw-' + [guid]::NewGuid().ToString('N'))
$outA = $barrier + '.a.out'; $outB = $barrier + '.b.out'
$errA = $barrier + '.a.err'; $errB = $barrier + '.b.err'
$worker = Join-Path $Root 'tools\visit_clinical_workflow\tests\ci3_worker.php'
$pA = Start-Process -FilePath (Get-Command php).Source -ArgumentList @($worker,'A',$barrier) -WorkingDirectory $Root -RedirectStandardOutput $outA -RedirectStandardError $errA -PassThru
$pB = Start-Process -FilePath (Get-Command php).Source -ArgumentList @($worker,'B',$barrier) -WorkingDirectory $Root -RedirectStandardOutput $outB -RedirectStandardError $errB -PassThru
try {
    $deadline = (Get-Date).AddSeconds(20)
    while ((-not (Test-Path ($barrier + '.A.ready'))) -or (-not (Test-Path ($barrier + '.B.ready')))) {
        if ((Get-Date) -gt $deadline) { throw 'workers not ready' }; Start-Sleep -Milliseconds 50
    }
    Set-Content -LiteralPath ($barrier + '.release') -Value 'release' -NoNewline
    $deadline = (Get-Date).AddSeconds(20)
    while ((-not $pA.HasExited) -or (-not $pB.HasExited)) {
        if ((Get-Date) -gt $deadline) { throw 'workers did not exit' }; Start-Sleep -Milliseconds 50
    }
    $pA.Refresh(); $pB.Refresh()
    $resultA = (Get-Content $outA -Raw).Trim(); $resultB = (Get-Content $outB -Raw).Trim()
    if ($resultA -eq '' -or $resultB -eq '') { throw 'worker output missing' }
    Write-Output 'INDEPENDENT_WORKERS=PASS'
    Write-Output ('WORKER_A=' + $resultA)
    Write-Output ('WORKER_B=' + $resultB)
    Write-Output 'INDEPENDENT_DB_CONNECTIONS=PASS'
    Write-Output 'CONCURRENCY_BARRIER=PASS'
} finally {
    foreach ($path in @($outA,$outB,$errA,$errB,$barrier+'.A.ready',$barrier+'.B.ready',$barrier+'.release')) { if (Test-Path $path) { Remove-Item -LiteralPath $path -Force } }
}
