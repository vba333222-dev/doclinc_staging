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
	'puskesmas_staff' => array('staff_id', 'kode_pkm', 'nama', 'gelar', 'no_hp', 'profesi', 'nomor_sip', 'sip_expired_at', 'nip', 'user_id', 'status'),
	'm_puskesmas' => array('kode_pkm', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'status'),
);
$db->rows = array(
	'users' => array(
		prerequisite_user(101, 'warga'),
		prerequisite_user(102, 'warga', array('email' => '', 'alamat' => '', 'foto' => '')),
		prerequisite_user(201, 'dokter'),
		prerequisite_user(202, 'dokter', array('tgl' => '', 'gender' => '', 'foto' => '')),
		prerequisite_user(203, 'dokter'),
		prerequisite_user(204, 'dokter', array('status' => 'nonaktif')),
	),
	'puskesmas_staff' => array(
		(object) array('staff_id' => 11, 'kode_pkm' => 'PKM01', 'nama' => 'Nakes Satu', 'gelar' => 'dr.', 'no_hp' => '081234567890', 'profesi' => 'Dokter', 'nomor_sip' => 'SIP-001', 'sip_expired_at' => '2026-12-31', 'nip' => null, 'user_id' => 201, 'status' => 'aktif'),
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
	'today' => '2026-08-10',
));

