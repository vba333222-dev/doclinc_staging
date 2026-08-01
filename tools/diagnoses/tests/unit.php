<?php
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
require_once dirname(__DIR__, 3) . '/application/libraries/Medicalrecord_diagnosis_service.php';

$passed = 0;
$failed = 0;
function diagnosis_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$label}\n";
		return;
	}
	$failed++;
	fwrite(STDERR, "FAIL {$label}\n");
}

final class DiagnosisUnitDatabase
{
	public $rows = array();
	public $deleteFails = false;
	public $insertFailsAt = 0;
	private $where = array();
	private $insertCount = 0;
	private $missingField = '';

	public function __construct($missingField = '') { $this->missingField = $missingField; }
	public function table_exists($table) { return in_array($table, array('medicalrecords', 'medicalrecord_diagnoses'), true); }
	public function field_exists($field, $table) { return $field !== $this->missingField; }
	public function where($field, $value) { $this->where[$field] = $value; return $this; }
	public function delete($table) { $this->where = array(); return !$this->deleteFails && $table === 'medicalrecord_diagnoses'; }
	public function insert($table, array $row) {
		$this->insertCount++;
		if ($table !== 'medicalrecord_diagnoses' || ($this->insertFailsAt > 0 && $this->insertCount === $this->insertFailsAt)) { return false; }
		$this->rows[] = $row;
		return true;
	}
}

$normalized = Medicalrecord_diagnosis_service::normalize(' Diagnosis utama ', array('Tambahan satu', 'Tambahan dua'), true);
diagnosis_expect($normalized['valid'] && $normalized['diagnoses'] === array('Diagnosis utama', 'Tambahan satu', 'Tambahan dua'), 'three_ordered_diagnoses');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize('Utama', array('', 'Tambahan'), true)['diagnoses'] === array('Utama', 'Tambahan'), 'empty_optional_ignored');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize('Utama', 'malicious-scalar', false)['diagnoses'] === array('Utama'), 'flag_off_ignores_additional_payload');
diagnosis_expect(!Medicalrecord_diagnosis_service::normalize('Utama', 'malicious-scalar', true)['valid'], 'enabled_rejects_scalar_additional');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize('', array(), true)['error'] === 'primary_required', 'primary_required');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize('Sama', array('sama'), true)['error'] === 'duplicate_diagnosis', 'case_insensitive_duplicate_rejected');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize('A', array('B', 'C', 'D'), true)['error'] === 'too_many_diagnoses', 'fourth_diagnosis_rejected');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize(str_repeat('a', 256), array(), true)['error'] === 'diagnosis_too_long', 'overlong_diagnosis_rejected');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize(str_repeat('a', 255), array(), true)['valid'], 'maximum_length_accepted');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize("unsafe\nvalue", array(), true)['error'] === 'invalid_characters', 'control_character_rejected');
diagnosis_expect(Medicalrecord_diagnosis_service::normalize("\xC3\x28", array(), true)['error'] === 'invalid_characters', 'invalid_utf8_rejected');

$db = new DiagnosisUnitDatabase();
diagnosis_expect(Medicalrecord_diagnosis_service::schemaReady($db), 'foundation_schema_ready');
diagnosis_expect(!Medicalrecord_diagnosis_service::schemaReady(new DiagnosisUnitDatabase('display_text')), 'missing_schema_field_rejected');
diagnosis_expect(Medicalrecord_diagnosis_service::replace($db, 51, 71, 201, $normalized['diagnoses']), 'replace_succeeds');
diagnosis_expect(count($db->rows) === 3, 'exact_row_count');
diagnosis_expect(array_column($db->rows, 'position') === array(1, 2, 3), 'exact_positions');
diagnosis_expect(array_column($db->rows, 'diagnosis_role') === array('primary', 'secondary', 'secondary'), 'exact_roles');
diagnosis_expect(array_column($db->rows, 'display_text') === $normalized['diagnoses'], 'exact_display_values');
diagnosis_expect(count(array_filter($db->rows, function ($row) { return $row['diagnosis_code'] !== null || $row['suggestion_term_id'] !== null; })) === 0, 'no_inferred_code_or_term');
diagnosis_expect(!Medicalrecord_diagnosis_service::replace(new DiagnosisUnitDatabase(), 0, 71, 201, array('A')), 'invalid_identity_rejected');
$deleteFailure = new DiagnosisUnitDatabase(); $deleteFailure->deleteFails = true;
diagnosis_expect(!Medicalrecord_diagnosis_service::replace($deleteFailure, 51, 71, 201, array('A')), 'delete_failure_visible');
$insertFailure = new DiagnosisUnitDatabase(); $insertFailure->insertFailsAt = 2;
diagnosis_expect(!Medicalrecord_diagnosis_service::replace($insertFailure, 51, 71, 201, array('A', 'B')), 'insert_failure_visible');

$root = dirname(__DIR__, 3);
$config = file_get_contents($root . '/application/config/config.php');
$controller = file_get_contents($root . '/application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
$model = file_get_contents($root . '/application/modules/konsultasi_nakes/models/Konsultasi_nakes_m.php');
$view = file_get_contents($root . '/application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
$history = file_get_contents($root . '/application/modules/home/models/Home_m.php')
	. file_get_contents($root . '/application/modules/home_nakes/models/Home_nakes_m.php');
diagnosis_expect(strpos($config, 'DOCLINC_ADDITIONAL_DIAGNOSES_ENABLED') !== false
	&& strpos($config, "additional_diagnoses_enabled']") !== false, 'feature_default_off_configuration');
diagnosis_expect(strpos($controller, 'Medicalrecord_diagnosis_service::normalize') !== false
	&& strpos($controller, 'Medicalrecord_diagnosis_service::schemaReady') !== false, 'controller_validates_payload_and_schema');
$begin = strpos($model, '$this->db->trans_begin()');
$replace = strpos($model, 'Medicalrecord_diagnosis_service::replace(');
$commit = strpos($model, '$this->db->trans_commit()');
diagnosis_expect($begin !== false && $replace > $begin && $commit > $replace, 'diagnoses_written_inside_completion_transaction');
diagnosis_expect(substr_count($view, 'name="diagnosa_tambahan[]"') === 2
	&& strpos($view, 'new Set(diagnosisKeys)') !== false, 'browser_renders_two_optional_fields_and_duplicate_guard');
diagnosis_expect(substr_count($history, 'AS diagnoses_display') >= 3, 'ordered_diagnoses_loaded_for_warga_and_nakes_history');

echo "DIAGNOSIS_UNIT_PASSED={$passed}\nDIAGNOSIS_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
