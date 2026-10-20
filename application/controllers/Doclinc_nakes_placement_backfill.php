<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Backfill penempatan awal Nakes dari `puskesmas_staff.kode_pkm`.
 *
 * Alur klinis memeriksa `nakes_facility_placements` aktif sebelum Nakes boleh
 * menyimpan/mengirim hasil kunjungan. Data lama hanya menyimpan fasilitas di
 * `puskesmas_staff.kode_pkm`, sehingga tanpa backfill semua Nakes lama akan
 * ditolak `ACCESS_DENIED` pada form hasil.
 *
 * Sifat operasi:
 *   - hanya INSERT, tidak pernah UPDATE/DELETE;
 *   - idempoten: staff yang sudah punya penempatan aktif dilewati;
 *   - dry-run secara default;
 *   - `apply` wajib menyertakan nama database + referensi backup;
 *   - advisory lock agar tidak berjalan ganda.
 *
 * Pemakaian (nilai lewat environment; CodeIgniter menolak "=" pada argv):
 *   php index.php doclinc_nakes_placement_backfill
 *   DOCLINC_PLACEMENT_APPLY=1 \
 *   DOCLINC_PLACEMENT_CONFIRM_DATABASE=doclinc_local \
 *   DOCLINC_PLACEMENT_BACKUP_REFERENCE="dump.sql:sha256:..." \
 *   php index.php doclinc_nakes_placement_backfill
 */
class Doclinc_nakes_placement_backfill extends CI_Controller
{
	const LOCK_NAME = 'doclinc_nakes_placement_backfill';

	private $apply = false;
	private $database = '';
	private $actor = 0;
	private $facilityFilter = '';
	private $staffFilter = 0;
	private $scanned = 0;
	private $inserted = 0;
	private $skipped = array();

	public function index()
	{
		if (!$this->input->is_cli_request()) {
			show_404();
			return;
		}

		$this->db->db_debug = false;
		$this->database = (string) $this->db->database;
		$this->apply = getenv('DOCLINC_PLACEMENT_APPLY') === '1';
		$this->facilityFilter = strtoupper(trim((string) getenv('DOCLINC_PLACEMENT_FACILITY')));
		$this->staffFilter = (int) getenv('DOCLINC_PLACEMENT_STAFF');
		$this->actor = (int) getenv('DOCLINC_PLACEMENT_ACTOR');
		if ($this->actor < 1) {
			$this->actor = $this->defaultActor();
		}

		if (!$this->schemaReady()) {
			$this->emit(array('ok' => false, 'code' => 'schema_unavailable'));
			return;
		}
		if (!$this->applyConfirmed()) {
			$this->emit(array('ok' => false, 'code' => 'apply_confirmation_required'));
			return;
		}

		$lock = 'doclinc_backfill_' . md5(self::LOCK_NAME . ':' . $this->database);
		$locked = (int) $this->db->query('SELECT GET_LOCK(?, 5) AS acquired', array($lock))->row()->acquired === 1;
		if (!$locked) {
			$this->emit(array('ok' => false, 'code' => 'lock_not_acquired'));
			return;
		}

		try {
			$items = $this->plan();
			if ($this->apply) {
				$this->db->trans_begin();
				foreach ($items as $item) {
					if (!$this->insertPlacement($item)) {
						$this->db->trans_rollback();
						$this->emit(array('ok' => false, 'code' => 'insert_failed', 'staff_id' => $item['staff_id']));
						return;
					}
				}
				if (!$this->db->trans_commit()) {
					$this->db->trans_rollback();
					$this->emit(array('ok' => false, 'code' => 'commit_failed'));
					return;
				}
			}
			$this->emit(array(
				'ok' => true,
				'apply' => $this->apply,
				'database' => $this->database,
				'actor_user_id' => $this->actor,
				'scanned' => $this->scanned,
				'planned' => count($items),
				'inserted' => $this->inserted,
				'skipped' => $this->skipped,
				'items' => $items,
			));
		} finally {
			$this->db->query('SELECT RELEASE_LOCK(?)', array($lock));
		}
	}

