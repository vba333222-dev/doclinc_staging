<?php

class ClinicalRegistrationWritePrivilegeValidator
{
	private static $tableColumns = array(
		'clinical_master_packages' => array(
			'select' => array('clinical_master_package_id','package_key','package_version','manifest_checksum','package_checksum','package_checksum_profile','manifest_schema_version','source_status','governance_status','production_ready','source_runtime_enabled','license_disposition','declared_dataset_count','observed_dataset_count','declared_record_count','observed_record_count','source_reference'),
			'insert' => array('package_key','package_version','manifest_checksum','package_checksum','package_checksum_profile','manifest_schema_version','source_status','governance_status','production_ready','source_runtime_enabled','license_disposition','declared_dataset_count','observed_dataset_count','declared_record_count','observed_record_count','source_reference','first_seen_at','last_seen_at'),
		),
		'clinical_master_package_capabilities' => array(
			'select' => array('clinical_master_package_capability_id','clinical_master_package_id','capability','decision_status','decision_reason','decision_actor','decided_at'),
			'insert' => array('clinical_master_package_id','capability','decision_status','decision_reason','decision_actor','decided_at'),
		),
		'clinical_master_datasets' => array(
			'select' => array('clinical_master_dataset_id','clinical_master_package_id','dataset_key','source_file','dataset_version','dataset_checksum','domain_key','entity_key','source_governance_status','governance_status','source_runtime_enabled','license_disposition','declared_record_count','observed_record_count','seed_order','natural_key_contract','hierarchy_mode'),
			'insert' => array('clinical_master_package_id','dataset_key','source_file','dataset_version','dataset_checksum','domain_key','entity_key','source_governance_status','governance_status','source_runtime_enabled','license_disposition','declared_record_count','observed_record_count','seed_order','natural_key_contract','hierarchy_mode'),
		),
		'clinical_master_dataset_capabilities' => array(
			'select' => array('clinical_master_dataset_capability_id','clinical_master_dataset_id','capability','decision_status','decision_reason','decision_actor','decided_at'),
			'insert' => array('clinical_master_dataset_id','capability','decision_status','decision_reason','decision_actor','decided_at'),
		),
		'clinical_master_dataset_field_contracts' => array(
			'select' => array('clinical_master_dataset_field_contract_id','clinical_master_dataset_id','source_field','source_json_type','cardinality','required_flag','nullable_flag','identity_role','target_domain','target_entity','target_attribute','transform_policy','contract_status','review_reason','decision_actor','decided_at'),
			'insert' => array('clinical_master_dataset_id','source_field','source_json_type','cardinality','required_flag','nullable_flag','identity_role','target_domain','target_entity','target_attribute','transform_policy','contract_status','review_reason','decision_actor','decided_at'),
		),
		'clinical_master_metadata_registration_events' => array(
			'select' => array(),
			'insert' => array('operation_type','execution_mode','registration_reference','package_key','package_version','package_checksum','package_checksum_profile','package_snapshot_sha256','metadata_entity_count','metadata_rows_planned','metadata_rows_inserted','state_before','state_after','result','idempotent_noop','metadata_transaction_committed','safe_failure_code'),
		),
	);

