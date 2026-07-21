<?php

class ClinicalSchemaMigrator
{
	private $descriptorLoader;
	private $connection;
	private $config;

	public function __construct(ClinicalSchemaDescriptor $descriptorLoader, $connection, array $config)
	{
		$this->descriptorLoader = $descriptorLoader;
		$this->connection = $connection;
		$this->config = $config;
	}

	public function plan(array $descriptor)
	{
		$this->validatedCatalog();
		if ($descriptor['migration_id'] === $this->config['ledger_migration_id']) {
			$this->assertLedgerDescriptorShape($descriptor);
		}
		return array(
			'migration_id' => $descriptor['migration_id'],
			'migration_name' => $descriptor['migration_name'],
			'checksum' => $descriptor['checksum'],
			'step_count' => count($descriptor['steps']),
			'created_tables' => $descriptor['expected_created_tables'],
			'modified_tables' => $descriptor['expected_modified_tables'],
			'schema_only' => true,
			'imports_package_data' => false,
			'enables_runtime' => false,
			'ddl_executed' => false,
		);
	}

	public function status(array $options)
	{
		$ledgerDescriptor = $this->descriptorLoader->loadById($this->config['ledger_migration_id']);
		$this->assertLedgerDescriptorShape($ledgerDescriptor);
		$inspection = $this->inspectExpectedSchema($ledgerDescriptor['steps'][0]['expected_schema']);
		if (!$inspection['exists']) {
			return array(
				'ledger_initialized' => false,
				'ledger_schema_valid' => null,
				'bootstrap_record_present' => false,
				'bootstrap_record_valid' => false,
				'bootstrap_validation_error' => null,
				'migration_count' => 0,
				'applied_count' => 0,
				'failed_count' => 0,
				'interrupted_count' => 0,
				'rolled_back_count' => 0,
				'ddl_executed' => false,
			);
		}
		if (!$inspection['matches']) {
			throw new ClinicalSchemaException('ledger_schema_mismatch', 'Clinical migration ledger schema does not match its descriptor.', array('difference_count' => count($inspection['differences'])));
		}
		$bootstrap = $this->ledgerRow($this->config['ledger_migration_id']);
		$bootstrapPresent = $bootstrap !== null;
		$bootstrapValid = false;
		$bootstrapError = null;
		if ($bootstrapPresent) {
			try {
				$this->assertExistingMigrationIdentity($bootstrap, $ledgerDescriptor, $options, false);
				if ((string) $bootstrap['state'] !== 'applied') {
					throw new ClinicalSchemaException('ledger_bootstrap_not_applied', 'The ledger bootstrap record must be applied.');
				}
				$this->assertAppliedCompletion($bootstrap, $ledgerDescriptor);
				$bootstrapValid = true;
			} catch (ClinicalSchemaException $exception) {
				$bootstrapError = $exception->getSafeCode();
			}
		}

		$rows = $this->connection->queryAll("SELECT state, COUNT(*) AS row_count FROM `clinical_schema_migrations` GROUP BY state ORDER BY state");
		$counts = array('applied' => 0, 'failed' => 0, 'applying' => 0, 'rolling_back' => 0, 'rolled_back' => 0);
		$total = 0;
		foreach ($rows as $row) {
			$state = (string) $row['state'];
			$count = (int) $row['row_count'];
			$total += $count;
			if (array_key_exists($state, $counts)) {
				$counts[$state] = $count;
			}
		}
		return array(
			'ledger_initialized' => $bootstrapValid,
			'ledger_schema_valid' => true,
			'bootstrap_record_present' => $bootstrapPresent,
			'bootstrap_record_valid' => $bootstrapValid,
			'bootstrap_validation_error' => $bootstrapError,
			'migration_count' => $total,
			'applied_count' => $counts['applied'],
			'failed_count' => $counts['failed'],
			'interrupted_count' => $counts['applying'] + $counts['rolling_back'],
			'rolled_back_count' => $counts['rolled_back'],
			'ddl_executed' => false,
		);
	}

	public function verify(array $descriptor, array $options)
	{
		$this->connection->verifyServer($descriptor['minimum_mariadb_version']);
		$ledgerDescriptor = $this->descriptorLoader->loadById($this->config['ledger_migration_id']);
		$this->assertLedgerDescriptorShape($ledgerDescriptor);
		$ledgerInspection = $this->inspectExpectedSchema($ledgerDescriptor['steps'][0]['expected_schema']);
		if (!$ledgerInspection['exists'] || !$ledgerInspection['matches']) {
			throw new ClinicalSchemaException('ledger_not_ready', 'Clinical migration ledger is absent or invalid.');
		}
		$this->assertLedgerBootstrapReady($ledgerDescriptor, $options);
		$row = $this->ledgerRow($descriptor['migration_id']);
		if (!$row) {
			throw new ClinicalSchemaException('migration_not_recorded', 'Migration is not recorded in the ledger.');
		}
		$this->assertExistingMigrationIdentity($row, $descriptor, $options, false);
		if ((string) $row['state'] !== 'applied') {
			throw new ClinicalSchemaException('migration_not_applied', 'Migration is not in the applied state.', array('state' => (string) $row['state']));
		}
		$this->assertAppliedCompletion($row, $descriptor);
		$catalog = $this->validatedCatalog($options, true);
		$verified = 0;
		foreach ($this->descriptorTables($descriptor) as $table) {
			$effective = $this->resolveEffectiveSchema($table, $catalog, $options);
			$inspection = $this->inspectExpectedSchema($effective['schema']);
			if (!$inspection['exists'] || !$inspection['matches']) {
				throw new ClinicalSchemaException('migration_schema_mismatch', 'Applied migration effective schema fingerprint does not match.', array('table_name' => $table, 'difference_count' => count($inspection['differences']), 'effective_migration_id' => $effective['migration_id']));
			}
			$verified++;
		}
		return array('verified_step_count' => $verified, 'ddl_executed' => false, 'state' => 'applied');
	}

	public function execute($command, array $descriptor, array $options)
	{
		if (!in_array($command, array('init', 'apply', 'resume'), true)) {
			throw new ClinicalSchemaException('migration_write_command_invalid', 'Unsupported migration write command.');
		}
		$isLedger = $descriptor['migration_id'] === $this->config['ledger_migration_id'];
		if ($isLedger) {
			$this->assertLedgerDescriptorShape($descriptor);
		}
		if ($command === 'init' && !$isLedger) {
			throw new ClinicalSchemaException('init_migration_rejected', 'Init may only bootstrap the clinical migration ledger.');
		}
		if ($command !== 'init' && $isLedger) {
			throw new ClinicalSchemaException('ledger_requires_init', 'The ledger bootstrap must use init.');
		}
		$this->connection->verifyServer($descriptor['minimum_mariadb_version']);
		$lockName = $this->config['lock_prefix'] . $options['confirm_database'];
		$this->connection->acquireLock($lockName, $this->config['lock_timeout_seconds']);
		$primaryException = null;
		$result = null;
		try {
			$result = $isLedger
				? $this->bootstrapLedger($descriptor, $options)
				: $this->applyNormalMigration($command, $descriptor, $options);
		} catch (Throwable $exception) {
			$primaryException = $exception;
		} finally {
			$released = false;
			try {
				$released = $this->connection->releaseLock($lockName);
			} catch (Throwable $releaseException) {
				if ($primaryException === null) {
					$primaryException = new ClinicalSchemaException('advisory_lock_release_failed', 'Clinical schema advisory lock release failed.');
				}
			}
			if (!$released && $primaryException === null) {
				$primaryException = new ClinicalSchemaException('advisory_lock_release_failed', 'Clinical schema advisory lock release failed.');
			}
		}
		if ($primaryException !== null) {
			throw $primaryException;
		}
		return $result;
	}

