<?php

class ClinicalPackageLoader
{
	private $pathPolicy;
	private $json;
	private $legacyValidatorFile;
	private $legacyRulesFile;

	public function __construct(
		ClinicalPackagePathPolicy $pathPolicy,
		StrictJsonDecoder $json,
		$legacyValidatorFile,
		$legacyRulesFile
	) {
		$this->pathPolicy = $pathPolicy;
		$this->json = $json;
		$this->legacyValidatorFile = $legacyValidatorFile;
		$this->legacyRulesFile = $legacyRulesFile;
	}

	public function load($requestedRoot)
	{
		$resolved = $this->pathPolicy->resolveAndInventory($requestedRoot);
		$root = $resolved['root'];
		$inventory = $this->hashInventory($resolved['files']);
		$byPath = array();
		foreach ($inventory as $file) {
			$byPath[$file['relative_path']] = $file;
		}
		foreach (array('00_manifest.json', '00_manifest.schema.json', '00_icd10_manifest.json', 'SHA256SUMS.txt') as $required) {
			if (!isset($byPath[$required])) {
				throw new ClinicalPackageException('package_control_file_missing', 'A required package control file is missing.', array('path' => $required));
			}
		}

		$manifestObject = $this->json->decodeFile($byPath['00_manifest.json']['absolute_path'], '00_manifest.json');
		if (!is_object($manifestObject)) {
			throw new ClinicalPackageException('manifest_root_type_invalid', 'Package manifest root must be an object.', array('path' => '00_manifest.json'));
		}
		$manifest = $this->toArray($manifestObject);
		if (!isset($manifest['datasets']) || !is_array($manifest['datasets'])) {
			throw new ClinicalPackageException('manifest_dataset_inventory_invalid', 'Package manifest dataset inventory is unavailable.');
		}
		$this->assertSafePackageIdentity($manifest);

		$declaredPaths = array(
			'00_manifest.json' => true,
			'00_manifest.schema.json' => true,
			'00_icd10_manifest.json' => true,
			'SHA256SUMS.txt' => true,
		);
		$datasetKeys = array();
		$datasetPaths = array();
		foreach ($manifest['datasets'] as $dataset) {
			if (!is_array($dataset) || !isset($dataset['dataset_id'], $dataset['path']) || !is_string($dataset['dataset_id']) || !is_string($dataset['path'])) {
				throw new ClinicalPackageException('manifest_dataset_entry_invalid', 'A dataset inventory entry is invalid.');
			}
			if (isset($datasetKeys[$dataset['dataset_id']])) {
				throw new ClinicalPackageException('duplicate_dataset_key', 'Dataset keys must be unique.', array('dataset_key' => $dataset['dataset_id']));
			}
			if (isset($datasetPaths[$dataset['path']])) {
				throw new ClinicalPackageException('duplicate_dataset_path', 'Dataset paths must be unique.', array('path' => $dataset['path']));
			}
			$this->pathPolicy->validateRelativePath($dataset['path']);
			$datasetKeys[$dataset['dataset_id']] = true;
			$datasetPaths[$dataset['path']] = true;
			$declaredPaths[$dataset['path']] = true;
		}
		foreach ($byPath as $relative => $unused) {
			if (!isset($declaredPaths[$relative])) {
				throw new ClinicalPackageException('undeclared_package_file', 'Package contains an undeclared file.', array('path' => $relative));
			}
		}
		foreach ($declaredPaths as $relative => $unused) {
			if (!isset($byPath[$relative])) {
				throw new ClinicalPackageException('declared_package_file_missing', 'A declared package file is missing.', array('path' => $relative));
			}
		}

		$schema = $this->json->decodeFile($byPath['00_manifest.schema.json']['absolute_path'], '00_manifest.schema.json');
		if (!is_object($schema)) {
			throw new ClinicalPackageException('manifest_schema_root_type_invalid', 'Manifest schema root must be an object.', array('path' => '00_manifest.schema.json'));
		}
		$submanifest = $this->json->decodeFile($byPath['00_icd10_manifest.json']['absolute_path'], '00_icd10_manifest.json');
		if (!is_object($submanifest)) {
			throw new ClinicalPackageException('submanifest_root_type_invalid', 'Submanifest root must be an object.', array('path' => '00_icd10_manifest.json'));
		}

		$observedDatasets = array();
		$observedRecordTotal = 0;
		foreach ($manifest['datasets'] as $dataset) {
			$path = $dataset['path'];
			$file = $byPath[$path];
			if (!isset($dataset['sha256']) || !is_string($dataset['sha256']) || !hash_equals($dataset['sha256'], $file['sha256'])) {
				throw new ClinicalPackageException('dataset_checksum_mismatch', 'Dataset bytes do not match the declared checksum.', array('dataset_key' => $dataset['dataset_id'], 'path' => $path));
			}
			$document = $this->json->decodeFile($file['absolute_path'], $path);
			$rows = $this->datasetRows($document, isset($dataset['json_root_type']) ? $dataset['json_root_type'] : null, $dataset['dataset_id']);
			if (!isset($dataset['record_count']) || !is_int($dataset['record_count']) || $dataset['record_count'] !== count($rows)) {
				throw new ClinicalPackageException('dataset_record_count_mismatch', 'Dataset record count does not match its declaration.', array('dataset_key' => $dataset['dataset_id']));
			}
			$contracts = $this->observeFieldContracts($rows, $dataset);
			$observedDatasets[] = array(
				'manifest' => $dataset,
				'observed_record_count' => count($rows),
				'observed_sha256' => $file['sha256'],
				'field_contracts' => $contracts,
			);
			$observedRecordTotal += count($rows);
			unset($document, $rows);
		}

		$declaredDatasetCount = isset($manifest['package_summary']['dataset_count']) ? $manifest['package_summary']['dataset_count'] : null;
		$declaredRecordCount = isset($manifest['package_summary']['record_count']) ? $manifest['package_summary']['record_count'] : null;
		if (!is_int($declaredDatasetCount) || $declaredDatasetCount !== count($observedDatasets)) {
			throw new ClinicalPackageException('package_dataset_count_mismatch', 'Package dataset count does not match observed inventory.');
		}
		if (!is_int($declaredRecordCount) || $declaredRecordCount !== $observedRecordTotal) {
			throw new ClinicalPackageException('package_record_count_mismatch', 'Package record count does not match observed records.');
		}

		$this->assertRuntimeAndLicensePolicy($manifest);
		$this->runEstablishedValidator($root);

		return array(
			'root' => $root,
			'manifest' => $manifest,
			'manifest_checksum' => $byPath['00_manifest.json']['sha256'],
			'package_file_count' => count($inventory),
			'package_snapshot_sha256' => $this->snapshotChecksum($inventory),
			'observed_dataset_count' => count($observedDatasets),
			'observed_record_count' => $observedRecordTotal,
			'datasets' => $observedDatasets,
		);
	}

