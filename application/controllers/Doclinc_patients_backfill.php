<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backfill registri pasien ber-NIK, tautan rekam medis, waktu selesai kanonik,
 * dan status pengodean diagnosis.
 *
 * Sifat operasi:
 *   - dry-run secara default;
 *   - hanya mengisi kolom yang masih NULL / masih 'uncoded';
 *   - tidak pernah DELETE dan tidak pernah menimpa nilai yang sudah ada;
 *   - idempoten: menjalankan ulang tidak mengubah apa pun;
 *   - `apply` wajib menyertakan nama database + referensi backup;
 *   - advisory lock agar tidak berjalan ganda.
 *
 * Sumber waktu selesai, berurutan (yang pertama tidak NULL dipakai):
 *   1. clinical_closure_operations.closed_at
 *   2. medicalrecords.clinical_finalized_at
 *   3. requests.visit_completed_at
 * Bila ketiganya NULL, kolom dibiarkan NULL dan permintaan itu masuk daftar
 * pengecualian. Waktu tidak dikarang dari updated_at karena kolom itu berubah
 * setiap kali baris disentuh.
 *
 * Pemakaian (nilai lewat environment; CodeIgniter menolak "=" pada argv):
 *   php index.php doclinc_patients_backfill
 *   DOCLINC_PATIENTS_BACKFILL_APPLY=1 \
 *   DOCLINC_PATIENTS_BACKFILL_CONFIRM_DATABASE=doclinc_local \
 *   DOCLINC_PATIENTS_BACKFILL_BACKUP_REFERENCE="dump.sql.gz:sha256:..." \
 *   php index.php doclinc_patients_backfill
 */
class Doclinc_patients_backfill extends CI_Controller
{
	const LOCK_NAME = 'doclinc_patients_backfill';
	const ICD10_PATTERN_SQL = "^[A-Z][0-9]{2}([.][0-9A-Z]{1,4})?$";
	const ICD10_DATASET = '20b_master_diagnosis_icd10_who_2019.json';
	const ICD10_VERSION = '2019';

	private $apply = false;
	private $database = '';
	private $backup_reference = '';

	public function index()
	{
		if (!$this->input->is_cli_request()) {
			show_404();
			return;
		}

		$report = array('result' => 'PASS', 'errors' => array());
		try {
			$this->load->database();
			$this->read_environment();
			$this->guard_target();
			$this->assert_schema();

			if (!$this->acquire_lock()) {
				throw new RuntimeException('lock_unavailable');
			}

			$before = $this->counts();
			$plan = $this->plan();

			if (!$this->apply) {
				$this->emit_plan($before, $plan);
				$this->release_lock();
				return;
			}

			$written = $this->run($plan);
			$after = $this->counts();
			$this->emit_apply($before, $after, $plan, $written);
			$this->release_lock();
		} catch (Throwable $exception) {
			$this->release_lock();
			$reason = $exception->getMessage();
			if (!preg_match('/^[a-z0-9_]{3,64}$/', $reason)) {
				$reason = 'backfill_execution_failed';
			}
			fwrite(STDERR, "BACKFILL_RESULT=FAIL\nSAFE_ERROR_CODE={$reason}\nWRITE_EXECUTED=false\n");
			exit(1);
		}
	}

	private function read_environment()
	{
		$this->apply = in_array(strtolower(trim((string) getenv('DOCLINC_PATIENTS_BACKFILL_APPLY'))), array('1', 'true', 'yes', 'on'), true);
		$this->database = trim((string) getenv('DOCLINC_PATIENTS_BACKFILL_CONFIRM_DATABASE'));
		$this->backup_reference = trim((string) getenv('DOCLINC_PATIENTS_BACKFILL_BACKUP_REFERENCE'));
	}

	private function guard_target()
	{
		$identity = $this->db->query('SELECT DATABASE() AS database_name')->row_array();
		$actual = isset($identity['database_name']) ? (string) $identity['database_name'] : '';
		if ($actual === '') {
			throw new RuntimeException('database_identity_missing');
		}
		if (!$this->apply) {
			return;
		}
		if ($this->database === '' || !hash_equals($actual, $this->database)) {
			throw new RuntimeException('database_confirmation_mismatch');
		}
		if (strlen($this->backup_reference) < 8 || strlen($this->backup_reference) > 255) {
			throw new RuntimeException('backup_reference_required');
		}
	}