	private function bootstrapLedger(array $descriptor, array $options)
	{
		$step = $descriptor['steps'][0];
		$inspection = $this->inspectExpectedSchema($step['expected_schema']);
		$ddlExecuted = false;
		if ($inspection['exists'] && !$inspection['matches']) {
			throw new ClinicalSchemaException('ledger_collision_mismatch', 'Existing clinical migration ledger does not match its descriptor.', array('difference_count' => count($inspection['differences'])));
		}
		if (!$inspection['exists']) {
			$this->connection->executeDdl($step['sql_bytes']);
			$ddlExecuted = true;
			$inspection = $this->inspectExpectedSchema($step['expected_schema']);
			if (!$inspection['exists'] || !$inspection['matches']) {
				throw new ClinicalSchemaException('ledger_bootstrap_verification_failed', 'Created migration ledger failed schema verification.');
			}
		}

		$row = $this->ledgerRow($descriptor['migration_id']);
		if ($row) {
			$this->assertExistingMigrationIdentity($row, $descriptor, $options, true);
			if ((string) $row['state'] === 'applied') {
				$this->assertAppliedCompletion($row, $descriptor);
				return array('state' => 'applied', 'step_count' => 1, 'ddl_executed' => $ddlExecuted, 'already_applied' => true);
			}
			if (!in_array((string) $row['state'], array('applying', 'failed'), true)) {
				throw new ClinicalSchemaException('ledger_bootstrap_state_invalid', 'Ledger bootstrap record is not recoverable.');
			}
			$this->ledgerTransaction(function () use ($descriptor, $row) {
				$affected = $this->connection->executePrepared(
					"UPDATE `clinical_schema_migrations` SET state='applied', last_completed_step=statement_count, applied_at=?, failed_at=NULL, error_code=NULL, error_summary=NULL WHERE migration_id=? AND state=?",
					'sss',
					array($this->now(), $descriptor['migration_id'], $row['state'])
				);
				$this->requireOneAffected($affected, 'bootstrap_recovery');
			});
			return array('state' => 'applied', 'step_count' => 1, 'ddl_executed' => $ddlExecuted, 'recovered' => true);
		}

		$this->assertChecksumUnique($descriptor);
		$now = $this->now();
		$this->ledgerTransaction(function () use ($descriptor, $options, $now) {
			$affected = $this->connection->executePrepared(
				"INSERT INTO `clinical_schema_migrations` (migration_id,migration_name,migration_checksum,state,attempt_count,statement_count,last_completed_step,execution_environment,target_database,server_version,tool_version,executor_identity,backup_reference,started_at,applied_at,failed_at,error_code,error_summary,created_at,updated_at) VALUES (?,?,?,'applied',1,1,1,?,?,?,?,?,?,?,?,NULL,NULL,NULL,?,?)",
				'sssssssssssss',
				array(
					$descriptor['migration_id'], $descriptor['migration_name'], $descriptor['checksum'],
					$options['environment'], $options['confirm_database'], $this->connection->getServerVersion(),
					$this->config['tool_version'], $this->connection->getExecutorIdentityHash(), $options['backup_reference'],
					$now, $now, $now, $now,
				)
			);
			$this->requireOneAffected($affected, 'bootstrap_insert');
		});
		return array('state' => 'applied', 'step_count' => 1, 'ddl_executed' => $ddlExecuted, 'bootstrap_created' => true);
	}