	private function runEstablishedValidator($root)
	{
		foreach (array($this->legacyValidatorFile, $this->legacyRulesFile) as $dependency) {
			if (!is_string($dependency) || !is_file($dependency) || !is_readable($dependency)) {
				throw new ClinicalPackageException('established_validator_dependency_unavailable', 'An established package-validator dependency is unavailable.');
			}
		}
		@require_once $this->legacyValidatorFile;
		$rules = @require $this->legacyRulesFile;
		if (!class_exists('ClinicalMasterValidator', false) || !is_array($rules)) {
			throw new ClinicalPackageException('established_validator_dependency_invalid', 'An established package-validator dependency is invalid.');
		}
		$validator = new ClinicalMasterValidator($root, $rules);
		$report = $validator->validate();
		if (!isset($report['validation_result']) || $report['validation_result'] !== 'PASS') {
			$first = isset($report['errors'][0]['code']) ? (string) $report['errors'][0]['code'] : 'unknown';
			throw new ClinicalPackageException('established_package_validation_failed', 'Established clinical package validation failed.', array('validator_code' => $first));
		}
	}

	private function hashInventory(array $inventory)
	{
		$result = array();
		foreach ($inventory as $file) {
			$hash = @hash_file('sha256', $file['absolute_path']);
			if ($hash === false) {
				throw new ClinicalPackageException('package_file_hash_failed', 'A package file could not be hashed.', array('path' => $file['relative_path']));
			}
			$file['sha256'] = strtolower($hash);
			$result[] = $file;
		}
		return $result;
	}

