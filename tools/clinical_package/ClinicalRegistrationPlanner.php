<?php

class ClinicalRegistrationPlanner
{
	public function offline(array $model)
	{
		return $this->result($model, 'NOT_REQUESTED', 'not_compared', 0, 0, 0, false);
	}

	public function compare(array $model, ClinicalRegistrationStateRepository $repository)
	{
		$graph = $repository->fetchGraph(
			$model['package']['persisted']['package_key'],
			$model['package']['persisted']['package_version'],
			$model['package_checksum']
		);
		$total = $this->entityCount($model);
		$conflicts = 0;
		$new = 0;
		$exact = 0;
		$identityRows = $graph['package_identity_rows'];
		$checksumRows = $graph['package_checksum_rows'];

		foreach ($checksumRows as $row) {
			if ((string) $row['package_key'] !== $model['package']['persisted']['package_key'] || (string) $row['package_version'] !== $model['package']['persisted']['package_version']) {
				$conflicts++;
			}
		}
		if (count($identityRows) === 0) {
			if ($conflicts === 0 && $this->graphChildCount($graph) === 0) {
				$new = $total;
				return $this->result($model, 'COMPLETE', 'new', $new, 0, 0, false);
			}
			return $this->result($model, 'COMPLETE', 'conflict', 0, 0, max(1, $conflicts), true);
		}
		if (count($identityRows) !== 1) {
			return $this->result($model, 'COMPLETE', 'conflict', 0, 0, max(1, count($identityRows)), true);
		}

		$packageRow = $identityRows[0];
		if (count($checksumRows) !== 1) {
			$conflicts += max(1, count($checksumRows));
		} else {
			$checksumRow = $checksumRows[0];
			if ((string) $checksumRow['package_key'] !== $model['package']['persisted']['package_key']
				|| (string) $checksumRow['package_version'] !== $model['package']['persisted']['package_version']
				|| !isset($checksumRow['clinical_master_package_id'], $packageRow['clinical_master_package_id'])
				|| (string) $checksumRow['clinical_master_package_id'] !== (string) $packageRow['clinical_master_package_id']) {
				$conflicts++;
			}
		}
		if (!$this->packageMatches($model['package']['persisted'], $packageRow)) $conflicts++;
		else $exact++;

		$datasetPlans = array();
		foreach ($model['datasets'] as $dataset) $datasetPlans[$dataset['persisted']['dataset_key']] = $dataset;
		$datasetRows = $this->groupRows($graph['datasets'], function ($row) { return (string) $row['dataset_key']; });
		foreach ($datasetPlans as $key => $plan) {
			if (!isset($datasetRows[$key])) $new++;
			elseif (count($datasetRows[$key]) !== 1 || !$this->datasetMatches($plan['persisted'], $datasetRows[$key][0])) $conflicts++;
			else $exact++;
		}
		foreach ($datasetRows as $key => $rows) if (!isset($datasetPlans[$key])) $conflicts += count($rows);

		$this->compareCapabilityRows($model['package_capabilities'], $graph['package_capabilities'], null, $new, $exact, $conflicts);
		$plannedDatasetCaps = array();
		$plannedFields = array();
		foreach ($model['datasets'] as $dataset) {
			foreach ($dataset['capabilities'] as $row) {
				$plannedDatasetCaps[] = array('dataset_key' => $dataset['persisted']['dataset_key']) + $row;
			}
			foreach ($dataset['field_contracts'] as $row) {
				$plannedFields[] = array('dataset_key' => $dataset['persisted']['dataset_key']) + $row;
			}
		}
		$this->compareCapabilityRows($plannedDatasetCaps, $graph['dataset_capabilities'], 'dataset_key', $new, $exact, $conflicts);
		$this->compareFieldRows($plannedFields, $graph['field_contracts'], $new, $exact, $conflicts);

		if ($new > 0 && $exact > 0) $conflicts += $new;
		$state = $conflicts > 0 ? 'conflict' : ($exact === $total ? 'exact_match' : 'new');
		return $this->result($model, 'COMPLETE', $state, $new, $exact, $conflicts, $conflicts > 0);
	}

	private function packageMatches(array $planned, array $actual)
	{
		$types = $this->comparisonFieldTypes();
		return $this->fieldsMatch($planned, $actual, $types['package']);
	}

	private function datasetMatches(array $planned, array $actual)
	{
		$types = $this->comparisonFieldTypes();
		return $this->fieldsMatch($planned, $actual, $types['dataset']);
	}

	private function compareCapabilityRows(array $plans, array $actualRows, $datasetKeyField, &$new, &$exact, &$conflicts)
	{
		$planned = array();
		foreach ($plans as $plan) {
			$datasetKey = $datasetKeyField === null ? '' : (string) $plan[$datasetKeyField];
			$key = $datasetKey . "\0" . $plan['persisted']['capability'];
			if (isset($planned[$key])) { $conflicts++; continue; }
			$planned[$key] = $plan;
		}
		$actual = $this->groupRows($actualRows, function ($row) use ($datasetKeyField) {
			return ($datasetKeyField === null ? '' : (string) $row[$datasetKeyField]) . "\0" . (string) $row['capability'];
		});
		foreach ($planned as $key => $plan) {
			if (!isset($actual[$key])) { $new++; continue; }
			$types = $this->comparisonFieldTypes();
			$entity = $datasetKeyField === null ? 'package_capability' : 'dataset_capability';
			if (count($actual[$key]) !== 1 || !$this->fieldsMatch($plan['persisted'], $actual[$key][0], $types[$entity])) $conflicts++;
			else $exact++;
		}
		foreach ($actual as $key => $rows) if (!isset($planned[$key])) $conflicts += count($rows);
	}