	private function applyNormalMigration($command, array $descriptor, array $options)
	{
		$ledgerDescriptor = $this->descriptorLoader->loadById($this->config['ledger_migration_id']);
		$this->assertLedgerDescriptorShape($ledgerDescriptor);
		$ledgerInspection = $this->inspectExpectedSchema($ledgerDescriptor['steps'][0]['expected_schema']);
		if (!$ledgerInspection['exists'] || !$ledgerInspection['matches']) {
			throw new ClinicalSchemaException('ledger_not_ready', 'Clinical migration ledger must be initialized and valid.');
		}
		$this->assertLedgerBootstrapReady($ledgerDescriptor, $options);
		if ($descriptor['steps'][0]['type'] !== 'create_table') {
			return $this->applyMutationMigration($command, $descriptor, $options);
		}
		$this->assertChecksumUnique($descriptor);
		$row = $this->ledgerRow($descriptor['migration_id']);
		if ($row) {
			$this->assertExistingMigrationIdentity($row, $descriptor, $options, $command === 'resume');
		}
		if ($row && (string) $row['state'] === 'applied') {
			if ($command === 'resume') {
				throw new ClinicalSchemaException('resume_applied_rejected', 'Applied migration cannot be resumed.');
			}
			$this->assertAppliedCompletion($row, $descriptor);
			$this->verifyDescriptorEffectiveTables($descriptor, $options, 'applied_migration_schema_drift');
			return array('state' => 'applied', 'step_count' => count($descriptor['steps']), 'ddl_executed' => false, 'already_applied' => true);
		}
		if ($command === 'apply' && $row) {
			throw new ClinicalSchemaException('migration_requires_resume', 'Interrupted or failed migration requires resume.', array('state' => (string) $row['state']));
		}
		if ($command === 'resume' && (!$row || !in_array((string) $row['state'], array('applying', 'failed'), true))) {
			throw new ClinicalSchemaException('migration_not_resumable', 'Migration is not in a resumable state.');
		}

		$now = $this->now();
		$attemptStarted = false;
		try {
			if (!$row) {
				$this->ledgerTransaction(function () use ($descriptor, $options, $now) {
					$affected = $this->connection->executePrepared(
					"INSERT INTO `clinical_schema_migrations` (migration_id,migration_name,migration_checksum,state,attempt_count,statement_count,last_completed_step,execution_environment,target_database,server_version,tool_version,executor_identity,backup_reference,started_at,applied_at,failed_at,error_code,error_summary,created_at,updated_at) VALUES (?,?,?,'applying',1,?,0,?,?,?,?,?,?,?,NULL,NULL,NULL,NULL,?,?)",
					'sssisssssssss',
					array(
						$descriptor['migration_id'], $descriptor['migration_name'], $descriptor['checksum'], count($descriptor['steps']),
						$options['environment'], $options['confirm_database'], $this->connection->getServerVersion(),
						$this->config['tool_version'], $this->connection->getExecutorIdentityHash(), $options['backup_reference'],
						$now, $now, $now,
					)
					);
					$this->requireOneAffected($affected, 'migration_insert');
				});
				$lastCompleted = 0;
				$attemptStarted = true;
			} else {
				$priorState = (string) $row['state'];
				$this->ledgerTransaction(function () use ($descriptor, $now, $priorState) {
					$affected = $this->connection->executePrepared(
						"UPDATE `clinical_schema_migrations` SET state='applying', attempt_count=attempt_count+1, failed_at=NULL, error_code=NULL, error_summary=NULL, updated_at=? WHERE migration_id=? AND state=?",
						'sss',
						array($now, $descriptor['migration_id'], $priorState)
					);
					$this->requireOneAffected($affected, 'resume_transition');
				});
				$lastCompleted = (int) $row['last_completed_step'];
				$attemptStarted = true;
			}

			$ddlExecuted = false;
			foreach ($descriptor['steps'] as $index => $step) {
			$stepNumber = $index + 1;
			if ($stepNumber <= $lastCompleted) {
				$inspection = $this->inspectExpectedSchema($step['expected_schema']);
				if (!$inspection['exists'] || !$inspection['matches']) {
					throw new ClinicalSchemaException('completed_step_schema_mismatch', 'Previously completed migration step no longer matches.', array('step_id' => $step['step_id']));
				}
				continue;
			}

			$inspection = $this->inspectExpectedSchema($step['expected_schema']);
			if ($inspection['exists'] && !$inspection['matches']) {
				throw new ClinicalSchemaException('migration_table_collision', 'Existing table conflicts with migration fingerprint.', array('table_name' => $step['expected_schema']['table_name'], 'difference_count' => count($inspection['differences'])));
			}
			if (!$inspection['exists']) {
				$this->connection->executeDdl($step['sql_bytes']);
				$ddlExecuted = true;
			}
			$post = $this->inspectExpectedSchema($step['expected_schema']);
			if (!$post['exists'] || !$post['matches']) {
				throw new ClinicalSchemaException('migration_step_verification_failed', 'Migration step failed exact schema verification.', array('step_id' => $step['step_id'], 'difference_count' => count($post['differences'])));
			}
				$this->ledgerTransaction(function () use ($stepNumber, $descriptor) {
					$affected = $this->connection->executePrepared(
					"UPDATE `clinical_schema_migrations` SET last_completed_step=?, updated_at=? WHERE migration_id=? AND state='applying'",
					'iss',
					array($stepNumber, $this->now(), $descriptor['migration_id'])
					);
					$this->requireOneAffected($affected, 'checkpoint_update');
				});
			}

			$appliedAt = $this->now();
			$this->ledgerTransaction(function () use ($appliedAt, $descriptor) {
				$affected = $this->connection->executePrepared(
				"UPDATE `clinical_schema_migrations` SET state='applied', last_completed_step=statement_count, applied_at=?, failed_at=NULL, error_code=NULL, error_summary=NULL, updated_at=? WHERE migration_id=?",
				'sss',
				array($appliedAt, $appliedAt, $descriptor['migration_id'])
				);
				$this->requireOneAffected($affected, 'final_applied_transition');
			});
			return array('state' => 'applied', 'step_count' => count($descriptor['steps']), 'ddl_executed' => $ddlExecuted, 'already_applied' => false);
		} catch (Throwable $exception) {
			if ($attemptStarted) {
				$this->markFailed($descriptor['migration_id'], $exception);
			}
			throw $exception;
		}
	}

