<?php
class Konsultasi_nakes_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function normalize_puskesmas_code($code)
	{
		$code = trim((string) $code);
		return $code !== '' && strtoupper($code) !== 'DEFAULT' ? $code : '';
	}

	private function request_assigned_puskesmas_code($request)
	{
		return $request && isset($request->assigned_puskesmas_code)
			? $this->normalize_puskesmas_code($request->assigned_puskesmas_code)
			: '';
	}

	private function get_user_puskesmas_code($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1) {
			return '';
		}

		$auth_db = $this->load->database('default', true);
		if (!$auth_db->field_exists('remark', 'users')) {
			return '';
		}

		$user = $auth_db
			->select('remark')
			->where('userId', $user_id)
			->where('role', 'dokter')
			->get('users')
			->row();
		if (method_exists($auth_db, 'reset_query')) {
			$auth_db->reset_query();
		}

		return $user ? $this->normalize_puskesmas_code($user->remark) : '';
	}

	private function request_matches_user_puskesmas($request, $user_id)
	{
		$request_code = $this->request_assigned_puskesmas_code($request);
		return $request_code === '' || $request_code === $this->get_user_puskesmas_code($user_id);
	}

	private function where_legacy_assigned_puskesmas($prefix = '')
	{
		$field = $prefix . 'assigned_puskesmas_code';
		$this->db->group_start();
		$this->db->where($field . ' IS NULL', null, false);
		$this->db->or_where("TRIM({$field}) = ''", null, false);
		$this->db->or_where("UPPER(TRIM({$field})) = 'DEFAULT'", null, false);
		$this->db->group_end();
	}

	private function where_puskesmas_flow_owner($user_id, $prefix = '')
	{
		if (!$this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			return;
		}

		$puskesmas_code = $this->get_user_puskesmas_code($user_id);
		if ($puskesmas_code === '') {
			$this->where_legacy_assigned_puskesmas($prefix);
			return;
		}

		$field = $prefix . 'assigned_puskesmas_code';
		$this->db->group_start();
		$this->db->where('TRIM(' . $field . ') = ' . $this->db->escape($puskesmas_code), null, false);
		$this->db->or_group_start();
		$this->where_legacy_assigned_puskesmas($prefix);
		$this->db->group_end();
		$this->db->group_end();
	}

	private function where_handling_nakes_owner($user_id, $prefix = '')
	{
		$this->db->group_start();
		if ($this->db->field_exists('assigned_nakes_user_id', 'requests')) {
			$this->db->where($prefix . 'assigned_nakes_user_id', $user_id);
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$this->db->or_where($prefix . 'accepted_by_user_id', $user_id);
			}
			if ($this->db->field_exists('dokter_id', 'requests')) {
				$this->db->or_where($prefix . 'dokter_id', $user_id);
			}
		} else {
			$this->db->where($prefix . 'dokter_id', $user_id);
			if ($this->db->field_exists('accepted_by_user_id', 'requests')) {
				$this->db->or_where($prefix . 'accepted_by_user_id', $user_id);
			}
		}
		$this->db->group_end();
	}

	public function get_data_request($request_id, $doctor_id = null, $identity_context = null)
	{
		$doctor_id = (int) $doctor_id;
		$database_identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($doctor_id, true)
			: null;
		if ($doctor_id < 1
			|| !is_array($identity_context)
			|| empty($identity_context['valid'])
			|| (int) $identity_context['user_id'] !== $doctor_id
			|| empty($database_identity['valid'])
			|| !function_exists('doclinc_can_view_nakes_request')
			|| !doclinc_can_view_nakes_request($request_id, $database_identity)) {
			return $this->db->query('SELECT 1 WHERE 1 = 0');
		}
		$userIdSelect = $this->db->field_exists('userid', 'users') ? 'users.userid' : 'users.userId AS userid';
		$tglSelect = $this->db->field_exists('tgl', 'users') ? 'users.tgl' : 'NULL AS tgl';

		$this->db
			->select('requests.*, users.nama')
			->select($userIdSelect, FALSE)
			->select($tglSelect, FALSE)
			->from('requests')
			->join('users', 'requests.user_id = users.userId')
			->where('requests.request_status', 'Accepted')
			->where('requests.request_id', $request_id);
		return $this->db
			->get();
	}

	// public function save_konsultasi_nakes($request_id, $diagnosa, $saran)
	// {
	// 	$date = date('Y-m-d H:i:s');
	// 	$user = $this->session->userdata('id');
	// 	$this->db->query("UPDATE requests SET request_status = 'Completed' WHERE request_id = '$request_id'");
	// 	$this->db->query("INSERT konsultasi SET
	// 					 request_id = '$request_id',
	// 					 diagnosa = '$diagnosa',
	// 					 saran = '$saran',
	// 					 create_date = '$date',
	// 					 create_user = '$user'");
	// }

	public function save_konsultasi_nakes($request_id, $diagnosa, $saran, $kriteria, $rujukan, $file_path, $terapi, $doctor_id = null, $identity_context = null, $anamnesis = null, $write_anamnesis = false)
	{
		$date = date('Y-m-d H:i:s');
		$user = $doctor_id ?: $this->session->userdata('id');
		$terapi = is_array($terapi) ? $terapi : [];

		if (!is_array($identity_context)
			|| empty($identity_context['valid'])
			|| (int) $identity_context['user_id'] !== (int) $user) {
			return false;
		}

		$this->db->trans_begin();
		$request = $this->db->query(
			'SELECT * FROM ' . $this->db->dbprefix('requests') . ' WHERE request_id = ? FOR UPDATE',
			array($request_id)
		)->row();
		$database_identity = function_exists('doclinc_dokter_identity_context')
			? doclinc_dokter_identity_context($user, true)
			: null;
		$access_context = $request && function_exists('doclinc_nakes_request_access_context')
			? doclinc_nakes_request_access_context($request_id, $database_identity)
			: null;
		if (!$request || empty($access_context['can_handle']) || (string) $request->request_status !== 'Accepted') {
			$this->db->trans_rollback();
			return false;
		}
		if ($write_anamnesis
			&& (!$this->db->table_exists('medicalrecords')
				|| !$this->db->field_exists('record_id', 'medicalrecords')
				|| !$this->db->field_exists('request_id', 'medicalrecords')
				|| !$this->db->field_exists('anamnesis', 'medicalrecords'))) {
			$this->db->trans_rollback();
			return false;
		}

		$request_data = ['request_status' => 'Completed'];
		if ($this->db->field_exists('updated_at', 'requests')) {
			$request_data['updated_at'] = $date;
		}
		if ($this->db->field_exists('visit_status', 'requests')) {
			$request_data['visit_status'] = 'completed';
		}
		if ($this->db->field_exists('consultation_mode', 'requests')) {
			$consultation_mode = $this->consultation_mode_from_kriteria($kriteria);
			if ($consultation_mode !== null) {
				$request_data['consultation_mode'] = $consultation_mode;
			}
		}

		$this->db->where('request_id', $request_id)->where('request_status', 'Accepted');
		$request_code = isset($request->assigned_puskesmas_code) ? trim((string) $request->assigned_puskesmas_code) : '';
		if ($request_code !== '') {
			$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($database_identity['puskesmas_code']), null, false);
		} else {
			$this->db->group_start()->where('assigned_puskesmas_code IS NULL', null, false)->or_where("TRIM(assigned_puskesmas_code) = ''", null, false)->group_end();
		}
		if ($this->db->field_exists('visit_completed_at', 'requests')) {
			$this->db->set('visit_completed_at', 'COALESCE(visit_completed_at, ' . $this->db->escape($date) . ')', FALSE);
		}
		$this->db->update('requests', $request_data);
		if ($this->db->affected_rows() < 1) {
			$this->db->trans_rollback();
			return false;
		}

		$data_konsul = [
			'request_id' => $request_id,
			'diagnosa' => $diagnosa,
			'saran' => $saran,
			'kriteria' => $kriteria,
			'rujukan' => $rujukan,
			'foto' => $file_path,
			'create_date' => $date,
			'create_user' => $user
		];

		if ($this->db->table_exists('medicalrecords')) {
			$record = [
				'request_id' => $request_id,
				'diagnosis' => $diagnosa,
				'treatment' => $this->build_treatment_summary($kriteria, $rujukan, $file_path, $terapi),
				'recommendations' => $saran,
				'created_at' => $date
			];

			if ($write_anamnesis) {
				$existing = $this->db->query(
					'SELECT * FROM ' . $this->db->dbprefix('medicalrecords') . ' WHERE request_id = ? ORDER BY record_id DESC LIMIT 1 FOR UPDATE',
					array($request_id)
				)->row();
				if ($anamnesis !== null || !$existing) {
					$record['anamnesis'] = $anamnesis;
				}
			} else {
				$existing = $this->db->get_where('medicalrecords', ['request_id' => $request_id])->row();
			}
			if ($existing) {
				if ($write_anamnesis) {
					$this->db->where('record_id', (int) $existing->record_id);
				} else {
					$this->db->where('request_id', $request_id);
				}
				if (!$this->db->update('medicalrecords', $record)) {
					$this->db->trans_rollback();
					return false;
				}
			} else {
				if (!$this->db->insert('medicalrecords', $record)) {
					$this->db->trans_rollback();
					return false;
				}
			}
		}

		if ($this->db->table_exists('konsultasi')) {
			$existing_konsul = $this->db->get_where('konsultasi', ['request_id' => $request_id])->row();
			if ($existing_konsul) {
				$this->db->where('request_id', $request_id);
				$this->db->update('konsultasi', $data_konsul);
				$konsul_id = $existing_konsul->konsul_id;
			} else {
				$this->db->insert('konsultasi', $data_konsul);
				$konsul_id = $this->db->insert_id();
			}

			if ($this->db->table_exists('terapi') && !empty($terapi)) {
				foreach ($terapi as $t) {
					$data_terapi = [
						'konsul_id' => $konsul_id,
						'terapi' => $t['terapi'] ?? '',
						'signa' => ($t['jumlah'] ?? '') . ' - ' . ($t['cara'] ?? ''),
						'keterangan' => $t['keterangan'] ?? '',
						'create_date' => $date,
						'create_user' => $user
					];
					$this->db->insert('terapi', $data_terapi);
				}
			}
		}

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return false;
		}

		$this->db->trans_commit();
		return true;
	}

	private function consultation_mode_from_kriteria($kriteria)
	{
		$normalized = strtolower(trim((string) $kriteria));
		if ($normalized === '1' || $normalized === 'kunjungan nakes') {
			return 'visit';
		}
		if ($normalized === '0' || $normalized === 'selesai konsultasi') {
			return 'non_visit';
		}

		return null;
	}

	private function build_treatment_summary($kriteria, $rujukan, $file_path, $terapi)
	{
		$parts = [];
		if (!empty($kriteria)) {
			$parts[] = 'Kriteria: ' . $kriteria;
		}
		if (!empty($rujukan)) {
			$parts[] = 'Rujukan: ' . $rujukan;
		}
		if (!empty($file_path)) {
			$parts[] = 'Foto: ' . $file_path;
		}
		if (!empty($terapi)) {
			$terapi_parts = [];
			foreach ($terapi as $t) {
				$terapi_parts[] = trim(($t['terapi'] ?? '') . ' ' . ($t['jumlah'] ?? '') . ' ' . ($t['cara'] ?? '') . ' ' . ($t['keterangan'] ?? ''));
			}
			$parts[] = 'Terapi: ' . implode('; ', array_filter($terapi_parts));
		}

		return implode("\n", $parts);
	}


	public function getICD($term = "")
	{
		if (!$this->db->table_exists('keluhan')) {
			return [];
		}

		// $query = $this->db->query("SELECT id_keluhan, nama_keluhan FROM keluhan");
		// return $query->result();
		$this->db->select('id_keluhan, nama_keluhan');
		$this->db->like('nama_keluhan', $term);
		$this->db->or_like('id_keluhan', $term);
		$query = $this->db->get('keluhan');
		return $query->result();
	}
}
