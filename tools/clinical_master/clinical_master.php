<?php

if (PHP_SAPI !== 'cli') {
    echo "Standalone CLI execution required.\nVALIDATION_RESULT=ERROR\n";
    exit(2);
}

function clinical_master_error_report($command, $code)
{
    return array(
        'command' => $command,
        'package' => array('id' => null, 'version' => null),
        'summary' => array(
            'dataset_count' => null,
            'error_count' => 1,
            'package_status' => null,
            'production_ready' => null,
            'record_count' => null,
            'runtime_enabled' => null,
            'warning_count' => 0,
        ),
        'checks' => array(),
        'errors' => array(array('code' => $code)),
        'warnings' => array(),
        'diagnostics' => array(
            'full_validation_executed' => false,
            'peak_memory_bytes' => memory_get_peak_usage(true),
        ),
        'exit_code' => 2,
        'validation_result' => 'ERROR',
    );
}

function clinical_master_help_report()
{
    return array(
        'command' => 'help',
        'package' => array('id' => null, 'version' => null),
        'summary' => array(
            'dataset_count' => null,
            'error_count' => 0,
            'package_status' => null,
            'production_ready' => null,
            'record_count' => null,
            'runtime_enabled' => null,
            'warning_count' => 0,
        ),
        'checks' => array(array('code' => 'help', 'status' => 'PASS')),
        'errors' => array(),
        'warnings' => array(),
        'diagnostics' => array(
            'full_validation_executed' => false,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'usage' => 'php tools/clinical_master/clinical_master.php <validate|status|help> [--package=<path>] [--format=<text|json>]',
        ),
        'exit_code' => 0,
        'validation_result' => 'PASS',
    );
}

function clinical_master_text_value($value)
{
    if ($value === true) {
        return 'true';
    }
    if ($value === false) {
        return 'false';
    }
    if ($value === null) {
        return '';
    }
    $text = (string) $value;
    if (preg_match('//u', $text) !== 1) {
        $escaped = '';
        for ($index = 0; $index < strlen($text); $index++) {
            $byte = ord($text[$index]);
            if ($byte === 9) {
                $escaped .= '\\t';
            } elseif ($byte === 10) {
                $escaped .= '\\n';
            } elseif ($byte === 13) {
                $escaped .= '\\r';
            } elseif ($byte < 32 || $byte === 127 || $byte >= 128) {
                $escaped .= sprintf('\\x%02X', $byte);
            } else {
                $escaped .= chr($byte);
            }
        }
        return $escaped;
    }
    return preg_replace_callback('/[\x00-\x1F\x7F]/', function ($match) {
        $byte = ord($match[0]);
        if ($byte === 9) {
            return '\\t';
        }
        if ($byte === 10) {
            return '\\n';
        }
        if ($byte === 13) {
            return '\\r';
        }
        return sprintf('\\x%02X', $byte);
    }, $text);
}

function clinical_master_key($value)
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $value));
}

function clinical_master_render_text(array $report)
{
    $lines = array();
    $lines[] = 'COMMAND=' . clinical_master_text_value($report['command']);
    if ($report['command'] === 'help') {
        $lines[] = 'USAGE=' . $report['diagnostics']['usage'];
        $lines[] = 'COMMANDS=validate,status,help';
        $lines[] = 'FLAGS=--package=<path>,--format=text,--format=json';
    }
    $lines[] = 'PACKAGE_ID=' . clinical_master_text_value($report['package']['id']);
    $lines[] = 'PACKAGE_VERSION=' . clinical_master_text_value($report['package']['version']);
    $lines[] = 'PACKAGE_STATUS=' . clinical_master_text_value($report['summary']['package_status']);
    $lines[] = 'PRODUCTION_READY=' . clinical_master_text_value($report['summary']['production_ready']);
    $lines[] = 'RUNTIME_ENABLED=' . clinical_master_text_value($report['summary']['runtime_enabled']);
    $lines[] = 'DATASET_COUNT=' . clinical_master_text_value($report['summary']['dataset_count']);
    $lines[] = 'RECORD_COUNT=' . clinical_master_text_value($report['summary']['record_count']);

    foreach ($report['checks'] as $check) {
        $lines[] = 'CHECK_' . clinical_master_key($check['code']) . '=' . clinical_master_text_value($check['status']);
    }
    foreach ($report['diagnostics'] as $key => $value) {
        if ($key === 'usage') {
            continue;
        }
        if (is_array($value)) {
            foreach ($value as $subKey => $subValue) {
                $lines[] = clinical_master_key($key . '_' . $subKey) . '=' . clinical_master_text_value($subValue);
            }
        } else {
            $lines[] = clinical_master_key($key) . '=' . clinical_master_text_value($value);
        }
    }
    foreach ($report['errors'] as $index => $error) {
        $lines[] = 'ERROR_' . ($index + 1) . '=' . clinical_master_text_value($error['code']);
        $context = $error;
        unset($context['code']);
        if (count($context) > 0) {
            $lines[] = 'ERROR_' . ($index + 1) . '_CONTEXT=' . clinical_master_text_value(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        }
    }
    foreach ($report['warnings'] as $index => $warning) {
        $lines[] = 'WARNING_' . ($index + 1) . '=' . clinical_master_text_value($warning['code']);
        $context = $warning;
        unset($context['code']);
        if (count($context) > 0) {
            $lines[] = 'WARNING_' . ($index + 1) . '_CONTEXT=' . clinical_master_text_value(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        }
    }
    $lines[] = 'ERROR_COUNT=' . clinical_master_text_value($report['summary']['error_count']);
    $lines[] = 'WARNING_COUNT=' . clinical_master_text_value($report['summary']['warning_count']);
    $lines[] = 'EXIT_CODE=' . clinical_master_text_value($report['exit_code']);
    $lines[] = 'VALIDATION_RESULT=' . clinical_master_text_value($report['validation_result']);
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function clinical_master_render(array $report, $format)
{
    if ($format === 'json') {
        $encoded = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            echo '{"command":"error","package":{"id":null,"version":null},"summary":{"dataset_count":null,"error_count":1,"package_status":null,"production_ready":null,"record_count":null,"runtime_enabled":null,"warning_count":0},"checks":[],"errors":[{"code":"output_encoding_failed"}],"warnings":[],"diagnostics":{"full_validation_executed":false,"peak_memory_bytes":0},"exit_code":2,"validation_result":"ERROR"}';
            return 2;
        }
        echo $encoded;
        return $report['exit_code'];
    }
    echo clinical_master_render_text($report);
    return $report['exit_code'];
}

function clinical_master_path_is_inside($root, $path)
{
    $normalRoot = rtrim($root, '/\\');
    $normalPath = $path;
    if (DIRECTORY_SEPARATOR === '\\') {
        $normalRoot = strtolower($normalRoot);
        $normalPath = strtolower($normalPath);
    }
    return $normalPath === $normalRoot || strpos($normalPath, $normalRoot . DIRECTORY_SEPARATOR) === 0;
}

function clinical_master_resolve_control_file($packageRoot, $fileName)
{
    $candidate = $packageRoot . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($candidate) || !is_readable($candidate)) {
        return false;
    }
    $resolved = realpath($candidate);
    if ($resolved === false || !clinical_master_path_is_inside($packageRoot, $resolved)) {
        return false;
    }
    return $resolved;
}

$previousErrorHandler = set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException('controlled_runtime_warning', 0, $severity, $file, $line);
});

