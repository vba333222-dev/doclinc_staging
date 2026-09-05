<?php
// Test-only guarded router for the disposable Task 11 CI3 runtime.
$dbHost = strtolower((string) (getenv('DB_HOST') ?: ''));
$dbName = (string) (getenv('DB_NAME') ?: '');
$valhalla = trim((string) (getenv('VALHALLA_BASE_URL') ?: ''));
if (!in_array($dbHost, array('127.0.0.1', 'localhost', 'task11-db'), true)
    || $dbName !== 'doclinc_visit_test'
    || $valhalla !== '') {
    http_response_code(500);
    echo 'Task11 disposable runtime guard failed';
    return;
}
$root = dirname(__DIR__, 2);
chdir($root);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/__task11/runtime') {
    header('Content-Type: application/json');
    $flag = static function ($name) {
        $value = getenv($name);
        if ($value === false || $value === '') return null;
        return in_array(strtolower((string) $value), array('1', 'true', 'yes', 'on'), true);
    };
    echo json_encode(array(
        'db_host' => $dbHost,
        'db_name' => $dbName,
        'care_team_workflow_enabled' => $flag('DOCLINC_CARE_TEAM_WORKFLOW_ENABLED'),
        'visit_clinical_workflow_enabled' => $flag('DOCLINC_VISIT_CLINICAL_WORKFLOW_ENABLED'),
    ), JSON_UNESCAPED_SLASHES);
    return;
}
$file = $root . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require $root . DIRECTORY_SEPARATOR . 'index.php';
