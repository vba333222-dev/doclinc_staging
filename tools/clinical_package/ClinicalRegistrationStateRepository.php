<?php

interface ClinicalRegistrationStateRepository
{
	public function fetchGraph($packageKey, $packageVersion, $packageChecksum);
	public function close();
}

class MysqliClinicalRegistrationStateRepository implements ClinicalRegistrationStateRepository
{
	private $mysqli;
	private $readOnlyTransactionStarted = false;

	public static function requested(array $environmentNames)
	{
		foreach ($environmentNames as $name) {
			$value = getenv($name);
			if ($value !== false && $value !== '') return true;
		}
		return false;
	}

	public static function fromEnvironment(array $config)
	{
		$names = $config['database_environment_variables'];
		$values = array();
		foreach ($names as $key => $name) {
			$value = getenv($name);
			$values[$key] = $value === false ? '' : $value;
		}
		foreach (array('host', 'database', 'user', 'password', 'allowed_users') as $required) {
			if ($values[$required] === '') {
				throw new ClinicalPackageException('database_configuration_incomplete', 'Read-only database comparison configuration is incomplete.');
			}
		}
		$port = $values['port'] === '' ? 3306 : filter_var($values['port'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 65535)));
		if ($port === false) throw new ClinicalPackageException('database_port_invalid', 'Read-only database port is invalid.');
		$allowed = array_filter(array_map('trim', explode(',', $values['allowed_users'])), 'strlen');
		if (!in_array($values['user'], $allowed, true) || in_array(strtolower($values['user']), array('doclinc-staging-user'), true)) {
			throw new ClinicalPackageException('database_user_rejected', 'Database identity is not allowed for clinical package comparison.');
		}
		if (!extension_loaded('mysqli')) throw new ClinicalPackageException('mysqli_extension_unavailable', 'The mysqli extension is required for database comparison.');
		mysqli_report(MYSQLI_REPORT_OFF);
		$mysqli = @new mysqli($values['host'], $values['user'], $values['password'], $values['database'], (int) $port);
		$values['password'] = str_repeat("\0", strlen($values['password']));
		if ($mysqli->connect_errno) throw new ClinicalPackageException('database_connection_failed', 'Read-only clinical package database connection failed.');
		if (!$mysqli->set_charset('utf8mb4')) {
			$mysqli->close();
			throw new ClinicalPackageException('database_charset_failed', 'Read-only database charset setup failed.');
		}
		$repository = new self($mysqli);
		$identity = $repository->selectOne('SELECT DATABASE() AS database_name, USER() AS authenticated_user, CURRENT_USER() AS effective_user, CURRENT_ROLE() AS effective_role');
		if (!$identity || !hash_equals($values['database'], (string) $identity['database_name'])) {
			$mysqli->close();
			throw new ClinicalPackageException('database_identity_mismatch', 'Authenticated database does not match configuration.');
		}
		$repository->assertSelectOnlyPrivileges($identity, $values['user']);
		if (!$mysqli->query('START TRANSACTION READ ONLY')) {
			$mysqli->close();
			throw new ClinicalPackageException('database_read_only_transaction_failed', 'Read-only database transaction could not be established.');
		}
		$repository->readOnlyTransactionStarted = true;
		return $repository;
	}

	public function __construct($mysqli)
	{
		$this->mysqli = $mysqli;
	}

	public function fetchGraph($packageKey, $packageVersion, $packageChecksum)
	{
		$identityRows = $this->selectAllPrepared(
			'SELECT clinical_master_package_id,package_key,package_version,manifest_checksum,package_checksum,package_checksum_profile,manifest_schema_version,source_status,governance_status,production_ready,source_runtime_enabled,license_disposition,declared_dataset_count,observed_dataset_count,declared_record_count,observed_record_count,source_reference FROM clinical_master_packages WHERE package_key=? AND package_version=? ORDER BY clinical_master_package_id',
			'ss', array($packageKey, $packageVersion)
		);
		$checksumRows = $this->selectAllPrepared(
			'SELECT clinical_master_package_id,package_key,package_version FROM clinical_master_packages WHERE package_checksum=? ORDER BY clinical_master_package_id',
			's', array($packageChecksum)
		);
		$graph = array(
			'package_identity_rows' => $identityRows,
			'package_checksum_rows' => $checksumRows,
			'datasets' => array(),
			'package_capabilities' => array(),
			'dataset_capabilities' => array(),
			'field_contracts' => array(),
		);
		if (count($identityRows) !== 1) return $graph;
		$packageId = (string) $identityRows[0]['clinical_master_package_id'];
		$graph['datasets'] = $this->selectAllPrepared(
			'SELECT clinical_master_dataset_id,dataset_key,source_file,dataset_version,dataset_checksum,domain_key,entity_key,source_governance_status,governance_status,source_runtime_enabled,license_disposition,declared_record_count,observed_record_count,seed_order,natural_key_contract,hierarchy_mode FROM clinical_master_datasets WHERE clinical_master_package_id=? ORDER BY BINARY dataset_key,clinical_master_dataset_id',
			's', array($packageId)
		);
		$graph['package_capabilities'] = $this->selectAllPrepared(
			'SELECT capability,decision_status,decision_reason,decision_actor,decided_at FROM clinical_master_package_capabilities WHERE clinical_master_package_id=? ORDER BY BINARY capability,clinical_master_package_capability_id',
			's', array($packageId)
		);
		$graph['dataset_capabilities'] = $this->selectAllPrepared(
			'SELECT d.dataset_key,c.capability,c.decision_status,c.decision_reason,c.decision_actor,c.decided_at FROM clinical_master_dataset_capabilities c INNER JOIN clinical_master_datasets d ON d.clinical_master_dataset_id=c.clinical_master_dataset_id WHERE d.clinical_master_package_id=? ORDER BY BINARY d.dataset_key,BINARY c.capability,c.clinical_master_dataset_capability_id',
			's', array($packageId)
		);
		$graph['field_contracts'] = $this->selectAllPrepared(
			'SELECT d.dataset_key,f.source_field,f.source_json_type,f.cardinality,f.required_flag,f.nullable_flag,f.identity_role,f.target_domain,f.target_entity,f.target_attribute,f.transform_policy,f.contract_status,f.review_reason,f.decision_actor,f.decided_at FROM clinical_master_dataset_field_contracts f INNER JOIN clinical_master_datasets d ON d.clinical_master_dataset_id=f.clinical_master_dataset_id WHERE d.clinical_master_package_id=? ORDER BY BINARY d.dataset_key,BINARY f.source_field,f.clinical_master_dataset_field_contract_id',
			's', array($packageId)
		);
		return $graph;
	}

	public function close()
	{
		if ($this->mysqli !== null) {
			if ($this->readOnlyTransactionStarted) {
				@$this->mysqli->rollback();
				$this->readOnlyTransactionStarted = false;
			}
			$this->mysqli->close();
			$this->mysqli = null;
		}
	}

	private function selectOne($sql)
	{
		$result = $this->mysqli->query($sql);
		if ($result === false) throw new ClinicalPackageException('database_select_failed', 'Read-only database query failed.');
		$row = $result->fetch_assoc();
		$result->free();
		return $row === null ? null : $row;
	}

	private function assertSelectOnlyPrivileges(array $identity, $configuredUser)
	{
		$currentUser = isset($identity['effective_user']) ? (string) $identity['effective_user'] : '';
		$parts = explode('@', $currentUser, 2);
		if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
			throw new ClinicalPackageException('database_identity_mismatch', 'Authenticated database identity is unavailable.');
		}
		$grantee = "'" . str_replace("'", "''", $parts[0]) . "'@'" . str_replace("'", "''", $parts[1]) . "'";
		$roleGrantee = $parts[0] . '@' . $parts[1];
		$privileges = $this->selectAllPrepared(
			"SELECT privilege_surface,privilege_type,is_grantable FROM (SELECT 'global' AS privilege_surface,privilege_type,is_grantable FROM information_schema.USER_PRIVILEGES WHERE grantee=? UNION ALL SELECT 'schema',privilege_type,is_grantable FROM information_schema.SCHEMA_PRIVILEGES WHERE grantee=? UNION ALL SELECT 'table',privilege_type,is_grantable FROM information_schema.TABLE_PRIVILEGES WHERE grantee=? UNION ALL SELECT 'column',privilege_type,is_grantable FROM information_schema.COLUMN_PRIVILEGES WHERE grantee=?) AS direct_privileges ORDER BY privilege_surface,privilege_type,is_grantable",
			'ssss', array($grantee, $grantee, $grantee, $grantee), 'database_privilege_metadata_unavailable'
		);
		$activeRoles = $this->selectAllPrepared('SELECT role_name FROM information_schema.ENABLED_ROLES WHERE role_name IS NOT NULL ORDER BY role_name', '', array(), 'database_privilege_metadata_unavailable');
		$applicableRoles = $this->selectAllPrepared('SELECT role_name,is_grantable,is_default FROM information_schema.APPLICABLE_ROLES WHERE grantee=? ORDER BY role_name', 's', array($roleGrantee), 'database_privilege_metadata_unavailable');
		$grants = $this->selectColumnValues('SHOW GRANTS FOR CURRENT_USER');
		try {
			self::assertReadOnlyPrivilegeEvidence(array(
				'configured_user' => (string) $configuredUser,
				'authenticated_user' => isset($identity['authenticated_user']) ? (string) $identity['authenticated_user'] : '',
				'current_user' => $currentUser,
				'current_role' => isset($identity['effective_role']) ? $identity['effective_role'] : null,
				'privileges' => $privileges,
				'active_roles' => $activeRoles,
				'applicable_roles' => $applicableRoles,
				'grant_statements' => $grants,
			));
		} catch (ClinicalPackageException $exception) {
			$this->close();
			throw $exception;
		}
	}

	public static function assertReadOnlyPrivilegeEvidence(array $evidence)
	{
		$required = array('configured_user','authenticated_user','current_user','current_role','privileges','active_roles','applicable_roles','grant_statements');
		foreach ($required as $field) if (!array_key_exists($field, $evidence)) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Read-only privilege metadata is incomplete.');
		foreach (array('privileges','active_roles','applicable_roles','grant_statements') as $field) if (!is_array($evidence[$field])) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Read-only privilege metadata is invalid.');
		$configured = (string) $evidence['configured_user'];
		$authenticated = self::accountUser((string) $evidence['authenticated_user']);
		$effective = self::accountUser((string) $evidence['current_user']);
		if ($configured === '' || $authenticated === null || $effective === null || !hash_equals($configured, $authenticated) || !hash_equals($configured, $effective)) {
			throw new ClinicalPackageException('database_proxy_identity_rejected', 'Proxy or unexpected effective database identity is prohibited.');
		}
		$currentRole = $evidence['current_role'];
		if (count($evidence['active_roles']) !== 0 || ($currentRole !== null && strtoupper((string) $currentRole) !== 'NONE')) {
			throw new ClinicalPackageException('database_active_role_rejected', 'Active database roles are prohibited for read-only comparison.');
		}
		$applicableRoles = array();
		foreach ($evidence['applicable_roles'] as $row) {
			if (!is_array($row) || !array_key_exists('role_name', $row) || !array_key_exists('is_grantable', $row) || !array_key_exists('is_default', $row)) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Applicable-role metadata is not interpretable.');
			}
			$roleName = (string) $row['role_name'];
			$grantable = strtoupper((string) $row['is_grantable']);
			$isDefault = strtoupper((string) $row['is_default']);
			if ($roleName === '' || !in_array($grantable, array('YES','NO'), true) || !in_array($isDefault, array('YES','NO'), true) || isset($applicableRoles[$roleName])) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Applicable-role metadata is not interpretable.');
			}
			if ($grantable === 'YES') throw new ClinicalPackageException('database_not_read_only', 'Grantable database roles are prohibited.');
			if ($isDefault === 'YES') throw new ClinicalPackageException('database_active_role_rejected', 'Default database roles are prohibited for read-only comparison.');
			$applicableRoles[$roleName] = true;
		}
		$hasSelect = false;
		foreach ($evidence['privileges'] as $row) {
			if (!is_array($row) || !isset($row['privilege_surface'], $row['privilege_type'], $row['is_grantable'])) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Privilege metadata is not interpretable.');
			}
			$surface = strtolower((string) $row['privilege_surface']);
			$privilege = strtoupper(trim((string) $row['privilege_type']));
			$grantable = strtoupper(trim((string) $row['is_grantable']));
			if (!in_array($surface, array('global','schema','table','column','routine'), true) || !in_array($grantable, array('YES','NO'), true)) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Privilege metadata contains an unknown representation.');
			}
			if ($grantable !== 'NO' || !in_array($privilege, array('SELECT','SHOW VIEW','USAGE'), true)) {
				throw new ClinicalPackageException('database_not_read_only', 'Database identity has privileges beyond the explicit read-only allowlist.');
			}
			if ($privilege === 'SELECT') $hasSelect = true;
		}
		if (!$hasSelect) throw new ClinicalPackageException('database_select_privilege_missing', 'Database identity lacks explicit SELECT access.');
		$grantRoles = array();
		foreach ($evidence['grant_statements'] as $statement) {
			foreach (self::assertReadOnlyGrantStatement($statement, $applicableRoles, (string) $evidence['current_user']) as $roleName) {
				if (isset($grantRoles[$roleName])) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database role grant is duplicated.');
				$grantRoles[$roleName] = true;
			}
		}
		$expectedRoleNames = array_keys($applicableRoles);
		$grantRoleNames = array_keys($grantRoles);
		sort($expectedRoleNames, SORT_STRING);
		sort($grantRoleNames, SORT_STRING);
		if ($expectedRoleNames !== $grantRoleNames) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Role grants do not match applicable-role metadata.');
		return true;
	}

	private static function accountUser($account)
	{
		$parts = explode('@', $account, 2);
		return count($parts) === 2 && $parts[0] !== '' ? $parts[0] : null;
	}

	private static function assertReadOnlyGrantStatement($statement, array $applicableRoles, $effectiveAccount)
	{
		if (!is_string($statement) || $statement === '' || trim($statement) !== $statement || preg_match('/[\x00-\x1f\x7f]/', $statement) === 1 || preg_match('/\/\*|\*\/|--|#|;/', $statement) === 1) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant statement is not interpretable.');
		}
		self::assertGrantLexicalStructure($statement);
		if (preg_match('/^GRANT +/i', $statement, $grantPrefix) !== 1) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant statement is not interpretable.');
		if (preg_match('/\bPROXY\b|\bWITH\s+(?:GRANT|ADMIN)\s+OPTION\b/i', $statement) === 1) {
			throw new ClinicalPackageException('database_not_read_only', 'Proxy and grantable database privileges are prohibited.');
		}
		$body = substr($statement, strlen($grantPrefix[0]));
		$onOffset = self::findGrantDelimiter($body, 'ON');
		$toOffset = self::findGrantDelimiter($body, 'TO');
		if ($toOffset === null) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant statement is not interpretable.');
		if ($onOffset === null) return self::assertReadOnlyRoleGrant($body, $toOffset, $applicableRoles, $effectiveAccount);
		if ($onOffset >= $toOffset) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant statement is not interpretable.');
		$privilegeList = substr($body, 0, $onOffset);
		$objectScope = substr($body, $onOffset + 4, $toOffset - ($onOffset + 4));
		$recipient = substr($body, $toOffset + 4);
		$scopeKind = self::parseGrantObjectScope($objectScope);
		if ($privilegeList === '' || $objectScope === '' || $recipient === '' || $scopeKind === null) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database object grant is not interpretable.');
		}
		self::assertGrantRecipient($recipient, $effectiveAccount, true);
		$privileges = self::assertReadOnlyPrivilegeList($privilegeList);
		self::assertGrantPrivilegeScope($scopeKind, $privileges);
		return array();
	}

	private static function assertReadOnlyRoleGrant($body, $toOffset, array $applicableRoles, $effectiveAccount)
	{
		$roleList = substr($body, 0, $toOffset);
		$recipient = substr($body, $toOffset + 4);
		if ($roleList === '' || $recipient === '' || self::findGrantDelimiter(substr($body, $toOffset + 4), 'TO') !== null) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database role grant is not interpretable.');
		}
		self::assertGrantRecipient($recipient, $effectiveAccount, false);
		$roles = array();
		$roleKeys = array();
		foreach (self::splitGrantList($roleList) as $encodedRole) {
			$roleName = self::decodeGrantRoleIdentifier($encodedRole);
			$roleKey = $roleName === null ? null : self::grantAsciiCaseKey($roleName);
			if ($roleName === null || !isset($applicableRoles[$roleName]) || isset($roleKeys[$roleKey])) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database role grant is not represented uniquely by applicable-role metadata.');
			}
			$roleKeys[$roleKey] = true;
			$roles[] = $roleName;
		}
		return $roles;
	}

	private static function assertReadOnlyPrivilegeList($privilegeList)
	{
		$seen = array();
		$parsed = array();
		foreach (self::splitGrantList($privilegeList) as $encodedPrivilege) {
			if (preg_match('/^([A-Za-z_]+(?: +[A-Za-z_]+)*)(?: *\((.*)\))?$/D', $encodedPrivilege, $match) !== 1) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege list is not interpretable.');
			}
			$privilege = strtoupper(preg_replace('/ +/', ' ', $match[1]));
			$hasColumns = array_key_exists(2, $match) && $match[2] !== '';
			if (!in_array($privilege, array('SELECT','SHOW VIEW','USAGE','EXECUTE'), true)) {
				throw new ClinicalPackageException('database_not_read_only', 'A grant statement contains a privilege outside the read-only allowlist.');
			}
			if (isset($seen[$privilege])) {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege list is ambiguous.');
			}
			if ($hasColumns && $privilege === 'SELECT') self::assertGrantColumnList($match[2]);
			elseif ($hasColumns) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database column privilege list is malformed.');
			elseif (strpos($encodedPrivilege, '(') !== false || strpos($encodedPrivilege, ')') !== false) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database column privilege list is malformed.');
			$seen[$privilege] = true;
			$parsed[] = array('name' => $privilege, 'has_columns' => $hasColumns);
		}
		return $parsed;
	}

	private static function assertGrantPrivilegeScope($scopeKind, array $privileges)
	{
		$executeGranted = false;
		foreach ($privileges as $privilege) {
			$name = $privilege['name'];
			$hasColumns = $privilege['has_columns'];
			if ($name === 'EXECUTE') {
				if (($scopeKind !== 'procedure' && $scopeKind !== 'function') || $hasColumns) {
					throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege is not valid for its object scope.');
				}
				$executeGranted = true;
				continue;
			}
			if ($scopeKind === 'procedure' || $scopeKind === 'function') {
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege is not valid for its object scope.');
			}
			if ($name === 'SELECT') {
				if (!in_array($scopeKind, array('global','schema','table'), true) || ($hasColumns && $scopeKind !== 'table')) {
					throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege is not valid for its object scope.');
				}
				continue;
			}
			if ($name === 'SHOW VIEW') {
				if (($scopeKind !== 'schema' && $scopeKind !== 'table') || $hasColumns) {
					throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege is not valid for its object scope.');
				}
				continue;
			}
			if ($name === 'USAGE') {
				if ($scopeKind !== 'global' || $hasColumns) {
					throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege is not valid for its object scope.');
				}
				continue;
			}
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database privilege is not interpretable.');
		}
		if ($executeGranted) {
			throw new ClinicalPackageException('database_not_read_only', 'A grant statement contains a privilege outside the read-only allowlist.');
		}
	}

	private static function assertGrantColumnList($columnList)
	{
		$seen = array();
		foreach (self::splitGrantList($columnList) as $encodedColumn) {
			$column = self::decodeGrantColumnIdentifier($encodedColumn);
			$columnKey = $column === null ? null : self::grantAsciiCaseKey($column);
			if ($column === null || isset($seen[$columnKey])) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database column privilege list is malformed.');
			$seen[$columnKey] = true;
		}
	}

	private static function assertGrantRecipient($recipient, $effectiveAccount, $allowPasswordClause)
	{
		$parsed = self::parseGrantAccount($recipient);
		if ($parsed === null) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant recipient is not interpretable.');
		$effective = self::parseEffectiveAccount($effectiveAccount);
		if ($effective === null || !hash_equals($effective[0], $parsed[0]) || !hash_equals($effective[1], $parsed[1])) {
			throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant recipient does not match the effective identity.');
		}
		$suffix = substr($recipient, $parsed[2]);
		if ($suffix === '') return;
		if ($allowPasswordClause && preg_match("/^ IDENTIFIED BY PASSWORD '\\*[0-9A-F]{40}'$/Di", $suffix) === 1) return;
		throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant suffix is not supported.');
	}

	private static function parseGrantAccount($text)
	{
		$offset = 0;
		$user = self::parseGrantAccountPart($text, $offset);
		if ($user === null || !isset($text[$offset]) || $text[$offset] !== '@') return null;
		$offset++;
		$host = self::parseGrantAccountPart($text, $offset);
		if ($host === null || (isset($text[$offset]) && $text[$offset] !== ' ')) return null;
		return array($user, $host, $offset);
	}

	private static function parseGrantAccountPart($text, &$offset)
	{
		if (!isset($text[$offset])) return null;
		$quote = $text[$offset];
		if ($quote === '`' || $quote === "'") {
			$offset++;
			$value = '';
			while (isset($text[$offset])) {
				$character = $text[$offset++];
				if ($character !== $quote) { $value .= $character; continue; }
				if (isset($text[$offset]) && $text[$offset] === $quote) { $value .= $quote; $offset++; continue; }
				return $value === '' ? null : $value;
			}
			return null;
		}
		$start = $offset;
		while (isset($text[$offset]) && preg_match('/[A-Za-z0-9_$%.:-]/', $text[$offset]) === 1) $offset++;
		return $offset === $start ? null : substr($text, $start, $offset - $start);
	}

	private static function parseEffectiveAccount($account)
	{
		if (!is_string($account)) return null;
		$separator = strrpos($account, '@');
		if ($separator === false || $separator === 0 || $separator === strlen($account) - 1) return null;
		return array(substr($account, 0, $separator), substr($account, $separator + 1));
	}

	private static function parseGrantObjectScope($scope)
	{
		if (!is_string($scope) || $scope === '' || trim($scope) !== $scope) return null;
		$routineKind = null;
		if (preg_match('/^(PROCEDURE|FUNCTION) +/i', $scope, $match) === 1) {
			$routineKind = strtolower($match[1]);
			$scope = substr($scope, strlen($match[0]));
		} elseif (preg_match('/^(?:PROCEDURE|FUNCTION)\b/i', $scope) === 1) {
			return null;
		}
		$components = self::splitGrantObjectScope($scope);
		if ($components === null) return null;
		if ($routineKind === null && $components[0] === '*' && $components[1] === '*') return 'global';
		$first = self::decodeGrantDatabaseIdentifier($components[0]);
		if ($first === null) return null;
		if ($routineKind === null && $components[1] === '*') return 'schema';
		$second = $routineKind === null
			? self::decodeGrantTableIdentifier($components[1])
			: self::decodeGrantRoutineIdentifier($components[1]);
		if ($second === null) return null;
		return $routineKind === null ? 'table' : $routineKind;
	}

	private static function splitGrantObjectScope($scope)
	{
		$dotOffset = null;
		$inBacktick = false;
		$length = strlen($scope);
		for ($index = 0; $index < $length; $index++) {
			$character = $scope[$index];
			if ($inBacktick) {
				if ($character === '`') {
					if ($index + 1 < $length && $scope[$index + 1] === '`') { $index++; continue; }
					$inBacktick = false;
				}
				continue;
			}
			if ($character === '`') { $inBacktick = true; continue; }
			if ($character !== '.') continue;
			if ($dotOffset !== null) return null;
			$dotOffset = $index;
		}
		if ($inBacktick || $dotOffset === null || $dotOffset === 0 || $dotOffset === $length - 1) return null;
		return array(substr($scope, 0, $dotOffset), substr($scope, $dotOffset + 1));
	}

	private static function decodeGrantDatabaseIdentifier($encoded)
	{
		return self::decodeGrantContextIdentifier($encoded, 64, true, false);
	}

	private static function decodeGrantTableIdentifier($encoded)
	{
		return self::decodeGrantContextIdentifier($encoded, 64, true, false);
	}

	private static function decodeGrantRoutineIdentifier($encoded)
	{
		return self::decodeGrantContextIdentifier($encoded, 64, false, false);
	}

	private static function decodeGrantColumnIdentifier($encoded)
	{
		return self::decodeGrantContextIdentifier(trim($encoded), 64, true, false);
	}

	private static function decodeGrantRoleIdentifier($encoded)
	{
		return self::decodeGrantContextIdentifier(trim($encoded), 128, false, true);
	}

	private static function decodeGrantContextIdentifier($encoded, $maximumCharacters, $rejectTrailingSpace, $rejectReservedRole)
	{
		if (!is_string($encoded) || $encoded === '' || trim($encoded) !== $encoded) return null;
		$value = null;
		if (preg_match('/^`((?:``|[^`])+)`$/D', $encoded, $match) === 1) {
			$value = str_replace('``', '`', $match[1]);
		} elseif (preg_match('/^[A-Za-z0-9_$]+$/D', $encoded) === 1) {
			if (preg_match('/^[0-9]+$/D', $encoded) === 1 || preg_match('/^[0-9]+[eE][0-9]*$/D', $encoded) === 1) return null;
			$value = $encoded;
		} else {
			return null;
		}
		if (preg_match('//u', $value) !== 1 || strpos($value, "\0") !== false || preg_match('/[\x{10000}-\x{10ffff}]/u', $value) === 1) return null;
		$characterCount = preg_match_all('/./us', $value, $characters);
		if ($characterCount === false || $characterCount < 1 || $characterCount > $maximumCharacters) return null;
		if ($rejectTrailingSpace && substr($value, -1) === ' ') return null;
		if ($rejectReservedRole && in_array(strtoupper($value), array('PUBLIC','NONE'), true)) return null;
		return $value;
	}

	private static function grantAsciiCaseKey($identifier)
	{
		return strtolower($identifier);
	}

	private static function assertGrantLexicalStructure($statement)
	{
		$quote = null;
		$depth = 0;
		$length = strlen($statement);
		for ($index = 0; $index < $length; $index++) {
			$character = $statement[$index];
			if ($quote !== null) {
				if ($character === $quote) {
					if ($index + 1 < $length && $statement[$index + 1] === $quote) { $index++; continue; }
					$quote = null;
				}
				continue;
			}
			if ($character === '`' || $character === "'") { $quote = $character; continue; }
			if ($character === '(') { $depth++; continue; }
			if ($character === ')') { if ($depth === 0) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant statement is not interpretable.'); $depth--; }
		}
		if ($quote !== null || $depth !== 0) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant statement is not interpretable.');
	}

	private static function findGrantDelimiter($text, $keyword)
	{
		$needle = ' ' . strtoupper($keyword) . ' ';
		$quote = null;
		$depth = 0;
		$length = strlen($text);
		for ($index = 0; $index <= $length - strlen($needle); $index++) {
			$character = $text[$index];
			if ($quote !== null) {
				if ($character === $quote) {
					if ($index + 1 < $length && $text[$index + 1] === $quote) { $index++; continue; }
					$quote = null;
				}
				continue;
			}
			if ($character === '`' || $character === "'") { $quote = $character; continue; }
			if ($character === '(') { $depth++; continue; }
			if ($character === ')') { $depth--; continue; }
			if ($depth === 0 && strtoupper(substr($text, $index, strlen($needle))) === $needle) return $index;
		}
		return null;
	}

	private static function splitGrantList($list)
	{
		if ($list === '' || trim($list) !== $list) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant list is malformed.');
		$parts = array();
		$start = 0;
		$quote = null;
		$depth = 0;
		$length = strlen($list);
		for ($index = 0; $index < $length; $index++) {
			$character = $list[$index];
			if ($quote !== null) {
				if ($character === $quote) {
					if ($index + 1 < $length && $list[$index + 1] === $quote) { $index++; continue; }
					$quote = null;
				}
				continue;
			}
			if ($character === '`' || $character === "'") { $quote = $character; continue; }
			if ($character === '(') { $depth++; continue; }
			if ($character === ')') { $depth--; continue; }
			if ($character === ',' && $depth === 0) { $parts[] = trim(substr($list, $start, $index - $start)); $start = $index + 1; }
		}
		$parts[] = trim(substr($list, $start));
		foreach ($parts as $part) if ($part === '') throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'A database grant list is malformed.');
		return $parts;
	}

	private function selectColumnValues($sql)
	{
		$result = $this->mysqli->query($sql);
		if ($result === false) throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Database privilege grants could not be inspected.');
		$values = array();
		while ($row = $result->fetch_row()) {
			if (!is_array($row) || count($row) !== 1 || !is_string($row[0])) {
				$result->free();
				throw new ClinicalPackageException('database_privilege_metadata_unavailable', 'Database privilege grants are not interpretable.');
			}
			$values[] = $row[0];
		}
		$result->free();
		return $values;
	}

	private function selectAllPrepared($sql, $types, array $values, $errorCode = 'database_select_failed')
	{
		$statement = $this->mysqli->prepare($sql);
		if ($statement === false) throw new ClinicalPackageException($errorCode, 'Read-only database query preparation failed.');
		$bound = true;
		if ($types !== '') {
			$references = array($types);
			foreach ($values as $index => $value) {
				$values[$index] = (string) $value;
				$references[] =& $values[$index];
			}
			$bound = call_user_func_array(array($statement, 'bind_param'), $references);
		}
		if (!$bound || !$statement->execute()) {
			$statement->close();
			throw new ClinicalPackageException($errorCode, 'Read-only database query failed.');
		}
		$result = $statement->get_result();
		if ($result === false) {
			$statement->close();
			throw new ClinicalPackageException($errorCode, 'Read-only database result is unavailable.');
		}
		$rows = array();
		while ($row = $result->fetch_assoc()) $rows[] = $row;
		$result->free();
		$statement->close();
		return $rows;
	}
}