	private function assert_schema()
	{
		foreach (array('users', 'requests', 'medicalrecords', 'medicalrecord_diagnoses', 'patients') as $table) {
			if (!$this->db->table_exists($table)) {
				throw new RuntimeException('schema_unavailable');
			}
		}
		foreach (array('patient_id', 'nik', 'nama') as $column) {
			if (!$this->db->field_exists($column, 'patients')) {
				throw new RuntimeException('schema_unavailable');
			}
		}
		foreach (array('medicalrecords' => 'patient_id', 'requests' => 'completed_at', 'medicalrecord_diagnoses' => 'coding_state') as $table => $column) {
			if (!$this->db->field_exists($column, $table)) {
				throw new RuntimeException('schema_unavailable');
			}
		}
	}

	private function acquire_lock()
	{
		$row = $this->db->query('SELECT GET_LOCK(?, 10) AS locked', array(self::LOCK_NAME))->row_array();
		return !empty($row['locked']);
	}

	private function release_lock()
	{
		$this->db->query('SELECT RELEASE_LOCK(?)', array(self::LOCK_NAME));
	}

	private function scalar($sql)
	{
		$row = $this->db->query($sql)->row_array();
		return $row ? (int) reset($row) : 0;
	}

	private function counts()
	{
		return array(
			'patients' => $this->scalar('SELECT COUNT(*) AS n FROM patients'),
			'rekam_total' => $this->scalar('SELECT COUNT(*) AS n FROM medicalrecords'),
			'rekam_tertaut' => $this->scalar('SELECT COUNT(*) AS n FROM medicalrecords WHERE patient_id IS NOT NULL'),
			'completed' => $this->scalar("SELECT COUNT(*) AS n FROM requests WHERE request_status='Completed'"),
			'completed_bertanggal' => $this->scalar("SELECT COUNT(*) AS n FROM requests WHERE request_status='Completed' AND completed_at IS NOT NULL"),
			'diagnosis_total' => $this->scalar('SELECT COUNT(*) AS n FROM medicalrecord_diagnoses'),
			'diagnosis_coded' => $this->scalar("SELECT COUNT(*) AS n FROM medicalrecord_diagnoses WHERE coding_state='coded'"),
		);
	}

	/**
	 * Subquery waktu selesai. Tabel closure dijaga karena tidak semua instalasi
	 * memilikinya; bila tidak ada, sumber itu dilewati, bukan dianggap nol.
	 */
	private function completed_at_expression($alias = 'r')
	{
		$parts = array();
		if ($this->db->table_exists('clinical_closure_operations')) {
			$parts[] = '(SELECT MAX(c.closed_at) FROM clinical_closure_operations c WHERE c.request_id = ' . $alias . '.request_id)';
		}
		$parts[] = '(SELECT MAX(mr.clinical_finalized_at) FROM medicalrecords mr WHERE mr.request_id = ' . $alias . '.request_id AND mr.clinical_finalized_at IS NOT NULL)';
		$parts[] = $alias . '.visit_completed_at';
		return 'COALESCE(' . implode(', ', $parts) . ')';
	}

	private function plan()
	{
		$term = $this->completed_at_expression('r');
		return array(
			'patients_baru' => $this->scalar("SELECT COUNT(*) AS n FROM users u WHERE u.role='warga' AND u.nik IS NOT NULL AND TRIM(u.nik) REGEXP '^[0-9]{16}$' AND NOT EXISTS (SELECT 1 FROM patients p WHERE p.nik = TRIM(u.nik))"),
			'warga_ber_nik' => $this->scalar("SELECT COUNT(*) AS n FROM users u WHERE u.role='warga' AND u.nik IS NOT NULL AND TRIM(u.nik) REGEXP '^[0-9]{16}$'"),
			'rekam_belum_tertaut' => $this->scalar('SELECT COUNT(*) AS n FROM medicalrecords WHERE patient_id IS NULL'),
			'rekam_bisa_ditautkan' => $this->scalar("SELECT COUNT(*) AS n FROM medicalrecords mr JOIN requests r ON r.request_id = mr.request_id JOIN users u ON u.userId = r.user_id WHERE mr.patient_id IS NULL AND u.nik IS NOT NULL AND TRIM(u.nik) REGEXP '^[0-9]{16}$'"),
			'rekam_tak_bisa_ditautkan' => $this->scalar("SELECT COUNT(*) AS n FROM medicalrecords mr LEFT JOIN requests r ON r.request_id = mr.request_id LEFT JOIN users u ON u.userId = r.user_id WHERE mr.patient_id IS NULL AND (u.nik IS NULL OR TRIM(u.nik) NOT REGEXP '^[0-9]{16}$')"),
			'completed_belum_bertanggal' => $this->scalar("SELECT COUNT(*) AS n FROM requests r WHERE r.request_status='Completed' AND r.completed_at IS NULL"),
			'completed_bisa_ditanggali' => $this->scalar("SELECT COUNT(*) AS n FROM requests r WHERE r.request_status='Completed' AND r.completed_at IS NULL AND {$term} IS NOT NULL"),
			'completed_tanpa_sumber_waktu' => $this->scalar("SELECT COUNT(*) AS n FROM requests r WHERE r.request_status='Completed' AND r.completed_at IS NULL AND {$term} IS NULL"),
			'diagnosis_bisa_dikode' => $this->scalar("SELECT COUNT(*) AS n FROM medicalrecord_diagnoses WHERE coding_state='uncoded' AND diagnosis_code IS NOT NULL AND TRIM(diagnosis_code) REGEXP '" . self::ICD10_PATTERN_SQL . "' AND reference_source='" . self::ICD10_DATASET . "' AND reference_version='" . self::ICD10_VERSION . "'"),
			'diagnosis_tetap_uncoded' => $this->scalar("SELECT COUNT(*) AS n FROM medicalrecord_diagnoses WHERE coding_state='uncoded'"),
		);
	}

