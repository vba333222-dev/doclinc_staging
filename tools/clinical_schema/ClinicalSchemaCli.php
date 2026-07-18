<?php

class ClinicalSchemaCli
{
	private static $commands = array('help', 'status', 'plan', 'init', 'apply', 'resume', 'verify');

	public static function assertCli($sapi)
	{
		if ($sapi !== 'cli') {
			throw new ClinicalSchemaException('cli_only', 'Standalone CLI execution required.');
		}
	}

	public static function parse(array $argv)
	{
		$command = isset($argv[1]) ? (string) $argv[1] : '';
		if (!in_array($command, self::$commands, true)) {
			throw new ClinicalSchemaException('invalid_command', 'Unknown or missing command.');
		}

		$options = array(
			'format' => 'text',
			'environment' => null,
			'confirm_database' => null,
			'migration' => null,
			'backup_reference' => null,
			'confirm_backup' => false,
		);
		$seen = array();

		for ($index = 2; $index < count($argv); $index++) {
			$argument = (string) $argv[$index];
			if ($argument === '--confirm-backup') {
				$key = 'confirm_backup';
				$value = true;
			} elseif (preg_match('/^--([a-z][a-z0-9-]*)=(.*)$/s', $argument, $match) === 1) {
				if ($match[1] === 'confirm-backup') {
					throw new ClinicalSchemaException('boolean_flag_assignment_rejected', 'Boolean flags must be supplied without a value.', array('flag' => 'confirm-backup'));
				}
				$key = str_replace('-', '_', $match[1]);
				$value = $match[2];
				if ($value === '') {
					throw new ClinicalSchemaException('malformed_flag', 'Flag values must not be empty.', array('flag' => $match[1]));
				}
			} else {
				throw new ClinicalSchemaException('malformed_flag', 'Malformed command argument.');
			}

			if (!array_key_exists($key, $options)) {
				throw new ClinicalSchemaException('unknown_flag', 'Unsupported flag.', array('flag' => str_replace('_', '-', $key)));
			}
			if (isset($seen[$key])) {
				throw new ClinicalSchemaException('duplicate_flag', 'Duplicate flag.', array('flag' => str_replace('_', '-', $key)));
			}
			$seen[$key] = true;
			$options[$key] = $value;
		}

		if (!in_array($options['format'], array('text', 'json'), true)) {
			throw new ClinicalSchemaException('unsupported_format', 'Format must be text or json.');
		}

		$allowed = array(
			'help' => array('format'),
			'status' => array('format', 'environment', 'confirm_database'),
			'plan' => array('format', 'migration'),
			'verify' => array('format', 'environment', 'confirm_database', 'migration'),
			'init' => array('format', 'environment', 'confirm_database', 'migration', 'backup_reference', 'confirm_backup'),
			'apply' => array('format', 'environment', 'confirm_database', 'migration', 'backup_reference', 'confirm_backup'),
			'resume' => array('format', 'environment', 'confirm_database', 'migration', 'backup_reference', 'confirm_backup'),
		);
		foreach ($seen as $key => $unused) {
			if (!in_array($key, $allowed[$command], true)) {
				throw new ClinicalSchemaException('command_inappropriate_flag', 'Flag is not valid for this command.', array('flag' => str_replace('_', '-', $key)));
			}
		}

		if (in_array($command, array('plan', 'verify', 'init', 'apply', 'resume'), true) && $options['migration'] === null) {
			throw new ClinicalSchemaException('missing_migration_id', 'An exact migration ID is required.');
		}
		if ($options['migration'] !== null && preg_match('/^[0-9]{14}_[a-z0-9_]+$/', $options['migration']) !== 1) {
			throw new ClinicalSchemaException('invalid_migration_id', 'Migration ID format is invalid.');
		}

		$writeCommand = in_array($command, array('init', 'apply', 'resume'), true);
		if ($writeCommand) {
			if ($options['environment'] === null) {
				throw new ClinicalSchemaException('missing_environment', 'Execution environment confirmation is required.');
			}
			if ($options['confirm_database'] === null) {
				throw new ClinicalSchemaException('missing_database_confirmation', 'Database confirmation is required.');
			}
			if ($options['backup_reference'] === null) {
				throw new ClinicalSchemaException('missing_backup_reference', 'A backup reference is required.');
			}
			if (!$options['confirm_backup']) {
				throw new ClinicalSchemaException('missing_backup_confirmation', 'Explicit backup confirmation is required.');
			}
			self::validateOpaqueValue($options['backup_reference'], 'backup_reference', 191);
			self::validateOpaqueValue($options['environment'], 'environment', 32);
			self::validateOpaqueValue($options['confirm_database'], 'confirm_database', 64);
		}

		if (in_array($command, array('status', 'verify'), true)) {
			if ($options['environment'] === null || $options['confirm_database'] === null) {
				throw new ClinicalSchemaException('missing_read_confirmation', 'Status and verify require environment and database confirmation.');
			}
			self::validateOpaqueValue($options['environment'], 'environment', 32);
			self::validateOpaqueValue($options['confirm_database'], 'confirm_database', 64);
		}

		return array('command' => $command, 'options' => $options);
	}

	private static function validateOpaqueValue($value, $field, $maximumLength)
	{
		$value = (string) $value;
		if ($value === '' || strlen($value) > $maximumLength || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
			throw new ClinicalSchemaException('invalid_confirmation_value', 'Confirmation value is invalid.', array('field' => $field));
		}
		if (preg_match('/(?:password|passwd|credential|secret|token|api[_ -]?key|:\/\/|@)/i', $value) === 1) {
			throw new ClinicalSchemaException('unsafe_confirmation_value', 'Confirmation value is not safe to retain.', array('field' => $field));
		}
	}
}
