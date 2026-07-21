<?php

class ClinicalSchemaDescriptor
{
	private $migrationRoot;

	public function __construct($migrationRoot)
	{
		$resolved = realpath($migrationRoot);
		if ($resolved === false || !is_dir($resolved) || !is_readable($resolved)) {
			throw new ClinicalSchemaException('migration_directory_unavailable', 'Migration directory is unavailable.');
		}
		$this->migrationRoot = rtrim($resolved, DIRECTORY_SEPARATOR);
	}

	public function getMigrationRoot()
	{
		return $this->migrationRoot;
	}

	public function loadById($migrationId)
	{
		if (preg_match('/^[0-9]{14}_[a-z0-9_]+$/', (string) $migrationId) !== 1) {
			throw new ClinicalSchemaException('invalid_migration_id', 'Migration ID format is invalid.');
		}

		$matches = glob($this->migrationRoot . DIRECTORY_SEPARATOR . $migrationId . '.json');
		if ($matches === false || count($matches) !== 1) {
			throw new ClinicalSchemaException('migration_descriptor_not_found', 'Exactly one migration descriptor is required.', array('migration_id' => $migrationId));
		}

		return $this->loadFile($matches[0]);
	}

	public function loadCatalog()
	{
		$paths = glob($this->migrationRoot . DIRECTORY_SEPARATOR . '*.json');
		if ($paths === false) {
			throw new ClinicalSchemaException('migration_catalog_unavailable', 'Migration descriptor catalog is unavailable.');
		}
		sort($paths, SORT_STRING);
		$catalog = array();
		foreach ($paths as $path) {
			$descriptor = $this->loadFile($path);
			if (isset($catalog[$descriptor['migration_id']])) {
				throw new ClinicalSchemaException('migration_catalog_duplicate', 'Migration descriptor catalog contains a duplicate migration ID.');
			}
			$catalog[$descriptor['migration_id']] = $descriptor;
		}
		return $catalog;
	}