	private function plan()
	{
		$sql = 'SELECT s.staff_id, s.kode_pkm, s.nama, s.profesi, s.user_id, u.status AS user_status '
			. 'FROM puskesmas_staff s INNER JOIN users u ON u.userId = s.user_id '
			. "LEFT JOIN nakes_facility_placements p ON p.staff_id = s.staff_id AND p.status = 'active' "
			. "WHERE p.placement_id IS NULL AND s.status = 'aktif' AND s.kode_pkm <> '' AND u.status = 'aktif'";
		$params = array();
		if ($this->staffFilter > 0) {
			$sql .= ' AND s.staff_id = ?';
			$params[] = $this->staffFilter;
		}
		if ($this->facilityFilter !== '') {
			$sql .= ' AND UPPER(s.kode_pkm) = ?';
			$params[] = $this->facilityFilter;
		}
		$sql .= ' ORDER BY s.staff_id';
		$rows = $this->db->query($sql, $params)->result();
		$this->scanned = count($rows);
		$items = array();
		foreach ($rows as $row) {
			$facility = strtoupper(trim((string) $row->kode_pkm));
			$known = $this->db->query(
				"SELECT kode_pkm FROM m_puskesmas WHERE UPPER(kode_pkm) = ? AND status = 'aktif' LIMIT 1",
				array($facility)
			)->row();
			if (!$known) {
				$this->skipped[] = array('staff_id' => (int) $row->staff_id, 'code' => 'FACILITY_NOT_FOUND', 'facility' => $facility);
				continue;
			}
			$items[] = array(
				'staff_id' => (int) $row->staff_id,
				'user_id' => (int) $row->user_id,
				'name' => (string) $row->nama,
				'profession' => (string) $row->profesi,
				'facility_code' => $facility,
			);
		}
		return $items;
	}

	private function insertPlacement(array $item)
	{
		$now = date('Y-m-d H:i:s');
		$inserted = $this->db->insert('nakes_facility_placements', array(
			'staff_id' => (int) $item['staff_id'],
			'facility_code' => (string) $item['facility_code'],
			'effective_from' => $now,
			'status' => 'active',
			'created_by_user_id' => $this->actor,
			'created_at' => $now,
			'updated_at' => $now,
		));
		if (!$inserted) {
			return false;
		}
		$this->inserted++;
		return true;
	}

	private function schemaReady()
	{
		foreach (array('puskesmas_staff', 'users', 'm_puskesmas', 'nakes_facility_placements') as $table) {
			$row = $this->db->query(
				'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
				array($table)
			)->row();
			if (!$row || (int) $row->total < 1) {
				return false;
			}
		}
		return true;
	}

	private function applyConfirmed()
	{
		if (!$this->apply) {
			return true;
		}
		$confirm = trim((string) getenv('DOCLINC_PLACEMENT_CONFIRM_DATABASE'));
		$backup = trim((string) getenv('DOCLINC_PLACEMENT_BACKUP_REFERENCE'));
		if ($confirm !== $this->database || $backup === '') {
			return false;
		}
		$parts = explode(':sha256:', $backup);
		if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{64}$/i', $parts[1])) {
			return false;
		}
		$emptyHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
		if (strtolower($parts[1]) === $emptyHash) {
			return false;
		}
		$path = trim($parts[0]);
		if ($path !== '' && is_file($path)) {
			$actual = hash_file('sha256', $path);
			if ($actual === false || !hash_equals(strtolower($parts[1]), strtolower($actual))) {
				return false;
			}
		}
		return true;
	}

	private function defaultActor()
	{
		$row = $this->db->query(
			"SELECT userId FROM users WHERE role IN ('super_admin','admin','dokter') AND status = 'aktif' ORDER BY FIELD(role,'super_admin','admin','dokter'), userId LIMIT 1"
		)->row();
		return $row ? (int) $row->userId : 0;
	}

	private function emit(array $payload)
	{
		$this->output->set_content_type('application/json')->set_output(json_encode($payload, JSON_UNESCAPED_SLASHES));
	}
}
