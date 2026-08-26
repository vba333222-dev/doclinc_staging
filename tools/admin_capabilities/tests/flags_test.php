<?php
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/libraries/Doclinc_feature_flags.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Admin_capability_flags.php';
$flags = Admin_capability_flags::resolve('staging');
if ($flags['capabilities']['enabled'] !== false || $flags['clinical_audit']['enabled'] !== false) { fwrite(STDERR, "FAIL: flags default off\n"); exit(1); }
echo "PASS: flags default off\n";