	public static function assertEvidence(array $evidence)
	{
		$required = array('configured_user','authenticated_user','current_user','current_role','database_name','privileges','active_roles','applicable_roles','grant_statements');
		foreach ($required as $field) {
			if (!array_key_exists($field, $evidence)) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer privilege metadata is incomplete.');
		}
		foreach (array('privileges','active_roles','applicable_roles','grant_statements') as $field) {
			if (!is_array($evidence[$field])) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer privilege metadata is invalid.');
		}
		$configured = (string) $evidence['configured_user'];
		$authenticated = self::accountUser((string) $evidence['authenticated_user']);
		$current = self::accountUser((string) $evidence['current_user']);
		if ($configured === '' || $authenticated === null || $current === null || !hash_equals($configured, $authenticated) || !hash_equals($configured, $current)) {
			throw new ClinicalPackageException('database_proxy_identity_rejected', 'Proxy or unexpected effective database identity is prohibited.');
		}
		if (count($evidence['active_roles']) !== 0 || count($evidence['applicable_roles']) !== 0
			|| ($evidence['current_role'] !== null && strtoupper((string) $evidence['current_role']) !== 'NONE')) {
			throw new ClinicalPackageException('database_active_role_rejected', 'Database roles are prohibited for metadata registration.');
		}
		$database = (string) $evidence['database_name'];
		if ($database === '') throw new ClinicalPackageException('database_identity_mismatch', 'Authenticated database identity is unavailable.');

		$coverage = array();
		foreach (self::$tableColumns as $table => $privileges) {
			$coverage[$table] = array('SELECT' => array('table' => false, 'columns' => array()), 'INSERT' => array('table' => false, 'columns' => array()));
		}
		$seen = array();
		foreach ($evidence['privileges'] as $row) {
			if (!is_array($row)) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer privilege metadata is not interpretable.');
			foreach (array('privilege_surface','privilege_type','is_grantable') as $field) {
				if (!array_key_exists($field, $row)) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer privilege metadata is not interpretable.');
			}
			$surface = strtolower((string) $row['privilege_surface']);
			$privilege = strtoupper(trim((string) $row['privilege_type']));
			$grantable = strtoupper(trim((string) $row['is_grantable']));
			$tableSchema = isset($row['table_schema']) ? (string) $row['table_schema'] : '';
			$tableName = isset($row['table_name']) ? (string) $row['table_name'] : '';
			$columnName = isset($row['column_name']) ? (string) $row['column_name'] : '';
			$key = implode("\0", array($surface,$privilege,$tableSchema,$tableName,$columnName));
			if (isset($seen[$key])) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer privilege metadata contains a duplicate.');
			$seen[$key] = true;
			if ($grantable !== 'NO') throw new ClinicalPackageException('database_writer_privilege_rejected', 'Grantable writer authority is prohibited.');
			if ($surface === 'global') {
				if ($privilege !== 'USAGE' || $tableSchema !== '' || $tableName !== '' || $columnName !== '') {
					throw new ClinicalPackageException('database_writer_privilege_rejected', 'Global writer authority is prohibited.');
				}
				continue;
			}
			if (!in_array($surface, array('table','column'), true) || !in_array($privilege, array('SELECT','INSERT'), true)
				|| !hash_equals($database, $tableSchema) || !isset(self::$tableColumns[$tableName])) {
				throw new ClinicalPackageException('database_writer_privilege_rejected', 'Writer authority exceeds the metadata registration allowlist.');
			}
			if ($surface === 'table') {
				if ($columnName !== '' || $coverage[$tableName][$privilege]['table'] || count($coverage[$tableName][$privilege]['columns']) !== 0) {
					throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer privilege scope is duplicated or malformed.');
				}
				$coverage[$tableName][$privilege]['table'] = true;
			} else {
				if ($columnName === '' || $coverage[$tableName][$privilege]['table'] || isset($coverage[$tableName][$privilege]['columns'][$columnName])) {
					throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer column privilege scope is duplicated or malformed.');
				}
				if (!in_array($columnName, self::$tableColumns[$tableName][strtolower($privilege)], true)) {
					throw new ClinicalPackageException('database_writer_privilege_rejected', 'Writer column authority exceeds the metadata registration allowlist.');
				}
				$coverage[$tableName][$privilege]['columns'][$columnName] = true;
			}
		}
		foreach (self::$tableColumns as $table => $requiredColumns) {
			foreach (array('SELECT','INSERT') as $privilege) {
				$requiredList = $requiredColumns[strtolower($privilege)];
				if (count($requiredList) === 0) continue;
				$actual = $coverage[$table][$privilege];
				if ($actual['table']) continue;
				$actualColumns = array_keys($actual['columns']);
				sort($actualColumns, SORT_STRING);
				$expectedColumns = $requiredList;
				sort($expectedColumns, SORT_STRING);
				if ($actualColumns !== $expectedColumns) {
					throw new ClinicalPackageException(
						$privilege === 'SELECT' ? 'database_select_privilege_missing' : 'database_insert_privilege_missing',
						'Writer database authority is incomplete.'
					);
				}
			}
		}
		$seenGrants = array();
		foreach ($evidence['grant_statements'] as $statement) {
			$statement = (string) $statement;
			if (isset($seenGrants[$statement])) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant statement is duplicated.');
			$seenGrants[$statement] = true;
			self::assertGrantStatement($statement, (string) $evidence['current_user'], $database);
		}
		return true;
	}