$warga = $service->evaluate(101, true);
prerequisite_expect($warga['allowed'] === true && $warga['complete'] === true && $warga['actor_type'] === 'warga', 'complete_warga_allowed');
$compat = $service->evaluate(102, false);
prerequisite_expect($compat['allowed'] === true && $compat['complete'] === true, 'warga_address_and_photo_are_non_blocking');
$optional_db = unserialize(serialize($db));
$optional_db->rows['users'][0]->nomor_kk = '';
$optional_db->rows['users'][0]->nomor_bpjs_kis = '';
$optional_db->rows['users'][0]->alamat = '';
$optional_service = new Role_prerequisite_service($optional_db, array('today' => '2026-08-10'));
$optional_state = $optional_service->evaluate(101, true);
prerequisite_expect($optional_state['complete'] === true && $optional_state['readiness_state'] === 'COMPLETE', 'warga_optional_kk_bpjs_address_do_not_block');
$missing_kk_db = unserialize(serialize($db));
$missing_kk_db->rows['users'][0]->nomor_kk = '';
prerequisite_expect((new Role_prerequisite_service($missing_kk_db))->evaluate(101, true)['complete'] === true, 'missing_kk_still_complete');
$missing_bpjs_db = unserialize(serialize($db));
$missing_bpjs_db->rows['users'][0]->nomor_bpjs_kis = '';
prerequisite_expect((new Role_prerequisite_service($missing_bpjs_db))->evaluate(101, true)['complete'] === true, 'missing_bpjs_still_complete');
$missing_address_db = unserialize(serialize($db));
$missing_address_db->rows['users'][0]->alamat = '';
prerequisite_expect((new Role_prerequisite_service($missing_address_db))->evaluate(101, true)['complete'] === true, 'missing_address_still_complete');
$missing_email_db = unserialize(serialize($db));
$missing_email_db->rows['users'][0]->email = '';
prerequisite_expect((new Role_prerequisite_service($missing_email_db))->evaluate(101, true)['complete'] === true, 'missing_email_still_complete');
$missing_photo_db = unserialize(serialize($db));
$missing_photo_db->rows['users'][0]->foto = '';
prerequisite_expect((new Role_prerequisite_service($missing_photo_db))->evaluate(101, true)['complete'] === true, 'missing_photo_still_complete');
$identity_db = unserialize(serialize($db));
$identity_db->rows['users'][0]->nik = '';
$identity_service = new Role_prerequisite_service($identity_db, array('photo_validator' => function () { return true; }));
$identity_state = $identity_service->evaluate(101, true);
prerequisite_expect($identity_state['allowed'] === false && in_array('nik', $identity_state['self_service_fields'], true), 'missing_nik_blocks_warga');
$phone_db = unserialize(serialize($db));
$phone_db->rows['users'][0]->no_hp = '';
$phone_state = (new Role_prerequisite_service($phone_db))->evaluate(101, true);
prerequisite_expect($phone_state['allowed'] === false && in_array('phone', $phone_state['missing_fields'], true), 'missing_phone_blocks_warga');
$warga_mandatory_cases = array(
	'name' => array('nama', ''),
	'birthdate' => array('tgl', ''),
	'gender' => array('gender', ''),
);
foreach ($warga_mandatory_cases as $label => $case) {
	$mandatory_db = unserialize(serialize($db));
	$mandatory_db->rows['users'][0]->{$case[0]} = $case[1];
	$mandatory_state = (new Role_prerequisite_service($mandatory_db))->evaluate(101, true);
	prerequisite_expect($mandatory_state['allowed'] === false
		&& in_array($label, $mandatory_state['missing_fields'], true), 'missing_' . $label . '_blocks_warga');
}
$phone_flag_off = (new Role_prerequisite_service($phone_db))->evaluate(101, false);
prerequisite_expect($phone_flag_off['allowed'] === true && $phone_flag_off['complete'] === false, 'feature_off_does_not_block_incomplete_valid_actor');
$personal = $service->evaluate(201, true);
prerequisite_expect($personal['allowed'] === true && $personal['actor_type'] === 'personal' && $personal['readiness_state'] === 'COMPLETE' && $personal['sip_state'] === 'ACTIVE', 'complete_personal_nakes_allowed');
$nip_db = unserialize(serialize($db));
$nip_db->rows['puskesmas_staff'][0]->nip = '';
$nip_service = new Role_prerequisite_service($nip_db, array(
	'identity_resolver' => function ($user_id) use ($identities) { return isset($identities[$user_id]) ? $identities[$user_id] : array('valid' => false); },
	'photo_validator' => function () { return true; },
	'today' => '2026-08-10',
));
$nip_state = $nip_service->evaluate(201, true);
prerequisite_expect($nip_state['allowed'] === true && !in_array('nip', $nip_state['managed_fields'], true), 'missing_nip_does_not_block_personal_nakes');
$command_center = $service->evaluate(202, true);
prerequisite_expect($command_center['allowed'] === true && $command_center['actor_type'] === 'command_center' && $command_center['readiness_state'] === 'FACILITY_READY', 'command_center_exempt_from_personal_fields');
prerequisite_expect(empty(array_intersect(
	array('birthdate', 'gender', 'title', 'registration_number', 'registration_expiry', 'nip', 'photo', 'staff_link'),
	$command_center['missing_fields']
)), 'command_center_has_no_personal_clinician_gaps');
prerequisite_expect($personal['actor_type'] === 'personal' && $command_center['actor_type'] === 'command_center', 'same_remark_personal_and_command_center_remain_distinct');
prerequisite_expect($personal['managed_fields'] === array() && $personal['remediation_mode'] === 'none', 'complete_personal_has_no_remediation');
$facility_db = unserialize(serialize($db));
$facility_db->rows['m_puskesmas'][0]->alamat = '';
$facility_db->rows['m_puskesmas'][0]->latitude = null;
$facility_service = new Role_prerequisite_service($facility_db, array(
	'identity_resolver' => function ($user_id) use ($identities) { return isset($identities[$user_id]) ? $identities[$user_id] : array('valid' => false); },
	'photo_validator' => function () { return true; },
	'today' => '2026-08-10',
));
$facility_personal = $facility_service->evaluate(201, true);
prerequisite_expect($facility_personal['allowed'] === true, 'personal_profile_does_not_require_facility_address_or_coordinates');
prerequisite_expect($facility_service->evaluate(202, true)['allowed'] === true, 'command_center_facility_operational_gaps_are_non_blocking');
prerequisite_expect(count($facility_personal['missing_fields']) === count($facility_personal['missing_labels']), 'missing_field_labels_remain_positionally_aligned');
$sip_missing_db = unserialize(serialize($db));
$sip_missing_db->rows['puskesmas_staff'][0]->nomor_sip = '';
$sip_missing = (new Role_prerequisite_service($sip_missing_db, array('identity_resolver' => function ($id) use ($identities) { return $identities[$id]; }, 'today' => '2026-08-10')))->evaluate(201, true);
prerequisite_expect($sip_missing['readiness_state'] === 'SIP_MISSING' && $sip_missing['allowed'] === false, 'missing_sip_has_explicit_state');
$sip_expired_db = unserialize(serialize($db));
$sip_expired_db->rows['puskesmas_staff'][0]->sip_expired_at = '2026-08-09';
$sip_expired = (new Role_prerequisite_service($sip_expired_db, array('identity_resolver' => function ($id) use ($identities) { return $identities[$id]; }, 'today' => '2026-08-10')))->evaluate(201, true);
prerequisite_expect($sip_expired['readiness_state'] === 'SIP_EXPIRED' && $sip_expired['allowed'] === false, 'expired_sip_not_ready');
$sip_expiring_db = unserialize(serialize($db));
$sip_expiring_db->rows['puskesmas_staff'][0]->sip_expired_at = '2026-09-01';
$sip_expiring = (new Role_prerequisite_service($sip_expiring_db, array('identity_resolver' => function ($id) use ($identities) { return $identities[$id]; }, 'today' => '2026-08-10')))->evaluate(201, true);
prerequisite_expect($sip_expiring['readiness_state'] === 'SIP_EXPIRING' && $sip_expiring['complete'] === true, 'expiring_sip_is_explicit_warning_state');
$invalid_identity = $service->evaluate(203, false);
prerequisite_expect($invalid_identity['allowed'] === false && $invalid_identity['safe_error_code'] === 'actor_denied', 'invalid_nakes_identity_denied_even_flag_off');
$inactive = $service->evaluate(204, false);
prerequisite_expect($inactive['allowed'] === false && $inactive['safe_error_code'] === 'actor_denied', 'inactive_actor_denied_even_flag_off');

