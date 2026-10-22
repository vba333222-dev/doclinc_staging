<?php
/**
 * Probe guard finalisasi rekam medis: identitas pasien (NIK) + kode ICD-10.
 *
 * Jalankan DI DALAM container PHP (butuh database stage + flag izin):
 *   docker exec -e ALLOW_STAGE_PROBE=1 doclinc-php8100 \
 *     php /var/www/html/tools/visit_clinical_workflow/tests/clinical_guard_stage_probe.php
 *
 * Tidak dijalankan otomatis oleh tools/visit_clinical_workflow/tests/run.php.
 *
 * Aman: semua perubahan dibungkus transaksi luar dan di-ROLLBACK di akhir,
 * jadi tidak ada satu baris pun yang benar-benar tersimpan. Probe menolak
 * berjalan bila nama database bukan database stage atau flag izin tidak ada.
 */
$root = dirname(__DIR__, 3) . '/';
define('BASEPATH', $root . 'system/');
define('APPPATH', $root . 'application/');
define('FCPATH', $root);
define('ENVIRONMENT', 'testing');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$port = (int) (getenv('DB_PORT') ?: 3306);
if (getenv('ALLOW_STAGE_PROBE') !== '1') { fwrite(STDERR, "refuse: set ALLOW_STAGE_PROBE=1\n"); exit(2); }
if (stripos($name, 'stage') === false) { fwrite(STDERR, "refuse: bukan database stage ($name)\n"); exit(2); }

require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';
$db = DB(array(
    'dsn' => '', 'hostname' => $host, 'username' => $user, 'password' => $pass,
    'database' => $name, 'dbdriver' => 'mysqli', 'dbprefix' => '', 'pconnect' => false,
    'db_debug' => false, 'cache_on' => false, 'cachedir' => '', 'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci', 'port' => $port, 'stricton' => true,
), true);
if (!$db) { fwrite(STDERR, "refuse: koneksi gagal\n"); exit(2); }

class ProbeConfig { private $i; public function __construct(array $i) { $this->i = $i; } public function item($k) { return $this->i[$k] ?? null; } public function set($k, $v) { $this->i[$k] = $v; } }
class ProbeLoader { private $db; public function __construct($db) { $this->db = $db; } public function database($g = 'default', $r = false) { return $this->db; } public function helper($n) { foreach ((array) $n as $h) { $f = APPPATH . 'helpers/' . $h . '_helper.php'; if (is_file($f)) require_once $f; } return $this; } }
class ProbeSession { public function userdata($k) { return null; } }
class ProbeApp { public $db; public $load; public $config; public $session; public function __construct($db) { $this->db = $db; $this->load = new ProbeLoader($db); $this->config = new ProbeConfig(array()); $this->session = new ProbeSession(); } }
$GLOBALS['probe_app'] = new ProbeApp($db);
if (!function_exists('get_instance')) { function &get_instance() { return $GLOBALS['probe_app']; } }

/** Decorator: menyembunyikan tabel tertentu dari table_exists (uji guard skema). */
class ProbeDbHiddenTable { private $db; private $hidden; public function __construct($db, array $hidden) { $this->db = $db; $this->hidden = $hidden; } public function table_exists($t) { return in_array($t, $this->hidden, true) ? false : $this->db->table_exists($t); } public function __call($m, $a) { return call_user_func_array(array($this->db, $m), $a); } }

require_once APPPATH . 'libraries/Clinical_finalization_service.php';

const P_REQUEST = 9106;
const P_DOCTOR_USER = 9004;
const P_DOCTOR_STAFF = 9004;
const P_WARGA_USER = 9006;
const P_NIK = '0000000000000006';
const P_SYNTH_NIK = '8888888888888888';

$fails = 0;

