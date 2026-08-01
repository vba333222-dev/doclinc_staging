<?php

$root = dirname(__DIR__, 3);
require_once $root . '/tools/clinical_suggestions/ClinicalSuggestionPackage.php';
if (!defined('BASEPATH')) define('BASEPATH', $root . '/system/');
require_once $root . '/application/libraries/Clinical_suggestion_policy.php';
require_once $root . '/application/libraries/Clinical_suggestion_feature.php';
require_once $root . '/application/libraries/Clinical_anamnesis.php';
if (!class_exists('CI_Model', false)) {
	class CI_Model { public $db; }
}
require_once $root . '/application/models/Clinical_suggestion_m.php';
require_once $root . '/application/libraries/Clinical_suggestion_presenter.php';

$passed = 0;
$failed = 0;
function check_case($name, $condition)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS " . $name . "\n";
	} else {
		$failed++;
		echo "FAIL " . $name . "\n";
	}
}

function run_php_cli(array $arguments)
{
	$command = array_merge(array(PHP_BINARY), $arguments);
	$descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$process = proc_open($command, $descriptors, $pipes, null, null, array('bypass_shell' => true));
	if (!is_resource($process)) return array(255, '', 'process_start_failed');
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return array(proc_close($process), $stdout, $stderr);
}

check_case('normalization_whitespace_case', ClinicalSuggestionPackage::normalize("  DeMAM\n Tinggi ") === 'demam tinggi');
$policy = new Clinical_suggestion_policy();
check_case('warga_create_without_request_id_allowed', $policy->authorize('warga', 'complaint', 7, 0));
check_case('warga_diagnosis_denied', !$policy->authorize('warga', 'diagnosis', 7, 0));
check_case('warga_medicine_denied', !$policy->authorize('warga', 'medicine', 7, 0));
$ownPending = (object) array('user_id' => 7, 'request_status' => 'Pending');
$otherPending = (object) array('user_id' => 8, 'request_status' => 'Pending');
$ownCompleted = (object) array('user_id' => 7, 'request_status' => 'Completed');
check_case('warga_edit_own_pending_allowed', $policy->authorize('warga', 'complaint', 7, 42, $ownPending));
check_case('warga_edit_other_request_denied', !$policy->authorize('warga', 'complaint', 7, 42, $otherPending));
check_case('warga_edit_accepted_denied', !$policy->authorize('warga', 'complaint', 7, 42, (object) array('user_id' => 7, 'request_status' => 'Accepted')));
check_case('warga_edit_completed_denied', !$policy->authorize('warga', 'complaint', 7, 42, $ownCompleted));
$acceptedPic = array('can_handle' => true, 'request_status' => 'Accepted', 'tenant_match' => true);
$notPic = array('can_handle' => false, 'request_status' => 'Accepted', 'tenant_match' => true);
$crossTenant = array('can_handle' => false, 'request_status' => 'Accepted', 'tenant_match' => false, 'errors' => array('tenant_mismatch'));
check_case('nakes_pic_diagnosis_allowed', $policy->authorize('dokter', 'diagnosis', 11, 42, null, $acceptedPic));
check_case('nakes_pic_medicine_allowed', $policy->authorize('dokter', 'medicine', 11, 42, null, $acceptedPic));
check_case('symptom_endpoint_assigned_nakes_allowed', $policy->authorize('dokter', 'symptom', 11, 42, null, $acceptedPic));
check_case('warga_symptom_denied', !$policy->authorize('warga', 'symptom', 7, 42, $ownPending));
check_case('nakes_not_pic_denied', !$policy->authorize('dokter', 'symptom', 11, 42, null, $notPic));
check_case('cross_puskesmas_denied', !$policy->authorize('dokter', 'diagnosis', 11, 42, null, $crossTenant));
check_case('nakes_completed_denied', !$policy->authorize('dokter', 'diagnosis', 11, 42, null, array('can_handle' => true, 'request_status' => 'Completed')));
check_case('nakes_cancelled_denied', !$policy->authorize('dokter', 'medicine', 11, 42, null, array('can_handle' => true, 'request_status' => 'Cancelled')));
check_case('nakes_missing_request_denied', !$policy->authorize('dokter', 'diagnosis', 11, 0, null, $acceptedPic));
check_case('inactive_or_invalid_identity_denied', !$policy->authorize('dokter', 'symptom', 11, 42, null, array('can_handle' => false, 'request_status' => 'Accepted', 'errors' => array('identity_invalid'))));
check_case('command_center_not_personal_pic_denied', !$policy->authorize('dokter', 'medicine', 11, 42, null, array('can_handle' => false, 'can_view' => true, 'request_status' => 'Accepted', 'account_type' => 'command_center')));
check_case('admin_not_implicitly_allowed', !$policy->authorize('admin', 'diagnosis', 1, 42, null, $acceptedPic));
check_case('invalid_domain_denied', !$policy->authorize('dokter', 'arbitrary_table', 11, 42, null, $acceptedPic));
try {
	(new ClinicalSuggestionPackage())->load($root . '/does-not-exist');
	check_case('missing_package_rejected', false);
} catch (ClinicalSuggestionPackageException $exception) {
	check_case('missing_package_rejected', $exception->getSafeCode() === 'package_root_invalid');
}

