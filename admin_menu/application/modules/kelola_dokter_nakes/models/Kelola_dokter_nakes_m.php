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
		return $this->db->query("SELECT * FROM users WHERE role='dokter'");
	}
	public function aktifkan_user($id_user, $user, $remark_aktif)
	{
		$date = date('Y-m-d H:i:s');
		return $this->db->query("UPDATE users SET status='aktif', remark='$remark_aktif', updated_by='$user', updated_at='$date' WHERE userId='$id_user'");
	}
	public function nonaktifkan_user($id_user, $user, $remark_nonaktif)
	{
		$date = date('Y-m-d H:i:s');
		return $this->db->query("UPDATE users SET status='nonaktif', remark='$remark_nonaktif', updated_by='$user', updated_at='$date' WHERE userId='$id_user'");
	}

	public function delete_dokter_nakes($id_user)
	{
		return $this->db->query("DELETE FROM users WHERE userId='$id_user'");
	}
}
