<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Kapabilitas admin: siapa boleh melakukan apa di panel ini.
 *
 * Halaman ini mengelola tiga kapabilitas yang sudah didefinisikan aplikasi utama
 * (`super_admin`, `clinical_audit`, `clinical_taxonomy_manage`) lewat
 * `Admin_capability_service`, bukan dengan menulis tabel langsung. Aturannya
 * ditegakkan di lapisan itu: hanya super admin aktif yang boleh mengubah, super
 * admin terakhir tidak boleh dicabut, dan perubahan tanpa baris audit dibatalkan.
 *
 * Fitur ini dikendalikan flag `DOCLINC_ADMIN_CAPABILITIES_ENABLED`. Saat flag
 * mati, halaman tetap terbuka dan menjelaskan keadaannya, tetapi tidak ada
 * perubahan yang dijalankan - bukan hanya tombolnya yang disembunyikan.
 */
class Kapabilitas extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Kapabilitas_m');
		$this->load->library('Admin_capability_gateway', array($this));
		$this->load->helper('form');
		if ($this->session->userdata('is_login') == FALSE) {
			redirect('/', 'refresh');
		}
		if ($this->session->userdata('level') !== 'admin') {
			show_error('Akses admin diperlukan.', 403, 'Akses ditolak');
		}
	}

	public function index()
	{
		$this->session->set_flashdata('title', 'Kapabilitas admin');
		$schema_ready = $this->Kapabilitas_m->schemaReady();
		$gateway = $this->admin_capability_gateway;

		$this->load->view('commons/header');
		$this->load->view('kapabilitas_v', array(
			'schema_ready' => $schema_ready,
			'feature_enabled' => $gateway->featureEnabled(),
			'feature_reason' => $gateway->reason(),
			'actor' => $gateway->actor(),
			'actor_is_active_admin' => $gateway->activeAdmin(),
			'actor_is_super_admin' => $gateway->hasCapability('super_admin'),
			'super_admin_count' => $gateway->superAdminCount(),
			'allowed_codes' => $gateway->allowedCodes(),
			'admins' => $schema_ready ? $this->Kapabilitas_m->getAdminUsers() : array(),
			'recent_audit' => $schema_ready ? $this->Kapabilitas_m->getRecentCapabilityAudit(10) : array(),
		));
		$this->load->view('commons/footer');
	}

	public function grant()
	{
		$this->mutate('grant');
	}

	public function revoke()
	{
		$this->mutate('revoke');
	}

	/**
	 * Satu jalur untuk kedua operasi. Hasilnya selalu dilaporkan apa adanya:
	 * `allowed`, `changed`, dan kode galat aman dari lapisan kapabilitas.
	 */
	private function mutate($operation)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->session->set_flashdata('error', 'Metode tidak diizinkan.');
			redirect('kapabilitas', 'refresh');
			return;
		}
		$target = (int) $this->input->post('target_user_id', TRUE);
		$code = trim((string) $this->input->post('capability_code', TRUE));
		if ($target < 1 || $code === '') {
			$this->session->set_flashdata('error', 'Permintaan kapabilitas tidak lengkap.');
			redirect('kapabilitas', 'refresh');
			return;
		}

		$gateway = $this->admin_capability_gateway;
		$result = $operation === 'grant' ? $gateway->grant($target, $code) : $gateway->revoke($target, $code);

		if (!empty($result['allowed'])) {
			if (!empty($result['changed'])) {
				$this->session->set_flashdata('success', $operation === 'grant' ? 'Kapabilitas diberikan.' : 'Kapabilitas dicabut.');
			} else {
				$this->session->set_flashdata('info', 'Tidak ada perubahan: kapabilitasnya sudah sesuai.');
			}
			redirect('kapabilitas', 'refresh');
			return;
		}
		$this->session->set_flashdata('error', Admin_capability_gateway::errorMessage($result['error'] ?? ''));
		redirect('kapabilitas', 'refresh');
	}
}
