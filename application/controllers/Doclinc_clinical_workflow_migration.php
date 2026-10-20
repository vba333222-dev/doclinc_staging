<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Migrasi skema alur klinis untuk instalasi nyata (lokal/staging/produksi).
 *
 * Berbeda dari application/migrations/20260826* yang sengaja dibatasi ke
 * database uji sekali-pakai, controller ini boleh diarahkan ke database
 * aplikasi. Pengamanannya:
 *   - hanya CLI,
 *   - database harus dikonfirmasi eksplisit (--confirm-database),
 *   - wajib menyertakan referensi backup (--backup-reference),
 *   - advisory lock agar tidak berjalan ganda,
 *   - DDL idempoten: dijalankan ulang tidak mengubah apa pun,
 *   - tanpa DROP/TRUNCATE/DML.
 *
 * Pemakaian:
 *   php index.php doclinc_clinical_workflow_migration
 *       -> dry-run: hanya mencetak rencana DDL
 *   php index.php doclinc_clinical_workflow_migration --apply \
 *       --confirm-database=doclinc_local \
 *       --backup-reference="dump-2026-10-18.sql (sha256:...)"
 */
class Doclinc_clinical_workflow_migration extends CI_Controller
{
	const LOCK_NAME = 'doclinc_clinical_workflow_migration';

	private $apply = false;
	private $planned = array();
	private $executed = 0;
	private $database = '';

