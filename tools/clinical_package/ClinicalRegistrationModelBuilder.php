<?php

class ClinicalRegistrationModelBuilder
{
	private static $capabilities = array(
		'audit_import',
		'canonical_storage',
		'clinical_selection',
		'decision_support',
		'prescribing',
		'production_deployment',
		'redistribution',
		'runtime_reference',
	);

	private $checksumBuilder;
	private $canonicalizer;
	private $contractValidator;

	public function __construct(ClinicalPackageChecksumBuilder $checksumBuilder, JcsCanonicalizer $canonicalizer, ClinicalRegistrationContractValidator $contractValidator)
	{
		$this->checksumBuilder = $checksumBuilder;
		$this->canonicalizer = $canonicalizer;
		$this->contractValidator = $contractValidator;
	}

	public function build(array $evidence)
	{
		$manifest = $evidence['manifest'];
		$license = $this->packageLicenseDisposition($manifest['datasets']);
		$packageCapabilities = $this->capabilityRows(null);
		$datasets = array();
		$fieldContractCount = 0;
		foreach ($evidence['datasets'] as $observed) {
			$source = $observed['manifest'];
			$naturalKey = is_string($source['unique_key']) ? array($source['unique_key']) : array_values($source['unique_key']);
			$naturalKeyContract = $this->canonicalizer->canonicalize($naturalKey);
			$datasetCapabilities = $this->capabilityRows($source['dataset_id']);
			$fieldRows = array();
			foreach ($observed['field_contracts'] as $contract) {
				$fieldRows[] = array(
					'natural_key' => array('dataset_key' => $source['dataset_id'], 'source_field' => $contract['source_field']),
					'persisted' => array(
						'source_field' => $contract['source_field'],
						'source_json_type' => $contract['source_json_type'],
						'cardinality' => $contract['cardinality'],
						'required_flag' => $contract['required_flag'],
						'nullable_flag' => $contract['nullable_flag'],
						'identity_role' => $contract['identity_role'],
						'target_domain' => $contract['target_domain'],
						'target_entity' => $contract['target_entity'],
						'target_attribute' => $contract['target_attribute'],
						'transform_policy' => $contract['transform_policy'],
						'contract_status' => $contract['contract_status'],
						'review_reason' => $contract['review_reason'],
						'decision_actor' => null,
						'decided_at' => null,
					),
					'observation' => array(
						'field_order' => $contract['field_order'],
						'identity_blocked' => $contract['identity_blocked'],
					),
				);
			}
			$fieldContractCount += count($fieldRows);
			$datasets[] = array(
				'natural_key' => array('package_key' => $manifest['package_id'], 'package_version' => $manifest['package_version'], 'dataset_key' => $source['dataset_id']),
				'persisted' => array(
					'dataset_key' => $source['dataset_id'],
					'source_file' => $source['path'],
					'dataset_version' => $source['version'],
					'dataset_checksum' => $observed['observed_sha256'],
					'domain_key' => $source['domain'],
					'entity_key' => $source['entity'],
					'source_governance_status' => $source['source_status'],
					'governance_status' => 'unreviewed',
					'source_runtime_enabled' => 0,
					'license_disposition' => $source['license_status'],
					'declared_record_count' => $source['record_count'],
					'observed_record_count' => $observed['observed_record_count'],
					'seed_order' => $source['seed_order'],
					'natural_key_contract' => $naturalKeyContract,
					'hierarchy_mode' => 'none',
				),
				'source_evidence' => $this->normalizedDatasetSourceEvidence($source),
				'capabilities' => $datasetCapabilities,
				'field_contracts' => $fieldRows,
			);
		}
		usort($datasets, function ($left, $right) { return strcmp($left['persisted']['dataset_key'], $right['persisted']['dataset_key']); });

		$packagePersisted = array(
			'package_key' => $manifest['package_id'],
			'package_version' => $manifest['package_version'],
			'manifest_checksum' => $evidence['manifest_checksum'],
			'manifest_schema_version' => $manifest['manifest_schema_version'],
			'source_status' => $manifest['package_status'],
			'governance_status' => 'unreviewed',
			'production_ready' => 0,
			'source_runtime_enabled' => 0,
			'license_disposition' => $license,
			'declared_dataset_count' => $manifest['package_summary']['dataset_count'],
			'observed_dataset_count' => $evidence['observed_dataset_count'],
			'declared_record_count' => $manifest['package_summary']['record_count'],
			'observed_record_count' => $evidence['observed_record_count'],
			'source_reference' => '00_manifest.json',
		);
		$model = array(
			'package' => array(
				'natural_key' => array('package_key' => $manifest['package_id'], 'package_version' => $manifest['package_version']),
				'persisted' => $packagePersisted,
			),
			'package_capabilities' => $packageCapabilities,
			'datasets' => $datasets,
			'audit_plan' => array(
				'import_batch_row_plan_count' => 0,
				'import_item_row_plan_count' => 0,
				'canonical_record_plan_count' => 0,
				'record_import_eligible' => false,
				'blocking_reasons' => array('capabilities_not_approved', 'governance_not_approved', 'license_not_declared'),
			),
			'counts' => array(
				'package_rows' => 1,
				'dataset_rows' => count($datasets),
				'package_capabilities' => count($packageCapabilities),
				'dataset_capabilities' => count($datasets) * count(self::$capabilities),
				'field_contracts' => $fieldContractCount,
			),
		);
		$this->contractValidator->validatePreChecksum($model, ClinicalPackageChecksumBuilder::PROFILE);
		$projection = $this->projection($model, $manifest);
		$model['package_checksum_profile'] = ClinicalPackageChecksumBuilder::PROFILE;
		$model['package_checksum'] = $this->checksumBuilder->checksum($projection);
		$model['package']['persisted']['package_checksum'] = $model['package_checksum'];
		$model['package']['persisted']['package_checksum_profile'] = ClinicalPackageChecksumBuilder::PROFILE;
		$model['package_file_count'] = $evidence['package_file_count'];
		$model['package_snapshot_sha256'] = $evidence['package_snapshot_sha256'];
		$this->contractValidator->validate($model);
		$this->assertPlanIntegrity($model);
		return $model;
	}

