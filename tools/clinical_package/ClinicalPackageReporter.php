<?php

class ClinicalPackageReporter
{
	public static function success($command, array $model, $comparison = null)
	{
		$package = $model['package']['persisted'];
		$report = array(
			'command' => $command,
			'package_key' => $package['package_key'],
			'package_version' => $package['package_version'],
			'manifest_schema_version' => $package['manifest_schema_version'],
			'manifest_checksum' => $package['manifest_checksum'],
			'package_checksum' => $model['package_checksum'],
			'package_checksum_profile' => $model['package_checksum_profile'],
			'package_file_count' => $model['package_file_count'],
			'package_snapshot_sha256' => $model['package_snapshot_sha256'],
			'declared_dataset_count' => $package['declared_dataset_count'],
			'observed_dataset_count' => $package['observed_dataset_count'],
			'declared_record_count' => $package['declared_record_count'],
			'observed_record_count' => $package['observed_record_count'],
			'production_ready' => false,
			'source_runtime_enabled' => false,
			'dml_executed' => false,
			'package_data_imported' => false,
			'runtime_enabled' => false,
			'error_count' => 0,
			'exit_code' => 0,
			'validation_result' => 'PASS',
		);
		if ($command === 'plan-registration') {
			$report += array(
				'database_comparison' => $comparison['database_comparison'],
				'package_state' => $comparison['package_state'],
				'package_row_plan_count' => $model['counts']['package_rows'],
				'dataset_row_plan_count' => $model['counts']['dataset_rows'],
				'package_capability_plan_count' => $model['counts']['package_capabilities'],
				'dataset_capability_plan_count' => $model['counts']['dataset_capabilities'],
				'field_contract_plan_count' => $model['counts']['field_contracts'],
				'canonical_record_plan_count' => 0,
				'import_item_plan_count' => 0,
				'new_entity_count' => $comparison['new_entity_count'],
				'exact_match_entity_count' => $comparison['exact_match_entity_count'],
				'conflict_entity_count' => $comparison['conflict_entity_count'],
			);
			if ($comparison['failed']) {
				$report['error_count'] = 1;
				$report['error_1'] = 'registration_state_conflict';
				$report['exit_code'] = 1;
				$report['validation_result'] = 'FAIL';
			}
		} elseif ($command === 'register-metadata') {
			$report += array(
				'registration_result' => $comparison['registration_result'],
				'dry_run' => $comparison['dry_run'],
				'planned_metadata_rows' => $comparison['planned_metadata_rows'],
				'existing_metadata_rows' => $comparison['existing_metadata_rows'],
				'conflict_count' => $comparison['conflict_count'],
				'metadata_rows_written' => $comparison['metadata_rows_written'],
				'audit_rows_written' => $comparison['audit_rows_written'],
				'transaction_committed' => $comparison['transaction_committed'],
				'lock_cleanup_status' => isset($comparison['lock_cleanup_status']) ? $comparison['lock_cleanup_status'] : null,
				'cleanup_warning' => isset($comparison['cleanup_warning']) ? $comparison['cleanup_warning'] : null,
				'write_executed' => $comparison['write_executed'],
				'registration_reference' => isset($comparison['registration_reference']) ? $comparison['registration_reference'] : null,
			);
			$report['dml_executed'] = $comparison['write_executed'];
		}
		return $report;
	}

	public static function failure($command, $code, array $context = array(), $exitCode = 1)
	{
		$report = array(
			'command' => (string) $command,
			'dml_executed' => false,
			'package_data_imported' => false,
			'runtime_enabled' => false,
			'error_count' => 1,
			'error_1' => (string) $code,
			'exit_code' => (int) $exitCode,
			'validation_result' => 'FAIL',
		);
		if ($command === 'register-metadata') {
			$writerDefaults = array(
				'registration_result' => 'failed',
				'dry_run' => false,
				'planned_metadata_rows' => 0,
				'existing_metadata_rows' => 0,
				'conflict_count' => 0,
				'metadata_rows_written' => 0,
				'audit_rows_written' => 0,
				'transaction_committed' => false,
				'lock_cleanup_status' => null,
				'cleanup_warning' => null,
				'write_executed' => false,
			);
			foreach ($writerDefaults as $key => $default) {
				$report[$key] = array_key_exists($key, $context) ? $context[$key] : $default;
				unset($context[$key]);
			}
			$report['dml_executed'] = $report['write_executed'];
		}
		if (count($context) > 0) $report['error_1_context'] = $context;
		return $report;
	}

	public static function render(array $report, $format)
	{
		$ordered = self::ordered($report);
		if ($format === 'json') {
			$json = json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
			if ($json === false) {
				echo '{"command":"error","dml_executed":false,"error_1":"output_encoding_failed","error_count":1,"exit_code":2,"package_data_imported":false,"runtime_enabled":false,"validation_result":"FAIL"}' . PHP_EOL;
				return 2;
			}
			echo $json . PHP_EOL;
			return isset($ordered['exit_code']) ? (int) $ordered['exit_code'] : 2;
		}
		foreach ($ordered as $key => $value) {
			echo strtoupper($key) . '=' . self::textValue($value) . PHP_EOL;
		}
		return isset($ordered['exit_code']) ? (int) $ordered['exit_code'] : 2;
	}

	private static function ordered(array $report)
	{
		$order = array(
			'command','package_key','package_version','manifest_schema_version','manifest_checksum','package_checksum','package_checksum_profile','package_file_count','package_snapshot_sha256',
			'declared_dataset_count','observed_dataset_count','declared_record_count','observed_record_count','production_ready','source_runtime_enabled','database_comparison','package_state',
			'package_row_plan_count','dataset_row_plan_count','package_capability_plan_count','dataset_capability_plan_count','field_contract_plan_count','canonical_record_plan_count','import_item_plan_count',
			'new_entity_count','exact_match_entity_count','conflict_entity_count','registration_result','dry_run','planned_metadata_rows','existing_metadata_rows','conflict_count',
			'metadata_rows_written','audit_rows_written','transaction_committed','lock_cleanup_status','cleanup_warning','registration_reference','write_executed','dml_executed','package_data_imported','runtime_enabled','error_1','error_1_context','error_count','exit_code','validation_result',
		);
		$result = array();
		foreach ($order as $key) if (array_key_exists($key, $report)) $result[$key] = $report[$key];
		$remaining = array_diff_key($report, $result);
		ksort($remaining, SORT_STRING);
		return $result + $remaining;
	}

	private static function textValue($value)
	{
		if ($value === true) return 'true';
		if ($value === false) return 'false';
		if ($value === null) return '';
		if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		$value = (string) $value;
		return preg_replace_callback('/[\x00-\x1F\x7F]/', function ($match) { return sprintf('\\x%02X', ord($match[0])); }, $value);
	}
}