	public function index()
	{
		if (!$this->input->is_cli_request()) {
			show_404();
			return;
		}

		$this->db->db_debug = false;
		$this->database = (string) $this->db->database;
		$options = $this->cli_options();

		if (!empty($options['help'])) {
			$this->print_usage();
			return;
		}

		$this->apply = !empty($options['apply']);
		$lock = false;

		try {
			$this->assert_preconditions($options);
			$lock = $this->acquire_lock();
			$this->build_plan();
			$this->verify_target_schema($this->apply);
			$this->print_result('PASS');
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

		// Nilai dikirim lewat environment, bukan "--kunci=nilai": CodeIgniter
		// memvalidasi seluruh argv terhadap permitted_uri_chars dan menolak "=".
		$environment = array(
			'DOCLINC_MIGRATION_APPLY' => 'apply',
			'DOCLINC_MIGRATION_CONFIRM_DATABASE' => 'confirm-database',
			'DOCLINC_MIGRATION_BACKUP_REFERENCE' => 'backup-reference',
			'DOCLINC_MIGRATION_HELP' => 'help',
		);
		foreach ($environment as $name => $key) {
			$value = getenv($name);
			if ($value === false || trim((string) $value) === '') {
				continue;
			}
			$options[$key] = trim((string) $value);
		}

		// Argumen CLI tidak dipakai: CodeIgniter memperlakukan "--apply" sebagai
		// segmen URI sehingga method controller tidak ditemukan. Semua opsi
		// dibaca dari environment.

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

		foreach (array('requests', 'users', 'medicalrecords', 'puskesmas_staff', 'm_puskesmas',
			'request_events', 'request_responsible_doctor_assignments',
			'request_visit_performer_assignments', 'request_vital_sign_measurements') as $table) {
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

		// Unique index hanya boleh dipasang bila data lama tidak melanggar.
		if ($this->table_exists('request_visit_performer_assignments')
			&& $this->column_exists('request_visit_performer_assignments', 'active_request_key') === false) {
			$row = $this->scalar(
				'SELECT COUNT(*) AS total FROM ('
				. 'SELECT request_id FROM request_visit_performer_assignments WHERE status = ? GROUP BY request_id HAVING COUNT(*) > 1'
				. ') AS duplicates',
				array('aktif')
			);
			if ((int) $row > 0) {
				throw new RuntimeException('active_assignment_duplicates');
			}
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

	private function print_usage()
	{
		echo "Pemakaian (dry-run):\n";
		echo "  php index.php doclinc_clinical_workflow_migration\n";
		echo "Pemakaian (apply):\n";
		echo "  DOCLINC_MIGRATION_APPLY=1 \\\n";
		echo "  DOCLINC_MIGRATION_CONFIRM_DATABASE=<nama-database> \\\n";
		echo "  DOCLINC_MIGRATION_BACKUP_REFERENCE=<referensi-backup> \\\n";
		echo "  php index.php doclinc_clinical_workflow_migration\n";
	}

	private function print_result($state)
	{
		$count = $this->apply ? $this->executed : count($this->planned);
		$already = $this->apply ? $count === 0 : count($this->planned) === 0;
		if (!$this->apply) {
			foreach ($this->planned as $code) {
				echo 'PLANNED=' . $code . "\n";
			}
		}
		echo "MIGRATION_RESULT={$state}\n";
		echo 'MODE=' . ($this->apply ? 'apply' : 'dry-run') . "\n";
		echo 'DATABASE=' . $this->database . "\n";
		echo 'DDL_STATEMENT_COUNT=' . $count . "\n";
		echo 'ALREADY_APPLIED=' . ($already ? 'true' : 'false') . "\n";
		echo "EXISTING_ROWS_CHANGED=false\n";
	}

	private function print_failure($code)
	{
		$safe = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $code);
		fwrite(STDERR, "MIGRATION_RESULT=FAIL\n");
		fwrite(STDERR, 'SAFE_ERROR_CODE=' . $safe . "\n");
		fwrite(STDERR, 'DDL_STATEMENT_COUNT=' . $this->executed . "\n");
		exit(1);
	}

	private function build_plan()
	{
		$this->plan_nakes_placement_tables();
		$this->plan_visit_disposition_table();
		$this->plan_visit_result_tables();
		$this->plan_visit_result_note_columns();
		$this->plan_clinical_review_table();
		$this->plan_amendment_tables();
		$this->plan_closure_table();
		$this->plan_assignment_operation_table();
		$this->plan_assignment_completion_columns();
		$this->plan_medicalrecord_finalization_columns();
		$this->plan_request_event_domain_key();
		$this->plan_review_provenance();
	}

	private function plan_nakes_placement_tables()
	{
		if (!$this->table_exists('nakes_facility_placements')) {
			$this->plan('create_nakes_facility_placements', <<<'SQL'
CREATE TABLE nakes_facility_placements (
  placement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id INT UNSIGNED NOT NULL,
  facility_code VARCHAR(64) NOT NULL,
  effective_from DATETIME NOT NULL,
  effective_until DATETIME NULL,
  status ENUM('active','ended') NOT NULL DEFAULT 'active',
  active_staff_key INT UNSIGNED AS (CASE WHEN status='active' THEN staff_id ELSE NULL END) STORED,
  created_by_user_id INT NOT NULL,
  ended_by_user_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (placement_id),
  UNIQUE KEY uq_nakes_active_staff (active_staff_key),
  KEY idx_nakes_placement_staff_status (staff_id,status,placement_id),
  KEY idx_nakes_placement_facility_status (facility_code,status,staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
			);
		}

		if (!$this->table_exists('nakes_facility_transfers')) {
			$this->plan('create_nakes_facility_transfers', <<<'SQL'
CREATE TABLE nakes_facility_transfers (
  transfer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  staff_id INT UNSIGNED NOT NULL,
  from_placement_id BIGINT UNSIGNED NOT NULL,
  destination_facility_code VARCHAR(64) NOT NULL,
  effective_at DATETIME NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  scheduled_staff_key INT UNSIGNED AS (CASE WHEN status='scheduled' THEN staff_id ELSE NULL END) STORED,
  requested_by_user_id INT NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_by_user_id INT NULL,
  completed_at DATETIME NULL,
  cancelled_by_user_id INT NULL,
  cancelled_at DATETIME NULL,
  last_block_reason VARCHAR(500) NULL,
  PRIMARY KEY (transfer_id),
  UNIQUE KEY uq_nakes_scheduled_transfer (scheduled_staff_key),
  KEY idx_nakes_transfer_effective (status,effective_at,staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
			);
		}
	}

	private function plan_visit_disposition_table()
	{
		if ($this->table_exists('visit_dispositions')) {
			return;
		}

		$this->plan('create_visit_dispositions', <<<'SQL'
CREATE TABLE visit_dispositions (
  disposition_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT(11) NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  decision ENUM('visit','non_visit') NOT NULL,
  urgency ENUM('routine','priority','urgent') NULL,
  instructions TEXT NULL,
  rationale TEXT NULL,
  required_profession VARCHAR(100) NULL,
  required_competency_note TEXT NULL,
  created_by_user_id INT(11) NOT NULL,
  created_at DATETIME(6) NOT NULL,
  superseded_at DATETIME(6) NULL,
  superseded_by_disposition_id BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(191) NOT NULL,
  active_request_key INT(11) AS (CASE WHEN superseded_at IS NULL THEN request_id ELSE NULL END) STORED,
  PRIMARY KEY (disposition_id),
  UNIQUE KEY uq_visit_disposition_version (request_id, version_no),
  UNIQUE KEY uq_visit_disposition_idempotency (idempotency_key),
  UNIQUE KEY uq_visit_disposition_active_request (active_request_key),
  KEY idx_visit_disposition_creator (created_by_user_id),
  CONSTRAINT fk_visit_disposition_request FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_disposition_creator FOREIGN KEY (created_by_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_disposition_superseded FOREIGN KEY (superseded_by_disposition_id) REFERENCES visit_dispositions (disposition_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_visit_disposition_urgency CHECK (
    (decision = 'visit' AND urgency IS NOT NULL AND urgency IN ('routine','priority','urgent'))
    OR (decision = 'non_visit' AND urgency IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
		);
	}

	private function plan_visit_result_tables()
	{
		if (!$this->table_exists('visit_results')) {
			$this->plan('create_visit_results', <<<'SQL'
CREATE TABLE visit_results (
  visit_result_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT(11) NOT NULL,
  visit_assignment_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  supersedes_result_id BIGINT UNSIGNED NULL,
  performer_user_id INT(11) NOT NULL,
  performer_staff_id INT(10) UNSIGNED NOT NULL,
  status ENUM('draft','submitted') NOT NULL,
  draft_revision INT UNSIGNED NOT NULL DEFAULT 0,
  observation_summary TEXT NULL,
  findings_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  actions_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  performer_notes TEXT NULL,
  created_at DATETIME(6) NOT NULL,
  updated_at DATETIME(6) NOT NULL,
  submitted_at DATETIME(6) NULL,
  submitted_by_user_id INT(11) NULL,
  submission_key VARCHAR(191) NULL,
  active_draft_key BIGINT UNSIGNED AS (CASE WHEN status = 'draft' THEN visit_assignment_id ELSE NULL END) STORED,
  PRIMARY KEY (visit_result_id),
  UNIQUE KEY uq_visit_result_version (visit_assignment_id, version_no),
  UNIQUE KEY uq_visit_result_active_draft (active_draft_key),
  UNIQUE KEY uq_visit_result_submission (submission_key),
  KEY idx_visit_result_request (request_id, visit_result_id),
  CONSTRAINT fk_visit_result_request FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_result_assignment FOREIGN KEY (visit_assignment_id) REFERENCES request_visit_performer_assignments (visit_assignment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_result_supersedes FOREIGN KEY (supersedes_result_id) REFERENCES visit_results (visit_result_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_result_performer FOREIGN KEY (performer_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_result_staff FOREIGN KEY (performer_staff_id) REFERENCES puskesmas_staff (staff_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_result_submitted_by FOREIGN KEY (submitted_by_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_visit_result_findings_json CHECK (JSON_VALID(findings_json)),
  CONSTRAINT chk_visit_result_actions_json CHECK (JSON_VALID(actions_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
			);
		}

		if (!$this->table_exists('visit_result_vital_sign_measurements')) {
			$this->plan('create_visit_result_vital_sign_measurements', <<<'SQL'
CREATE TABLE visit_result_vital_sign_measurements (
  visit_result_id BIGINT UNSIGNED NOT NULL,
  measurement_id BIGINT UNSIGNED NOT NULL,
  linked_at DATETIME(6) NOT NULL,
  PRIMARY KEY (visit_result_id, measurement_id),
  CONSTRAINT fk_visit_result_vital_result FOREIGN KEY (visit_result_id) REFERENCES visit_results (visit_result_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_result_vital_measurement FOREIGN KEY (measurement_id) REFERENCES request_vital_sign_measurements (measurement_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
			);
		}
	}

	private function plan_visit_result_note_columns()
	{
		if (!$this->column_exists('visit_results', 'anamnesis')) {
			$this->plan('alter_visit_result_anamnesis', 'ALTER TABLE `visit_results` ADD COLUMN `anamnesis` TEXT NULL AFTER `performer_notes`');
		}
		if (!$this->column_exists('visit_results', 'therapy')) {
			$this->plan('alter_visit_result_therapy', 'ALTER TABLE `visit_results` ADD COLUMN `therapy` TEXT NULL AFTER `anamnesis`');
		}
	}

	private function plan_clinical_review_table()
	{
		if ($this->table_exists('clinical_reviews')) {
			return;
		}

		$this->plan('create_clinical_reviews', <<<'SQL'
CREATE TABLE clinical_reviews (
  clinical_review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT(11) NOT NULL,
  visit_result_id BIGINT UNSIGNED NOT NULL,
  reviewer_user_id INT(11) NOT NULL,
  responsible_assignment_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('approved','correction_required') NOT NULL,
  correction_reason TEXT NULL,
  review_notes TEXT NULL,
  reviewed_at DATETIME(6) NOT NULL,
  idempotency_key VARCHAR(191) NOT NULL,
  PRIMARY KEY (clinical_review_id),
  UNIQUE KEY uq_clinical_review_result (visit_result_id),
  UNIQUE KEY uq_clinical_review_idempotency (idempotency_key),
  KEY idx_clinical_review_request (request_id, clinical_review_id),
  CONSTRAINT fk_clinical_review_request FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_review_result FOREIGN KEY (visit_result_id) REFERENCES visit_results (visit_result_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_review_reviewer FOREIGN KEY (reviewer_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_review_assignment FOREIGN KEY (responsible_assignment_id) REFERENCES request_responsible_doctor_assignments (responsible_assignment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_clinical_review_reason CHECK (decision = 'approved' OR (correction_reason IS NOT NULL AND CHAR_LENGTH(TRIM(correction_reason)) > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
		);
	}

	private function plan_amendment_tables()
	{
		if (!$this->table_exists('clinical_amendments')) {
			$this->plan('create_clinical_amendments', <<<'SQL'
CREATE TABLE clinical_amendments (
  amendment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  record_id INT(11) NOT NULL,
  request_id INT(11) NOT NULL,
  sequence_no INT UNSIGNED NOT NULL,
  previous_amendment_id BIGINT UNSIGNED NULL,
  created_by_user_id INT(11) NOT NULL,
  reason TEXT NOT NULL,
  created_at DATETIME(6) NOT NULL,
  idempotency_key VARCHAR(191) NOT NULL,
  PRIMARY KEY (amendment_id),
  UNIQUE KEY uq_clinical_amendment_sequence (record_id,sequence_no),
  UNIQUE KEY uq_clinical_amendment_idempotency (idempotency_key),
  KEY idx_clinical_amendment_request (request_id,amendment_id),
  CONSTRAINT fk_clinical_amendment_record FOREIGN KEY (record_id) REFERENCES medicalrecords (record_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_amendment_request FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_amendment_previous FOREIGN KEY (previous_amendment_id) REFERENCES clinical_amendments (amendment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_amendment_author FOREIGN KEY (created_by_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
			);
		}

		if (!$this->table_exists('clinical_amendment_items')) {
			$this->plan('create_clinical_amendment_items', <<<'SQL'
CREATE TABLE clinical_amendment_items (
  amendment_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  amendment_id BIGINT UNSIGNED NOT NULL,
  item_order INT UNSIGNED NOT NULL,
  field_key VARCHAR(100) NOT NULL,
  previous_value TEXT NULL,
  corrected_value TEXT NULL,
  PRIMARY KEY (amendment_item_id),
  UNIQUE KEY uq_clinical_amendment_item_order (amendment_id,item_order),
  CONSTRAINT fk_clinical_amendment_item_header FOREIGN KEY (amendment_id) REFERENCES clinical_amendments (amendment_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
			);
		}
	}

	private function plan_closure_table()
	{
		if ($this->table_exists('clinical_closure_operations')) {
			return;
		}

		$this->plan('create_clinical_closure_operations', <<<'SQL'
CREATE TABLE clinical_closure_operations (
  closure_operation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT(11) NOT NULL,
  closure_mode ENUM('visit','non_visit') NOT NULL,
  idempotency_key VARCHAR(191) NOT NULL,
  operation_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  closed_by_user_id INT(11) NOT NULL,
  authority_source ENUM('responsible_doctor','doctor_visit_performer') NOT NULL,
  responsible_assignment_id BIGINT UNSIGNED NULL,
  visit_assignment_id BIGINT UNSIGNED NULL,
  closed_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (closure_operation_id),
  UNIQUE KEY uq_clinical_closure_operation_request (request_id),
  UNIQUE KEY uq_clinical_closure_operation_idempotency (idempotency_key),
  CONSTRAINT ck_clinical_closure_operation_provenance CHECK (
    (closure_mode = 'visit' AND authority_source = 'responsible_doctor' AND responsible_assignment_id IS NOT NULL AND visit_assignment_id IS NULL)
    OR (closure_mode = 'visit' AND authority_source = 'doctor_visit_performer' AND responsible_assignment_id IS NULL AND visit_assignment_id IS NOT NULL)
    OR (closure_mode = 'non_visit' AND authority_source = 'responsible_doctor' AND responsible_assignment_id IS NOT NULL AND visit_assignment_id IS NULL)
  ),
  CONSTRAINT fk_clinical_closure_operation_request FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_closure_operation_actor FOREIGN KEY (closed_by_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_closure_operation_responsible FOREIGN KEY (responsible_assignment_id) REFERENCES request_responsible_doctor_assignments (responsible_assignment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clinical_closure_operation_visit FOREIGN KEY (visit_assignment_id) REFERENCES request_visit_performer_assignments (visit_assignment_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
		);
	}

	private function plan_assignment_operation_table()
	{
		if ($this->table_exists('visit_assignment_operations')) {
			return;
		}

		$this->plan('create_visit_assignment_operations', <<<'SQL'
CREATE TABLE visit_assignment_operations (
  assignment_operation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT(11) NOT NULL,
  operation_type ENUM('assign','reassign','cancel_before_start') NOT NULL,
  actor_user_id INT(11) NOT NULL,
  source_assignment_id BIGINT UNSIGNED NULL,
  result_assignment_id BIGINT UNSIGNED NULL,
  idempotency_key VARCHAR(191) NOT NULL,
  operation_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (assignment_operation_id),
  UNIQUE KEY uq_visit_assignment_operation_idempotency (idempotency_key),
  KEY idx_visit_assignment_operation_request_created (request_id, created_at),
  CONSTRAINT ck_visit_assignment_operation_shape CHECK (
    (operation_type = 'assign' AND source_assignment_id IS NULL AND result_assignment_id IS NOT NULL)
    OR (operation_type = 'reassign' AND source_assignment_id IS NOT NULL AND result_assignment_id IS NOT NULL AND source_assignment_id <> result_assignment_id)
    OR (operation_type = 'cancel_before_start' AND source_assignment_id IS NOT NULL AND result_assignment_id IS NULL)
  ),
  CONSTRAINT fk_visit_assignment_operation_request FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_assignment_operation_actor FOREIGN KEY (actor_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_assignment_operation_source FOREIGN KEY (source_assignment_id) REFERENCES request_visit_performer_assignments (visit_assignment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_visit_assignment_operation_result FOREIGN KEY (result_assignment_id) REFERENCES request_visit_performer_assignments (visit_assignment_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
		);
	}

	private function plan_assignment_completion_columns()
	{
		$table = 'request_visit_performer_assignments';
		$status_type = $this->column_type($table, 'status');
		if ($status_type !== '' && stripos($status_type, "'selesai'") === false) {
			$this->plan('alter_assignment_status_selesai',
				"ALTER TABLE `{$table}` MODIFY COLUMN `status` ENUM('aktif','diganti','dibatalkan','selesai') NOT NULL DEFAULT 'aktif'");
		}

		$columns = array(
			'ended_by_user_id' => "ALTER TABLE `{$table}` ADD COLUMN `ended_by_user_id` INT(11) NULL AFTER `ended_at`",
			'end_reason' => "ALTER TABLE `{$table}` ADD COLUMN `end_reason` TEXT NULL AFTER `ended_by_user_id`",
			'completed_at' => "ALTER TABLE `{$table}` ADD COLUMN `completed_at` DATETIME(6) NULL AFTER `end_reason`",
		);
		foreach ($columns as $column => $sql) {
			if (!$this->column_exists($table, $column)) {
				$this->plan('alter_assignment_add_' . $column, $sql);
			}
		}

		if (!$this->column_exists($table, 'active_request_key')) {
			$this->plan('alter_assignment_active_key',
				"ALTER TABLE `{$table}` ADD COLUMN `active_request_key` INT(11) AS (CASE WHEN status='aktif' THEN request_id ELSE NULL END) STORED, ADD UNIQUE KEY `uq_visit_performer_active_request` (`active_request_key`)");
		} elseif (!$this->index_exists($table, 'uq_visit_performer_active_request')) {
			$this->plan('alter_assignment_active_key_index',
				"ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_visit_performer_active_request` (`active_request_key`)");
		}
	}

	private function plan_medicalrecord_finalization_columns()
	{
		if (!$this->column_exists('medicalrecords', 'clinical_finalized_at')) {
			$this->plan('alter_medicalrecords_finalized_at',
				'ALTER TABLE `medicalrecords` ADD COLUMN `clinical_finalized_at` DATETIME(6) NULL');
		}

		if (!$this->column_exists('medicalrecords', 'clinical_finalized_by_user_id')) {
			$this->plan('alter_medicalrecords_finalized_by',
				'ALTER TABLE `medicalrecords` ADD COLUMN `clinical_finalized_by_user_id` INT(11) NULL, '
				. 'ADD KEY `idx_medical_clinical_finalizer` (`clinical_finalized_by_user_id`, `record_id`), '
				. 'ADD CONSTRAINT `fk_medical_clinical_finalizer` FOREIGN KEY (`clinical_finalized_by_user_id`) REFERENCES `users` (`userId`) ON DELETE RESTRICT ON UPDATE RESTRICT');
			return;
		}

		if (!$this->index_exists('medicalrecords', 'idx_medical_clinical_finalizer')) {
			$this->plan('alter_medicalrecords_finalizer_index',
				'ALTER TABLE `medicalrecords` ADD KEY `idx_medical_clinical_finalizer` (`clinical_finalized_by_user_id`, `record_id`)');
		}
		if (!$this->constraint_exists('medicalrecords', 'fk_medical_clinical_finalizer')) {
			$this->plan('alter_medicalrecords_finalizer_fk',
				'ALTER TABLE `medicalrecords` ADD CONSTRAINT `fk_medical_clinical_finalizer` FOREIGN KEY (`clinical_finalized_by_user_id`) REFERENCES `users` (`userId`) ON DELETE RESTRICT ON UPDATE RESTRICT');
		}
	}

	private function plan_request_event_domain_key()
	{
		if (!$this->column_exists('request_events', 'domain_event_key')) {
			$this->plan('alter_request_events_domain_key',
				'ALTER TABLE `request_events` ADD COLUMN `domain_event_key` VARCHAR(191) NULL');
		}
		if (!$this->index_exists('request_events', 'uq_request_events_domain_key')) {
			$this->plan('alter_request_events_domain_key_unique',
				'ALTER TABLE `request_events` ADD UNIQUE KEY `uq_request_events_domain_key` (`domain_event_key`)');
		}
	}

	private function plan_review_provenance()
	{
		if (!$this->table_exists('clinical_reviews')) {
			return;
		}

		if ($this->is_nullable('clinical_reviews', 'responsible_assignment_id') === 'NO') {
			$this->plan('alter_reviews_responsible_nullable',
				'ALTER TABLE `clinical_reviews` MODIFY COLUMN `responsible_assignment_id` BIGINT UNSIGNED NULL');
		}

		if (!$this->column_exists('clinical_reviews', 'reviewer_visit_assignment_id')) {
			$this->plan('alter_reviews_add_visit_assignment',
				'ALTER TABLE `clinical_reviews` ADD COLUMN `reviewer_visit_assignment_id` BIGINT UNSIGNED NULL AFTER `responsible_assignment_id`');
		}
		if (!$this->index_exists('clinical_reviews', 'idx_clinical_review_visit_assignment')) {
			$this->plan('alter_reviews_visit_assignment_index',
				'ALTER TABLE `clinical_reviews` ADD KEY `idx_clinical_review_visit_assignment` (`reviewer_visit_assignment_id`)');
		}
		if (!$this->constraint_exists('clinical_reviews', 'fk_clinical_review_visit_assignment')) {
			$this->plan('alter_reviews_visit_assignment_fk',
				'ALTER TABLE `clinical_reviews` ADD CONSTRAINT `fk_clinical_review_visit_assignment` FOREIGN KEY (`reviewer_visit_assignment_id`) REFERENCES `request_visit_performer_assignments` (`visit_assignment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT');
		}
		if (!$this->constraint_exists('clinical_reviews', 'chk_clinical_review_provenance')) {
			$this->plan('alter_reviews_provenance_check',
				'ALTER TABLE `clinical_reviews` ADD CONSTRAINT `chk_clinical_review_provenance` CHECK ((`responsible_assignment_id` IS NOT NULL AND `reviewer_visit_assignment_id` IS NULL) OR (`responsible_assignment_id` IS NULL AND `reviewer_visit_assignment_id` IS NOT NULL))');
		}
	}

	private function plan($code, $sql)
	{
		if (!$this->apply) {
			$this->planned[] = $code;
			return;
		}

		$result = $this->db->query($sql);
		if ($result === false) {
			$error = $this->db->error();
			throw new RuntimeException('ddl_failed_' . $code . '_' . (int) ($error['code'] ?? 0));
		}
		$this->executed++;
	}

	private function verify_target_schema($throw = true)
	{
		$requirements = array(
			'nakes_facility_placements' => array('placement_id', 'staff_id', 'facility_code', 'status', 'active_staff_key'),
			'nakes_facility_transfers' => array('transfer_id', 'staff_id', 'status'),
			'visit_dispositions' => array('disposition_id', 'request_id', 'version_no', 'decision', 'urgency', 'required_profession', 'idempotency_key', 'superseded_at'),
			'visit_results' => array('visit_result_id', 'request_id', 'visit_assignment_id', 'status', 'draft_revision', 'submission_key', 'anamnesis', 'therapy'),
			'visit_result_vital_sign_measurements' => array('visit_result_id', 'measurement_id'),
			'request_vital_sign_measurements' => array('measurement_id', 'request_id', 'measured_by_user_id', 'visit_performer_user_id'),
			'clinical_reviews' => array('clinical_review_id', 'request_id', 'visit_result_id', 'reviewer_user_id', 'responsible_assignment_id', 'reviewer_visit_assignment_id', 'decision', 'idempotency_key'),
			'clinical_amendments' => array('amendment_id', 'record_id', 'idempotency_key'),
			'clinical_amendment_items' => array('amendment_item_id', 'amendment_id', 'field_key'),
			'clinical_closure_operations' => array('closure_operation_id', 'request_id', 'closure_mode', 'authority_source'),
			'visit_assignment_operations' => array('assignment_operation_id', 'operation_type', 'idempotency_key'),
		);

		$missing = array();
		foreach ($requirements as $table => $columns) {
			if (!$this->table_exists($table)) {
				$missing[] = 'table:' . $table;
				continue;
			}
			foreach ($columns as $column) {
				if (!$this->column_exists($table, $column)) {
					$missing[] = 'column:' . $table . '.' . $column;
				}
			}
		}

		foreach (array('ended_by_user_id', 'end_reason', 'completed_at', 'active_request_key') as $column) {
			if (!$this->column_exists('request_visit_performer_assignments', $column)) {
				$missing[] = 'column:request_visit_performer_assignments.' . $column;
			}
		}
		if (stripos($this->column_type('request_visit_performer_assignments', 'status'), "'selesai'") === false) {
			$missing[] = 'enum:request_visit_performer_assignments.status.selesai';
		}
		foreach (array('clinical_finalized_at', 'clinical_finalized_by_user_id') as $column) {
			if (!$this->column_exists('medicalrecords', $column)) {
				$missing[] = 'column:medicalrecords.' . $column;
			}
		}
		if (!$this->column_exists('request_events', 'domain_event_key')) {
			$missing[] = 'column:request_events.domain_event_key';
		}

		if (empty($missing)) {
			return true;
		}
		foreach ($missing as $item) {
			echo 'MISSING=' . $item . "\n";
		}
		if ($throw) {
			throw new RuntimeException('target_schema_mismatch_' . count($missing));
		}
		return false;
	}

	private function table_exists($table)
	{
		// Sengaja tidak memakai $this->db->table_exists(): CodeIgniter menyimpan
		// daftar tabel di data_cache, sehingga tabel yang baru dibuat pada run
		// yang sama masih terbaca "tidak ada".
		$row = $this->db->query(
			'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
			array((string) $table)
		)->row();
		return $row && (int) $row->total > 0;
	}

	private function column_exists($table, $column)
	{
		$row = $this->db->query(
			'SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
			array((string) $table, (string) $column)
		)->row();
		return $row && (int) $row->total > 0;
	}

	private function column_type($table, $column)
	{
		$row = $this->db->query(
			'SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
			array((string) $table, (string) $column)
		)->row();
		return $row ? (string) $row->column_type : '';
	}

	private function is_nullable($table, $column)
	{
		$row = $this->db->query(
			'SELECT IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
			array((string) $table, (string) $column)
		)->row();
		return $row ? strtoupper((string) $row->is_nullable) : '';
	}

	private function index_exists($table, $index)
	{
		$row = $this->db->query(
			'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
			array((string) $table, (string) $index)
		)->row();
		return $row && (int) $row->total > 0;
	}

	private function constraint_exists($table, $constraint)
	{
		$row = $this->db->query(
			'SELECT COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
			array((string) $table, (string) $constraint)
		)->row();
		return $row && (int) $row->total > 0;
	}

	private function scalar($sql, array $binds = array())
	{
		$row = $this->db->query($sql, $binds)->row();
		if (!$row) {
			return 0;
		}
		$values = array_values((array) $row);
		return isset($values[0]) ? $values[0] : 0;
	}
}