	private function compareFieldRows(array $plans, array $actualRows, &$new, &$exact, &$conflicts)
	{
		$planned = array();
		foreach ($plans as $plan) {
			$key = $plan['dataset_key'] . "\0" . $plan['persisted']['source_field'];
			if (isset($planned[$key])) { $conflicts++; continue; }
			$planned[$key] = $plan;
		}
		$actual = $this->groupRows($actualRows, function ($row) { return (string) $row['dataset_key'] . "\0" . (string) $row['source_field']; });
		$types = $this->comparisonFieldTypes();
		$fields = $types['field_contract'];
		foreach ($planned as $key => $plan) {
			if (!isset($actual[$key])) { $new++; continue; }
			$row = $actual[$key][0];
			if (count($actual[$key]) !== 1 || !$this->fieldsMatch($plan['persisted'], $row, $fields)) $conflicts++;
			else $exact++;
		}
		foreach ($actual as $key => $rows) if (!isset($planned[$key])) $conflicts += count($rows);
	}

	private function fieldsMatch(array $planned, array $actual, array $fieldTypes)
	{
		if (array_keys($planned) !== array_keys($fieldTypes)) return false;
		foreach ($fieldTypes as $field => $type) {
			if (!array_key_exists($field, $actual)) return false;
			$left = $this->normalizePersistedValue($planned[$field], $type);
			$right = $this->normalizePersistedValue($actual[$field], $type);
			if ($left === false || $right === false || $left !== $right) return false;
		}
		return true;
	}

	private function normalizePersistedValue($value, $type)
	{
		if ($type === 'nullable_string') {
			if ($value === null) return array('null', null);
			$type = 'string';
		}
		if ($type === 'string') {
			return is_string($value) ? array('string', $value) : false;
		}
		if ($type === 'integer') {
			if (is_int($value) && $value >= 0) return array('integer', (string) $value);
			if (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/D', $value) === 1) return array('integer', $value);
			return false;
		}
		return false;
	}

	private function comparisonFieldTypes()
	{
		$capability = array(
			'capability' => 'string',
			'decision_status' => 'string',
			'decision_reason' => 'nullable_string',
			'decision_actor' => 'nullable_string',
			'decided_at' => 'nullable_string',
		);
		return array(
			'package' => array(
				'package_key' => 'string',
				'package_version' => 'string',
				'manifest_checksum' => 'string',
				'manifest_schema_version' => 'nullable_string',
				'source_status' => 'string',
				'governance_status' => 'string',
				'production_ready' => 'integer',
				'source_runtime_enabled' => 'integer',
				'license_disposition' => 'string',
				'declared_dataset_count' => 'integer',
				'observed_dataset_count' => 'integer',
				'declared_record_count' => 'integer',
				'observed_record_count' => 'integer',
				'source_reference' => 'nullable_string',
				'package_checksum' => 'string',
				'package_checksum_profile' => 'string',
			),
			'dataset' => array(
				'dataset_key' => 'string',
				'source_file' => 'string',
				'dataset_version' => 'string',
				'dataset_checksum' => 'string',
				'domain_key' => 'string',
				'entity_key' => 'nullable_string',
				'source_governance_status' => 'string',
				'governance_status' => 'string',
				'source_runtime_enabled' => 'integer',
				'license_disposition' => 'string',
				'declared_record_count' => 'integer',
				'observed_record_count' => 'integer',
				'seed_order' => 'integer',
				'natural_key_contract' => 'nullable_string',
				'hierarchy_mode' => 'string',
			),
			'package_capability' => $capability,
			'dataset_capability' => $capability,
			'field_contract' => array(
				'source_field' => 'string',
				'source_json_type' => 'string',
				'cardinality' => 'string',
				'required_flag' => 'integer',
				'nullable_flag' => 'integer',
				'identity_role' => 'string',
				'target_domain' => 'nullable_string',
				'target_entity' => 'nullable_string',
				'target_attribute' => 'nullable_string',
				'transform_policy' => 'string',
				'contract_status' => 'string',
				'review_reason' => 'nullable_string',
				'decision_actor' => 'nullable_string',
				'decided_at' => 'nullable_string',
			),
		);
	}

	private function groupRows(array $rows, $keyBuilder)
	{
		$result = array();
		foreach ($rows as $row) {
			$key = call_user_func($keyBuilder, $row);
			if (!isset($result[$key])) $result[$key] = array();
			$result[$key][] = $row;
		}
		return $result;
	}

	private function graphChildCount(array $graph)
	{
		return count($graph['datasets']) + count($graph['package_capabilities']) + count($graph['dataset_capabilities']) + count($graph['field_contracts']);
	}

	private function entityCount(array $model)
	{
		return 1 + $model['counts']['dataset_rows'] + $model['counts']['package_capabilities'] + $model['counts']['dataset_capabilities'] + $model['counts']['field_contracts'];
	}

	private function result(array $model, $comparison, $state, $new, $exact, $conflicts, $failed)
	{
		return array(
			'database_comparison' => $comparison,
			'package_state' => $state,
			'new_entity_count' => $new,
			'exact_match_entity_count' => $exact,
			'conflict_entity_count' => $conflicts,
			'failed' => $failed,
		);
	}
}
