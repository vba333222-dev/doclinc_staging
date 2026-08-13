<?php
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/');
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
    public function is_ajax_request() { return false; }
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
    public function set_flashdata($key, $value) { $this->flash[$key] = $value; }
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

#[AllowDynamicProperties]
class MX_Controller
{
    public $input;
    public $output;
    public $session;
    public $load;
    public function __construct()
    {
        $this->input = new Phase8aInput();
        $this->output = new Phase8aOutput();
        $this->session = new Phase8aSession();
        $this->load = new Phase8aLoader($this);
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
require_once dirname(__DIR__, 3) . '/admin_menu/application/modules/rekam_medis/controllers/Rekam_medis.php';
$record_controller = new Rekam_medis();
$record_controller->input->values = array('record_id' => 999999);
$record_controller->detail();
$body = json_decode($record_controller->output->body, true);
phase8a_expect($record_controller->output->status === 403 && empty($body['success']) && $record_model->detail_calls === 0, 'admin_arbitrary_clinical_record_read_denied_before_model');
phase8a_expect(strpos($record_controller->output->body, 'private') === false, 'admin_denial_response_contains_no_clinical_content');

echo "PHASE8A_RUNTIME_PASSED={$phase8a_passed}\n";
echo "PHASE8A_RUNTIME_FAILED={$phase8a_failed}\n";
exit($phase8a_failed === 0 ? 0 : 1);
