<?php

interface ClinicalRegistrationWriteRepository extends ClinicalRegistrationStateRepository
{
	public function databaseName();
	public function acquireLock($lockName, $timeoutSeconds);
	public function releaseLock($lockName);
	public function begin();
	public function commit();
	public function rollback();
	public function insertPackage(array $row, $observedAt);
	public function insertPackageCapability($packageId, array $row);
	public function insertDataset($packageId, array $row);
	public function insertDatasetCapability($datasetId, array $row);
	public function insertFieldContract($datasetId, array $row);
	public function insertAudit(array $row);
}

class MysqliClinicalRegistrationWriteRepository implements ClinicalRegistrationWriteRepository
{
	private $mysqli;
	private $stateRepository;
	private $database;
	private $transactionStarted = false;
	private $heldLock = null;

	public static function fromEnvironment(array $config)
	{
		$names = $config['write_database_environment_variables'];
		$values = array();
		foreach ($names as $key => $name) {
			$value = getenv($name);
			$values[$key] = $value === false ? '' : $value;
		}
		foreach (array('host','database','user','password','allowed_users') as $required) {
			if ($values[$required] === '') throw new ClinicalPackageException('database_configuration_incomplete', 'Metadata writer database configuration is incomplete.');
		}
		$port = $values['port'] === '' ? 3306 : filter_var($values['port'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)));
		if ($port === false) throw new ClinicalPackageException('database_port_invalid', 'Metadata writer database port is invalid.');
		$allowed = array_filter(array_map('trim', explode(',', $values['allowed_users'])), 'strlen');
		$hardRejected = isset($config['hard_rejected_database_users']) && is_array($config['hard_rejected_database_users']) ? $config['hard_rejected_database_users'] : array();
		$hardRejected[] = 'doclinc-staging-user';
		$normalizedRejected = array_map('strtolower', $hardRejected);
		if (!in_array($values['user'], $allowed, true) || in_array(strtolower($values['user']), $normalizedRejected, true)) {
			throw new ClinicalPackageException('database_user_rejected', 'Database identity is not allowed for metadata registration.');
		}
		if (!extension_loaded('mysqli')) throw new ClinicalPackageException('mysqli_extension_unavailable', 'The mysqli extension is required for metadata registration.');
		mysqli_report(MYSQLI_REPORT_OFF);
		$mysqli = @new mysqli($values['host'], $values['user'], $values['password'], $values['database'], (int) $port);
		$values['password'] = str_repeat("\0", strlen($values['password']));
		if ($mysqli->connect_errno) throw new ClinicalPackageException('database_connection_failed', 'Metadata writer database connection failed.');
		if (!$mysqli->set_charset('utf8mb4')) {
			$mysqli->close();
			throw new ClinicalPackageException('database_charset_failed', 'Metadata writer database charset setup failed.');
		}
		$repository = new self($mysqli, $values['database']);
		try {
			$identity = $repository->selectOnePrepared('SELECT DATABASE() AS database_name, USER() AS authenticated_user, CURRENT_USER() AS effective_user, CURRENT_ROLE() AS effective_role', '', array());
			if (!$identity || !hash_equals($values['database'], (string) $identity['database_name'])) {
				throw new ClinicalPackageException('database_identity_mismatch', 'Authenticated database does not match configuration.');
			}
			$repository->assertPrivileges($identity, $values['user']);
		} catch (Throwable $exception) {
			$repository->close();
			throw $exception;
		}
		return $repository;
	}

	public function __construct($mysqli, $database)
	{
		$this->mysqli = $mysqli;
		$this->database = (string) $database;
		$this->stateRepository = new MysqliClinicalRegistrationStateRepository($mysqli);
	}

	public function databaseName()
	{
		return $this->database;
	}

	public function fetchGraph($packageKey, $packageVersion, $packageChecksum)
	{
		return $this->stateRepository->fetchGraph($packageKey, $packageVersion, $packageChecksum);
	}

	public function acquireLock($lockName, $timeoutSeconds)
	{
		if ($this->heldLock !== null) throw new ClinicalPackageException('registration_transaction_failed', 'A metadata registration lock is already held.');
		$row = $this->selectOnePrepared('SELECT GET_LOCK(?,?) AS lock_acquired', 'si', array($lockName, (int) $timeoutSeconds));
		if (!$row || (string) $row['lock_acquired'] !== '1') throw new ClinicalPackageException('registration_lock_timeout', 'Metadata registration lock was not acquired.');
		$this->heldLock = $lockName;
	}

	public function releaseLock($lockName)
	{
		if ($this->heldLock === null) return;
		$row = $this->selectOnePrepared('SELECT RELEASE_LOCK(?) AS lock_released', 's', array($lockName));
		if (!$row || (string) $row['lock_released'] !== '1') throw new ClinicalPackageException('registration_lock_release_failed', 'Metadata registration lock was not released cleanly.');
		$this->heldLock = null;
	}

	public function begin()
	{
		if ($this->transactionStarted) throw new ClinicalPackageException('registration_transaction_failed', 'A metadata registration transaction is already active.');
		if (!$this->mysqli->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || !$this->mysqli->begin_transaction()) {
			throw new ClinicalPackageException('registration_transaction_failed', 'Metadata registration transaction could not be started.');
		}
		$this->transactionStarted = true;
	}

	public function commit()
	{
		if (!$this->transactionStarted || !$this->mysqli->commit()) throw new ClinicalPackageException('registration_transaction_failed', 'Metadata registration transaction could not be committed.');
		$this->transactionStarted = false;
	}

	public function rollback()
	{
		if (!$this->transactionStarted) return;
		if (!$this->mysqli->rollback()) throw new ClinicalPackageException('registration_rollback_failed', 'Metadata registration transaction could not be rolled back.');
		$this->transactionStarted = false;
	}

	public function insertPackage(array $row, $observedAt)
	{
		$sql = 'INSERT INTO clinical_master_packages (package_key,package_version,manifest_checksum,package_checksum,package_checksum_profile,manifest_schema_version,source_status,governance_status,production_ready,source_runtime_enabled,license_disposition,declared_dataset_count,observed_dataset_count,declared_record_count,observed_record_count,source_reference,first_seen_at,last_seen_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
		$values = array($row['package_key'],$row['package_version'],$row['manifest_checksum'],$row['package_checksum'],$row['package_checksum_profile'],$row['manifest_schema_version'],$row['source_status'],$row['governance_status'],$row['production_ready'],$row['source_runtime_enabled'],$row['license_disposition'],$row['declared_dataset_count'],$row['observed_dataset_count'],$row['declared_record_count'],$row['observed_record_count'],$row['source_reference'],$observedAt,$observedAt);
		$this->executeInsert($sql, $values, 'package');
		return (string) $this->mysqli->insert_id;
	}

	public function insertPackageCapability($packageId, array $row)
	{
		$sql = 'INSERT INTO clinical_master_package_capabilities (clinical_master_package_id,capability,decision_status,decision_reason,decision_actor,decided_at) VALUES (?,?,?,?,?,?)';
		$this->executeInsert($sql, array($packageId,$row['capability'],$row['decision_status'],$row['decision_reason'],$row['decision_actor'],$row['decided_at']), 'package_capability');
	}

	public function insertDataset($packageId, array $row)
	{
		$sql = 'INSERT INTO clinical_master_datasets (clinical_master_package_id,dataset_key,source_file,dataset_version,dataset_checksum,domain_key,entity_key,source_governance_status,governance_status,source_runtime_enabled,license_disposition,declared_record_count,observed_record_count,seed_order,natural_key_contract,hierarchy_mode) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
		$values = array($packageId,$row['dataset_key'],$row['source_file'],$row['dataset_version'],$row['dataset_checksum'],$row['domain_key'],$row['entity_key'],$row['source_governance_status'],$row['governance_status'],$row['source_runtime_enabled'],$row['license_disposition'],$row['declared_record_count'],$row['observed_record_count'],$row['seed_order'],$row['natural_key_contract'],$row['hierarchy_mode']);
		$this->executeInsert($sql, $values, 'dataset');
		return (string) $this->mysqli->insert_id;
	}

	public function insertDatasetCapability($datasetId, array $row)
	{
		$sql = 'INSERT INTO clinical_master_dataset_capabilities (clinical_master_dataset_id,capability,decision_status,decision_reason,decision_actor,decided_at) VALUES (?,?,?,?,?,?)';
		$this->executeInsert($sql, array($datasetId,$row['capability'],$row['decision_status'],$row['decision_reason'],$row['decision_actor'],$row['decided_at']), 'dataset_capability');
	}

	public function insertFieldContract($datasetId, array $row)
	{
		$sql = 'INSERT INTO clinical_master_dataset_field_contracts (clinical_master_dataset_id,source_field,source_json_type,cardinality,required_flag,nullable_flag,identity_role,target_domain,target_entity,target_attribute,transform_policy,contract_status,review_reason,decision_actor,decided_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
		$values = array($datasetId,$row['source_field'],$row['source_json_type'],$row['cardinality'],$row['required_flag'],$row['nullable_flag'],$row['identity_role'],$row['target_domain'],$row['target_entity'],$row['target_attribute'],$row['transform_policy'],$row['contract_status'],$row['review_reason'],$row['decision_actor'],$row['decided_at']);
		$this->executeInsert($sql, $values, 'field_contract');
	}

	public function insertAudit(array $row)
	{
		$sql = 'INSERT INTO clinical_master_metadata_registration_events (operation_type,execution_mode,registration_reference,package_key,package_version,package_checksum,package_checksum_profile,package_snapshot_sha256,metadata_entity_count,metadata_rows_planned,metadata_rows_inserted,state_before,state_after,result,idempotent_noop,metadata_transaction_committed,safe_failure_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
		$values = array($row['operation_type'],$row['execution_mode'],$row['registration_reference'],$row['package_key'],$row['package_version'],$row['package_checksum'],$row['package_checksum_profile'],$row['package_snapshot_sha256'],$row['metadata_entity_count'],$row['metadata_rows_planned'],$row['metadata_rows_inserted'],$row['state_before'],$row['state_after'],$row['result'],$row['idempotent_noop'],$row['metadata_transaction_committed'],$row['safe_failure_code']);
		$this->executeInsert($sql, $values, 'audit');
	}

	public function close()
	{
		if ($this->mysqli === null) return;
		if ($this->transactionStarted) {
			@$this->mysqli->rollback();
			$this->transactionStarted = false;
		}
		$this->stateRepository->close();
		$this->stateRepository = null;
		$this->mysqli = null;
		$this->heldLock = null;
	}

	private function assertPrivileges(array $identity, $configuredUser)
	{
		$current = isset($identity['effective_user']) ? (string) $identity['effective_user'] : '';
		$parts = explode('@', $current, 2);
		if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') throw new ClinicalPackageException('database_identity_mismatch', 'Authenticated database identity is unavailable.');
		$grantee = "'" . str_replace("'", "''", $parts[0]) . "'@'" . str_replace("'", "''", $parts[1]) . "'";
		$roleGrantee = $parts[0] . '@' . $parts[1];
		$sql = "SELECT privilege_surface,table_schema,table_name,column_name,privilege_type,is_grantable FROM ("
			. "SELECT 'global' AS privilege_surface,NULL AS table_schema,NULL AS table_name,NULL AS column_name,privilege_type,is_grantable FROM information_schema.USER_PRIVILEGES WHERE grantee=? "
			. "UNION ALL SELECT 'schema',table_schema,NULL,NULL,privilege_type,is_grantable FROM information_schema.SCHEMA_PRIVILEGES WHERE grantee=? "
			. "UNION ALL SELECT 'table',table_schema,table_name,NULL,privilege_type,is_grantable FROM information_schema.TABLE_PRIVILEGES WHERE grantee=? "
			. "UNION ALL SELECT 'column',table_schema,table_name,column_name,privilege_type,is_grantable FROM information_schema.COLUMN_PRIVILEGES WHERE grantee=?) p "
			. "ORDER BY privilege_surface,table_schema,table_name,column_name,privilege_type";
		$privileges = $this->selectAllPrepared($sql, 'ssss', array($grantee,$grantee,$grantee,$grantee));
		$activeRoles = $this->selectAllPrepared('SELECT role_name FROM information_schema.ENABLED_ROLES WHERE role_name IS NOT NULL ORDER BY role_name', '', array());
		$applicableRoles = $this->selectAllPrepared('SELECT role_name,is_grantable,is_default FROM information_schema.APPLICABLE_ROLES WHERE grantee=? ORDER BY role_name', 's', array($roleGrantee));
		$grants = $this->selectGrantStatements();
		ClinicalRegistrationWritePrivilegeValidator::assertEvidence(array(
			'configured_user' => $configuredUser,
			'authenticated_user' => isset($identity['authenticated_user']) ? (string) $identity['authenticated_user'] : '',
			'current_user' => $current,
			'current_role' => isset($identity['effective_role']) ? $identity['effective_role'] : null,
			'database_name' => $this->database,
			'privileges' => $privileges,
			'active_roles' => $activeRoles,
			'applicable_roles' => $applicableRoles,
			'grant_statements' => $grants,
		));
	}

	private function executeInsert($sql, array $values, $entity)
	{
		$statement = $this->prepareAndBind($sql, $this->types($values), $values, 'metadata_insert_failed');
		$executed = $statement->execute();
		$affected = $statement->affected_rows;
		$statement->close();
		if (!$executed || $affected !== 1) throw new ClinicalPackageException('metadata_insert_failed', 'Metadata registration insert failed.', array('entity_type' => $entity));
	}

	private function selectOnePrepared($sql, $types, array $values)
	{
		$rows = $this->selectAllPrepared($sql, $types, $values);
		return count($rows) === 0 ? null : $rows[0];
	}

	private function selectAllPrepared($sql, $types, array $values)
	{
		$statement = $this->prepareAndBind($sql, $types, $values, 'database_privilege_metadata_unavailable');
		if (!$statement->execute()) {
			$statement->close();
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Metadata writer database query failed.');
		}
		$result = $statement->get_result();
		if ($result === false) {
			$statement->close();
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Metadata writer database result is unavailable.');
		}
		$rows = array();
		while ($row = $result->fetch_assoc()) $rows[] = $row;
		$result->free();
		$statement->close();
		return $rows;
	}

	private function selectGrantStatements()
	{
		$result = $this->mysqli->query('SHOW GRANTS FOR CURRENT_USER');
		if ($result === false) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Metadata writer grants could not be inspected.');
		$values = array();
		while ($row = $result->fetch_row()) {
			if (!isset($row[0]) || !is_string($row[0])) {
				$result->free();
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Metadata writer grants are not interpretable.');
			}
			$values[] = $row[0];
		}
		$result->free();
		return $values;
	}

	private function prepareAndBind($sql, $types, array $values, $errorCode)
	{
		$statement = $this->mysqli->prepare($sql);
		if ($statement === false) throw new ClinicalPackageException($errorCode, 'Metadata writer prepared statement failed.');
		if ($types !== '') {
			$parameters = array($types);
			foreach ($values as $index => $value) {
				$values[$index] = $value;
				$parameters[] = &$values[$index];
			}
			if (!call_user_func_array(array($statement, 'bind_param'), $parameters)) {
				$statement->close();
				throw new ClinicalPackageException($errorCode, 'Metadata writer parameter binding failed.');
			}
		}
		return $statement;
	}

	private function types(array $values)
	{
		$result = '';
		foreach ($values as $value) $result .= is_int($value) ? 'i' : 's';
		return $result;
	}
}
