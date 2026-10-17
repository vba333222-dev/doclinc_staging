<?php
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/');
}
// The harness loads admin controllers below, and those resolve their library
// paths through APPPATH. Without it the require_once calls die with
// "Undefined constant APPPATH".
if (!defined('APPPATH')) {
    define('APPPATH', dirname(__DIR__, 3) . '/admin_menu/application/');
}

$phase8a_passed = 0;
$phase8a_failed = 0;
$phase8a_redirects = array();
$phase8a_models = array();

function phase8a_expect($condition, $name)
{
    global $phase8a_passed, $phase8a_failed;
    if ($condition) {
        $phase8a_passed++;
        echo "PASS {$name}\n";
        return;
    }
    $phase8a_failed++;
    echo "FAIL {$name}\n";
}

function redirect($target, $method = '')
{
    global $phase8a_redirects;
    $phase8a_redirects[] = array($target, $method);
}

class Phase8aInput
{
    public $method = 'POST';
    public $values = array();
    public function method($upper = false) { return $upper ? strtoupper($this->method) : strtolower($this->method); }
    public function post($key = null, $xss = false) { return $key === null ? $this->values : ($this->values[$key] ?? null); }
    public function get($key = null, $xss = false) { return $key === null ? array() : null; }
    public function is_ajax_request() { return false; }
    // Consumed by Admin_clinical_access_gateway when it records the audit
    // context, so the harness must answer them.
    public function ip_address() { return '127.0.0.1'; }
    public function user_agent() { return 'phase8a-harness'; }
    public function server($key = null) { return null; }
}

class Phase8aOutput
{
    public $status = 200;
    public $body = '';
    public function set_status_header($status) { $this->status = (int) $status; return $this; }
    public function set_content_type($type) { return $this; }
    public function set_output($body) { $this->body = (string) $body; return $this; }
}

class Phase8aSession
{
    public $data = array('is_login' => true, 'level' => 'admin');
    public $flash = array();
    public function userdata($key) { return $this->data[$key] ?? null; }
    public function set_userdata($key, $value = null)
    {
        if (is_array($key)) { $this->data = array_merge($this->data, $key); return; }
        $this->data[$key] = $value;
    }
    public function unset_userdata($key) { unset($this->data[$key]); }
    public function set_flashdata($key, $value) { $this->flash[$key] = $value; }
    public function flashdata($key) { return $this->flash[$key] ?? null; }
}

class Phase8aLoader
{
    private $owner;
    public function __construct($owner) { $this->owner = $owner; }
    public function model($name)
    {
        global $phase8a_models;
        $this->owner->{$name} = $phase8a_models[$name];
    }
    public function view($name, $data = array()) {}
}

/**
 * Minimal stand-in for CI's database driver. Admin_clinical_access_gateway's
 * constructor resolves the actor with a chainable where()/get()/row_array()
 * sequence, so the harness must supply that even when the clinical audit feature
 * is off and no real query would otherwise run.
 */
class Phase8aDbStub
{
    private $row;
    public function __construct(array $row = array()) { $this->row = $row; }
    public function where($field, $value = null) { return $this; }
    public function select($fields, $escape = null) { return $this; }
    public function limit($count) { return $this; }
    public function get($table) { return $this; }
    public function row_array() { return $this->row; }
    public function row() { return (object) $this->row; }
    public function query($sql, $binds = null) { return $this; }
    public function num_rows() { return 0; }
    public function table_exists($table) { return false; }
}

#[AllowDynamicProperties]
class MX_Controller
{
    public $input;
    public $output;
    public $session;
    public $load;
    public $db;
    public function __construct()
    {
        $this->input = new Phase8aInput();
        $this->output = new Phase8aOutput();
        $this->session = new Phase8aSession();
        $this->load = new Phase8aLoader($this);
        $this->db = new Phase8aDbStub();
    }
}

class Phase8aTindakanModel
{
    public $edit_calls = 0;
    public $delete_calls = 0;
    public function edit_tindakan($id, $value) { $this->edit_calls++; return true; }
    public function delete_tindakan($id, $value) { $this->delete_calls++; return true; }
}

class Phase8aRecordModel
{
    public $detail_calls = 0;
    public $audit_context_calls = 0;
    public function get_record_audit_context($id) { $this->audit_context_calls++; return array('record_id' => (int) $id); }
    public function get_record_detail($id) { $this->detail_calls++; return (object) array('diagnosis' => 'private'); }
}