	public function assertPlanIntegrity(array $model)
	{
		$this->assertUniqueRows($model['package_capabilities'], function ($row) { return $row['persisted']['capability']; }, 'duplicate_package_capability_plan');
		$datasets = array();
		$paths = array();
		foreach ($model['datasets'] as $dataset) {
			$key = $dataset['persisted']['dataset_key'];
			$path = $dataset['persisted']['source_file'];
			if (isset($datasets[$key])) throw new ClinicalPackageException('duplicate_dataset_plan', 'Registration plan contains duplicate dataset keys.');
			if (isset($paths[$path])) throw new ClinicalPackageException('duplicate_dataset_path_plan', 'Registration plan contains duplicate dataset paths.');
			$datasets[$key] = true;
			$paths[$path] = true;
			$this->assertUniqueRows($dataset['capabilities'], function ($row) { return $row['persisted']['capability']; }, 'duplicate_dataset_capability_plan');
			$this->assertUniqueRows($dataset['field_contracts'], function ($row) { return $row['persisted']['source_field']; }, 'duplicate_field_contract_plan');
			foreach ($dataset['capabilities'] as $row) {
				if ($row['persisted']['decision_status'] !== 'blocked') throw new ClinicalPackageException('permissive_capability_plan_rejected', 'Initial capabilities must be blocked.');
			}
		}
		foreach ($model['package_capabilities'] as $row) {
			if ($row['persisted']['decision_status'] !== 'blocked') throw new ClinicalPackageException('permissive_capability_plan_rejected', 'Initial capabilities must be blocked.');
		}
		if ($model['audit_plan']['canonical_record_plan_count'] !== 0 || $model['audit_plan']['import_item_row_plan_count'] !== 0) {
			throw new ClinicalPackageException('record_import_plan_rejected', 'Metadata planning may not include record import.');
		}
	}

	private function projection(array $model, array $manifest)
	{
		$projectionDatasets = array();
		foreach ($model['datasets'] as $dataset) {
			$fields = array();
			foreach ($dataset['field_contracts'] as $row) {
				$fields[] = array('persisted' => $row['persisted'], 'observation' => $row['observation']);
			}
			$fields = $this->sortedSemanticList($fields, 'duplicate_semantic_field_contract');
			$projectionDatasets[] = array(
				'registration' => $dataset['persisted'],
				'source_evidence' => $dataset['source_evidence'],
				'capabilities' => $this->sortedSemanticList($this->persistedRows($dataset['capabilities']), 'duplicate_semantic_dataset_capability'),
				'field_contracts' => $fields,
			);
		}
		return array(
			'profile' => ClinicalPackageChecksumBuilder::PROFILE,
			'package' => $this->semanticPackageProjection($model['package']['persisted'], $manifest),
			'package_capabilities' => $this->sortedSemanticList($this->persistedRows($model['package_capabilities']), 'duplicate_semantic_package_capability'),
			'datasets' => $projectionDatasets,
			'governance' => array(
				'governance_notices' => $this->sortedSemanticList($manifest['governance_notices'], 'duplicate_semantic_governance_notice'),
				'review_governance' => $this->normalizeGovernance($manifest['review_governance']),
				'dependency_policy' => $manifest['dependency_policy'],
				'runtime_policies' => $manifest['runtime_policies'],
				'submanifests' => $this->sortedObjectList($manifest['submanifests'], 'submanifest_id'),
			),
		);
	}