$format = 'text';
$packageArgument = null;
$command = isset($argv[1]) ? $argv[1] : null;
$report = null;

try {
    $seenFlags = array();
    for ($index = 2; $index < count($argv); $index++) {
        $argument = $argv[$index];
        if (strpos($argument, '--format=') === 0) {
            if (isset($seenFlags['format'])) {
                $report = clinical_master_error_report($command === null ? '' : $command, 'duplicate_flag');
                break;
            }
            $seenFlags['format'] = true;
            $format = substr($argument, strlen('--format='));
            if (!in_array($format, array('text', 'json'), true)) {
                $report = clinical_master_error_report($command === null ? '' : $command, 'unsupported_format');
                $format = 'text';
                break;
            }
        } elseif (strpos($argument, '--package=') === 0) {
            if (isset($seenFlags['package'])) {
                $report = clinical_master_error_report($command === null ? '' : $command, 'duplicate_flag');
                break;
            }
            $seenFlags['package'] = true;
            $packageArgument = substr($argument, strlen('--package='));
            if ($packageArgument === '') {
                $report = clinical_master_error_report($command === null ? '' : $command, 'malformed_package_flag');
                break;
            }
        } else {
            $report = clinical_master_error_report($command === null ? '' : $command, 'unsupported_flag');
            break;
        }
    }
    if ($report === null && ($command === null || !in_array($command, array('validate', 'status', 'help'), true))) {
        $report = clinical_master_error_report($command === null ? '' : $command, 'invalid_command');
    }

    if ($report === null && $command === 'help') {
        $report = clinical_master_help_report();
    }

    if ($report === null) {
        $repositoryRoot = dirname(__DIR__, 2);
        $candidate = $packageArgument === null
            ? $repositoryRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'master_data' . DIRECTORY_SEPARATOR . 'clinical' . DIRECTORY_SEPARATOR . 'v1'
            : $packageArgument;
        if ($packageArgument !== null && !preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $candidate)) {
            $candidate = getcwd() . DIRECTORY_SEPARATOR . $candidate;
        }
        $packageRoot = realpath($candidate);
        if ($packageRoot === false || !is_dir($packageRoot) || !is_readable($packageRoot)) {
            $report = clinical_master_error_report($command, 'package_root_unavailable');
        } elseif (clinical_master_resolve_control_file($packageRoot, '00_manifest.json') === false) {
            $report = clinical_master_error_report($command, 'manifest_unavailable');
        } elseif (clinical_master_resolve_control_file($packageRoot, '00_manifest.schema.json') === false) {
            $report = clinical_master_error_report($command, 'schema_unavailable');
        } else {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'ClinicalMasterValidator.php';
            $rules = require __DIR__ . DIRECTORY_SEPARATOR . 'rules.php';
            if (!is_array($rules)) {
                throw new RuntimeException('invalid_rules_configuration');
            }
            $validator = new ClinicalMasterValidator($packageRoot, $rules);
            $report = $command === 'status' ? $validator->status() : $validator->validate();
        }
    }
} catch (Throwable $exception) {
    $report = clinical_master_error_report($command === null ? '' : $command, 'internal_validator_exception');
}

restore_error_handler();
$renderExitCode = clinical_master_render($report, $format);
exit($renderExitCode);