	private function applyMutationMigration($command, array $descriptor, array $options)
	{
		$catalog = $this->validatedCatalog($options, true);
		$this->assertRequiredAppliedMigrations($descriptor, $catalog, $options);
		$this->assertChecksumUnique($descriptor);
		$row = $this->ledgerRow($descriptor['migration_id']);
		if ($row) {
			$this->assertExistingMigrationIdentity($row, $descriptor, $options, $command === 'resume');
		}
		if ($row && (string) $row['state'] === 'applied') {
			if ($command === 'resume') {
				throw new ClinicalSchemaException('resume_applied_rejected', 'Applied migration cannot be resumed.');
			}
			$this->assertAppliedCompletion($row, $descriptor);
			$this->verifyDescriptorEffectiveTables($descriptor, $options, 'applied_migration_schema_drift', $catalog);
			return array(
				'state' => 'applied', 'step_count' => count($descriptor['steps']), 'ddl_executed' => false,
				'already_applied' => true, 'created_tables' => array(), 'modified_tables' => $descriptor['expected_modified_tables'],
			);
		}
		if ($command === 'apply' && $row) {
			throw new ClinicalSchemaException('migration_requires_resume', 'Interrupted or failed migration requires resume.', array('state' => (string) $row['state']));
		}
		if ($command === 'resume' && (!$row || !in_array((string) $row['state'], array('applying', 'failed'), true))) {
			throw new ClinicalSchemaException('migration_not_resumable', 'Mutation migration is not in a resumable state.');
		}

		$actualStates = array();
		foreach ($descriptor['steps'] as $index => $step) {
			$predecessor = $this->nodeKey($step['schema_lineage']['predecessor_migration_id'], $step['schema_lineage']['predecessor_step_id']);
			$effective = $this->resolveEffectiveSchema($step['table_name'], $catalog, $options, $descriptor['migration_id']);
			if ($effective['node_key'] !== $predecessor) {
				throw new ClinicalSchemaException('mutation_predecessor_not_effective', 'Mutation predecessor is not the latest applied effective schema.', array('table_name' => $step['table_name']));
			}
			$before = $this->inspectExpectedSchema($step['expected_before_schema']);
			$after = $this->inspectExpectedSchema($step['expected_after_schema']);
			$completed = $row ? (int) $row['last_completed_step'] : 0;
			if ($index + 1 <= $completed) {
				if (!$after['exists'] || !$after['matches']) {
					throw new ClinicalSchemaException('completed_step_schema_mismatch', 'Previously completed mutation step no longer matches its after-schema.', array('step_id' => $step['step_id']));
				}
				$actualStates[$index] = 'after';
			} elseif ($after['exists'] && $after['matches']) {
				if (!$row) {
					throw new ClinicalSchemaException('unledgered_schema_mutation', 'Expected-after schema exists without a migration ledger attempt.', array('table_name' => $step['table_name']));
				}
				$actualStates[$index] = 'after';
			} elseif ($before['exists'] && $before['matches']) {
				$actualStates[$index] = 'before';
			} else {
				throw new ClinicalSchemaException('mutation_schema_state_unknown', 'Mutation target matches neither exact before nor exact after schema.', array('table_name' => $step['table_name']));
			}
		}
		$this->assertEmptyTablePreconditions($descriptor);

		$now = $this->now();
		$attemptStarted = false;
		try {
			if (!$row) {
				$this->ledgerTransaction(function () use ($descriptor, $options, $now) {
					$affected = $this->connection->executePrepared(
						"INSERT INTO `clinical_schema_migrations` (migration_id,migration_name,migration_checksum,state,attempt_count,statement_count,last_completed_step,execution_environment,target_database,server_version,tool_version,executor_identity,backup_reference,started_at,applied_at,failed_at,error_code,error_summary,created_at,updated_at) VALUES (?,?,?,'applying',1,?,0,?,?,?,?,?,?,?,NULL,NULL,NULL,NULL,?,?)",
						'sssisssssssss',
						array(
							$descriptor['migration_id'], $descriptor['migration_name'], $descriptor['checksum'], count($descriptor['steps']),
							$options['environment'], $options['confirm_database'], $this->connection->getServerVersion(),
							$this->config['tool_version'], $this->connection->getExecutorIdentityHash(), $options['backup_reference'],
							$now, $now, $now,
						)
					);
					$this->requireOneAffected($affected, 'migration_insert');
				});
				$lastCompleted = 0;
			} else {
				$lastCompleted = (int) $row['last_completed_step'];
				$priorState = (string) $row['state'];
				$this->ledgerTransaction(function () use ($descriptor, $now, $priorState) {
					$affected = $this->connection->executePrepared(
						"UPDATE `clinical_schema_migrations` SET state='applying', attempt_count=attempt_count+1, failed_at=NULL, error_code=NULL, error_summary=NULL, updated_at=? WHERE migration_id=? AND state=?",
						'sss', array($now, $descriptor['migration_id'], $priorState)
					);
					$this->requireOneAffected($affected, 'resume_transition');
				});
			}
			$attemptStarted = true;
			$ddlExecuted = false;
			foreach ($descriptor['steps'] as $index => $step) {
				$stepNumber = $index + 1;
				if ($stepNumber <= $lastCompleted) {
					continue;
				}
				if ($actualStates[$index] === 'before') {
					$this->connection->executeDdl($step['sql_bytes']);
					$ddlExecuted = true;
				}
				$post = $this->inspectExpectedSchema($step['expected_after_schema']);
				if (!$post['exists'] || !$post['matches']) {
					throw new ClinicalSchemaException('migration_step_verification_failed', 'Mutation step failed exact after-schema verification.', array('step_id' => $step['step_id'], 'difference_count' => count($post['differences'])));
				}
				$this->ledgerTransaction(function () use ($stepNumber, $descriptor) {
					$affected = $this->connection->executePrepared(
						"UPDATE `clinical_schema_migrations` SET last_completed_step=?, updated_at=? WHERE migration_id=? AND state='applying'",
						'iss', array($stepNumber, $this->now(), $descriptor['migration_id'])
					);
					$this->requireOneAffected($affected, 'checkpoint_update');
				});
			}
			$appliedAt = $this->now();
			$this->ledgerTransaction(function () use ($appliedAt, $descriptor) {
				$affected = $this->connection->executePrepared(
					"UPDATE `clinical_schema_migrations` SET state='applied', last_completed_step=statement_count, applied_at=?, failed_at=NULL, error_code=NULL, error_summary=NULL, updated_at=? WHERE migration_id=?",
					'sss', array($appliedAt, $appliedAt, $descriptor['migration_id'])
				);
				$this->requireOneAffected($affected, 'final_applied_transition');
			});
			return array(
				'state' => 'applied', 'step_count' => count($descriptor['steps']), 'ddl_executed' => $ddlExecuted,
				'already_applied' => false, 'created_tables' => array(), 'modified_tables' => $descriptor['expected_modified_tables'],
			);
		} catch (Throwable $exception) {
			if ($attemptStarted) {
				$this->markFailed($descriptor['migration_id'], $exception);
			}
			throw $exception;
		}
	}

	private function assertRequiredAppliedMigrations(array $descriptor, array $catalog, array $options)
	{
		foreach ($descriptor['required_applied_migrations'] as $dependency) {
			$id = $dependency['migration_id'];
			if (!isset($catalog['descriptors'][$id]) || !hash_equals($dependency['checksum'], $catalog['descriptors'][$id]['checksum'])) {
				throw new ClinicalSchemaException('migration_dependency_source_mismatch', 'Required migration source is missing or has the wrong checksum.', array('migration_id' => $id));
			}
			$row = $this->ledgerRow($id);
			if (!$row) {
				throw new ClinicalSchemaException('migration_dependency_missing', 'Required migration is not recorded.', array('migration_id' => $id));
			}
			$this->assertExistingMigrationIdentity($row, $catalog['descriptors'][$id], $options, false);
			if ((string) $row['state'] !== 'applied') {
				throw new ClinicalSchemaException('migration_dependency_not_applied', 'Required migration is not applied.', array('migration_id' => $id, 'state' => (string) $row['state']));
			}
			$this->assertAppliedCompletion($row, $catalog['descriptors'][$id]);
		}
	}

	private function assertEmptyTablePreconditions(array $descriptor)
	{
		foreach ($descriptor['preconditions']['tables_must_be_empty'] as $table) {
			$rows = $this->connection->queryAll("SELECT 1 AS row_exists FROM `" . $table . "` LIMIT 1");
			if (count($rows) > 0) {
				throw new ClinicalSchemaException('migration_precondition_failed', 'Required table is not empty.', array('table_name' => $table, 'ddl_executed' => false, 'ledger_row_created' => false));
			}
		}
	}

	private function assertChecksumUnique(array $descriptor)
	{
		$ledgerDescriptor = $this->descriptorLoader->loadById($this->config['ledger_migration_id']);
		$inspection = $this->inspectExpectedSchema($ledgerDescriptor['steps'][0]['expected_schema']);
		if (!$inspection['exists']) {
			return;
		}
		$row = $this->connection->queryOnePrepared(
			"SELECT migration_id FROM `clinical_schema_migrations` WHERE migration_checksum=? AND migration_id<>? LIMIT 1",
			'ss',
			array($descriptor['checksum'], $descriptor['migration_id'])
		);
		if ($row) {
			throw new ClinicalSchemaException('duplicate_migration_checksum', 'Migration checksum is already recorded under another migration ID.');
		}
	}

	private function ledgerRow($migrationId)
	{
		return $this->connection->queryOnePrepared(
			"SELECT migration_id,migration_name,migration_checksum,state,attempt_count,statement_count,last_completed_step,execution_environment,target_database,server_version,tool_version,executor_identity,backup_reference,started_at,applied_at,failed_at FROM `clinical_schema_migrations` WHERE migration_id=? LIMIT 1",
			's',
			array($migrationId)
		);
	}

	private function assertLedgerDescriptorShape(array $descriptor)
	{
		$valid = $descriptor['migration_id'] === $this->config['ledger_migration_id']
			&& count($descriptor['expected_created_tables']) === 1
			&& $descriptor['expected_created_tables'][0] === $this->config['ledger_table']
			&& count($descriptor['steps']) === 1
			&& $descriptor['steps'][0]['type'] === 'create_table'
			&& $descriptor['steps'][0]['expected_schema']['table_name'] === $this->config['ledger_table'];
		if (!$valid) {
			throw new ClinicalSchemaException('ledger_descriptor_shape_invalid', 'Ledger descriptor must declare exactly the configured bootstrap table and step.');
		}
	}

