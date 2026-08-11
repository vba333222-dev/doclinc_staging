<?php
class Kelola_staff_puskesmas_m extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->db = $this->load->database('default', TRUE);
	}

	public function table_ready()
	{
		if (!$this->db->table_exists('puskesmas_staff')) {
			return false;
		}

		foreach ($this->staff_fields() as $field) {
			if (!$this->db->field_exists($field, 'puskesmas_staff')) {
				return false;
			}
		}

		return true;
	}

	public function get_all($filters = array())
	{
		if (!$this->table_ready()) {
			return array();
		}

		$has_puskesmas = $this->db->table_exists('m_puskesmas');
		$has_users = $this->db->table_exists('users');
		$this->db
			->select('puskesmas_staff.staff_id, puskesmas_staff.kode_pkm, puskesmas_staff.nama, puskesmas_staff.no_hp, puskesmas_staff.profesi, puskesmas_staff.nomor_sip, puskesmas_staff.user_id, puskesmas_staff.status')
			->from('puskesmas_staff');
		$this->db->select($this->db->field_exists('gelar', 'puskesmas_staff') ? 'puskesmas_staff.gelar' : 'NULL AS gelar', false);
		$this->db->select($this->db->field_exists('sip_expired_at', 'puskesmas_staff') ? 'puskesmas_staff.sip_expired_at' : 'NULL AS sip_expired_at', false);
		$this->db->select($this->nip_schema_ready() ? 'puskesmas_staff.nip' : 'NULL AS nip', false);
		$this->db->select($this->db->field_exists('penugasan', 'puskesmas_staff') ? 'puskesmas_staff.penugasan' : 'NULL AS penugasan', false);
		$this->db->select('(SELECT COUNT(*) FROM ' . $this->db->dbprefix('puskesmas_staff') . ' linked_staff WHERE linked_staff.user_id = puskesmas_staff.user_id AND linked_staff.status = \'aktif\') AS active_user_link_count', false);

		if ($has_puskesmas) {
			$this->db->select('m_puskesmas.nama_puskesmas');
			$this->db->select($this->db->field_exists('status', 'm_puskesmas') ? 'm_puskesmas.status AS puskesmas_status' : 'NULL AS puskesmas_status', false);
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm = puskesmas_staff.kode_pkm', 'left');
		} else {
			$this->db->select('NULL AS nama_puskesmas, NULL AS puskesmas_status', FALSE);
		}

		if ($has_users) {
			$this->db->select('staff_user.nama AS akun_nama, staff_user.username AS akun_username, staff_user.email AS akun_email');
			$this->db->select($this->db->field_exists('status', 'users') ? 'staff_user.status AS akun_status' : 'NULL AS akun_status', false);
			$this->db->select($this->db->field_exists('role', 'users') ? 'staff_user.role AS akun_role' : 'NULL AS akun_role', false);
			$this->db->select($this->db->field_exists('remark', 'users') ? 'staff_user.remark AS akun_remark' : 'NULL AS akun_remark', false);
			$this->db->select($this->db->field_exists('must_change_password', 'users') ? 'staff_user.must_change_password AS akun_must_change_password' : 'NULL AS akun_must_change_password', false);
			$this->db->select($this->db->field_exists('password_changed_at', 'users') ? 'staff_user.password_changed_at AS akun_password_changed_at' : 'NULL AS akun_password_changed_at', false);
			$this->db->select($this->db->field_exists('no_hp', 'users') ? 'staff_user.no_hp AS akun_no_hp' : 'NULL AS akun_no_hp', false);
			$this->db->select($this->db->field_exists('tgl', 'users') ? 'staff_user.tgl AS akun_tgl' : 'NULL AS akun_tgl', false);
			$this->db->select($this->db->field_exists('gender', 'users') ? 'staff_user.gender AS akun_gender' : 'NULL AS akun_gender', false);
			$this->db->join('users staff_user', 'staff_user.userId = puskesmas_staff.user_id', 'left');
		} else {
			$this->db->select('NULL AS akun_nama, NULL AS akun_username, NULL AS akun_email, NULL AS akun_status, NULL AS akun_role, NULL AS akun_remark, NULL AS akun_must_change_password, NULL AS akun_password_changed_at, NULL AS akun_no_hp, NULL AS akun_tgl, NULL AS akun_gender', FALSE);
		}

		$kode_pkm = isset($filters['kode_pkm']) ? trim((string) $filters['kode_pkm']) : '';
		if ($kode_pkm !== '') {
			$this->db->where('puskesmas_staff.kode_pkm', $kode_pkm);
		}

		$status = isset($filters['status']) ? trim((string) $filters['status']) : '';
		if (in_array($status, array('aktif', 'nonaktif'), true)) {
			$this->db->where('puskesmas_staff.status', $status);
		}

		$keyword = isset($filters['keyword']) ? trim((string) $filters['keyword']) : '';
		if ($keyword !== '') {
			$this->db->group_start()
				->like('puskesmas_staff.nama', $keyword)
				->or_like('puskesmas_staff.no_hp', $keyword)
				->or_like('puskesmas_staff.profesi', $keyword)
				->or_like('puskesmas_staff.nomor_sip', $keyword);
			$nip_keyword = preg_replace('/\D+/', '', $keyword);
			if ($this->nip_schema_ready() && $nip_keyword !== '') {
				$this->db->or_like('puskesmas_staff.nip', $nip_keyword);
			}
			$this->db->group_end();
		}

		if ($has_puskesmas) {
			$this->db->order_by('m_puskesmas.nama_puskesmas', 'ASC');
		}

		return $this->db
			->order_by('puskesmas_staff.nama', 'ASC')
			->order_by('puskesmas_staff.staff_id', 'ASC')
			->get()
			->result();
	}

	public function get_by_id($staff_id)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return null;
		}

		return $this->db
			->where('staff_id', $staff_id)
			->get('puskesmas_staff')
			->row();
	}

	public function nip_schema_ready()
	{
		return $this->db->table_exists('puskesmas_staff') && $this->db->field_exists('nip', 'puskesmas_staff');
	}

	public function nakes_profile_schema_ready()
	{
		return $this->db->table_exists('puskesmas_staff')
			&& $this->db->field_exists('gelar', 'puskesmas_staff')
			&& $this->db->field_exists('sip_expired_at', 'puskesmas_staff');
	}

	public function nip_available($nip, $exclude_staff_id = 0)
	{
		$nip = preg_replace('/\D+/', '', trim((string) $nip));
		$exclude_staff_id = (int) $exclude_staff_id;
		if (!$this->nip_schema_ready() || preg_match('/^[0-9]{18}$/D', $nip) !== 1) {
			return false;
		}
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$this->db->where('nip', $nip);
		if ($exclude_staff_id > 0) {
			$this->db->where('staff_id !=', $exclude_staff_id);
		}
		$count = $this->db->count_all_results('puskesmas_staff');
		$this->db->db_debug = $db_debug;
		return $count !== false && (int) $count === 0;
	}

	public function insert($data)
	{
		if (!$this->table_ready()) {
			return false;
		}

		$row = $this->filter_staff_payload($data);
		if (empty($row['kode_pkm']) || empty($row['nama'])) {
			return false;
		}
		if ($this->db->field_exists('created_at', 'puskesmas_staff')) {
			$row['created_at'] = date('Y-m-d H:i:s');
		}
		if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}
		$admin_id = $this->current_admin_id();
		if ($admin_id !== null && $this->db->field_exists('created_by_user_id', 'puskesmas_staff')) {
			$row['created_by_user_id'] = $admin_id;
		}
		if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
			$row['updated_by_user_id'] = $admin_id;
		}

		return $this->db->insert('puskesmas_staff', $row);
	}

	public function update($staff_id, $data)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return false;
		}

		$row = $this->filter_staff_payload($data);
		unset($row['staff_id'], $row['user_id'], $row['created_at'], $row['created_by_user_id']);
		if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}
		$admin_id = $this->current_admin_id();
		if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
			$row['updated_by_user_id'] = $admin_id;
		}
		if (empty($row)) {
			return false;
		}

		return $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', $row);
	}

	public function set_status($staff_id, $status)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return false;
		}

		$status = in_array($status, array('aktif', 'nonaktif'), true) ? $status : 'nonaktif';
		$row = array('status' => $status);
		if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}
		$admin_id = $this->current_admin_id();
		if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
			$row['updated_by_user_id'] = $admin_id;
		}

		return $this->db
			->where('staff_id', $staff_id)
			->update('puskesmas_staff', $row);
	}

	public function staff_is_operationally_complete($staff)
	{
		if (!$staff) {
			return false;
		}
		require_once dirname(APPPATH, 2) . '/application/libraries/Nakes_profile_readiness_policy.php';
		$state = (new Nakes_profile_readiness_policy())->evaluateStaffRecord(array(
			'name' => $staff->nama ?? '',
			'title' => $staff->gelar ?? '',
			'phone' => $staff->no_hp ?? '',
			'profession' => $staff->profesi ?? '',
			'registration_number' => $staff->nomor_sip ?? '',
			'registration_expires_at' => $staff->sip_expired_at ?? '',
			'facility_status' => $this->puskesmas_is_active((string) ($staff->kode_pkm ?? '')) ? 'aktif' : 'nonaktif',
		));
		return $state['operationally_ready'] === true;
	}

	public function personal_account_state($staff, $command_center_user_id)
	{
		$provisioning = $this->provisioning_account_state($staff, $command_center_user_id);
		if ($provisioning === Nakes_personal_account_policy::STAFF_ONLY
			|| $provisioning === Nakes_personal_account_policy::STAFF_WITH_UNLINKED_ACCOUNT) {
			return 'unlinked';
		}
		return $provisioning === Nakes_personal_account_policy::INVALID ? 'invalid' : 'linked';
	}

	public function provisioning_account_state($staff, $command_center_user_id, $unlinked_account_available = false)
	{
		require_once dirname(APPPATH, 2) . '/application/libraries/Nakes_personal_account_policy.php';
		$policy = new Nakes_personal_account_policy();
		$result = $policy->evaluate(array(
			'staff_exists' => !empty($staff),
			'staff_status' => $staff->status ?? '',
			'staff_facility' => $staff->kode_pkm ?? '',
			'facility_status' => $staff->puskesmas_status ?? '',
			'user_id' => $staff->user_id ?? 0,
			'user_exists' => (int) ($staff->user_id ?? 0) > 0 && isset($staff->akun_role),
			'user_role' => $staff->akun_role ?? '',
			'user_status' => $staff->akun_status ?? '',
			'user_facility' => $staff->akun_remark ?? '',
			'active_link_count' => $staff->active_user_link_count ?? 0,
			'is_command_center' => (int) ($staff->user_id ?? 0) > 0
				&& (int) $command_center_user_id === (int) ($staff->user_id ?? 0),
			'must_change_password' => $staff->akun_must_change_password ?? 0,
			'password_changed_at' => $staff->akun_password_changed_at ?? null,
			'unlinked_account_available' => $unlinked_account_available,
		));
		return $result['state'];
	}

	public function readiness_issues($staff)
	{
		$issues = array();
		$account_state = (string) ($staff->personal_account_state ?? 'invalid');
		require_once dirname(APPPATH, 2) . '/application/libraries/Nakes_profile_readiness_policy.php';
		$readiness = (new Nakes_profile_readiness_policy())->evaluate(array(
			'name' => $staff->akun_nama ?? '',
			'title' => $staff->gelar ?? '',
			'birthdate' => $staff->akun_tgl ?? '',
			'gender' => $staff->akun_gender ?? '',
			'profession' => $staff->profesi ?? '',
			'registration_number' => $staff->nomor_sip ?? '',
			'registration_expires_at' => $staff->sip_expired_at ?? '',
			'phone' => $staff->akun_no_hp ?? '',
			'account_state' => $account_state,
			'staff_status' => $staff->status ?? '',
			'account_status' => $staff->akun_status ?? '',
			'facility_status' => $staff->puskesmas_status ?? '',
		));
		$staff->profile_readiness_state = $readiness['state'];
		$staff->sip_state = $readiness['sip_state'];
		$core_fields = array('name', 'birthdate', 'gender', 'phone');
		if (array_intersect($core_fields, $readiness['missing_fields'])) {
			$issues[] = 'staff_core';
		}
		if (in_array('title', $readiness['missing_fields'], true)) {
			$issues[] = 'staff_title';
		}
		if (in_array('profession', $readiness['missing_fields'], true)) {
			$issues[] = 'staff_profession';
		}
		if (in_array('registration_number', $readiness['missing_fields'], true)) {
			$issues[] = 'staff_registration_number';
		}
		if (in_array('registration_expiry', $readiness['missing_fields'], true)) {
			$issues[] = 'staff_registration_expiry';
		} elseif ($readiness['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_EXPIRED) {
			$issues[] = 'staff_sip_expired';
		} elseif ($readiness['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_EXPIRING) {
			$issues[] = 'staff_sip_expiring';
		}
		if ($account_state !== 'linked') {
			$issues[] = $account_state === 'unlinked' ? 'staff_account_unlinked' : 'staff_account_invalid';
		} else {
			$credential_state = $this->credential_state($staff);
			if ($credential_state === Nakes_credential_policy::FIRST_LOGIN_PENDING) {
				$issues[] = 'staff_first_login_pending';
			} elseif ($credential_state === Nakes_credential_policy::ADMIN_RESET_PENDING) {
				$issues[] = 'staff_password_reset_pending';
			}
		}
		if ((string) ($staff->puskesmas_status ?? '') !== 'aktif') {
			$issues[] = 'staff_facility';
		}
		return $issues;
	}

	public function credential_state($staff)
	{
		require_once dirname(APPPATH, 2) . '/application/libraries/Nakes_credential_policy.php';
		$policy = new Nakes_credential_policy();
		return $policy->state(
			(string) ($staff->akun_role ?? ''),
			(int) ($staff->akun_must_change_password ?? 0),
			$staff->akun_password_changed_at ?? null
		);
	}

	public function readiness_summary(array $rows)
	{
		$summary = array('total' => 0, 'ready' => 0, 'warning' => 0, 'attention' => 0, 'issue_counts' => array());
		foreach ($rows as $row) {
			if ((string) ($row->status ?? '') !== 'aktif') {
				continue;
			}
			$summary['total']++;
			$issues = $this->readiness_issues($row);
			$blocking_issues = array_values(array_diff($issues, array('staff_sip_expiring')));
			if (empty($blocking_issues)) {
				$summary['ready']++;
				if (in_array('staff_sip_expiring', $issues, true)) {
					$summary['warning']++;
				}
			} else {
				$summary['attention']++;
			}
			foreach ($issues as $issue) {
				$summary['issue_counts'][$issue] = ($summary['issue_counts'][$issue] ?? 0) + 1;
			}
		}
		return $summary;
	}

	public function get_active_puskesmas_options()
	{
		if (!$this->db->table_exists('m_puskesmas')) {
			return array();
		}

		$this->db->select('kode_pkm, nama_puskesmas');
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('status', 'aktif');
		}
		$this->db->where('kode_pkm !=', 'DEFAULT');
		$this->db->order_by('nama_puskesmas', 'ASC');

		return $this->db->get('m_puskesmas')->result();
	}

	public function puskesmas_is_active($kode_pkm)
	{
		$kode_pkm = trim((string) $kode_pkm);
		if ($kode_pkm === '' || strtoupper($kode_pkm) === 'DEFAULT' || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$this->db->where('kode_pkm', $kode_pkm);
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('status', 'aktif');
		}

		return $this->db->count_all_results('m_puskesmas') > 0;
	}

	public function personal_account_creation_ready()
	{
		if (!$this->table_ready() || !$this->db->table_exists('users') || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		foreach (array('userId', 'nama', 'email', 'username', 'password', 'role', 'status', 'remark', 'must_change_password', 'password_changed_at', 'tgl', 'gender', 'no_hp') as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				return false;
			}
		}

		return true;
	}

	public function username_exists($username)
	{
		return $this->user_value_exists_case_insensitive('username', $username);
	}

	public function email_exists($email)
	{
		return $this->user_value_exists_case_insensitive('email', $email);
	}

	public function get_personal_account_creation_eligibility($staff)
	{
		if (!$staff || (int) ($staff->staff_id ?? 0) < 1) {
			return array('eligible' => false, 'message' => 'Staf tidak ditemukan.');
		}
		if ((int) ($staff->user_id ?? 0) > 0) {
			return array('eligible' => false, 'message' => 'Staf sudah memiliki akun personal.');
		}
		if (($staff->status ?? '') !== 'aktif') {
			return array('eligible' => false, 'message' => 'Aktifkan staf terlebih dahulu.');
		}
		if (!$this->staff_is_operationally_complete($staff)) {
			return array('eligible' => false, 'message' => 'Lengkapi gelar, profesi, nomor HP, SIP, masa berlaku SIP, dan Puskesmas aktif sebelum membuat akun personal.');
		}
		$staff_name = trim((string) ($staff->nama ?? ''));
		if ($staff_name === '' || strlen($staff_name) > 100) {
			return array('eligible' => false, 'message' => 'Nama staf tidak valid.');
		}

		$kode_pkm = trim((string) ($staff->kode_pkm ?? ''));
		if (!$this->puskesmas_is_active($kode_pkm)) {
			return array('eligible' => false, 'message' => 'Puskesmas staf tidak valid atau tidak aktif.');
		}

		$command_center_user_id = $this->get_command_center_user_id($kode_pkm);
		if ($command_center_user_id < 1) {
			return array('eligible' => false, 'message' => 'Akun Puskesmas belum tersedia.');
		}
		if ($this->command_center_linked_to_staff($command_center_user_id)) {
			return array('eligible' => false, 'message' => 'Akun Puskesmas terhubung ke staf; periksa data akun.');
		}

		return array('eligible' => true, 'message' => '');
	}

	public function create_and_link_personal_account($staff_id, $account)
	{
		$staff_id = (int) $staff_id;
		$username = trim((string) ($account['username'] ?? ''));
		$email = trim((string) ($account['email'] ?? ''));
		$birthdate = trim((string) ($account['birthdate'] ?? ''));
		$gender = trim((string) ($account['gender'] ?? ''));
		$password = (string) ($account['plain_password'] ?? '');
		unset($account['plain_password']);
		require_once dirname(APPPATH, 2) . '/application/libraries/Password_strength_policy.php';
		$password_policy = new Password_strength_policy();
		$password_error = $password_policy->validate($password);
		$birthdate_value = DateTime::createFromFormat('!Y-m-d', $birthdate);
		if ($staff_id < 1
			|| $username === ''
			|| strlen($username) < 3
			|| strlen($username) > 100
			|| !preg_match('/^[A-Za-z0-9._-]+$/', $username)
			|| $email === ''
			|| strlen($email) > 100
			|| !filter_var($email, FILTER_VALIDATE_EMAIL)
			|| !$birthdate_value
			|| $birthdate_value->format('Y-m-d') !== $birthdate
			|| $birthdate > date('Y-m-d')
			|| !in_array($gender, array('Laki-laki', 'Perempuan'), true)
			|| $password_error !== null
			|| !$this->personal_account_creation_ready()) {
			return array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
		}

		$lock_name = 'doclinc_staff_personal_account_create';
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		try {
			$lock_query = $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', array($lock_name));
		} catch (Throwable $e) {
			$lock_query = false;
		}
		$lock_row = $lock_query ? $lock_query->row() : null;
		$lock_acquired = $lock_row
			&& isset($lock_row->acquired)
			&& ($lock_row->acquired === 1 || $lock_row->acquired === '1');
		if (!$lock_acquired) {
			$this->db->db_debug = $db_debug;
			return array('status' => 'error', 'message' => 'Proses pembuatan akun sedang digunakan. Silakan coba kembali.');
		}

		$result = array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
		$transaction_started = false;
		$abort = function ($message) use (&$result) {
			$result = array('status' => 'error', 'message' => $message);
			throw new RuntimeException('personal_account_creation_aborted');
		};

		try {
			if (!$this->db->trans_begin()) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
			$transaction_started = true;

			$staff_query = $this->db->query(
				'SELECT * FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
				array($staff_id)
			);
			if (!$staff_query) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$staff = $staff_query->row();
			if (!$staff) {
				$abort('Staf tidak ditemukan.');
			}
			if ((int) ($staff->user_id ?? 0) > 0) {
				$abort('Staf sudah memiliki akun personal.');
			}
			if (($staff->status ?? '') !== 'aktif') {
				$abort('Aktifkan staf terlebih dahulu.');
			}
			if (!$this->staff_is_operationally_complete($staff)) {
				$abort('Lengkapi gelar, profesi, nomor HP, SIP, masa berlaku SIP, dan Puskesmas aktif sebelum membuat akun personal.');
			}
			$staff_name = trim((string) ($staff->nama ?? ''));
			if ($staff_name === '' || strlen($staff_name) > 100) {
				$abort('Nama staf tidak valid.');
			}
			$kode_pkm = trim((string) ($staff->kode_pkm ?? ''));
			if (!$this->puskesmas_is_active($kode_pkm)) {
				$abort('Puskesmas staf tidak valid atau tidak aktif.');
			}

			$command_center_user_id = $this->get_command_center_user_id($kode_pkm);
			if ($command_center_user_id < 1) {
				$abort('Akun Puskesmas belum tersedia.');
			}
			$command_center_query = $this->db->query(
				'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? AND role = ? AND TRIM(remark) = ? AND status = ? FOR UPDATE',
				array($command_center_user_id, 'dokter', $kode_pkm, 'aktif')
			);
			if (!$command_center_query || !$command_center_query->row()) {
				$abort('Akun Puskesmas belum tersedia.');
			}
			if ($this->command_center_linked_to_staff($command_center_user_id, true)) {
				$abort('Akun Puskesmas terhubung ke staf; periksa data akun.');
			}

			$duplicate_query = $this->db->query(
				'SELECT username, email FROM ' . $this->db->dbprefix('users') . ' WHERE LOWER(TRIM(username)) = ? OR LOWER(TRIM(email)) = ? FOR UPDATE',
				array(strtolower($username), strtolower($email))
			);
			if (!$duplicate_query) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
			foreach ($duplicate_query->result() as $existing_user) {
				if (strtolower(trim((string) $existing_user->username)) === strtolower($username)) {
					$abort('Nama pengguna sudah digunakan.');
				}
				if (strtolower(trim((string) $existing_user->email)) === strtolower($email)) {
					$abort('Email sudah digunakan.');
				}
			}

			if (!function_exists('doclinc_password_hash')) {
				$this->load->helper('password_compat');
			}
			$password_hash = function_exists('doclinc_password_hash') ? doclinc_password_hash($password) : false;
			$password = null;
			if (!$password_hash) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$now = date('Y-m-d H:i:s');
			$user_row = array(
				'nama' => $staff_name,
				'email' => $email,
				'no_hp' => trim((string) ($staff->no_hp ?? '')),
				'tgl' => $birthdate,
				'gender' => $gender,
				'username' => $username,
				'password' => $password_hash,
				'role' => 'dokter',
				'status' => 'aktif',
				'remark' => $kode_pkm,
				'must_change_password' => 1,
				'password_changed_at' => null,
			);
			if ($this->db->field_exists('created_at', 'users')) {
				$user_row['created_at'] = $now;
			}
			if ($this->db->field_exists('updated_at', 'users')) {
				$user_row['updated_at'] = $now;
			}
			if ($this->db->field_exists('updated_by', 'users')) {
				$user_row['updated_by'] = trim((string) ($account['updated_by'] ?? ''));
			}
			if (!$this->db->insert('users', $user_row)) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$user_id = (int) $this->db->insert_id();
			if ($user_id < 1) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			$staff_update = array('user_id' => $user_id);
			if ($this->db->field_exists('updated_at', 'puskesmas_staff')) {
				$staff_update['updated_at'] = $now;
			}
			$admin_id = $this->current_admin_id();
			if ($admin_id !== null && $this->db->field_exists('updated_by_user_id', 'puskesmas_staff')) {
				$staff_update['updated_by_user_id'] = $admin_id;
			}
			$link_succeeded = $this->db
				->where('staff_id', $staff_id)
				->group_start()
					->where('user_id IS NULL', null, false)
					->or_where('user_id', 0)
				->group_end()
				->update('puskesmas_staff', $staff_update);
			if (!$link_succeeded || $this->db->affected_rows() !== 1 || $this->db->trans_status() === false) {
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}

			if (!$this->db->trans_commit()) {
				$this->db->trans_rollback();
				$transaction_started = false;
				$abort('Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
			$transaction_started = false;
			$this->log_account_audit('create_personal_nakes_account', $user_id, $staff_id, array('credential_state' => 'change_required'));
			$result = array(
				'status' => 'success',
				'message' => 'Akun personal dibuat dan dihubungkan. Nakes wajib mengganti password sementara saat login pertama.',
			);
		} catch (RuntimeException $e) {
			if ($e->getMessage() !== 'personal_account_creation_aborted') {
				$result = array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
			}
		} catch (Throwable $e) {
			$result = array('status' => 'error', 'message' => 'Akun personal gagal dibuat. Tidak ada perubahan data yang disimpan.');
		} finally {
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			try {
				$this->db->query('SELECT RELEASE_LOCK(?) AS released', array($lock_name));
			} catch (Throwable $e) {
				// The account operation is not retried when lock cleanup reports a failure.
			}
			$this->db->db_debug = $db_debug;
		}

		return $result;
	}

	public function reset_personal_password($staff_id, $password, $updated_by)
	{
		$staff_id = (int) $staff_id;
		$password = is_string($password) ? $password : '';
		require_once dirname(APPPATH, 2) . '/application/libraries/Password_strength_policy.php';
		$policy = new Password_strength_policy();
		if ($staff_id < 1 || $policy->validate($password) !== null
			|| !$this->personal_account_creation_ready()) {
			return array('status' => 'error', 'message' => 'Password sementara belum dapat direset.');
		}
		if (!function_exists('doclinc_password_hash')) {
			$this->load->helper('password_compat');
		}
		$password_hash = function_exists('doclinc_password_hash') ? doclinc_password_hash($password) : false;
		if (!$password_hash) {
			$password = null;
			return array('status' => 'error', 'message' => 'Password sementara belum dapat direset.');
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$transaction_started = false;
		try {
			if (!$this->db->trans_begin()) {
				throw new RuntimeException('transaction_failed');
			}
			$transaction_started = true;
			$staff_query = $this->db->query(
				'SELECT staff_id, user_id, kode_pkm, status FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
				array($staff_id)
			);
			$staff = $staff_query ? $staff_query->row() : null;
			if (!$staff || (string) $staff->status !== 'aktif' || (int) $staff->user_id < 1) {
				throw new RuntimeException('staff_not_eligible');
			}

			$user_query = $this->db->query(
				'SELECT userId, role, status, remark, password, password_changed_at FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
				array((int) $staff->user_id)
			);
			$user = $user_query ? $user_query->row() : null;
			$kode_pkm = trim((string) $staff->kode_pkm);
			if (!$user || (string) $user->role !== 'dokter' || (string) $user->status !== 'aktif'
				|| trim((string) $user->remark) !== $kode_pkm
				|| $this->get_command_center_user_id($kode_pkm) === (int) $user->userId
				|| $this->account_linked_to_other_active_staff((int) $user->userId, $staff_id)) {
				throw new RuntimeException('account_not_eligible');
			}
			if (password_verify($password, (string) $user->password)) {
				$password = null;
				throw new RuntimeException('temporary_password_reused');
			}
			$password = null;

			$update = array(
				'password' => $password_hash,
				'must_change_password' => 1,
			);
			if ($this->db->field_exists('updated_at', 'users')) {
				$update['updated_at'] = date('Y-m-d H:i:s');
			}
			if ($this->db->field_exists('updated_by', 'users')) {
				$update['updated_by'] = trim((string) $updated_by);
			}
			$updated = $this->db->where('userId', (int) $user->userId)->update('users', $update);
			$password_hash = null;
			if (!$updated || $this->db->affected_rows() !== 1 || $this->db->trans_status() === false) {
				throw new RuntimeException('password_update_failed');
			}
			if (!$this->db->trans_commit()) {
				throw new RuntimeException('commit_failed');
			}
			$transaction_started = false;
			$this->log_account_audit('admin_reset_personal_nakes_password', (int) $user->userId, $staff_id, array('credential_state' => 'change_required'));
			return array(
				'status' => 'success',
				'message' => 'Password sementara direset. Nakes wajib membuat password pribadi saat login berikutnya.',
			);
		} catch (Throwable $e) {
			$password = null;
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			$password_hash = null;
			return array('status' => 'error', 'message' => 'Password sementara belum dapat direset.');
		} finally {
			$this->db->db_debug = $db_debug;
		}
	}

	public function update_linked_personal_profile($staff_id, $birthdate, $gender, $updated_by)
	{
		$staff_id = (int) $staff_id;
		$birthdate = trim((string) $birthdate);
		$gender = trim((string) $gender);
		$birthdate_value = DateTime::createFromFormat('!Y-m-d', $birthdate);
		if ($staff_id < 1 || !$birthdate_value || $birthdate_value->format('Y-m-d') !== $birthdate
			|| $birthdate > date('Y-m-d') || !in_array($gender, array('Laki-laki', 'Perempuan'), true)
			|| !$this->personal_account_creation_ready()) {
			return array('status' => 'error', 'message' => 'Data personal Nakes belum dapat diperbarui.');
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$transaction_started = false;
		try {
			if (!$this->db->trans_begin()) {
				throw new RuntimeException('transaction_failed');
			}
			$transaction_started = true;
			$staff_query = $this->db->query(
				'SELECT staff_id, user_id, kode_pkm, nama, no_hp, status FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
				array($staff_id)
			);
			$staff = $staff_query ? $staff_query->row() : null;
			if (!$staff || (string) $staff->status !== 'aktif' || (int) $staff->user_id < 1
				|| trim((string) $staff->nama) === '' || trim((string) $staff->no_hp) === '') {
				throw new RuntimeException('staff_invalid');
			}
			$user_query = $this->db->query(
				'SELECT userId, role, status, remark FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
				array((int) $staff->user_id)
			);
			$user = $user_query ? $user_query->row() : null;
			$kode_pkm = trim((string) $staff->kode_pkm);
			if (!$user || (string) $user->role !== 'dokter' || (string) $user->status !== 'aktif'
				|| trim((string) $user->remark) !== $kode_pkm || !$this->puskesmas_is_active($kode_pkm)
				|| $this->get_command_center_user_id($kode_pkm) === (int) $user->userId
				|| $this->account_linked_to_other_active_staff((int) $user->userId, $staff_id)) {
				throw new RuntimeException('identity_invalid');
			}
			$update = array(
				'nama' => trim((string) $staff->nama),
				'no_hp' => trim((string) $staff->no_hp),
				'tgl' => $birthdate,
				'gender' => $gender,
			);
			if ($this->db->field_exists('updated_at', 'users')) {
				$update['updated_at'] = date('Y-m-d H:i:s');
			}
			if ($this->db->field_exists('updated_by', 'users')) {
				$update['updated_by'] = trim((string) $updated_by);
			}
			if (!$this->db->where('userId', (int) $user->userId)->update('users', $update)
				|| $this->db->affected_rows() > 1 || $this->db->trans_status() === false) {
				throw new RuntimeException('profile_update_failed');
			}
			if (!$this->db->trans_commit()) {
				throw new RuntimeException('commit_failed');
			}
			$transaction_started = false;
			$this->log_account_audit('update_personal_nakes_profile', (int) $user->userId, $staff_id);
			return array('status' => 'success', 'message' => 'Data personal Nakes diperbarui.');
		} catch (Throwable $e) {
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			return array('status' => 'error', 'message' => 'Data personal Nakes belum dapat diperbarui.');
		} finally {
			$this->db->db_debug = $db_debug;
		}
	}

	public function get_command_center_user_id($kode_pkm)
	{
		$kode_pkm = trim((string) $kode_pkm);
		if ($kode_pkm === '' || !$this->db->table_exists('users') || !$this->db->field_exists('remark', 'users')) {
			return 0;
		}

		$this->db
			->select('userId')
			->from('users')
			->where('role', 'dokter')
			->where('TRIM(remark) =', $kode_pkm);
		if ($this->db->field_exists('status', 'users')) {
			$this->db->where('status', 'aktif');
		}

		$user = $this->db
			->order_by('userId', 'ASC')
			->limit(1)
			->get()
			->row();

		return $user ? (int) $user->userId : 0;
	}

	public function get_eligible_account_candidates($kode_pkm, $staff_id = null)
	{
		$kode_pkm = trim((string) $kode_pkm);
		if ($kode_pkm === '' || !$this->table_ready() || !$this->db->table_exists('users') || !$this->db->field_exists('remark', 'users')) {
			return array();
		}

		$staff_id = (int) $staff_id;
		$command_center_user_id = $this->get_command_center_user_id($kode_pkm);
		$linked_user_ids = $this->get_linked_active_user_ids($staff_id);
		$has_user_status = $this->db->field_exists('status', 'users');

		$this->db
			->select('userId, nama, username, email, remark, no_hp, tgl, gender')
			->from('users')
			->where('role', 'dokter')
			->where('TRIM(remark) =', $kode_pkm);
		if ($has_user_status) {
			$this->db->select('status');
			$this->db->where('status', 'aktif');
		} else {
			$this->db->select('NULL AS status', false);
		}
		if ($command_center_user_id > 0) {
			$this->db->where('userId !=', $command_center_user_id);
		}
		if (!empty($linked_user_ids)) {
			$this->db->where_not_in('userId', $linked_user_ids);
		}
		$this->db
			->where('tgl IS NOT NULL', null, false)
			->where("TRIM(no_hp) != ''", null, false)
			->where_in('gender', array('Laki-laki', 'Perempuan'));

		return $this->db
			->order_by('nama', 'ASC')
			->order_by('username', 'ASC')
			->get()
			->result();
	}

	public function bind_staff_account($staff_id, $user_id)
	{
		$staff_id = (int) $staff_id;
		$user_id = (int) $user_id;
		if ($staff_id < 1 || $user_id < 1 || !$this->table_ready()) {
			return array('status' => 'error', 'message' => 'Pilih staf dan akun yang valid.');
		}

		$lock_name = 'doclinc_staff_personal_account_link';
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$transaction_started = false;
		$lock_acquired = false;
		try {
			$lock_query = $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', array($lock_name));
			$lock_row = $lock_query ? $lock_query->row() : null;
			$lock_acquired = $lock_row && ($lock_row->acquired === 1 || $lock_row->acquired === '1');
			if (!$lock_acquired || !$this->db->trans_begin()) {
				throw new RuntimeException('lock_failed');
			}
			$transaction_started = true;
			$staff_query = $this->db->query(
				'SELECT * FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
				array($staff_id)
			);
			$user_query = $this->db->query(
				'SELECT userId, role, status, remark, no_hp, tgl, gender FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
				array($user_id)
			);
			$staff = $staff_query ? $staff_query->row() : null;
			$user = $user_query ? $user_query->row() : null;
			if (!$staff || !$user || (int) ($staff->user_id ?? 0) > 0
				|| (string) $staff->status !== 'aktif' || !$this->staff_is_operationally_complete($staff)) {
				throw new RuntimeException('identity_invalid');
			}
			$kode_pkm = trim((string) $staff->kode_pkm);
			if ($kode_pkm === '' || trim((string) $user->remark) !== $kode_pkm
				|| (string) $user->role !== 'dokter' || (string) $user->status !== 'aktif'
				|| trim((string) $user->no_hp) === '' || empty($user->tgl)
				|| !in_array((string) $user->gender, array('Laki-laki', 'Perempuan'), true)
				|| !$this->puskesmas_is_active($kode_pkm)
				|| $this->get_command_center_user_id($kode_pkm) === $user_id) {
				throw new RuntimeException('identity_invalid');
			}
			$link_query = $this->db->query(
				'SELECT staff_id FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE user_id = ? AND status = ? FOR UPDATE',
				array($user_id, 'aktif')
			);
			if (!$link_query || $link_query->num_rows() > 0) {
				throw new RuntimeException('duplicate_link');
			}
			$updated = $this->db
				->where('staff_id', $staff_id)
				->group_start()->where('user_id IS NULL', null, false)->or_where('user_id', 0)->group_end()
				->update('puskesmas_staff', array('user_id' => $user_id));
			if (!$updated || $this->db->affected_rows() !== 1 || $this->db->trans_status() === false
				|| !$this->db->trans_commit()) {
				throw new RuntimeException('link_failed');
			}
			$transaction_started = false;
			$this->log_account_audit('link_personal_nakes_account', $user_id, $staff_id);
			return array('status' => 'success', 'message' => 'Akun personal dihubungkan.');
		} catch (Throwable $e) {
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			return array('status' => 'error', 'message' => 'Akun login belum dapat dihubungkan. Periksa status akun, Puskesmas, dan hubungan staf.');
		} finally {
			if ($lock_acquired) {
				$this->db->query('SELECT RELEASE_LOCK(?) AS released', array($lock_name));
			}
			$this->db->db_debug = $db_debug;
		}
	}

	public function unbind_staff_account($staff_id)
	{
		$staff_id = (int) $staff_id;
		if ($staff_id < 1 || !$this->table_ready()) {
			return array('status' => 'error', 'message' => 'Pilih staf yang valid.');
		}

		$lock_name = 'doclinc_staff_personal_account_link';
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$transaction_started = false;
		$lock_acquired = false;
		try {
			$lock_query = $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', array($lock_name));
			$lock_row = $lock_query ? $lock_query->row() : null;
			$lock_acquired = $lock_row && ($lock_row->acquired === 1 || $lock_row->acquired === '1');
			if (!$lock_acquired || !$this->db->trans_begin()) {
				throw new RuntimeException('lock_failed');
			}
			$transaction_started = true;
			$staff_query = $this->db->query(
				'SELECT staff_id, user_id FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE staff_id = ? FOR UPDATE',
				array($staff_id)
			);
			$staff = $staff_query ? $staff_query->row() : null;
			$user_id = $staff ? (int) $staff->user_id : 0;
			if (!$staff || $user_id < 1) {
				throw new RuntimeException('link_missing');
			}
			$updated = $this->db
				->where('staff_id', $staff_id)
				->where('user_id', $user_id)
				->update('puskesmas_staff', array('user_id' => null));
			if (!$updated || $this->db->affected_rows() !== 1 || $this->db->trans_status() === false
				|| !$this->db->trans_commit()) {
				throw new RuntimeException('unlink_failed');
			}
			$transaction_started = false;
			$this->log_account_audit('unlink_personal_nakes_account', $user_id, $staff_id);
			return array('status' => 'success', 'message' => 'Akun personal dilepas. Akun tidak dihapus.');
		} catch (Throwable $e) {
			if ($transaction_started) {
				$this->db->trans_rollback();
			}
			return array('status' => 'error', 'message' => 'Akun login belum dapat dilepas.');
		} finally {
			if ($lock_acquired) {
				$this->db->query('SELECT RELEASE_LOCK(?) AS released', array($lock_name));
			}
			$this->db->db_debug = $db_debug;
		}
	}

	public function get_dokter_account_by_id($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->db->table_exists('users')) {
			return null;
		}

		$this->db
			->where('userId', $user_id)
			->where('role', 'dokter');

		return $this->db->get('users')->row();
	}

	public function account_linked_to_other_active_staff($user_id, $staff_id)
	{
		$user_id = (int) $user_id;
		$staff_id = (int) $staff_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return false;
		}

		$this->db
			->where('user_id', $user_id)
			->where('staff_id !=', $staff_id)
			->where('status', 'aktif');

		return $this->db->count_all_results('puskesmas_staff') > 0;
	}

	private function command_center_linked_to_staff($user_id, $lock_rows = false)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->table_ready()) {
			return false;
		}

		$sql = 'SELECT staff_id FROM ' . $this->db->dbprefix('puskesmas_staff') . ' WHERE user_id = ?';
		if ($lock_rows) {
			$sql .= ' FOR UPDATE';
		}
		$query = $this->db->query($sql, array($user_id));

		return $query && $query->num_rows() > 0;
	}

	private function get_linked_active_user_ids($exclude_staff_id = null)
	{
		if (!$this->table_ready()) {
			return array();
		}

		$exclude_staff_id = (int) $exclude_staff_id;
		$this->db
			->select('user_id')
			->from('puskesmas_staff')
			->where('user_id IS NOT NULL', null, false)
			->where('user_id !=', 0)
			->where('status', 'aktif');
		if ($exclude_staff_id > 0) {
			$this->db->where('staff_id !=', $exclude_staff_id);
		}

		$rows = $this->db->get()->result();
		$user_ids = array();
		foreach ($rows as $row) {
			$user_id = (int) ($row->user_id ?? 0);
			if ($user_id > 0) {
				$user_ids[] = $user_id;
			}
		}

		return array_values(array_unique($user_ids));
	}

	private function user_value_exists_case_insensitive($field, $value)
	{
		$value = strtolower(trim((string) $value));
		if ($value === '' || !in_array($field, array('username', 'email'), true) || !$this->db->table_exists('users')) {
			return false;
		}

		return $this->db
			->where('LOWER(TRIM(' . $field . ')) =', $value)
			->count_all_results('users') > 0;
	}

	private function filter_staff_payload($data)
	{
		$row = array();
		foreach (array('kode_pkm', 'nama', 'gelar', 'no_hp', 'profesi', 'nomor_sip', 'sip_expired_at', 'nip', 'status') as $field) {
			if ($this->db->field_exists($field, 'puskesmas_staff') && array_key_exists($field, $data)) {
				$row[$field] = $field === 'nip' && $data[$field] === null ? null : trim((string) $data[$field]);
			}
		}
		if (isset($row['status']) && !in_array($row['status'], array('aktif', 'nonaktif'), true)) {
			$row['status'] = 'aktif';
		}

		return $row;
	}

	private function staff_fields()
	{
		return array('staff_id', 'kode_pkm', 'nama', 'no_hp', 'profesi', 'nomor_sip', 'user_id', 'status');
	}

	private function current_admin_id()
	{
		$username = $this->session->userdata('username');
		if (!$username || !$this->db->table_exists('users')) {
			return null;
		}

		$user = $this->db
			->select('userId')
			->where('username', $username)
			->where('role', 'admin')
			->get('users')
			->row();

		return $user ? (int) $user->userId : null;
	}

	private function log_account_audit($action, $target_user_id, $staff_id, $metadata = array())
	{
		if (!$this->db->table_exists('audit_logs')) {
			return false;
		}
		$db_debug = $this->db->db_debug;
		$this->db->db_debug = false;
		$metadata = is_array($metadata) ? $metadata : array();
		$metadata['staff_id'] = (int) $staff_id;
		$result = $this->db->insert('audit_logs', array(
			'actor_user_id' => $this->current_admin_id(),
			'action' => $action,
			'entity_type' => 'users',
			'entity_id' => (int) $target_user_id,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode($metadata),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = $db_debug;
		return $result;
	}
}