function seed_visit($db, $requestId, $userId, $staffId, $tag) {
    $now = now6($db);
    q($db, "UPDATE requests SET visit_status='completed' WHERE request_id=?", array($requestId));
    q($db, "INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,'selesai',?)", array($requestId, $staffId, $userId, $userId, $now));
    $assignId = (int) $db->insert_id();
    q($db, "INSERT INTO visit_dispositions (request_id,version_no,decision,urgency,created_by_user_id,created_at,idempotency_key) VALUES (?,1,'visit','routine',?,?,?)", array($requestId, $userId, $now, 'probe-disp-' . $tag));
    q($db, "INSERT INTO visit_results (request_id,visit_assignment_id,version_no,performer_user_id,performer_staff_id,status,findings_json,actions_json,created_at,updated_at) VALUES (?,?,1,?,?,'submitted','{}','[]',?,?)", array($requestId, $assignId, $userId, $staffId, $now, $now));
    $resultId = (int) $db->insert_id();
    $rdRow = one($db, "SELECT responsible_assignment_id FROM request_responsible_doctor_assignments WHERE request_id=? AND status='aktif' ORDER BY responsible_assignment_id ASC LIMIT 1", array($requestId));
    if (!$rdRow) { fwrite(STDERR, "refuse: penanggung jawab aktif tidak ada\n"); exit(3); }
    q($db, "INSERT INTO clinical_reviews (request_id,visit_result_id,reviewer_user_id,responsible_assignment_id,decision,reviewed_at,idempotency_key) VALUES (?,?,?,?,'approved',?,?)", array($requestId, $resultId, $userId, (int) $rdRow->responsible_assignment_id, $now, 'probe-rev-' . $tag));
    return $assignId;
}

/** Rekam medis + satu diagnosis. $coded=false meniru diagnosis teks bebas. */
function seed_record($db, $requestId, $userId, $coded) {
    q($db, "INSERT INTO medicalrecords (request_id,diagnosis,anamnesis,recorded_by_user_id,responsible_doctor_user_id) VALUES (?,'Batuk berdahak','Demam 2 hari',?,?)", array($requestId, $userId, $userId));
    $rid = (int) $db->insert_id();
    $term = one($db, "SELECT suggestion_term_id,term_code,preferred_label,source_name,source_version FROM clinical_suggestion_terms WHERE term_code='J06.9' AND term_type='diagnosis' LIMIT 1");
    if (!$term) { fwrite(STDERR, "refuse: master J06.9 tidak ada\n"); exit(3); }
    q($db, "INSERT INTO medicalrecord_diagnoses (medicalrecord_id,request_id,position,diagnosis_role,diagnosis_code,diagnosis_label,coding_state,display_text,suggestion_term_id,reference_source,reference_version,created_by_user_id) VALUES (?,?,1,'primary',?,?,?,?,?,?,?,?)", array(
        $rid, $requestId,
        $coded ? (string) $term->term_code : null,
        $coded ? (string) $term->preferred_label : 'Batuk pilek',
        $coded ? 'coded' : 'uncoded',
        $coded ? ((string) $term->term_code . ' — ' . (string) $term->preferred_label) : 'Batuk pilek',
        $coded ? (int) $term->suggestion_term_id : null,
        $coded ? (string) $term->source_name : null,
        $coded ? (string) $term->source_version : null,
        $userId,
    ));
    return $rid;
}

/** NIK sintetis: memaksa jalur "buat pasien baru", tanpa menyentuh identitas asli. */
function use_synthetic_nik($db) { q($db, "UPDATE users SET nik=? WHERE userId=?", array(P_SYNTH_NIK, P_WARGA_USER)); }

function state($db, $requestId, $nik = P_SYNTH_NIK) {
    $mr = one($db, 'SELECT record_id,patient_id,clinical_finalized_at FROM medicalrecords WHERE request_id=? ORDER BY record_id DESC LIMIT 1', array($requestId));
    $rq = one($db, 'SELECT completed_at FROM requests WHERE request_id=?', array($requestId));
    $pt = one($db, 'SELECT COUNT(*) AS n FROM patients WHERE nik=?', array($nik));
    return array('record_id' => $mr ? (int) $mr->record_id : 0, 'patient_id' => $mr ? $mr->patient_id : null,
        'finalized_at' => $mr ? $mr->clinical_finalized_at : null, 'completed_at' => $rq ? $rq->completed_at : null,
        'patients' => (int) $pt->n);
}

function check($label, $cond, $detail = '') { global $fails; if ($cond) { echo "PASS  $label\n"; } else { $fails++; echo "FAIL  $label  $detail\n"; } }
function q($db, $sql, $binds = array()) { $r = $db->query($sql, $binds); if ($r === false) { fwrite(STDERR, 'SQL_ERROR: ' . $sql . ' :: ' . $db->error('message') . "\n"); exit(3); } return $r; }
function one($db, $sql, $binds = array()) { return q($db, $sql, $binds)->row(); }
function now6($db) { return (string) one($db, 'SELECT CURRENT_TIMESTAMP(6) AS n')->n; }
echo "== DB: {$name} ==\n";

