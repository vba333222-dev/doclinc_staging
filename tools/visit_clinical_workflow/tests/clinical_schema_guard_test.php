<?php
/**
 * Fase 0 - katalog galat + kesiapan skema klinis.
 *
 * Dua kelas kegagalan yang dikunci di sini:
 *   1. endpoint klinis membalas pesan generik tanpa kode yang bisa ditindaklanjuti;
 *   2. service klinis melempar exception (500) saat tabel/kolom belum dimigrasikan.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

require_once APPPATH . 'helpers/clinical_error_helper.php';
require_once APPPATH . 'libraries/Clinical_workflow_requirements.php';

$passed = 0;
$failed = 0;
function schema_guard_check($condition, $label)
{
    global $passed, $failed;
    if ($condition) { $passed++; echo "  ok   - {$label}\n"; return true; }
    $failed++; echo "  FAIL - {$label}\n"; return false;
}

class SchemaGuardRow
{
    public $value;
    public function __construct($value) { $this->value = $value; }
}

class SchemaGuardResult
{
    private $row;
    public function __construct($row) { $this->row = $row; }
    public function row() { return $this->row; }
}

class SchemaGuardDb
{
    public $tables = array();
    public $columns = array();
    public function query($sql, array $binds = array())
    {
        if (stripos($sql, 'SELECT COLUMN_TYPE') !== false) {
            $key = $binds[0] . '.' . $binds[1];
            $type = isset($this->columns[$key]) ? $this->columns[$key] : '';
            if ($type === true) { $type = "enum('aktif','diganti','dibatalkan','selesai')"; }
            return new SchemaGuardResult((object) array('column_type' => is_string($type) ? $type : ''));
        }
        if (stripos($sql, 'information_schema.COLUMNS') !== false) {
            $key = $binds[0] . '.' . $binds[1];
            return new SchemaGuardResult((object) array('total' => isset($this->columns[$key]) ? 1 : 0));
        }
        if (stripos($sql, 'information_schema.TABLES') !== false) {
            return new SchemaGuardResult((object) array('total' => in_array($binds[0], $this->tables, true) ? 1 : 0));
        }
        throw new RuntimeException('unexpected_query');
    }
}

function schema_guard_full_db()
{
    $db = new SchemaGuardDb();
    $requirements = array(
        'nakes_facility_placements' => array('placement_id','staff_id','facility_code','status','active_staff_key'),
        'nakes_facility_transfers' => array('transfer_id','staff_id','status'),
        'visit_dispositions' => array('disposition_id','request_id','version_no','decision','urgency','required_profession','idempotency_key','superseded_at'),
        'visit_results' => array('visit_result_id','request_id','visit_assignment_id','status','draft_revision','submission_key','anamnesis','therapy'),
        'visit_result_vital_sign_measurements' => array('visit_result_id','measurement_id'),
        'request_vital_sign_measurements' => array('measurement_id','request_id','measured_by_user_id','visit_performer_user_id'),
        'clinical_reviews' => array('clinical_review_id','request_id','visit_result_id','reviewer_user_id','responsible_assignment_id','reviewer_visit_assignment_id','decision','idempotency_key'),
        'clinical_amendments' => array('amendment_id','record_id','idempotency_key'),
        'clinical_amendment_items' => array('amendment_item_id','amendment_id','field_key'),
        'clinical_closure_operations' => array('closure_operation_id','request_id','closure_mode','authority_source'),
        'visit_assignment_operations' => array('assignment_operation_id','operation_type','idempotency_key'),
        'request_visit_performer_assignments' => array('ended_by_user_id','end_reason','completed_at','active_request_key'),
        'medicalrecords' => array('clinical_finalized_at','clinical_finalized_by_user_id'),
        'request_events' => array('domain_event_key'),
    );
    foreach ($requirements as $table => $columns) {
        $db->tables[] = $table;
        foreach ($columns as $column) { $db->columns[$table . '.' . $column] = true; }
    }
    $db->columns['request_visit_performer_assignments.status'] = "enum('aktif','diganti','dibatalkan','selesai')";
    return $db;
}

echo "== katalog galat ==\n";
$payload = doclinc_clinical_error_payload('schema_unavailable');
schema_guard_check($payload['safe_error_code'] === 'schema_unavailable', 'kode galat dipertahankan');
schema_guard_check($payload['success'] === false, 'payload selalu success=false');
schema_guard_check(doclinc_clinical_http_status('schema_unavailable') === 503, 'schema_unavailable -> 503');
schema_guard_check(doclinc_clinical_http_status('VISIT_ALREADY_STARTED') === 409, 'konflik state -> 409');
schema_guard_check(doclinc_clinical_http_status('ACCESS_DENIED') === 403, 'akses -> 403');
schema_guard_check(doclinc_clinical_error_message('CLINICAL_CLOSURE_REQUIRED') !== '', 'pesan tidak kosong');
schema_guard_check(strpos(doclinc_clinical_error_message('tidak_ada_di_katalog'), 'belum dapat diproses') !== false, 'kode tak dikenal jatuh ke pesan aman');

echo "\n== skema kosong harus gagal tertutup ==\n";
$empty = new Clinical_workflow_requirements(new SchemaGuardDb());
schema_guard_check($empty->ready() === false, 'ready=false');
$missing = $empty->missing();
schema_guard_check(in_array('table:visit_dispositions', $missing, true), 'melaporkan visit_dispositions');
schema_guard_check(in_array('column:request_events.domain_event_key', $missing, true), 'melaporkan domain_event_key');

echo "\n== skema lengkap harus siap ==\n";
$full = new Clinical_workflow_requirements(schema_guard_full_db());
schema_guard_check($full->ready() === true, 'ready=true');
schema_guard_check($full->missing() === array(), 'tidak ada yang hilang');

echo "\n== status ENUM tanpa selesai harus ditolak ==\n";
$no_selesai = schema_guard_full_db();
$no_selesai->columns['request_visit_performer_assignments.status'] = 'enum(aktif,diganti,dibatalkan)';
$requirements = new Clinical_workflow_requirements($no_selesai);
schema_guard_check(in_array('enum:request_visit_performer_assignments.status.selesai', $requirements->missing(), true), 'enum selesai dideteksi');

echo "\n== service klinis memakai guard skema ==\n";
$services = array(
    'Visit_disposition_service.php',
    'Visit_result_service.php',
    'Clinical_review_service.php',
    'Clinical_finalization_service.php',
    'Clinical_closure_service.php',
    'Clinical_amendment_service.php',
    'Clinical_workflow_surface.php',
    'Clinical_record_service.php',
);
foreach ($services as $service) {
    $source = file_get_contents(APPPATH . 'libraries/' . $service);
    schema_guard_check(strpos($source, 'Clinical_workflow_requirements') !== false, $service . ' memakai requirements');
}
schema_guard_check(strpos(file_get_contents(APPPATH . 'modules/home_nakes/controllers/Home_nakes.php'), 'doclinc_clinical_error_payload') !== false, 'Home_nakes memakai katalog galat');
schema_guard_check(strpos(file_get_contents(APPPATH . 'modules/konsultasi_nakes/controllers/Konsultasi_nakes.php'), 'doclinc_clinical_error_payload') !== false, 'Konsultasi_nakes memakai katalog galat');

echo "\nCLINICAL_SCHEMA_GUARD=PASSED={$passed}, FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