	private function assertLedgerBootstrapReady(array $descriptor, array $options)
	{
		$row = $this->ledgerRow($this->config['ledger_migration_id']);
		if (!$row) {
			throw new ClinicalSchemaException('ledger_bootstrap_record_missing', 'The ledger bootstrap record is required before normal migration operations.');
		}
		$this->assertExistingMigrationIdentity($row, $descriptor, $options, false);
		if ((string) $row['state'] !== 'applied') {
			throw new ClinicalSchemaException('ledger_bootstrap_not_applied', 'The ledger bootstrap record must be applied.');
		}
		$this->assertAppliedCompletion($row, $descriptor);
	}

	private function assertExistingMigrationIdentity(array $row, array $descriptor, array $options, $requireBackupMatch)
	{
		if (!isset($row['migration_id']) || (string) $row['migration_id'] !== $descriptor['migration_id']) {
			$this->identityMismatch('migration_id');
		}
		if (!isset($row['migration_name']) || (string) $row['migration_name'] !== $descriptor['migration_name']) {
			$this->identityMismatch('migration_name');
		}
		if (!isset($row['migration_checksum']) || !hash_equals((string) $row['migration_checksum'], $descriptor['checksum'])) {
			throw new ClinicalSchemaException('applied_checksum_mismatch', 'Recorded migration checksum does not match source.');
		}
		if (!isset($row['statement_count']) || (int) $row['statement_count'] !== count($descriptor['steps'])) {
			$this->identityMismatch('statement_count');
		}
		if (!isset($row['execution_environment']) || (string) $row['execution_environment'] !== (string) $options['environment']) {
			$this->identityMismatch('execution_environment');
		}
		$authenticatedDatabase = (string) $this->connection->getDatabaseName();
		if (!isset($row['target_database']) || (string) $row['target_database'] !== (string) $options['confirm_database'] || (string) $row['target_database'] !== $authenticatedDatabase) {
			$this->identityMismatch('target_database');
		}
		$validStates = array('applying', 'applied', 'failed', 'rolling_back', 'rolled_back');
		if (!isset($row['state']) || !in_array((string) $row['state'], $validStates, true)) {
			$this->identityMismatch('state');
		}
		$lastCompleted = isset($row['last_completed_step']) ? (int) $row['last_completed_step'] : -1;
		if ($lastCompleted < 0 || $lastCompleted > (int) $row['statement_count']) {
			$this->identityMismatch('last_completed_step');
		}
		if (!isset($row['backup_reference']) || (string) $row['backup_reference'] === '') {
			$this->identityMismatch('backup_reference');
		}
		if ($requireBackupMatch && !hash_equals((string) $row['backup_reference'], (string) $options['backup_reference'])) {
			throw new ClinicalSchemaException('backup_reference_mismatch', 'Resume or recovery backup reference does not match the initial migration attempt.');
		}
		foreach (array('server_version', 'executor_identity', 'started_at') as $field) {
			if (!isset($row[$field]) || (string) $row[$field] === '') {
				$this->identityMismatch($field);
			}
		}
	}

	private function assertAppliedCompletion(array $row, array $descriptor)
	{
		$stepCount = count($descriptor['steps']);
		if ((int) $row['statement_count'] !== $stepCount || (int) $row['last_completed_step'] !== $stepCount) {
			throw new ClinicalSchemaException('applied_migration_incomplete', 'Applied migration ledger completion does not match its descriptor.');
		}
	}

	private function validatedCatalog(?array $options = null, $checkLedgerSources = false)
	{
		$descriptors = $this->descriptorLoader->loadCatalog();
		$nodes = array();
		$origins = array();
		$successors = array();
		$stepOrders = array();
		foreach ($descriptors as $migrationId => $descriptor) {
			foreach ($descriptor['required_applied_migrations'] as $dependency) {
				$id = $dependency['migration_id'];
				if (!isset($descriptors[$id]) || !hash_equals($dependency['checksum'], $descriptors[$id]['checksum'])) {
					throw new ClinicalSchemaException('migration_dependency_source_mismatch', 'Required migration descriptor is missing or does not match its declared checksum.', array('migration_id' => $id));
				}
			}
			foreach ($descriptor['steps'] as $index => $step) {
				$key = $this->nodeKey($migrationId, $step['step_id']);
				$table = $step['type'] === 'create_table' ? $step['expected_schema']['table_name'] : $step['table_name'];
				$nodes[$key] = array(
					'key' => $key, 'migration_id' => $migrationId, 'step_id' => $step['step_id'],
					'step_index' => $index, 'table_name' => $table, 'type' => $step['type'],
					'before_schema' => $step['type'] === 'create_table' ? null : $step['expected_before_schema'],
					'after_schema' => $step['type'] === 'create_table' ? $step['expected_schema'] : $step['expected_after_schema'],
					'predecessor' => $step['type'] === 'create_table' ? null : $this->nodeKey($step['schema_lineage']['predecessor_migration_id'], $step['schema_lineage']['predecessor_step_id']),
				);
				$stepOrders[$key] = array($migrationId, $index);
				if ($step['type'] === 'create_table') {
					if (isset($origins[$table])) {
						throw new ClinicalSchemaException('schema_lineage_origin_duplicate', 'A table has more than one create-table origin.', array('table_name' => $table));
					}
					$origins[$table] = $key;
				}
			}
		}
		foreach ($nodes as $key => $node) {
			if ($node['type'] === 'create_table') {
				continue;
			}
			$predecessor = $node['predecessor'];
			if (!isset($nodes[$predecessor])) {
				throw new ClinicalSchemaException('schema_lineage_predecessor_unknown', 'Mutation lineage predecessor is unknown.', array('step_id' => $node['step_id']));
			}
			if ($nodes[$predecessor]['table_name'] !== $node['table_name']) {
				throw new ClinicalSchemaException('schema_lineage_table_mismatch', 'Mutation lineage predecessor addresses a different table.');
			}
			$previousOrder = $stepOrders[$predecessor];
			$currentOrder = $stepOrders[$key];
			if ($previousOrder[0] > $currentOrder[0] || ($previousOrder[0] === $currentOrder[0] && $previousOrder[1] >= $currentOrder[1])) {
				throw new ClinicalSchemaException('schema_lineage_order_invalid', 'Mutation lineage must move strictly forward.');
			}
			if (isset($successors[$predecessor])) {
				throw new ClinicalSchemaException('schema_lineage_fork', 'Schema lineage may not fork.', array('predecessor' => $predecessor));
			}
			if ($this->normalizeExpectedSchema($nodes[$predecessor]['after_schema']) !== $this->normalizeExpectedSchema($node['before_schema'])) {
				throw new ClinicalSchemaException('schema_lineage_before_mismatch', 'Mutation before-schema does not equal predecessor after-schema.');
			}
			$successors[$predecessor] = $key;
		}
		foreach ($nodes as $key => $node) {
			$seen = array();
			$current = $key;
			while (isset($successors[$current])) {
				if (isset($seen[$current])) {
					throw new ClinicalSchemaException('schema_lineage_cycle', 'Schema lineage contains a cycle.');
				}
				$seen[$current] = true;
				$current = $successors[$current];
			}
		}
		$catalog = array('descriptors' => $descriptors, 'nodes' => $nodes, 'origins' => $origins, 'successors' => $successors);
		if ($checkLedgerSources && $this->connection !== null) {
			$rows = $this->connection->queryAll("SELECT migration_id,migration_name,migration_checksum,state,attempt_count,statement_count,last_completed_step,execution_environment,target_database,server_version,tool_version,executor_identity,backup_reference,started_at,applied_at,failed_at FROM `clinical_schema_migrations` ORDER BY migration_id");
			foreach ($rows as $row) {
				$id = isset($row['migration_id']) ? (string) $row['migration_id'] : '';
				if (!isset($descriptors[$id])) {
					throw new ClinicalSchemaException('applied_migration_descriptor_missing', 'Recorded migration source descriptor is unavailable.', array('migration_id' => $id));
				}
			}
		}
		return $catalog;
	}

