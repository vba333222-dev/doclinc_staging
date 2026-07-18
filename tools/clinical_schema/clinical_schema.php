<?php

require_once __DIR__ . '/ClinicalSchemaException.php';
require_once __DIR__ . '/ClinicalSchemaCli.php';
require_once __DIR__ . '/ClinicalSchemaDescriptor.php';
require_once __DIR__ . '/ClinicalSchemaConnection.php';
require_once __DIR__ . '/ClinicalSchemaMigrator.php';
require_once __DIR__ . '/ClinicalSchemaReporter.php';

$requestedFormat = 'text';
if (isset($argv) && is_array($argv)) {
	foreach ($argv as $argument) {
		if ($argument === '--format=json') {
			$requestedFormat = 'json';
		}
	}
}

$report = ClinicalSchemaReporter::baseReport(isset($argv[1]) ? $argv[1] : 'error');
$connection = null;

try {
	ClinicalSchemaCli::assertCli(PHP_SAPI);
	$parsed = ClinicalSchemaCli::parse($argv);
	$command = $parsed['command'];
	$options = $parsed['options'];
	$requestedFormat = $options['format'];
	$report = ClinicalSchemaReporter::baseReport($command, $options['migration']);

	if ($command === 'help') {
		$report['summary'] = array(
			'commands' => 'help,status,plan,init,apply,resume,verify',
			'flags' => '--format,--environment,--confirm-database,--migration,--backup-reference,--confirm-backup',
			'usage' => 'php tools/clinical_schema/clinical_schema.php <command> [flags]',
			'schema_only' => true,
			'package_import_supported' => false,
			'runtime_enable_supported' => false,
		);
		ClinicalSchemaReporter::addCheck($report, 'help', 'PASS');
		$report = ClinicalSchemaReporter::finalize($report, 0, 'PASS');
		exit(ClinicalSchemaReporter::render($report, $requestedFormat));
	}

	$config = require __DIR__ . '/config.php';
	$descriptorLoader = new ClinicalSchemaDescriptor($config['migrations_directory']);

	if ($command === 'plan') {
		$descriptor = $descriptorLoader->loadById($options['migration']);
		$migrator = new ClinicalSchemaMigrator($descriptorLoader, null, $config);
		$result = $migrator->plan($descriptor);
		$report['migration']['name'] = $descriptor['migration_name'];
		$report['migration']['checksum'] = $descriptor['checksum'];
		$report['summary'] = $result;
		ClinicalSchemaReporter::addCheck($report, 'descriptor_and_sql_sources', 'PASS');
		ClinicalSchemaReporter::addCheck($report, 'schema_only_policy', 'PASS');
		ClinicalSchemaReporter::addCheck($report, 'ddl_not_executed', 'PASS');
		$report = ClinicalSchemaReporter::finalize($report, 0, 'PASS');
		exit(ClinicalSchemaReporter::render($report, $requestedFormat));
	}

	$writeRequired = in_array($command, array('init', 'apply', 'resume'), true);
	$minimumVersion = $config['minimum_mariadb_version'];
	$descriptor = null;
	if ($options['migration'] !== null) {
		$descriptor = $descriptorLoader->loadById($options['migration']);
		$minimumVersion = $descriptor['minimum_mariadb_version'];
		$report['migration']['name'] = $descriptor['migration_name'];
		$report['migration']['checksum'] = $descriptor['checksum'];
	}

	$connection = ClinicalSchemaConnection::fromEnvironment(
		$config,
		$options['confirm_database'],
		$minimumVersion,
		$writeRequired
	);
	$migrator = new ClinicalSchemaMigrator($descriptorLoader, $connection, $config);

	if ($command === 'status') {
		$result = $migrator->status($options);
		$report['summary'] = $result;
		ClinicalSchemaReporter::addCheck($report, 'status_read_only', 'PASS');
		if (!$result['ledger_initialized']) {
			ClinicalSchemaReporter::addWarning($report, 'ledger_not_initialized');
		}
	} elseif ($command === 'verify') {
		$result = $migrator->verify($descriptor, $options);
		$report['summary'] = $result;
		ClinicalSchemaReporter::addCheck($report, 'migration_schema_fingerprint', 'PASS');
		ClinicalSchemaReporter::addCheck($report, 'ddl_not_executed', 'PASS');
	} else {
		$result = $migrator->execute($command, $descriptor, $options);
		$report['summary'] = $result;
		ClinicalSchemaReporter::addCheck($report, 'migration_lifecycle', 'PASS');
		ClinicalSchemaReporter::addCheck($report, 'schema_only_policy', 'PASS');
		ClinicalSchemaReporter::addCheck($report, 'advisory_lock_released', 'PASS');
	}

	$report = ClinicalSchemaReporter::finalize($report, 0, 'PASS');
} catch (Throwable $exception) {
	$code = $exception instanceof ClinicalSchemaException ? $exception->getSafeCode() : 'internal_execution_failure';
	$context = $exception instanceof ClinicalSchemaException ? $exception->getSafeContext() : array();
	ClinicalSchemaReporter::addError($report, $code, $context);
	$usageCodes = array(
		'cli_only', 'invalid_command', 'unknown_flag', 'duplicate_flag', 'malformed_flag',
		'boolean_flag_assignment_rejected',
		'command_inappropriate_flag', 'unsupported_format', 'missing_migration_id',
		'invalid_migration_id', 'missing_environment', 'missing_database_confirmation',
		'missing_backup_reference', 'missing_backup_confirmation', 'missing_read_confirmation',
		'invalid_confirmation_value', 'unsafe_confirmation_value',
	);
	$executionCodes = array(
		'connection_configuration_missing', 'connection_port_invalid', 'database_confirmation_mismatch',
		'runtime_database_user_rejected', 'migration_database_user_not_allowed', 'database_password_unavailable',
		'mysqli_extension_unavailable', 'database_connection_failed', 'database_charset_failed',
		'authenticated_database_mismatch', 'authenticated_database_user_mismatch',
		'database_server_identity_unavailable', 'database_product_rejected', 'database_version_unparseable',
		'database_version_too_old', 'innodb_unavailable', 'database_privileges_insufficient',
		'database_metadata_query_failed', 'database_result_bind_failed',
		'advisory_lock_unavailable', 'advisory_lock_release_failed', 'internal_execution_failure',
	);
	$exitCode = in_array($code, $usageCodes, true) || in_array($code, $executionCodes, true) ? 2 : 1;
	$result = $exitCode === 2 ? 'ERROR' : 'FAIL';
	$report = ClinicalSchemaReporter::finalize($report, $exitCode, $result);
} finally {
	if ($connection !== null) {
		$connection->close();
	}
}

exit(ClinicalSchemaReporter::render($report, $requestedFormat));
