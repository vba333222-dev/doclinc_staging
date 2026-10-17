<?php
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/libraries/Doclinc_feature_flags.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Admin_capability_flags.php';

$keys = array(
    'DOCLINC_ADMIN_CAPABILITIES_ENABLED',
    'DOCLINC_ADMIN_CAPABILITIES_ENVIRONMENT',
    'DOCLINC_CLINICAL_AUDIT_ENABLED',
    'DOCLINC_CLINICAL_AUDIT_ENVIRONMENT',
    'DOCLINC_RUNTIME_ENVIRONMENT',
);

// The ambient deployment environment must not decide this test. The default-off
// contract is about an *unset* flag, and the staging mirror is deliberately
// configured with these flags on, so every input is cleared first.
$saved = array();
foreach ($keys as $key) {
    $saved[$key] = getenv($key);
    putenv($key . '=');
}

$flags = Admin_capability_flags::resolve('staging');
if ($flags['capabilities']['enabled'] !== false || $flags['clinical_audit']['enabled'] !== false) {
    fwrite(STDERR, "FAIL: flags default off\n");
    exit(1);
}
if ($flags['capabilities']['reason'] !== 'flag_missing_or_malformed') {
    fwrite(STDERR, "FAIL: unset flag reason is {$flags['capabilities']['reason']}\n");
    exit(1);
}
echo "PASS: flags default off\n";

// A matching enabled/environment pair turns them on.
putenv('DOCLINC_ADMIN_CAPABILITIES_ENABLED=true');
putenv('DOCLINC_ADMIN_CAPABILITIES_ENVIRONMENT=staging');
putenv('DOCLINC_CLINICAL_AUDIT_ENABLED=true');
putenv('DOCLINC_CLINICAL_AUDIT_ENVIRONMENT=staging');
$enabled = Admin_capability_flags::resolve('staging');
if ($enabled['capabilities']['enabled'] !== true || $enabled['clinical_audit']['enabled'] !== true) {
    fwrite(STDERR, "FAIL: matching pair enables flags\n");
    exit(1);
}
echo "PASS: matching pair enables flags\n";

// A runtime environment that does not match the feature environment keeps them
// off, which is the failure mode that silently disabled them before.
$mismatch = Admin_capability_flags::resolve('uat');
if ($mismatch['capabilities']['enabled'] !== false || $mismatch['clinical_audit']['enabled'] !== false) {
    fwrite(STDERR, "FAIL: mismatched runtime environment disables flags\n");
    exit(1);
}
if ($mismatch['capabilities']['reason'] !== 'environment_mismatch') {
    fwrite(STDERR, "FAIL: mismatched flag reason is {$mismatch['capabilities']['reason']}\n");
    exit(1);
}
echo "PASS: mismatched runtime environment disables flags\n";

// An unset runtime environment also keeps them off.
$noRuntime = Admin_capability_flags::resolve('');
if ($noRuntime['capabilities']['enabled'] !== false || $noRuntime['clinical_audit']['enabled'] !== false) {
    fwrite(STDERR, "FAIL: unset runtime environment disables flags\n");
    exit(1);
}
echo "PASS: unset runtime environment disables flags\n";

foreach ($keys as $key) {
    putenv($key . '=' . ($saved[$key] === false ? '' : $saved[$key]));
}