	private function resolveEffectiveSchema($table, array $catalog, array $options, $excludedMigrationId = null)
	{
		if (!isset($catalog['origins'][$table])) {
			throw new ClinicalSchemaException('schema_lineage_origin_missing', 'Table has no create-table lineage origin.', array('table_name' => $table));
		}
		$currentKey = $catalog['origins'][$table];
		$current = $catalog['nodes'][$currentKey];
		$originDescriptor = $catalog['descriptors'][$current['migration_id']];
		$originRow = $this->ledgerRow($current['migration_id']);
		if (!$originRow) {
			throw new ClinicalSchemaException('schema_lineage_origin_not_applied', 'Table origin migration is not recorded.', array('table_name' => $table));
		}
		$this->assertExistingMigrationIdentity($originRow, $originDescriptor, $options, false);
		if ((string) $originRow['state'] !== 'applied') {
			throw new ClinicalSchemaException('schema_lineage_origin_not_applied', 'Table origin migration is not applied.', array('table_name' => $table));
		}
		$this->assertAppliedCompletion($originRow, $originDescriptor);
		while (isset($catalog['successors'][$currentKey])) {
			$nextKey = $catalog['successors'][$currentKey];
			$next = $catalog['nodes'][$nextKey];
			if ($next['migration_id'] === $excludedMigrationId) {
				break;
			}
			$row = $this->ledgerRow($next['migration_id']);
			if (!$row) {
				$descendantKey = $nextKey;
				while (isset($catalog['successors'][$descendantKey])) {
					$descendantKey = $catalog['successors'][$descendantKey];
					$descendant = $catalog['nodes'][$descendantKey];
					if ($this->ledgerRow($descendant['migration_id'])) {
						throw new ClinicalSchemaException('schema_lineage_applied_descendant_without_predecessor', 'A mutation descendant is recorded without its predecessor.');
					}
				}
				break;
			}
			$nextDescriptor = $catalog['descriptors'][$next['migration_id']];
			$this->assertExistingMigrationIdentity($row, $nextDescriptor, $options, false);
			if ((string) $row['state'] !== 'applied') {
				throw new ClinicalSchemaException('schema_lineage_mutation_incomplete', 'Relevant mutation migration is not completely applied.', array('migration_id' => $next['migration_id'], 'state' => (string) $row['state']));
			}
			$this->assertAppliedCompletion($row, $nextDescriptor);
			$currentKey = $nextKey;
			$current = $next;
		}
		return array(
			'schema' => $current['after_schema'], 'node_key' => $currentKey,
			'migration_id' => $current['migration_id'], 'step_id' => $current['step_id'],
		);
	}

	private function descriptorTables(array $descriptor)
	{
		return count($descriptor['expected_modified_tables']) > 0
			? $descriptor['expected_modified_tables']
			: $descriptor['expected_created_tables'];
	}

	private function nodeKey($migrationId, $stepId)
	{
		return (string) $migrationId . '::' . (string) $stepId;
	}

	private function verifyDescriptorEffectiveTables(array $descriptor, array $options, $errorCode, ?array $catalog = null)
	{
		$catalog = $catalog === null ? $this->validatedCatalog($options, true) : $catalog;
		foreach ($this->descriptorTables($descriptor) as $table) {
			$effective = $this->resolveEffectiveSchema($table, $catalog, $options);
			$inspection = $this->inspectExpectedSchema($effective['schema']);
			if (!$inspection['exists'] || !$inspection['matches']) {
				throw new ClinicalSchemaException($errorCode, 'Migration effective schema fingerprint does not match.', array(
					'table_name' => $table, 'difference_count' => count($inspection['differences']),
					'effective_migration_id' => $effective['migration_id'], 'effective_step_id' => $effective['step_id'],
				));
			}
		}
	}

	private function verifyDescriptorTables(array $descriptor, $errorCode)
	{
		foreach ($descriptor['steps'] as $step) {
			$inspection = $this->inspectExpectedSchema($step['expected_schema']);
			if (!$inspection['exists'] || !$inspection['matches']) {
				throw new ClinicalSchemaException($errorCode, 'Migration schema fingerprint does not match.', array(
					'table_name' => $step['expected_schema']['table_name'],
					'difference_count' => count($inspection['differences']),
				));
			}
		}
	}

	private function identityMismatch($field)
	{
		throw new ClinicalSchemaException('migration_ledger_identity_mismatch', 'Migration ledger immutable identity does not match.', array('field' => $field));
	}

