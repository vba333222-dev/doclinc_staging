<?php
define('BASEPATH', __DIR__ . '/');
define('FCPATH', sys_get_temp_dir() . '/doclinc-profile-public-' . bin2hex(random_bytes(4)) . '/');

require_once dirname(__DIR__, 3) . '/application/libraries/Profile_image_storage.php';

$passed = 0;
$failed = 0;
function profile_expect($condition, $name)
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

$private = sys_get_temp_dir() . '/doclinc-profile-private-' . bin2hex(random_bytes(4));
mkdir(FCPATH, 0700, true);
$storage = new Profile_image_storage(array('storage_path' => $private, 'public_root' => FCPATH));
$directory = $storage->ensure_storage_directory();
profile_expect(is_string($directory) && is_dir($directory), 'private_storage_created');
profile_expect((fileperms($directory) & 0777) === 0700, 'private_storage_mode_0700');

$jpeg_name = str_repeat('a', 32) . '.jpg';
$jpeg_path = $directory . $jpeg_name;
$jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EH//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EH//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EH//2Q==');
file_put_contents($jpeg_path, $jpeg);
$key = $storage->stored_key_from_upload(array('file_name' => $jpeg_name));
profile_expect($key === 'profile-images/' . $jpeg_name, 'private_key_created');
profile_expect($storage->safe_stored_key($jpeg_name) === 'uploads/profile/' . $jpeg_name, 'legacy_bare_key_normalized');
profile_expect($storage->safe_stored_key('foto-pasien_01.jpeg') === 'uploads/profile/foto-pasien_01.jpeg', 'safe_named_legacy_key_normalized');
profile_expect($storage->safe_stored_key('profile-images/foto-pasien.jpeg') === '', 'private_key_requires_hash');
profile_expect($storage->safe_stored_key('../' . $jpeg_name) === '', 'traversal_rejected');
profile_expect($storage->safe_stored_key('https://example.test/' . $jpeg_name) === '', 'absolute_url_rejected');
profile_expect($storage->resolve_stored_file($key) === realpath($jpeg_path), 'private_file_resolved');
profile_expect($storage->allowed_mime($jpeg_path) === 'image/jpeg', 'content_mime_detected');
profile_expect($storage->upload_is_valid($jpeg_path, $key, 5120) === true, 'valid_upload_accepted');

$fake_png = $directory . str_repeat('b', 32) . '.png';
file_put_contents($fake_png, $jpeg);
profile_expect($storage->upload_is_valid($fake_png, 'profile-images/' . basename($fake_png), 5120) === false, 'extension_mime_mismatch_rejected');
profile_expect($storage->remove_private_file($key) === true && !file_exists($jpeg_path), 'private_rollback_removes_file');

class ProfileFakeResult
{
	private $rows;
	public function __construct(array $rows) { $this->rows = $rows; }
	public function result() { return $this->rows; }
}
class ProfileFakeDb
{
	public $rows = array();
	private $warga_id = 0;
	public function table_exists($table) { return $table === 'requests'; }
	public function where($field, $value) { if ($field === 'user_id') { $this->warga_id = (int) $value; } return $this; }
	public function where_in($field, $values) { return $this; }
	public function order_by($field, $direction) { return $this; }
	public function limit($limit) { return $this; }
	public function get($table) { return new ProfileFakeResult(isset($this->rows[$this->warga_id]) ? $this->rows[$this->warga_id] : array()); }
}
function get_instance() { return null; }
function doclinc_dokter_identity_context($user_id, $refresh = false)
{
	$identities = array(
		10 => array('valid' => true, 'is_command_center' => true, 'is_personal' => false, 'puskesmas_code' => 'PKM01'),
		20 => array('valid' => true, 'is_command_center' => false, 'is_personal' => true, 'puskesmas_code' => 'PKM01'),
		21 => array('valid' => true, 'is_command_center' => false, 'is_personal' => true, 'puskesmas_code' => 'PKM02'),
		22 => array('valid' => true, 'is_command_center' => false, 'is_personal' => true, 'puskesmas_code' => 'PKM01'),
	);
	return isset($identities[(int) $user_id]) ? $identities[(int) $user_id] : array('valid' => false);
}
function doclinc_request_assigned_puskesmas_code($request) { return isset($request->assigned_puskesmas_code) ? (string) $request->assigned_puskesmas_code : ''; }
function doclinc_request_is_handled_by_nakes($request, $user_id)
{
	$identity = doclinc_dokter_identity_context($user_id, true);
	return isset($request->assigned_nakes_user_id)
		&& (int) $request->assigned_nakes_user_id === (int) $user_id
		&& !empty($identity['valid'])
		&& (string) $identity['puskesmas_code'] === doclinc_request_assigned_puskesmas_code($request);
}
function profile_user($id, $role, $status = 'aktif', $must_change = 0)
{
	return (object) array('userId' => $id, 'role' => $role, 'status' => $status, 'must_change_password' => $must_change);
}

require_once dirname(__DIR__, 3) . '/application/libraries/Profile_image_policy.php';
$fake_db = new ProfileFakeDb();
$fake_db->rows = array(
	101 => array((object) array('request_id' => 1, 'request_status' => 'Accepted', 'assigned_nakes_user_id' => 20, 'assigned_puskesmas_code' => 'PKM01')),
	102 => array((object) array('request_id' => 2, 'request_status' => 'Completed', 'assigned_nakes_user_id' => 21, 'assigned_puskesmas_code' => 'PKM02')),
);
$policy = new Profile_image_policy($fake_db);
profile_expect($policy->can_view(profile_user(101, 'warga'), profile_user(101, 'warga')) === true, 'active_self_allowed');
profile_expect($policy->can_view(profile_user(101, 'admin'), profile_user(101, 'admin')) === false, 'admin_denied');
profile_expect($policy->can_view(profile_user(101, 'warga', 'nonaktif'), profile_user(101, 'warga')) === false, 'inactive_denied');
profile_expect($policy->can_view(profile_user(101, 'warga', 'aktif', 1), profile_user(101, 'warga')) === false, 'must_change_denied');
profile_expect($policy->can_view(profile_user(101, 'warga'), profile_user(20, 'dokter')) === true, 'warga_handling_nakes_allowed');
profile_expect($policy->can_view(profile_user(101, 'warga'), profile_user(21, 'dokter')) === false, 'warga_unrelated_nakes_denied');
profile_expect($policy->can_view(profile_user(20, 'dokter'), profile_user(101, 'warga')) === true, 'personal_nakes_patient_allowed');
profile_expect($policy->can_view(profile_user(21, 'dokter'), profile_user(101, 'warga')) === false, 'cross_tenant_personal_denied');
profile_expect($policy->can_view(profile_user(10, 'dokter'), profile_user(101, 'warga')) === true, 'command_center_tenant_patient_allowed');
profile_expect($policy->can_view(profile_user(10, 'dokter'), profile_user(102, 'warga')) === false, 'command_center_cross_tenant_patient_denied');
profile_expect($policy->can_view(profile_user(10, 'dokter'), profile_user(20, 'dokter')) === true, 'command_center_same_tenant_staff_allowed');
profile_expect($policy->can_view(profile_user(10, 'dokter'), profile_user(21, 'dokter')) === false, 'command_center_cross_tenant_staff_denied');
profile_expect($policy->can_view(profile_user(20, 'dokter'), profile_user(22, 'dokter')) === false, 'personal_peer_denied');

@unlink($fake_png);
@rmdir($private);
@rmdir(FCPATH);

echo "PROFILE_IMAGE_UNIT_PASSED={$passed}\n";
echo "PROFILE_IMAGE_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