$js = file_get_contents($root . '/assets/js/doclinc-clinical-suggestions.js');
check_case('stale_response_sequence_guard', strpos($js, 'sequence !== self.sequence') !== false);
check_case('inflight_request_abort', strpos($js, 'AbortController') !== false && strpos($js, '.abort()') !== false);
check_case('safe_dom_text_rendering', strpos($js, '.textContent =') !== false && strpos($js, '.innerHTML') === false);
check_case('query_context_minimal', strpos($js, "new URLSearchParams({ type: this.type, q: query, limit: '10' })") !== false);

class FakeSuggestionResult { public function result_array() { return array(); } }
class FakeSuggestionDb
{
	public $queryCount = 0;
	public $sql = '';
	public $bindings = array();
	public function table_exists($table) { return true; }
	public function escape_like_str($value) { return str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), $value); }
	public function query($sql, $bindings) { $this->queryCount++; $this->sql = $sql; $this->bindings = $bindings; return new FakeSuggestionResult(); }
}
$model = new Clinical_suggestion_m();
$model->db = new FakeSuggestionDb();
check_case('minimum_query_no_database_search', $model->search('complaint', 'a', 'uat', 10) === array() && $model->db->queryCount === 0);
$model->search('diagnosis', 'A%_', 'uat', 999);
check_case('maximum_limit_enforced', strpos($model->db->sql, 'LIMIT 11') !== false);
check_case('wildcards_escaped', in_array('a!%!_%', $model->db->bindings, true));
check_case('lexical_rank_order', strpos($model->db->sql, 'WHEN t.term_code = ? THEN 1') !== false && strpos($model->db->sql, 'normalized_alias LIKE') !== false);
check_case('search_placeholder_binding_count_matches', substr_count($model->db->sql, '?') === count($model->db->bindings));
check_case('matched_alias_is_projected_for_localized_search_aid', strpos($model->db->sql, 'AS matched_alias') !== false
	&& strpos($model->db->sql, 'a.alias_label') !== false);
$whoDiagnosis = Clinical_suggestion_presenter::present(array(
	'term_type' => 'diagnosis',
	'reference_key' => 'J45',
	'term_code' => 'j45',
	'preferred_label' => 'Asthma',
	'matched_alias' => 'Asma',
	'source_dataset' => '20b_master_diagnosis_icd10_who_2019.json',
	'source_name' => 'WHO ICD-10 2019 via simple_icd_10 2.1.1',
	'source_version' => '2019',
	'internal_debug_source' => 'Doclinc internal source must never be exposed',
));
check_case('who_icd10_primary_label_and_secondary_metadata', is_array($whoDiagnosis)
	&& $whoDiagnosis['display'] === 'Asma'
	&& $whoDiagnosis['supporting'] === 'Asthma'
	&& $whoDiagnosis['value'] === 'J45 — Asthma'
	&& $whoDiagnosis['meta'] === 'ICD-10 J45 · WHO 2019'
	&& $whoDiagnosis['standard'] === 'WHO ICD-10'
	&& $whoDiagnosis['edition'] === '2019');
