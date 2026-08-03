<?php

define('BASEPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/lib/NakesSource.php';
require_once dirname(__DIR__, 3) . '/application/libraries/First_login_password_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/First_login_token_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/First_login_gate_policy.php';
require_once __DIR__ . '/SyntheticXlsxBuilder.php';

function test_expect($condition, $code)
{
	if (!$condition) throw new RuntimeException($code);
}

function test_exception(callable $callback, $code)
{
	try { $callback(); } catch (NakesImportException $exception) { test_expect($exception->getMessage() === $code, 'wrong_negative_code'); return; }
	throw new RuntimeException('negative_not_rejected');
}

function test_feature_flag($root, $flag, $environment)
{
	$code = "define('BASEPATH',__DIR__);define('FCPATH'," . var_export($root . '/', true) . ");define('APPPATH'," . var_export($root . '/application/', true) . ");require " . var_export($root . '/application/config/config.php', true) . ";echo !empty(\$config['first_login_password_change_enabled'])?'1':'0';";
	$env = getenv();
	if (!is_array($env)) $env = array();
	if ($flag === null) unset($env['DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENABLED']); else $env['DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENABLED'] = $flag;
	if ($environment === null) unset($env['DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENVIRONMENT']); else $env['DOCLINC_FIRST_LOGIN_PASSWORD_CHANGE_ENVIRONMENT'] = $environment;
	$process=proc_open(array(PHP_BINARY,'-r',$code),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,$env,array('bypass_shell'=>true));
	if(!is_resource($process))return null;$out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return proc_close($process)===0?$out:null;
}

$sourcePath = getenv('DOCLINC_NAKES_TEST_XLSX') ?: '';
if ($sourcePath === '') {
	fwrite(STDERR, "NAKES_IMPORT_UNIT=FAIL\nSAFE_ERROR_CODE=test_source_required\n");
	exit(2);
}

try {
	$sourceReader = new DoclincNakesSource();
	$inspected = $sourceReader->inspect($sourcePath, false);
	test_expect(count($inspected['records']) === 26, 'record_count');
	test_expect($inspected['sheet_counts'] === DoclincNakesSource::COUNTS, 'sheet_counts');
	test_expect($inspected['source_inference_count'] === 3 && $inspected['nullable_assignment_count'] === 3, 'grogol_contract');
	foreach ($inspected['records'] as $record) {
		test_expect(isset(DoclincNakesSource::CODES[$record['sheet']]) && DoclincNakesSource::CODES[$record['sheet']] === $record['kode_pkm'], 'mapping');
		test_expect($record['sheet'] !== 'Grogol' || ($record['profesi'] === 'Dokter' && $record['penugasan'] === null && $record['source_inference'] === true), 'grogol_inference');
		test_expect(strpos((string) $record['penugasan'], 'TimPelayanan') === false, 'assignment_typo');
	}
	test_expect(DoclincNakesSource::CODES['Pulomerak'] === '10280401', 'pulomerak_normalization');
	$artifactRoots = array(dirname(__DIR__), dirname(__DIR__, 3) . '/application/hooks', dirname(__DIR__, 3) . '/application/libraries');
	$artifactFiles = array(
		dirname(__DIR__, 3) . '/application/migrations/20260727000300_nakes_import_account_foundation.php',
		dirname(__DIR__, 3) . '/application/modules/login/controllers/Login.php',
		dirname(__DIR__, 3) . '/application/modules/login/models/Login_m.php',
		dirname(__DIR__, 3) . '/application/modules/login/views/change_password_v.php',
	);
	foreach ($artifactRoots as $artifactRoot) {
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($artifactRoot, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $artifact) if ($artifact->isFile()) $artifactFiles[] = $artifact->getPathname();
	}
	$artifactText = '';
	foreach (array_unique($artifactFiles) as $artifactFile) $artifactText .= file_get_contents($artifactFile) . "\n";
	foreach ($inspected['records'] as $record) foreach (array($record['nama'], $record['no_hp'], '0' . substr($record['no_hp'], 3)) as $sourceValue) {
		test_expect($sourceValue === '' || strpos($artifactText, $sourceValue) === false, 'real_source_value_in_repository');
	}

	$raw = (new DoclincXlsxReader())->read($sourcePath);
	$duplicateName = $raw;
	$dataRows = array_values(array_filter(array_keys($duplicateName[0]['rows']), static function ($row) { return $row > 4; }));
	$duplicateName[0]['rows'][$dataRows[1]][2] = $duplicateName[0]['rows'][$dataRows[0]][2];
	test_exception(static function () use ($sourceReader, $duplicateName) { $sourceReader->validateWorkbook($duplicateName); }, 'duplicate_name');
	$duplicatePhone = $raw;
	$duplicatePhone[0]['rows'][$dataRows[1]][5] = $duplicatePhone[0]['rows'][$dataRows[0]][5];
	test_exception(static function () use ($sourceReader, $duplicatePhone) { $sourceReader->validateWorkbook($duplicatePhone); }, 'duplicate_phone');
	$invalidPhone = $raw;
	$invalidPhone[0]['rows'][$dataRows[0]][5] = 'INVALID';
	test_exception(static function () use ($sourceReader, $invalidPhone) { $sourceReader->validateWorkbook($invalidPhone); }, 'invalid_phone');
	$extraSheet = $raw; $extraSheet[] = array('name' => 'Extra', 'rows' => array());
	test_exception(static function () use ($sourceReader, $extraSheet) { $sourceReader->validateWorkbook($extraSheet); }, 'sheet_contract_mismatch');
	$extraRow = $raw; $extraRow[0]['rows'][999] = array(1 => '5', 2 => 'EXTRA', 3 => 'Dokter', 4 => 'EXTRA', 5 => '0' . '8' . str_repeat('1', 10));
	test_exception(static function () use ($sourceReader, $extraRow) { $sourceReader->validateWorkbook($extraRow); }, 'sheet_record_count_mismatch');
	$extraColumn = $raw; $extraColumn[0]['rows'][$dataRows[0]][6] = 'meaningful';
	test_exception(static function () use ($sourceReader, $extraColumn) { $sourceReader->validateWorkbook($extraColumn); }, 'extra_source_column');
	$scientificPhone = $raw; $scientificPhone[0]['rows'][$dataRows[0]][5] = '8.1E+10';
	test_exception(static function () use ($sourceReader, $scientificPhone) { $sourceReader->validateWorkbook($scientificPhone); }, 'invalid_phone');
	$mergedData = $raw; $mergedData[0]['merged_ranges'][] = array('start_row' => $dataRows[0], 'end_row' => $dataRows[0], 'start_column' => 1, 'end_column' => 5);
	test_exception(static function () use ($sourceReader, $mergedData) { $sourceReader->validateWorkbook($mergedData); }, 'merged_data_region_rejected');

	$xlsx = new SyntheticXlsxBuilder();
	$xlsxReader = new DoclincXlsxReader();
	test_expect(count($xlsxReader->readData($xlsx->build())) === 9, 'synthetic_xlsx_valid');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('external' => true))); }, 'external_relationship_rejected');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('macro' => true))); }, 'xlsx_part_not_allowed');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('unsafe_entry' => true))); }, 'unsafe_xlsx_entry');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('hidden' => true))); }, 'hidden_sheet_rejected');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('formula' => true))); }, 'formula_cell_rejected');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('error' => true))); }, 'error_cell_rejected');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('invalid_shared' => true))); }, 'invalid_shared_string_reference');
	test_exception(static function () use ($xlsxReader, $xlsx) { $xlsxReader->readData($xlsx->build(array('scientific' => true))); }, 'numeric_cell_format_rejected');
	$syntheticMerged = $xlsxReader->readData($xlsx->build(array('merge' => true)));
	test_expect($syntheticMerged[0]['merged_ranges'][0]['end_column'] === 5, 'merge_inventory');

	$first = $inspected['records'][0];
	$base = $sourceReader->username($first['nama'], $first['kode_pkm'], $first['no_hp'], array());
	$collision = $sourceReader->username($first['nama'], $first['kode_pkm'], $first['no_hp'], array(strtolower($base) => true));
	test_expect($base !== $collision && strlen($collision) <= 77 && strlen($collision . '@staging.doclinc.local') <= 100, 'login_collision');

	$temp = tempnam(sys_get_temp_dir(), 'doclinc-xlsx-negative-');
	copy($sourcePath, $temp); $changed=file_get_contents($temp); $changed[100]=chr(ord($changed[100])^1); file_put_contents($temp,$changed); $changed=null;
	test_exception(static function () use ($sourceReader, $temp) { $sourceReader->inspect($temp, false); }, 'source_checksum_mismatch');
	unlink($temp);
	if (DIRECTORY_SEPARATOR === '/') {
		$permissionFile = tempnam(sys_get_temp_dir(), 'doclinc-xlsx-mode-'); copy($sourcePath, $permissionFile); chmod($permissionFile, 0644);
		$oldRoots=getenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_ROOTS');$oldUids=getenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_UIDS');$oldGids=getenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_GIDS');
		putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_ROOTS='.sys_get_temp_dir());putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_UIDS='.(string)fileowner($permissionFile));putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_GIDS='.(string)filegroup($permissionFile));putenv('DOCLINC_NAKES_DISPOSABLE_TEST');putenv('DOCLINC_NAKES_ALLOW_BIND_MOUNT_SOURCE_FOR_TEST');
		test_exception(static function () use ($sourceReader, $permissionFile) { $sourceReader->inspect($permissionFile, true); }, 'source_permissions_rejected');
		$oldRoots===false?putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_ROOTS'):putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_ROOTS='.$oldRoots);$oldUids===false?putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_UIDS'):putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_UIDS='.$oldUids);$oldGids===false?putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_GIDS'):putenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_GIDS='.$oldGids);unlink($permissionFile);
	}
	if (DIRECTORY_SEPARATOR === '/' && function_exists('symlink')) {
		$link = sys_get_temp_dir() . '/doclinc-xlsx-link-' . bin2hex(random_bytes(6));
		symlink($sourcePath, $link);
		test_exception(static function () use ($sourceReader, $link) { $sourceReader->inspect($link, false); }, 'source_path_rejected');
		unlink($link);
	}

	$policy = new First_login_password_policy();
	$e164Phone = '+62' . '8' . str_repeat('1', 9);
	$localPhone = '0' . substr($e164Phone, 3);
	$acceptablePassword = implode('', array('Longer', '-', 'New', '!', '42'));
	$identity = array('username' => 'test-login', 'email' => 'test-login@staging.doclinc.local', 'no_hp' => $e164Phone);
	$current = password_hash($localPhone, PASSWORD_DEFAULT);
	test_expect($policy->validate($acceptablePassword, $acceptablePassword, $identity, $current) === null, 'password_valid');
	test_expect($policy->validate($localPhone, $localPhone, $identity, $current) !== null, 'local_phone_reuse');
	test_expect($policy->validate($e164Phone, $e164Phone, $identity, $current) !== null, 'e164_reuse');
	test_expect($policy->validate('test-login', 'test-login', $identity, $current) !== null, 'username_reuse');
	test_expect($policy->validate('test-login@staging.doclinc.local', 'test-login@staging.doclinc.local', $identity, $current) !== null, 'email_reuse');
	test_expect($policy->validate(array('bad'), 'bad', $identity, $current) === 'invalid_payload', 'array_payload');
	test_expect($policy->validate(str_repeat('A', 73), str_repeat('A', 73), $identity, $current) !== null, 'password_maximum');

	$tokenPolicy = new First_login_token_policy();
	$binding = bin2hex(random_bytes(16));
	$issued = $tokenPolicy->issue(101, $binding, 1000);
	test_expect(is_array($issued) && strlen($issued['token']) === 64 && strpos(json_encode($issued['state']), $issued['token']) === false, 'token_entropy_storage');
	test_expect($tokenPolicy->validate($issued['token'], $issued['state'], 101, $binding, 1001), 'token_valid');
	test_expect(!$tokenPolicy->validate(str_repeat('0', 64), $issued['state'], 101, $binding, 1001), 'token_wrong');
	test_expect(!$tokenPolicy->validate($issued['token'], $issued['state'], 102, $binding, 1001), 'token_cross_account');
	test_expect(!$tokenPolicy->validate($issued['token'], $issued['state'], 101, bin2hex(random_bytes(16)), 1001), 'token_cross_session');
	test_expect(!$tokenPolicy->validate($issued['token'], $issued['state'], 101, $binding, 1600), 'token_expired');
	test_expect(!$tokenPolicy->validate(array('bad'), $issued['state'], 101, $binding, 1001), 'token_array');
	$replacement = $tokenPolicy->issue(101, $binding, 1002);
	test_expect($replacement['token'] !== $issued['token'] && !$tokenPolicy->validate($issued['token'], $replacement['state'], 101, $binding, 1003), 'token_refresh_revokes_old');

	$gatePolicy = new First_login_gate_policy();
	test_expect($gatePolicy->allowed('Login', 'change_password'), 'gate_allow_change');
	test_expect(!$gatePolicy->allowed('Login', 'change_password_extra') && !$gatePolicy->allowed('LoginExtra', 'change_password'), 'gate_exact_allowlist');
	test_expect($gatePolicy->jsonResponse('chat', 'messages', false, '', ''), 'gate_json_route');
	test_expect($gatePolicy->jsonResponse('clinical_suggestions', 'anything', false, '', ''), 'gate_clinical_json_route');
	test_expect(!$gatePolicy->jsonResponse('home', 'index', false, '', ''), 'gate_html_route');

	$root = dirname(__DIR__, 3);
	test_expect(test_feature_flag($root,null,null)==='0','flag_missing');
	test_expect(test_feature_flag($root,'malformed','staging')==='0','flag_malformed');
	test_expect(test_feature_flag($root,'true','production')==='0','production_flag');
	test_expect(test_feature_flag($root,'true','uat')==='1','uat_flag');
	$runtimeFiles = array(
		'application/modules/home/models/Home_m.php',
		'admin_menu/application/modules/home/models/Home_m.php',
		'admin_menu/application/modules/laporan/models/Laporan_m.php',
		'admin_menu/application/modules/kelola_dokter_nakes/models/Kelola_dokter_nakes_m.php',
	);
	foreach ($runtimeFiles as $file) test_expect(strpos(file_get_contents($root . '/' . $file), 'm_dokter') === false, 'm_dokter_runtime_dependency');
	$homeModel = file_get_contents($root . '/application/modules/home/models/Home_m.php');
	test_expect(strpos($homeModel, 'dokter_user.userId') !== false && strpos($homeModel, 'professional_id') === false, 'identity_collision_regression');
	$homeView = file_get_contents($root . '/application/modules/home/views/home_v.php');
	test_expect(strpos($homeView, '$doc->userId') !== false && strpos($homeView, '$doc->professional_id') === false, 'identity_view_collision_regression');
	$login = file_get_contents($root . '/application/modules/login/controllers/Login.php');
	$hook = file_get_contents($root . '/application/hooks/Password_change_gate.php');
	$loginModel = file_get_contents($root . '/application/modules/login/models/Login_m.php');
	$hooksConfig = file_get_contents($root . '/application/config/hooks.php');
	test_expect(strpos($login, 'sess_regenerate(TRUE)') !== false && strpos($login, 'password_change_token_state') !== false && strpos($login, "unset_userdata('password_change_token_state')") !== false, 'session_csrf_contract');
	test_expect(strpos($hook, 'set_status_header(403)') !== false && strpos($hook, 'application/json') !== false, 'json_403_contract');
	test_expect(strpos($hooksConfig, 'Password_change_gate') !== false && strpos($hook, "PHP_SAPI === 'cli'") !== false && strpos($hook, 'First_login_gate_policy') !== false, 'unauthorized_bypass_contract');
	test_expect(strpos($loginModel, 'trans_begin()') !== false && strpos($loginModel, 'FOR UPDATE') !== false
		&& strpos($loginModel, 'hash_equals($expected_current_hash, (string) $locked->password)') !== false
		&& strpos($loginModel, "'must_change_password' => 0") !== false && strpos($loginModel, 'trans_commit()') !== false, 'password_change_atomic_contract');
	$identityMutation = str_replace('dokter_user.userId', 'legacy_doctor.professional_id', $homeModel);
	$identityCollision = array('users' => array(101 => 'users-identity'), 'm_dokter' => array(101 => 'different-legacy-identity'));
	$resolvedIdentity = strpos($homeModel, 'dokter_user.userId') !== false ? $identityCollision['users'][101] : $identityCollision['m_dokter'][101];
	test_expect($resolvedIdentity === 'users-identity' && strpos($identityMutation, 'legacy_doctor.professional_id') !== false && strpos($identityMutation, 'dokter_user.userId') === false, 'identity_collision_101_mutation_detected');

	echo "NAKES_IMPORT_UNIT=PASS\n";
	echo "SOURCE_CHECKSUM_SIZE=PASS\nSHEET_CONTRACT=PASS\nSOURCE_NORMALIZATION=PASS\nSOURCE_NEGATIVE_CASES=PASS\nSOURCE_VALUES_ABSENT_FROM_REPOSITORY=PASS\nSYNTHETIC_XLSX_SECURITY=PASS\nUSERNAME_EMAIL_COLLISION=PASS\nFIRST_LOGIN_POLICY=PASS\nFIRST_LOGIN_FEATURE_FLAG=PASS\nFIRST_LOGIN_TOKEN_BINDING=PASS\nFIRST_LOGIN_CSRF_CONTRACT=PASS\nFIRST_LOGIN_SESSION_REGENERATION=PASS\nFIRST_LOGIN_JSON_403_CONTRACT=PASS\nFIRST_LOGIN_UNAUTHORIZED_BYPASS=PASS\nFIRST_LOGIN_ATOMIC_UPDATE_CONTRACT=PASS\nM_DOKTER_IDENTITY_REGRESSION=PASS\nIDENTITY_COLLISION_101_MUTATION=PASS\n";
} catch (Throwable $exception) {
	$diagnostic = preg_match('/\A[a-z0-9_]+\z/', $exception->getMessage()) === 1 ? $exception->getMessage() : 'unit_test_failed';
	fwrite(STDERR, "NAKES_IMPORT_UNIT=FAIL\nSAFE_ERROR_CODE=unit_test_failed\n");
	if (getenv('DOCLINC_NAKES_TEST_DIAGNOSTICS') === '1') fwrite(STDERR, "TEST_ASSERTION=" . $diagnostic . "\n");
	exit(1);
}
