<?php
putenv('VCW_HTTP_DB_HOST=' . (getenv('VCW_HTTP_DB_HOST') ?: (getenv('DB_HOST') ?: 'task11-db')));
putenv('VCW_HTTP_DB_PORT=' . (getenv('VCW_HTTP_DB_PORT') ?: (getenv('DB_PORT') ?: '3306')));
putenv('VCW_HTTP_DB_NAME=' . (getenv('VCW_HTTP_DB_NAME') ?: (getenv('DB_NAME') ?: 'doclinc_visit_test')));
putenv('VCW_HTTP_DB_USER=' . (getenv('VCW_HTTP_DB_USER') ?: (getenv('DB_USER') ?: 'doclinc_test')));
putenv('VCW_HTTP_DB_PASSWORD=' . (getenv('VCW_HTTP_DB_PASSWORD') ?: (getenv('DB_PASS') ?: 'doclinc_test_only')));
require_once dirname(__DIR__, 2) . '/visit_clinical_workflow/tests/clinical_http_acceptance_support.php';
$requestId = (int) ($argv[1] ?? 0);
task11_assert($requestId > 0, 'state_probe_request_required');
$stmt = task11_sql('SELECT request_status,dokter_id,assigned_nakes_user_id,visit_performer_user_id,visit_status FROM requests WHERE request_id=?', array($requestId));
$row = $stmt->get_result()->fetch_assoc() ?: array();
$row['disposition_count'] = (int) task11_scalar('SELECT COUNT(*) FROM visit_dispositions WHERE request_id=?', array($requestId));
$row['visit_assignment_count'] = (int) task11_scalar('SELECT COUNT(*) FROM request_visit_performer_assignments WHERE request_id=?', array($requestId));
echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
