<?php
$migration = file_get_contents(dirname(__DIR__, 3) . '/application/migrations/20260814000100_admin_capability_clinical_access_audit.php');
function source_expect($ok, $label) { if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); } echo "PASS: {$label}\n"; }
source_expect(strpos($migration, 'doclinc_admin_capability_test') !== false, 'migration requires disposable test database');
source_expect(strpos($migration, 'doclinc_governance_test') !== false, 'migration accepts shared governance test database');
source_expect(strpos($migration, 'DROP ') === false && strpos($migration, 'TRUNCATE ') === false, 'migration has no destructive statements');
source_expect(strpos($migration, 'admin_user_capabilities') !== false && strpos($migration, 'clinical_access_audit') !== false, 'migration defines both capability tables');
source_expect(strpos($migration, 'GET_LOCK') !== false && strpos($migration, 'post_apply_verification_failed') !== false, 'migration uses lock and post-apply verification');
$bootstrap = file_get_contents(dirname(__DIR__) . '/bootstrap.php');
source_expect(strpos($bootstrap, 'BOOTSTRAP_SUPER_ADMIN') !== false && strpos($bootstrap, 'doclinc_admin_capability_test_') !== false, 'bootstrap is explicitly guarded and disposable-only');
source_expect(strpos($bootstrap, 'diagnosis') === false && strpos($bootstrap, 'anamnesis') === false, 'bootstrap contains no clinical payload');
$store = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Admin_capability_store.php');
source_expect(strpos($store, 'mutateWithAudit') !== false && strpos($store, 'trans_begin') !== false, 'capability mutations use transactional audit boundary');
$ledger = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Clinical_access_audit_ledger.php');
$report = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Clinical_access_audit_report_service.php');
source_expect(strpos($ledger, '!$authorized') !== false && strpos($report, 'hasCapability') !== false, 'clinical audit reporting has capability boundary');