	private function semanticPackageProjection(array $persisted, array $manifest)
	{
		$copy = $persisted;
		unset($copy['manifest_checksum']);
		return array(
			'registration' => $copy,
			'manifest_schema_reference' => $manifest['$schema'],
			'contains_patient_data' => $manifest['contains_patient_data'],
			'contains_personal_data' => $manifest['contains_personal_data'],
			'contains_credentials' => $manifest['contains_credentials'],
			'package_summary' => $manifest['package_summary'],
		);
	}

	private function normalizedDatasetSourceEvidence(array $source)
	{
		$copy = $source;
		if (isset($copy['dependencies']) && is_array($copy['dependencies'])) {
			$copy['dependencies'] = $this->sortedSemanticList($copy['dependencies'], 'duplicate_semantic_dependency');
		}
		return $copy;
	}

	private function normalizeGovernance(array $governance)
	{
		if (isset($governance['canonical_statuses'])) $governance['canonical_statuses'] = $this->sortedSemanticList($governance['canonical_statuses'], 'duplicate_semantic_canonical_status');
		if (isset($governance['principles'])) $governance['principles'] = $this->sortedSemanticList($governance['principles'], 'duplicate_semantic_review_principle');
		if (isset($governance['source_status_mappings'])) $governance['source_status_mappings'] = $this->sortedObjectList($governance['source_status_mappings'], 'source_value');
		return $governance;
	}

	private function sortedSemanticList(array $items, $duplicateCode)
	{
		$decorated = array();
		foreach ($items as $index => $item) {
			$decorated[] = array('canonical' => $this->canonicalizer->canonicalize($item), 'index' => $index, 'value' => $item);
		}
		usort($decorated, function ($left, $right) {
			$comparison = strcmp($left['canonical'], $right['canonical']);
			return $comparison !== 0 ? $comparison : ($left['index'] <=> $right['index']);
		});
		$result = array();
		$previous = null;
		foreach ($decorated as $position => $entry) {
			if ($position > 0 && hash_equals($previous, $entry['canonical'])) {
				throw new ClinicalPackageException($duplicateCode, 'Semantic package projection contains a duplicate unordered entry.');
			}
			$previous = $entry['canonical'];
			$result[] = $entry['value'];
		}
		return $result;
	}

	private function sortedObjectList(array $items, $primaryKey)
	{
		$seen = array();
		foreach ($items as $item) {
			$key = isset($item[$primaryKey]) ? (string) $item[$primaryKey] : '';
			if (isset($seen[$key])) throw new ClinicalPackageException('duplicate_semantic_object_key', 'Semantic package projection contains a duplicate object identity.');
			$seen[$key] = true;
		}
		return $this->sortedSemanticList($items, 'duplicate_semantic_object');
	}

	private function capabilityRows($datasetKey)
	{
		$rows = array();
		foreach (self::$capabilities as $capability) {
			$natural = $datasetKey === null ? array('capability' => $capability) : array('dataset_key' => $datasetKey, 'capability' => $capability);
			$rows[] = array(
				'natural_key' => $natural,
				'persisted' => array(
					'capability' => $capability,
					'decision_status' => 'blocked',
					'decision_reason' => 'initial_registration_not_approved',
					'decision_actor' => null,
					'decided_at' => null,
				),
			);
		}
		return $rows;
	}

	private function persistedRows(array $rows)
	{
		$result = array();
		foreach ($rows as $row) $result[] = $row['persisted'];
		return $result;
	}

	private function packageLicenseDisposition(array $datasets)
	{
		$values = array();
		foreach ($datasets as $dataset) $values[$dataset['license_status']] = true;
		if (count($values) !== 1) {
			throw new ClinicalPackageException('package_license_aggregate_unavailable', 'Package license disposition cannot be represented without inference.');
		}
		return (string) key($values);
	}

	private function assertUniqueRows(array $rows, $keyBuilder, $errorCode)
	{
		$seen = array();
		foreach ($rows as $row) {
			$key = call_user_func($keyBuilder, $row);
			if (isset($seen[$key])) throw new ClinicalPackageException($errorCode, 'Registration plan contains a duplicate natural key.');
			$seen[$key] = true;
		}
	}
}
