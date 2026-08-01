<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }

final class VisitProofUnitConfig
{
	private $items = array(
		'visit_proof_required' => false,
		'visit_proof_max_size_kb' => 5120,
		'visit_proof_location_max_age_seconds' => 120,
		'visit_location_max_accuracy_meters' => 100,
		'visit_arrival_radius_meters' => 75,
	);
	public function item($key) { return array_key_exists($key, $this->items) ? $this->items[$key] : null; }
	public function set($key, $value) { $this->items[$key] = $value; }
}

final class VisitProofUnitLoader
{
	public function database() { return true; }
}

final class VisitProofUnitApplication
{
	public $config;
	public $load;
	public $db;
	public function __construct() { $this->config = new VisitProofUnitConfig(); $this->load = new VisitProofUnitLoader(); }
}

$GLOBALS['visit_proof_unit_application'] = new VisitProofUnitApplication();
function &get_instance() { return $GLOBALS['visit_proof_unit_application']; }

require_once APPPATH . 'helpers/visit_routing_helper.php';
require_once APPPATH . 'helpers/visit_proof_helper.php';
require_once APPPATH . 'libraries/Doclinc_feature_flags.php';
require_once APPPATH . 'libraries/Visit_proof_service.php';

final class VisitProofUnitDatabase
{
	public $rows = array();
	public $last_id = 0;
	private $where = array();
	private $fields = array(
		'media_id', 'request_id', 'medicalrecord_id', 'uploaded_by_user_id', 'media_type',
		'storage_key', 'mime_type', 'size_bytes', 'sha256', 'lifecycle_state',
		'associated_at', 'finalized_at', 'failed_at', 'failure_code',
	);
	public function table_exists($table) { return $table === 'consultation_visit_media'; }
	public function field_exists($field, $table) { return $table === 'consultation_visit_media' && in_array($field, $this->fields, true); }
	public function insert($table, array $row) {
		if ($table !== 'consultation_visit_media') { return false; }
		$this->last_id++;
		$row['media_id'] = $this->last_id;
		$this->rows[$this->last_id] = $row;
		return true;
	}
	public function insert_id() { return $this->last_id; }
	public function where($field, $value) { $this->where[$field] = $value; return $this; }
	public function update($table, array $values) {
		if ($table !== 'consultation_visit_media') { return false; }
		foreach ($this->rows as $id => $row) {
			$matches = true;
			foreach ($this->where as $field => $value) {
				if (!array_key_exists($field, $row) || $row[$field] != $value) { $matches = false; break; }
			}
			if ($matches) { $this->rows[$id] = array_merge($row, $values); }
		}
		$this->where = array();
		return true;
	}
}

$passed = 0;
$failed = 0;
function visit_proof_expect($condition, $label) {
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; fwrite(STDERR, "FAIL {$label}\n");
}

$temporary_directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'doclinc_visit_proof_unit_' . bin2hex(random_bytes(6));
if (!mkdir($temporary_directory, 0700, true)) { fwrite(STDERR, "SAFE_ERROR_CODE=temp_directory_failed\n"); exit(1); }

