<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Kelola_dokter_nakes extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Kelola_dokter_nakes_m');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
	}
	public function index()
	{
		$this->session->set_flashdata('title', 'Kelola Dokter/Nakes');
		$this->session->set_flashdata('active_tab_kelola_dokter_nakes', 'active');
		$year = date('Y');
		$month = date('m');
		$x['data_dokter_nakes'] = $this->Kelola_dokter_nakes_m->get_data_dokter_nakes();
		$this->load->view('commons/header');
		$this->load->view('kelola_dokter_nakes_v', $x);
		$this->load->view('commons/footer');
	}
	public function aktifkan_user()
	{
		$id_user = $this->input->post('id_user');
		$remark_aktif = $this->input->post('remark_aktif');
		$user = $this->session->userdata('username');
		$this->Kelola_dokter_nakes_m->aktifkan_user($id_user, $user, $remark_aktif);
		$this->session->set_flashdata('success', 'Anda berhasil mengaktifkan user.');
		redirect('kelola_dokter_nakes', 'refresh');
	}
	public function nonaktifkan_user()
	{
		$id_user = $this->input->post('id_user');
		$remark_nonaktif = $this->input->post('remark_nonaktif');
		$user = $this->session->userdata('username');
		$this->Kelola_dokter_nakes_m->nonaktifkan_user($id_user, $user, $remark_nonaktif);
		$this->session->set_flashdata('success', 'Anda berhasil menonaktifkan user.');
		redirect('kelola_dokter_nakes', 'refresh');
	}

	public function update($id_user)
	{
		$data = array(
			'nama' => $this->input->post('nama'),
			'remark' => $this->input->post('remark'),
			'no_hp' => $this->input->post('no_hp'),
			'updated_by' => $this->session->userdata('username'),
			'updated_at' => date('Y-m-d H:i:s')
		);

		$this->Kelola_dokter_nakes_m->update_dokter_nakes($id_user, $data);
		$this->session->set_flashdata('success', 'Data berhasil diperbarui.');
		redirect('kelola_dokter_nakes', 'refresh');
	}

	public function delete_dokter_nakes()
	{
		$id_dokter_nakes = $this->input->get('id_dokter_nakes');
		$this->Kelola_dokter_nakes_m->delete_dokter_nakes($id_dokter_nakes);
		$this->session->set_flashdata('success', 'Data berhasil dihapus.');
		redirect('kelola_dokter_nakes', 'refresh');
	}
}