	private function markFailed($migrationId, Throwable $exception)
	{
		$code = $exception instanceof ClinicalSchemaException ? $exception->getSafeCode() : 'internal_migration_failure';
		$summary = $exception instanceof ClinicalSchemaException ? $exception->getMessage() : 'Internal migration operation failed.';
		$summary = substr(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $summary), 0, 1000);
		$now = $this->now();
		$this->ledgerTransaction(function () use ($now, $code, $summary, $migrationId) {
			$affected = $this->connection->executePrepared(
				"UPDATE `clinical_schema_migrations` SET state='failed', failed_at=?, error_code=?, error_summary=?, updated_at=? WHERE migration_id=? AND state='applying'",
				'sssss',
				array($now, $code, $summary, $now, $migrationId)
			);
			$this->requireOneAffected($affected, 'failure_transition');
		});
	}

	private function requireOneAffected($affected, $transition)
	{
		if ((int) $affected !== 1) {
			throw new ClinicalSchemaException('ledger_state_transition_failed', 'Ledger state transition did not affect exactly one row.', array('transition' => $transition));
		}
	}

	private function ledgerTransaction(callable $operation)
	{
		$this->connection->begin();
		try {
			$result = $operation();
			$this->connection->commit();
			return $result;
		} catch (Throwable $exception) {
			$this->connection->rollback();
			throw $exception;
		}
	}

	public function inspectExpectedSchema(array $expected)
	{
		$table = $expected['table_name'];
		$tableRow = $this->connection->queryOnePrepared(
			"SELECT ENGINE,TABLE_COLLATION,ROW_FORMAT,CREATE_OPTIONS,TABLE_COMMENT,AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND TABLE_TYPE='BASE TABLE'",
			's',
			array($table)
		);
		if (!$tableRow) {
			return array('exists' => false, 'matches' => false, 'differences' => array('table_missing'));
		}

		$actual = array(
			'table_name' => $table,
			'engine' => (string) $tableRow['ENGINE'],
			'default_charset' => $this->charsetFromCollation((string) $tableRow['TABLE_COLLATION']),
			'default_collation' => (string) $tableRow['TABLE_COLLATION'],
			'columns' => array(),
			'primary_key' => array(),
			'unique_indexes' => array(),
			'indexes' => array(),
			'foreign_keys' => array(),
			'check_constraints' => array(),
			'index_details' => array(),
			'table_features' => array(
				'create_options' => trim((string) $tableRow['CREATE_OPTIONS']),
				'table_comment' => (string) $tableRow['TABLE_COMMENT'],
				'partitioned' => false,
				'tablespace' => null,
			),
			'observed_row_format' => (string) $tableRow['ROW_FORMAT'],
		);

		$partitions = $this->connection->queryPrepared(
			"SELECT PARTITION_NAME,TABLESPACE_NAME FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY PARTITION_ORDINAL_POSITION",
			's',
			array($table)
		);
		foreach ($partitions as $partition) {
			if ($partition['PARTITION_NAME'] !== null && (string) $partition['PARTITION_NAME'] !== '') {
				$actual['table_features']['partitioned'] = true;
			}
			$tablespace = $partition['TABLESPACE_NAME'] === null ? '' : (string) $partition['TABLESPACE_NAME'];
			if ($tablespace !== '' && strtolower($tablespace) !== 'innodb_system') {
				$actual['table_features']['tablespace'] = $tablespace;
			}
		}

		$columns = $this->connection->queryPrepared(
			"SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME,COLUMN_COMMENT,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION",
			's',
			array($table)
		);
		foreach ($columns as $column) {
			$actual['columns'][] = array(
				'name' => (string) $column['COLUMN_NAME'],
				'column_type' => strtolower((string) $column['COLUMN_TYPE']),
				'nullable' => strtoupper((string) $column['IS_NULLABLE']) === 'YES',
				'default' => $this->normalizeDefault($column['COLUMN_DEFAULT']),
				'extra' => $this->normalizeExtra((string) $column['EXTRA']),
				'character_set' => $column['CHARACTER_SET_NAME'] === null ? null : (string) $column['CHARACTER_SET_NAME'],
				'collation' => $column['COLLATION_NAME'] === null ? null : (string) $column['COLLATION_NAME'],
				'comment' => (string) $column['COLUMN_COMMENT'],
				'generation_expression' => $this->normalizeGenerationExpression((string) $column['GENERATION_EXPRESSION']),
			);
		}

		$statistics = $this->connection->queryPrepared(
			"SELECT INDEX_NAME,NON_UNIQUE,INDEX_TYPE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,COLLATION,IGNORED FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX",
			's',
			array($table)
		);
		$groupedIndexes = array();
		foreach ($statistics as $statistic) {
			$name = (string) $statistic['INDEX_NAME'];
			if (!isset($groupedIndexes[$name])) {
				$groupedIndexes[$name] = array(
					'name' => $name,
					'non_unique' => (int) $statistic['NON_UNIQUE'],
					'index_type' => strtoupper((string) $statistic['INDEX_TYPE']),
					'columns' => array(),
					'sub_parts' => array(),
					'collations' => array(),
					'ignored' => false,
				);
			}
			$groupedIndexes[$name]['columns'][] = (string) $statistic['COLUMN_NAME'];
			$groupedIndexes[$name]['sub_parts'][] = $statistic['SUB_PART'] === null ? null : (int) $statistic['SUB_PART'];
			$groupedIndexes[$name]['collations'][] = $statistic['COLLATION'] === null ? null : strtoupper((string) $statistic['COLLATION']);
			if (strtoupper((string) $statistic['IGNORED']) === 'YES') {
				$groupedIndexes[$name]['ignored'] = true;
			}
		}
		ksort($groupedIndexes, SORT_STRING);
		foreach ($groupedIndexes as $name => $definition) {
			$actual['index_details'][] = $definition;
			if ($name === 'PRIMARY') {
				$actual['primary_key'] = $definition['columns'];
			} elseif ($definition['non_unique'] === 0) {
				$actual['unique_indexes'][] = array('name' => $name, 'columns' => $definition['columns']);
			} else {
				$actual['indexes'][] = array('name' => $name, 'columns' => $definition['columns']);
			}
		}

		$foreignKeys = $this->connection->queryPrepared(
			"SELECT k.CONSTRAINT_NAME,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE,k.ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.CONSTRAINT_NAME,k.ORDINAL_POSITION",
			's',
			array($table)
		);
		$groupedForeignKeys = array();
		foreach ($foreignKeys as $foreignKey) {
			$name = (string) $foreignKey['CONSTRAINT_NAME'];
			if (!isset($groupedForeignKeys[$name])) {
				$groupedForeignKeys[$name] = array(
					'name' => $name,
					'columns' => array(),
					'referenced_table' => (string) $foreignKey['REFERENCED_TABLE_NAME'],
					'referenced_columns' => array(),
					'on_update' => strtoupper((string) $foreignKey['UPDATE_RULE']),
					'on_delete' => strtoupper((string) $foreignKey['DELETE_RULE']),
				);
			}
			$groupedForeignKeys[$name]['columns'][] = (string) $foreignKey['COLUMN_NAME'];
			$groupedForeignKeys[$name]['referenced_columns'][] = (string) $foreignKey['REFERENCED_COLUMN_NAME'];
		}
		ksort($groupedForeignKeys, SORT_STRING);
		$actual['foreign_keys'] = array_values($groupedForeignKeys);

		$checks = $this->connection->queryPrepared(
			"SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.CONSTRAINT_SCHEMA=DATABASE() AND tc.TABLE_NAME=? AND tc.CONSTRAINT_TYPE='CHECK' ORDER BY tc.CONSTRAINT_NAME",
			's',
			array($table)
		);
		foreach ($checks as $check) {
			$actual['check_constraints'][] = array(
				'name' => (string) $check['CONSTRAINT_NAME'],
				'expression' => $this->normalizeCheck((string) $check['CHECK_CLAUSE']),
			);
		}
		usort($actual['check_constraints'], array($this, 'compareNamedDefinition'));

		$normalizedExpected = $this->normalizeExpectedSchema($expected);
		$differences = $this->schemaDifferences($normalizedExpected, $actual);
		return array('exists' => true, 'matches' => count($differences) === 0, 'differences' => $differences, 'actual' => $actual);
	}

	private function normalizeExpectedSchema(array $expected)
	{
		$normalized = $expected;
		foreach ($normalized['columns'] as $index => $column) {
			$generationExpression = array_key_exists('generation_expression', $column) ? $column['generation_expression'] : '';
			unset($normalized['columns'][$index]['generation_expression']);
			$normalized['columns'][$index]['column_type'] = strtolower($column['column_type']);
			$normalized['columns'][$index]['default'] = $this->normalizeDefault($column['default']);
			$normalized['columns'][$index]['extra'] = $this->normalizeExtra($column['extra']);
			$normalized['columns'][$index]['comment'] = '';
			$normalized['columns'][$index]['generation_expression'] = $this->normalizeGenerationExpression($generationExpression);
		}
		foreach ($normalized['check_constraints'] as $index => $check) {
			$normalized['check_constraints'][$index]['expression'] = $this->normalizeCheck($check['expression']);
		}
		usort($normalized['unique_indexes'], array($this, 'compareNamedDefinition'));
		usort($normalized['indexes'], array($this, 'compareNamedDefinition'));
		usort($normalized['foreign_keys'], array($this, 'compareNamedDefinition'));
		usort($normalized['check_constraints'], array($this, 'compareNamedDefinition'));
		$normalized['index_details'] = array();
		$normalized['index_details'][] = $this->expectedIndexDetail('PRIMARY', 0, $normalized['primary_key']);
		foreach ($normalized['unique_indexes'] as $index) {
			$normalized['index_details'][] = $this->expectedIndexDetail($index['name'], 0, $index['columns']);
		}
		foreach ($normalized['indexes'] as $index) {
			$normalized['index_details'][] = $this->expectedIndexDetail($index['name'], 1, $index['columns']);
		}
		usort($normalized['index_details'], array($this, 'compareNamedDefinition'));
		$normalized['table_features'] = array(
			'create_options' => '',
			'table_comment' => '',
			'partitioned' => false,
			'tablespace' => null,
		);
		return $normalized;
	}

	private function expectedIndexDetail($name, $nonUnique, array $columns)
	{
		return array(
			'name' => $name,
			'non_unique' => (int) $nonUnique,
			'index_type' => 'BTREE',
			'columns' => $columns,
			'sub_parts' => array_fill(0, count($columns), null),
			'collations' => array_fill(0, count($columns), 'A'),
			'ignored' => false,
		);
	}

	public function compareNamedDefinition($left, $right)
	{
		return strcmp((string) $left['name'], (string) $right['name']);
	}

	private function schemaDifferences(array $expected, array $actual)
	{
		$differences = array();
		foreach (array('table_name', 'engine', 'default_charset', 'default_collation', 'columns', 'primary_key', 'unique_indexes', 'indexes', 'foreign_keys', 'check_constraints', 'index_details', 'table_features') as $field) {
			if ($expected[$field] !== $actual[$field]) {
				$differences[] = $field;
			}
		}
		sort($differences, SORT_STRING);
		return $differences;
	}

	private function normalizeDefault($value)
	{
		if ($value === null) {
			return null;
		}
		$value = trim((string) $value);
		if (preg_match("/^'(?:''|[^'])*'$/s", $value) === 1) {
			return str_replace("''", "'", substr($value, 1, -1));
		}
		if (strcasecmp($value, 'NULL') === 0) {
			return null;
		}
		if (preg_match('/^current_timestamp(?:\([0-9]+\))?$/i', $value) === 1) {
			return strtolower($value);
		}
		return $value;
	}

	private function normalizeExtra($value)
	{
		$value = strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
		$value = trim(str_replace('default_generated', '', $value));
		return trim(preg_replace('/\s+/', ' ', $value));
	}

	private function normalizeGenerationExpression($value)
	{
		return $this->normalizeCheck((string) $value);
	}

	private function normalizeCheck($value)
	{
		$value = (string) $value;
		$normalized = '';
		$outside = '';
		$length = strlen($value);
		for ($index = 0; $index < $length;) {
			$char = $value[$index];
			if ($char !== "'") {
				if ($char !== '`') {
					$outside .= $char;
				}
				$index++;
				continue;
			}

			$normalized .= $this->normalizeCheckOutside($outside);
			$outside = '';
			$literal = "'";
			$index++;
			$closed = false;
			while ($index < $length) {
				$literalChar = $value[$index];
				$literal .= $literalChar;
				if ($literalChar === '\\' && $index + 1 < $length) {
					$index++;
					$literal .= $value[$index];
					$index++;
					continue;
				}
				if ($literalChar === "'") {
					if ($index + 1 < $length && $value[$index + 1] === "'") {
						$index++;
						$literal .= $value[$index];
						$index++;
						continue;
					}
					$closed = true;
					$index++;
					break;
				}
				$index++;
			}
			if (!$closed) {
				throw new ClinicalSchemaException('check_expression_quote_unclosed', 'CHECK expression contains an unclosed quoted literal.');
			}
			$normalized .= $literal;
		}
		$normalized .= $this->normalizeCheckOutside($outside);
		$normalized = trim($normalized);
		while (strlen($normalized) > 1 && $normalized[0] === '(' && substr($normalized, -1) === ')' && $this->outerParenthesesWrap($normalized)) {
			$normalized = trim(substr($normalized, 1, -1));
		}
		return $normalized;
	}

	private function normalizeCheckOutside($value)
	{
		$value = strtolower((string) $value);
		$value = preg_replace('/\s+/', ' ', $value);
		return preg_replace('/\s*([(),=<>+\-*\/%|&!])\s*/', '$1', $value);
	}

	private function outerParenthesesWrap($value)
	{
		$depth = 0;
		$quoted = false;
		for ($index = 0; $index < strlen($value); $index++) {
			$char = $value[$index];
			if ($quoted && $char === '\\' && $index + 1 < strlen($value)) {
				$index++;
				continue;
			}
			if ($char === "'") {
				if ($quoted && $index + 1 < strlen($value) && $value[$index + 1] === "'") {
					$index++;
					continue;
				}
				$quoted = !$quoted;
			}
			if ($quoted) {
				continue;
			}
			if ($char === '(') {
				$depth++;
			} elseif ($char === ')') {
				$depth--;
				if ($depth < 0) {
					return false;
				}
				if ($depth === 0 && $index < strlen($value) - 1) {
					return false;
				}
			}
		}
		return $depth === 0 && !$quoted;
	}

	private function charsetFromCollation($collation)
	{
		$position = strpos($collation, '_');
		return $position === false ? $collation : substr($collation, 0, $position);
	}

	private function now()
	{
		$date = new DateTimeImmutable('now', new DateTimeZone('UTC'));
		return $date->format('Y-m-d H:i:s.u');
	}
}
