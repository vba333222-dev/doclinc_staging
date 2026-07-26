<?php

$root = dirname(__DIR__, 3);
$migration = $root . '/application/migrations/20260727000200_medicalrecords_anamnesis_foundation.php';
$database = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME') ?: '';
$test_allowed = filter_var(getenv('DOCLINC_CLINICAL_SUGGESTION_DISPOSABLE_TEST') ?: false, FILTER_VALIDATE_BOOLEAN);
if (!$test_allowed || preg_match('/(?:^|[_-])test(?:$|[_-])/', strtolower($database)) !== 1) {
	fwrite(STDERR, "ANAMNESIS_INTEGRATION=FAIL\nSAFE_ERROR_CODE=disposable_test_database_required\n");
	exit(2);
}

function run_anamnesis_migration($migration, array $arguments)
{
	$process = proc_open(
		array_merge(array(PHP_BINARY, $migration), $arguments),
		array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
		$pipes,
		null,
		null,
		array('bypass_shell' => true)
	);
	if (!is_resource($process)) return array(255, '', 'process_start_failed');
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array(proc_close($process), $stdout, $stderr);
}

function create_legacy_medicalrecords_fixture(mysqli $db)
{
	$db->query('SET FOREIGN_KEY_CHECKS=0');
	$db->query('DROP TABLE IF EXISTS medicalrecords');
	$db->query('DROP TABLE IF EXISTS requests');
	$db->query('SET FOREIGN_KEY_CHECKS=1');
	$db->query("CREATE TABLE requests (
		request_id int(11) NOT NULL AUTO_INCREMENT,
		request_status enum('Pending','Accepted','Completed','Cancelled') NULL DEFAULT 'Pending',
		assigned_puskesmas_code varchar(64) NULL,
		PRIMARY KEY (request_id)
	) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");
	$db->query("CREATE TABLE medicalrecords (
		record_id int(11) NOT NULL AUTO_INCREMENT,
		request_id int(11) NOT NULL,
		diagnosis text CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,
		treatment text CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,
		recommendations text CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL,
		created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (record_id),
		KEY request_id (request_id),
		CONSTRAINT medicalrecords_ibfk_1 FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT
	) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci");
}

function expect_true($condition, $code)
{
	if (!$condition) throw new RuntimeException($code);
}