	private function snapshotChecksum(array $inventory)
	{
		$projection = '';
		foreach ($inventory as $file) {
			$projection .= $file['relative_path'] . "\t" . $file['byte_size'] . "\t" . $file['sha256'] . "\n";
		}
		return hash('sha256', $projection);
	}

	private function datasetRows($document, $declaredRootType, $datasetKey)
	{
		if ($declaredRootType === 'array' && is_array($document)) {
			return $document;
		}
		if ($declaredRootType === 'object_with_data_array' && is_object($document) && isset($document->data) && is_array($document->data)) {
			return $document->data;
		}
		throw new ClinicalPackageException('dataset_root_type_mismatch', 'Dataset JSON root does not match its declaration.', array('dataset_key' => $datasetKey));
	}

	private function observeFieldContracts(array $rows, array $dataset)
	{
		$fields = array();
		$ordinal = 0;
		foreach ($rows as $row) {
			if (!is_object($row)) {
				throw new ClinicalPackageException('dataset_record_not_object', 'Dataset records must be JSON objects.', array('dataset_key' => $dataset['dataset_id']));
			}
			foreach (get_object_vars($row) as $name => $value) {
				if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
					throw new ClinicalPackageException('dataset_field_name_invalid', 'Dataset field name cannot be represented safely.', array('dataset_key' => $dataset['dataset_id']));
				}
				if (!isset($fields[$name])) {
					$fields[$name] = array('source_field' => $name, 'field_order' => $ordinal++, 'present_count' => 0, 'nullable_flag' => 0, 'types' => array());
				}
				$fields[$name]['present_count']++;
				$type = $this->jsonType($value);
				if ($type === 'null') {
					$fields[$name]['nullable_flag'] = 1;
				} else {
					$fields[$name]['types'][$type] = true;
				}
			}
		}
		$naturalKey = $this->naturalKeyFields(isset($dataset['unique_key']) ? $dataset['unique_key'] : null, $dataset['dataset_id']);
		$naturalKeyMap = array_flip($naturalKey);
		$statusField = isset($dataset['canonical_status_source']['field']) && is_string($dataset['canonical_status_source']['field'])
			? $dataset['canonical_status_source']['field'] : null;
		$contracts = array();
		foreach ($fields as $name => $observation) {
			$typeNames = array_keys($observation['types']);
			sort($typeNames, SORT_STRING);
			$sourceType = count($typeNames) === 0 ? 'null' : (count($typeNames) === 1 ? $typeNames[0] : 'mixed');
			$identityRole = 'none';
			$identityBlocked = false;
			if (isset($naturalKeyMap[$name])) {
				if ($this->looksLikeHumanLabel($name)) {
					$identityRole = 'label';
					$identityBlocked = true;
				} else {
					$identityRole = count($naturalKey) === 1 ? 'primary_code' : 'natural_key_component';
				}
			} elseif ($statusField === $name) {
				$identityRole = 'provenance';
			} elseif (preg_match('/^(?:parent_code|parent_kode|kode_parent)$/i', $name) === 1) {
				$identityRole = 'parent_reference';
			} elseif ($this->looksLikeHumanLabel($name)) {
				$identityRole = 'label';
			}
			$contracts[] = array(
				'source_field' => $name,
				'field_order' => $observation['field_order'],
				'source_json_type' => $sourceType,
				'cardinality' => isset($observation['types']['array']) ? 'array' : 'scalar',
				'required_flag' => $observation['present_count'] === count($rows) ? 1 : 0,
				'nullable_flag' => $observation['nullable_flag'],
				'identity_role' => $identityRole,
				'identity_blocked' => $identityBlocked,
				'target_domain' => null,
				'target_entity' => null,
				'target_attribute' => null,
				'transform_policy' => 'blocked',
				'contract_status' => 'draft',
				'review_reason' => 'observed_package_field_contract_not_approved',
			);
		}
		usort($contracts, function ($left, $right) { return strcmp($left['source_field'], $right['source_field']); });
		return $contracts;
	}

	private function naturalKeyFields($value, $datasetKey)
	{
		$fields = is_string($value) ? array($value) : $value;
		if (!is_array($fields) || count($fields) === 0) {
			throw new ClinicalPackageException('dataset_natural_key_invalid', 'Dataset natural key is invalid.', array('dataset_key' => $datasetKey));
		}
		$seen = array();
		foreach ($fields as $field) {
			if (!is_string($field) || $field === '' || isset($seen[$field])) {
				throw new ClinicalPackageException('dataset_natural_key_invalid', 'Dataset natural key contains an invalid or duplicate field.', array('dataset_key' => $datasetKey));
			}
			$seen[$field] = true;
		}
		return array_values($fields);
	}

	private function assertSafePackageIdentity(array $manifest)
	{
		if (!isset($manifest['package_id']) || !is_string($manifest['package_id']) || preg_match('/^[a-z][a-z0-9-]{0,127}$/', $manifest['package_id']) !== 1) {
			throw new ClinicalPackageException('package_key_invalid', 'Package key is not a stable safe identifier.');
		}
		if (!isset($manifest['package_version']) || !is_string($manifest['package_version']) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $manifest['package_version']) !== 1) {
			throw new ClinicalPackageException('package_version_invalid', 'Package version is not a stable safe identifier.');
		}
	}

	private function assertRuntimeAndLicensePolicy(array $manifest)
	{
		if (!isset($manifest['production_ready']) || $manifest['production_ready'] !== false) {
			throw new ClinicalPackageException('production_ready_package_rejected', 'This planner accepts only explicitly non-production-ready packages.');
		}
		if (!isset($manifest['runtime_enabled']) || $manifest['runtime_enabled'] !== false) {
			throw new ClinicalPackageException('runtime_enabled_package_rejected', 'Runtime-enabled packages cannot be registered by this planner.');
		}
		foreach ($manifest['datasets'] as $dataset) {
			if (!isset($dataset['runtime_enabled'], $dataset['runtime_selectable']) || $dataset['runtime_enabled'] !== false || $dataset['runtime_selectable'] !== false) {
				throw new ClinicalPackageException('runtime_enabled_dataset_rejected', 'Runtime-enabled or selectable datasets cannot be registered by this planner.', array('dataset_key' => $dataset['dataset_id']));
			}
			if (!isset($dataset['license_status']) || !is_string($dataset['license_status'])) {
				throw new ClinicalPackageException('dataset_license_unavailable', 'Dataset license declaration is unavailable.', array('dataset_key' => $dataset['dataset_id']));
			}
		}
	}

	private function jsonType($value)
	{
		if ($value === null) return 'null';
		if (is_string($value)) return 'string';
		if (is_int($value)) return 'integer';
		if (is_float($value)) return 'number';
		if (is_bool($value)) return 'boolean';
		if (is_object($value)) return 'object';
		if (is_array($value)) return 'array';
		throw new ClinicalPackageException('dataset_value_type_invalid', 'Dataset contains an unsupported JSON value type.');
	}

	private function looksLikeHumanLabel($field)
	{
		return preg_match('/^(?:nama|label|deskripsi|description|display|teks|text)(?:_|$)/i', $field) === 1;
	}

	private function toArray($value)
	{
		if (is_object($value)) {
			$result = array();
			foreach (get_object_vars($value) as $key => $child) {
				$result[$key] = $this->toArray($child);
			}
			return $result;
		}
		if (is_array($value)) {
			$result = array();
			foreach ($value as $child) {
				$result[] = $this->toArray($child);
			}
			return $result;
		}
		return $value;
	}
}
