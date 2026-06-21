<?php
class Kelola_dokter_nakes_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}
	public function get_data_dokter_nakes()
	{
		if (!$this->db->table_exists('users')) {
			return $this->db->query("SELECT NULL AS userId, NULL AS nama, NULL AS remark, NULL AS no_hp, NULL AS foto WHERE 1=0");
		}

		$this->db->where('role', 'dokter');
		return $this->db->get('users');
	}
	public function aktifkan_user($id_user, $user, $remark_aktif)
	{
		return $this->update_status($id_user, 'aktif', $user, $remark_aktif);
	}
	public function nonaktifkan_user($id_user, $user, $remark_nonaktif)
	{
		return $this->update_status($id_user, 'nonaktif', $user, $remark_nonaktif);
	}

	public function delete_dokter_nakes($id_user)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		return $this->db->delete('users');
	}

	public function update_dokter_nakes($id_user, $data)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$allowed = array();
		foreach (array('nama', 'remark', 'no_hp', 'updated_by', 'updated_at') as $field) {
			if ($this->db->field_exists($field, 'users') && array_key_exists($field, $data)) {
				$allowed[$field] = $data[$field];
			}
		}

		if (empty($allowed)) {
			return false;
		}

		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		return $this->db->update('users', $allowed);
	}

	private function update_status($id_user, $status, $user, $remark)
	{
		if (!$this->db->table_exists('users')) {
			return false;
		}

		$data = array('status' => $status);
		foreach (array('remark' => $remark, 'updated_by' => $user, 'updated_at' => date('Y-m-d H:i:s')) as $field => $value) {
			if ($this->db->field_exists($field, 'users')) {
				$data[$field] = $value;
			}
		}

		$this->db->where('userId', $id_user);
		$this->db->where('role', 'dokter');
		return $this->db->update('users', $data);
	}
}
