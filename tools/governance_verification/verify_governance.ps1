param([switch]$SelfTest)
$ErrorActionPreference = 'Stop'

function Emit([string]$key, [string]$value) { Write-Output ("{0}={1}" -f $key, $value) }

if ($SelfTest) {
    & php "$PSScriptRoot\http_matrix.php" --self-test
    $httpExit = $LASTEXITCODE
    if ($httpExit -ne 1) { Emit 'SELF_TEST' 'FAIL'; exit 1 }
    & powershell -NoProfile -ExecutionPolicy Bypass -File "$PSScriptRoot\run_concurrency.ps1" -SelfTest
    if ($LASTEXITCODE -ne 0) { Emit 'SELF_TEST' 'FAIL'; exit 1 }
    Emit 'SELF_TEST' 'PASS'
    exit 0
}
$worktree = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$schema = 'C:\Project\doclinc-local-mirror\schema\alibaba-staging-schema-20260822-034559.sql'
$suffix = [guid]::NewGuid().ToString('N').Substring(0,8)
$database = 'doclinc_governance_test_runner_' + $suffix
$network = 'doclinc-governance-test'
$dbContainer = 'gov-db-runner'
$phpContainer = 'gov-php-runner'
$nginxContainer = 'gov-nginx-runner'
$statePath = Join-Path ([IO.Path]::GetTempPath()) ('doclinc-governance-' + $suffix + '.json')
$rootPassword = 'GvRunner_' + [guid]::NewGuid().ToString('N') + '!9'
$appPassword = 'ApRunner_' + [guid]::NewGuid().ToString('N') + '!7'
$adminPassword = 'Run' + [guid]::NewGuid().ToString('N') + '!9'
$baseUrl = 'https://localhost:18460/'
$runtimeOk = $false
$cookieJar = $null
try {
    if (-not (Test-Path $schema)) { throw 'schema_dump_missing' }
    $bootstrap = & powershell -NoProfile -ExecutionPolicy Bypass -File "$PSScriptRoot\bootstrap_mariadb.ps1" -DatabaseName $database -SchemaDumpPath $schema -NetworkName $network -ContainerName $dbContainer -RootPassword $rootPassword -AppPassword $appPassword -ProvisionApplication -EnablePlacement -ApplicationRoot $worktree -BaseUrl $baseUrl -OffBaseUrl 'https://localhost:18461/' -PhpContainerName $phpContainer -OffPhpContainerName 'gov-php-off-runner' -NginxContainerName $nginxContainer -OffNginxContainerName 'gov-nginx-off-runner' -HostPort 18460 -OffHostPort 18461 -DatabaseHostPort 13306 -RuntimeStatePath $statePath -KeepRuntime
    if ($LASTEXITCODE -ne 0) { throw 'runtime_bootstrap_failed' }
    docker network connect $network doclinc-local-php81 2>$null
    docker cp "$worktree\application\migrations\20260814000100_admin_capability_clinical_access_audit.php" doclinc-local-php81:/tmp/admin_runner.php
    docker cp "$worktree\application\migrations\20260825000100_nakes_facility_placement_transfer_foundation.php" doclinc-local-php81:/tmp/nakes_runner.php
    $adminEnv = @('-e','DOCLINC_ADMIN_SCHEMA_WRITE_ENABLED=true','-e','DOCLINC_ADMIN_DISPOSABLE_TEST=true','-e','DOCLINC_ADMIN_SCHEMA_DB_HOST=mariadb','-e','DOCLINC_ADMIN_SCHEMA_DB_PORT=3306','-e','DOCLINC_ADMIN_SCHEMA_DB_USER=gov_app',("-e=DOCLINC_ADMIN_SCHEMA_DB_PASSWORD=$appPassword"),'-e','DOCLINC_ADMIN_SCHEMA_ALLOWED_USERS=gov_app',("-e=DOCLINC_ADMIN_SCHEMA_DB_NAME=$database"))
    docker exec @adminEnv doclinc-local-php81 php /tmp/admin_runner.php --apply --environment=test --confirm-database=$database --confirm-disposable-test=true | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'admin_migration_failed' }
    $nakesEnv = @('-e','DOCLINC_NAKES_PLACEMENT_SCHEMA_WRITE_ENABLED=true','-e','DOCLINC_NAKES_PLACEMENT_DISPOSABLE_TEST=true','-e','DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_HOST=mariadb','-e','DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_PORT=3306','-e','DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_USER=gov_app',("-e=DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_PASSWORD=$appPassword"),'-e','DOCLINC_NAKES_PLACEMENT_SCHEMA_ALLOWED_USERS=gov_app',("-e=DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_NAME=$database"))
    docker exec @nakesEnv doclinc-local-php81 php /tmp/nakes_runner.php --apply --environment=test --confirm-database=$database --confirm-disposable-test=true | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'placement_migration_failed' }
    docker exec @adminEnv doclinc-local-php81 php /tmp/admin_runner.php --apply --environment=test --confirm-database=$database --confirm-disposable-test=true | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'admin_migration_rerun_failed' }
    docker exec @nakesEnv doclinc-local-php81 php /tmp/nakes_runner.php --apply --environment=test --confirm-database=$database --confirm-disposable-test=true | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'placement_migration_rerun_failed' }
    $seed = "INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (9900,'Synthetic Command Center','cc@runner.invalid','cc@runner.invalid','__ADMIN_HASH__','dokter','aktif',0,'PKM-SOURCE'),(9901,'Synthetic Admin','admin@runner.invalid','admin@runner.invalid','__ADMIN_HASH__','admin','aktif',0,'ADMIN'),(9902,'Synthetic Nakes','nakes@runner.invalid','nakes@runner.invalid','__ADMIN_HASH__','dokter','aktif',0,'PKM-SOURCE'),(9903,'Synthetic User','user@runner.invalid','user@runner.invalid','__ADMIN_HASH__','warga','aktif',0,''); INSERT INTO m_puskesmas(kode_pkm,nama_puskesmas,status) VALUES('PKM-SOURCE','Source','aktif'),('PKM-DEST','Dest','aktif'),('PKM-INACTIVE','Inactive','nonaktif'); INSERT INTO puskesmas_staff(staff_id,kode_pkm,user_id,nama,profesi,status) VALUES(9902,'PKM-SOURCE',9902,'Nakes','Perawat','aktif'); INSERT INTO nakes_facility_placements(staff_id,facility_code,effective_from,status,created_by_user_id) VALUES(9902,'PKM-SOURCE','2026-01-01','active',9901);"
    $seed64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($seed))
    $adminPassword64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($adminPassword))
    docker cp "$worktree\tools\governance_verification\seed_runtime.php" "${phpContainer}:/tmp/seed_runtime.php"
    docker cp "$worktree\tools\governance_verification\feature_probe.php" "${phpContainer}:/tmp/feature_probe.php"
    docker cp "$worktree\tools\governance_verification\feature_probe.php" "gov-php-off-runner:/tmp/feature_probe.php"
    $onFeature = (docker exec $phpContainer php /tmp/feature_probe.php 2>&1 | Out-String).Trim()
    $offFeature = (docker exec gov-php-off-runner php /tmp/feature_probe.php 2>&1 | Out-String).Trim()
    Emit 'APP_ON_FEATURE_STATE' $onFeature; Emit 'APP_OFF_FEATURE_STATE' $offFeature
    if ($onFeature -ne 'ON' -or $offFeature -ne 'OFF') { throw 'dual_runtime_feature_probe_failed' }
    $seedResult = docker exec -e DB_HOST=mariadb -e DB_USER=gov_app -e DB_PASS=$appPassword -e DB_NAME=$database -e SEED_SQL_B64=$seed64 -e SEED_ADMIN_PASSWORD_B64=$adminPassword64 $phpContainer php /tmp/seed_runtime.php 2>&1
    if ($LASTEXITCODE -ne 0) { throw ('fixture_seed_failed:' + (($seedResult -join ' ') -replace $appPassword, '<redacted>')) }
    docker cp "$worktree\tools\governance_verification\http_fixture.php" "${phpContainer}:/tmp/http_fixture_runner.php"
    docker cp "$worktree\tools\governance_verification\service_worker.php" "${phpContainer}:/tmp/service_worker.php"
    docker exec -e DB_HOST=mariadb -e DB_USER=gov_app -e DB_PASS=$appPassword -e DB_NAME=$database $phpContainer php /tmp/http_fixture_runner.php base 8801 | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'fixture_preflight_failed' }
    docker exec -e DB_HOST=mariadb -e DB_USER=gov_app -e DB_PASS=$appPassword -e DB_NAME=$database $phpContainer php /tmp/http_fixture_runner.php owner 8802 responsible_doctor_user_id | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'fixture_owner_preflight_failed' }
    docker exec -e DB_HOST=mariadb -e DB_USER=gov_app -e DB_PASS=$appPassword -e DB_NAME=$database $phpContainer php /tmp/http_fixture_runner.php cleanup 8801 | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'fixture_base_cleanup_failed' }
    docker exec -e DB_HOST=mariadb -e DB_USER=gov_app -e DB_PASS=$appPassword -e DB_NAME=$database $phpContainer php /tmp/http_fixture_runner.php cleanup 8802 | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'fixture_owner_cleanup_failed' }
    if ($env:GOVERNANCE_SESSION_STABILITY -eq 'true') {
        $probePath = Join-Path ([IO.Path]::GetTempPath()) ('doclinc-session-stability-' + $suffix + '.json')
        & powershell -NoProfile -ExecutionPolicy Bypass -File "$PSScriptRoot\session_stability_probe.ps1" -BaseUrl $baseUrl -PhpContainer $phpContainer -AdminEmail 'admin@runner.invalid' -AdminPassword $adminPassword -OutputPath $probePath
        $probeExit = $LASTEXITCODE
        if (Test-Path $probePath) { Get-Content $probePath | Write-Output; Remove-Item -LiteralPath $probePath -Force -ErrorAction SilentlyContinue }
        Emit 'AUTH_SESSION_STABILITY' ($(if ($probeExit -eq 0) {'PASS'} else {'FAIL'}))
        if ($probeExit -ne 0) { throw 'session_stability_failed' }
        if ($env:GOVERNANCE_STABILITY_ONLY -eq 'true') { Emit 'RUNTIME' 'PASS'; Emit 'GOVERNANCE_GATE' 'BLOCKED'; exit 0 }
    }
    $matrixBaseUrl = $baseUrl
    $cookieJar = Join-Path ([IO.Path]::GetTempPath()) ('doclinc-http-cookie-' + $suffix + '.txt')
    $env:GOVERNANCE_HTTP_MATRIX_READY = 'true'; $env:GOVERNANCE_BASE_URL = $matrixBaseUrl; $env:GOVERNANCE_BASE_URL_ON = $matrixBaseUrl; $env:GOVERNANCE_BASE_URL_OFF = 'https://localhost:18461/'; $env:GOVERNANCE_PHP_CONTAINER = $phpContainer; $env:GOVERNANCE_PHP_CONTAINER_OFF = 'gov-php-off-runner'; $env:DB_HOST = '127.0.0.1'; $env:DB_PORT = '13306'; $env:DB_USER = 'gov_app'; $env:DB_PASS = $appPassword; $env:DB_NAME = $database; $env:GOVERNANCE_ADMIN_EMAIL = 'admin@runner.invalid'; $env:GOVERNANCE_ADMIN_PASSWORD = $adminPassword; $env:GOVERNANCE_COOKIE_JAR = $cookieJar
    $matrix = & php "$PSScriptRoot\http_matrix.php" 2>&1
    $matrix | ForEach-Object { Write-Output $_ }
    $concurrency = & powershell -NoProfile -ExecutionPolicy Bypass -File "$PSScriptRoot\run_concurrency.ps1" -RuntimeDescriptorPath $statePath 2>&1
    $concurrency | ForEach-Object { Write-Output $_ }
    $concurrencyText = ($concurrency -join "`n")
    $activationConcurrency = if ($concurrencyText -match 'SERVICE_ACTIVATION_CONCURRENCY=PASS') { 'PASS' } else { 'FAIL' }
    $schedulingConcurrency = if ($concurrencyText -match 'SERVICE_SCHEDULING_CONCURRENCY=PASS') { 'PASS' } else { 'FAIL' }
    $matrixText = ($matrix -join "`n")
    $httpGate = if ($matrixText -match 'HTTP_MATRIX=17/17' -and $matrixText -match 'HTTP_CASES_FAIL=0' -and $activationConcurrency -eq 'PASS' -and $schedulingConcurrency -eq 'PASS') { 'PASS' } else { 'BLOCKED' }
    Emit 'RUNTIME' 'PASS'; Emit 'MARIADB_READINESS' 'PASS'; Emit 'APP_USER_AUTH' 'PASS'; Emit 'SCHEMA_IMPORT' 'PASS'; Emit 'ADMIN_MIGRATION' 'PASS'; Emit 'PLACEMENT_MIGRATION' 'PASS'; Emit 'EXACT_SCHEMA' 'PASS'; Emit 'PHP_FPM' 'PASS'; Emit 'NGINX' 'PASS'; Emit 'APPLICATION_DB' 'PASS'; Emit 'AUTHENTICATED_ADMIN_SESSION' 'NOT_PROVEN_BY_PRECHECK'; Emit 'FIXTURE_PREFLIGHT' 'PASS'; Emit 'FIXTURE_ATOMICITY' 'PASS'; Emit 'HTTP_GOVERNANCE_GATE' $httpGate; Emit 'SERVICE_ACTIVATION_CONCURRENCY' $activationConcurrency; Emit 'SERVICE_SCHEDULING_CONCURRENCY' $schedulingConcurrency; Emit 'GOVERNANCE_GATE' 'BLOCKED'; exit 2
} catch { Emit 'GOVERNANCE_GATE' 'BLOCKED'; Emit 'RUNTIME' 'FAIL'; Emit 'FAILURE_MAP' $_.Exception.Message; exit 2 }
finally {
    if ($env:GOVERNANCE_RETAIN_RUNTIME -ne 'true') {
        try { docker rm -f $nginxContainer gov-nginx-off-runner 2>$null | Out-Null } catch { }
        try { docker rm -f $phpContainer gov-php-off-runner 2>$null | Out-Null } catch { }
        try { docker rm -f $dbContainer 2>$null | Out-Null } catch { }
        docker network disconnect $network doclinc-local-php81 2>$null | Out-Null
        docker network rm $network 2>$null | Out-Null
        Remove-Item -Force -ErrorAction SilentlyContinue $statePath
        if ($cookieJar) { Remove-Item -Force -ErrorAction SilentlyContinue $cookieJar }
    }
}