check_case('internal_source_metadata_not_exposed', is_array($whoDiagnosis)
	&& !array_key_exists('source', $whoDiagnosis)
	&& !array_key_exists('version', $whoDiagnosis)
	&& stripos(json_encode($whoDiagnosis), 'doclinc') === false);
check_case('internal_brand_in_clinical_label_rejected', Clinical_suggestion_presenter::present(array(
	'term_type' => 'diagnosis', 'reference_key' => 'J45', 'term_code' => 'J45',
	'preferred_label' => 'Doclinc Asthma', 'source_dataset' => '20b_master_diagnosis_icd10_who_2019.json', 'source_version' => '2019',
)) === null);
check_case('non_who_diagnosis_dataset_rejected', Clinical_suggestion_presenter::present(array(
	'term_type' => 'diagnosis', 'reference_key' => 'J45', 'term_code' => 'J45',
	'preferred_label' => 'Asthma', 'source_dataset' => 'internal_diagnosis.json', 'source_version' => '2019',
)) === null);
check_case('invalid_icd10_code_rejected', Clinical_suggestion_presenter::present(array(
	'term_type' => 'diagnosis', 'reference_key' => 'INTERNAL-1', 'term_code' => 'INTERNAL-1',
	'preferred_label' => 'Asthma', 'source_dataset' => '20b_master_diagnosis_icd10_who_2019.json', 'source_version' => '2019',
)) === null);
check_case('non_2019_icd10_source_rejected', Clinical_suggestion_presenter::present(array(
	'term_type' => 'diagnosis', 'reference_key' => 'J45', 'term_code' => 'J45',
	'preferred_label' => 'Asthma', 'source_dataset' => '20b_master_diagnosis_icd10_who_2019.json', 'source_version' => 'internal',
)) === null);
$medicinePresentation = Clinical_suggestion_presenter::present(array(
	'term_type' => 'medicine', 'reference_key' => 'FORNAS-1', 'term_code' => 'FORNAS-1',
	'preferred_label' => 'Paracetamol 500 mg', 'source_dataset' => '21_master_obat_fornas.json',
	'source_name' => 'e-Fornas Kementerian Kesehatan RI',
));
check_case('medicine_primary_label_has_no_internal_prefix', is_array($medicinePresentation)
	&& $medicinePresentation['display'] === 'Paracetamol 500 mg'
	&& $medicinePresentation['value'] === 'Paracetamol 500 mg'
	&& $medicinePresentation['meta'] === 'Formularium Nasional'
	&& $medicinePresentation['standard'] === 'Formularium Nasional');
check_case('non_fornas_medicine_dataset_rejected', Clinical_suggestion_presenter::present(array(
	'term_type' => 'medicine', 'reference_key' => 'INTERNAL-1', 'term_code' => 'INTERNAL-1',
	'preferred_label' => 'Paracetamol 500 mg', 'source_dataset' => 'internal_medicine.json',
	'source_name' => 'e-Fornas Kementerian Kesehatan RI',
)) === null);
check_case('non_efornas_medicine_source_rejected', Clinical_suggestion_presenter::present(array(
	'term_type' => 'medicine', 'reference_key' => 'FORNAS-1', 'term_code' => 'FORNAS-1',
	'preferred_label' => 'Paracetamol 500 mg', 'source_dataset' => '21_master_obat_fornas.json',
	'source_name' => 'internal_medicine_source',
)) === null);
$featureMissing = Clinical_suggestion_feature::resolve(false, false);
$featureEmpty = Clinical_suggestion_feature::resolve('', '');
$featureMalformed = Clinical_suggestion_feature::resolve('definitely', 'uat');
$featureProduction = Clinical_suggestion_feature::resolve('true', 'production');
check_case('flag_missing_is_off', $featureMissing['enabled'] === false && $featureMissing['environment'] === '');
check_case('flag_empty_is_off', $featureEmpty['enabled'] === false && $featureEmpty['environment'] === '');
check_case('flag_malformed_is_off', $featureMalformed['enabled'] === false);
check_case('flag_false_values_are_off', !Clinical_suggestion_feature::resolve('false', 'uat')['enabled']
	&& !Clinical_suggestion_feature::resolve('0', 'uat')['enabled']
	&& !Clinical_suggestion_feature::resolve('off', 'uat')['enabled']
	&& !Clinical_suggestion_feature::resolve('no', 'uat')['enabled']);