$schema_db = unserialize(serialize($db));
$schema_db->schemas['users'] = array_values(array_diff($schema_db->schemas['users'], array('nik')));
$schema_service = new Role_prerequisite_service($schema_db, array('photo_validator' => function () { return true; }));
$schema_state = $schema_service->evaluate(101, true);
prerequisite_expect($schema_state['allowed'] === false && $schema_state['safe_error_code'] === 'profile_schema_unavailable', 'missing_schema_fails_closed_when_enforced');

$pre_migration_db = unserialize(serialize($db));
$pre_migration_db->schemas['puskesmas_staff'] = array_values(array_diff(
	$pre_migration_db->schemas['puskesmas_staff'],
	array('gelar', 'sip_expired_at')
));
$pre_migration_service = new Role_prerequisite_service($pre_migration_db, array(
	'identity_resolver' => function ($user_id) use ($identities) { return isset($identities[$user_id]) ? $identities[$user_id] : array('valid' => false); },
	'today' => '2026-08-10',
));
$pre_migration_state_off = $pre_migration_service->evaluate(201, false);
$pre_migration_state_on = $pre_migration_service->evaluate(201, true);
prerequisite_expect($pre_migration_state_off['allowed'] === true
	&& in_array('puskesmas_staff.gelar', $pre_migration_state_off['schema_gaps'], true)
	&& in_array('puskesmas_staff.sip_expired_at', $pre_migration_state_off['schema_gaps'], true), 'source_without_profile_v2_migration_is_safe_when_feature_off');
prerequisite_expect($pre_migration_state_on['allowed'] === false
	&& $pre_migration_state_on['safe_error_code'] === 'profile_schema_unavailable', 'profile_v2_schema_gap_fails_closed_when_enforced');

echo "ROLE_PREREQUISITE_UNIT_PASSED={$passed}\n";
echo "ROLE_PREREQUISITE_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
