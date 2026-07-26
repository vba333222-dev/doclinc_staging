<?php
if (PHP_SAPI !== 'cli') {
	exit('No direct script access allowed');
}

ini_set('display_errors', '0');
ini_set('log_errors', '0');

require_once __DIR__ . '/lib/NakesSource.php';
require_once __DIR__ . '/lib/ResetImportService.php';

function nakes_cli_fail($code, $command = '')
{
	$output = array('success' => false, 'command' => $command, 'safe_error_code' => $code, 'write_executed' => false);
	fwrite(STDERR, json_encode($output, JSON_UNESCAPED_SLASHES) . PHP_EOL);
	exit(1);
}

function nakes_cli_option(array $options, $name)
{
	return isset($options[$name]) && is_string($options[$name]) ? trim($options[$name]) : '';
}

$command = isset($argv[1]) ? trim((string) $argv[1]) : '';
if (in_array($command, array('help', '--help'), true)) {
	echo json_encode(array(
		'success' => true,
		'commands' => array('inspect-source', 'plan-reset-import', 'apply-reset-import'),
		'common_required_options' => array('--source-xlsx=<absolute-path>', '--format=json'),
		'apply_required_options' => array(
			'--apply', '--confirm-database=<database>', '--confirm-source-sha256=<sha256>',
			'--confirm-source-record-count=<count>', '--confirm-preserved-puskesmas-count=<count>',
			'--confirm-preserved-command-center-count=<count>', '--confirm-backup-sha256=<sha256>',
			'--reset-reference=<unique-reference>', '--actor-admin-user-id=<id>',
		),
		'write_executed' => false,
	), JSON_UNESCAPED_SLASHES) . PHP_EOL;
	exit(0);
}
if (!in_array($command, array('inspect-source', 'plan-reset-import', 'apply-reset-import'), true)) {
	nakes_cli_fail('invalid_command', $command);
}
$valueOptions = array('source-xlsx','format','confirm-database','confirm-source-sha256','confirm-source-record-count','confirm-preserved-puskesmas-count','confirm-preserved-command-center-count','confirm-backup-sha256','reset-reference','actor-admin-user-id');
$options = array();
foreach (array_slice($argv, 2) as $argument) {
	if ($argument === '--apply') {
		if (isset($options['apply'])) nakes_cli_fail('duplicate_option', $command);
		$options['apply'] = false;
		continue;
	}
	if (preg_match('/^--([a-z0-9-]+)=(.*)$/s', $argument, $match) !== 1 || !in_array($match[1], $valueOptions, true) || isset($options[$match[1]])) {
		nakes_cli_fail('invalid_option', $command);
	}
	$options[$match[1]] = $match[2];
}
if (nakes_cli_option($options, 'format') !== 'json') nakes_cli_fail('json_format_required', $command);
$sourcePath = nakes_cli_option($options, 'source-xlsx');
if ($sourcePath === '') nakes_cli_fail('source_path_required', $command);

$service = null;
try {
	$sourceReader = new DoclincNakesSource();
	$source = $sourceReader->inspect($sourcePath, true);
	if ($command === 'inspect-source') {
		$result = array(
			'success' => true,
			'command' => $command,
			'source_sha256' => DoclincNakesSource::SHA256,
			'source_size_bytes' => DoclincNakesSource::SIZE,
			'sheet_count' => count($source['sheet_counts']),
			'sheet_record_counts' => $source['sheet_counts'],
			'source_record_count' => count($source['records']),
			'source_inference_count' => $source['source_inference_count'],
			'nullable_assignment_count' => $source['nullable_assignment_count'],
			'assignment_normalization_count' => $source['assignment_normalization_count'],
			'pii_emitted' => false,
			'write_executed' => false,
		);
		echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
		exit(0);
	}

	$service = DoclincResetImportService::connectFromEnvironment();
	if ($command === 'plan-reset-import') {
		$result = $service->plan($source);
		$result = array_merge(array('success' => true, 'command' => $command), $result, array('pii_emitted' => false));
		echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
		exit(0);
	}
	if (!array_key_exists('apply', $options)) throw new NakesImportException('explicit_apply_flag_required');
	$confirmation = array(
		'confirm_database' => nakes_cli_option($options, 'confirm-database'),
		'confirm_source_sha256' => nakes_cli_option($options, 'confirm-source-sha256'),
		'confirm_source_record_count' => nakes_cli_option($options, 'confirm-source-record-count'),
		'confirm_preserved_puskesmas_count' => nakes_cli_option($options, 'confirm-preserved-puskesmas-count'),
		'confirm_preserved_command_center_count' => nakes_cli_option($options, 'confirm-preserved-command-center-count'),
		'confirm_backup_sha256' => nakes_cli_option($options, 'confirm-backup-sha256'),
		'reset_reference' => nakes_cli_option($options, 'reset-reference'),
		'actor_admin_user_id' => (int) nakes_cli_option($options, 'actor-admin-user-id'),
	);
	$result = $service->apply($source, $confirmation, $sourcePath);
	$result = array_merge(array('success' => true, 'command' => $command, 'write_executed' => true, 'transaction_committed' => true), $result, array('pii_emitted' => false));
	echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (NakesImportException $exception) {
	nakes_cli_fail($exception->getMessage(), $command);
} catch (Throwable $exception) {
	nakes_cli_fail('operation_failed', $command);
} finally {
	if ($service instanceof DoclincResetImportService) $service->close();
	putenv('DOCLINC_NAKES_RESET_DB_PASSWORD');
}
