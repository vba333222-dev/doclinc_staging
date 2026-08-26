param(
    [Parameter(Mandatory = $true)][string]$DatabaseName,
    [Parameter(Mandatory = $true)][string]$SchemaDumpPath,
    [string]$NetworkName = 'doclinc-governance-test',
    [string]$ContainerName = 'mariadb',
    [string]$RootPassword,
    [string]$AppPassword,
    [switch]$KeepRuntime,
    [switch]$ProvisionApplication,
    [switch]$EnablePlacement,
    [string]$ApplicationRoot,
    [string]$BaseUrl = 'https://localhost:18460/',
    [string]$OffBaseUrl = 'https://localhost:18461/',
    [string]$PhpContainerName = 'gov-php-runtime',
    [string]$OffPhpContainerName = 'gov-php-off-runtime',
    [string]$NginxContainerName = 'gov-nginx-runtime',
    [string]$OffNginxContainerName = 'gov-nginx-off-runtime',
    [int]$HostPort = 18460,
    [int]$OffHostPort = 18461,
    [int]$DatabaseHostPort = 13306,
    [string]$RuntimeStatePath
)

$ErrorActionPreference = 'Stop'
if ($DatabaseName -notmatch '^doclinc_governance_test_[a-z0-9_]+$') {
    throw 'database_name_not_allowlisted'
}
if (-not (Test-Path -LiteralPath $SchemaDumpPath -PathType Leaf)) {
    throw 'schema_dump_missing'
}

if ([string]::IsNullOrWhiteSpace($RootPassword)) { $RootPassword = 'Gv' + [guid]::NewGuid().ToString('N') + '!9' }
if ([string]::IsNullOrWhiteSpace($AppPassword)) { $AppPassword = 'Ap' + [guid]::NewGuid().ToString('N') + '!7' }
$rootPassword = $RootPassword
$appPassword = $AppPassword
$startedAt = Get-Date

try { docker rm -f $ContainerName 2>$null | Out-Null } catch { }
try { docker network rm $NetworkName 2>$null | Out-Null } catch { }
docker network create $NetworkName | Out-Null
docker run -d --name $ContainerName --network $NetworkName --network-alias mariadb -p ("${DatabaseHostPort}:3306") -e ("MARIADB_ROOT_PASSWORD=$rootPassword") mariadb:10.11 | Out-Null

$ready = $false
$readinessFirstAttempt = $null
$readinessSuccess = $null
for ($attempt = 1; $attempt -le 60; $attempt++) {
    if ($null -eq $readinessFirstAttempt) { $readinessFirstAttempt = Get-Date }
    try { $probe = docker exec $ContainerName sh -lc 'mariadb-admin --protocol=tcp -h127.0.0.1 -uroot -p"$MARIADB_ROOT_PASSWORD" ping' 2>&1 } catch { $probe = @($_.Exception.Message) }
    if ($LASTEXITCODE -eq 0 -and ($probe -join ' ') -match 'alive') {
        $ready = $true
        $readinessSuccess = Get-Date
        break
    }
    Start-Sleep -Milliseconds 500
}
if (-not $ready) {
    docker rm -f $ContainerName | Out-Null
    docker network rm $NetworkName | Out-Null
    throw 'mariadb_readiness_timeout'
}

$sql = @"
CREATE DATABASE IF NOT EXISTS $DatabaseName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'gov_app'@'%' IDENTIFIED BY '$appPassword';
ALTER USER 'gov_app'@'%' IDENTIFIED BY '$appPassword';
GRANT ALL PRIVILEGES ON $DatabaseName.* TO 'gov_app'@'%';
FLUSH PRIVILEGES;
"@
$sql64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($sql))
$bootstrapAt = Get-Date
docker exec $ContainerName sh -lc ('echo ' + $sql64 + ' | base64 -d | mariadb --protocol=tcp -h127.0.0.1 -uroot -p"$MARIADB_ROOT_PASSWORD"') | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'bootstrap_database_operation_failed' }

Get-Content -LiteralPath $SchemaDumpPath -Raw | docker exec -i -e ("GOV_APP_PASSWORD=$appPassword") $ContainerName sh -lc ('mariadb --protocol=tcp -h127.0.0.1 -ugov_app -p"$GOV_APP_PASSWORD" ' + $DatabaseName)
if ($LASTEXITCODE -ne 0) { throw 'application_user_schema_import_failed' }

