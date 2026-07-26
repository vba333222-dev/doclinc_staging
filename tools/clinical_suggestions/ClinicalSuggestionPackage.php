<?php

class ClinicalSuggestionPackageException extends RuntimeException
{
	private $safeCode;

	public function __construct($safeCode)
	{
		parent::__construct('controlled_clinical_suggestion_failure');
		$this->safeCode = (string) $safeCode;
	}

	public function getSafeCode()
	{
		return $this->safeCode;
	}
}

class ClinicalSuggestionPackage
{
	const PACKAGE_CLI = __DIR__ . '/../clinical_package/clinical_package.php';
	const MANIFEST = '00_manifest.json';

	private static $datasets = array(
		'01_master_keluhan.json' => 'complaint',
		'02_master_gejala.json' => 'symptom',
		'20b_master_diagnosis_icd10_who_2019.json' => 'diagnosis',
		'20c_master_diagnosis_alias_indonesia_starter.json' => 'diagnosis_alias',
		'21_master_obat_fornas.json' => 'medicine',
	);

	public function load($packageRoot)
	{
		$root = realpath((string) $packageRoot);
		if ($root === false || !is_dir($root) || !$this->isAbsolute($root)) {
			throw new ClinicalSuggestionPackageException('package_root_invalid');
		}
		$inspection = $this->inspect($root);
		$manifest = $this->jsonFile($root, self::MANIFEST);
		if (($manifest['package_id'] ?? '') !== 'doclink-clinical-master' || ($manifest['package_version'] ?? '') !== 'v1') {
			throw new ClinicalSuggestionPackageException('package_identity_mismatch');
		}

		$terms = array();
		$diagnosisAliases = array();
		$counts = array('source_records' => 0, 'eligible_terms' => 0, 'eligible_aliases' => 0, 'duplicates' => 0, 'rejected' => 0);
		foreach (self::$datasets as $filename => $type) {
			$payload = $this->jsonFile($root, $filename);
			if (!isset($payload['data']) || !is_array($payload['data'])) {
				throw new ClinicalSuggestionPackageException('dataset_contract_invalid');
			}
			foreach ($payload['data'] as $record) {
				$counts['source_records']++;
				if (!is_array($record) || empty($record['aktif'])) {
					$counts['rejected']++;
					continue;
				}
				if ($type === 'diagnosis_alias') {
					$code = trim((string) ($record['kode_icd10'] ?? ''));
					$label = trim((string) ($record['nama_indonesia'] ?? ''));
					if ($code === '' || $label === '') {
						$counts['rejected']++;
						continue;
					}
					$diagnosisAliases[$code][] = $this->aliasRecord($label, $record, $filename);
					foreach (($record['kata_kunci'] ?? array()) as $alias) {
						if (is_string($alias) && trim($alias) !== '') $diagnosisAliases[$code][] = $this->aliasRecord(trim($alias), $record, $filename);
					}
					continue;
				}

				$term = $this->mapTerm($type, $filename, $record);
				if ($term === null) {
					$counts['rejected']++;
					continue;
				}
				$key = $type . "\0" . $term['reference_key'];
				if (isset($terms[$key])) {
					$counts['duplicates']++;
					if ($terms[$key] !== $term) throw new ClinicalSuggestionPackageException('source_natural_key_conflict');
					continue;
				}
				$terms[$key] = $term;
			}
		}

		foreach ($diagnosisAliases as $code => $aliases) {
			$key = 'diagnosis' . "\0" . $code;
			if (!isset($terms[$key])) {
				$counts['rejected'] += count($aliases);
				continue;
			}
			$terms[$key]['aliases'] = array_merge($terms[$key]['aliases'], $aliases);
		}
		foreach ($terms as &$term) {
			$normalizedAliases = array();
			foreach ($term['aliases'] as $alias) {
				$normalized = self::normalize($alias['label']);
				if ($normalized === '' || $normalized === $term['normalized_label']) continue;
				$normalizedAliases[$normalized] = $alias;
			}
			ksort($normalizedAliases, SORT_STRING);
			$term['aliases'] = $normalizedAliases;
			$counts['eligible_aliases'] += count($normalizedAliases);
		}
		unset($term);
		ksort($terms, SORT_STRING);
		$counts['eligible_terms'] = count($terms);

		return array(
			'package_id' => (string) $manifest['package_id'],
			'package_version' => (string) $manifest['package_version'],
			'package_checksum' => (string) $inspection['package_checksum'],
			'package_snapshot_sha256' => (string) $inspection['package_snapshot_sha256'],
			'terms' => array_values($terms),
			'counts' => $counts,
			'datasets' => array_keys(self::$datasets),
		);
	}

