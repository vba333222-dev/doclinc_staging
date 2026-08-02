<?php
if (!defined('BASEPATH')) { define('BASEPATH', __DIR__ . '/'); }
if (!class_exists('MX_Controller')) { class MX_Controller {} }

require_once dirname(__DIR__, 3) . '/admin_menu/application/modules/master_puskesmas/models/Master_puskesmas_m.php';
require_once dirname(__DIR__, 3) . '/admin_menu/application/modules/kelola_staff_puskesmas/models/Kelola_staff_puskesmas_m.php';

$passed = 0;
$failed = 0;
function admin_readiness_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

$facility_model = (new ReflectionClass('Master_puskesmas_m'))->newInstanceWithoutConstructor();
$staff_model = (new ReflectionClass('Kelola_staff_puskesmas_m'))->newInstanceWithoutConstructor();

$valid_facility = (object) array(
	'nama_puskesmas' => 'Puskesmas Cilegon', 'alamat' => 'Jalan Pelayanan Nomor 1',
	'latitude' => '-6.0101', 'longitude' => '106.0101', 'status' => 'aktif',
);
admin_readiness_expect($facility_model->readiness_issues($valid_facility) === array(), 'valid_facility_ready');
$invalid_facility = clone $valid_facility;
$invalid_facility->alamat = '-';
$invalid_facility->latitude = '91';
admin_readiness_expect($facility_model->readiness_issues($invalid_facility) === array('facility_address', 'facility_latitude'), 'facility_issue_codes_exact');
$inactive_facility = clone $invalid_facility;
$inactive_facility->status = 'nonaktif';
$facility_summary = $facility_model->readiness_summary(array($valid_facility, $invalid_facility, $inactive_facility));
admin_readiness_expect($facility_summary['total'] === 2 && $facility_summary['ready'] === 1 && $facility_summary['attention'] === 1, 'facility_summary_counts_active_only');
admin_readiness_expect(($facility_summary['issue_counts']['facility_address'] ?? 0) === 1, 'facility_summary_issue_count_exact');

$valid_staff = (object) array(
	'nama' => 'Nakes Uji', 'no_hp' => '081234567890', 'profesi' => 'Dokter',
	'nomor_sip' => 'SIP-001', 'nip' => '198001012010011001', 'user_id' => 12,
	'active_user_link_count' => 1, 'akun_role' => 'dokter', 'akun_status' => 'aktif',
	'akun_remark' => 'PKM01', 'kode_pkm' => 'PKM01', 'puskesmas_status' => 'aktif', 'status' => 'aktif',
	'akun_must_change_password' => 0,
	'personal_account_state' => 'linked',
);
admin_readiness_expect($staff_model->personal_account_state($valid_staff, 10) === 'linked', 'canonical_personal_account_linked');
admin_readiness_expect($staff_model->readiness_issues($valid_staff) === array(), 'valid_staff_ready');
$password_pending_staff = clone $valid_staff;
$password_pending_staff->akun_must_change_password = 1;
admin_readiness_expect($staff_model->readiness_issues($password_pending_staff) === array('staff_password_change_required'), 'temporary_password_state_requires_attention');
admin_readiness_expect($staff_model->personal_account_state($valid_staff, 12) === 'invalid', 'command_center_link_rejected');
$duplicate_staff = clone $valid_staff;
$duplicate_staff->active_user_link_count = 2;
admin_readiness_expect($staff_model->personal_account_state($duplicate_staff, 10) === 'invalid', 'duplicate_active_link_rejected');
$unlinked_staff = clone $valid_staff;
$unlinked_staff->user_id = null;
admin_readiness_expect($staff_model->personal_account_state($unlinked_staff, 10) === 'unlinked', 'missing_account_is_unlinked');
$invalid_staff = clone $valid_staff;
$invalid_staff->nomor_sip = '';
$invalid_staff->nip = '';
$invalid_staff->personal_account_state = 'invalid';
admin_readiness_expect($staff_model->readiness_issues($invalid_staff) === array('staff_registration_number', 'staff_nip', 'staff_account_invalid'), 'staff_issue_codes_exact');
$inactive_staff = clone $invalid_staff;
$inactive_staff->status = 'nonaktif';
$staff_summary = $staff_model->readiness_summary(array($valid_staff, $password_pending_staff, $invalid_staff, $inactive_staff));
admin_readiness_expect($staff_summary['total'] === 3 && $staff_summary['ready'] === 1 && $staff_summary['attention'] === 2, 'staff_summary_counts_active_only');
admin_readiness_expect(($staff_summary['issue_counts']['staff_nip'] ?? 0) === 1, 'staff_summary_issue_count_exact');
admin_readiness_expect(($staff_summary['issue_counts']['staff_password_change_required'] ?? 0) === 1, 'password_change_issue_count_exact');

echo "ADMIN_DATA_READINESS_UNIT_PASSED={$passed}\n";
echo "ADMIN_DATA_READINESS_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
