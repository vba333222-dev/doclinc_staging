<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backfill baris penugasan care team untuk data lama.
 *
 * Masalah yang diselesaikan: sebagian permintaan menyimpan
 * `requests.responsible_doctor_user_id` tetapi tidak memiliki baris
 * `request_responsible_doctor_assignments` yang aktif. UI menampilkan dokter
 * tersebut, tetapi policy klinis membaca baris penugasan, sehingga dokter
 * tidak dapat menetapkan disposisi (NOT_RESPONSIBLE_DOCTOR).
 *
 * Sifat operasi:
 *   - hanya INSERT, tidak pernah UPDATE/DELETE;
 *   - idempoten: permintaan yang sudah punya penugasan aktif dilewati;
 *   - dry-run secara default;
 *   - `apply` wajib menyertakan nama database + referensi backup;
 *   - advisory lock agar tidak berjalan ganda.
 *
 * Pemakaian (nilai lewat environment; CodeIgniter menolak "=" pada argv):
 *   php index.php doclinc_care_team_assignment_backfill
 *   DOCLINC_BACKFILL_APPLY=1 \
 *   DOCLINC_BACKFILL_CONFIRM_DATABASE=doclinc_local \
 *   DOCLINC_BACKFILL_BACKUP_REFERENCE="dump.sql:sha256:..." \
 *   php index.php doclinc_care_team_assignment_backfill
 */
class Doclinc_care_team_assignment_backfill extends CI_Controller
{
	const LOCK_NAME = 'doclinc_care_team_assignment_backfill';

	private $apply = false;
	private $database = '';
	private $inserted = array('responsible' => 0, 'performer' => 0);
	private $planned = array('responsible' => 0, 'performer' => 0);
	private $skipped = array();

	public function index()
	{
		if (!$this->input->is_cli_request()) {
			show_404();
			return;
		}

		$this->db->db_debug = false;
		$this->database = (string) $this->db->database;
		$options = $this->cli_options();
		$this->apply = !empty($options['apply']);
		$lock = false;

		try {
			$this->assert_preconditions($options);
			$lock = $this->acquire_lock();
			$this->load->helper('request_event');
			$this->backfill_responsible();
			$this->backfill_performer();
			$this->print_result();
		} catch (Throwable $exception) {
			$this->print_failure($exception->getMessage());
		} finally {
			if ($lock) {
				$this->db->query('SELECT RELEASE_LOCK(?)', array(self::LOCK_NAME));
			}
		}
	}

	private function cli_options()
	{
		$options = array();
		$environment = array(
			'DOCLINC_BACKFILL_APPLY' => 'apply',
			'DOCLINC_BACKFILL_CONFIRM_DATABASE' => 'confirm-database',
			'DOCLINC_BACKFILL_BACKUP_REFERENCE' => 'backup-reference',
			'DOCLINC_BACKFILL_HELP' => 'help',
		);
		foreach ($environment as $name => $key) {
			$value = getenv($name);
			if ($value === false || trim((string) $value) === '') {
				continue;
			}
			$options[$key] = trim((string) $value);
		}
		if (array_key_exists('apply', $options)) {
			$options['apply'] = filter_var($options['apply'], FILTER_VALIDATE_BOOLEAN);
		}
		return $options;
	}

	private function assert_preconditions(array $options)
	{
		if ($this->database === '') {
			throw new RuntimeException('database_name_unavailable');
		}
		foreach (array('requests', 'users', 'puskesmas_staff', 'request_responsible_doctor_assignments', 'request_visit_performer_assignments') as $table) {
			if (!$this->table_exists($table)) {
				throw new RuntimeException('base_schema_mismatch_' . $table);
			}
		}
		if (!$this->apply) {
			return;
		}
		$confirmation = isset($options['confirm-database']) ? (string) $options['confirm-database'] : '';
		if ($confirmation === '' || !hash_equals($this->database, $confirmation)) {
			throw new RuntimeException('database_confirmation_mismatch');
		}
		$backup = isset($options['backup-reference']) ? trim((string) $options['backup-reference']) : '';
		if (strlen($backup) < 8) {
			throw new RuntimeException('backup_reference_missing');
		}
	}

	private function acquire_lock()
	{
		$row = $this->db->query('SELECT GET_LOCK(?, 10) AS acquired', array(self::LOCK_NAME))->row();
		if (!$row || (int) $row->acquired !== 1) {
			throw new RuntimeException('migration_lock_unavailable');
		}
		return true;
	}

	private function backfill_responsible()
	{
		$rows = $this->db->query(
			'SELECT r.request_id, r.assigned_puskesmas_code, r.responsible_doctor_user_id FROM ' . $this->db->dbprefix('requests') . ' r'
			. " WHERE r.request_status = 'Accepted' AND r.responsible_doctor_user_id > 0"
			. ' AND NOT EXISTS (SELECT 1 FROM ' . $this->db->dbprefix('request_responsible_doctor_assignments') . " a WHERE a.request_id = r.request_id AND a.status = 'aktif')"
			. ' ORDER BY r.request_id ASC'
		)->result();

		foreach ($rows as $row) {
			$this->backfill_one('responsible', (int) $row->request_id, (int) $row->responsible_doctor_user_id, (string) $row->assigned_puskesmas_code);
		}
	}

