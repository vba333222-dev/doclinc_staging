# Runs every test file under tools/ and reports an honest pass/fail/skip tally.
#
# Methodology rules this runner enforces:
#   * Every test file is executed, not just the ones a change touches.
#   * A suite that SKIPs is reported as UNVERIFIED, never as passing.
#   * PHP suites run on the project runtime (PHP 8.1 in doclinc-local-php81), not
#     the host PHP. The host's 8.4 emits a dynamic-property deprecation on stdout
#     that corrupts the phase4_5 child-process token parsing and fails three
#     concurrency assertions spuriously.
#   * tools/visit_clinical_workflow refuses non-loopback database hosts, so its
#     runner executes on the host where 127.0.0.1:33317 is the published port.

[CmdletBinding()]
param(
    [string]$ReportPath = 'C:\Users\USER\AppData\Local\Temp\platform_parity_sweep.txt'
)

$ErrorActionPreference = 'Continue'
$repo = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$tools = Join-Path $repo 'tools'

$phpContainer = 'doclinc-local-php81'
$envFile = 'C:\Project\doclinc-local-mirror\secrets\local-stage-schema.env'

# Suites must see the same feature configuration the application runs with,
# otherwise a flag-dependent suite fails for the wrong reason. Only DOCLINC_*
# values are forwarded: the live DB_* credentials are deliberately withheld so no
# suite can ever reach the staging schema, which is read-only and must stay that
# way.
$flagEnv = @{}
if (Test-Path $envFile) {
    foreach ($line in Get-Content $envFile) {
        if ($line -match '^\s*(DOCLINC_[A-Z0-9_]+)\s*=\s*(.*)$') {
            $flagEnv[$Matches[1]] = $Matches[2].Trim()
        }
    }
}

$dbEnv = @{
    DOCLINC_TEST_DB_ADMIN_HOST     = 'host.docker.internal'
    DOCLINC_TEST_DB_ADMIN_PORT     = '33317'
    DOCLINC_TEST_DB_ADMIN_USER     = 'root'
    DOCLINC_TEST_DB_ADMIN_PASSWORD = 'root_test_only'
}
foreach ($key in $flagEnv.Keys) { $dbEnv[$key] = $flagEnv[$key] }

$vcwEnv = @{
    VCW_DB_HOST     = '127.0.0.1'
    VCW_DB_PORT     = '33317'
    VCW_DB_NAME     = 'doclinc_visit_test'
    VCW_DB_USER     = 'doclinc_test'
    VCW_DB_PASSWORD = 'doclinc_test_only'
}

# Suites that cannot pass on this host, with the verified reason. They are still
# executed; the verdict is only downgraded to UNVERIFIED so the reason is
# recorded instead of being silently lost.
#
# Docker Desktop presents host files to the container as mode 0777, so
# is_executable() is true for every repository file. ClinicalPackagePathPolicy
# correctly refuses executable package files, and ClinicalSuggestionPackage::load
# shells out to that same inspector, so both suites fail on the mount rather than
# on anything in the code. Verified: with DOCLINK_CLINICAL_PACKAGE_ALLOWED_ROOTS
# set, the inspector reports
#   error_1=package_executable_file_rejected, path=00_icd10_manifest.json
# and `php -r 'is_executable(".../00_manifest.json")'` prints TRUE.
$environmentBlocked = @{
    'tools\clinical_package\tests\run.php' = 'windows_bind_mount_reports_every_file_executable'
    'tools\clinical_suggestions\tests\integration.php' = 'windows_bind_mount_reports_every_file_executable'
    'tools\task11_browser\tests\config_safety_test.js' = 'playwright_not_installed'
    'tools\visit_clinical_workflow\tests\clinical_http_actor_matrix_test.php' = 'requires_external_real_ci3_http_server_via_VCW_HTTP_BASE_URL'
}

# Suites that need arguments to run at all. Without them they exit 2 with
# PACKAGE_ROOT_REQUIRED, which is not a skip and must not be reported as one.
$suiteArguments = @{
    'tools\clinical_suggestions\tests\integration.php' = @('--package-root=/var/www/html/database/master_data/clinical/v1')
    'tools\visit_clinical_workflow\tests\run.php' = @('all')
}