	private static function assertGrantStatement($statement, $effectiveAccount, $database)
	{
		if ($statement === '' || trim($statement) !== $statement || preg_match('/[\x00-\x1F\x7F;#]|\/\*|--/', $statement) === 1
			|| preg_match('/\b(GRANT OPTION|ADMIN OPTION|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|EXECUTE|FILE|SUPER|PROCESS|TRIGGER|EVENT|REFERENCES|INDEX|LOCK TABLES|CREATE USER|ROLE ADMIN)\b/i', $statement) === 1) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant statement is not interpretable.');
		}
		if (preg_match('/^GRANT (.+) ON (.+) TO (.+)$/D', $statement, $match) !== 1) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant statement is not interpretable.');
		}
		$recipient = $match[3];
		$recipient = preg_replace("/ IDENTIFIED BY PASSWORD '[*0-9A-F]+'$/D", '', $recipient, 1, $passwordClauseCount);
		if ($recipient === null || ($passwordClauseCount > 0 && $match[2] !== '*.*')) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant statement is not interpretable.');
		}
		$parsedRecipient = self::parseAccount($recipient);
		$parsedEffective = self::parseEffectiveAccount($effectiveAccount);
		if ($parsedRecipient === null || $parsedEffective === null || $parsedRecipient !== $parsedEffective) {
			throw new ClinicalPackageException('database_proxy_identity_rejected', 'Writer grant recipient does not match the effective database identity.');
		}
		if ($match[2] === '*.*') {
			if (strtoupper($match[1]) !== 'USAGE') throw new ClinicalPackageException('database_writer_privilege_rejected', 'Global writer authority is prohibited.');
			return;
		}
		if (preg_match('/^`((?:``|[^`])+)`\.`((?:``|[^`])+)`$/D', $match[2], $scope) !== 1) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant object is not interpretable.');
		}
		$scopeDatabase = str_replace('``', '`', $scope[1]);
		$table = str_replace('``', '`', $scope[2]);
		if (!hash_equals($database, $scopeDatabase) || !isset(self::$tableColumns[$table])) {
			throw new ClinicalPackageException('database_writer_privilege_rejected', 'Writer grant object exceeds the metadata registration allowlist.');
		}
		$parts = self::splitPrivileges($match[1]);
		$seen = array();
		foreach ($parts as $part) {
			if (preg_match('/^(SELECT|INSERT)(?: \((.*)\))?$/D', $part, $privilegeMatch) !== 1) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant privilege is not interpretable.');
			}
			$privilege = $privilegeMatch[1];
			if (isset($seen[$privilege])) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant privilege is duplicated.');
			$seen[$privilege] = true;
			if (isset($privilegeMatch[2]) && $privilegeMatch[2] !== '') {
				foreach (explode(',', $privilegeMatch[2]) as $encodedColumn) {
					$encodedColumn = trim($encodedColumn);
					if (preg_match('/^`((?:``|[^`])+)`$/D', $encodedColumn, $columnMatch) !== 1
						|| !in_array(str_replace('``', '`', $columnMatch[1]), self::$tableColumns[$table][strtolower($privilege)], true)) {
						throw new ClinicalPackageException('database_writer_privilege_rejected', 'Writer grant column exceeds the metadata registration allowlist.');
					}
				}
			}
		}
	}

	private static function splitPrivileges($list)
	{
		$parts = array();
		$start = 0;
		$depth = 0;
		$length = strlen($list);
		for ($index = 0; $index < $length; $index++) {
			if ($list[$index] === '(') $depth++;
			elseif ($list[$index] === ')') {
				$depth--;
				if ($depth < 0) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant privilege is malformed.');
			} elseif ($list[$index] === ',' && $depth === 0) {
				$parts[] = trim(substr($list, $start, $index - $start));
				$start = $index + 1;
			}
		}
		if ($depth !== 0) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant privilege is malformed.');
		$parts[] = trim(substr($list, $start));
		foreach ($parts as $part) if ($part === '') throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Writer grant privilege is malformed.');
		return $parts;
	}

	private static function parseAccount($account)
	{
		if (preg_match('/^([`\\\'])((?:(?!\\1).)+)\\1@([`\\\'])((?:(?!\\3).)+)\\3$/D', $account, $match) !== 1) return null;
		$user = $match[1] === '`' ? str_replace('``', '`', $match[2]) : str_replace("''", "'", $match[2]);
		$host = $match[3] === '`' ? str_replace('``', '`', $match[4]) : str_replace("''", "'", $match[4]);
		return array($user, $host);
	}

	private static function parseEffectiveAccount($account)
	{
		$parts = explode('@', $account, 2);
		return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '' ? array($parts[0], $parts[1]) : null;
	}

	private static function accountUser($account)
	{
		$parts = explode('@', $account, 2);
		return count($parts) === 2 && $parts[0] !== '' ? $parts[0] : null;
	}
}
