<?php
require_once dirname(APPPATH, 2) . '/application/libraries/Core_puskesmas.php';

class Kelola_dokter_nakes_m extends MX_Controller
{
	/** @var object|null|string 'unloaded' sampai `master_model()` dipanggil */
	private $master_model = 'unloaded';

	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}
	public function get_data_dokter_nakes()
	{
		if (!$this->db->table_exists('users')) {
			return $this->db->query("SELECT NULL AS userId, NULL AS nama, NULL AS email, NULL AS username, NULL AS status, NULL AS remark, NULL AS no_hp, NULL AS foto, NULL AS nama_puskesmas, NULL AS puskesmas_status WHERE 1=0");
		}

		$this->db->select('users.*, m_puskesmas.nama_puskesmas, m_puskesmas.status AS puskesmas_status');
		$this->db->from('users');
		if ($this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users')) {
			$this->db->join('m_puskesmas', 'm_puskesmas.kode_pkm COLLATE utf8mb4_general_ci = CONVERT(users.remark USING utf8mb4) COLLATE utf8mb4_general_ci', 'left', false);
		}
		$this->db->where('users.role', 'dokter');
		if ($this->db->table_exists('puskesmas_staff') && $this->db->field_exists('user_id', 'puskesmas_staff')) {
			$this->db->where('NOT EXISTS (SELECT 1 FROM ' . $this->db->dbprefix('puskesmas_staff') . ' personal_staff WHERE personal_staff.user_id = users.userId)', null, false);
		}
		$this->db->order_by('users.nama', 'ASC');

		return $this->db->get();
	}

	public function get_puskesmas_options()
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

	/**
	 * Daftar sembilan Puskesmas inti, sudah dilengkapi keadaan basis data.
	 *
	 * Kode dan nama resmi datang dari `Core_puskesmas`, bukan dari isi tabel:
	 * jumlah baris daftar ini harus tetap sembilan walaupun `m_puskesmas`
	 * memuat kode warisan atau kehilangan satu baris. Nama disimpan di basis
	 * data tetap menang atas nama resmi, karena hanya itulah yang boleh
	 * diubah pengelola; kolom lain menyatakan apa yang BELUM ada.
	 *
	 * @return array<int,object>
	 */
	public function get_core_puskesmas_rows()
	{
		$core = $this->core_puskesmas();
		$codes = $core->codes();

		$facilities = $this->facilities_by_code($codes);
		$accounts = $this->account_by_code($codes);
		$staff = $this->staff_counts_by_code($codes);

		$rows = array();
		foreach ($core->rows() as $definition) {
			$code = $definition['kode_pkm'];
			$facility = isset($facilities[$code]) ? $facilities[$code] : null;
			$account = isset($accounts[$code]) ? $accounts[$code] : null;
			$row = new stdClass();
			$row->kode_pkm = $code;
			$row->nama_puskesmas = $facility && trim((string) $facility->nama_puskesmas) !== ''
				? (string) $facility->nama_puskesmas
				: $definition['nama_puskesmas'];
			$row->nama_resmi = $definition['nama_puskesmas'];
			$row->kecamatan = $definition['kecamatan'];
			$row->master_ready = $facility !== null;
			$row->status = $facility ? (string) $facility->status : '';
			$row->alamat = $facility && isset($facility->alamat) ? (string) $facility->alamat : '';
			$row->telepon = $facility && isset($facility->telepon) ? (string) $facility->telepon : '';
			$row->email = $facility && isset($facility->email) ? (string) $facility->email : '';
			$row->kepala_puskesmas = $facility && isset($facility->kepala_puskesmas)
				? (string) $facility->kepala_puskesmas
				: '';
			$row->account_user_id = $account ? (int) $account->userId : 0;
			$row->account_username = $account ? (string) $account->username : '';
			$row->account_status = $account ? (string) $account->status : '';
			$row->must_change_password = $account ? (int) ($account->must_change_password ?? 0) : 0;
			$row->password_changed_at = $account && isset($account->password_changed_at)
				? $account->password_changed_at
				: null;
			$row->account_total = $account && isset($account->account_total) ? (int) $account->account_total : 0;
			$row->staff_total = isset($staff[$code]) ? (int) $staff[$code]['total'] : 0;
			$row->staff_active = isset($staff[$code]) ? (int) $staff[$code]['aktif'] : 0;
			/* Kesiapan fasilitas: apakah alamat, titik lokasi, dan nama sudah
			   cukup untuk menerima permintaan warga. Aturannya milik
			   `Master_puskesmas_m` - di sini hanya dipinjam, supaya daftar ini
			   dan validasi aktivasi tidak pernah menghitung dua versi aturan. */
			$row->readiness_issues = $this->readiness_issues($facility);
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Kekurangan data fasilitas untuk satu baris, memakai aturan yang sama
	 * dengan validasi aktivasi `Master_puskesmas_m`.
	 *
	 * Model master dimuat langsung dari berkasnya, bukan lewat `$this->load`:
	 * HMVC memasang model per modul, dan halaman ini tidak boleh gagal hanya
	 * karena model modul lain belum terdaftar. Bila berkasnya tidak ada, daftar
	 * kosong dikembalikan - lebih baik kolom kesiapan tidak tampil daripada
	 * daftar Puskesmas tidak dapat dibuka.
	 *
	 * @param object|null $facility baris `m_puskesmas` (null bila belum ada)
	 * @return array<int,string> kode masalah: facility_name, facility_address, ...
	 */
	public function readiness_issues($facility)
	{
		$master = $this->master_model();
		if (!$master) {
			return array();
		}

		return $master->readiness_issues($facility);
	}

	/**
	 * Ringkasan kesiapan sembilan Puskesmas inti.
	 *
	 * @param array<int,object> $rows hasil `get_core_puskesmas_rows()`
	 * @return array<string,mixed>
	 */
	public function readiness_summary(array $rows)
	{
		$summary = array('total' => 0, 'ready' => 0, 'attention' => 0, 'issue_counts' => array());
		foreach ($rows as $row) {
			$issues = isset($row->readiness_issues) ? (array) $row->readiness_issues : array();
			$summary['total']++;
			if (empty($issues)) {
				$summary['ready']++;
				continue;
			}
			$summary['attention']++;
			foreach ($issues as $issue) {
				$summary['issue_counts'][$issue] = ($summary['issue_counts'][$issue] ?? 0) + 1;
			}
		}

		return $summary;
	}

	/**
	 * @return object|null instans `Master_puskesmas_m`, atau null
	 */
	private function master_model()
	{
		if ($this->master_model === 'unloaded') {
			$this->master_model = null;
			$path = APPPATH . 'modules/master_puskesmas/models/Master_puskesmas_m.php';
			if (!class_exists('Master_puskesmas_m') && is_file($path)) {
				require_once $path;
			}
			if (class_exists('Master_puskesmas_m')) {
				/* Tanpa konstruktor: `Master_puskesmas_m` turunan controller, dan
				   memanggil konstruktornya dari dalam controller lain akan
				   menginisialisasi ulang seluruh aplikasi. Aturan kesiapan hanya
				   memakai metode murni (tanpa `$this->db`), jadi instance kosong
				   cukup. */
				$mirror = new ReflectionClass('Master_puskesmas_m');
				$this->master_model = $mirror->newInstanceWithoutConstructor();
			}
		}

		return $this->master_model;
	}

	/**
	 * Satu baris Puskesmas inti, atau null bila kode bukan Puskesmas inti.
	 *
	 * @param mixed $kode_pkm
	 * @return object|null
	 */
	public function get_puskesmas($kode_pkm)
	{
		$core = $this->core_puskesmas();
		$kode_pkm = $core->normalize($kode_pkm);
		if (!$core->isCore($kode_pkm)) {
			return null;
		}

		$facilities = $this->facilities_by_code(array($kode_pkm));
		$accounts = $this->account_by_code(array($kode_pkm));
		$staff = $this->staff_counts_by_code(array($kode_pkm));

		$facility = isset($facilities[$kode_pkm]) ? $facilities[$kode_pkm] : null;
		$row = new stdClass();
		$row->kode_pkm = $kode_pkm;
		$row->nama_puskesmas = $facility && trim((string) $facility->nama_puskesmas) !== ''
			? (string) $facility->nama_puskesmas
			: (string) $core->name($kode_pkm);
		$row->kecamatan = $core->kecamatan($kode_pkm);
		$row->master_ready = $facility !== null;
		$row->status = $facility ? (string) $facility->status : '';
		$row->alamat = $facility && isset($facility->alamat) ? (string) $facility->alamat : '';
		$row->telepon = $facility && isset($facility->telepon) ? (string) $facility->telepon : '';
		$row->email = $facility && isset($facility->email) ? (string) $facility->email : '';
		$row->kepala_puskesmas = $facility && isset($facility->kepala_puskesmas)
			? (string) $facility->kepala_puskesmas
			: '';
		$row->kota = $facility && isset($facility->kota) ? (string) $facility->kota : '';
		$row->provinsi = $facility && isset($facility->provinsi) ? (string) $facility->provinsi : '';
		$row->latitude = $facility && isset($facility->latitude) ? (string) $facility->latitude : '';
		$row->longitude = $facility && isset($facility->longitude) ? (string) $facility->longitude : '';
		$row->readiness_issues = $this->readiness_issues($facility);
		$row->staff_total = isset($staff[$kode_pkm]) ? (int) $staff[$kode_pkm]['total'] : 0;
		$row->staff_active = isset($staff[$kode_pkm]) ? (int) $staff[$kode_pkm]['aktif'] : 0;

		return $row;
	}

	/**
	 * Apakah kolom profil (email, kepala Puskesmas) sudah ada.
	 *
	 * Layar detail tetap terbuka walau migrasi belum dijalankan; isian yang
	 * kolomnya belum ada ditampilkan nonaktif dengan penjelasan, bukan
	 * dihilangkan - pengelola perlu tahu apa yang belum tersedia.
	 *
	 * @return bool
	 */
	public function profile_schema_ready()
	{
		return $this->db->table_exists('m_puskesmas')
			&& $this->db->field_exists('email', 'm_puskesmas')
			&& $this->db->field_exists('kepala_puskesmas', 'm_puskesmas');
	}

	/**
	 * @param mixed $value
	 * @return Core_puskesmas
	 */
	public function core_puskesmas()
	{
		return new Core_puskesmas();
	}


	public function normalize_remark($remark)
	{
		$remark = trim((string) $remark);
		if ($remark === '') {
			return 'DEFAULT';
		}

		if (!$this->db->table_exists('m_puskesmas')) {
			return $remark;
		}

		$exists = $this->db
			->where('kode_pkm', $remark)
			->where('kode_pkm !=', 'DEFAULT')
			->count_all_results('m_puskesmas') > 0;

		return $exists ? $remark : 'DEFAULT';
	}

	public function has_active_puskesmas()
	{
		return count($this->get_puskesmas_options()) > 0;
	}

	public function puskesmas_exists($remark)
	{
		$remark = trim((string) $remark);
		if ($remark === '' || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$this->db->where('kode_pkm', $remark);
		$this->db->where('kode_pkm !=', 'DEFAULT');
		if ($this->db->field_exists('status', 'm_puskesmas')) {
			$this->db->where('status', 'aktif');
		}

		return $this->db->count_all_results('m_puskesmas') > 0;
	}

	public function email_exists($email, $exclude_user_id = null)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('email', $email);
		if ($exclude_user_id !== null) {
			$this->db->where('userId !=', $exclude_user_id);
		}

		return $this->db->count_all_results('users') > 0;
	}

	public function username_exists($username, $exclude_user_id = null)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('username', $username);
		if ($exclude_user_id !== null) {
			$this->db->where('userId !=', $exclude_user_id);
		}

		return $this->db->count_all_results('users') > 0;
	}

	public function create_dokter_nakes($data)
	{
		if (!$this->db->table_exists('users')
			|| !$this->db->field_exists('must_change_password', 'users')
			|| !$this->db->field_exists('password_changed_at', 'users')) {
			return false;
		}

		$now = date('Y-m-d H:i:s');
		$insert = array(
			'nama' => $data['nama'],
			'email' => $data['email'],
			'username' => $data['username'],
			'password' => $data['password'],
			'role' => 'dokter',
			'status' => in_array($data['status'], array('aktif', 'nonaktif'), TRUE) ? $data['status'] : 'aktif',
			'must_change_password' => 1,
			'password_changed_at' => null,
		);

		foreach (array('no_hp', 'remark', 'updated_by') as $field) {
			if ($this->db->field_exists($field, 'users')) {
				$insert[$field] = isset($data[$field]) ? $data[$field] : null;
			}
		}
		if ($this->db->field_exists('created_at', 'users')) {
			$insert['created_at'] = $now;
		}
		if ($this->db->field_exists('updated_at', 'users')) {
			$insert['updated_at'] = $now;
		}

		$this->db->trans_begin();
		$this->db->insert('users', $insert);
		$user_id = $this->db->insert_id();
		if (!$user_id) {
			$this->db->trans_rollback();
			return false;
		}

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		}

		$this->db->trans_commit();
		$this->log_audit('admin_create_puskesmas_nakes_user', $user_id);

		return $user_id;
	}
	public function aktifkan_user($id_user, $user, $remark_aktif)
	{
		return $this->update_status($id_user, 'aktif', $user, $remark_aktif);
	}
	public function nonaktifkan_user($id_user, $user, $remark_nonaktif)
	{
		return $this->update_status($id_user, 'nonaktif', $user, $remark_nonaktif);
	}

	public function delete_dokter_nakes($id_user, $user = null, $remark_nonaktif = null)
	{
		return $this->update_status($id_user, 'nonaktif', $user, $remark_nonaktif);
	}

	public function get_dokter_user($id_user)
	{
		$id_user = (int) $id_user;
		if ($id_user < 1 || !$this->db->table_exists('users')) {
			return null;
		}

		return $this->db
			->where('userId', $id_user)
			->where('role', 'dokter')
			->get('users')
			->row();
	}

	public function blocking_relations_for_user($id_user)
	{
		$id_user = (int) $id_user;
		if ($id_user < 1) {
			return array();
		}

		$checks = array(
			array('requests', 'user_id', 'requests.user_id'),
			array('requests', 'dokter_id', 'requests.dokter_id'),
			array('requests', 'accepted_by_user_id', 'requests.accepted_by_user_id'),
			array('requests', 'assigned_nakes_user_id', 'requests.assigned_nakes_user_id'),
			array('requests', 'assigned_nakes_by_user_id', 'requests.assigned_nakes_by_user_id'),
			array('medicalrecords', 'user_id', 'medicalrecords.user_id'),
			array('medicalrecords', 'dokter_id', 'medicalrecords.dokter_id'),
			array('puskesmas_staff', 'user_id', 'puskesmas_staff.user_id'),
			array('admin_notification_reads', 'user_id', 'admin_notification_reads.user_id'),
			array('notifications', 'recipient_user_id', 'notifications.recipient_user_id'),
			array('notifications', 'actor_user_id', 'notifications.actor_user_id'),
			array('consultation_messages', 'sender_user_id', 'consultation_messages.sender_user_id'),
			array('call_sessions', 'caller_user_id', 'call_sessions.caller_user_id'),
			array('call_sessions', 'callee_user_id', 'call_sessions.callee_user_id'),
			array('request_events', 'actor_user_id', 'request_events.actor_user_id'),
			array('request_staff_assignments', 'assigned_by_user_id', 'request_staff_assignments.assigned_by_user_id'),
		);

		$blocking = array();
		foreach ($checks as $check) {
			list($table, $field, $label) = $check;
			if (!$this->db->table_exists($table) || !$this->db->field_exists($field, $table)) {
				continue;
			}

			$count = (int) $this->db
				->where($field, $id_user)
				->count_all_results($table);
			if ($count > 0) {
				$blocking[$label] = $count;
			}
		}

		return $blocking;
	}

	public function destroy_dokter_nakes($id_user)
	{
		$id_user = (int) $id_user;
		if ($id_user < 1 || !$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		$result = $this->db->delete('users');
		if ($result) {
			$this->log_audit('admin_destroy_puskesmas_nakes_user', $id_user);
		}

		return $result;
	}

	public function update_dokter_nakes($id_user, $data)
	{
		if (!$this->db->table_exists('users')
			|| (array_key_exists('password', $data)
				&& (!$this->db->field_exists('must_change_password', 'users')
					|| !$this->db->field_exists('password_changed_at', 'users')))) {
			return false;
		}

		$allowed = array();
		foreach (array('nama', 'email', 'username', 'remark', 'no_hp', 'status', 'password', 'updated_by', 'updated_at') as $field) {
			if ($this->db->field_exists($field, 'users') && array_key_exists($field, $data)) {
				$allowed[$field] = $data[$field];
			}
		}
		if (isset($allowed['password'])) {
			$allowed['must_change_password'] = 1;
		}

		if (empty($allowed)) {
			return false;
		}

		$revoke = isset($allowed['password']) && $this->config->item('single_active_session_enabled') === true;
		$binding_service = $revoke ? $this->session_binding_service() : null;
		if ($revoke && (!$binding_service || !$this->db->trans_begin())) {
			return false;
		}
		if ($revoke) {
			$locked = $this->db->query(
				'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? AND role = ? FOR UPDATE',
				array((int) $id_user, 'dokter')
			);
			if (!$locked || !$locked->row()) {
				$this->db->trans_rollback();
				return false;
			}
		}
		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		$result = $this->db->update('users', $allowed);
		if ($revoke) {
			if (!$result || !$binding_service->revokeLocked((int) $id_user)
				|| $this->db->trans_status() === false || !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return false;
			}
		}
		if ($result) {
			$this->log_audit('admin_update_puskesmas_nakes_user', $id_user);
			if (isset($allowed['password'])) {
				$this->log_audit('admin_reset_puskesmas_nakes_password', $id_user);
			}
		}

		return $result;
	}

	/**
	 * Akun koordinator Puskesmas: akun `role = dokter` tertua yang tertaut ke
	 * kode itu lewat `users.remark`.
	 *
	 * Satu Puskesmas secara data boleh punya lebih dari satu akun; layar detail
	 * hanya mengelola satu, dan yang tertua adalah pilihan yang stabil - akun
	 * yang dibuat belakangan tidak akan mengambil alih tautan "reset password".
	 *
	 * @param mixed $kode_pkm
	 * @return object|null
	 */
	public function get_puskesmas_account($kode_pkm)
	{
		$core = $this->core_puskesmas();
		$kode_pkm = $core->normalize($kode_pkm);
		if (!$core->isCore($kode_pkm)) {
			return null;
		}

		$accounts = $this->account_by_code(array($kode_pkm));

		return isset($accounts[$kode_pkm]) ? $accounts[$kode_pkm] : null;
	}

	/**
	 * Jumlah staf pada satu Puskesmas. Tanpa `$status`, menghitung semua baris.
	 *
	 * @param mixed $kode_pkm
	 * @param string|null $status
	 * @return int
	 */
	public function count_staff($kode_pkm, $status = null)
	{
		$kode_pkm = $this->core_puskesmas()->normalize($kode_pkm);
		if ($kode_pkm === '' || !$this->db->table_exists('puskesmas_staff')) {
			return 0;
		}

		$this->db->where('kode_pkm', $kode_pkm);
		if ($status !== null) {
			$this->db->where('status', $status);
		}

		return (int) $this->db->count_all_results('puskesmas_staff');
	}

	/**
	 * Menyimpan profil fasilitas: alamat, telepon, email, nama kepala, dan
	 * titik lokasi.
	 *
	 * Daftar putih kolomnya sempit dengan sengaja - `kode_pkm` dan `status`
	 * menentukan routing permintaan warga, jadi keduanya tidak boleh berubah
	 * dari layar profil. Nilai kosong disimpan sebagai NULL, bukan string
	 * kosong, supaya "belum diisi" tidak menyamar sebagai isian yang sah.
	 *
	 * Titik lokasi ikut di sini karena `master_puskesmas` - satu-satunya layar
	 * yang dulu menyuntingnya - sekarang hanya mengalihkan ke daftar Puskesmas.
	 * Tanpa kolom ini, koordinat fasilitas menjadi data yang tidak dapat
	 * diperbaiki dari panel.
	 *
	 * @param mixed $kode_pkm
	 * @param array<string,mixed> $data
	 * @return bool
	 */
	public function update_puskesmas_profile($kode_pkm, $data)
	{
		$core = $this->core_puskesmas();
		$kode_pkm = $core->normalize($kode_pkm);
		if (!$core->isCore($kode_pkm) || !$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$update = array();
		foreach (array('alamat', 'telepon', 'email', 'kepala_puskesmas', 'latitude', 'longitude') as $field) {
			if (!$this->db->field_exists($field, 'm_puskesmas') || !array_key_exists($field, $data)) {
				continue;
			}
			$value = is_scalar($data[$field]) ? trim((string) $data[$field]) : '';
			$update[$field] = $value === '' ? null : $value;
		}
		if (empty($update)) {
			return false;
		}

		$this->db->where('kode_pkm', $kode_pkm);
		$result = $this->db->update('m_puskesmas', $update);
		if ($result) {
			$this->log_facility_audit('admin_update_puskesmas_profile', $kode_pkm, array_keys($update));
		}

		return (bool) $result;
	}

	private function update_status($id_user, $status, $user, $remark)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$data = array('status' => $status);
		$updates = array('updated_by' => $user, 'updated_at' => date('Y-m-d H:i:s'));
		if ($remark !== null) {
			$updates['remark'] = $remark;
		}

		foreach ($updates as $field => $value) {
			if ($this->db->field_exists($field, 'users')) {
				$data[$field] = $value;
			}
		}

		$revoke = $status === 'nonaktif' && $this->config->item('single_active_session_enabled') === true;
		$binding_service = $revoke ? $this->session_binding_service() : null;
		if ($revoke && (!$binding_service || !$this->db->trans_begin())) {
			return false;
		}
		if ($revoke) {
			$locked = $this->db->query(
				'SELECT userId FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? AND role = ? FOR UPDATE',
				array((int) $id_user, 'dokter')
			);
			if (!$locked || !$locked->row()) {
				$this->db->trans_rollback();
				return false;
			}
		}
		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		$result = $this->db->update('users', $data);
		if ($revoke) {
			if (!$result || !$binding_service->revokeLocked((int) $id_user)
				|| $this->db->trans_status() === false || !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return false;
			}
		}
		if ($result) {
			$this->log_audit($status === 'aktif' ? 'admin_enable_puskesmas_nakes_user' : 'admin_disable_puskesmas_nakes_user', $id_user);
		}

		return $result;
	}

	private function session_binding_service()
	{
		$service_file = dirname(APPPATH, 2) . '/application/libraries/Session_binding_service.php';
		if (!is_file($service_file)) {
			return null;
		}
		require_once $service_file;
		$service = new Session_binding_service($this->db);
		return $service->schemaReady() ? $service : null;
	}

	/**
	 * Baris `m_puskesmas` untuk kode tertentu, hanya kolom yang ada.
	 *
	 * @param array<int,string> $codes
	 * @return array<string,object>
	 */
	private function facilities_by_code($codes)
	{
		$result = array();
		if (empty($codes) || !$this->db->table_exists('m_puskesmas')) {
			return $result;
		}

		$fields = array('kode_pkm', 'nama_puskesmas', 'status');
		foreach (array('alamat', 'telepon', 'email', 'kepala_puskesmas', 'kota', 'provinsi', 'latitude', 'longitude') as $field) {
			if ($this->db->field_exists($field, 'm_puskesmas')) {
				$fields[] = $field;
			}
		}

		$rows = $this->db
			->select(implode(',', $fields))
			->where_in('kode_pkm', $codes)
			->get('m_puskesmas')
			->result();
		foreach ($rows as $row) {
			$result[trim((string) $row->kode_pkm)] = $row;
		}

		return $result;
	}

	/**
	 * Akun koordinator per kode, ditambah `account_total`.
	 *
	 * @param array<int,string> $codes
	 * @return array<string,object>
	 */
	private function account_by_code($codes)
	{
		$result = array();
		if (empty($codes) || !$this->db->table_exists('users') || !$this->db->field_exists('remark', 'users')) {
			return $result;
		}

		$fields = array('userId', 'username', 'status', 'remark');
		foreach (array('must_change_password', 'password_changed_at') as $field) {
			if ($this->db->field_exists($field, 'users')) {
				$fields[] = $field;
			}
		}

		$rows = $this->db
			->select(implode(',', $fields))
			->where('role', 'dokter')
			->where_in('remark', $codes)
			->order_by('userId', 'ASC')
			->get('users')
			->result();
		foreach ($rows as $row) {
			$code = trim((string) $row->remark);
			if (!isset($result[$code])) {
				$row->account_total = 0;
				$result[$code] = $row;
			}
			$result[$code]->account_total++;
		}

		return $result;
	}

	/**
	 * @param array<int,string> $codes
	 * @return array<string,array<string,int>>
	 */
	private function staff_counts_by_code($codes)
	{
		$result = array();
		if (empty($codes) || !$this->db->table_exists('puskesmas_staff')
			|| !$this->db->field_exists('status', 'puskesmas_staff')) {
			return $result;
		}

		$this->db->select('kode_pkm, COUNT(*) AS total, SUM(status = \'aktif\') AS aktif', false);
		$this->db->where_in('kode_pkm', $codes);
		$this->db->group_by('kode_pkm');
		foreach ($this->db->get('puskesmas_staff')->result() as $row) {
			$result[trim((string) $row->kode_pkm)] = array(
				'total' => (int) $row->total,
				'aktif' => (int) $row->aktif,
			);
		}

		return $result;
	}

	/**
	 * Jejak audit perubahan profil fasilitas.
	 *
	 * Dipisahkan dari `log_audit()` karena `entity_type` di sana selalu menunjuk
	 * baris `users`; mencampur keduanya membuat penampil audit menautkan
	 * perubahan Puskesmas ke akun yang tidak ada.
	 *
	 * @param string $action
	 * @param string $kode_pkm
	 * @param array<int,string> $fields
	 * @return bool
	 */
	private function log_facility_audit($action, $kode_pkm, $fields)
	{
		if (!$this->db->table_exists('audit_logs')) {
			return false;
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$result = $this->db->insert('audit_logs', array(
			'actor_user_id' => $this->current_admin_id(),
			'action' => $action,
			'entity_type' => 'm_puskesmas',
			'entity_id' => null,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode(array('kode_pkm' => $kode_pkm, 'fields' => $fields)),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = $db_debug;

		return (bool) $result;
	}

	private function log_audit($action, $target_user_id)
	{
		if (!$this->db->table_exists('audit_logs')) {
			return false;
		}

		$db_debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;
		$result = $this->db->insert('audit_logs', array(
			'actor_user_id' => $this->current_admin_id(),
			'action' => $action,
			'entity_type' => 'users',
			'entity_id' => $target_user_id,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode(array('target_user_id' => $target_user_id, 'role' => 'dokter')),
			'created_at' => date('Y-m-d H:i:s'),
		));
		$this->db->db_debug = $db_debug;

		return $result;
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

		return $user ? $user->userId : null;
	}
}
