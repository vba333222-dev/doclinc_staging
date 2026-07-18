<?php

require_once dirname(__DIR__) . '/ClinicalSchemaException.php';
require_once dirname(__DIR__) . '/ClinicalSchemaCli.php';
require_once dirname(__DIR__) . '/ClinicalSchemaDescriptor.php';
require_once dirname(__DIR__) . '/ClinicalSchemaConnection.php';
require_once dirname(__DIR__) . '/ClinicalSchemaMigrator.php';
require_once dirname(__DIR__) . '/ClinicalSchemaReporter.php';

class ClinicalSchemaTestFailure extends RuntimeException
{
}

class FakeClinicalSchemaConnection extends ClinicalSchemaConnection
{
	public $schemas = array();
	public $present = array();
	public $ledger = array();
	public $tableFeatures = array();
	public $indexOverrides = array();
	public $affectedOverrides = array();
	public $globalPrivileges = array();
	public $schemaPrivileges = array();
	public $unrelatedSchemaPrivileges = array();
	public $tablePrivileges = array();
	public $queryLog = array();
	public $ddlCount = 0;
	public $ledgerWriteCount = 0;
	public $lockAcquireCount = 0;
	public $failDdl = false;
	public $lockContention = false;
	public $serverProduct = 'MariaDB';
	public $serverVersion = '10.11.15';
	public $databaseName = 'clinical_schema_unit';
	private $lockHeld = false;

	public function __construct()
	{
	}

	public function addDescriptorSchemas(array $descriptor)
	{
		foreach ($descriptor['steps'] as $step) {
			$this->schemas[$step['expected_schema']['table_name']] = $step['expected_schema'];
		}
	}

	public function makePresent($table)
	{
		$this->present[$table] = true;
	}

	public function acquireLock($name, $timeout)
	{
		$this->lockAcquireCount++;
		if ($this->lockContention || $this->lockHeld) {
			throw new ClinicalSchemaException('advisory_lock_unavailable');
		}
		$this->lockHeld = true;
	}

	public function releaseLock($name)
	{
		$this->lockHeld = false;
		return true;
	}

	public function getServerVersion()
	{
		return $this->serverVersion;
	}

	public function getDatabaseName()
	{
		return $this->databaseName;
	}

	public function getExecutorIdentityHash()
	{
		return 'db-account-sha256:' . str_repeat('a', 64);
	}

	public function queryAll($sql)
	{
		$this->queryLog[] = $sql;
		if (strpos($sql, 'SELECT VERSION()') !== false) {
			if ($this->serverProduct === 'MariaDB') {
				return array(array('server_version' => $this->serverVersion . '-MariaDB', 'version_comment' => 'MariaDB Server'));
			}
			return array(array('server_version' => $this->serverVersion, 'version_comment' => 'MySQL Community Server'));
		}
		if (strpos($sql, 'information_schema.USER_PRIVILEGES') !== false) {
			return $this->privilegeRows($this->globalPrivileges);
		}
		if (strpos($sql, 'information_schema.SCHEMA_PRIVILEGES') !== false) {
			return $this->privilegeRows($this->schemaPrivileges);
		}
		if (strpos($sql, 'GROUP BY state') !== false) {
			$counts = array();
			foreach ($this->ledger as $row) {
				$state = $row['state'];
				$counts[$state] = isset($counts[$state]) ? $counts[$state] + 1 : 1;
			}
			ksort($counts, SORT_STRING);
			$rows = array();
			foreach ($counts as $state => $count) {
				$rows[] = array('state' => $state, 'row_count' => $count);
			}
			return $rows;
		}
		return array();
	}

	private function privilegeRows(array $privileges)
	{
		$rows = array();
		foreach ($privileges as $privilege) {
			$rows[] = array('PRIVILEGE_TYPE' => $privilege);
		}
		return $rows;
	}

	public function queryOnePrepared($sql, $types, array $parameters)
	{
		$rows = $this->queryPrepared($sql, $types, $parameters);
		return count($rows) ? $rows[0] : null;
	}

	public function queryPrepared($sql, $types, array $parameters)
	{
		$this->assertBindingCount($sql, $types, $parameters);
		if (strpos($sql, 'information_schema.TABLES') !== false) {
			$table = $parameters[0];
			if (empty($this->present[$table]) || !isset($this->schemas[$table])) {
				return array();
			}
			$schema = $this->schemas[$table];
			$features = isset($this->tableFeatures[$table]) ? $this->tableFeatures[$table] : array();
			return array(array(
				'ENGINE' => $schema['engine'],
				'TABLE_COLLATION' => $schema['default_collation'],
				'ROW_FORMAT' => isset($features['row_format']) ? $features['row_format'] : 'Dynamic',
				'CREATE_OPTIONS' => isset($features['create_options']) ? $features['create_options'] : '',
				'TABLE_COMMENT' => isset($features['table_comment']) ? $features['table_comment'] : '',
				'AUTO_INCREMENT' => isset($features['auto_increment']) ? $features['auto_increment'] : null,
			));
		}
		if (strpos($sql, 'information_schema.PARTITIONS') !== false) {
			$table = $parameters[0];
			if (empty($this->present[$table])) {
				return array();
			}
			$features = isset($this->tableFeatures[$table]) ? $this->tableFeatures[$table] : array();
			return array(array(
				'PARTITION_NAME' => !empty($features['partitioned']) ? 'p0' : null,
				'TABLESPACE_NAME' => isset($features['tablespace']) ? $features['tablespace'] : null,
			));
		}
		if (strpos($sql, 'information_schema.COLUMNS') !== false) {
			$table = $parameters[0];
			if (empty($this->present[$table]) || !isset($this->schemas[$table])) {
				return array();
			}
			$rows = array();
			foreach ($this->schemas[$table]['columns'] as $column) {
				$rows[] = array(
					'COLUMN_NAME' => $column['name'], 'COLUMN_TYPE' => $column['column_type'],
					'IS_NULLABLE' => $column['nullable'] ? 'YES' : 'NO', 'COLUMN_DEFAULT' => $column['default'],
					'EXTRA' => $column['extra'], 'CHARACTER_SET_NAME' => $column['character_set'],
					'COLLATION_NAME' => $column['collation'],
					'COLUMN_COMMENT' => isset($column['comment']) ? $column['comment'] : '',
					'GENERATION_EXPRESSION' => isset($column['generation_expression']) ? $column['generation_expression'] : '',
				);
			}
			return $rows;
		}
		if (strpos($sql, 'information_schema.STATISTICS') !== false) {
			$table = $parameters[0];
			if (empty($this->present[$table]) || !isset($this->schemas[$table])) {
				return array();
			}
			$rows = array();
			$this->indexRows($rows, $table, 'PRIMARY', 0, $this->schemas[$table]['primary_key']);
			foreach ($this->schemas[$table]['unique_indexes'] as $index) {
				$this->indexRows($rows, $table, $index['name'], 0, $index['columns']);
			}
			foreach ($this->schemas[$table]['indexes'] as $index) {
				$this->indexRows($rows, $table, $index['name'], 1, $index['columns']);
			}
			usort($rows, function ($a, $b) {
				return strcmp($a['INDEX_NAME'] . sprintf('%03d', $a['SEQ_IN_INDEX']), $b['INDEX_NAME'] . sprintf('%03d', $b['SEQ_IN_INDEX']));
			});
			return $rows;
		}
		if (strpos($sql, 'information_schema.KEY_COLUMN_USAGE') !== false) {
			$table = $parameters[0];
			if (empty($this->present[$table]) || !isset($this->schemas[$table])) {
				return array();
			}
			$rows = array();
			foreach ($this->schemas[$table]['foreign_keys'] as $foreignKey) {
				foreach ($foreignKey['columns'] as $index => $column) {
					$rows[] = array(
						'CONSTRAINT_NAME' => $foreignKey['name'], 'COLUMN_NAME' => $column,
						'REFERENCED_TABLE_NAME' => $foreignKey['referenced_table'],
						'REFERENCED_COLUMN_NAME' => $foreignKey['referenced_columns'][$index],
						'UPDATE_RULE' => $foreignKey['on_update'], 'DELETE_RULE' => $foreignKey['on_delete'],
						'ORDINAL_POSITION' => $index + 1,
					);
				}
			}
			return $rows;
		}
		if (strpos($sql, 'information_schema.TABLE_CONSTRAINTS') !== false) {
			$table = $parameters[0];
			if (empty($this->present[$table]) || !isset($this->schemas[$table])) {
				return array();
			}
			$rows = array();
			foreach ($this->schemas[$table]['check_constraints'] as $check) {
				$rows[] = array('CONSTRAINT_NAME' => $check['name'], 'CHECK_CLAUSE' => $check['expression']);
			}
			return $rows;
		}
		if (strpos($sql, 'migration_checksum=?') !== false && strpos($sql, 'migration_id<>?') !== false) {
			foreach ($this->ledger as $id => $row) {
				if ($row['migration_checksum'] === $parameters[0] && $id !== $parameters[1]) {
					return array(array('migration_id' => $id));
				}
			}
			return array();
		}
		if (strpos($sql, 'FROM `clinical_schema_migrations` WHERE migration_id=?') !== false) {
			$id = $parameters[0];
			return isset($this->ledger[$id]) ? array($this->ledger[$id]) : array();
		}
		return array();
	}

	public function executeDdl($sql)
	{
		$this->ddlCount++;
		if ($this->failDdl) {
			throw new ClinicalSchemaException('ddl_step_failed');
		}
		if (preg_match('/CREATE\s+TABLE\s+`([a-z0-9_]+)`/i', $sql, $match) !== 1 || !isset($this->schemas[$match[1]])) {
			throw new ClinicalSchemaException('fake_ddl_unknown_table');
		}
		$this->present[$match[1]] = true;
	}