	public static function normalize($value)
	{
		$value = preg_replace('/\s+/u', ' ', trim((string) $value));
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	private function mapTerm($type, $filename, array $record)
	{
		if ($type === 'diagnosis' && empty($record['dapat_dipilih_dalam_diagnosis'])) return null;
		$code = trim((string) ($type === 'medicine' ? ($record['kode'] ?? '') : ($record['kode'] ?? '')));
		if ($type === 'diagnosis') $code = trim((string) ($record['kode_icd10'] ?? $code));
		$label = '';
		if ($type === 'complaint') $label = trim((string) ($record['nama_pasien'] ?? $record['nama'] ?? ''));
		elseif ($type === 'symptom') $label = trim((string) ($record['label_klinis'] ?? $record['nama'] ?? ''));
		elseif ($type === 'diagnosis') $label = trim((string) ($record['nama_internasional'] ?? $record['nama'] ?? ''));
		elseif ($type === 'medicine') $label = trim((string) ($record['nama_obat'] ?? ''));
		if ($code === '' || $label === '' || strlen($code) > 128 || strlen($label) > 255) return null;

		$aliases = array();
		foreach (array('nama', 'nama_pasien', 'label_klinis', 'nama_internasional', 'nama_indonesia') as $field) {
			if (isset($record[$field]) && is_string($record[$field])) $aliases[] = $this->aliasRecord($record[$field], $record, $filename);
		}
		foreach (($record['kata_kunci'] ?? array()) as $alias) {
			if (is_string($alias)) $aliases[] = $this->aliasRecord($alias, $record, $filename);
		}
		return array(
			'type' => $type,
			'reference_key' => $code,
			'code' => $code,
			'label' => $label,
			'normalized_label' => self::normalize($label),
			'source_name' => trim((string) ($record['sumber'] ?? '')),
			'source_version' => trim((string) ($record['versi_sumber'] ?? '')),
			'source_dataset' => $filename,
			'source_governance_status' => trim((string) ($record['status_review_klinis'] ?? 'unreviewed')),
			'aliases' => $aliases,
		);
	}

	private function aliasRecord($label, array $record, $filename)
	{
		return array(
			'label' => trim((string) $label),
			'source_name' => trim((string) ($record['sumber'] ?? '')),
			'source_version' => trim((string) ($record['versi_sumber'] ?? '')),
			'source_dataset' => (string) $filename,
			'source_governance_status' => trim((string) ($record['status_review_klinis'] ?? 'unreviewed')),
		);
	}

	private function inspect($root)
	{
		if (!is_file(self::PACKAGE_CLI)) throw new ClinicalSuggestionPackageException('official_inspector_unavailable');
		$command = array(PHP_BINARY, self::PACKAGE_CLI, 'inspect', '--package-root=' . $root, '--format=json');
		$spec = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
		$process = proc_open($command, $spec, $pipes, null, null, array('bypass_shell' => true));
		if (!is_resource($process)) throw new ClinicalSuggestionPackageException('official_inspector_unavailable');
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit = proc_close($process);
		$report = json_decode((string) $output, true);
		if ($exit !== 0 || !is_array($report) || ($report['validation_result'] ?? '') !== 'PASS'
			|| !empty($report['dml_executed']) || !empty($report['package_data_imported']) || !empty($report['runtime_enabled'])) {
			throw new ClinicalSuggestionPackageException('official_inspection_failed');
		}
		return $report;
	}

	private function jsonFile($root, $filename)
	{
		$path = $root . DIRECTORY_SEPARATOR . $filename;
		$resolved = realpath($path);
		if ($resolved === false || !is_file($resolved) || dirname($resolved) !== $root) {
			throw new ClinicalSuggestionPackageException('dataset_path_invalid');
		}
		$data = json_decode(file_get_contents($resolved), true, 512, JSON_BIGINT_AS_STRING);
		if (!is_array($data)) throw new ClinicalSuggestionPackageException('dataset_json_invalid');
		return $data;
	}

	private function isAbsolute($path)
	{
		return strpos($path, DIRECTORY_SEPARATOR) === 0 || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
	}
}