# Extra environment a specific suite needs beyond the shared flags.
$suiteEnvironment = @{
    'tools\clinical_suggestions\tests\integration.php' = @{
        DOCLINK_CLINICAL_PACKAGE_ALLOWED_ROOTS = '/var/www/html/database/master_data/clinical'
        DOCLINC_CLINICAL_SUGGESTION_DB_HOST     = 'host.docker.internal'
        DOCLINC_CLINICAL_SUGGESTION_DB_PORT     = '33317'
        DOCLINC_CLINICAL_SUGGESTION_DB_USER     = 'root'
        DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD = 'root_test_only'
        DOCLINC_CLINICAL_SUGGESTION_DB_NAME     = 'doclinc_visit_test'
    }
}

# Support files are included by suites rather than executed directly.
$skipNames = @(
    'assert.php', 'bootstrap.php', 'ci3_bootstrap.php', 'fixtures.php',
    'acceptance_environment.php', 'clinical_http_acceptance_support.php',
    'result_submission_support.php', 'MariaDbReadiness.php', 'support.php'
)

function Get-TestFiles {
    Get-ChildItem -Path $tools -Recurse -File -Include '*.js', '*.php' -ErrorAction SilentlyContinue |
        Where-Object { $_.DirectoryName -match '\\tests$' } |
        Where-Object { $_.Name -match '(_test\.js|_test\.php|^unit\.php$|^integration\.php$|^source_test\.php$|^run\.php$)' } |
        Where-Object { $skipNames -notcontains $_.Name } |
        Where-Object { $_.Name -notmatch '_worker\.php$' } |
        Where-Object { -not (Test-IncludeOnlyTest $_.FullName) } |
        Sort-Object FullName
}

# tools/visit_clinical_workflow ships one entry point (run.php <mode>) that
# requires each *_test.php file with its own bootstrap. Executing those files
# directly fails by construction, so only the runner and the genuinely standalone
# suites in that directory are executed on their own.
function Test-IncludeOnlyTest {
    param([string]$FullName)
    $relative = $FullName.Substring($repo.Length + 1).Replace('\', '/')
    if ($relative -notmatch '^tools/visit_clinical_workflow/tests/') { return $false }
    $name = Split-Path $FullName -Leaf
    if ($name -eq 'run.php') { return $false }
    if ($name -eq 'clinical_http_actor_matrix_test.php') { return $false }
    return $true
}

/** Runs a process with a hard timeout so one hanging suite cannot stall the sweep. */
function Invoke-WithTimeout {
    param([string]$FilePath, [string[]]$Arguments, [int]$TimeoutSeconds = 420)
    $stdout = [System.IO.Path]::GetTempFileName()
    $stderr = [System.IO.Path]::GetTempFileName()
    $process = $null
    try {
        $process = Start-Process -FilePath $FilePath -ArgumentList $Arguments -NoNewWindow -PassThru `
            -RedirectStandardOutput $stdout -RedirectStandardError $stderr
        $exited = $process.WaitForExit($TimeoutSeconds * 1000)
        $text = ((Get-Content $stdout -Raw -ErrorAction SilentlyContinue) + "`n" +
                 (Get-Content $stderr -Raw -ErrorAction SilentlyContinue))
        if (-not $exited) {
            try { $process.Kill($true) } catch { }
            return @{ Exit = 124; Output = "$text`nSUITE_TIMEOUT after ${TimeoutSeconds}s"; TimedOut = $true }
        }
        return @{ Exit = $process.ExitCode; Output = $text; TimedOut = $false }
    } catch {
        return @{ Exit = 125; Output = "SUITE_LAUNCH_FAILED: $($_.Exception.Message)"; TimedOut = $false }
    } finally {
        Remove-Item $stdout, $stderr -Force -ErrorAction SilentlyContinue
    }
}

function Invoke-JsTest {
    param([string]$Path)
    return Invoke-WithTimeout -FilePath 'node' -Arguments @($Path)
}