try {
	visit_proof_expect(Doclinc_feature_flags::resolve(null, 'staging', 'staging')['enabled'] === false, 'feature_default_off');
	visit_proof_expect(Doclinc_feature_flags::resolve('true', 'production', 'production')['enabled'] === false, 'feature_production_denied');
	visit_proof_expect(Doclinc_feature_flags::resolve('true', 'staging', 'staging')['enabled'] === true, 'feature_exact_staging_enabled');
	visit_proof_expect(doclinc_visit_proof_required() === false, 'helper_default_off');
	$GLOBALS['visit_proof_unit_application']->config->set('visit_proof_required', true);
	visit_proof_expect(doclinc_visit_proof_required() === true, 'helper_exact_boolean_on');
	visit_proof_expect(doclinc_visit_proof_is_visit('1') && doclinc_visit_proof_is_visit('Kunjungan Nakes'), 'visit_criteria_recognized');
	visit_proof_expect(!doclinc_visit_proof_is_visit('0') && !doclinc_visit_proof_is_visit('Selesai Konsultasi'), 'non_visit_criteria_ignored');
	visit_proof_expect(doclinc_visit_proof_persisted_visit((object) array('consultation_mode' => 'visit', 'visit_status' => 'not_started'))
		&& doclinc_visit_proof_persisted_visit((object) array('visit_status' => 'arrived')), 'persisted_visit_state_requires_proof');
	visit_proof_expect(!doclinc_visit_proof_persisted_visit((object) array('consultation_mode' => 'non_visit', 'visit_status' => 'not_started')),
		'persisted_non_visit_state_does_not_require_proof');

	$now_ms = 1900000000000;
	$location = doclinc_visit_proof_location_input(-6.0, 106.0, 15, $now_ms - 1000, $now_ms);
	visit_proof_expect(!empty($location['valid']) && $location['client_sequence'] === $now_ms - 1000, 'fresh_location_accepted');
	visit_proof_expect(doclinc_visit_proof_location_input(-6.0, 106.0, 101, $now_ms, $now_ms)['reason'] === 'low_accuracy', 'low_accuracy_rejected');
	visit_proof_expect(doclinc_visit_proof_location_input(-6.0, 106.0, 15, $now_ms - 121000, $now_ms)['reason'] === 'stale_location', 'stale_location_rejected');
	visit_proof_expect(doclinc_visit_proof_location_input(-91, 106.0, 15, $now_ms, $now_ms)['reason'] === 'invalid_coordinate', 'invalid_coordinate_rejected');

	$db = new VisitProofUnitDatabase();
	$GLOBALS['visit_proof_unit_application']->db = $db;
	$service = new Visit_proof_service($db, $temporary_directory);
	visit_proof_expect($service->schemaReady() && $service->ensureStorageReady(), 'schema_and_storage_ready');
	$public_service = new Visit_proof_service($db, FCPATH);
	visit_proof_expect($public_service->ensureStorageReady() === false, 'public_web_root_storage_rejected');
	$key_one = $service->newStorageKey();
	$key_two = $service->newStorageKey();
	visit_proof_expect(preg_match('/^[a-f0-9]{64}$/', $key_one) === 1 && $key_one !== $key_two, 'storage_keys_random_and_canonical');
	$config = $service->uploadConfig($key_one);
	visit_proof_expect(is_array($config) && $config['file_name'] === $key_one && $config['allowed_types'] === 'jpg|jpeg', 'upload_config_exact_types_and_name');

	$valid_path = $temporary_directory . DIRECTORY_SEPARATOR . $key_one . '.jpg';
	$valid_jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAAAAAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==', true);
	file_put_contents($valid_path, $valid_jpeg);
	$staged = $service->stageUploadedImage(5001, 201, array('full_path' => $valid_path, 'file_name' => basename($valid_path)), $key_one);
	visit_proof_expect(!empty($staged['success']) && $staged['media_id'] === 1, 'valid_image_staged');
	visit_proof_expect($db->rows[1]['lifecycle_state'] === 'pending'
		&& $db->rows[1]['mime_type'] === 'image/jpeg'
		&& hash_equals($db->rows[1]['sha256'], hash_file('sha256', $valid_path)), 'staged_metadata_exact_hash_and_mime');
	$service->failStaged($staged, 'visit_location_outside_radius');
	visit_proof_expect($db->rows[1]['lifecycle_state'] === 'failed'
		&& $db->rows[1]['failure_code'] === 'visit_location_outside_radius'
		&& !is_file($valid_path), 'failed_stage_marked_and_file_removed');

	$png_key = $service->newStorageKey();
	$png_path = $temporary_directory . DIRECTORY_SEPARATOR . $png_key . '.png';
	file_put_contents($png_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
	$png_result = $service->stageUploadedImage(5002, 201, array('full_path' => $png_path, 'file_name' => basename($png_path)), $png_key);
	visit_proof_expect(empty($png_result['success']) && $png_result['reason'] === 'storage_key_mismatch'
		&& !is_file($png_path), 'png_visit_proof_rejected_and_removed');

	$invalid_key = $service->newStorageKey();
	$invalid_path = $temporary_directory . DIRECTORY_SEPARATOR . $invalid_key . '.jpg';
	file_put_contents($invalid_path, 'not-an-image');
	$invalid = $service->stageUploadedImage(5003, 201, array('full_path' => $invalid_path, 'file_name' => basename($invalid_path)), $invalid_key);
	visit_proof_expect(empty($invalid['success']) && $invalid['reason'] === 'invalid_image_content'
		&& !is_file($invalid_path) && count($db->rows) === 1, 'invalid_image_rejected_and_removed');
} finally {
	foreach (glob($temporary_directory . DIRECTORY_SEPARATOR . '*') ?: array() as $path) {
		if (is_file($path)) { @unlink($path); }
	}
	@rmdir($temporary_directory);
}

echo "VISIT_PROOF_UNIT_PASSED={$passed}\nVISIT_PROOF_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