	private function run(array $plan)
	{
		$written = array('patients' => 0, 'rekam' => 0, 'completed' => 0, 'diagnosis' => 0);

		$this->db->trans_begin();

		$this->db->query(
			"INSERT INTO patients (nik, nama, tgl_lahir, gender, no_hp, alamat, nomor_kk, nomor_bpjs_kis, primary_user_id)
			 SELECT TRIM(u.nik), u.nama, u.tgl, u.gender, u.no_hp, u.alamat,
			        NULLIF(TRIM(u.nomor_kk), ''), NULLIF(TRIM(u.nomor_bpjs_kis), ''), u.userId
			 FROM users u
			 WHERE u.role='warga' AND u.nik IS NOT NULL AND TRIM(u.nik) REGEXP '^[0-9]{16}$'
			 ON DUPLICATE KEY UPDATE primary_user_id = COALESCE(patients.primary_user_id, VALUES(primary_user_id))"
		);
		$written['patients'] = (int) $this->db->affected_rows();

		$this->db->query(
			"UPDATE medicalrecords mr
			 JOIN requests r ON r.request_id = mr.request_id
			 JOIN users u ON u.userId = r.user_id
			 JOIN patients p ON p.nik = TRIM(u.nik)
			 SET mr.patient_id = p.patient_id
			 WHERE mr.patient_id IS NULL"
		);
		$written['rekam'] = (int) $this->db->affected_rows();

		$term = $this->completed_at_expression('r');
		$this->db->query(
			"UPDATE requests r SET r.completed_at = {$term}
			 WHERE r.request_status='Completed' AND r.completed_at IS NULL AND {$term} IS NOT NULL"
		);
		$written['completed'] = (int) $this->db->affected_rows();

		$this->db->query(
			"UPDATE medicalrecord_diagnoses SET coding_state='coded'
			 WHERE coding_state='uncoded'
			   AND diagnosis_code IS NOT NULL
			   AND TRIM(diagnosis_code) REGEXP '" . self::ICD10_PATTERN_SQL . "'
			   AND reference_source='" . self::ICD10_DATASET . "'
			   AND reference_version='" . self::ICD10_VERSION . "'"
		);
		$written['diagnosis'] = (int) $this->db->affected_rows();

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			throw new RuntimeException('transaction_failed');
		}
		$this->db->trans_commit();
		return $written;
	}


	private function emit_plan(array $before, array $plan)
	{
		echo "BACKFILL_RESULT=PLAN\nMODE=DRY_RUN\n";
		foreach ($before as $key => $value) {
			echo strtoupper($key) . "={$value}\n";
		}
		foreach ($plan as $key => $value) {
			echo 'PLANNED_' . strtoupper($key) . "={$value}\n";
		}
		echo "WRITE_EXECUTED=false\n";
		echo "NOTE=Jalankan dengan DOCLINC_PATIENTS_BACKFILL_APPLY=1, CONFIRM_DATABASE, dan BACKUP_REFERENCE untuk menulis.\n";
	}

	private function emit_apply(array $before, array $after, array $plan, array $written)
	{
		echo "BACKFILL_RESULT=PASS\nMODE=APPLY\n";
		foreach ($plan as $key => $value) {
			echo 'PLANNED_' . strtoupper($key) . "={$value}\n";
		}
		foreach ($written as $key => $value) {
			echo 'WROTE_' . strtoupper($key) . "={$value}\n";
		}
		foreach ($after as $key => $value) {
			$delta = $value - (isset($before[$key]) ? $before[$key] : 0);
			echo strtoupper($key) . "={$value} DELTA={$delta}\n";
		}
		echo "WRITE_EXECUTED=true\n";
	}
}

