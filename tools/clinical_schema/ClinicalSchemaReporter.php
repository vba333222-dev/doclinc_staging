<?php

class ClinicalSchemaReporter
{
	public static function baseReport($command, $migrationId = null)
	{
		return array(
			'command' => (string) $command,
			'migration' => array('id' => $migrationId, 'checksum' => null, 'name' => null),
			'summary' => array(),
			'checks' => array(),
			'errors' => array(),
			'warnings' => array(),
			'diagnostics' => array('peak_memory_bytes' => memory_get_peak_usage(true)),
			'exit_code' => 2,
			'validation_result' => 'ERROR',
		);
	}

	public static function addCheck(array &$report, $code, $status, array $context = array())
	{
		$report['checks'][] = array('code' => (string) $code, 'status' => (string) $status, 'context' => $context);
	}

	public static function addError(array &$report, $code, array $context = array())
	{
		$report['errors'][] = array('code' => (string) $code, 'context' => $context);
	}

	public static function addWarning(array &$report, $code, array $context = array())
	{
		$report['warnings'][] = array('code' => (string) $code, 'context' => $context);
	}

	public static function finalize(array $report, $exitCode, $result)
	{
		$report['diagnostics']['peak_memory_bytes'] = memory_get_peak_usage(true);
		$report['exit_code'] = (int) $exitCode;
		$report['validation_result'] = (string) $result;
		foreach (array('checks', 'errors', 'warnings') as $field) {
			usort($report[$field], function ($left, $right) {
				$leftKey = isset($left['code']) ? (string) $left['code'] : '';
				$rightKey = isset($right['code']) ? (string) $right['code'] : '';
				if ($leftKey === $rightKey) {
					return strcmp(json_encode($left), json_encode($right));
				}
				return strcmp($leftKey, $rightKey);
			});
		}
		ksort($report['summary'], SORT_STRING);
		ksort($report['diagnostics'], SORT_STRING);
		return $report;
	}

	public static function render(array $report, $format)
	{
		if ($format === 'json') {
			return self::renderJson($report);
		}
		return self::renderText($report);
	}

	public static function textValue($value)
	{
		if (is_bool($value)) {
			return $value ? 'true' : 'false';
		}
		if ($value === null) {
			return '';
		}
		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}
		if (is_array($value) || is_object($value)) {
			$encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
			$value = $encoded === false ? '[encoding-error]' : $encoded;
		}
		$value = (string) $value;
		$output = '';
		$length = strlen($value);
		for ($index = 0; $index < $length; $index++) {
			$byte = ord($value[$index]);
			if ($byte === 9) {
				$output .= '\\t';
			} elseif ($byte === 10) {
				$output .= '\\n';
			} elseif ($byte === 13) {
				$output .= '\\r';
			} elseif ($byte < 32 || $byte === 127) {
				$output .= sprintf('\\x%02X', $byte);
			} elseif ($byte >= 128 && preg_match('//u', $value) !== 1) {
				$output .= sprintf('\\x%02X', $byte);
			} else {
				$output .= $value[$index];
			}
		}
		return $output;
	}

	private static function renderText(array $report)
	{
		$lines = array();
		$lines[] = 'COMMAND=' . self::textValue($report['command']);
		$lines[] = 'MIGRATION_ID=' . self::textValue($report['migration']['id']);
		$lines[] = 'MIGRATION_NAME=' . self::textValue($report['migration']['name']);
		$lines[] = 'MIGRATION_CHECKSUM=' . self::textValue($report['migration']['checksum']);
		foreach ($report['summary'] as $key => $value) {
			$lines[] = self::key($key) . '=' . self::textValue($value);
		}
		foreach ($report['checks'] as $index => $check) {
			$number = $index + 1;
			$lines[] = 'CHECK_' . $number . '=' . self::textValue($check['code']);
			$lines[] = 'CHECK_' . $number . '_STATUS=' . self::textValue($check['status']);
			if (!empty($check['context'])) {
				$lines[] = 'CHECK_' . $number . '_CONTEXT=' . self::textValue($check['context']);
			}
		}
		foreach (array('errors' => 'ERROR', 'warnings' => 'WARNING') as $field => $prefix) {
			foreach ($report[$field] as $index => $issue) {
				$number = $index + 1;
				$lines[] = $prefix . '_' . $number . '=' . self::textValue($issue['code']);
				if (!empty($issue['context'])) {
					$lines[] = $prefix . '_' . $number . '_CONTEXT=' . self::textValue($issue['context']);
				}
			}
		}
		foreach ($report['diagnostics'] as $key => $value) {
			$lines[] = self::key($key) . '=' . self::textValue($value);
		}
		$lines[] = 'ERROR_COUNT=' . count($report['errors']);
		$lines[] = 'WARNING_COUNT=' . count($report['warnings']);
		$lines[] = 'EXIT_CODE=' . self::textValue($report['exit_code']);
		$lines[] = 'VALIDATION_RESULT=' . self::textValue($report['validation_result']);
		echo implode(PHP_EOL, $lines) . PHP_EOL;
		return (int) $report['exit_code'];
	}

	private static function renderJson(array $report)
	{
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
		$json = json_encode($report, $flags);
		if ($json === false) {
			echo '{"command":"error","migration":{"id":null,"checksum":null,"name":null},"summary":{},"checks":[],"errors":[{"code":"output_encoding_failed","context":{}}],"warnings":[],"diagnostics":{"peak_memory_bytes":0},"exit_code":2,"validation_result":"ERROR"}';
			return 2;
		}
		echo $json;
		return (int) $report['exit_code'];
	}

	private static function key($value)
	{
		$value = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $value));
		return trim($value, '_');
	}
}