check_case('production_environment_rejected', $featureProduction['enabled'] === false && $featureProduction['environment'] === '');
check_case('uat_environment_can_enable', Clinical_suggestion_feature::resolve('true', 'uat')['enabled'] === true);

$normalizedAnamnesis = Clinical_anamnesis::normalize("  Demam\r\nBatuk  ");
check_case('anamnesis_line_endings_normalized', $normalizedAnamnesis['valid'] && $normalizedAnamnesis['value'] === "Demam\nBatuk");
check_case('empty_anamnesis_is_safe', Clinical_anamnesis::normalize(" \r\n ")['value'] === null);
check_case('array_payload_rejected', Clinical_anamnesis::normalize(array('invalid'))['error'] === 'invalid_payload_type');
check_case('oversized_anamnesis_rejected', Clinical_anamnesis::normalize(str_repeat('a', 5001))['error'] === 'value_too_long');
$htmlAnamnesis = Clinical_anamnesis::normalize('<script>alert(1)</script>');
check_case('anamnesis_kept_as_plain_text', $htmlAnamnesis['valid'] && $htmlAnamnesis['value'] === '<script>alert(1)</script>'
	&& strpos(htmlspecialchars($htmlAnamnesis['value'], ENT_QUOTES, 'UTF-8'), '<script>') === false);

