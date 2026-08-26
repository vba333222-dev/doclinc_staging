<?php
if (PHP_SAPI !== 'cli') { exit('No direct script access allowed'); }

const DOCLINC_VISIT_WORKFLOW_MIGRATION_ID = '20260826000100_visit_clinical_workflow_foundation';

function vcw_migration_db()
{
    $host = getenv('VCW_DB_HOST') ?: '127.0.0.1';
    if (!in_array($host, array('127.0.0.1', 'localhost'), true)) { throw new RuntimeException('database_host_not_loopback'); }
    $database = getenv('VCW_DB_NAME') ?: 'doclinc_visit_test';
    if ($database !== 'doclinc_visit_test') { throw new RuntimeException('disposable_database_required'); }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, getenv('VCW_DB_USER') ?: 'doclinc_test', getenv('VCW_DB_PASSWORD') ?: 'doclinc_test_only', $database, (int) (getenv('VCW_DB_PORT') ?: 33317));
    $db->set_charset('utf8mb4');
    return $db;
}

function vcw_migration_table(mysqli $db, $table)
{
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $statement->bind_param('s', $table); $statement->execute(); $statement->bind_result($count); $statement->fetch(); $statement->close();
    return (int) $count === 1;
}

function vcw_migration_column(mysqli $db, $table, $column)
{
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $statement->bind_param('ss', $table, $column); $statement->execute(); $statement->bind_result($count); $statement->fetch(); $statement->close();
    return (int) $count === 1;
}

function vcw_migration_index(mysqli $db, $table, $index)
{
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
    $statement->bind_param('ss', $table, $index); $statement->execute(); $statement->bind_result($count); $statement->fetch(); $statement->close();
    return (int) $count > 0;
}

try {
    $db = vcw_migration_db();
    foreach (array('requests', 'medicalrecords', 'request_responsible_doctor_assignments', 'request_visit_performer_assignments', 'request_vital_sign_measurements', 'request_events', 'users', 'puskesmas_staff') as $base) {
        if (!vcw_migration_table($db, $base)) { throw new RuntimeException('base_schema_mismatch'); }
    }

    if (!vcw_migration_table($db, 'visit_dispositions')) {
        $db->query("CREATE TABLE visit_dispositions (
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
            CONSTRAINT chk_visit_disposition_urgency CHECK ((decision = 'visit' AND urgency IN ('routine','priority','urgent')) OR (decision = 'non_visit' AND urgency IS NULL)
            )
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!vcw_migration_table($db, 'visit_results')) {
        $db->query("CREATE TABLE visit_results (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!vcw_migration_table($db, 'visit_result_vital_sign_measurements')) {
        $db->query("CREATE TABLE visit_result_vital_sign_measurements (
            visit_result_id BIGINT UNSIGNED NOT NULL,
            measurement_id BIGINT UNSIGNED NOT NULL,
            linked_at DATETIME(6) NOT NULL,
            PRIMARY KEY (visit_result_id, measurement_id),
            CONSTRAINT fk_visit_result_vital_result FOREIGN KEY (visit_result_id) REFERENCES visit_results (visit_result_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
            CONSTRAINT fk_visit_result_vital_measurement FOREIGN KEY (measurement_id) REFERENCES request_vital_sign_measurements (measurement_id) ON DELETE RESTRICT ON UPDATE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!vcw_migration_table($db, 'clinical_reviews')) {
        $db->query("CREATE TABLE clinical_reviews (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!vcw_migration_column($db, 'medicalrecords', 'clinical_finalized_at')) { $db->query('ALTER TABLE medicalrecords ADD COLUMN clinical_finalized_at DATETIME(6) NULL'); }
    if (!vcw_migration_column($db, 'medicalrecords', 'clinical_finalized_by_user_id')) {
        $db->query('ALTER TABLE medicalrecords ADD COLUMN clinical_finalized_by_user_id INT(11) NULL, ADD KEY idx_medical_clinical_finalizer (clinical_finalized_by_user_id, record_id), ADD CONSTRAINT fk_medical_clinical_finalizer FOREIGN KEY (clinical_finalized_by_user_id) REFERENCES users (userId) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }
    if (!vcw_migration_column($db, 'request_events', 'domain_event_key')) { $db->query('ALTER TABLE request_events ADD COLUMN domain_event_key VARCHAR(191) NULL'); }
    if (!vcw_migration_index($db, 'request_events', 'uq_request_events_domain_key')) { $db->query('ALTER TABLE request_events ADD UNIQUE KEY uq_request_events_domain_key (domain_event_key)'); }
    echo "MIGRATION_RESULT=PASS\nMIGRATION_ID=" . DOCLINC_VISIT_WORKFLOW_MIGRATION_ID . "\nDESTRUCTIVE_STATEMENTS=0\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . preg_replace('/[^a-z0-9_]/i', '_', $exception->getMessage()) . "\n");
    exit(1);
}