$appAuth = docker exec -e ("GOV_APP_PASSWORD=$appPassword") $ContainerName sh -lc 'mariadb-admin --protocol=tcp -h127.0.0.1 -ugov_app -p"$GOV_APP_PASSWORD" ping' 2>&1
if ($LASTEXITCODE -ne 0 -or ($appAuth -join ' ') -notmatch 'alive') { throw 'application_user_auth_failed' }

$runtimeDescriptor = $null
if ($ProvisionApplication) {
    if ([string]::IsNullOrWhiteSpace($ApplicationRoot) -or -not (Test-Path -LiteralPath $ApplicationRoot -PathType Container)) { throw 'application_root_missing' }
    $sessionPath = Join-Path ([System.IO.Path]::GetTempPath()) ('doclinc-sessions-' + [guid]::NewGuid().ToString('N'))
    $logPath = Join-Path ([System.IO.Path]::GetTempPath()) ('doclinc-logs-' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Force $sessionPath,$logPath | Out-Null
    $placementValue = if ($EnablePlacement) { 'true' } else { 'false' }
    docker run -d --name $PhpContainerName --network $NetworkName --network-alias $PhpContainerName -v ("${ApplicationRoot}:/var/www/html:ro") -v ("${sessionPath}:/var/run/doclinc/sessions") -v ("${logPath}:/var/log/doclinc") -e "ADMIN_DB_HOST=mariadb" -e "ADMIN_DB_NAME=$DatabaseName" -e "ADMIN_DB_USER=gov_app" -e "ADMIN_DB_PASS=$appPassword" -e "DB_HOST=mariadb" -e "DB_NAME=$DatabaseName" -e "DB_USER=gov_app" -e "DB_PASS=$appPassword" -e "DOCLINC_BASE_URL=$BaseUrl" -e "ADMIN_BASE_URL=${BaseUrl}admin_menu/" -e "DOCLINC_SESSION_PATH=/var/run/doclinc/sessions" -e 'DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT=staging' -e "DOCLINC_NAKES_PLACEMENT_ENABLED=$placementValue" -e 'DOCLINC_NAKES_PLACEMENT_ENVIRONMENT=staging' compose-php81:latest | Out-Null
    $nginxConfig = Join-Path ([System.IO.Path]::GetTempPath()) ('doclinc-nginx-' + [guid]::NewGuid().ToString('N') + '.conf')
    "server { listen 443 ssl; server_name localhost; root /var/www/html; index index.php; ssl_certificate /etc/nginx/certs/localhost.crt; ssl_certificate_key /etc/nginx/certs/localhost.key; location = /admin_menu/index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME /var/www/html/admin_menu/index.php; fastcgi_param HTTPS on; fastcgi_pass $PhpContainerName`:9000; } location ^~ /admin_menu/ { try_files `$uri `$uri/ /admin_menu/index.php?`$query_string; } location / { try_files `$uri `$uri/ /index.php?`$query_string; } location ~ \.php`$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME `$document_root`$fastcgi_script_name; fastcgi_param HTTPS on; fastcgi_pass $PhpContainerName`:9000; } }" | Set-Content -Encoding ascii $nginxConfig
    docker run -d --name $NginxContainerName --network $NetworkName -v ("${nginxConfig}:/etc/nginx/conf.d/default.conf:ro") -v 'C:\Project\doclinc-local-mirror\compose\certs\localhost.crt:/etc/nginx/certs/localhost.crt:ro' -v 'C:\Project\doclinc-local-mirror\compose\certs\localhost.key:/etc/nginx/certs/localhost.key:ro' -v ("${ApplicationRoot}:/var/www/html:ro") -p ("${HostPort}:443") nginx:1.30-alpine | Out-Null
    $offSessionPath = Join-Path ([System.IO.Path]::GetTempPath()) ('doclinc-sessions-off-' + [guid]::NewGuid().ToString('N'))
    $offLogPath = Join-Path ([System.IO.Path]::GetTempPath()) ('doclinc-logs-off-' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Force $offSessionPath,$offLogPath | Out-Null
    docker run -d --name $OffPhpContainerName --network $NetworkName --network-alias $OffPhpContainerName -v ("${ApplicationRoot}:/var/www/html:ro") -v ("${offSessionPath}:/var/run/doclinc/sessions") -v ("${offLogPath}:/var/log/doclinc") -e "ADMIN_DB_HOST=mariadb" -e "ADMIN_DB_NAME=$DatabaseName" -e "ADMIN_DB_USER=gov_app" -e "ADMIN_DB_PASS=$appPassword" -e "DB_HOST=mariadb" -e "DB_NAME=$DatabaseName" -e "DB_USER=gov_app" -e "DB_PASS=$appPassword" -e "DOCLINC_BASE_URL=$OffBaseUrl" -e "ADMIN_BASE_URL=${OffBaseUrl}admin_menu/" -e "DOCLINC_SESSION_PATH=/var/run/doclinc/sessions" -e 'DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT=staging' -e 'DOCLINC_NAKES_PLACEMENT_ENABLED=false' -e 'DOCLINC_NAKES_PLACEMENT_ENVIRONMENT=staging' compose-php81:latest | Out-Null
    $offNginxConfig = Join-Path ([System.IO.Path]::GetTempPath()) ('doclinc-nginx-off-' + [guid]::NewGuid().ToString('N') + '.conf')
    "server { listen 443 ssl; server_name localhost; root /var/www/html; index index.php; ssl_certificate /etc/nginx/certs/localhost.crt; ssl_certificate_key /etc/nginx/certs/localhost.key; location = /admin_menu/index.php { include fastcgi_params; fastcgi_param SCRIPT_FILENAME /var/www/html/admin_menu/index.php; fastcgi_param HTTPS on; fastcgi_pass $OffPhpContainerName`:9000; } location ^~ /admin_menu/ { try_files `$uri `$uri/ /admin_menu/index.php?`$query_string; } location / { try_files `$uri `$uri/ /index.php?`$query_string; } location ~ \.php`$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME `$document_root`$fastcgi_script_name; fastcgi_param HTTPS on; fastcgi_pass $OffPhpContainerName`:9000; } }" | Set-Content -Encoding ascii $offNginxConfig
    docker run -d --name $OffNginxContainerName --network $NetworkName -v ("${offNginxConfig}:/etc/nginx/conf.d/default.conf:ro") -v 'C:\Project\doclinc-local-mirror\compose\certs\localhost.crt:/etc/nginx/certs/localhost.crt:ro' -v 'C:\Project\doclinc-local-mirror\compose\certs\localhost.key:/etc/nginx/certs/localhost.key:ro' -v ("${ApplicationRoot}:/var/www/html:ro") -p ("${OffHostPort}:443") nginx:1.30-alpine | Out-Null
    $runtimeDescriptor = [pscustomobject]@{ runtime_id = [guid]::NewGuid().ToString('N'); database = $DatabaseName; network = $NetworkName; base_url = $BaseUrl; base_url_on = $BaseUrl; base_url_off = $OffBaseUrl; mariadb_container = $ContainerName; php_container = $PhpContainerName; nginx_container = $NginxContainerName; off_php_container = $OffPhpContainerName; off_nginx_container = $OffNginxContainerName; session_path = $sessionPath; off_session_path = $offSessionPath; log_path = $logPath; off_log_path = $offLogPath; nginx_config = $nginxConfig; off_nginx_config = $offNginxConfig; cleanup_owned = $true }
    if ($RuntimeStatePath) { $runtimeDescriptor | ConvertTo-Json | Set-Content -Encoding utf8 $RuntimeStatePath }
}

[pscustomobject]@{
    CONTAINER_STARTED_AT = $startedAt.ToString('o')
    READINESS_FIRST_ATTEMPT_AT = $readinessFirstAttempt.ToString('o')
    READINESS_SUCCESS_AT = $readinessSuccess.ToString('o')
    BOOTSTRAP_DB_OPERATION_AT = $bootstrapAt.ToString('o')
    MARIADB_HEALTHY = $ready
    ROOT_BOOTSTRAP_AUTH = $true
    APP_DATABASE_CREATED = $true
    APP_USER_CREATED = $true
    APP_USER_AUTH = $true
    DATABASE_NAME = $DatabaseName
    NETWORK_NAME = $NetworkName
    CONTAINER_NAME = $ContainerName
    APPLICATION_PROVISIONED = [bool]$ProvisionApplication
    RUNTIME_DESCRIPTOR = $runtimeDescriptor
    APP_USER = 'gov_app'
} | ConvertTo-Json -Compress

if (-not $KeepRuntime) {
    if ($runtimeDescriptor) {
        docker rm -f $NginxContainerName,$PhpContainerName,$OffNginxContainerName,$OffPhpContainerName 2>$null | Out-Null
        Remove-Item -Force -ErrorAction SilentlyContinue $runtimeDescriptor.nginx_config,$runtimeDescriptor.off_nginx_config
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue $runtimeDescriptor.session_path,$runtimeDescriptor.log_path,$runtimeDescriptor.off_session_path,$runtimeDescriptor.off_log_path
    }
    docker rm -f $ContainerName | Out-Null
    docker network rm $NetworkName | Out-Null
}
