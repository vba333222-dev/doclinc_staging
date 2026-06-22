<?php
class Master_puskesmas_m extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->db = $this->load->database('default', TRUE);
	}

	public function get_all()
	{
		if (!$this->db->table_exists('m_puskesmas')) {
			return $this->db->query("SELECT NULL AS kode_pkm, NULL AS nama_puskesmas, NULL AS alamat, NULL AS latitude, NULL AS longitude, NULL AS status WHERE 1=0");
		}

		$this->db->select('kode_pkm, nama_puskesmas, alamat, latitude, longitude, status');
		$this->db->order_by('kode_pkm', 'ASC');

		return $this->db->get('m_puskesmas');
	}

	public function code_exists($kode)
	{
		if (!$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		return $this->db
			->where('kode_pkm', trim((string) $kode))
			->count_all_results('m_puskesmas') > 0;
	}

	public function create($data)
	{
		if (!$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$row = $this->filter_fields($data);
		if (empty($row['kode_pkm']) || empty($row['nama_puskesmas'])) {
			return false;
		}
		if ($this->db->field_exists('created_at', 'm_puskesmas')) {
			$row['created_at'] = date('Y-m-d H:i:s');
		}

		$result = $this->db->insert('m_puskesmas', $row);
		if ($result) {
			$this->log_audit('admin_create_puskesmas', $row['kode_pkm']);
		}

		return $result;
	}

	public function update($kode, $data)
	{
		if (!$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$row = $this->filter_fields($data);
		unset($row['kode_pkm'], $row['created_at']);
		if ($this->db->field_exists('updated_at', 'm_puskesmas')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}
		if (empty($row)) {
			return false;
		}

		$result = $this->db
			->where('kode_pkm', trim((string) $kode))
			->update('m_puskesmas', $row);
		if ($result) {
			$this->log_audit('admin_update_puskesmas', $kode);
		}

		return $result;
	}

	public function set_status($kode, $status)
	{
		if (!$this->db->table_exists('m_puskesmas')) {
			return false;
		}

		$status = in_array($status, array('aktif', 'nonaktif'), TRUE) ? $status : 'nonaktif';
		$row = array('status' => $status);
		if ($this->db->field_exists('updated_at', 'm_puskesmas')) {
			$row['updated_at'] = date('Y-m-d H:i:s');
		}

		$result = $this->db
			->where('kode_pkm', trim((string) $kode))
			->update('m_puskesmas', $row);
		if ($result) {
			$this->log_audit($status === 'aktif' ? 'admin_enable_puskesmas' : 'admin_disable_puskesmas', $kode);
		}

		return $result;
	}

	private function filter_fields($data)
	{
		$row = array();
		foreach (array('kode_pkm', 'nama_puskesmas', 'alamat', 'latitude', 'longitude', 'status') as $field) {
			if ($this->db->field_exists($field, 'm_puskesmas') && array_key_exists($field, $data)) {
				$value = $data[$field];
				if (in_array($field, array('latitude', 'longitude'), TRUE) && $value === '') {
					$value = null;
				}
				$row[$field] = $value;
			}
		}

		return $row;
	}

	private function log_audit($action, $kode)
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
			'entity_id' => $kode,
			'ip_address' => $this->input->ip_address(),
			'user_agent' => substr((string) $this->input->user_agent(), 0, 255),
			'metadata_json' => json_encode(array('kode_pkm' => $kode)),
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
