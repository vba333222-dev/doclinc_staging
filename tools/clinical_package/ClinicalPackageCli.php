<?php

class ClinicalPackageCli
{
	private static $commands = array('inspect', 'plan-registration');

	public static function discoverFailureFormat(array $argv)
	{
		$format = null;
		$ambiguous = false;
		for ($index = 2; $index < count($argv); $index++) {
			$argument = (string) $argv[$index];
			if ($argument === '--format=text' || $argument === '--format=json') {
				if ($format !== null) $ambiguous = true;
				$format = substr($argument, 9);
			} elseif ($argument === '--format' || strpos($argument, '--format=') === 0 || strpos($argument, '--format ') === 0) {
				$ambiguous = true;
			}
		}
		return !$ambiguous && $format !== null ? $format : 'text';
	}

	public static function parse(array $argv)
	{
		$command = isset($argv[1]) ? (string) $argv[1] : '';
		if (!in_array($command, self::$commands, true)) {
			throw new ClinicalPackageException('invalid_command', 'Unknown or missing clinical package command.');
		}
		$options = array('package_root' => null, 'format' => 'text');
		$seen = array();
		for ($index = 2; $index < count($argv); $index++) {
			$argument = (string) $argv[$index];
			if (preg_match('/^--([a-z][a-z0-9-]*)=(.*)$/s', $argument, $match) !== 1 || $match[2] === '') {
				throw new ClinicalPackageException('malformed_option', 'CLI options require a non-empty value.');
			}
			$key = str_replace('-', '_', $match[1]);
			if (!array_key_exists($key, $options)) {
				throw new ClinicalPackageException('unknown_option', 'Unsupported clinical package option.', array('option' => $match[1]));
			}
			if (isset($seen[$key])) {
				throw new ClinicalPackageException('duplicate_option', 'Duplicate clinical package option.', array('option' => $match[1]));
			}
			$seen[$key] = true;
			$options[$key] = $match[2];
		}
		if ($options['package_root'] === null) {
			throw new ClinicalPackageException('missing_package_root', 'An explicit package root is required.');
		}
		if (!in_array($options['format'], array('text', 'json'), true)) {
			throw new ClinicalPackageException('unsupported_format', 'Output format must be text or json.');
		}
		if (preg_match('/[\x00-\x1F\x7F]/', $options['package_root']) === 1 || trim($options['package_root']) !== $options['package_root']) {
			throw new ClinicalPackageException('invalid_package_root', 'Package root contains unsafe characters.');
		}
		return array('command' => $command, 'options' => $options);
	}
}
