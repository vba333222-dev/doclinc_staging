<?php

function doclinkClinicalPackageFailureFormat(array $arguments)
{
	$format = null;
	$ambiguous = false;
	for ($index = 2; $index < count($arguments); $index++) {
		$argument = (string) $arguments[$index];
		if ($argument === '--format=text' || $argument === '--format=json') {
			if ($format !== null) $ambiguous = true;
			$format = substr($argument, 9);
		} elseif ($argument === '--format' || strpos($argument, '--format=') === 0 || strpos($argument, '--format ') === 0) {
			$ambiguous = true;
		}
	}
	return !$ambiguous && $format !== null ? $format : 'text';
}

$format = doclinkClinicalPackageFailureFormat($argv);
$command = isset($argv[1]) ? (string) $argv[1] : '';
$repository = null;
$report = null;
$bootstrapFailureCode = null;
$previousDisplayErrors = ini_get('display_errors');
$previousLogErrors = ini_get('log_errors');
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$previousErrorHandler = set_error_handler(function ($severity) {
	if ((error_reporting() & $severity) === 0) return false;
	throw new ErrorException('controlled_runtime_warning', 0, $severity);
});

try {
	$dependencies = array(
		'ClinicalPackageException.php','ClinicalPackageCli.php','ClinicalPackagePathPolicy.php','StrictJsonDecoder.php','JcsCanonicalizer.php',
		'ClinicalPackageChecksumBuilder.php','ClinicalPackageLoader.php','ClinicalRegistrationContractValidator.php','ClinicalRegistrationModelBuilder.php',
		'ClinicalRegistrationStateRepository.php','ClinicalRegistrationPlanner.php','ClinicalRegistrationWritePrivilegeValidator.php',
		'ClinicalRegistrationWriteRepository.php','ClinicalRegistrationWriter.php','ClinicalPackageReporter.php','config.php',
	);
	foreach ($dependencies as $dependency) {
		$path = __DIR__ . DIRECTORY_SEPARATOR . $dependency;
		if (!is_file($path) || !is_readable($path)) {
			$bootstrapFailureCode = 'production_dependency_unavailable';
			throw new RuntimeException('controlled_bootstrap_failure');
		}
	}
	foreach (array_slice($dependencies, 0, -1) as $dependency) @require_once __DIR__ . DIRECTORY_SEPARATOR . $dependency;
	$format = ClinicalPackageCli::discoverFailureFormat($argv);
	if (PHP_SAPI !== 'cli') throw new ClinicalPackageException('cli_only', 'Standalone CLI execution is required.');
	$parsed = ClinicalPackageCli::parse($argv);
	$command = $parsed['command'];
	$config = @require __DIR__ . '/config.php';
	if (!is_array($config)) throw new ClinicalPackageException('production_configuration_invalid', 'Clinical package production configuration is invalid.');
	$canonicalizer = new JcsCanonicalizer();
	$loader = new ClinicalPackageLoader(
		new ClinicalPackagePathPolicy($config['allowed_roots_environment']),
		new StrictJsonDecoder(),
		$config['established_validator_file'],
		$config['established_rules_file']
	);
	$evidence = $loader->load($parsed['options']['package_root']);
	$contractValidator = new ClinicalRegistrationContractValidator();
	$builder = new ClinicalRegistrationModelBuilder(new ClinicalPackageChecksumBuilder($canonicalizer), $canonicalizer, $contractValidator);
	$model = $builder->build($evidence);
	if ($command === 'inspect') {
		$report = ClinicalPackageReporter::success($command, $model);
	} elseif ($command === 'plan-registration') {
		$planner = new ClinicalRegistrationPlanner();
		$environmentNames = array_values($config['database_environment_variables']);
		if (MysqliClinicalRegistrationStateRepository::requested($environmentNames)) {
			$repository = MysqliClinicalRegistrationStateRepository::fromEnvironment($config);
			$comparison = $planner->compare($model, $repository);
		} else {
			$comparison = $planner->offline($model);
		}
		$report = ClinicalPackageReporter::success($command, $model, $comparison);
	} else {
		ClinicalRegistrationWriter::assertPreConnectionApplyGates($model, $parsed['options'], $config);
		$repository = MysqliClinicalRegistrationWriteRepository::fromEnvironment($config);
		$planner = new ClinicalRegistrationPlanner();
		$writer = new ClinicalRegistrationWriter($repository, $planner, $config['metadata_registration_pins']);
		$revalidate = function () use ($loader, $builder, $parsed) {
			return $builder->build($loader->load($parsed['options']['package_root']));
		};
		$timeoutValue = getenv($config['metadata_write_lock_timeout_environment']);
		$timeout = $timeoutValue === false || $timeoutValue === '' ? 10 : $timeoutValue;
		$result = $writer->execute($model, $revalidate, $parsed['options'], $timeout);
		$result['registration_reference'] = $parsed['options']['registration_reference'];
		$report = ClinicalPackageReporter::success($command, $model, $result);
	}
} catch (Throwable $exception) {
	$code = $bootstrapFailureCode !== null ? $bootstrapFailureCode : ($exception instanceof ClinicalPackageException ? $exception->getSafeCode() : 'internal_execution_failure');
	$context = $exception instanceof ClinicalPackageException ? $exception->getSafeContext() : array();
	$usage = array('invalid_command','missing_package_root','malformed_option','unknown_option','duplicate_option','unsupported_format','invalid_package_root','package_path_invalid');
	if (class_exists('ClinicalPackageReporter', false)) {
		$report = ClinicalPackageReporter::failure($command, $code, $context, in_array($code, $usage, true) ? 2 : 1);
	} else {
		$report = array('command'=>$command,'dml_executed'=>false,'package_data_imported'=>false,'runtime_enabled'=>false,'error_1'=>$code,'error_count'=>1,'exit_code'=>1,'validation_result'=>'FAIL');
	}
} finally {
	if ($repository !== null) {
		try { $repository->close(); }
		catch (Throwable $ignored) {
			if (class_exists('ClinicalPackageReporter', false)) $report = ClinicalPackageReporter::failure($command, 'database_cleanup_failed');
		}
	}
	restore_error_handler();
	ini_set('display_errors', $previousDisplayErrors === false ? '0' : (string) $previousDisplayErrors);
	ini_set('log_errors', $previousLogErrors === false ? '0' : (string) $previousLogErrors);
}

if (class_exists('ClinicalPackageReporter', false)) exit(ClinicalPackageReporter::render($report, $format));
if ($format === 'json') {
	echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
	foreach ($report as $key => $value) echo strtoupper($key) . '=' . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value) . PHP_EOL;
}
exit(isset($report['exit_code']) ? (int) $report['exit_code'] : 1);