$nakesView = file_get_contents($root . '/application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');
$nakesController = file_get_contents($root . '/application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
$nakesModel = file_get_contents($root . '/application/modules/konsultasi_nakes/models/Konsultasi_nakes_m.php');
check_case('anamnesis_not_appended_to_saran', strpos($nakesView, 'Anamnesis / Gejala Nakes:') === false);
check_case('anamnesis_not_appended_to_legacy_fields', strpos($nakesController, '$saran .=') === false
	&& strpos($nakesController, '$diagnosa .=') === false
	&& strpos($nakesController, '$kriteria .=') === false
	&& strpos($nakesModel, "'recommendations' => \$anamnesis") === false
	&& strpos($nakesModel, "'diagnosis' => \$anamnesis") === false);
check_case('anamnesis_not_appended_to_diagnosa', strpos($nakesController, '$diagnosa .=') === false);
check_case('anamnesis_not_appended_to_kriteria', strpos($nakesController, '$kriteria .=') === false);
check_case('anamnesis_field_has_semantic_payload', strpos($nakesView, 'name="anamnesis"') !== false
	&& strpos($nakesView, 'data-clinical-suggestion-type="symptom"') !== false
	&& strpos($nakesView, 'name="saran"') !== false);
check_case('flag_off_preserves_legacy_payload', strpos($nakesView, '<?php if ($anamnesis_enabled) : ?>') !== false
	&& strpos($nakesController, "\$write_anamnesis = (bool) \$this->config->item('clinical_suggestions_enabled')") !== false);
check_case('flag_on_without_schema_fails_closed', strpos($nakesController, 'Clinical_anamnesis::schema_ready') !== false
	&& strpos($nakesController, 'set_status_header(503)') !== false);
check_case('flag_on_with_schema_saves_anamnesis', strpos($nakesController, '$normalized_anamnesis') !== false
	&& strpos($nakesModel, "\$record['anamnesis'] = \$anamnesis") !== false);
check_case('completion_transaction_contains_anamnesis_write', strpos($nakesModel, '$this->db->trans_begin()') !== false
	&& strpos($nakesModel, "ORDER BY record_id DESC LIMIT 1 FOR UPDATE") !== false
	&& strpos($nakesModel, "'anamnesis'") !== false
	&& strpos($nakesModel, '$this->db->trans_rollback()') !== false
	&& strpos($nakesModel, '$this->db->trans_commit()') !== false);
check_case('duplicate_medicalrecord_not_created_for_existing', strpos($nakesModel, "where('record_id', (int) \$existing->record_id)") !== false);
check_case('empty_anamnesis_does_not_overwrite_existing', strpos($nakesModel, 'if ($anamnesis !== null || !$existing)') !== false);
$homeModelSource = file_get_contents($root . '/application/modules/home/models/Home_m.php');
$homeViewSource = file_get_contents($root . '/application/modules/home/views/home_v.php');
$nakesHistorySource = file_get_contents($root . '/application/modules/home_nakes/views/partials/nakes_history_v.php');
check_case('legacy_record_without_anamnesis_still_renders', strpos($homeModelSource, 'NULL AS anamnesis') !== false
	&& strpos($homeViewSource, "if (\$anamnesis !== '')") !== false);
check_case('anamnesis_history_is_escaped', strpos($homeViewSource, 'nl2br(html_escape($anamnesis)') !== false
	&& strpos($nakesHistorySource, 'nl2br(html_escape($anamnesis)') !== false);
check_case('legacy_non_who_diagnosis_endpoint_retired', strpos($nakesController, 'public function getICD_json()') !== false
	&& strpos($nakesController, 'show_404();') !== false
	&& strpos($nakesView, "base_url('konsultasi_nakes/getICD_json')") === false);
$suggestionController = file_get_contents($root . '/application/controllers/Clinical_suggestions.php');
check_case('endpoint_uses_database_identity_and_assignment_helper', strpos($suggestionController, 'doclinc_dokter_identity_context') !== false
	&& strpos($suggestionController, 'doclinc_nakes_request_access_context') !== false);
check_case('safe_json_encoding', strpos($suggestionController, 'JSON_HEX_TAG') !== false
	&& strpos($suggestionController, 'JSON_HEX_AMP') !== false
	&& strpos($suggestionController, 'JSON_HEX_APOS') !== false
	&& strpos($suggestionController, 'JSON_HEX_QUOT') !== false);
check_case('endpoint_uses_clinical_presenter_without_internal_source_output', strpos($suggestionController, 'Clinical_suggestion_presenter::present') !== false
	&& strpos($suggestionController, "'source' =>") === false
	&& strpos($suggestionController, "'version' =>") === false);
$migrationScript = $root . '/application/migrations/20260726000100_clinical_suggestions_uat_foundation.php';
list($migrationPlanCode, $migrationPlanOut) = run_php_cli(array($migrationScript));
check_case('migration_direct_command_valid', $migrationPlanCode === 0
	&& strpos($migrationPlanOut, 'EXECUTION_MODE=PLAN') !== false
	&& strpos($migrationPlanOut, 'DDL_EXECUTED=false') !== false);
$migrationSource = file_get_contents($migrationScript);
check_case('migration_partial_schema_fails_closed', strpos($migrationSource, 'partial_schema_collision') !== false
	&& strpos($migrationSource, 'GET_LOCK') !== false
	&& strpos($migrationSource, 'array_reverse($created_tables)') !== false);
$anamnesisMigrationScript = $root . '/application/migrations/20260727000200_medicalrecords_anamnesis_foundation.php';
list($anamnesisPlanCode, $anamnesisPlanOut) = run_php_cli(array($anamnesisMigrationScript));
check_case('anamnesis_migration_direct_command_valid', $anamnesisPlanCode === 0
	&& strpos($anamnesisPlanOut, 'EXECUTION_MODE=PLAN') !== false
	&& strpos($anamnesisPlanOut, 'DDL_EXECUTED=false') !== false
	&& strpos($anamnesisPlanOut, 'EXISTING_ROWS_CHANGED=false') !== false);
$anamnesisMigrationSource = file_get_contents($anamnesisMigrationScript);
check_case('anamnesis_migration_apply_explicit', strpos($anamnesisMigrationSource, "array_key_exists('apply', \$options)") !== false
	&& strpos($anamnesisMigrationSource, 'DOCLINC_CLINICAL_ANAMNESIS_SCHEMA_WRITE_ENABLED') !== false
	&& strpos($anamnesisMigrationSource, "hash_equals('doclinc-staging', \$database)") !== false);
check_case('anamnesis_migration_named_lock_and_signature', strpos($anamnesisMigrationSource, 'GET_LOCK') !== false
	&& strpos($anamnesisMigrationSource, 'anamnesis_column_signature_mismatch') !== false
	&& strpos($anamnesisMigrationSource, 'DROP COLUMN') === false);
list($staticBatchCode, $staticBatchOut) = run_php_cli(array(
	$root . '/tools/clinical_suggestions/clinical_suggestions.php',
	'import', '--environment=uat', '--batch-reference=uat-ac1-20260726'
));
check_case('unique_batch_reference_required', $staticBatchCode !== 0
	&& strpos($staticBatchOut, 'unique_batch_reference_required') !== false);

$packageRoot = null;
foreach (array_slice($argv, 1) as $argument) {
	if (strpos($argument, '--package-root=') === 0) $packageRoot = substr($argument, 15);
}
if ($packageRoot !== null) {
	$package = (new ClinicalSuggestionPackage())->load($packageRoot);
	$byType = array();
	$whoDiagnosisContractValid = true;
	$fornasMedicineContractValid = true;
	foreach ($package['terms'] as $term) {
		$byType[$term['type']] = ($byType[$term['type']] ?? 0) + 1;
		if ($term['type'] === 'diagnosis' && (
			$term['source_dataset'] !== Clinical_suggestion_presenter::WHO_ICD10_DATASET
			|| preg_match('/\A[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?[†*]?\z/u', strtoupper($term['code'])) !== 1
			|| preg_match('/(?:doclinc|doklinc|doclink)/iu', $term['label']) === 1
		)) {
			$whoDiagnosisContractValid = false;
		}
		if ($term['type'] === 'medicine' && (
			$term['source_dataset'] !== Clinical_suggestion_presenter::FORNAS_DATASET
			|| $term['source_name'] !== Clinical_suggestion_presenter::FORNAS_SOURCE
			|| strpos($term['code'], 'EFORNAS_') !== 0
			|| preg_match('/(?:doclinc|doklinc|doclink)/iu', $term['label']) === 1
		)) {
			$fornasMedicineContractValid = false;
		}
	}
	check_case('official_package_inspection_and_allowlist', $package['datasets'] === array('01_master_keluhan.json', '02_master_gejala.json', '20b_master_diagnosis_icd10_who_2019.json', '20c_master_diagnosis_alias_indonesia_starter.json', '21_master_obat_fornas.json'));
	check_case('all_diagnoses_match_who_icd10_2019_presentation_contract', $whoDiagnosisContractValid);
	check_case('all_medicines_match_efornas_name_reference_contract', $fornasMedicineContractValid);
	check_case('complaint_source_count', ($byType['complaint'] ?? 0) === 35);
	check_case('symptom_source_count', ($byType['symptom'] ?? 0) === 94);
	check_case('diagnosis_selectable_source_count', ($byType['diagnosis'] ?? 0) === 10658);
	check_case('medicine_source_count', ($byType['medicine'] ?? 0) === 663);
	check_case('package_runtime_stays_disabled', $package['package_checksum'] === 'fa512de0909e5e1f4fe6d394ca3b8103f5cefd98389f39ae016c4438e992fc34');
}

echo "TESTS_PASSED=" . $passed . "\nTESTS_FAILED=" . $failed . "\n";
exit($failed === 0 ? 0 : 1);