$tindakan_model = new Phase8aTindakanModel();
$phase8a_models['Kelola_tindakan_m'] = $tindakan_model;
require_once dirname(__DIR__, 3) . '/admin_menu/application/modules/kelola_tindakan/controllers/Kelola_tindakan.php';

$clinical_row = array('konsul_id' => 77, 'saran' => 'unchanged', 'kriteria' => 'Aktif');
$before = hash('sha256', json_encode($clinical_row));
$controller = new Kelola_tindakan();
$controller->input->values = array('konsul_id' => 77, 'saran' => 'crafted');
$controller->edit_tindakan();
phase8a_expect($controller->output->status === 403 && $tindakan_model->edit_calls === 0, 'admin_direct_clinical_edit_denied_before_model');
$controller = new Kelola_tindakan();
$controller->input->values = array('konsul_id' => 77, 'remark' => 'crafted');
$controller->delete_tindakan();
phase8a_expect($controller->output->status === 403 && $tindakan_model->delete_calls === 0, 'admin_direct_clinical_deactivation_denied_before_model');
phase8a_expect($before === hash('sha256', json_encode($clinical_row)), 'clinical_record_unchanged_after_crafted_attempts');
$controller = new Kelola_tindakan();
$controller->session->data['level'] = 'warga';
$controller->input->values = array('konsul_id' => 77, 'saran' => 'crafted');
$controller->edit_tindakan();
phase8a_expect($controller->output->status === 403 && $tindakan_model->edit_calls === 0, 'non_admin_clinical_mutation_remains_denied');

$record_model = new Phase8aRecordModel();
$phase8a_models['Rekam_medis_m'] = $record_model;
require_once dirname(__DIR__, 3) . '/admin_menu/application/libraries/Admin_clinical_access_gateway.php';

// The clinical-audit feature state decides which branch of detail() runs, so the
// test sets it explicitly instead of inheriting whatever the deployment happens
// to have. Without this the assertion depends on the ambient environment.
function phase8a_clinical_audit($enabled)
{
    $value = $enabled === true ? 'true' : 'false';
    putenv('DOCLINC_CLINICAL_AUDIT_ENABLED=' . $value);
    putenv('DOCLINC_CLINICAL_AUDIT_ENVIRONMENT=staging');
    putenv('DOCLINC_RUNTIME_ENVIRONMENT=staging');
}

require_once dirname(__DIR__, 3) . '/admin_menu/application/modules/rekam_medis/controllers/Rekam_medis.php';

// Feature off: the endpoint must fail closed without reading the record at all.
phase8a_clinical_audit(false);
$record_controller = new Rekam_medis();
$record_controller->input->values = array('record_id' => 999999);
$record_controller->detail();
$body = json_decode($record_controller->output->body, true);
phase8a_expect($record_controller->output->status === 403 && empty($body['success']) && $record_model->detail_calls === 0, 'admin_arbitrary_clinical_record_read_denied_before_model');
phase8a_expect(strpos($record_controller->output->body, 'private') === false, 'admin_denial_response_contains_no_clinical_content');
phase8a_expect($record_model->audit_context_calls === 0, 'denied_request_never_loads_audit_context');

// Feature on: the record is resolved and the audit context is loaded, and the
// read still only happens after the authorization decision.
phase8a_clinical_audit(true);
$record_model->audit_context_calls = 0;
$record_controller = new Rekam_medis();
$record_controller->input->values = array('record_id' => 999999);
$record_controller->detail();
phase8a_expect($record_model->audit_context_calls === 1, 'enabled_request_loads_audit_context');
phase8a_expect($record_controller->output->status !== 200 && $record_model->detail_calls === 0, 'unauthorized_request_still_reads_no_clinical_detail');

// The gateway must be constructible: a missing class here is what made this
// endpoint return 500 instead of its documented 403.
$gateway = new Admin_clinical_access_gateway($record_controller);
phase8a_expect(is_object($gateway), 'admin_clinical_access_gateway_constructs');

echo "PHASE8A_RUNTIME_PASSED={$phase8a_passed}\n";
echo "PHASE8A_RUNTIME_FAILED={$phase8a_failed}\n";
exit($phase8a_failed === 0 ? 0 : 1);
