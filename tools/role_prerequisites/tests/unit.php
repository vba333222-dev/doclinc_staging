<?php
define('BASEPATH', __DIR__ . '/');

require_once dirname(__DIR__, 3) . '/application/libraries/Role_prerequisite_service.php';

$passed = 0;
$failed = 0;
function prerequisite_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$name}\n";
		return;
	}
	$failed++;
	echo "FAIL {$name}\n";
}

class PrerequisiteResult
{
	private $row;
	public function __construct($row) { $this->row = $row; }
	public function row() { return $this->row; }
}

class PrerequisiteDb
{
	public $schemas = array();
	public $rows = array();
	private $where_field = '';
	private $where_value = null;

	public function table_exists($table) { return isset($this->schemas[$table]); }
	public function field_exists($field, $table) { return isset($this->schemas[$table]) && in_array($field, $this->schemas[$table], true); }
	public function select($fields, $escape = null) { return $this; }
	public function where($field, $value) { $this->where_field = $field; $this->where_value = $value; return $this; }
	public function limit($limit) { return $this; }
	public function get($table)
	{
		$row = null;
		foreach (isset($this->rows[$table]) ? $this->rows[$table] : array() as $candidate) {
			if (isset($candidate->{$this->where_field}) && (string) $candidate->{$this->where_field} === (string) $this->where_value) {
				$row = $candidate;
				break;
			}
		}
		$this->where_field = '';
		$this->where_value = null;
		return new PrerequisiteResult($row);
	}
}

function prerequisite_user($id, $role, array $changes = array())
{
	return (object) array_merge(array(
		'userId' => $id,
		'nama' => 'Nama Lengkap',
		'email' => 'user@example.test',
		'role' => $role,
		'status' => 'aktif',
		'must_change_password' => 0,
		'no_hp' => '081234567890',
		'alamat' => 'Jalan Contoh Nomor 1',
		'tgl' => '1990-01-01',
		'gender' => 'Laki-laki',
		'foto' => 'profile-images/' . str_repeat('a', 32) . '.jpg',
		'nik' => '3671010101010001',
		'nomor_kk' => '3671010101010002',
		'nomor_bpjs_kis' => '0001234567890',
		'remark' => $role === 'dokter' ? 'PKM01' : '',
	), $changes);
}

$db = new PrerequisiteDb();
$db->schemas = array(
	'users' => array('userId', 'nama', 'email', 'role', 'status', 'must_change_password', 'no_hp', 'alamat', 'tgl', 'gender', 'foto', 'remark', 'nik', 'nomor_kk', 'nomor_bpjs_kis'),
	'puskesmas_staff' => array('staff_id', 'kode_pkm', 'nama', 'no_hp', 'profesi', 'nomor_sip', 'nip', 'user_id', 'status'),
	'm_puskesmas' => array('kode_pkm', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'status'),
);
$db->rows = array(
	'users' => array(
		prerequisite_user(101, 'warga'),
		prerequisite_user(102, 'warga', array('alamat' => '', 'foto' => '')),
		prerequisite_user(201, 'dokter'),
		prerequisite_user(202, 'dokter'),
		prerequisite_user(203, 'dokter'),
		prerequisite_user(204, 'dokter', array('status' => 'nonaktif')),
	),
	'puskesmas_staff' => array(
		(object) array('staff_id' => 11, 'kode_pkm' => 'PKM01', 'nama' => 'Nakes Satu', 'no_hp' => '081234567890', 'profesi' => 'Dokter', 'nomor_sip' => 'SIP-001', 'nip' => '198001012010011001', 'user_id' => 201, 'status' => 'aktif'),
	),
	'm_puskesmas' => array(
		(object) array('kode_pkm' => 'PKM01', 'nama_puskesmas' => 'Puskesmas Contoh', 'alamat' => 'Jalan Puskesmas Nomor 1', 'latitude' => '-6.0160000', 'longitude' => '106.0500000', 'status' => 'aktif'),
	),
);
$identities = array(
	201 => array('valid' => true, 'account_type' => 'personal', 'user_id' => 201, 'staff_id' => 11, 'puskesmas_code' => 'PKM01'),
	202 => array('valid' => true, 'account_type' => 'command_center', 'user_id' => 202, 'puskesmas_code' => 'PKM01'),
	203 => array('valid' => false, 'account_type' => 'unclassified', 'user_id' => 203),
);
$service = new Role_prerequisite_service($db, array(
	'identity_resolver' => function ($user_id) use ($identities) { return isset($identities[$user_id]) ? $identities[$user_id] : array('valid' => false); },
	'photo_validator' => function ($key) { return strpos($key, 'profile-images/') === 0; },
));