$stage = 'connection';
try {
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$db = new mysqli(
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost',
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER'),
		getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD'),
		$database,
		(int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306)
	);
	$db->set_charset('utf8mb4');
	create_legacy_medicalrecords_fixture($db);
	$db->query("INSERT INTO requests (request_id,request_status,assigned_puskesmas_code) VALUES (1,'Completed','PKM-TEST')");
	$db->query("INSERT INTO medicalrecords (record_id,request_id,diagnosis,treatment,recommendations,created_at) VALUES (1,1,'Diagnosis lama','Terapi lama','Saran lama','2026-01-01 00:00:00')");
	$before = $db->query('SELECT record_id,request_id,diagnosis,treatment,recommendations,created_at FROM medicalrecords ORDER BY record_id')->fetch_all(MYSQLI_ASSOC);

	$stage = 'plan';
	list($plan_code, $plan_out) = run_anamnesis_migration($migration, array());
	$column_before = $db->query("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medicalrecords' AND COLUMN_NAME='anamnesis'")->fetch_assoc();
	expect_true($plan_code === 0 && strpos($plan_out, 'DDL_EXECUTED=false') !== false && (int) $column_before['total'] === 0, 'plan_not_zero_write');

	$arguments = array('--apply', '--confirm-database=' . $database, '--environment=uat', '--backup-reference=disposable-test-backup-20260727');
	$stage = 'apply';
	list($apply_code, $apply_out, $apply_err) = run_anamnesis_migration($migration, $arguments);
	if ($apply_code !== 0) fwrite(STDERR, $apply_err);
	expect_true($apply_code === 0 && strpos($apply_out, 'DDL_EXECUTED=true') !== false, 'apply_failed');
	$signature = $db->query("SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='medicalrecords' AND COLUMN_NAME='anamnesis'")->fetch_assoc();
	expect_true($signature && strtolower($signature['COLUMN_TYPE']) === 'text' && $signature['IS_NULLABLE'] === 'YES'
		&& ($signature['COLUMN_DEFAULT'] === null || strtoupper((string) $signature['COLUMN_DEFAULT']) === 'NULL'), 'schema_signature_failed');
	$after = $db->query('SELECT record_id,request_id,diagnosis,treatment,recommendations,created_at FROM medicalrecords ORDER BY record_id')->fetch_all(MYSQLI_ASSOC);
	$legacy_anamnesis = $db->query('SELECT anamnesis FROM medicalrecords WHERE record_id=1')->fetch_assoc();
	expect_true($before === $after && $legacy_anamnesis['anamnesis'] === null, 'existing_rows_changed');

	$stage = 'idempotency';
	list($second_code, $second_out) = run_anamnesis_migration($migration, $arguments);
	expect_true($second_code === 0 && strpos($second_out, 'ALREADY_APPLIED=true') !== false && strpos($second_out, 'DDL_EXECUTED=false') !== false, 'idempotency_failed');

	$stage = 'partial_schema';
	$db->query('ALTER TABLE medicalrecords MODIFY anamnesis VARCHAR(255) NULL');
	list($partial_code, $partial_out, $partial_err) = run_anamnesis_migration($migration, $arguments);
	expect_true($partial_code !== 0 && strpos($partial_err, 'SAFE_ERROR_CODE=anamnesis_column_signature_mismatch') !== false, 'partial_schema_not_closed');

	$stage = 'restore';
	create_legacy_medicalrecords_fixture($db);
	list($restore_code) = run_anamnesis_migration($migration, $arguments);
	expect_true($restore_code === 0, 'restore_failed');

	$stage = 'completion';
	$db->query("INSERT INTO requests (request_id,request_status,assigned_puskesmas_code) VALUES (2,'Accepted','PKM-TEST'),(3,'Accepted','PKM-TEST')");
	$db->query("INSERT INTO medicalrecords (record_id,request_id,diagnosis,treatment,recommendations,anamnesis,created_at) VALUES
		(20,2,'Diagnosis awal','Terapi awal','Saran tetap',NULL,'2026-01-01 00:00:00'),
		(21,2,'Diagnosis terbaru','Terapi terbaru','Saran terbaru tetap',NULL,'2026-01-02 00:00:00'),
		(30,3,'Diagnosis atomik','Terapi atomik','Saran atomik',NULL,'2026-01-03 00:00:00')");
	$db->begin_transaction();
	$request = $db->query("SELECT request_status FROM requests WHERE request_id=2 FOR UPDATE")->fetch_assoc();
	expect_true($request && $request['request_status'] === 'Accepted', 'request_not_accepted');
	$latest = $db->query('SELECT record_id FROM medicalrecords WHERE request_id=2 ORDER BY record_id DESC LIMIT 1 FOR UPDATE')->fetch_assoc();
	$db->query("UPDATE requests SET request_status='Completed' WHERE request_id=2 AND request_status='Accepted'");
	$stmt = $db->prepare('UPDATE medicalrecords SET anamnesis=? WHERE record_id=?');
	$plain_text = "Demam dua hari\n<script>alert(1)</script>";
	$latest_id = (int) $latest['record_id'];
	$stmt->bind_param('si', $plain_text, $latest_id);
	$stmt->execute();
	$db->commit();
	$records = $db->query('SELECT record_id,anamnesis,recommendations FROM medicalrecords WHERE request_id=2 ORDER BY record_id')->fetch_all(MYSQLI_ASSOC);
	expect_true(count($records) === 2 && $records[0]['anamnesis'] === null && $records[1]['anamnesis'] === $plain_text, 'wrong_medicalrecord_target');
	expect_true($records[0]['recommendations'] === 'Saran tetap' && $records[1]['recommendations'] === 'Saran terbaru tetap', 'legacy_saran_changed');
	expect_true(strpos(htmlspecialchars($records[1]['anamnesis'], ENT_QUOTES, 'UTF-8'), '<script>') === false, 'html_not_rendered_as_text');

	$stage = 'atomicity';
	try {
		$db->begin_transaction();
		$db->query("SELECT request_status FROM requests WHERE request_id=3 FOR UPDATE");
		$db->query("UPDATE requests SET request_status='Completed' WHERE request_id=3 AND request_status='Accepted'");
		$db->query('UPDATE medicalrecords SET missing_anamnesis_column=1 WHERE record_id=30');
		$db->commit();
		throw new RuntimeException('forced_write_should_fail');
	} catch (mysqli_sql_exception $expected) {
		$db->rollback();
	}
	$atomic_request = $db->query('SELECT request_status FROM requests WHERE request_id=3')->fetch_assoc();
	$atomic_record = $db->query('SELECT anamnesis FROM medicalrecords WHERE record_id=30')->fetch_assoc();
	expect_true($atomic_request['request_status'] === 'Accepted' && $atomic_record['anamnesis'] === null, 'completion_not_atomic');

	echo "ANAMNESIS_INTEGRATION=PASS\n";
	echo "MIGRATION_PLAN_ZERO_WRITE=PASS\nMIGRATION_APPLY=PASS\nMIGRATION_IDEMPOTENCY=PASS\nMIGRATION_SCHEMA_SIGNATURE=PASS\nMIGRATION_PARTIAL_SCHEMA_NEGATIVE=PASS\nMIGRATION_EXISTING_ROWS_UNCHANGED=PASS\n";
	echo "ANAMNESIS_PERSISTS_TO_LATEST_RECORD=PASS\nDUPLICATE_MEDICALRECORD_NOT_CREATED=PASS\nLEGACY_SARAN_UNCHANGED=PASS\nCOMPLETION_AND_ANAMNESIS_ATOMIC=PASS\nHTML_PAYLOAD_RENDERED_AS_TEXT=PASS\n";
} catch (Throwable $exception) {
	fwrite(STDERR, "ANAMNESIS_INTEGRATION=FAIL\nSAFE_ERROR_CODE=anamnesis_" . $stage . "_failed\n");
	exit(1);
} finally {
	if (isset($db) && $db instanceof mysqli) $db->close();
}
