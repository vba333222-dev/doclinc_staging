<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Role_prerequisite_service
{
	private $db;
	private $identity_resolver;
	private $photo_validator;

	public function __construct($db = null, array $options = array())
	{
		$this->db = $db ?: get_instance()->db;
		$this->identity_resolver = isset($options['identity_resolver']) && is_callable($options['identity_resolver'])
			? $options['identity_resolver']
			: null;
		$this->photo_validator = isset($options['photo_validator']) && is_callable($options['photo_validator'])
			? $options['photo_validator']
			: null;
	}

	public function evaluate($user_id, $enforced = false)
	{
		$user_id = (int) $user_id;
		$base = array(
			'enforced' => (bool) $enforced,
			'allowed' => false,
			'complete' => false,
			'actor_type' => 'denied',
			'safe_error_code' => 'actor_denied',
			'missing_fields' => array(),
			'missing_labels' => array(),
			'self_service_fields' => array(),
			'managed_fields' => array(),
			'remediation_mode' => 'none',
			'schema_gaps' => array(),
			'cta_url' => '',
			'cta_label' => 'Lengkapi profil',
		);

		$required_user_fields = array('userId', 'nama', 'email', 'role', 'status', 'must_change_password', 'no_hp', 'alamat', 'tgl', 'gender', 'foto', 'remark', 'nik', 'nomor_kk', 'nomor_bpjs_kis');
		$core_user_fields = array('userId', 'nama', 'email', 'role', 'status', 'must_change_password');
		if ($user_id < 1 || !$this->db->table_exists('users')) {
			$base['safe_error_code'] = 'profile_schema_unavailable';
			$base['schema_gaps'][] = 'users';
			return $base;
		}
		foreach ($core_user_fields as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$base['safe_error_code'] = 'profile_schema_unavailable';
				$base['schema_gaps'][] = 'users.' . $field;
			}
		}
		if (!empty($base['schema_gaps'])) {
			return $base;
		}

		$select = array();
		$schema_gaps = array();
		foreach ($required_user_fields as $field) {
			if ($this->db->field_exists($field, 'users')) {
				$select[] = $field;
			} else {
				$select[] = 'NULL AS ' . $field;
			}
		}
		$user = $this->db->select(implode(', ', $select), false)->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$user || !in_array((string) $user->role, array('warga', 'dokter'), true) || (string) $user->status !== 'aktif' || (int) $user->must_change_password === 1) {
			return $base;
		}

		$actor_type = (string) $user->role === 'warga' ? 'warga' : 'unclassified';
		$missing = array();
		$labels = array();
		$this->require_text($user->nama, 'name', 'Nama lengkap', 2, $missing, $labels);
		$this->require_email($user->email, $missing, $labels);

		if ((string) $user->role === 'warga') {
			$this->collect_user_schema_gaps(array('no_hp', 'alamat', 'tgl', 'gender', 'foto', 'nik', 'nomor_kk', 'nomor_bpjs_kis'), $schema_gaps);
			$this->require_phone($user->no_hp, $missing, $labels);
			$this->require_text($user->alamat, 'address', 'Alamat', 5, $missing, $labels);
			$this->require_birthdate($user->tgl, $missing, $labels);
			$this->require_gender($user->gender, $missing, $labels);
			$this->require_photo($user->foto, $missing, $labels);
			$this->warga_identity_requirements($user, $missing, $labels);
		} else {
			$identity = $this->resolve_identity($user_id);
			if (!is_array($identity) || empty($identity['valid']) || !in_array((string) ($identity['account_type'] ?? ''), array('personal', 'command_center'), true)) {
				$base['safe_error_code'] = 'actor_denied';
				$base['missing_fields'] = array('nakes_identity');
				$base['missing_labels'] = array('Identitas akun');
				$base['managed_fields'] = array('nakes_identity');
				$base['remediation_mode'] = 'managed';
				$base['cta_label'] = 'Hubungi pengelola';
				$base['schema_gaps'] = array_values(array_unique($schema_gaps));
				return $base;
			} else {
				$actor_type = (string) $identity['account_type'];
				$this->collect_user_schema_gaps(array('no_hp', 'foto'), $schema_gaps);
				$this->require_phone($user->no_hp, $missing, $labels);
				$this->require_photo($user->foto, $missing, $labels);
				if ($actor_type === 'personal') {
					$this->collect_user_schema_gaps(array('alamat', 'tgl', 'gender'), $schema_gaps);
					$this->require_text($user->alamat, 'address', 'Alamat', 5, $missing, $labels);
					$this->require_birthdate($user->tgl, $missing, $labels);
					$this->require_gender($user->gender, $missing, $labels);
					$this->personal_requirements($identity, $missing, $labels, $schema_gaps);
				}
				$this->facility_requirements($identity, $missing, $labels, $schema_gaps);
			}
		}

		// add_missing() already de-duplicates by field code. Keep labels in the
		// same positional order because multiple fields may intentionally share
		// one user-facing label (for example both facility coordinates).
		$missing = array_values($missing);
		$labels = array_values($labels);
		$complete = empty($missing) && empty($schema_gaps);
		$allowed = !$enforced || $complete;
		$self_service_codes = array('name', 'email', 'phone', 'address', 'birthdate', 'gender', 'photo', 'nik', 'family_card_number', 'bpjs_number');
		$self_service_fields = array_values(array_intersect($missing, $self_service_codes));
		$managed_fields = array_values(array_diff($missing, $self_service_codes));
		$remediation_mode = 'none';
		if (!empty($self_service_fields) && !empty($managed_fields)) {
			$remediation_mode = 'mixed';
		} elseif (!empty($self_service_fields)) {
			$remediation_mode = 'self_service';
		} elseif (!empty($managed_fields)) {
			$remediation_mode = 'managed';
		}
		$cta_url = !empty($self_service_fields) ? 'profile/complete' : '';
		return array(
			'enforced' => (bool) $enforced,
			'allowed' => $allowed,
			'complete' => $complete,
			'actor_type' => $actor_type,
			'safe_error_code' => $allowed ? '' : (!empty($schema_gaps) ? 'profile_schema_unavailable' : 'profile_prerequisites_missing'),
			'missing_fields' => $missing,
			'missing_labels' => $labels,
			'self_service_fields' => $self_service_fields,
			'managed_fields' => $managed_fields,
			'remediation_mode' => $remediation_mode,
			'schema_gaps' => array_values(array_unique($schema_gaps)),
			'cta_url' => $cta_url,
			'cta_label' => $cta_url !== '' ? 'Lengkapi profil' : ($complete ? '' : 'Hubungi pengelola'),
		);
	}

	private function personal_requirements(array $identity, array &$missing, array &$labels, array &$schema_gaps)
	{
		$staff_id = (int) ($identity['staff_id'] ?? 0);
		if ($staff_id < 1 || !$this->db->table_exists('puskesmas_staff')) {
			$this->add_missing('staff_link', 'Data staf', $missing, $labels);
			if (!$this->db->table_exists('puskesmas_staff')) {
				$schema_gaps[] = 'puskesmas_staff';
			}
			return;
		}
		$fields = array('staff_id', 'kode_pkm', 'nama', 'no_hp', 'profesi', 'nomor_sip', 'nip', 'user_id', 'status');
		$select = array();
		foreach ($fields as $field) {
			if ($this->db->field_exists($field, 'puskesmas_staff')) {
				$select[] = $field;
			} else {
				$select[] = 'NULL AS ' . $field;
				$schema_gaps[] = 'puskesmas_staff.' . $field;
			}
		}
		$staff = $this->db->select(implode(', ', $select), false)->where('staff_id', $staff_id)->limit(1)->get('puskesmas_staff')->row();
		if (!$staff || (int) $staff->user_id !== (int) $identity['user_id'] || (string) $staff->status !== 'aktif' || (string) $staff->kode_pkm !== (string) $identity['puskesmas_code']) {
			$this->add_missing('staff_link', 'Data staf', $missing, $labels);
			return;
		}
		$this->require_text($staff->nama, 'staff_name', 'Nama staf', 2, $missing, $labels);
		$this->require_phone($staff->no_hp, $missing, $labels, 'staff_phone', 'Kontak staf');
		$this->require_text($staff->profesi, 'profession', 'Profesi', 2, $missing, $labels);
		$this->require_text($staff->nomor_sip, 'registration_number', 'Nomor SIP', 3, $missing, $labels);
		$this->require_nip($staff->nip, $missing, $labels);
	}

	private function warga_identity_requirements($user, array &$missing, array &$labels)
	{
		require_once __DIR__ . '/Role_identity_policy.php';
		$result = (new Role_identity_policy())->warga(array(
			'nik' => isset($user->nik) ? $user->nik : '',
			'nomor_kk' => isset($user->nomor_kk) ? $user->nomor_kk : '',
			'nomor_bpjs_kis' => isset($user->nomor_bpjs_kis) ? $user->nomor_bpjs_kis : '',
		));
		$labels_by_field = array(
			'nik' => 'NIK',
			'nomor_kk' => 'Nomor Kartu Keluarga',
			'nomor_bpjs_kis' => 'Nomor kartu BPJS/KIS',
		);
		$codes = array(
			'nik' => 'nik',
			'nomor_kk' => 'family_card_number',
			'nomor_bpjs_kis' => 'bpjs_number',
		);
		foreach (array_keys($result['field_errors']) as $field) {
			$this->add_missing($codes[$field], $labels_by_field[$field], $missing, $labels);
		}
	}

	private function require_nip($value, array &$missing, array &$labels)
	{
		require_once __DIR__ . '/Role_identity_policy.php';
		if (!(new Role_identity_policy())->nip($value)['valid']) {
			$this->add_missing('nip', 'NIP', $missing, $labels);
		}
	}

	private function facility_requirements(array $identity, array &$missing, array &$labels, array &$schema_gaps)
	{
		$code = trim((string) ($identity['puskesmas_code'] ?? ''));
		if ($code === '' || !$this->db->table_exists('m_puskesmas')) {
			$this->add_missing('puskesmas', 'Data Puskesmas', $missing, $labels);
			if (!$this->db->table_exists('m_puskesmas')) {
				$schema_gaps[] = 'm_puskesmas';
			}
			return;
		}
		$fields = array('kode_pkm', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'status');
		$select = array();
		foreach ($fields as $field) {
			if ($this->db->field_exists($field, 'm_puskesmas')) {
				$select[] = $field;
			} else {
				$select[] = 'NULL AS ' . $field;
				$schema_gaps[] = 'm_puskesmas.' . $field;
			}
		}
		$puskesmas = $this->db->select(implode(', ', $select), false)->where('kode_pkm', $code)->limit(1)->get('m_puskesmas')->row();
		if (!$puskesmas || (string) $puskesmas->status !== 'aktif') {
			$this->add_missing('puskesmas', 'Data Puskesmas', $missing, $labels);
			return;
		}
		$this->require_text($puskesmas->nama_puskesmas, 'facility_name', 'Nama Puskesmas', 3, $missing, $labels);
		$this->require_text($puskesmas->alamat, 'facility_address', 'Alamat Puskesmas', 5, $missing, $labels);
		$this->require_coordinate($puskesmas->latitude, 'facility_latitude', 'Lokasi Puskesmas', -90, 90, $missing, $labels);
		$this->require_coordinate($puskesmas->longitude, 'facility_longitude', 'Lokasi Puskesmas', -180, 180, $missing, $labels);
	}

	private function resolve_identity($user_id)
	{
		if ($this->identity_resolver) {
			return call_user_func($this->identity_resolver, $user_id);
		}
		return function_exists('doclinc_dokter_identity_context') ? doclinc_dokter_identity_context($user_id, true) : array('valid' => false);
	}

	private function collect_user_schema_gaps(array $fields, array &$schema_gaps)
	{
		foreach ($fields as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$schema_gaps[] = 'users.' . $field;
			}
		}
	}

	private function require_photo($value, array &$missing, array &$labels)
	{
		$valid = trim((string) $value) !== '';
		if ($valid && $this->photo_validator) {
			$valid = call_user_func($this->photo_validator, (string) $value) === true;
		}
		if (!$valid) {
			$this->add_missing('photo', 'Foto profil', $missing, $labels);
		}
	}

	private function require_email($value, array &$missing, array &$labels)
	{
		if (filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL) === false) {
			$this->add_missing('email', 'Email', $missing, $labels);
		}
	}

	private function require_phone($value, array &$missing, array &$labels, $code = 'phone', $label = 'Nomor HP')
	{
		$phone = preg_replace('/[^0-9+]/', '', trim((string) $value));
		if (preg_match('/^\+?[0-9]{8,20}$/', $phone) !== 1) {
			$this->add_missing($code, $label, $missing, $labels);
		}
	}

	private function require_birthdate($value, array &$missing, array &$labels)
	{
		$value = trim((string) $value);
		$date = DateTime::createFromFormat('!Y-m-d', $value);
		if (!$date || $date->format('Y-m-d') !== $value || $date > new DateTime('today')) {
			$this->add_missing('birthdate', 'Tanggal lahir', $missing, $labels);
		}
	}

	private function require_gender($value, array &$missing, array &$labels)
	{
		if (!in_array(strtolower(trim((string) $value)), array('l', 'p', 'laki-laki', 'perempuan'), true)) {
			$this->add_missing('gender', 'Jenis kelamin', $missing, $labels);
		}
	}

	private function require_coordinate($value, $code, $label, $minimum, $maximum, array &$missing, array &$labels)
	{
		$value = trim((string) $value);
		if ($value === '' || !is_numeric($value) || (float) $value < (float) $minimum || (float) $value > (float) $maximum) {
			$this->add_missing($code, $label, $missing, $labels);
		}
	}

	private function require_text($value, $code, $label, $minimum, array &$missing, array &$labels)
	{
		$value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
		$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
		if ($length < (int) $minimum || in_array(strtolower($value), array('n/a', 'na', '-', 'default', 'belum ditentukan'), true)) {
			$this->add_missing($code, $label, $missing, $labels);
		}
	}

	private function add_missing($code, $label, array &$missing, array &$labels)
	{
		if (in_array((string) $code, $missing, true)) {
			return;
		}
		$missing[] = (string) $code;
		$labels[] = (string) $label;
	}
}