	public function executePrepared($sql, $types, array $parameters)
	{
		$this->ledgerWriteCount++;
		$this->assertBindingCount($sql, $types, $parameters);
		if (strpos($sql, 'INSERT INTO `clinical_schema_migrations`') !== false) {
			$id = $parameters[0];
			$bootstrap = strpos($sql, "'applied'") !== false;
			$transition = $bootstrap ? 'bootstrap_insert' : 'migration_insert';
			$affected = $this->affectedFor($transition);
			if ($affected !== 1) {
				return $affected;
			}
			$this->ledger[$id] = array(
				'migration_id' => $id,
				'migration_name' => $parameters[1],
				'migration_checksum' => $parameters[2],
				'state' => $bootstrap ? 'applied' : 'applying',
				'attempt_count' => 1,
				'statement_count' => $bootstrap ? 1 : (int) $parameters[3],
				'last_completed_step' => $bootstrap ? 1 : 0,
				'execution_environment' => $parameters[$bootstrap ? 3 : 4],
				'target_database' => $parameters[$bootstrap ? 4 : 5],
				'server_version' => $parameters[$bootstrap ? 5 : 6],
				'tool_version' => $parameters[$bootstrap ? 6 : 7],
				'executor_identity' => $parameters[$bootstrap ? 7 : 8],
				'backup_reference' => $parameters[$bootstrap ? 8 : 9],
				'started_at' => $parameters[$bootstrap ? 9 : 10],
				'applied_at' => $bootstrap ? $parameters[10] : null,
				'failed_at' => null,
			);
			return $affected;
		}
		if (strpos($sql, 'attempt_count=attempt_count+1') !== false) {
			$transition = 'resume_transition';
			$id = $parameters[1];
		} elseif (strpos($sql, 'last_completed_step=?') !== false) {
			$transition = 'checkpoint_update';
			$id = $parameters[2];
		} elseif (strpos($sql, "state='failed'") !== false) {
			$transition = 'failure_transition';
			$id = $parameters[4];
		} elseif (strpos($sql, "state='applied'") !== false && strpos($sql, 'updated_at=?') !== false) {
			$transition = 'final_applied_transition';
			$id = $parameters[2];
		} elseif (strpos($sql, "state='applied'") !== false) {
			$transition = 'bootstrap_recovery';
			$id = $parameters[1];
		} else {
			$transition = 'unknown_transition';
			$id = end($parameters);
		}
		$affected = $this->affectedFor($transition);
		if ($affected !== 1) {
			return $affected;
		}
		if (!isset($this->ledger[$id])) {
			return 0;
		}
		if ($transition === 'resume_transition') {
			$this->ledger[$id]['state'] = 'applying';
			$this->ledger[$id]['attempt_count']++;
		}
		if ($transition === 'checkpoint_update') {
			$this->ledger[$id]['last_completed_step'] = (int) $parameters[0];
		}
		if ($transition === 'final_applied_transition' || $transition === 'bootstrap_recovery') {
			$this->ledger[$id]['state'] = 'applied';
			$this->ledger[$id]['last_completed_step'] = $this->ledger[$id]['statement_count'];
		}
		if ($transition === 'failure_transition') {
			$this->ledger[$id]['state'] = 'failed';
			$this->ledger[$id]['failed_at'] = $parameters[0];
		}
		return $affected;
	}

	private function affectedFor($transition)
	{
		if (!array_key_exists($transition, $this->affectedOverrides)) {
			return 1;
		}
		$value = $this->affectedOverrides[$transition];
		if (is_array($value)) {
			$result = array_shift($value);
			$this->affectedOverrides[$transition] = $value;
			return (int) $result;
		}
		return (int) $value;
	}

	public function begin()
	{
	}

	public function commit()
	{
	}

	public function rollback()
	{
	}

	private function assertBindingCount($sql, $types, array $parameters)
	{
		$placeholderCount = substr_count($sql, '?');
		if ($placeholderCount !== strlen($types) || $placeholderCount !== count($parameters)) {
			throw new ClinicalSchemaTestFailure('Prepared statement binding count mismatch in test double.');
		}
	}

	private function indexRows(array &$rows, $table, $name, $nonUnique, array $columns)
	{
		$override = isset($this->indexOverrides[$table][$name]) ? $this->indexOverrides[$table][$name] : array();
		foreach ($columns as $index => $column) {
			$rows[] = array(
				'INDEX_NAME' => $name,
				'NON_UNIQUE' => $nonUnique,
				'INDEX_TYPE' => isset($override['index_type']) ? $override['index_type'] : 'BTREE',
				'SEQ_IN_INDEX' => $index + 1,
				'COLUMN_NAME' => $column,
				'SUB_PART' => isset($override['sub_parts'][$index]) ? $override['sub_parts'][$index] : null,
				'COLLATION' => isset($override['collations'][$index]) ? $override['collations'][$index] : 'A',
				'IGNORED' => !empty($override['ignored']) ? 'YES' : 'NO',
			);
		}
	}
}

class PrivilegeMetadataTestConnection extends ClinicalSchemaConnection
{
	public $database = 'doclink_clinical_schema_test';
	public $currentGrantee = "'migration_user'@'%'";
	public $globalGrantRows = array();
	public $schemaGrantRows = array();
	public $tableGrantRows = array();
	public $queryLog = array();

	public function __construct()
	{
	}

	public function queryAll($sql)
	{
		$this->queryLog[] = $sql;
		if (strpos($sql, 'GRANTEE=CONCAT(QUOTE(LEFT(CURRENT_USER()') === false) {
			return array();
		}
		if (strpos($sql, 'information_schema.USER_PRIVILEGES') !== false) {
			return $this->matchingAccountRows($this->globalGrantRows);
		}
		if (strpos($sql, 'information_schema.SCHEMA_PRIVILEGES') !== false) {
			if (strpos($sql, "DATABASE() LIKE TABLE_SCHEMA ESCAPE '\\\\'") === false) {
				return array();
			}
			$rows = array();
			foreach ($this->matchingAccountRows($this->schemaGrantRows, true) as $row) {
				if ($this->databaseLikePattern($this->database, $row['TABLE_SCHEMA'])) {
					$rows[] = array('PRIVILEGE_TYPE' => $row['PRIVILEGE_TYPE']);
				}
			}
			return $rows;
		}
		return array();
	}

	private function matchingAccountRows(array $rows, $includeSchema = false)
	{
		$matching = array();
		foreach ($rows as $row) {
			if ($row['GRANTEE'] !== $this->currentGrantee) {
				continue;
			}
			$matchingRow = array('PRIVILEGE_TYPE' => $row['PRIVILEGE_TYPE']);
			if ($includeSchema) {
				$matchingRow['TABLE_SCHEMA'] = $row['TABLE_SCHEMA'];
			}
			$matching[] = $matchingRow;
		}
		return $matching;
	}

	private function databaseLikePattern($database, $pattern)
	{
		$regex = '';
		$length = strlen($pattern);
		for ($index = 0; $index < $length; $index++) {
			$character = $pattern[$index];
			if ($character === '\\') {
				$index++;
				if ($index >= $length) {
					return false;
				}
				$regex .= preg_quote($pattern[$index], '/');
			} elseif ($character === '%') {
				$regex .= '.*';
			} elseif ($character === '_') {
				$regex .= '.';
			} else {
				$regex .= preg_quote($character, '/');
			}
		}
		return preg_match('/\\A' . $regex . '\\z/is', $database) === 1;
	}
}

class ClinicalSchemaTestRunner
{
	private $root;
	private $migrationRoot;
	private $config;
	private $passed = 0;
	private $failed = 0;
	private $skipped = 0;
	private $results = array();
	private $integrationEnvironment = array();

	public function __construct()
	{
		$this->root = dirname(__DIR__);
		$this->migrationRoot = $this->root . DIRECTORY_SEPARATOR . 'migrations';
		$this->config = require $this->root . DIRECTORY_SEPARATOR . 'config.php';
		foreach ($this->config['connection_environment_variables'] as $name) {
			$this->integrationEnvironment[$name] = getenv($name);
		}
	}

