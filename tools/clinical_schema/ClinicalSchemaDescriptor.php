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

		$this->assertExactFields($assoc, array(
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

		if (!is_array($typed->steps) || !is_array($assoc['steps']) || count($assoc['steps']) < 1) {
			throw new ClinicalSchemaException('descriptor_steps_not_array', 'Descriptor steps must be a non-empty array.');
		}

		$stepIds = array();
		$stepTables = array();
		$steps = array();
		foreach ($assoc['steps'] as $index => $step) {
			if (!isset($typed->steps[$index]) || !is_object($typed->steps[$index]) || !is_array($step)) {
				throw new ClinicalSchemaException('descriptor_step_not_object', 'Every descriptor step must be an object.', array('step_index' => $index));
			}
			$this->assertExactFields($step, array('step_id', 'type', 'sql_file', 'expected_schema'), 'step');
			foreach (array('step_id', 'type', 'sql_file') as $field) {
				$this->assertNonEmptyString($step, $field, 'step');
			}
			if (preg_match('/^[0-9]{3}_[a-z0-9_]+$/', $step['step_id']) !== 1 || isset($stepIds[$step['step_id']])) {
				throw new ClinicalSchemaException('descriptor_step_id_invalid', 'Step IDs must be unique safe identifiers.', array('step_index' => $index));
			}
			$stepIds[$step['step_id']] = true;
			if ($step['type'] !== 'create_table') {
				throw new ClinicalSchemaException('descriptor_step_type_unknown', 'Only create_table steps are supported.', array('step_id' => $step['step_id']));
			}
			if (!is_object($typed->steps[$index]->expected_schema) || !is_array($step['expected_schema'])) {
				throw new ClinicalSchemaException('expected_schema_not_object', 'Expected schema must be an object.', array('step_id' => $step['step_id']));
			}

			$expectedSchema = $this->validateExpectedSchema($step['expected_schema'], $typed->steps[$index]->expected_schema, $step['step_id']);
			$tableName = $expectedSchema['table_name'];
			if (isset($stepTables[$tableName])) {
				throw new ClinicalSchemaException('descriptor_table_duplicate', 'A table may be created by only one step.', array('table_name' => $tableName));
			}
			$stepTables[$tableName] = true;

			$sqlResolved = $this->resolveRelativeSqlFile($step['sql_file']);
			$sqlBytes = $this->readUtf8Source($sqlResolved, 'sql');
			$this->validateSql($sqlBytes, $expectedSchema);

			$steps[] = array(
				'step_id' => $step['step_id'],
				'type' => $step['type'],
				'sql_file' => str_replace('\\', '/', $step['sql_file']),
				'sql_path' => $sqlResolved,
				'sql_bytes' => $sqlBytes,
				'expected_schema' => $expectedSchema,
			);
		}

		if (array_keys($expectedTables) !== array_keys($stepTables)) {
			$expectedSorted = array_keys($expectedTables);
			$actualSorted = array_keys($stepTables);
			sort($expectedSorted, SORT_STRING);
			sort($actualSorted, SORT_STRING);
			if ($expectedSorted !== $actualSorted) {
				throw new ClinicalSchemaException('descriptor_expected_tables_mismatch', 'Expected table membership must match step schemas.');
			}
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
			'steps' => $steps,
			'descriptor_path' => $resolved,
			'descriptor_relative_path' => $relativeDescriptor,
			'checksum' => $checksum,
		);
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

	private function assertNonEmptyString(array $value, $field, $context)
	{
		if (!isset($value[$field]) || !is_string($value[$field]) || trim($value[$field]) === '' || preg_match('/[\x00-\x1F\x7F]/', $value[$field]) === 1) {
			throw new ClinicalSchemaException($context . '_string_invalid', 'Required string is invalid.', array('field' => $field));
		}
	}
}