function Invoke-PhpTest {
    param([string]$Path, [hashtable]$Environment, [bool]$InContainer)
    $relative = $Path.Substring($repo.Length + 1).Replace('\', '/')
    $extra = @()
    $key = $Path.Substring($repo.Length + 1)
    if ($suiteArguments.ContainsKey($key)) { $extra = $suiteArguments[$key] }

    # Merge the shared flags with any suite-specific environment.
    $merged = @{}
    foreach ($name in $Environment.Keys) { $merged[$name] = $Environment[$name] }
    if ($suiteEnvironment.ContainsKey($key)) {
        foreach ($name in $suiteEnvironment[$key].Keys) { $merged[$name] = $suiteEnvironment[$key][$name] }
    }

    if ($InContainer) {
        $arguments = @('exec', '-w', '/var/www/html')
        foreach ($name in $merged.Keys) { $arguments += @('-e', "$name=$($merged[$name])") }
        $arguments += @($phpContainer, 'php', $relative)
        $arguments += $extra
        return Invoke-WithTimeout -FilePath 'docker' -Arguments $arguments
    }
    foreach ($name in $merged.Keys) { Set-Item -Path "env:$name" -Value $merged[$name] }
    return Invoke-WithTimeout -FilePath 'php' -Arguments (@($relative) + $extra)
}

"" | Out-File $ReportPath
$results = @()

$files = Get-TestFiles
"Discovered $($files.Count) test files" | Add-Content $ReportPath
"" | Add-Content $ReportPath

foreach ($file in $files) {
    $relative = $file.FullName.Substring($repo.Length + 1)
    $isJs = $file.Extension -eq '.js'
    # tools/visit_clinical_workflow refuses non-loopback database hosts, so its
    # suites run on the host where 127.0.0.1:33317 is the published port.
    $isVcw = $relative -match 'visit_clinical_workflow'

    if ($isJs) {
        $run = Invoke-JsTest -Path $file.FullName
    } elseif ($isVcw) {
        $run = Invoke-PhpTest -Path $file.FullName -Environment $vcwEnv -InContainer $false
    } else {
        $run = Invoke-PhpTest -Path $file.FullName -Environment $dbEnv -InContainer $true
    }

    $text = $run.Output
    # A suite-level skip is only claimed when the suite says so at suite level.
    # Matching a bare "=SKIP" anywhere was wrong: suites legitimately report SKIP
    # for individual unsupported cases while the suite itself fails, which is how
    # clinical_schema was being mis-reported as a skip while emitting
    # TEST_RESULT=FAIL.
    $suiteSkip = $text -match 'SAFE_ERROR_CODE=|_SKIPPED=[1-9]|SUITE=SKIP|^\s*SKIP\s*$'
    # An explicit failure verdict always wins over a skip. The token pattern is
    # general on purpose: any uppercase suite verdict ending in =FAIL is a
    # self-declared failure. Enumerating tokens was wrong and hid a real failure:
    # nakes_import emits NAKES_IMPORT_UNIT=FAIL together with
    # SAFE_ERROR_CODE=test_source_required, and because only TEST_RESULT/RESULT/
    # INTEGRATION were listed, the suite was reported as UNVERIFIED_SKIP while it
    # was actually failing.
    #
    # Anchored to the start of a line. A suite verdict is printed on its own
    # line; matching anywhere made a suite that merely *mentions* =FAIL in its
    # output look like it failed. The parity runner's own verdict test was
    # misclassified for exactly that reason.
    $explicitFail = $text -match '(?m)^\s*[A-Z0-9_]+=FAIL\b|_FAILED=[1-9]'
    $blocked = $text -match '=BLOCKED|MODULE_NOT_FOUND'
    $blockedReason = $environmentBlocked[$relative]

    if ($run.TimedOut) { $status = 'TIMEOUT' }
    elseif ($blocked -or ($blockedReason -and $run.Exit -ne 0)) { $status = 'UNVERIFIED_ENV' }
    elseif ($explicitFail) { $status = 'FAIL' }
    elseif ($suiteSkip) { $status = 'UNVERIFIED_SKIP' }
    elseif ($run.Exit -eq 0) { $status = 'PASS' }
    else { $status = 'FAIL' }

    $summary = ($text -split "`n" |
        Where-Object { $_ -match 'PASSED=|FAILED=|_PASS\b|_FAIL\b|_PASS=|_FAIL=|TASK13_ALL|RESULT=|FAIL:|Fatal error' } |
        Select-Object -Last 3) -join ' | '

    $results += [pscustomobject]@{
        File = $relative; Status = $status; Exit = $run.Exit; Summary = $summary.Trim()
    }
    "{0,-16} {1}" -f $status, $relative | Add-Content $ReportPath
    if ($status -ne 'PASS') {
        if ($summary -ne '') { "                 $summary" | Add-Content $ReportPath }
        if ($blockedReason) { "                 reason: $blockedReason" | Add-Content $ReportPath }
    }
}

"" | Add-Content $ReportPath
"=== tally ===" | Add-Content $ReportPath
foreach ($group in $results | Group-Object Status) {
    "  {0,-16} {1}" -f $group.Name, $group.Count | Add-Content $ReportPath
}
"" | Add-Content $ReportPath
"=== not passing ===" | Add-Content $ReportPath
$results | Where-Object { $_.Status -ne 'PASS' } |
    ForEach-Object { "  {0,-16} {1}" -f $_.Status, $_.File } | Add-Content $ReportPath

Write-Host "sweep complete -> $ReportPath"