/* Prakondisi: fixture harus ada, kalau tidak probe berhenti dengan jelas. */
$pre = one($db, "SELECT r.request_status, r.assigned_puskesmas_code, rd.user_id AS rd_user, rd.staff_id AS rd_staff FROM requests r JOIN request_responsible_doctor_assignments rd ON rd.request_id=r.request_id AND rd.status='aktif' WHERE r.request_id=? LIMIT 1", array(P_REQUEST));
if (!$pre || (string) $pre->request_status !== 'Accepted') { fwrite(STDERR, "refuse: permintaan " . P_REQUEST . " bukan Accepted / tanpa penanggung jawab aktif\n"); exit(3); }
if ((int) $pre->rd_user !== P_DOCTOR_USER || (int) $pre->rd_staff !== P_DOCTOR_STAFF) { fwrite(STDERR, "refuse: penanggung jawab bukan user/staff " . P_DOCTOR_USER . "\n"); exit(3); }
$warga = one($db, 'SELECT nik FROM users WHERE userId=? LIMIT 1', array(P_WARGA_USER));
if (!$warga) { fwrite(STDERR, "refuse: warga " . P_WARGA_USER . " tidak ada\n"); exit(3); }
echo "fixture: request=" . P_REQUEST . " dokter=" . P_DOCTOR_USER . " warga=" . P_WARGA_USER . " nik=" . (string) $warga->nik . "\n";


/* ---------- Kasus A: jalur bahagia, pasien baru + diagnosis terkode ---------- */
$keyA = 'probe-ok-' . time();
$db->trans_begin();
seed_visit($db, P_REQUEST, P_DOCTOR_USER, P_DOCTOR_STAFF, 'A');
seed_record($db, P_REQUEST, P_DOCTOR_USER, true);
use_synthetic_nik($db);
$res = (new Clinical_finalization_service($db))->finalize(P_REQUEST, P_DOCTOR_USER, $keyA);
$st = state($db, P_REQUEST);
check('A1 finalisasi sukses', ($res['status'] ?? '') === 'success', json_encode($res));
check('A2 patient_id terisi', (int) $st['patient_id'] > 0, 'patient_id=' . var_export($st['patient_id'], true));
check('A3 patients baris dibuat dari NIK', $st['patients'] === 1, 'rows=' . $st['patients']);
check('A4 completed_at terisi', !empty($st['completed_at']), 'completed_at=' . var_export($st['completed_at'], true));
check('A5 clinical_finalized_at terisi', !empty($st['finalized_at']), 'finalized_at=' . var_export($st['finalized_at'], true));
$pt = one($db, 'SELECT nama,gender,primary_user_id,status FROM patients WHERE nik=?', array(P_SYNTH_NIK));
check('A6 identitas pasien tersalin', $pt && (int) $pt->primary_user_id === P_WARGA_USER && $pt->status === 'aktif', json_encode($pt));
check('A7 completed_at = waktu finalisasi', !empty($st['completed_at']) && !empty($st['finalized_at']) && substr($st['completed_at'], 0, 19) === substr($st['finalized_at'], 0, 19), json_encode(array($st['completed_at'], $st['finalized_at'])));

/* ---------- Kasus B: ulangan kunci yang sama (idempoten) ---------- */
$resB = (new Clinical_finalization_service($db))->finalize(P_REQUEST, P_DOCTOR_USER, $keyA);
$stB = state($db, P_REQUEST);
check('B1 idempoten', ($resB['status'] ?? '') === 'success' && !empty($resB['idempotent']), json_encode($resB));
check('B2 tidak menggandakan pasien', $stB['patients'] === 1, 'rows=' . $stB['patients']);
$db->trans_rollback();
$after = state($db, P_REQUEST);
check('B3 rollback membatalkan semua tulisan', $after['patients'] === 0 && $after['record_id'] === 0 && empty($after['completed_at']), json_encode($after));

/* ---------- Kasus G: warga yang sudah punya pasien (pakai ulang, tanpa duplikat) ---------- */
$db->trans_begin();
seed_visit($db, P_REQUEST, P_DOCTOR_USER, P_DOCTOR_STAFF, 'G');
seed_record($db, P_REQUEST, P_DOCTOR_USER, true);
$before = one($db, 'SELECT COUNT(*) AS n FROM patients');
$resG = (new Clinical_finalization_service($db))->finalize(P_REQUEST, P_DOCTOR_USER, 'probe-reuse-' . time());
$stG = state($db, P_REQUEST, P_NIK);
$afterCount = one($db, 'SELECT COUNT(*) AS n FROM patients');
$linked = one($db, 'SELECT patient_id FROM patients WHERE nik=?', array(P_NIK));
check('G1 finalisasi sukses (NIK sudah terdaftar)', ($resG['status'] ?? '') === 'success', json_encode($resG));
check('G2 memakai patient_id yang ada', $linked && (int) $stG['patient_id'] === (int) $linked->patient_id, json_encode(array($stG['patient_id'], $linked)));
check('G3 tidak ada pasien duplikat', (int) $before->n === (int) $afterCount->n && $stG['patients'] === 1, 'sebelum=' . (int) $before->n . ' sesudah=' . (int) $afterCount->n);
$db->trans_rollback();