	public function run($integration)
	{
		try {
			$this->runUnitTests();
			if ($integration) {
				$this->restoreIntegrationEnvironment();
				$this->runIntegrationTests();
			} else {
				$this->skip('I01_disposable_database_name_gate','disposable_integration_not_requested');
				$this->skip('I02_disposable_mariadb_privilege_preflight','disposable_integration_not_requested');
			}
		} finally {
			$this->restoreIntegrationEnvironment();
		}
		foreach ($this->results as $result) {
			echo $result . PHP_EOL;
		}
		echo 'TEST_PASS_COUNT=' . $this->passed . PHP_EOL;
		echo 'TEST_FAIL_COUNT=' . $this->failed . PHP_EOL;
		echo 'TEST_SKIP_COUNT=' . $this->skipped . PHP_EOL;
		echo 'TEST_RESULT=' . ($this->failed === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
		return $this->failed === 0 ? 0 : 1;
	}

	private function runUnitTests()
	{
		$loader = new ClinicalSchemaDescriptor($this->migrationRoot);
		$ledger = $loader->loadById($this->config['ledger_migration_id']);
		$foundation = $loader->loadById('20260718000100_clinical_import_audit_foundation');

		$this->test('01_non_cli_rejection', function () { $this->expectCode(function () { ClinicalSchemaCli::assertCli('cgi-fcgi'); }, 'cli_only'); });
		$this->test('02_unknown_command', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'unknown')); }, 'invalid_command'); });
		$this->test('03_unknown_flag', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'help', '--force=yes')); }, 'unknown_flag'); });
		$this->test('04_duplicate_flag', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'help', '--format=text', '--format=json')); }, 'duplicate_flag'); });
		$this->test('05_malformed_flag', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'help', '--format')); }, 'malformed_flag'); });
		$this->test('06_password_flag_prohibited', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'help', '--password=value')); }, 'unknown_flag'); });
		$this->test('07_missing_migration_id', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'plan')); }, 'missing_migration_id'); });
		$this->test('08_missing_environment', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'apply', '--migration=20260718000100_clinical_import_audit_foundation', '--confirm-database=unit_db', '--backup-reference=snapshot-1', '--confirm-backup')); }, 'missing_environment'); });
		$this->test('09_missing_database_confirmation', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'apply', '--migration=20260718000100_clinical_import_audit_foundation', '--environment=test', '--backup-reference=snapshot-1', '--confirm-backup')); }, 'missing_database_confirmation'); });
		$this->test('10_missing_backup_confirmation', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'apply', '--migration=20260718000100_clinical_import_audit_foundation', '--environment=test', '--confirm-database=unit_db', '--backup-reference=snapshot-1')); }, 'missing_backup_confirmation'); });
		$this->test('11_missing_backup_reference', function () { $this->expectCode(function () { ClinicalSchemaCli::parse(array('tool', 'apply', '--migration=20260718000100_clinical_import_audit_foundation', '--environment=test', '--confirm-database=unit_db', '--confirm-backup')); }, 'missing_backup_reference'); });
		$this->test('12_runtime_application_username_rejected', function () {
			$names = $this->config['connection_environment_variables'];
			$values = array($names['host'] => '127.0.0.1', $names['port'] => '1', $names['database'] => 'clinical_schema_unit', $names['user'] => $this->config['hard_rejected_database_users'][0], $names['password'] => 'temporary-unit-value', $names['allowed_users'] => $this->config['hard_rejected_database_users'][0]);
			$previous = array();
			foreach ($values as $name => $value) { $previous[$name]=getenv($name);putenv($name . '=' . $value); }
			try { $this->expectCode(function () { ClinicalSchemaConnection::fromEnvironment($this->config, 'clinical_schema_unit', '10.11.0', true); }, 'runtime_database_user_rejected'); }
			finally { foreach ($previous as $name => $value) { if($value===false){putenv($name);}else{putenv($name.'='.$value);} } }
		});

		$this->fixtureTest('13_descriptor_root_object', function ($root) { file_put_contents($root . '/00000000000000_clinical_schema_ledger.json', '[]'); }, 'descriptor_root_not_object');
		$this->fixtureTest('14_steps_array', function ($root) { $this->mutateJson($root, $this->config['ledger_migration_id'], function (&$json) { $json['steps'] = new stdClass(); }); }, 'descriptor_steps_not_array');
		$this->fixtureTest('15_descriptor_unknown_field', function ($root) { $this->mutateJson($root, $this->config['ledger_migration_id'], function (&$json) { $json['unknown'] = true; }); }, 'descriptor_unknown_field');
		$this->fixtureTest('16_step_unknown_field', function ($root) { $this->mutateJson($root, $this->config['ledger_migration_id'], function (&$json) { $json['steps'][0]['unknown'] = true; }); }, 'step_unknown_field');
		$this->fixtureTest('17_unknown_step_type', function ($root) { $this->mutateJson($root, $this->config['ledger_migration_id'], function (&$json) { $json['steps'][0]['type'] = 'alter_table'; }); }, 'descriptor_step_type_unknown');
		$this->fixtureTest('18_duplicate_step_id', function ($root) { $this->mutateJson($root, '20260718000100_clinical_import_audit_foundation', function (&$json) { $json['steps'][1]['step_id'] = $json['steps'][0]['step_id']; }); }, 'descriptor_step_id_invalid', '20260718000100_clinical_import_audit_foundation');
		$this->fixtureTest('19_absolute_sql_path', function ($root) { $this->mutateJson($root, $this->config['ledger_migration_id'], function (&$json) { $json['steps'][0]['sql_file'] = '/tmp/outside.sql'; }); }, 'sql_path_absolute');
		$this->fixtureTest('20_traversal_sql_path', function ($root) { $this->mutateJson($root, $this->config['ledger_migration_id'], function (&$json) { $json['steps'][0]['sql_file'] = '../outside.sql'; }); }, 'sql_path_traversal');
		$this->testSymlinkEscape();
		$this->fixtureTest('22_descriptor_bom', function ($root) { $path=$root.'/'.$this->config['ledger_migration_id'].'.json'; file_put_contents($path, "\xEF\xBB\xBF".file_get_contents($path)); }, 'descriptor_bom_rejected');
		$this->fixtureTest('23_descriptor_invalid_utf8', function ($root) { $path=$root.'/'.$this->config['ledger_migration_id'].'.json'; file_put_contents($path, "{\"bad\":\"\xFF\"}"); }, 'descriptor_utf8_invalid');
		$this->fixtureTest('24_sql_bom', function ($root) { $path=$root.'/sql/00000000000000/001_create_clinical_schema_migrations.sql'; file_put_contents($path, "\xEF\xBB\xBF".file_get_contents($path)); }, 'sql_bom_rejected');
		$this->fixtureTest('25_sql_invalid_utf8', function ($root) { $path=$root.'/sql/00000000000000/001_create_clinical_schema_migrations.sql'; file_put_contents($path, file_get_contents($path)."\xFF"); }, 'sql_utf8_invalid');

		$this->test('26_sql_multiple_statements', function () use ($loader, $ledger) { $this->expectCode(function () use ($loader, $ledger) { $loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT); CREATE TABLE `other` (`x` INT);', $ledger['steps'][0]['expected_schema']); }, 'sql_statement_count_invalid'); });
		$this->test('27_forbidden_sql_verb', function () use ($loader, $ledger) { $this->expectCode(function () use ($loader, $ledger) { $loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) ENGINE=InnoDB UPDATE;', $ledger['steps'][0]['expected_schema']); }, 'sql_forbidden_construct'); });
		$this->test('28_executable_version_comment', function () use ($loader, $ledger) { $this->expectCode(function () use ($loader, $ledger) { $loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*! ENGINE=InnoDB */;', $ledger['steps'][0]['expected_schema']); }, 'sql_executable_comment_rejected'); });
		$this->test('29_quoted_semicolon_accepted', function () use ($loader) { $expected=$this->minimalSchema('quoted_table'); $loader->validateSql("CREATE TABLE `quoted_table` (`value` VARCHAR(10) DEFAULT ';') ENGINE=InnoDB;", $expected); });
		$this->test('30_composite_checksum_deterministic', function () use ($loader, $ledger) { $again=$loader->loadById($ledger['migration_id']); $this->assertSame($ledger['checksum'], $again['checksum']); });
		$this->testChecksumNewlines($ledger['checksum']);
		$this->testChecksumMutation('32_sql_content_changes_checksum', function ($root) { $path=$root.'/sql/00000000000000/001_create_clinical_schema_migrations.sql'; file_put_contents($path, file_get_contents($path)."\n"); });
		$this->testChecksumMutation('33_sql_path_changes_checksum', function ($root) { $old='sql/00000000000000/001_create_clinical_schema_migrations.sql'; $new='sql/00000000000000/099_create_clinical_schema_migrations.sql'; copy($root.'/'.$old,$root.'/'.$new); $this->mutateJson($root,$this->config['ledger_migration_id'],function (&$json) use ($new) { $json['steps'][0]['sql_file']=$new; }); });
		$this->testChecksumOrder();

		$this->test('35_text_newline_injection_escaped', function () { $value=ClinicalSchemaReporter::textValue("safe\ninjected\r\tvalue\0"); $this->assertTrue(strpos($value, "\n") === false && strpos($value, "\r") === false); $this->assertTrue(strpos($value, '\\n') !== false && strpos($value, '\\x00') !== false); });
		$this->test('36_json_output_one_object', function () { $report=ClinicalSchemaReporter::finalize(ClinicalSchemaReporter::baseReport('test'),0,'PASS'); ob_start(); $exit=ClinicalSchemaReporter::render($report,'json'); $json=ob_get_clean(); $this->assertSame(0,$exit); $this->assertTrue(is_array(json_decode($json,true))); });
		$this->test('37_json_encoding_fallback', function () { $report=ClinicalSchemaReporter::baseReport('test'); $recursive=array(); $recursive['self']=&$recursive; $report['summary']=$recursive; ob_start(); $exit=ClinicalSchemaReporter::render($report,'json'); $json=ob_get_clean(); $decoded=json_decode($json,true); $this->assertSame(2,$exit); $this->assertSame('ERROR',$decoded['validation_result']); });
		$this->test('38_credentials_absent_from_output', function () { $marker='temporary-sensitive-marker'; $report=ClinicalSchemaReporter::baseReport('error'); ClinicalSchemaReporter::addError($report,'database_connection_failed'); $report=ClinicalSchemaReporter::finalize($report,2,'ERROR'); ob_start(); ClinicalSchemaReporter::render($report,'text'); $text=ob_get_clean(); $this->assertTrue(strpos($text,$marker)===false); });

		$this->test('39_status_absent_ledger_read_only', function () use ($loader, $ledger, $foundation) { $fake=$this->fakeEmpty($ledger,$foundation); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $result=$m->status($this->writeOptions()); $this->assertSame(false,$result['ledger_initialized']); $this->assertSame(null,$result['ledger_schema_valid']); $this->assertSame(false,$result['bootstrap_record_present']); $this->assertSame(false,$result['bootstrap_record_valid']); $this->assertStatusReadOnly($fake); });
		$this->test('40_plan_no_ddl', function () use ($loader, $ledger, $foundation) { $fake=$this->fake($ledger,$foundation); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $result=$m->plan($foundation); $this->assertSame(false,$result['ddl_executed']); $this->assertSame(0,$fake->ddlCount); });
		$this->test('41_verify_no_ddl', function () use ($loader, $ledger, $foundation) { $fake=$this->fakeApplied($ledger,$foundation); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $result=$m->verify($foundation,$this->writeOptions()); $this->assertSame(false,$result['ddl_executed']); $this->assertSame(0,$fake->ddlCount); });
		$this->test('42_existing_applied_noop', function () use ($loader, $ledger, $foundation) { $fake=$this->fakeApplied($ledger,$foundation); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $result=$m->execute('apply',$foundation,$this->writeOptions()); $this->assertSame(true,$result['already_applied']); $this->assertSame(0,$fake->ddlCount); });
		$this->test('43_applied_checksum_mismatch', function () use ($loader, $ledger, $foundation) { $fake=$this->fakeApplied($ledger,$foundation); $fake->ledger[$foundation['migration_id']]['migration_checksum']=str_repeat('0',64); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $this->expectCode(function () use ($m,$foundation) { $m->execute('apply',$foundation,$this->writeOptions()); },'applied_checksum_mismatch'); });
		$this->test('44_duplicate_migration_checksum', function () use ($loader, $ledger, $foundation) { $fake=$this->fake($ledger,$foundation); $fake->makePresent('clinical_schema_migrations'); $fake->ledger['20990101000000_other']=array('migration_id'=>'20990101000000_other','migration_checksum'=>$foundation['checksum'],'state'=>'applied','attempt_count'=>1,'statement_count'=>1,'last_completed_step'=>1,'server_version'=>'10.11.15'); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $this->expectCode(function () use ($m,$foundation) { $m->execute('apply',$foundation,$this->writeOptions()); },'duplicate_migration_checksum'); });
		$this->test('45_interrupted_requires_resume', function () use ($loader, $ledger, $foundation) { $fake=$this->fake($ledger,$foundation); $fake->makePresent('clinical_schema_migrations'); $fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'applying',0); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $this->expectCode(function () use ($m,$foundation) { $m->execute('apply',$foundation,$this->writeOptions()); },'migration_requires_resume'); });
		$this->test('46_exact_existing_table_recovery', function () use ($loader, $ledger, $foundation) { $fake=$this->fake($ledger,$foundation); $fake->makePresent('clinical_schema_migrations'); $fake->makePresent('clinical_master_import_batches'); $fake->makePresent('clinical_master_import_items'); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $result=$m->execute('apply',$foundation,$this->writeOptions()); $this->assertSame(0,$fake->ddlCount); $this->assertSame('applied',$result['state']); });
		$this->test('47_mismatched_existing_table', function () use ($loader, $ledger, $foundation) { $fake=$this->fake($ledger,$foundation); $fake->makePresent('clinical_schema_migrations'); $fake->makePresent('clinical_master_import_batches'); $fake->schemas['clinical_master_import_batches']['engine']='MyISAM'; $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $this->expectCode(function () use ($m,$foundation) { $m->execute('apply',$foundation,$this->writeOptions()); },'migration_table_collision'); });
		$this->test('48_ddl_failure_not_applied', function () use ($loader, $ledger, $foundation) { $fake=$this->fake($ledger,$foundation); $fake->makePresent('clinical_schema_migrations'); $fake->failDdl=true; $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $this->expectCode(function () use ($m,$foundation) { $m->execute('apply',$foundation,$this->writeOptions()); },'ddl_step_failed'); $this->assertSame('failed',$fake->ledger[$foundation['migration_id']]['state']); });
		$this->test('49_resume_checkpoint_gap', function () use ($loader, $ledger, $foundation) { $fake=$this->fake($ledger,$foundation); $fake->makePresent('clinical_schema_migrations'); $fake->makePresent('clinical_master_import_batches'); $fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'applying',0); $m=new ClinicalSchemaMigrator($loader,$fake,$this->config); $result=$m->execute('resume',$foundation,$this->writeOptions()); $this->assertSame('applied',$result['state']); $this->assertSame(1,$fake->ddlCount); });
		$this->test('50_import_item_fk_fingerprint', function () use ($foundation) { $fk=$foundation['steps'][1]['expected_schema']['foreign_keys'][0]; $this->assertSame('clinical_master_import_batches',$fk['referenced_table']); $this->assertSame(array('import_batch_id'),$fk['columns']); });
		$this->test('51_no_cascade_delete_fk', function () use ($foundation) { foreach($foundation['steps'] as $step){foreach($step['expected_schema']['foreign_keys'] as $fk){$this->assertSame('RESTRICT',$fk['on_delete']);$this->assertSame('RESTRICT',$fk['on_update']);}} });
		$this->test('52_package_directory_never_opened', function () { $source=$this->allSource(); $this->assertTrue(strpos($source,'database/master_data')===false && strpos($source,'00_manifest.json')===false); });
		$this->test('53_no_import_rows_created', function () { foreach(glob($this->migrationRoot.'/sql/*/*.sql') as $path){$sql=file_get_contents($path);$this->assertTrue(preg_match('/\b(?:INSERT|REPLACE|UPDATE|DELETE)\b/i',preg_replace('/\bON\s+(?:UPDATE|DELETE)\b/i','ON ACTION',$sql))!==1);} });
		$this->test('54_application_tables_unchanged_scope', function () use ($ledger,$foundation) { $tables=array_merge($ledger['expected_created_tables'],$foundation['expected_created_tables']); sort($tables); $this->assertSame(array('clinical_master_import_batches','clinical_master_import_items','clinical_schema_migrations'),$tables); });
		$this->fixtureTest('55_runtime_enable_descriptor_rejected', function ($root) { $this->mutateJson($root,$this->config['ledger_migration_id'],function (&$json){$json['enables_runtime']=true;}); },'descriptor_runtime_policy_rejected');
		$this->fixtureTest('56_package_import_descriptor_rejected', function ($root) { $this->mutateJson($root,$this->config['ledger_migration_id'],function (&$json){$json['imports_package_data']=true;}); },'descriptor_runtime_policy_rejected');
		$this->test('57_advisory_lock_contention', function () use ($loader,$ledger,$foundation) { $fake=$this->fake($ledger,$foundation);$fake->lockContention=true;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function () use($m,$ledger){$m->execute('init',$ledger,$this->writeOptions());},'advisory_lock_unavailable'); });
		$this->test('58_patch_version_compatible', function () use ($loader,$ledger,$foundation) { $fake=$this->fake($ledger,$foundation);$fake->serverVersion='10.11.99';$fake->verifyServer('10.11.0'); });
		$this->test('59_mysql_product_rejected', function () use ($loader,$ledger,$foundation) { $fake=$this->fake($ledger,$foundation);$fake->serverProduct='MySQL';$this->expectCode(function () use($fake){$fake->verifyServer('10.11.0');},'database_product_rejected'); });
		$this->test('60_mariadb_below_minimum', function () use ($loader,$ledger,$foundation) { $fake=$this->fake($ledger,$foundation);$fake->serverVersion='10.10.9';$this->expectCode(function () use($fake){$fake->verifyServer('10.11.0');},'database_version_too_old'); });

		$this->test('61_bare_confirm_backup_accepted', function () {
			$parsed=ClinicalSchemaCli::parse(array('tool','apply','--migration=20260718000100_clinical_import_audit_foundation','--environment=unit_test','--confirm-database=clinical_schema_unit','--backup-reference=unit-test-snapshot','--confirm-backup'));
			$this->assertSame(true,$parsed['options']['confirm_backup']);
		});
		$booleanAssignments=array('true','false','yes','no','1','0');
		foreach($booleanAssignments as $offset=>$value){
			$number=62+$offset;
			$this->test(sprintf('%02d_confirm_backup_assignment_%s_rejected',$number,$value),function()use($value){
				$this->expectCode(function()use($value){ClinicalSchemaCli::parse(array('tool','apply','--migration=20260718000100_clinical_import_audit_foundation','--environment=unit_test','--confirm-database=clinical_schema_unit','--backup-reference=unit-test-snapshot','--confirm-backup='.$value));},'boolean_flag_assignment_rejected');
			});
		}

		$required=$this->config['required_write_privileges'];
		$this->test('68_required_global_privileges_pass',function()use($ledger,$foundation,$required){$fake=$this->fake($ledger,$foundation);$fake->globalPrivileges=$required;$fake->verifyPrivileges($required);});
		$this->test('69_required_target_schema_privileges_pass',function()use($ledger,$foundation,$required){$fake=$this->fake($ledger,$foundation);$fake->schemaPrivileges=$required;$fake->verifyPrivileges($required);});
		$this->test('70_mixed_global_schema_privileges_pass',function()use($ledger,$foundation,$required){$fake=$this->fake($ledger,$foundation);$fake->globalPrivileges=array_slice($required,0,3);$fake->schemaPrivileges=array_slice($required,3);$fake->verifyPrivileges($required);});
		$this->test('71_other_schema_all_privileges_fail',function()use($ledger,$foundation,$required){$fake=$this->fake($ledger,$foundation);$fake->unrelatedSchemaPrivileges=array('ALL PRIVILEGES');$this->expectCode(function()use($fake,$required){$fake->verifyPrivileges($required);},'database_privileges_insufficient');});
		$this->test('72_table_only_all_privileges_fail',function()use($ledger,$foundation,$required){$fake=$this->fake($ledger,$foundation);$fake->tablePrivileges=array('ALL PRIVILEGES');$this->expectCode(function()use($fake,$required){$fake->verifyPrivileges($required);},'database_privileges_insufficient');});
		$this->test('73_missing_one_required_privilege_fails',function()use($ledger,$foundation,$required){$fake=$this->fake($ledger,$foundation);$fake->schemaPrivileges=array_slice($required,0,-1);$this->expectCode(function()use($fake,$required){$fake->verifyPrivileges($required);},'database_privileges_insufficient');});

		$this->test('74_missing_bootstrap_record_blocks_normal_apply',function()use($loader,$ledger,$foundation){$fake=$this->fakeEmpty($ledger,$foundation);$fake->makePresent($this->config['ledger_table']);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'ledger_bootstrap_record_missing');});
		$this->test('75_ledger_descriptor_extra_step_rejected',function()use($loader,$ledger,$foundation){$modified=$ledger;$modified['expected_created_tables'][]='clinical_master_import_batches';$modified['steps'][]=$foundation['steps'][0];$m=new ClinicalSchemaMigrator($loader,null,$this->config);$this->expectCode(function()use($m,$modified){$m->plan($modified);},'ledger_descriptor_shape_invalid');});
		$this->test('76_existing_environment_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->ledger[$foundation['migration_id']]['execution_environment']='other';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});
		$this->test('77_existing_database_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->ledger[$foundation['migration_id']]['target_database']='other';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});
		$this->test('78_existing_migration_name_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->ledger[$foundation['migration_id']]['migration_name']='changed';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});
		$this->test('79_existing_statement_count_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->ledger[$foundation['migration_id']]['statement_count']=3;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});
		$this->test('80_last_completed_step_overflow',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->ledger[$foundation['migration_id']]['last_completed_step']=3;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});
		$this->test('81_resume_backup_reference_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'failed',0);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$options=$this->writeOptions();$options['backup_reference']='different-snapshot';$this->expectCode(function()use($m,$foundation,$options){$m->execute('resume',$foundation,$options);},'backup_reference_mismatch');});
		$this->test('82_resume_preserves_initial_backup_reference',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'failed',0);$before=$fake->ledger[$foundation['migration_id']]['backup_reference'];$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$m->execute('resume',$foundation,$this->writeOptions());$this->assertSame($before,$fake->ledger[$foundation['migration_id']]['backup_reference']);});
		$this->test('83_stored_server_patch_is_immutable_compatible',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->serverVersion='10.11.99';$before=$fake->ledger[$foundation['migration_id']]['server_version'];$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$m->execute('apply',$foundation,$this->writeOptions());$this->assertSame($before,$fake->ledger[$foundation['migration_id']]['server_version']);});

		$this->test('84_bootstrap_checksum_mismatch_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['migration_checksum']=str_repeat('0',64);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'applied_checksum_mismatch');});
		$this->test('85_bootstrap_state_must_be_applied',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['state']='failed';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'ledger_bootstrap_not_applied');});
		$this->test('86_bootstrap_statement_count_must_be_one',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['statement_count']=2;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});
		$this->test('87_bootstrap_completed_step_must_be_one',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['last_completed_step']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'applied_migration_incomplete');});
		$this->test('88_bootstrap_target_database_match',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['target_database']='other';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});
		$this->test('89_bootstrap_environment_match',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['execution_environment']='other';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_ledger_identity_mismatch');});

		$this->test('90_applied_noop_wrong_engine_blocks_without_state_change',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->schemas['clinical_master_import_batches']['engine']='MyISAM';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'applied_migration_schema_drift');$this->assertSame('applied',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('91_applied_noop_missing_index_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);array_pop($fake->schemas['clinical_master_import_batches']['indexes']);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'applied_migration_schema_drift');$this->assertSame('applied',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('92_applied_noop_wrong_fk_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->schemas['clinical_master_import_items']['foreign_keys'][0]['on_delete']='CASCADE';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'applied_migration_schema_drift');$this->assertSame('applied',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('93_applied_noop_wrong_check_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$fake->schemas['clinical_master_import_items']['check_constraints'][0]['expression']='1 = 1';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'applied_migration_schema_drift');$this->assertSame('applied',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('94_applied_noop_missing_table_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);unset($fake->present['clinical_master_import_items']);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'applied_migration_schema_drift');$this->assertSame('applied',$fake->ledger[$foundation['migration_id']]['state']);});

		$this->test('95_wrong_apply_preserves_applying_state',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'applying',0);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_requires_resume');$this->assertSame('applying',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('96_wrong_apply_preserves_failed_state',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'failed',0);$before=$fake->ledger[$foundation['migration_id']];$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'migration_requires_resume');$this->assertSame($before,$fake->ledger[$foundation['migration_id']]);});

		$this->test('97_zero_affected_bootstrap_insert_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fakeEmpty($ledger,$foundation);$fake->affectedOverrides['bootstrap_insert']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$ledger){$m->execute('init',$ledger,$this->writeOptions());},'ledger_state_transition_failed');});
		$this->test('98_zero_affected_bootstrap_recovery_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]=$this->ledgerRow($ledger,'failed',0);$fake->affectedOverrides['bootstrap_recovery']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$ledger){$m->execute('init',$ledger,$this->writeOptions());},'ledger_state_transition_failed');});
		$this->test('99_zero_affected_migration_insert_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->affectedOverrides['migration_insert']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'ledger_state_transition_failed');});
		$this->test('100_zero_affected_resume_transition_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'failed',0);$fake->affectedOverrides['resume_transition']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('resume',$foundation,$this->writeOptions());},'ledger_state_transition_failed');$this->assertSame('failed',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('101_zero_affected_checkpoint_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->affectedOverrides['checkpoint_update']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'ledger_state_transition_failed');$this->assertSame('failed',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('102_zero_affected_final_transition_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->affectedOverrides['final_applied_transition']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'ledger_state_transition_failed');$this->assertSame('failed',$fake->ledger[$foundation['migration_id']]['state']);});
		$this->test('103_zero_affected_failure_transition_blocks',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->failDdl=true;$fake->affectedOverrides['failure_transition']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m,$foundation){$m->execute('apply',$foundation,$this->writeOptions());},'ledger_state_transition_failed');$this->assertSame('applying',$fake->ledger[$foundation['migration_id']]['state']);});

		$this->test('104_index_prefix_mismatch_detected',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->makePresent('clinical_master_import_batches');$fake->indexOverrides['clinical_master_import_batches']['idx_clinical_import_batch_status_created']=array('sub_parts'=>array(5,null));$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->inspectExpectedSchema($foundation['steps'][0]['expected_schema']);$this->assertSame(false,$result['matches']);$this->assertTrue(in_array('index_details',$result['differences'],true));});
		$this->test('105_index_type_mismatch_detected',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->makePresent('clinical_master_import_batches');$fake->indexOverrides['clinical_master_import_batches']['idx_clinical_import_batch_status_created']=array('index_type'=>'HASH');$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->assertSame(false,$m->inspectExpectedSchema($foundation['steps'][0]['expected_schema'])['matches']);});
		$this->test('106_descending_index_mismatch_detected',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->makePresent('clinical_master_import_batches');$fake->indexOverrides['clinical_master_import_batches']['idx_clinical_import_batch_status_created']=array('collations'=>array('D','A'));$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->assertSame(false,$m->inspectExpectedSchema($foundation['steps'][0]['expected_schema'])['matches']);});
		$this->test('107_ignored_index_mismatch_detected',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->makePresent('clinical_master_import_batches');$fake->indexOverrides['clinical_master_import_batches']['idx_clinical_import_batch_status_created']=array('ignored'=>true);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->assertSame(false,$m->inspectExpectedSchema($foundation['steps'][0]['expected_schema'])['matches']);});
		$this->test('108_partitioned_table_mismatch_detected',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->makePresent('clinical_master_import_batches');$fake->tableFeatures['clinical_master_import_batches']=array('partitioned'=>true,'create_options'=>'partitioned');$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->assertSame(false,$m->inspectExpectedSchema($foundation['steps'][0]['expected_schema'])['matches']);});
		$this->test('109_data_directory_sql_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql("CREATE TABLE `clinical_schema_migrations` (`x` INT) ENGINE=InnoDB DATA DIRECTORY='/tmp';",$ledger['steps'][0]['expected_schema']);},'sql_unrepresented_table_option');});
		$this->test('110_tablespace_sql_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) ENGINE=InnoDB TABLESPACE ts1;',$ledger['steps'][0]['expected_schema']);},'sql_unrepresented_table_option');});
		$this->test('111_table_comment_sql_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql("CREATE TABLE `clinical_schema_migrations` (`x` INT) ENGINE=InnoDB COMMENT='x';",$ledger['steps'][0]['expected_schema']);},'sql_unrepresented_table_option');});
		$this->test('112_generated_column_mismatch_detected',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->makePresent('clinical_master_import_batches');$fake->schemas['clinical_master_import_batches']['columns'][0]['generation_expression']='1 + 1';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->inspectExpectedSchema($foundation['steps'][0]['expected_schema']);$this->assertSame(false,$result['matches']);$this->assertTrue(in_array('columns',$result['differences'],true));});
		$this->test('113_create_table_as_select_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) AS SELECT 1;',$ledger['steps'][0]['expected_schema']);},'sql_forbidden_construct');});
		$this->test('114_production_connection_credential_redaction',function(){$this->testProductionCredentialRedaction();});
		$this->test('115_privilege_query_is_current_user_and_target_schema_scoped',function()use($ledger,$foundation,$required){$fake=$this->fake($ledger,$foundation);$fake->schemaPrivileges=$required;$fake->verifyPrivileges($required);$surface=implode("\n",$fake->queryLog);$this->assertTrue(strpos($surface,'CURRENT_USER()')!==false);$this->assertTrue(strpos($surface,'DATABASE() LIKE TABLE_SCHEMA')!==false);$this->assertTrue(strpos($surface,'TABLE_PRIVILEGES')===false);});

		$this->test('116_check_keyword_case_equivalent',function()use($loader){$left=$this->normalizeCheckForTest($loader,"STATE IN ('applied')");$right=$this->normalizeCheckForTest($loader,"state in ('applied')");$this->assertSame($left,$right);});
		$this->test('117_check_identifier_backticks_equivalent',function()use($loader){$left=$this->normalizeCheckForTest($loader,"`state` IN ('applied')");$right=$this->normalizeCheckForTest($loader,"state in ('applied')");$this->assertSame($left,$right);});
		$this->test('118_check_status_literal_case_distinct',function()use($loader){$left=$this->normalizeCheckForTest($loader,"state IN ('applied')");$right=$this->normalizeCheckForTest($loader,"state IN ('APPLIED')");$this->assertTrue($left!==$right);});
		$this->test('119_check_regex_range_case_distinct',function()use($loader){$left=$this->normalizeCheckForTest($loader,"checksum REGEXP '^[0-9a-f]{64}$'");$right=$this->normalizeCheckForTest($loader,"checksum REGEXP '^[0-9A-F]{64}$'");$this->assertTrue($left!==$right);});
		$this->test('120_check_backtick_inside_literal_preserved',function()use($loader){$value=$this->normalizeCheckForTest($loader,"`state` = 'A`B'");$this->assertTrue(strpos($value,"'A`B'")!==false);});
		$this->test('121_check_doubled_quotes_preserved',function()use($loader){$value=$this->normalizeCheckForTest($loader,"name = 'A''B'");$this->assertTrue(strpos($value,"'A''B'")!==false);});
		$this->test('122_check_literal_whitespace_preserved',function()use($loader){$left=$this->normalizeCheckForTest($loader,"name = 'A  B'");$right=$this->normalizeCheckForTest($loader,"name='A B'");$this->assertTrue($left!==$right);$this->assertTrue(strpos($left,"'A  B'")!==false);});
		$this->test('123_existing_canonical_check_fingerprints_pass',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);foreach(array($ledger['steps'][0],$foundation['steps'][0],$foundation['steps'][1]) as $step){$this->assertSame(true,$m->inspectExpectedSchema($step['expected_schema'])['matches']);}});
		$this->test('124_unclosed_check_literal_rejected',function()use($loader){$this->expectCode(function()use($loader){$this->normalizeCheckForTest($loader,"state = 'applied");},'check_expression_quote_unclosed');});

		$this->test('125_status_exact_table_without_bootstrap',function()use($loader,$ledger,$foundation){$fake=$this->fakeEmpty($ledger,$foundation);$fake->makePresent($this->config['ledger_table']);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(true,$result['ledger_schema_valid']);$this->assertSame(false,$result['bootstrap_record_present']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertStatusReadOnly($fake);});
		$this->test('126_status_bootstrap_checksum_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['migration_checksum']=str_repeat('0',64);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(true,$result['bootstrap_record_present']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertSame('applied_checksum_mismatch',$result['bootstrap_validation_error']);$this->assertStatusReadOnly($fake);});
		$this->test('127_status_bootstrap_not_applied',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]=$this->ledgerRow($ledger,'failed',1);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertSame('ledger_bootstrap_not_applied',$result['bootstrap_validation_error']);$this->assertStatusReadOnly($fake);});
		$this->test('128_status_bootstrap_environment_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['execution_environment']='other';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertSame('migration_ledger_identity_mismatch',$result['bootstrap_validation_error']);$this->assertStatusReadOnly($fake);});
		$this->test('129_status_bootstrap_database_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['target_database']='other';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertSame('migration_ledger_identity_mismatch',$result['bootstrap_validation_error']);$this->assertStatusReadOnly($fake);});
		$this->test('130_status_bootstrap_statement_count_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['statement_count']=2;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertStatusReadOnly($fake);});
		$this->test('131_status_bootstrap_completed_step_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['last_completed_step']=0;$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertSame('applied_migration_incomplete',$result['bootstrap_validation_error']);$this->assertStatusReadOnly($fake);});
		$this->test('132_status_valid_bootstrap_initialized',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(true,$result['ledger_initialized']);$this->assertSame(true,$result['ledger_schema_valid']);$this->assertSame(true,$result['bootstrap_record_present']);$this->assertSame(true,$result['bootstrap_record_valid']);$this->assertSame(null,$result['bootstrap_validation_error']);$this->assertStatusReadOnly($fake);});
		$this->test('133_status_bootstrap_name_mismatch',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->ledger[$ledger['migration_id']]['migration_name']='other';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->status($this->writeOptions());$this->assertSame(false,$result['ledger_initialized']);$this->assertSame(false,$result['bootstrap_record_valid']);$this->assertStatusReadOnly($fake);});
		$this->test('134_status_mismatched_ledger_schema_blocks_read_only',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);$fake->schemas[$this->config['ledger_table']]['engine']='MyISAM';$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$this->expectCode(function()use($m){$m->status($this->writeOptions());},'ledger_schema_mismatch');$this->assertStatusReadOnly($fake);});
		$this->test('135_check_backslash_escape_preserved',function()use($loader){$value=$this->normalizeCheckForTest($loader,"name = 'A\\'B'");$this->assertTrue(strpos($value,"'A\\'B'")!==false);});
		$this->test('136_fingerprint_detects_status_literal_case_change',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);foreach($fake->schemas[$this->config['ledger_table']]['check_constraints'] as &$check){if($check['name']==='chk_clinical_schema_state'){$check['expression']=str_replace("'applied'","'APPLIED'",$check['expression']);}}unset($check);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->inspectExpectedSchema($ledger['steps'][0]['expected_schema']);$this->assertSame(false,$result['matches']);$this->assertTrue(in_array('check_constraints',$result['differences'],true));});
		$this->test('137_fingerprint_detects_regex_range_case_change',function()use($loader,$ledger,$foundation){$fake=$this->fake($ledger,$foundation);foreach($fake->schemas[$this->config['ledger_table']]['check_constraints'] as &$check){if($check['name']==='chk_clinical_schema_checksum'){$check['expression']=str_replace('[0-9a-f]','[0-9A-F]',$check['expression']);}}unset($check);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->inspectExpectedSchema($ledger['steps'][0]['expected_schema']);$this->assertSame(false,$result['matches']);$this->assertTrue(in_array('check_constraints',$result['differences'],true));});

		$this->test('138_mysql_executable_comment_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*! ROW_FORMAT=COMPRESSED */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('139_mariadb_uppercase_executable_comment_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*M! ROW_FORMAT=COMPRESSED */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('140_mariadb_lowercase_executable_comment_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*m! ROW_FORMAT=COMPRESSED */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('141_mariadb_uppercase_version_comment_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*M!100000 ROW_FORMAT=COMPRESSED */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('142_mariadb_lowercase_version_comment_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*m!100000 ROW_FORMAT=COMPRESSED */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('143_mariadb_comment_hidden_partition_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) ENGINE=InnoDB /*M! PARTITION BY HASH (`x`) PARTITIONS 2 */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('144_mariadb_comment_hidden_ctas_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*M! AS SELECT 1 */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('145_mariadb_comment_hidden_dml_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) /*M! INSERT INTO other VALUES (1) */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('146_mariadb_comment_between_create_and_table_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE /*M!100000 SET @x=1 */ TABLE `clinical_schema_migrations` (`x` INT);',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('147_mariadb_comment_after_table_definition_rejected',function()use($loader,$ledger){$this->expectCode(function()use($loader,$ledger){$loader->validateSql('CREATE TABLE `clinical_schema_migrations` (`x` INT) ENGINE=InnoDB /*m!100000 ROW_FORMAT=COMPRESSED */;',$ledger['steps'][0]['expected_schema']);},'sql_executable_comment_rejected');});
		$this->test('148_ordinary_block_comment_accepted',function()use($loader){$loader->validateSql('/* normal documentation comment */ CREATE TABLE `ordinary_comment_table` (`x` INT) ENGINE=InnoDB;',$this->minimalSchema('ordinary_comment_table'));});
		$this->test('149_ordinary_comment_semicolon_is_not_statement',function()use($loader){$scan=$loader->scanSql('/* documentation; still one statement */ CREATE TABLE `ordinary_semicolon_table` (`x` INT) ENGINE=InnoDB;');$this->assertSame(1,$scan['statement_count']);$loader->validateSql('/* documentation; still one statement */ CREATE TABLE `ordinary_semicolon_table` (`x` INT) ENGINE=InnoDB;',$this->minimalSchema('ordinary_semicolon_table'));});
		$this->test('150_canonical_sql_still_validates_after_comment_hardening',function()use($loader,$ledger,$foundation){foreach(array($ledger,$foundation) as $descriptor){$again=$loader->loadById($descriptor['migration_id']);$this->assertSame($descriptor['checksum'],$again['checksum']);}});

		$this->test('151_exact_schema_grant_pattern_passes',function()use($required){$connection=$this->schemaPrivilegeConnection('doclinkclinical','doclinkclinical',$required);$connection->verifyPrivileges($required);});
		$this->test('152_escaped_underscore_schema_grant_pattern_passes',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_clinical_schema_test','doclink\\_clinical\\_schema\\_test',$required);$connection->verifyPrivileges($required);});
		$this->test('153_escaped_percent_schema_grant_pattern_passes',function()use($required){$connection=$this->schemaPrivilegeConnection('archive%2026','archive\\%2026',$required);$connection->verifyPrivileges($required);});
		$this->test('154_percent_wildcard_schema_grant_pattern_passes',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_clinical','doclink%',$required);$connection->verifyPrivileges($required);});
		$this->test('155_underscore_wildcard_schema_grant_pattern_passes',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_x','doclink_%',$required);$connection->verifyPrivileges($required);});
		$this->test('156_unrelated_schema_grant_pattern_fails',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_clinical','archive%',$required);$this->expectCode(function()use($connection,$required){$connection->verifyPrivileges($required);},'database_privileges_insufficient');});
		$this->test('157_near_prefix_schema_grant_pattern_fails',function()use($required){$connection=$this->schemaPrivilegeConnection('doclinkclinicalextra','doclinkclinical',$required);$this->expectCode(function()use($connection,$required){$connection->verifyPrivileges($required);},'database_privileges_insufficient');});
		$this->test('158_escaped_backslash_schema_grant_pattern_passes',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink\\archive','doclink\\\\archive',$required);$connection->verifyPrivileges($required);});
		$this->test('159_table_only_privileges_fail_production_method',function()use($required){$connection=new PrivilegeMetadataTestConnection();$connection->tableGrantRows=$this->privilegeGrantRows($required,$connection->currentGrantee);$this->expectCode(function()use($connection,$required){$connection->verifyPrivileges($required);},'database_privileges_insufficient');});
		$this->test('160_global_privileges_pass_production_method',function()use($required){$connection=new PrivilegeMetadataTestConnection();$connection->globalGrantRows=$this->privilegeGrantRows($required,$connection->currentGrantee);$connection->verifyPrivileges($required);});
		$this->test('161_mixed_global_and_matching_schema_privileges_pass',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_clinical_schema_test','doclink\\_clinical\\_schema\\_test',array_slice($required,3));$connection->globalGrantRows=$this->privilegeGrantRows(array_slice($required,0,3),$connection->currentGrantee);$connection->verifyPrivileges($required);});
		$this->test('162_missing_one_required_privilege_has_safe_count',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_clinical_schema_test','doclink\\_clinical\\_schema\\_test',array_slice($required,0,-1));try{$connection->verifyPrivileges($required);}catch(ClinicalSchemaException $error){$this->assertSame('database_privileges_insufficient',$error->getSafeCode());$this->assertSame(array('missing_count'=>1),$error->getSafeContext());return;}throw new ClinicalSchemaTestFailure('expected_exception');});
		$this->test('163_wrong_grantee_fails_production_method',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_clinical_schema_test','doclink\\_clinical\\_schema\\_test',$required,"'other_user'@'%'");$this->expectCode(function()use($connection,$required){$connection->verifyPrivileges($required);},'database_privileges_insufficient');});
		$this->test('164_production_privilege_query_like_escape_shape',function()use($required){$connection=$this->schemaPrivilegeConnection('doclink_clinical_schema_test','doclink\\_clinical\\_schema\\_test',$required);$connection->verifyPrivileges($required);$surface=implode("\n",$connection->queryLog);$this->assertTrue(preg_match('/TABLE_SCHEMA\\s*=\\s*DATABASE\\(\\)/i',$surface)!==1);$this->assertTrue(strpos($surface,"DATABASE() LIKE TABLE_SCHEMA ESCAPE '\\\\'")!==false);$this->assertTrue(strpos($surface,'GRANTEE=CONCAT(QUOTE(LEFT(CURRENT_USER()')!==false);$this->assertTrue(strpos($surface,'doclink_clinical_schema_test')===false);});
		$this->test('165_privilege_metadata_sources_remain_safe',function()use($required){$connection=new PrivilegeMetadataTestConnection();$connection->globalGrantRows=$this->privilegeGrantRows($required,$connection->currentGrantee);$connection->verifyPrivileges($required);$surface=implode("\n",$connection->queryLog);$this->assertTrue(strpos($surface,'information_schema.USER_PRIVILEGES')!==false);$this->assertTrue(strpos($surface,'information_schema.SCHEMA_PRIVILEGES')!==false);$this->assertTrue(stripos($surface,'SHOW GRANTS')===false);$this->assertTrue(stripos($surface,'mysql.db')===false);$this->assertTrue(strpos($surface,'TABLE_PRIVILEGES')===false);$this->assertTrue(strpos($surface,'COLUMN_PRIVILEGES')===false);});

		$this->test('166_php_null_default_normalizes_to_null',function(){$this->assertSame(null,$this->normalizeDefaultForTest(null));});
		$this->test('167_unquoted_uppercase_null_default_normalizes_to_null',function(){$this->assertSame(null,$this->normalizeDefaultForTest('NULL'));});
		$this->test('168_unquoted_lowercase_null_default_normalizes_to_null',function(){$this->assertSame(null,$this->normalizeDefaultForTest('null'));});
		$this->test('169_unquoted_mixed_case_null_default_normalizes_to_null',function(){$this->assertSame(null,$this->normalizeDefaultForTest('NuLl'));});
		$this->test('170_quoted_uppercase_null_default_remains_string',function(){$this->assertSame('NULL',$this->normalizeDefaultForTest("'NULL'"));});
		$this->test('171_quoted_lowercase_null_default_remains_string',function(){$this->assertSame('null',$this->normalizeDefaultForTest("'null'"));});
		$this->test('172_quoted_state_default_remains_string',function(){$this->assertSame('applying',$this->normalizeDefaultForTest("'applying'"));});
		$this->test('173_quoted_default_preserves_case',function(){$this->assertSame('MiXeD',$this->normalizeDefaultForTest("'MiXeD'"));});
		$this->test('174_quoted_default_decodes_doubled_quotes',function(){$this->assertSame("O'Brien",$this->normalizeDefaultForTest("'O''Brien'"));});
		$this->test('175_current_timestamp_default_normalizes_case',function(){$this->assertSame('current_timestamp',$this->normalizeDefaultForTest('CURRENT_TIMESTAMP'));});
		$this->test('176_current_timestamp_precision_default_normalizes_case',function(){$this->assertSame('current_timestamp(6)',$this->normalizeDefaultForTest('CURRENT_TIMESTAMP(6)'));});
		$this->test('177_ledger_expected_schema_unchanged_by_inspection',function()use($loader,$ledger,$foundation){$expected=$ledger['steps'][0]['expected_schema'];$before=$expected;$fake=$this->fakeApplied($ledger,$foundation);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$m->inspectExpectedSchema($expected);$this->assertSame($before,$expected);});
		$this->test('178_mariadb_string_null_metadata_matches_ledger_schema',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);foreach($fake->schemas[$this->config['ledger_table']]['columns'] as &$column){if(in_array($column['name'],array('applied_at','failed_at','error_code','error_summary'),true)){$column['default']='NULL';}}unset($column);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->inspectExpectedSchema($ledger['steps'][0]['expected_schema']);$this->assertSame(true,$result['matches']);$this->assertSame(array(),$result['differences']);});
		$this->test('179_quoted_null_string_does_not_match_sql_null',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);foreach($fake->schemas[$this->config['ledger_table']]['columns'] as &$column){if($column['name']==='applied_at'){$column['default']="'NULL'";}}unset($column);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->inspectExpectedSchema($ledger['steps'][0]['expected_schema']);$this->assertSame(false,$result['matches']);$this->assertTrue(in_array('columns',$result['differences'],true));});
		$this->test('180_real_default_mismatch_remains_columns_difference',function()use($loader,$ledger,$foundation){$fake=$this->fakeApplied($ledger,$foundation);foreach($fake->schemas[$this->config['ledger_table']]['columns'] as &$column){if($column['name']==='state'){$column['default']="'unexpected'";}}unset($column);$m=new ClinicalSchemaMigrator($loader,$fake,$this->config);$result=$m->inspectExpectedSchema($ledger['steps'][0]['expected_schema']);$this->assertSame(false,$result['matches']);$this->assertTrue(in_array('columns',$result['differences'],true));});
		$this->test('181_integration_environment_snapshot_restores_original',function(){$name=$this->config['connection_environment_variables']['host'];$expected=$this->integrationEnvironment[$name];putenv($name.'=temporary-environment-mutation');$this->restoreIntegrationEnvironment();$this->assertSame($expected,getenv($name));});
		$this->test('182_quoted_current_timestamp_remains_literal_case',function(){$this->assertSame('CURRENT_TIMESTAMP',$this->normalizeDefaultForTest("'CURRENT_TIMESTAMP'"));});
	}

	private function runIntegrationTests()
	{
		$names = $this->config['connection_environment_variables'];
		$requiredNames = array_values($names);
		foreach ($requiredNames as $requiredName) {
			$value = getenv($requiredName);
			if (!is_string($value) || $value === '') {
				$this->skip('I01_disposable_database_name_gate','disposable_integration_environment_absent');
				$this->skip('I02_disposable_mariadb_privilege_preflight','disposable_integration_environment_absent');
				return;
			}
		}
		$database = getenv($names['database']);
		$user = getenv($names['user']);
		$safeDatabase = is_string($database) && strcasecmp($database,'doclinc-staging')!==0 && strpos($database,'doclink_clinical_schema_test_')===0 && strpos($database,'_')!==false;
		$safeUser = is_string($user) && !in_array(strtolower($user),array_map('strtolower',$this->config['hard_rejected_database_users']),true);
		$this->test('I01_disposable_database_name_gate', function () use ($safeDatabase,$safeUser) {
			$this->assertTrue($safeDatabase && $safeUser);
		});
		if (!$safeDatabase || !$safeUser) {
			$this->skip('I02_disposable_mariadb_privilege_preflight','disposable_identity_gate_failed');
			return;
		}
		$this->test('I02_disposable_mariadb_privilege_preflight', function () use ($database) {
			$config=$this->config;
			$connection=ClinicalSchemaConnection::fromEnvironment($config,$database,'10.11.0',true);
			try {
				$this->assertSame($database,$connection->getDatabaseName());
				$this->assertTrue(version_compare($connection->getServerVersion(),'10.11.0','>='));
			} finally {
				$connection->close();
			}
		});
	}

	private function restoreIntegrationEnvironment()
	{
		foreach ($this->integrationEnvironment as $name => $value) {
			if ($value === false) {
				putenv($name);
			} else {
				putenv($name . '=' . $value);
			}
		}
	}

	private function schemaPrivilegeConnection($database, $pattern, array $privileges, $grantee = null)
	{
		$connection = new PrivilegeMetadataTestConnection();
		$connection->database = $database;
		$grantGrantee = $grantee === null ? $connection->currentGrantee : $grantee;
		$connection->schemaGrantRows = $this->privilegeGrantRows($privileges,$grantGrantee,$pattern);
		return $connection;
	}

	private function privilegeGrantRows(array $privileges, $grantee, $pattern = null)
	{
		$rows = array();
		foreach ($privileges as $privilege) {
			$row = array('GRANTEE'=>$grantee,'PRIVILEGE_TYPE'=>$privilege);
			if ($pattern !== null) {
				$row['TABLE_SCHEMA'] = $pattern;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	private function fake(array $ledger, array $foundation)
	{
		$fake=$this->fakeEmpty($ledger,$foundation);
		$fake->makePresent($this->config['ledger_table']);
		$fake->ledger[$ledger['migration_id']]=$this->ledgerRow($ledger,'applied',1);
		return $fake;
	}

	private function fakeEmpty(array $ledger, array $foundation)
	{
		$fake=new FakeClinicalSchemaConnection();$fake->addDescriptorSchemas($ledger);$fake->addDescriptorSchemas($foundation);return $fake;
	}

	private function fakeApplied(array $ledger, array $foundation)
	{
		$fake=$this->fake($ledger,$foundation);foreach(array_merge($ledger['expected_created_tables'],$foundation['expected_created_tables']) as $table){$fake->makePresent($table);} $fake->ledger[$ledger['migration_id']]=$this->ledgerRow($ledger,'applied',1);$fake->ledger[$foundation['migration_id']]=$this->ledgerRow($foundation,'applied',2);return $fake;
	}

	private function ledgerRow(array $descriptor,$state,$completed)
	{
		return array(
			'migration_id'=>$descriptor['migration_id'],
			'migration_name'=>$descriptor['migration_name'],
			'migration_checksum'=>$descriptor['checksum'],
			'state'=>$state,
			'attempt_count'=>1,
			'statement_count'=>count($descriptor['steps']),
			'last_completed_step'=>$completed,
			'execution_environment'=>'unit_test',
			'target_database'=>'clinical_schema_unit',
			'server_version'=>'10.11.14',
			'tool_version'=>'1.0.0',
			'executor_identity'=>'db-account-sha256:'.str_repeat('a',64),
			'backup_reference'=>'unit-test-snapshot',
			'started_at'=>'2026-07-18 00:00:00.000000',
			'applied_at'=>$state==='applied'?'2026-07-18 00:00:01.000000':null,
			'failed_at'=>$state==='failed'?'2026-07-18 00:00:01.000000':null,
		);
	}

	private function writeOptions()
	{
		return array('environment'=>'unit_test','confirm_database'=>'clinical_schema_unit','backup_reference'=>'unit-test-snapshot','confirm_backup'=>true);
	}

	private function test($name, callable $callback)
	{
		try {$callback();$this->passed++;$this->results[]=$name.'=PASS';}
		catch(Throwable $error){$this->failed++;$code=$error instanceof ClinicalSchemaException?$error->getSafeCode():get_class($error);$this->results[]=$name.'=FAIL:'.$code;}
	}

	private function skip($name,$reason)
	{
		$this->skipped++;$this->results[]=$name.'=SKIP:'.$reason;
	}

	private function expectCode(callable $callback,$expected)
	{
		try{$callback();}catch(ClinicalSchemaException $error){$this->assertSame($expected,$error->getSafeCode());return;}throw new ClinicalSchemaTestFailure('expected_exception');
	}

	private function assertSame($expected,$actual)
	{
		if($expected!==$actual){throw new ClinicalSchemaTestFailure('assert_same');}
	}

	private function assertTrue($value)
	{
		if($value!==true){throw new ClinicalSchemaTestFailure('assert_true');}
	}

	private function fixtureTest($name,callable $mutation,$expectedCode,$migrationId=null)
	{
		$this->test($name,function()use($mutation,$expectedCode,$migrationId){$root=$this->fixture();try{$mutation($root);$loader=new ClinicalSchemaDescriptor($root);$id=$migrationId?:$this->config['ledger_migration_id'];$this->expectCode(function()use($loader,$id){$loader->loadById($id);},$expectedCode);}finally{$this->removeTree($root);}});
	}

	private function testSymlinkEscape()
	{
		$name='21_escaped_sql_symlink';$root=$this->fixture();$outside=tempnam(sys_get_temp_dir(),'clinical_sql_');file_put_contents($outside,'CREATE TABLE `clinical_schema_migrations` (`x` INT);');$link=$root.'/sql/00000000000000/099_escape.sql';
		$created=function_exists('symlink')?@symlink($outside,$link):false;
		if(!$created){$this->skip($name,'symlink_unavailable');$this->removeTree($root);@unlink($outside);return;}
		$this->test($name,function()use($root,$outside){try{$this->mutateJson($root,$this->config['ledger_migration_id'],function(&$json){$json['steps'][0]['sql_file']='sql/00000000000000/099_escape.sql';});$loader=new ClinicalSchemaDescriptor($root);$this->expectCode(function()use($loader){$loader->loadById($this->config['ledger_migration_id']);},'sql_path_unsafe');}finally{$this->removeTree($root);@unlink($outside);}});
	}

	private function testChecksumNewlines($expected)
	{
		$this->test('31_crlf_lf_checksum_equivalence',function()use($expected){$root=$this->fixture();try{foreach($this->fixtureSourceFiles($root) as $path){$bytes=file_get_contents($path);$bytes=str_replace(array("\r\n","\r"),"\n",$bytes);file_put_contents($path,str_replace("\n","\r\n",$bytes));}$loader=new ClinicalSchemaDescriptor($root);$actual=$loader->loadById($this->config['ledger_migration_id']);$this->assertSame($expected,$actual['checksum']);}finally{$this->removeTree($root);}});
	}

	private function testChecksumMutation($name,callable $mutation)
	{
		$this->test($name,function()use($mutation){$root=$this->fixture();try{$loader=new ClinicalSchemaDescriptor($root);$before=$loader->loadById($this->config['ledger_migration_id'])['checksum'];$mutation($root);$loader=new ClinicalSchemaDescriptor($root);$after=$loader->loadById($this->config['ledger_migration_id'])['checksum'];$this->assertTrue($before!==$after);}finally{$this->removeTree($root);}});
	}

	private function testChecksumOrder()
	{
		$this->test('34_sql_order_changes_checksum',function(){$root=$this->fixture();try{$id='20260718000100_clinical_import_audit_foundation';$loader=new ClinicalSchemaDescriptor($root);$before=$loader->loadById($id)['checksum'];$this->mutateJson($root,$id,function(&$json){$json['steps']=array_reverse($json['steps']);});$loader=new ClinicalSchemaDescriptor($root);$after=$loader->loadById($id)['checksum'];$this->assertTrue($before!==$after);}finally{$this->removeTree($root);}});
	}

	private function testProductionCredentialRedaction()
	{
		$marker='r1-secret-'.bin2hex(random_bytes(8));
		$host='host-'.$marker;
		$user='user_'.$marker;
		$password='password-'.$marker;
		$database='db_'.$marker;
		$environment=array();
		foreach(array('PATH','Path','SystemRoot','SYSTEMROOT','COMSPEC','TEMP','TMP') as $inheritedName){$inherited=getenv($inheritedName);if($inherited!==false){$environment[$inheritedName]=$inherited;}}
		$environment['DOCLINK_CLINICAL_SCHEMA_DB_HOST']=$host;
		$environment['DOCLINK_CLINICAL_SCHEMA_DB_PORT']='invalid-port';
		$environment['DOCLINK_CLINICAL_SCHEMA_DB_NAME']=$database;
		$environment['DOCLINK_CLINICAL_SCHEMA_DB_USER']=$user;
		$environment['DOCLINK_CLINICAL_SCHEMA_DB_PASSWORD']=$password;
		$environment['DOCLINK_CLINICAL_SCHEMA_ALLOWED_USERS']=$user;
		$command=array(PHP_BINARY,$this->root.'/clinical_schema.php','status','--format=json','--environment=unit_test','--confirm-database='.$database);
		$specification=array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w'));
		$process=proc_open($command,$specification,$pipes,null,$environment);
		if(!is_resource($process)){throw new ClinicalSchemaTestFailure('proc_open_failed');}
		fclose($pipes[0]);
		$stdout=stream_get_contents($pipes[1]);
		$stderr=stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit=proc_close($process);
		$this->assertSame(2,$exit);
		$combined=$stdout.$stderr;
		foreach(array($marker,$host,$user,$password,$database) as $sensitive){$this->assertTrue(strpos($combined,$sensitive)===false);}
		$decoded=json_decode($stdout,true);
		$this->assertTrue(is_array($decoded));
		$this->assertSame('ERROR',$decoded['validation_result']);
	}

	private function normalizeCheckForTest(ClinicalSchemaDescriptor $loader, $expression)
	{
		$migrator=new ClinicalSchemaMigrator($loader,null,$this->config);
		$method=new ReflectionMethod('ClinicalSchemaMigrator','normalizeCheck');
		$method->setAccessible(true);
		return $method->invoke($migrator,$expression);
	}

	private function normalizeDefaultForTest($value)
	{
		$class=new ReflectionClass('ClinicalSchemaMigrator');
		$migrator=$class->newInstanceWithoutConstructor();
		$method=new ReflectionMethod('ClinicalSchemaMigrator','normalizeDefault');
		$method->setAccessible(true);
		return $method->invoke($migrator,$value);
	}

	private function assertStatusReadOnly(FakeClinicalSchemaConnection $fake)
	{
		$this->assertSame(0,$fake->ddlCount);
		$this->assertSame(0,$fake->ledgerWriteCount);
		$this->assertSame(0,$fake->lockAcquireCount);
	}

	private function minimalSchema($table)
	{
		return array('table_name'=>$table,'engine'=>'InnoDB','default_charset'=>'utf8mb4','default_collation'=>'utf8mb4_general_ci','columns'=>array(),'primary_key'=>array(),'unique_indexes'=>array(),'indexes'=>array(),'foreign_keys'=>array(),'check_constraints'=>array());
	}

	private function fixture()
	{
		$root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'doclink_clinical_schema_'.bin2hex(random_bytes(8));mkdir($root,0700,true);$this->copyTree($this->migrationRoot,$root);return $root;
	}

	private function mutateJson($root,$id,callable $mutation)
	{
		$path=$root.'/'.$id.'.json';$json=json_decode(file_get_contents($path),true);$mutation($json);file_put_contents($path,json_encode($json,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
	}

	private function fixtureSourceFiles($root)
	{
		$files=array();$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));foreach($iterator as $file){if($file->isFile()){$files[]=$file->getPathname();}}return $files;
	}

	private function copyTree($source,$target)
	{
		$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);foreach($iterator as $item){$destination=$target.DIRECTORY_SEPARATOR.$iterator->getSubPathName();if($item->isDir()){if(!is_dir($destination)){mkdir($destination,0700,true);}}else{copy($item->getPathname(),$destination);}}
	}

	private function removeTree($root)
	{
		if(!is_dir($root)){return;}$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $item){$path=$item->getPathname();if($item->isLink()||$item->isFile()){@unlink($path);}else{@rmdir($path);}}@rmdir($root);
	}

	private function allSource()
	{
		$text='';$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root,FilesystemIterator::SKIP_DOTS));foreach($iterator as $file){if(strpos(str_replace('\\','/',$file->getPathname()),'/tests/')!==false){continue;}if($file->isFile()&&in_array(strtolower($file->getExtension()),array('php','json','sql'),true)){$text.=file_get_contents($file->getPathname());}}return $text;
	}
}

$integration=in_array('--integration',$argv,true);
$runner=new ClinicalSchemaTestRunner();
exit($runner->run($integration));