	public function loadFile($path)
	{
		$resolved = $this->resolveContainedFile($path, 'descriptor_path_unsafe');
		if (strtolower(pathinfo($resolved, PATHINFO_EXTENSION)) !== 'json') {
			throw new ClinicalSchemaException('descriptor_extension_invalid', 'Migration descriptors must be JSON files.');
		}

		$descriptorBytes = $this->readUtf8Source($resolved, 'descriptor');
		$typed = json_decode($descriptorBytes);
		$typedError = json_last_error();
		$assoc = json_decode($descriptorBytes, true);
		$assocError = json_last_error();
		if ($typedError !== JSON_ERROR_NONE || $assocError !== JSON_ERROR_NONE) {
			throw new ClinicalSchemaException('descriptor_json_invalid', 'Migration descriptor JSON is invalid.');
		}
		if (!is_object($typed) || !is_array($assoc)) {
			throw new ClinicalSchemaException('descriptor_root_not_object', 'Migration descriptor root must be an object.');
		}

		$this->assertAllowedAndRequiredFields($assoc, array(
			'migration_id', 'migration_name', 'description', 'minimum_mariadb_version',
			'schema_only', 'imports_package_data', 'enables_runtime',
			'expected_created_tables', 'expected_modified_tables', 'required_applied_migrations',
			'preconditions', 'steps',
		), array(
			'migration_id', 'migration_name', 'description', 'minimum_mariadb_version',
			'schema_only', 'imports_package_data', 'enables_runtime',
			'expected_created_tables', 'steps',
		), 'descriptor');

		foreach (array('migration_id', 'migration_name', 'description', 'minimum_mariadb_version') as $field) {
			$this->assertNonEmptyString($assoc, $field, 'descriptor');
		}
		if (preg_match('/^[0-9]{14}_[a-z0-9_]+$/', $assoc['migration_id']) !== 1) {
			throw new ClinicalSchemaException('descriptor_migration_id_invalid', 'Descriptor migration ID is invalid.');
		}
		$expectedFilename = $assoc['migration_id'] . '.json';
		if (basename($resolved) !== $expectedFilename) {
			throw new ClinicalSchemaException('descriptor_filename_mismatch', 'Descriptor filename must equal its migration ID.');
		}
		if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $assoc['minimum_mariadb_version']) !== 1) {
			throw new ClinicalSchemaException('descriptor_version_invalid', 'Minimum MariaDB version is invalid.');
		}
		foreach (array('schema_only', 'imports_package_data', 'enables_runtime') as $field) {
			if (!is_bool($assoc[$field])) {
				throw new ClinicalSchemaException('descriptor_boolean_invalid', 'Descriptor policy flags must be booleans.', array('field' => $field));
			}
		}
		if ($assoc['schema_only'] !== true || $assoc['imports_package_data'] !== false || $assoc['enables_runtime'] !== false) {
			throw new ClinicalSchemaException('descriptor_runtime_policy_rejected', 'Migration descriptor violates schema-only policy.');
		}

		if (!is_array($typed->expected_created_tables) || !is_array($assoc['expected_created_tables'])) {
			throw new ClinicalSchemaException('expected_tables_not_array', 'Expected created tables must be an array.');
		}
		$expectedTables = array();
		foreach ($assoc['expected_created_tables'] as $table) {
			if (!is_string($table) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table) !== 1 || isset($expectedTables[$table])) {
				throw new ClinicalSchemaException('expected_table_invalid', 'Expected table names must be unique safe identifiers.');
			}
			$expectedTables[$table] = true;
		}
		$expectedModifiedTables = $this->validateIdentifierArray(
			isset($assoc['expected_modified_tables']) ? $assoc['expected_modified_tables'] : array(),
			isset($typed->expected_modified_tables) ? $typed->expected_modified_tables : array(),
			'expected_modified_tables'
		);
		foreach ($expectedModifiedTables as $table => $unused) {
			if (isset($expectedTables[$table])) {
				throw new ClinicalSchemaException('descriptor_table_membership_overlap', 'Created and modified table membership may not overlap.', array('table_name' => $table));
			}
		}

		$requiredAppliedMigrations = $this->validateRequiredMigrations(
			isset($assoc['required_applied_migrations']) ? $assoc['required_applied_migrations'] : array(),
			isset($typed->required_applied_migrations) ? $typed->required_applied_migrations : array(),
			$assoc['migration_id']
		);
		$preconditions = $this->validatePreconditions(
			isset($assoc['preconditions']) ? $assoc['preconditions'] : array('tables_must_be_empty' => array()),
			isset($typed->preconditions) ? $typed->preconditions : (object) array('tables_must_be_empty' => array())
		);

		if (!is_array($typed->steps) || !is_array($assoc['steps']) || count($assoc['steps']) < 1) {
			throw new ClinicalSchemaException('descriptor_steps_not_array', 'Descriptor steps must be a non-empty array.');
		}

		$stepIds = array();
		$stepTables = array();
		$createTables = array();
		$modifiedTables = array();
		$stepFamilies = array();
		$steps = array();
		foreach ($assoc['steps'] as $index => $step) {
			if (!isset($typed->steps[$index]) || !is_object($typed->steps[$index]) || !is_array($step)) {
				throw new ClinicalSchemaException('descriptor_step_not_object', 'Every descriptor step must be an object.', array('step_index' => $index));
			}
			if (!isset($step['type']) || !is_string($step['type'])) {
				throw new ClinicalSchemaException('step_string_invalid', 'Required string is invalid.', array('field' => 'type'));
			}
			$type = $step['type'];
			if ($type === 'create_table') {
				$this->assertExactFields($step, array('step_id', 'type', 'sql_file', 'expected_schema'), 'step');
			} elseif ($type === 'add_columns') {
				$this->assertExactFields($step, array(
					'step_id', 'type', 'sql_file', 'table_name', 'schema_lineage',
					'added_column_names', 'added_check_names', 'expected_before_schema', 'expected_after_schema',
				), 'step');
			} elseif ($type === 'modify_column') {
				$this->assertExactFields($step, array(
					'step_id', 'type', 'sql_file', 'table_name', 'schema_lineage',
					'modified_column_name', 'expected_before_schema', 'expected_after_schema',
				), 'step');
			} else {
				throw new ClinicalSchemaException('descriptor_step_type_unknown', 'Unsupported migration step type.', array('step_id' => isset($step['step_id']) ? $step['step_id'] : null));
			}
			foreach (array('step_id', 'type', 'sql_file') as $field) {
				$this->assertNonEmptyString($step, $field, 'step');
			}
			if (preg_match('/^[0-9]{3}_[a-z0-9_]+$/', $step['step_id']) !== 1 || isset($stepIds[$step['step_id']])) {
				throw new ClinicalSchemaException('descriptor_step_id_invalid', 'Step IDs must be unique safe identifiers.', array('step_index' => $index));
			}
			$stepIds[$step['step_id']] = true;
			$stepFamilies[$type === 'create_table' ? 'create' : 'mutation'] = true;
			if ($type === 'create_table') {
				if (!is_object($typed->steps[$index]->expected_schema) || !is_array($step['expected_schema'])) {
					throw new ClinicalSchemaException('expected_schema_not_object', 'Expected schema must be an object.', array('step_id' => $step['step_id']));
				}
				$expectedSchema = $this->validateExpectedSchema($step['expected_schema'], $typed->steps[$index]->expected_schema, $step['step_id']);
				$tableName = $expectedSchema['table_name'];
				$createTables[$tableName] = true;
			} else {
				$this->assertSafeIdentifier($step['table_name'], 'mutation_table_invalid');
				$this->validateLineage($step['schema_lineage'], $typed->steps[$index]->schema_lineage, $assoc['migration_id'], $step['step_id']);
				if (!is_object($typed->steps[$index]->expected_before_schema) || !is_array($step['expected_before_schema'])
					|| !is_object($typed->steps[$index]->expected_after_schema) || !is_array($step['expected_after_schema'])) {
					throw new ClinicalSchemaException('expected_schema_not_object', 'Mutation before and after schemas must be objects.', array('step_id' => $step['step_id']));
				}
				$before = $this->validateExpectedSchema($step['expected_before_schema'], $typed->steps[$index]->expected_before_schema, $step['step_id']);
				$after = $this->validateExpectedSchema($step['expected_after_schema'], $typed->steps[$index]->expected_after_schema, $step['step_id']);
				$tableName = $step['table_name'];
				if ($before['table_name'] !== $tableName || $after['table_name'] !== $tableName) {
					throw new ClinicalSchemaException('mutation_table_schema_mismatch', 'Mutation table and before/after schemas must address the same table.');
				}
				if ($type === 'add_columns') {
					$addedColumns = $this->validateIdentifierArray($step['added_column_names'], $typed->steps[$index]->added_column_names, 'added_column_names');
					$addedChecks = $this->validateIdentifierArray($step['added_check_names'], $typed->steps[$index]->added_check_names, 'added_check_names');
					if (count($addedColumns) < 1 || count($addedChecks) < 1) {
						throw new ClinicalSchemaException('mutation_add_membership_empty', 'Added columns and checks must be explicitly represented.');
					}
					$this->validateAddColumnsDelta($before, $after, array_keys($addedColumns), array_keys($addedChecks));
				} else {
					$this->assertSafeIdentifier($step['modified_column_name'], 'modified_column_invalid');
					$this->validateModifyColumnDelta($before, $after, $step['modified_column_name']);
				}
				$modifiedTables[$tableName] = true;
			}
			if (isset($stepTables[$tableName])) {
				throw new ClinicalSchemaException('descriptor_table_duplicate', 'A table may be addressed by only one step in a migration.', array('table_name' => $tableName));
			}
			$stepTables[$tableName] = true;

			$sqlResolved = $this->resolveRelativeSqlFile($step['sql_file']);
			$sqlBytes = $this->readUtf8Source($sqlResolved, 'sql');
			if ($type === 'create_table') {
				$this->validateSql($sqlBytes, $expectedSchema);
			} else {
				$this->validateMutationSql($sqlBytes, $step, $before, $after);
			}

			$normalizedStep = array(
				'step_id' => $step['step_id'],
				'type' => $type,
				'sql_file' => str_replace('\\', '/', $step['sql_file']),
				'sql_path' => $sqlResolved,
				'sql_bytes' => $sqlBytes,
			);
			if ($type === 'create_table') {
				$normalizedStep['expected_schema'] = $expectedSchema;
			} else {
				$normalizedStep['table_name'] = $tableName;
				$normalizedStep['schema_lineage'] = $step['schema_lineage'];
				$normalizedStep['expected_before_schema'] = $before;
				$normalizedStep['expected_after_schema'] = $after;
				if ($type === 'add_columns') {
					$normalizedStep['added_column_names'] = array_keys($addedColumns);
					$normalizedStep['added_check_names'] = array_keys($addedChecks);
				} else {
					$normalizedStep['modified_column_name'] = $step['modified_column_name'];
				}
			}
			$steps[] = $normalizedStep;
		}
		if (count($stepFamilies) > 1) {
			throw new ClinicalSchemaException('descriptor_mixed_step_families', 'Create-table and mutation steps may not be mixed.');
		}

		if (!$this->sameIdentifierMembership(array_keys($expectedTables), array_keys($createTables))) {
			throw new ClinicalSchemaException('descriptor_expected_tables_mismatch', 'Expected created table membership must match create-table steps.');
		}
		if (!$this->sameIdentifierMembership(array_keys($expectedModifiedTables), array_keys($modifiedTables))) {
			throw new ClinicalSchemaException('descriptor_expected_modified_tables_mismatch', 'Expected modified table membership must match mutation steps.');
		}
		$relativeDescriptor = $this->relativePath($resolved);
		$checksum = $this->compositeChecksum($relativeDescriptor, $descriptorBytes, $steps);

		return array(
			'migration_id' => $assoc['migration_id'],
			'migration_name' => $assoc['migration_name'],
			'description' => $assoc['description'],
			'minimum_mariadb_version' => $assoc['minimum_mariadb_version'],
			'schema_only' => true,
			'imports_package_data' => false,
			'enables_runtime' => false,
			'expected_created_tables' => array_keys($expectedTables),
			'expected_modified_tables' => array_keys($expectedModifiedTables),
			'required_applied_migrations' => $requiredAppliedMigrations,
			'preconditions' => $preconditions,
			'steps' => $steps,
			'descriptor_path' => $resolved,
			'descriptor_relative_path' => $relativeDescriptor,
			'checksum' => $checksum,
		);
	}

	private function validateIdentifierArray($value, $typed, $context)
	{
		if (!is_array($value) || !is_array($typed)) {
			throw new ClinicalSchemaException($context . '_not_array', 'Identifier collection must be an array.');
		}
		$result = array();
		foreach ($value as $identifier) {
			$this->assertSafeIdentifier($identifier, $context . '_invalid');
			if (isset($result[$identifier])) {
				throw new ClinicalSchemaException($context . '_duplicate', 'Identifier collection must not contain duplicates.', array('identifier' => $identifier));
			}
			$result[$identifier] = true;
		}
		return $result;
	}

	private function validateRequiredMigrations($value, $typed, $migrationId)
	{
		if (!is_array($value) || !is_array($typed)) {
			throw new ClinicalSchemaException('required_applied_migrations_not_array', 'Required applied migrations must be an array.');
		}
		$result = array();
		$seen = array();
		foreach ($value as $index => $dependency) {
			if (!isset($typed[$index]) || !is_object($typed[$index]) || !is_array($dependency)) {
				throw new ClinicalSchemaException('required_migration_not_object', 'Required migration entries must be objects.');
			}
			$this->assertExactFields($dependency, array('migration_id', 'checksum'), 'required_migration');
			if (!is_string($dependency['migration_id']) || preg_match('/^[0-9]{14}_[a-z0-9_]+$/', $dependency['migration_id']) !== 1
				|| !is_string($dependency['checksum']) || preg_match('/^[0-9a-f]{64}$/', $dependency['checksum']) !== 1) {
				throw new ClinicalSchemaException('required_migration_invalid', 'Required migration identity is invalid.');
			}
			if ($dependency['migration_id'] >= $migrationId || isset($seen[$dependency['migration_id']])) {
				throw new ClinicalSchemaException('required_migration_order_invalid', 'Required migrations must be unique and precede the current migration.');
			}
			$seen[$dependency['migration_id']] = true;
			$result[] = $dependency;
		}
		return $result;
	}

	private function validatePreconditions($value, $typed)
	{
		if (!is_array($value) || !is_object($typed)) {
			throw new ClinicalSchemaException('preconditions_not_object', 'Preconditions must be an object.');
		}
		$this->assertExactFields($value, array('tables_must_be_empty'), 'preconditions');
		$tables = $this->validateIdentifierArray($value['tables_must_be_empty'], $typed->tables_must_be_empty, 'precondition_tables');
		return array('tables_must_be_empty' => array_keys($tables));
	}

	private function validateLineage($value, $typed, $migrationId, $stepId)
	{
		if (!is_array($value) || !is_object($typed)) {
			throw new ClinicalSchemaException('schema_lineage_not_object', 'Schema lineage must be an object.');
		}
		$this->assertExactFields($value, array('predecessor_migration_id', 'predecessor_step_id'), 'schema_lineage');
		if (!is_string($value['predecessor_migration_id']) || preg_match('/^[0-9]{14}_[a-z0-9_]+$/', $value['predecessor_migration_id']) !== 1
			|| !is_string($value['predecessor_step_id']) || preg_match('/^[0-9]{3}_[a-z0-9_]+$/', $value['predecessor_step_id']) !== 1) {
			throw new ClinicalSchemaException('schema_lineage_reference_invalid', 'Schema lineage predecessor reference is invalid.');
		}
		if ($value['predecessor_migration_id'] > $migrationId
			|| ($value['predecessor_migration_id'] === $migrationId && strcmp($value['predecessor_step_id'], $stepId) >= 0)) {
			throw new ClinicalSchemaException('schema_lineage_order_invalid', 'Schema lineage must move strictly forward.');
		}
	}

	private function validateAddColumnsDelta(array $before, array $after, array $addedColumns, array $addedChecks)
	{
		$this->assertSameSchemaCollectionsExcept($before, $after, array('columns', 'check_constraints'), 'add_columns_schema_delta_invalid');
		$beforeColumns = array();
		foreach ($before['columns'] as $column) {
			$beforeColumns[$column['name']] = $column;
		}
		$afterColumns = array();
		foreach ($after['columns'] as $column) {
			$afterColumns[$column['name']] = $column;
		}
		$actualAdded = array_values(array_diff(array_keys($afterColumns), array_keys($beforeColumns)));
		if ($actualAdded !== $addedColumns || count(array_diff(array_keys($beforeColumns), array_keys($afterColumns))) > 0) {
			throw new ClinicalSchemaException('add_columns_schema_delta_invalid', 'Added column metadata does not match the declared schema delta.');
		}
		foreach ($beforeColumns as $name => $column) {
			if (!isset($afterColumns[$name]) || $afterColumns[$name] !== $column) {
				throw new ClinicalSchemaException('add_columns_schema_delta_invalid', 'Existing column metadata may not change.');
			}
		}
		foreach ($addedColumns as $name) {
			$column = $afterColumns[$name];
			$generation = isset($column['generation_expression']) ? $column['generation_expression'] : '';
			if ($column['default'] !== null || $column['extra'] !== '' || $generation !== '') {
				throw new ClinicalSchemaException('add_columns_policy_invalid', 'Added columns may not have defaults, EXTRA metadata, or generation expressions.');
			}
		}
		$beforeChecks = array();
		foreach ($before['check_constraints'] as $check) {
			$beforeChecks[$check['name']] = $check;
		}
		$afterChecks = array();
		foreach ($after['check_constraints'] as $check) {
			$afterChecks[$check['name']] = $check;
		}
		$actualChecks = array_values(array_diff(array_keys($afterChecks), array_keys($beforeChecks)));
		if ($actualChecks !== $addedChecks || count(array_diff(array_keys($beforeChecks), array_keys($afterChecks))) > 0) {
			throw new ClinicalSchemaException('add_columns_schema_delta_invalid', 'Added checks do not match the declared schema delta.');
		}
		foreach ($beforeChecks as $name => $check) {
			if (!isset($afterChecks[$name]) || $afterChecks[$name] !== $check) {
				throw new ClinicalSchemaException('add_columns_schema_delta_invalid', 'Existing checks may not change.');
			}
		}
	}

	private function validateModifyColumnDelta(array $before, array $after, $columnName)
	{
		$this->assertSameSchemaCollectionsExcept($before, $after, array('columns'), 'modify_column_schema_delta_invalid');
		if (count($before['columns']) !== count($after['columns'])) {
			throw new ClinicalSchemaException('modify_column_schema_delta_invalid', 'Column modification may not add or remove columns.');
		}
		$changed = 0;
		foreach ($before['columns'] as $index => $column) {
			$next = $after['columns'][$index];
			if ($column['name'] !== $next['name']) {
				throw new ClinicalSchemaException('modify_column_schema_delta_invalid', 'Column modification may not reorder or rename columns.');
			}
			if ($column !== $next) {
				if ($column['name'] !== $columnName) {
					throw new ClinicalSchemaException('modify_column_schema_delta_invalid', 'Only the declared column may change.');
				}
				$changed++;
			}
		}
		if ($changed !== 1) {
			throw new ClinicalSchemaException('modify_column_schema_delta_invalid', 'Declared column must have exactly one represented metadata change.');
		}
	}

	private function assertSameSchemaCollectionsExcept(array $before, array $after, array $exceptions, $errorCode)
	{
		foreach ($before as $field => $value) {
			if (!in_array($field, $exceptions, true) && (!array_key_exists($field, $after) || $after[$field] !== $value)) {
				throw new ClinicalSchemaException($errorCode, 'Mutation contains an unrelated schema change.', array('field' => $field));
			}
		}
	}

	private function sameIdentifierMembership(array $left, array $right)
	{
		sort($left, SORT_STRING);
		sort($right, SORT_STRING);
		return $left === $right;
	}

	private function assertSafeIdentifier($value, $errorCode)
	{
		if (!is_string($value) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $value) !== 1) {
			throw new ClinicalSchemaException($errorCode, 'Unsafe machine identifier is prohibited.');
		}
	}

	private function validateExpectedSchema(array $schema, $typed, $stepId)
	{
		$this->assertExactFields($schema, array(
			'table_name', 'engine', 'default_charset', 'default_collation', 'columns',
			'primary_key', 'unique_indexes', 'indexes', 'foreign_keys', 'check_constraints',
		), 'expected_schema');
		foreach (array('table_name', 'engine', 'default_charset', 'default_collation') as $field) {
			$this->assertNonEmptyString($schema, $field, 'expected_schema');
		}
		if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $schema['table_name']) !== 1) {
			throw new ClinicalSchemaException('expected_schema_table_invalid', 'Expected table name is invalid.', array('step_id' => $stepId));
		}
		if ($schema['engine'] !== 'InnoDB' || $schema['default_charset'] !== 'utf8mb4' || $schema['default_collation'] !== 'utf8mb4_general_ci') {
			throw new ClinicalSchemaException('expected_schema_table_policy_invalid', 'Table engine, charset, and collation must match policy.', array('table_name' => $schema['table_name']));
		}

		$arrayFields = array('columns', 'primary_key', 'unique_indexes', 'indexes', 'foreign_keys', 'check_constraints');
		foreach ($arrayFields as $field) {
			if (!is_array($typed->$field) || !is_array($schema[$field])) {
				throw new ClinicalSchemaException('expected_schema_array_invalid', 'Expected schema collection must be an array.', array('field' => $field));
			}
		}
		if (count($schema['columns']) < 1 || count($schema['primary_key']) < 1) {
			throw new ClinicalSchemaException('expected_schema_required_collection_empty', 'Columns and primary key must be non-empty.');
		}

		$columnNames = array();
		$usesGenerationExpressions = false;
		foreach ($schema['columns'] as $column) {
			if (is_array($column) && array_key_exists('generation_expression', $column)) {
				$usesGenerationExpressions = true;
				break;
			}
		}
		foreach ($schema['columns'] as $index => $column) {
			if (!isset($typed->columns[$index]) || !is_object($typed->columns[$index]) || !is_array($column)) {
				throw new ClinicalSchemaException('expected_column_not_object', 'Expected columns must be objects.');
			}
			$columnFields = array('name', 'column_type', 'nullable', 'default', 'extra', 'character_set', 'collation');
			if ($usesGenerationExpressions) {
				$columnFields[] = 'generation_expression';
			}
			$this->assertExactFields($column, $columnFields, 'expected_column');
			$this->assertNonEmptyString($column, 'name', 'expected_column');
			$this->assertNonEmptyString($column, 'column_type', 'expected_column');
			if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $column['name']) !== 1 || isset($columnNames[$column['name']])) {
				throw new ClinicalSchemaException('expected_column_name_invalid', 'Expected column names must be unique safe identifiers.');
			}
			$columnNames[$column['name']] = true;
			if (!is_bool($column['nullable']) || (!is_null($column['default']) && !is_string($column['default'])) || !is_string($column['extra'])) {
				throw new ClinicalSchemaException('expected_column_value_invalid', 'Expected column metadata has an invalid type.', array('column' => $column['name']));
			}
			if ($usesGenerationExpressions && !is_string($column['generation_expression'])) {
				throw new ClinicalSchemaException('expected_column_generation_expression_invalid', 'Expected generation expression must be a string.', array('column' => $column['name']));
			}
			foreach (array('character_set', 'collation') as $field) {
				if (!is_null($column[$field]) && !is_string($column[$field])) {
					throw new ClinicalSchemaException('expected_column_character_policy_invalid', 'Expected character metadata must be string or null.', array('column' => $column['name']));
				}
			}
		}

		$this->validateColumnList($schema['primary_key'], $columnNames, 'primary_key');
		$this->validateIndexList($schema['unique_indexes'], $typed->unique_indexes, $columnNames, 'unique_index');
		$this->validateIndexList($schema['indexes'], $typed->indexes, $columnNames, 'index');
		$this->validateForeignKeys($schema['foreign_keys'], $typed->foreign_keys, $columnNames);
		$this->validateChecks($schema['check_constraints'], $typed->check_constraints);

		return $schema;
	}

	private function validateIndexList(array $indexes, $typedIndexes, array $columnNames, $kind)
	{
		$names = array();
		foreach ($indexes as $index => $definition) {
			if (!isset($typedIndexes[$index]) || !is_object($typedIndexes[$index]) || !is_array($definition)) {
				throw new ClinicalSchemaException('expected_index_not_object', 'Expected indexes must be objects.');
			}
			$this->assertExactFields($definition, array('name', 'columns'), 'expected_index');
			$this->assertNonEmptyString($definition, 'name', 'expected_index');
			if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $definition['name']) !== 1 || isset($names[$definition['name']])) {
				throw new ClinicalSchemaException('expected_index_name_invalid', 'Index names must be unique safe identifiers.', array('index_kind' => $kind));
			}
			if (!is_array($typedIndexes[$index]->columns) || !is_array($definition['columns']) || count($definition['columns']) < 1) {
				throw new ClinicalSchemaException('expected_index_columns_invalid', 'Index columns must be a non-empty array.');
			}
			$this->validateColumnList($definition['columns'], $columnNames, 'index_columns');
			$names[$definition['name']] = true;
		}
	}

	private function validateForeignKeys(array $foreignKeys, $typedForeignKeys, array $columnNames)
	{
		$names = array();
		foreach ($foreignKeys as $index => $definition) {
			if (!isset($typedForeignKeys[$index]) || !is_object($typedForeignKeys[$index]) || !is_array($definition)) {
				throw new ClinicalSchemaException('expected_foreign_key_not_object', 'Foreign keys must be objects.');
			}
			$this->assertExactFields($definition, array('name', 'columns', 'referenced_table', 'referenced_columns', 'on_update', 'on_delete'), 'expected_foreign_key');
			foreach (array('name', 'referenced_table', 'on_update', 'on_delete') as $field) {
				$this->assertNonEmptyString($definition, $field, 'expected_foreign_key');
			}
			if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $definition['name']) !== 1 || isset($names[$definition['name']])) {
				throw new ClinicalSchemaException('expected_foreign_key_name_invalid', 'Foreign key names must be unique safe identifiers.');
			}
			if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $definition['referenced_table']) !== 1) {
				throw new ClinicalSchemaException('expected_foreign_key_target_invalid', 'Foreign key target is invalid.');
			}
			if (!is_array($typedForeignKeys[$index]->columns) || !is_array($typedForeignKeys[$index]->referenced_columns)) {
				throw new ClinicalSchemaException('expected_foreign_key_columns_invalid', 'Foreign key column lists must be arrays.');
			}
			$this->validateColumnList($definition['columns'], $columnNames, 'foreign_key_columns');
			if (count($definition['columns']) !== count($definition['referenced_columns']) || count($definition['referenced_columns']) < 1) {
				throw new ClinicalSchemaException('expected_foreign_key_arity_invalid', 'Foreign key column counts must match.');
			}
			foreach ($definition['referenced_columns'] as $column) {
				if (!is_string($column) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $column) !== 1) {
					throw new ClinicalSchemaException('expected_foreign_key_reference_column_invalid', 'Foreign key reference column is invalid.');
				}
			}
			if (!in_array($definition['on_update'], array('RESTRICT', 'NO ACTION'), true) || !in_array($definition['on_delete'], array('RESTRICT', 'NO ACTION'), true)) {
				throw new ClinicalSchemaException('expected_foreign_key_action_invalid', 'Only preservation-oriented foreign key actions are allowed.');
			}
			$names[$definition['name']] = true;
		}
	}

	private function validateChecks(array $checks, $typedChecks)
	{
		$names = array();
		foreach ($checks as $index => $definition) {
			if (!isset($typedChecks[$index]) || !is_object($typedChecks[$index]) || !is_array($definition)) {
				throw new ClinicalSchemaException('expected_check_not_object', 'Check constraints must be objects.');
			}
			$this->assertExactFields($definition, array('name', 'expression'), 'expected_check');
			$this->assertNonEmptyString($definition, 'name', 'expected_check');
			$this->assertNonEmptyString($definition, 'expression', 'expected_check');
			if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $definition['name']) !== 1 || isset($names[$definition['name']])) {
				throw new ClinicalSchemaException('expected_check_name_invalid', 'Check names must be unique safe identifiers.');
			}
			$names[$definition['name']] = true;
		}
	}

	private function validateColumnList(array $columns, array $knownColumns, $context)
	{
		$seen = array();
		foreach ($columns as $column) {
			if (!is_string($column) || !isset($knownColumns[$column]) || isset($seen[$column])) {
				throw new ClinicalSchemaException('expected_column_list_invalid', 'Column list is invalid.', array('context' => $context));
			}
			$seen[$column] = true;
		}
	}

	private function resolveRelativeSqlFile($relativePath)
	{
		if (!is_string($relativePath) || $relativePath === '' || strpos($relativePath, "\0") !== false) {
			throw new ClinicalSchemaException('sql_path_invalid', 'SQL path is invalid.');
		}
		$normalized = str_replace('\\', '/', $relativePath);
		if ($normalized[0] === '/' || preg_match('/^[A-Za-z]:\//', $normalized) === 1 || strpos($normalized, '//') === 0) {
			throw new ClinicalSchemaException('sql_path_absolute', 'Absolute SQL paths are prohibited.');
		}
		$parts = explode('/', $normalized);
		foreach ($parts as $part) {
			if ($part === '' || $part === '.' || $part === '..') {
				throw new ClinicalSchemaException('sql_path_traversal', 'SQL path traversal is prohibited.');
			}
		}
		if (strtolower(pathinfo($normalized, PATHINFO_EXTENSION)) !== 'sql') {
			throw new ClinicalSchemaException('sql_path_extension_invalid', 'SQL step files must use the .sql extension.');
		}
		return $this->resolveContainedFile($this->migrationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized), 'sql_path_unsafe');
	}

	private function resolveContainedFile($path, $errorCode)
	{
		$resolved = realpath($path);
		if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
			throw new ClinicalSchemaException($errorCode, 'Required migration source is unavailable.');
		}
		$prefix = $this->migrationRoot . DIRECTORY_SEPARATOR;
		if ($resolved !== $this->migrationRoot && strncmp($resolved, $prefix, strlen($prefix)) !== 0) {
			throw new ClinicalSchemaException($errorCode, 'Migration source escapes the migration directory.');
		}
		return $resolved;
	}

	private function readUtf8Source($path, $kind)
	{
		$warning = null;
		set_error_handler(function ($severity, $message) use (&$warning) {
			$warning = $message;
			return true;
		});
		try {
			$bytes = file_get_contents($path);
		} finally {
			restore_error_handler();
		}
		if ($bytes === false || $warning !== null) {
			throw new ClinicalSchemaException($kind . '_read_failed', 'Migration source could not be read.');
		}
		if (substr($bytes, 0, 3) === "\xEF\xBB\xBF") {
			throw new ClinicalSchemaException($kind . '_bom_rejected', 'UTF-8 BOM is prohibited.');
		}
		if (strpos($bytes, "\0") !== false) {
			throw new ClinicalSchemaException($kind . '_nul_rejected', 'NUL bytes are prohibited.');
		}
		if (preg_match('//u', $bytes) !== 1) {
			throw new ClinicalSchemaException($kind . '_utf8_invalid', 'Migration source must be valid UTF-8.');
		}
		return $bytes;
	}

	public function validateSql($sql, array $expectedSchema)
	{
		$scan = $this->scanSql($sql);
		if ($scan['statement_count'] !== 1) {
			throw new ClinicalSchemaException('sql_statement_count_invalid', 'SQL step must contain exactly one statement.');
		}
		$statement = trim($scan['statement']);
		if (preg_match('/^CREATE\s+TABLE\s+(?!IF\s+NOT\s+EXISTS\b)(?:`([a-z][a-z0-9_]*)`|([a-z][a-z0-9_]*))\s*\(/i', $statement, $match) !== 1) {
			throw new ClinicalSchemaException('sql_create_table_required', 'SQL step must be one explicit CREATE TABLE statement.');
		}
		$table = $match[1] !== '' ? $match[1] : $match[2];
		if ($table !== $expectedSchema['table_name']) {
			throw new ClinicalSchemaException('sql_table_name_mismatch', 'SQL table name does not match expected schema.', array('table_name' => $expectedSchema['table_name']));
		}

		$keywordSurface = preg_replace('/\bON\s+(?:UPDATE|DELETE)\b/i', 'ON ACTION', $scan['keyword_surface']);
		$forbidden = '/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE|GRANT|REVOKE|LOAD\s+DATA|OUTFILE|DUMPFILE|CALL|EXECUTE|PREPARE|HANDLER|LOCK\s+TABLES|UNLOCK\s+TABLES|BEGIN|COMMIT|ROLLBACK|SAVEPOINT|START\s+TRANSACTION|SET\s+(?:AUTOCOMMIT|TRANSACTION)|DELIMITER)\b/i';
		if (preg_match($forbidden, $keywordSurface) === 1) {
			throw new ClinicalSchemaException('sql_forbidden_construct', 'SQL step contains a prohibited construct.');
		}
		$expectedGeneratedColumns = 0;
		foreach ($expectedSchema['columns'] as $column) {
			if (isset($column['generation_expression']) && trim($column['generation_expression']) !== '') {
				$expectedGeneratedColumns++;
			}
		}
		$generatedClauseCount = preg_match_all('/\bGENERATED\s+ALWAYS\b/i', $scan['keyword_surface'], $generatedMatches);
		$persistentCount = preg_match_all('/\bPERSISTENT\b/i', $scan['keyword_surface'], $persistentMatches);
		$virtualCount = preg_match_all('/\bVIRTUAL\b/i', $scan['keyword_surface'], $virtualMatches);
		if ($virtualCount > 0) {
			throw new ClinicalSchemaException('sql_generated_column_virtual_rejected', 'VIRTUAL generated columns are prohibited.');
		}
		if ($generatedClauseCount !== $expectedGeneratedColumns) {
			throw new ClinicalSchemaException('sql_generated_column_count_mismatch', 'Generated column clauses do not match expected metadata.');
		}
		if ($persistentCount !== $expectedGeneratedColumns) {
			throw new ClinicalSchemaException('sql_generated_column_persistent_count_mismatch', 'Every represented generated column must be explicitly PERSISTENT.');
		}

		$unrepresentedOptions = '/(?:\b(?:PARTITION\s+BY|DATA\s+DIRECTORY|INDEX\s+DIRECTORY|TABLESPACE|COMMENT|ROW_FORMAT|KEY_BLOCK_SIZE|COMPRESSION|PAGE_COMPRESSED|PAGE_COMPRESSION_LEVEL|ENCRYPTION|WITH\s+SYSTEM\s+VERSIONING)\b|\bAUTO_INCREMENT\s*=)/i';
		if (preg_match($unrepresentedOptions, $scan['keyword_surface']) === 1) {
			throw new ClinicalSchemaException('sql_unrepresented_table_option', 'SQL step contains an unrepresented table or column option.');
		}

		$declaredTargets = array();
		foreach ($expectedSchema['foreign_keys'] as $foreignKey) {
			$declaredTargets[$foreignKey['referenced_table']] = true;
		}
		$referencedTargets = array();
		if (preg_match_all('/\bREFERENCES\s+(?:`([a-z][a-z0-9_]*)`|([a-z][a-z0-9_]*))/i', $statement, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $reference) {
				$target = $reference[1] !== '' ? $reference[1] : $reference[2];
				$referencedTargets[$target] = true;
			}
		}
		$declared = array_keys($declaredTargets);
		$actual = array_keys($referencedTargets);
		sort($declared, SORT_STRING);
		sort($actual, SORT_STRING);
		if ($declared !== $actual) {
			throw new ClinicalSchemaException('sql_foreign_key_target_mismatch', 'SQL foreign key targets do not match the descriptor.');
		}
	}

	private function validateMutationSql($sql, array $step, array $before, array $after)
	{
		$scan = $this->scanSql($sql);
		if ($scan['statement_count'] !== 1) {
			throw new ClinicalSchemaException('sql_statement_count_invalid', 'SQL step must contain exactly one statement.');
		}
		$statement = trim($scan['statement']);
		if (preg_match('/^ALTER\s+TABLE\s+(?:`([a-z][a-z0-9_]*)`|([a-z][a-z0-9_]*))\s+(.+)$/is', $statement, $match) !== 1) {
			throw new ClinicalSchemaException('sql_mutation_alter_required', 'Mutation step must be one explicit ALTER TABLE statement.');
		}
		$table = $match[1] !== '' ? $match[1] : $match[2];
		if ($table !== $step['table_name']) {
			throw new ClinicalSchemaException('sql_table_name_mismatch', 'SQL table name does not match mutation metadata.');
		}
		$keywordSurface = preg_replace('/\bON\s+(?:UPDATE|DELETE)\b/i', 'ON ACTION', $scan['keyword_surface']);
		$forbidden = '/\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|CHANGE|RENAME|TRUNCATE|GRANT|REVOKE|LOAD\s+DATA|OUTFILE|DUMPFILE|CALL|EXECUTE|PREPARE|HANDLER|LOCK\s+TABLES|UNLOCK\s+TABLES|BEGIN|COMMIT|ROLLBACK|SAVEPOINT|START\s+TRANSACTION|SET\s+(?:AUTOCOMMIT|TRANSACTION)|DELIMITER|CASCADE|PARTITION|TABLESPACE|PROCEDURE|TRIGGER|GENERATED|VIRTUAL|PERSISTENT)\b/i';
		if (preg_match($forbidden, $keywordSurface) === 1) {
			throw new ClinicalSchemaException('sql_mutation_forbidden_construct', 'Mutation SQL contains a prohibited construct.');
		}

		$clauses = $this->splitTopLevelClauses($match[3]);
		if ($step['type'] === 'add_columns') {
			$expectedCount = count($step['added_column_names']) + count($step['added_check_names']);
			if (count($clauses) !== $expectedCount) {
				throw new ClinicalSchemaException('sql_add_columns_clause_count_mismatch', 'ADD COLUMN clauses do not match descriptor representation.');
			}
			$afterColumns = array();
			$columnPositions = array();
			foreach ($after['columns'] as $index => $column) {
				$afterColumns[$column['name']] = $column;
				$columnPositions[$column['name']] = $index;
			}
			foreach ($step['added_column_names'] as $index => $name) {
				$clause = $clauses[$index];
				if (preg_match('/^ADD\s+COLUMN\s+(?:`([a-z][a-z0-9_]*)`|([a-z][a-z0-9_]*))\s+(.+?)\s+AFTER\s+(?:`([a-z][a-z0-9_]*)`|([a-z][a-z0-9_]*))$/is', $clause, $columnMatch) !== 1) {
					throw new ClinicalSchemaException('sql_add_column_shape_invalid', 'Added column must use the exact represented ADD COLUMN ... AFTER shape.');
				}
				$actualName = $columnMatch[1] !== '' ? $columnMatch[1] : $columnMatch[2];
				$afterName = $columnMatch[4] !== '' ? $columnMatch[4] : $columnMatch[5];
				$position = $columnPositions[$name];
				$expectedAfter = $position > 0 ? $after['columns'][$position - 1]['name'] : null;
				if ($actualName !== $name || $afterName !== $expectedAfter
					|| $this->normalizeSqlFragment($columnMatch[3]) !== $this->normalizeSqlFragment($this->columnSqlDefinition($afterColumns[$name]))) {
					throw new ClinicalSchemaException('sql_add_column_definition_mismatch', 'Added column definition or position does not match expected metadata.', array('column' => $name));
				}
			}
			$afterChecks = array();
			foreach ($after['check_constraints'] as $check) {
				$afterChecks[$check['name']] = $check['expression'];
			}
			foreach ($step['added_check_names'] as $offset => $name) {
				$clause = $clauses[count($step['added_column_names']) + $offset];
				if (preg_match('/^ADD\s+CONSTRAINT\s+(?:`([a-z][a-z0-9_]*)`|([a-z][a-z0-9_]*))\s+CHECK\s*\((.*)\)$/is', $clause, $checkMatch) !== 1) {
					throw new ClinicalSchemaException('sql_add_check_shape_invalid', 'Added check must use the exact represented ADD CONSTRAINT CHECK shape.');
				}
				$actualName = $checkMatch[1] !== '' ? $checkMatch[1] : $checkMatch[2];
				if ($actualName !== $name || !isset($afterChecks[$name])
					|| $this->normalizeSqlFragment($checkMatch[3]) !== $this->normalizeSqlFragment($afterChecks[$name])) {
					throw new ClinicalSchemaException('sql_add_check_definition_mismatch', 'Added check does not match expected metadata.', array('constraint' => $name));
				}
			}
			return;
		}

		if (count($clauses) !== 1 || preg_match('/\b(?:FIRST|AFTER)\b/i', $clauses[0]) === 1
			|| preg_match('/^MODIFY\s+COLUMN\s+(?:`([a-z][a-z0-9_]*)`|([a-z][a-z0-9_]*))\s+(.+)$/is', $clauses[0], $columnMatch) !== 1) {
			throw new ClinicalSchemaException('sql_modify_column_shape_invalid', 'MODIFY COLUMN must change exactly one represented column without repositioning it.');
		}
		$name = $columnMatch[1] !== '' ? $columnMatch[1] : $columnMatch[2];
		$afterColumn = null;
		foreach ($after['columns'] as $column) {
			if ($column['name'] === $step['modified_column_name']) {
				$afterColumn = $column;
				break;
			}
		}
		if ($name !== $step['modified_column_name'] || $afterColumn === null
			|| $this->normalizeSqlFragment($columnMatch[3]) !== $this->normalizeSqlFragment($this->columnSqlDefinition($afterColumn))) {
			throw new ClinicalSchemaException('sql_modify_column_definition_mismatch', 'Modified column definition does not match expected metadata.');
		}
	}

	private function columnSqlDefinition(array $column)
	{
		$definition = $column['column_type'];
		if ($column['character_set'] !== null) {
			$definition .= ' CHARACTER SET ' . $column['character_set'];
		}
		if ($column['collation'] !== null) {
			$definition .= ' COLLATE ' . $column['collation'];
		}
		$definition .= $column['nullable'] ? ' NULL' : ' NOT NULL';
		if ($column['default'] !== null) {
			$definition .= ' DEFAULT ' . $column['default'];
		}
		if ($column['extra'] !== '') {
			$definition .= ' ' . $column['extra'];
		}
		return $definition;
	}

	private function splitTopLevelClauses($sql)
	{
		$clauses = array();
		$current = '';
		$depth = 0;
		$quote = null;
		$length = strlen($sql);
		for ($index = 0; $index < $length; $index++) {
			$char = $sql[$index];
			if ($quote !== null) {
				$current .= $char;
				if ($char === $quote) {
					if ($index + 1 < $length && $sql[$index + 1] === $quote) {
						$current .= $sql[++$index];
					} else {
						$quote = null;
					}
				} elseif ($char === '\\' && $index + 1 < $length) {
					$current .= $sql[++$index];
				}
				continue;
			}
			if ($char === "'" || $char === '"' || $char === '`') {
				$quote = $char;
				$current .= $char;
			} elseif ($char === '(') {
				$depth++;
				$current .= $char;
			} elseif ($char === ')') {
				$depth--;
				$current .= $char;
			} elseif ($char === ',' && $depth === 0) {
				$clauses[] = trim($current);
				$current = '';
			} else {
				$current .= $char;
			}
		}
		if ($quote !== null || $depth !== 0 || trim($current) === '') {
			throw new ClinicalSchemaException('sql_mutation_clause_invalid', 'Mutation SQL clauses are malformed.');
		}
		$clauses[] = trim($current);
		return $clauses;
	}

	private function normalizeSqlFragment($value)
	{
		$value = (string) $value;
		$output = '';
		$quote = null;
		$length = strlen($value);
		for ($index = 0; $index < $length; $index++) {
			$char = $value[$index];
			if ($quote !== null) {
				$output .= $char;
				if ($char === $quote) {
					if ($index + 1 < $length && $value[$index + 1] === $quote) {
						$output .= $value[++$index];
					} else {
						$quote = null;
					}
				}
				continue;
			}
			if ($char === "'" || $char === '"') {
				$quote = $char;
				$output .= $char;
			} elseif ($char !== '`' && preg_match('/\s/', $char) !== 1) {
				$output .= strtolower($char);
			}
		}
		return $output;
	}

	public function scanSql($sql)
	{
		$length = strlen($sql);
		$state = 'code';
		$statement = '';
		$keyword = '';
		$statements = array();
		$comment = '';
		$comments = array();

		for ($i = 0; $i < $length; $i++) {
			$char = $sql[$i];
			$next = $i + 1 < $length ? $sql[$i + 1] : '';
			$nextTwo = $i + 2 < $length ? $sql[$i + 2] : '';

			if ($state === 'code') {
				if ($char === "'") {
					$state = 'single';
					$statement .= $char;
					$keyword .= ' ';
				} elseif ($char === '"') {
					$state = 'double';
					$statement .= $char;
					$keyword .= ' ';
				} elseif ($char === '`') {
					$state = 'backtick';
					$statement .= $char;
					$keyword .= ' ';
				} elseif ($char === '-' && $next === '-' && ($nextTwo === '' || preg_match('/\s/', $nextTwo) === 1)) {
					$state = 'line_comment';
					$comment = '--';
					$i++;
					$statement .= '  ';
					$keyword .= '  ';
				} elseif ($char === '#') {
					$state = 'line_comment';
					$comment = '#';
					$statement .= ' ';
					$keyword .= ' ';
				} elseif ($char === '/' && $next === '*') {
					$afterNextTwo = $i + 3 < $length ? $sql[$i + 3] : '';
					$isMariaDbExecutableComment = ($nextTwo === 'M' || $nextTwo === 'm') && $afterNextTwo === '!';
					if ($nextTwo === '!' || $nextTwo === '+' || $isMariaDbExecutableComment) {
						throw new ClinicalSchemaException('sql_executable_comment_rejected', 'Executable comments and optimizer hints are prohibited.');
					}
					$state = 'block_comment';
					$comment = '/*';
					$i++;
					$statement .= '  ';
					$keyword .= '  ';
				} elseif ($char === ';') {
					if (trim($statement) === '') {
						throw new ClinicalSchemaException('sql_empty_statement_rejected', 'Empty SQL statements are prohibited.');
					}
					$statements[] = trim($statement);
					$statement = '';
					$keyword .= ' ';
				} else {
					$statement .= $char;
					$keyword .= $char;
				}
			} elseif ($state === 'single' || $state === 'double') {
				$quote = $state === 'single' ? "'" : '"';
				$statement .= $char;
				$keyword .= ' ';
				if ($char === '\\' && $next !== '') {
					$i++;
					$statement .= $next;
					$keyword .= ' ';
				} elseif ($char === $quote) {
					if ($next === $quote) {
						$i++;
						$statement .= $next;
						$keyword .= ' ';
					} else {
						$state = 'code';
					}
				}
			} elseif ($state === 'backtick') {
				$statement .= $char;
				$keyword .= ' ';
				if ($char === '`') {
					if ($next === '`') {
						$i++;
						$statement .= $next;
						$keyword .= ' ';
					} else {
						$state = 'code';
					}
				}
			} elseif ($state === 'line_comment') {
				$comment .= $char;
				$statement .= ($char === "\n" || $char === "\r") ? $char : ' ';
				$keyword .= ($char === "\n" || $char === "\r") ? $char : ' ';
				if ($char === "\n" || $char === "\r") {
					$comments[] = $comment;
					$comment = '';
					$state = 'code';
				}
			} elseif ($state === 'block_comment') {
				$comment .= $char;
				$statement .= ' ';
				$keyword .= ' ';
				if ($char === '*' && $next === '/') {
					$i++;
					$comment .= '/';
					$statement .= ' ';
					$keyword .= ' ';
					$comments[] = $comment;
					$comment = '';
					$state = 'code';
				}
			}
		}

		if ($state === 'single' || $state === 'double' || $state === 'backtick' || $state === 'block_comment') {
			throw new ClinicalSchemaException('sql_lexical_state_unclosed', 'SQL contains an unclosed quoted value, identifier, or comment.');
		}
		if ($state === 'line_comment') {
			$comments[] = $comment;
		}
		if (trim($statement) !== '') {
			$statements[] = trim($statement);
		}
		foreach ($comments as $commentText) {
			if (preg_match('/(?:password|passwd|credential|secret|token|api[_ -]?key|connection\s+string)/i', $commentText) === 1) {
				throw new ClinicalSchemaException('sql_sensitive_comment_rejected', 'Comments containing credential-related terms are prohibited.');
			}
		}

		return array(
			'statement_count' => count($statements),
			'statement' => count($statements) === 1 ? $statements[0] : '',
			'keyword_surface' => $keyword,
		);
	}

	private function compositeChecksum($descriptorPath, $descriptorBytes, array $steps)
	{
		$context = hash_init('sha256');
		$this->hashComponent($context, $descriptorPath, $this->normalizeNewlines($descriptorBytes));
		foreach ($steps as $step) {
			$this->hashComponent($context, $step['sql_file'], $this->normalizeNewlines($step['sql_bytes']));
		}
		return strtolower(hash_final($context));
	}

	private function hashComponent($context, $path, $bytes)
	{
		hash_update($context, pack('N', strlen($path)));
		hash_update($context, $path);
		hash_update($context, pack('N', strlen($bytes)));
		hash_update($context, $bytes);
	}

	private function normalizeNewlines($bytes)
	{
		return str_replace(array("\r\n", "\r"), "\n", $bytes);
	}

	private function relativePath($resolved)
	{
		$relative = substr($resolved, strlen($this->migrationRoot) + 1);
		return str_replace('\\', '/', $relative);
	}

	private function assertExactFields(array $value, array $allowed, $context)
	{
		$actual = array_keys($value);
		$unknown = array_diff($actual, $allowed);
		$missing = array_diff($allowed, $actual);
		if (count($unknown) > 0) {
			sort($unknown, SORT_STRING);
			throw new ClinicalSchemaException($context . '_unknown_field', 'Unknown field is prohibited.', array('field' => $unknown[0]));
		}
		if (count($missing) > 0) {
			sort($missing, SORT_STRING);
			throw new ClinicalSchemaException($context . '_missing_field', 'Required field is missing.', array('field' => $missing[0]));
		}
	}

	private function assertAllowedAndRequiredFields(array $value, array $allowed, array $required, $context)
	{
		$actual = array_keys($value);
		$unknown = array_diff($actual, $allowed);
		$missing = array_diff($required, $actual);
		if (count($unknown) > 0) {
			sort($unknown, SORT_STRING);
			throw new ClinicalSchemaException($context . '_unknown_field', 'Unknown field is prohibited.', array('field' => $unknown[0]));
		}
		if (count($missing) > 0) {
			sort($missing, SORT_STRING);
			throw new ClinicalSchemaException($context . '_missing_field', 'Required field is missing.', array('field' => $missing[0]));
		}
	}

	private function assertNonEmptyString(array $value, $field, $context)
	{
		if (!isset($value[$field]) || !is_string($value[$field]) || trim($value[$field]) === '' || preg_match('/[\x00-\x1F\x7F]/', $value[$field]) === 1) {
			throw new ClinicalSchemaException($context . '_string_invalid', 'Required string is invalid.', array('field' => $field));
		}
	}
}