$warga = $service->evaluate(101, true);
prerequisite_expect($warga['allowed'] === true && $warga['complete'] === true && $warga['actor_type'] === 'warga', 'complete_warga_allowed');
$compat = $service->evaluate(102, false);
prerequisite_expect($compat['allowed'] === true && $compat['complete'] === false, 'flag_off_preserves_incomplete_profile');
prerequisite_expect(in_array('address', $compat['missing_fields'], true) && in_array('photo', $compat['missing_fields'], true), 'missing_fields_are_explicit');
$identity_db = unserialize(serialize($db));
$identity_db->rows['users'][0]->nik = '';
$identity_service = new Role_prerequisite_service($identity_db, array('photo_validator' => function () { return true; }));
$identity_state = $identity_service->evaluate(101, true);
prerequisite_expect($identity_state['allowed'] === false && in_array('nik', $identity_state['self_service_fields'], true), 'missing_nik_blocks_warga');
$blocked = $service->evaluate(102, true);
prerequisite_expect($blocked['allowed'] === false && $blocked['safe_error_code'] === 'profile_prerequisites_missing', 'flag_on_blocks_incomplete_warga');
$personal = $service->evaluate(201, true);
prerequisite_expect($personal['allowed'] === true && $personal['actor_type'] === 'personal', 'complete_personal_nakes_allowed');
$nip_db = unserialize(serialize($db));
$nip_db->rows['puskesmas_staff'][0]->nip = '';
$nip_service = new Role_prerequisite_service($nip_db, array(
	'identity_resolver' => function ($user_id) use ($identities) { return isset($identities[$user_id]) ? $identities[$user_id] : array('valid' => false); },
	'photo_validator' => function () { return true; },
));
$nip_state = $nip_service->evaluate(201, true);
prerequisite_expect($nip_state['allowed'] === false && in_array('nip', $nip_state['managed_fields'], true), 'missing_nip_blocks_personal_nakes');
$command_center = $service->evaluate(202, true);
prerequisite_expect($command_center['allowed'] === true && $command_center['actor_type'] === 'command_center', 'complete_command_center_allowed');
prerequisite_expect($personal['managed_fields'] === array() && $personal['remediation_mode'] === 'none', 'complete_personal_has_no_remediation');
$facility_db = unserialize(serialize($db));
$facility_db->rows['m_puskesmas'][0]->alamat = '';
$facility_db->rows['m_puskesmas'][0]->latitude = null;
$facility_service = new Role_prerequisite_service($facility_db, array(
	'identity_resolver' => function ($user_id) use ($identities) { return isset($identities[$user_id]) ? $identities[$user_id] : array('valid' => false); },
	'photo_validator' => function () { return true; },
));
$facility_personal = $facility_service->evaluate(201, true);
prerequisite_expect($facility_personal['allowed'] === false && in_array('facility_address', $facility_personal['managed_fields'], true), 'personal_requires_complete_assigned_facility');
prerequisite_expect($facility_service->evaluate(202, true)['allowed'] === true, 'command_center_facility_operational_gaps_are_non_blocking');
prerequisite_expect(count($facility_personal['missing_fields']) === count($facility_personal['missing_labels']), 'missing_field_labels_remain_positionally_aligned');
prerequisite_expect($facility_personal['remediation_mode'] === 'managed' && $facility_personal['cta_url'] === '' && $facility_personal['cta_label'] === 'Hubungi pengelola', 'managed_facility_gap_has_no_misleading_profile_cta');
$mixed_db = unserialize(serialize($facility_db));
foreach ($mixed_db->rows['users'] as $candidate) {
	if ((int) $candidate->userId === 201) {
		$candidate->foto = '';
	}
}
$mixed_service = new Role_prerequisite_service($mixed_db, array(
	'identity_resolver' => function ($user_id) use ($identities) { return isset($identities[$user_id]) ? $identities[$user_id] : array('valid' => false); },
	'photo_validator' => function () { return true; },
));
$mixed = $mixed_service->evaluate(201, true);
prerequisite_expect($mixed['remediation_mode'] === 'mixed' && in_array('photo', $mixed['self_service_fields'], true) && in_array('facility_address', $mixed['managed_fields'], true), 'mixed_remediation_is_explicit');
$invalid_identity = $service->evaluate(203, false);
prerequisite_expect($invalid_identity['allowed'] === false && $invalid_identity['safe_error_code'] === 'actor_denied', 'invalid_nakes_identity_denied_even_flag_off');
$inactive = $service->evaluate(204, false);
prerequisite_expect($inactive['allowed'] === false && $inactive['safe_error_code'] === 'actor_denied', 'inactive_actor_denied_even_flag_off');

$schema_db = unserialize(serialize($db));
$schema_db->schemas['users'] = array_values(array_diff($schema_db->schemas['users'], array('alamat')));
$schema_service = new Role_prerequisite_service($schema_db, array('photo_validator' => function () { return true; }));
$schema_state = $schema_service->evaluate(101, true);
prerequisite_expect($schema_state['allowed'] === false && $schema_state['safe_error_code'] === 'profile_schema_unavailable', 'missing_schema_fails_closed_when_enforced');

echo "ROLE_PREREQUISITE_UNIT_PASSED={$passed}\n";
echo "ROLE_PREREQUISITE_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