	private function backfill_performer()
	{
		$rows = $this->db->query(
			'SELECT r.request_id, r.assigned_puskesmas_code, r.visit_performer_user_id FROM ' . $this->db->dbprefix('requests') . ' r'
			. " WHERE r.request_status = 'Accepted' AND r.visit_performer_user_id > 0 AND r.consultation_mode = 'visit'"
			. ' AND NOT EXISTS (SELECT 1 FROM ' . $this->db->dbprefix('request_visit_performer_assignments') . " a WHERE a.request_id = r.request_id AND a.status = 'aktif')"
			. ' ORDER BY r.request_id ASC'
		)->result();

		foreach ($rows as $row) {
			$this->backfill_one('performer', (int) $row->request_id, (int) $row->visit_performer_user_id, (string) $row->assigned_puskesmas_code);
		}
	}

	private function backfill_one($kind, $request_id, $user_id, $facility_code)
	{
		if ($request_id < 1 || $user_id < 1) {
			return;
		}
		$staff = $this->staff_for_user($user_id, $facility_code);
		if (!$staff) {
			$this->skipped[] = $kind . ':' . $request_id . ':staff_missing';
			return;
		}

		$this->planned[$kind]++;
		if (!$this->apply) {
			echo 'PLAN=' . $kind . ':' . $request_id . ':' . $user_id . "\n";
			return;
		}

		$now = date('Y-m-d H:i:s');
		$ok = $kind === 'responsible'
			? $this->db->insert('request_responsible_doctor_assignments', array(
				'request_id' => $request_id,
				'staff_id' => (int) $staff->staff_id,
				'user_id' => $user_id,
				'assigned_by_user_id' => $user_id,
				'status' => 'aktif',
				'assigned_at' => $now,
				'created_at' => $now,
				'updated_at' => $now,
			))
			: $this->db->insert('request_visit_performer_assignments', array(
				'request_id' => $request_id,
				'staff_id' => (int) $staff->staff_id,
				'user_id' => $user_id,
				'assigned_by_user_id' => $user_id,
				'status' => 'aktif',
				'assigned_at' => $now,
				'created_at' => $now,
				'updated_at' => $now,
			));
		if (!$ok) {
			$this->skipped[] = $kind . ':' . $request_id . ':insert_failed';
			return;
		}

		$this->inserted[$kind]++;
		echo 'FIXED=' . $kind . ':' . $request_id . ':' . $user_id . "\n";
		$this->log_backfill($request_id, $kind, $user_id);
	}


	private function staff_for_user($user_id, $facility_code)
	{
		return $this->db->select('staff_id')
			->where('user_id', (int) $user_id)
			->where('kode_pkm', trim((string) $facility_code))
			->where('status', 'aktif')
			->order_by('staff_id', 'ASC')
			->limit(1)
			->get('puskesmas_staff')
			->row();
	}

	private function log_backfill($request_id, $kind, $user_id)
	{
		if (!function_exists('doclinc_append_request_event')) {
			return;
		}
		doclinc_append_request_event((int) $request_id, 'care_team.assignment_backfilled', array(
			'domain_event_key' => 'care-team-backfill:' . $kind . ':' . (int) $request_id,
			'metadata' => array('kind' => $kind, 'user_id' => (int) $user_id, 'source' => 'backfill'),
		), $this);
	}

	private function print_result()
	{
		echo "BACKFILL_RESULT=PASS\n";
		echo 'MODE=' . ($this->apply ? 'apply' : 'dry-run') . "\n";
		echo 'DATABASE=' . $this->database . "\n";
		echo 'RESPONSIBLE_PLANNED=' . $this->planned['responsible'] . "\n";
		echo 'RESPONSIBLE_INSERTED=' . $this->inserted['responsible'] . "\n";
		echo 'PERFORMER_PLANNED=' . $this->planned['performer'] . "\n";
		echo 'PERFORMER_INSERTED=' . $this->inserted['performer'] . "\n";
		echo 'SKIPPED=' . count($this->skipped) . "\n";
		foreach ($this->skipped as $item) {
			echo 'SKIP=' . $item . "\n";
		}
	}

	private function print_failure($code)
	{
		$safe = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $code);
		fwrite(STDERR, "BACKFILL_RESULT=FAIL\n");
		fwrite(STDERR, 'SAFE_ERROR_CODE=' . $safe . "\n");
		exit(1);
	}

	private function table_exists($table)
	{
		$row = $this->db->query(
			'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
			array((string) $table)
		)->row();
		return $row && (int) $row->total > 0;
	}
}

