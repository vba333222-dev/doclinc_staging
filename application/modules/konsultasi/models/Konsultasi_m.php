<?php
class Konsultasi_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db  = $this->load->database('default', TRUE);
	}

	private function empty_query()
	{
		return $this->db->query('SELECT 1 WHERE 1 = 0');
	}

	public function get_master_gejala_keluhan_options()
	{
		if (!$this->db->table_exists('keluhan')) {
			return array();
		}

		$select = array();
		$select[] = $this->db->field_exists('id_keluhan', 'keluhan') ? 'id_keluhan' : 'NULL AS id_keluhan';
		$select[] = $this->db->field_exists('nama_keluhan', 'keluhan') ? 'nama_keluhan' : 'NULL AS nama_keluhan';
		$select[] = $this->db->field_exists('status', 'keluhan') ? 'status' : 'NULL AS status';

		$this->db->select(implode(', ', $select), FALSE);
		$this->db->from('keluhan');
		if ($this->db->field_exists('status', 'keluhan')) {
			$this->db->where('status', 'Aktif');
		}
		if ($this->db->field_exists('nama_keluhan', 'keluhan')) {
			$this->db->where("TRIM(nama_keluhan) <> ''", null, false);
			$this->db->order_by('nama_keluhan', 'ASC');
		}

		return $this->db->get()->result();
	}

	public function save_konsultasi(
		$id_user,
		$dokter_id,
		$riwayat,
		$keluhan,
		$alamat,
		$lattitude,
		$longitude,
		$tanggal,
		$foto = null,
		$video = null,
		$routing = array()
	) {
		if (empty($id_user)) {
			return false;
		}
		if (empty($dokter_id)) {
			return false;
		}
		$assigned_puskesmas_code = isset($routing['assigned_puskesmas_code']) ? trim((string) $routing['assigned_puskesmas_code']) : '';
		if (
			!$this->db->field_exists('assigned_puskesmas_code', 'requests')
			|| $assigned_puskesmas_code === ''
			|| strtoupper($assigned_puskesmas_code) === 'DEFAULT'
		) {
			return false;
		}
		$routing['assigned_puskesmas_code'] = $assigned_puskesmas_code;
		if (isset($routing['assigned_puskesmas_name'])) {
			$routing['assigned_puskesmas_name'] = trim((string) $routing['assigned_puskesmas_name']);
		}

		$now = date('Y-m-d H:i:s');
		$has_patient_location = $this->is_valid_latitude($lattitude) && $this->is_valid_longitude($longitude);
		$legacy_latitude = $has_patient_location ? (string) (float) $lattitude : '';
		$legacy_longitude = $has_patient_location ? (string) (float) $longitude : '';
		$patient_latitude = $has_patient_location ? (float) $lattitude : null;
		$patient_longitude = $has_patient_location ? (float) $longitude : null;
		if (array_key_exists('patient_latitude', $routing) && array_key_exists('patient_longitude', $routing)) {
			$routing_has_patient_location = $this->is_valid_latitude($routing['patient_latitude']) && $this->is_valid_longitude($routing['patient_longitude']);
			$patient_latitude = $routing_has_patient_location ? (float) $routing['patient_latitude'] : null;
			$patient_longitude = $routing_has_patient_location ? (float) $routing['patient_longitude'] : null;
		}

		$this->db->trans_start(); // Mulai transaksi

		$data = [
			'user_id' => $id_user,
			'dokter_id' => $dokter_id,
			'request_description' => $keluhan,
			'request_status' => 'Pending',
			'location' => $alamat,
			'lattitude' => $legacy_latitude,
			'longitude' => $legacy_longitude,
		];
		if ($this->db->field_exists('date', 'requests')) {
			$data['date'] = date('Y-m-d', strtotime($tanggal));
		}
		if ($this->db->field_exists('photos', 'requests')) {
			$data['photos'] = $foto;
		}
		if ($this->db->field_exists('video', 'requests')) {
			$data['video'] = $video;
		}
		if ($this->db->field_exists('created_at', 'requests')) {
			$data['created_at'] = $now;
		}
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = $now;
		}
		$routing['patient_latitude'] = $patient_latitude;
		$routing['patient_longitude'] = $patient_longitude;
		foreach (array('assigned_puskesmas_code', 'assigned_puskesmas_name', 'patient_latitude', 'patient_longitude') as $field) {
			if ($this->db->field_exists($field, 'requests') && array_key_exists($field, $routing)) {
				$data[$field] = $routing[$field];
			}
		}
		$this->apply_queue_fields($data, $tanggal, $now);

		$this->db->insert('requests', $data);
		$request_id = $this->db->insert_id();

		// Cek apakah sudah ada riwayat untuk user tersebut
		if ($this->db->table_exists('tbl_riwayat')) {
			$cek = $this->db
				->where('idUser', $id_user)
				->get('tbl_riwayat')
				->row();

			if ($cek) {
				$this->db
					->where('idUser', $id_user)
					->update('tbl_riwayat', array(
						'riwayat' => $riwayat,
						'updated_at' => date('Y-m-d H:i:s'),
					));
			} else {
				$this->db->insert('tbl_riwayat', array(
					'idUser' => $id_user,
					'riwayat' => $riwayat,
					'created_at' => date('Y-m-d H:i:s'),
					'updated_at' => date('Y-m-d H:i:s'),
				));
			}
		}

		$this->db->trans_complete(); // Selesaikan transaksi

		// Kembalikan status transaksi
		return $this->db->trans_status() ? $request_id : false; // true jika berhasil, false jika gagal
	}

	private function apply_queue_fields(&$data, $tanggal, $now)
	{
		foreach (array('queue_date', 'queue_number', 'queue_code') as $field) {
			if (!$this->db->field_exists($field, 'requests')) {
				return;
			}
		}

		$queue_date = $this->resolve_queue_date($tanggal, $now);
		$bucket = $this->queue_bucket(isset($data['assigned_puskesmas_code']) ? $data['assigned_puskesmas_code'] : '');
		$queue_number = $this->next_queue_number($bucket, $queue_date);

		$data['queue_date'] = $queue_date;
		$data['queue_number'] = $queue_number;
		$data['queue_code'] = $bucket . '-' . date('Ymd', strtotime($queue_date)) . '-' . str_pad((string) $queue_number, 3, '0', STR_PAD_LEFT);
	}

	private function resolve_queue_date($tanggal, $now)
	{
		if ($this->db->field_exists('created_at', 'requests')) {
			return date('Y-m-d', strtotime($now));
		}
		if ($this->db->field_exists('date', 'requests') && !empty($tanggal)) {
			return date('Y-m-d', strtotime($tanggal));
		}

		return date('Y-m-d');
	}

	private function queue_bucket($assigned_puskesmas_code)
	{
		$bucket = trim((string) $assigned_puskesmas_code);
		return $bucket !== '' ? $bucket : 'LEGACY';
	}

	private function next_queue_number($bucket, $queue_date)
	{
		$this->db->select('MAX(queue_number) AS max_queue_number', FALSE);
		$this->db->where('queue_date', $queue_date);
		if ($bucket === 'LEGACY') {
			$this->db->group_start();
			$this->db->where('assigned_puskesmas_code IS NULL', null, false);
			$this->db->or_where("TRIM(assigned_puskesmas_code) = ''", null, false);
			$this->db->group_end();
		} else {
			$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($bucket), null, false);
		}

		$row = $this->db->get('requests')->row();
		return ((int) ($row ? $row->max_queue_number : 0)) + 1;
	}

	public function is_active_doctor($dokter_id)
	{
		if (empty($dokter_id)) {
			return false;
		}

		$this->db->where('userId', $dokter_id);
		$this->db->where('role', 'dokter');
		if ($this->db->field_exists('status', 'users')) {
			$this->db->where('status', 'aktif');
		}

		return $this->db->count_all_results('users') > 0;
	}

	public function save_konsultasis($id_user, $dokter_id, $riwayat, $keluhan, $alamat, $lattitude, $longitude, $tanggal, $foto = null, $video = null)
	{

		$this->db->trans_start();
		$now = date('Y-m-d H:i:s');

		$data = [
			'user_id'             => $id_user,
			'dokter_id'           => $dokter_id,
			'request_description' => $keluhan,
			'request_status'      => 'Pending',
			'location'            => $alamat,
			'lattitude'           => $lattitude,
			'longitude'           => $longitude,
		];
		if ($this->db->field_exists('date', 'requests')) {
			$data['date'] = date('Y-m-d', strtotime($tanggal));
		}
		if ($this->db->field_exists('photos', 'requests')) {
			$data['photos'] = $foto;
		}
		if ($this->db->field_exists('video', 'requests')) {
			$data['video'] = $video;
		}
		if ($this->db->field_exists('created_at', 'requests')) {
			$data['created_at'] = $now;
		}
		if ($this->db->field_exists('updated_at', 'requests')) {
			$data['updated_at'] = $now;
		}
		$this->apply_queue_fields($data, $tanggal, $now);

		$this->db->insert('requests', $data);
		// $this->db->insert_id(); // atau return true/false kalau kamu tidak perlu ID-nya

		$data_riwayat = [
			'idUser' => $id_user,
			'riwayat' => $riwayat,
		];

		if ($this->db->table_exists('tbl_riwayat')) {
			$this->db->insert('tbl_riwayat', $data_riwayat);
		}

		$this->db->trans_complete();
		return $this->db->trans_status();
	}


	public function getAllDataLocations($dokter)
	{
		if (!$this->db->table_exists('locations')) {
			return [];
		}

		$query = $this->db->query("SELECT
			latitude, longitude, location
		FROM
			locations
		WHERE
			id_user=" . $this->db->escape($dokter) . "
		AND
			date(locations.create_date)=date(now())");
		return $query->result_array();
	}

	public function getLocation($dokter)
	{
		if (!$this->db->table_exists('locations')) {
			return ['location' => 'Lokasi belum tersedia'];
		}

		$query = $this->db->query("SELECT
			location
		FROM
			locations
		WHERE
			id_user=" . $this->db->escape($dokter) . "
		AND
			date(locations.create_date)=date(now())
		ORDER BY
			create_date
		DESC LIMIT 1");
		$result = $query->row_array();
		return $result ?: ['location' => 'Lokasi belum tersedia'];
	}

	public function getDataDoctor($dokter)
	{
		$this->db->select('users.*');
		foreach (['foto', 'no_hp', 'alamat', 'tgl'] as $field) {
			if (!$this->db->field_exists($field, 'users')) {
				$this->db->select("NULL AS {$field}", FALSE);
			}
		}

		return $this->db->where('userId', $dokter)->get('users');
	}

	public function getFotoDokter($dokter)
	{
		return $this->getDataDoctor($dokter);
	}

	public function getDataTokenDoctor($dokter)
	{
		if (!$this->db->table_exists('fcm_tokens') || !$this->db->field_exists('no_hp', 'users')) {
			return $this->empty_query();
		}

		// buatkan query join antara tabel users dan tabel fcm_tokens diaman yang menjadi referensinya adalah no_hp users
		// dan phone di tabel fcm_tokens
		return $this->db
			->select('fcm_tokens.phone, fcm_tokens.token, users.nama')
			->from('fcm_tokens')
			->join('users', 'fcm_tokens.phone = users.no_hp')
			->where('users.userId', $dokter)
			->get();
	}

	public function getDataPenunjangById($id_user)
	{
		if (!$this->db->table_exists('tbl_riwayat')) {
			return '';
		}

		// Load library encryption
		$CI = &get_instance();
		$CI->load->library('encryption');

		$query = $this->db->where('idUser', $id_user)->get('tbl_riwayat')->row();
		// $query = $this->db->query("SELECT * FROM tbl_riwayat WHERE idUser ='$id_user'");
		// $hasil = $query->row();

		if (!$query || empty($query->riwayat)) {
			return '';
		}

		$hasil = $CI->encryption->decrypt(base64_decode($query->riwayat));

		return $hasil;
	}

	private function is_valid_latitude($value)
	{
		return is_numeric($value) && (float) $value >= -90 && (float) $value <= 90;
	}

	private function is_valid_longitude($value)
	{
		return is_numeric($value) && (float) $value >= -180 && (float) $value <= 180;
	}
}