/* ---------- Kasus C: diagnosis tanpa kode ICD-10 ---------- */
$db->trans_begin();
seed_visit($db, P_REQUEST, P_DOCTOR_USER, P_DOCTOR_STAFF, 'C');
seed_record($db, P_REQUEST, P_DOCTOR_USER, false);
use_synthetic_nik($db);
$resC = (new Clinical_finalization_service($db))->finalize(P_REQUEST, P_DOCTOR_USER, 'probe-uncoded-' . time());
$stC = state($db, P_REQUEST);
check('C1 ditolak DIAGNOSIS_CODING_REQUIRED', ($resC['safe_error_code'] ?? '') === 'DIAGNOSIS_CODING_REQUIRED', json_encode($resC));
check('C2 rekam medis & request tanpa tulisan separuh', $stC['patient_id'] === null && empty($stC['completed_at']) && empty($stC['finalized_at']), json_encode($stC));
$db->trans_rollback();
$postC = state($db, P_REQUEST);
check('C3 pasien sementara ikut dibatalkan', $postC['patients'] === 0, 'rows=' . $postC['patients']);


/* ---------- Kasus D: warga tanpa NIK sah ---------- */
$db->trans_begin();
seed_visit($db, P_REQUEST, P_DOCTOR_USER, P_DOCTOR_STAFF, 'D');
seed_record($db, P_REQUEST, P_DOCTOR_USER, true);
q($db, "UPDATE users SET nik='' WHERE userId=?", array(P_WARGA_USER));
$resD = (new Clinical_finalization_service($db))->finalize(P_REQUEST, P_DOCTOR_USER, 'probe-nonik-' . time());
$stD = state($db, P_REQUEST);
check('D1 ditolak PATIENT_IDENTITY_MISSING', ($resD['safe_error_code'] ?? '') === 'PATIENT_IDENTITY_MISSING', json_encode($resD));
check('D2 tanpa tulisan separuh', $stD['patient_id'] === null && empty($stD['completed_at']) && empty($stD['finalized_at']), json_encode($stD));
$db->trans_rollback();

/* ---------- Kasus E: skema patients tidak tersedia ---------- */
$db->trans_begin();
seed_visit($db, P_REQUEST, P_DOCTOR_USER, P_DOCTOR_STAFF, 'E');
seed_record($db, P_REQUEST, P_DOCTOR_USER, true);
$resE = (new Clinical_finalization_service(new ProbeDbHiddenTable($db, array('patients'))))->finalize(P_REQUEST, P_DOCTOR_USER, 'probe-noschema-' . time());
$stE = state($db, P_REQUEST);
check('E1 ditolak schema_unavailable', ($resE['safe_error_code'] ?? '') === 'schema_unavailable', json_encode($resE));
check('E2 tanpa tulisan separuh', empty($stE['completed_at']) && empty($stE['finalized_at']), json_encode($stE));
$db->trans_rollback();

/* ---------- Kasus F: bersih setelah semua ---------- */
$final = state($db, P_REQUEST);
$ev = one($db, "SELECT COUNT(*) AS n FROM request_events WHERE domain_event_key LIKE 'clinical-finalization:probe-%'");
$vs = one($db, 'SELECT visit_status FROM requests WHERE request_id=?', array(P_REQUEST));
$tot = one($db, 'SELECT COUNT(*) AS n FROM patients');
check('F1 DB bersih (tanpa jejak)', $final['patients'] === 0 && $final['record_id'] === 0 && empty($final['completed_at']) && (int) $ev->n === 0 && (string) $vs->visit_status === 'en_route' && (int) $tot->n === 4, json_encode(array($final, (int) $ev->n, (string) $vs->visit_status, (int) $tot->n)));

echo "\nPROBE_RESULT=" . ($fails === 0 ? 'PASS' : 'FAIL') . " (gagal=$fails)\n";
exit($fails === 0 ? 0 : 1);
