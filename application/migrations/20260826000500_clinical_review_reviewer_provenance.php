<?php
if (PHP_SAPI !== 'cli') { exit('No direct access'); }

const DOCLINC_CLINICAL_REVIEW_PROVENANCE_MIGRATION_ID = '20260826000500_clinical_review_reviewer_provenance';

function vcw_review_provenance_db() {
    $host = getenv('VCW_DB_HOST') ?: '127.0.0.1';
    $name = getenv('VCW_DB_NAME') ?: 'doclinc_visit_test';
    if (!in_array($host, array('127.0.0.1', 'localhost'), true) || $name !== 'doclinc_visit_test') {
        throw new RuntimeException('disposable_database_required');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, getenv('VCW_DB_USER') ?: 'doclinc_test', getenv('VCW_DB_PASSWORD') ?: 'doclinc_test_only', $name, (int) (getenv('VCW_DB_PORT') ?: 33317));
    $db->set_charset('utf8mb4');
    return $db;
}

function vcw_review_provenance_column(mysqli $db, $column) {
    $s = $db->prepare("SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='clinical_reviews' AND column_name=?");
    $s->bind_param('s', $column); $s->execute(); $s->bind_result($type, $nullable); $found = $s->fetch(); $s->close();
    return $found ? array('type' => $type, 'nullable' => $nullable) : null;
}
function vcw_review_provenance_index(mysqli $db, $name) {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='clinical_reviews' AND index_name=?");
    $s->bind_param('s', $name); $s->execute(); $s->bind_result($count); $s->fetch(); $s->close(); return (int) $count > 0;
}
function vcw_review_provenance_fk(mysqli $db, $name) {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='clinical_reviews' AND constraint_name=? AND constraint_type='FOREIGN KEY'");
    $s->bind_param('s', $name); $s->execute(); $s->bind_result($count); $s->fetch(); $s->close(); return (int) $count > 0;
}

function vcw_review_provenance_apply(mysqli $db) {
    $responsible = vcw_review_provenance_column($db, 'responsible_assignment_id');
    if (!$responsible) { throw new RuntimeException('base_schema_mismatch'); }
    if ($responsible['nullable'] !== 'YES') {
        $db->query('ALTER TABLE clinical_reviews MODIFY COLUMN responsible_assignment_id BIGINT UNSIGNED NULL');
    }
    if (!vcw_review_provenance_column($db, 'reviewer_visit_assignment_id')) {
        $db->query('ALTER TABLE clinical_reviews ADD COLUMN reviewer_visit_assignment_id BIGINT UNSIGNED NULL AFTER responsible_assignment_id');
    }
    if (!vcw_review_provenance_index($db, 'idx_clinical_review_visit_assignment')) {
        $db->query('ALTER TABLE clinical_reviews ADD KEY idx_clinical_review_visit_assignment (reviewer_visit_assignment_id)');
    }
    if (!vcw_review_provenance_fk($db, 'fk_clinical_review_visit_assignment')) {
        $db->query('ALTER TABLE clinical_reviews ADD CONSTRAINT fk_clinical_review_visit_assignment FOREIGN KEY (reviewer_visit_assignment_id) REFERENCES request_visit_performer_assignments (visit_assignment_id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }
    $check = $db->query("SELECT COUNT(*) AS c FROM information_schema.check_constraints WHERE constraint_schema=DATABASE() AND table_name='clinical_reviews' AND constraint_name='chk_clinical_review_provenance'")->fetch_assoc();
    if ((int) $check['c'] === 0) {
        $db->query('ALTER TABLE clinical_reviews ADD CONSTRAINT chk_clinical_review_provenance CHECK ((responsible_assignment_id IS NOT NULL AND reviewer_visit_assignment_id IS NULL) OR (responsible_assignment_id IS NULL AND reviewer_visit_assignment_id IS NOT NULL))');
    }
}

function vcw_review_provenance_rollback(mysqli $db) {
    $rows = $db->query('SELECT COUNT(*) AS c FROM clinical_reviews WHERE responsible_assignment_id IS NULL OR reviewer_visit_assignment_id IS NOT NULL')->fetch_assoc();
    if ((int) $rows['c'] > 0) { throw new RuntimeException('rollback_blocked_provenance_rows'); }
    $check = $db->query("SELECT COUNT(*) AS c FROM information_schema.check_constraints WHERE constraint_schema=DATABASE() AND table_name='clinical_reviews' AND constraint_name='chk_clinical_review_provenance'")->fetch_assoc();
    if ((int) $check['c'] > 0) { $db->query('ALTER TABLE clinical_reviews DROP CONSTRAINT chk_clinical_review_provenance'); }
    if (vcw_review_provenance_fk($db, 'fk_clinical_review_visit_assignment')) { $db->query('ALTER TABLE clinical_reviews DROP FOREIGN KEY fk_clinical_review_visit_assignment'); }
    if (vcw_review_provenance_index($db, 'idx_clinical_review_visit_assignment')) { $db->query('ALTER TABLE clinical_reviews DROP INDEX idx_clinical_review_visit_assignment'); }
    if (vcw_review_provenance_column($db, 'reviewer_visit_assignment_id')) { $db->query('ALTER TABLE clinical_reviews DROP COLUMN reviewer_visit_assignment_id'); }
    $db->query('ALTER TABLE clinical_reviews MODIFY COLUMN responsible_assignment_id BIGINT UNSIGNED NOT NULL');
}

try {
    $db = vcw_review_provenance_db();
    $mode = $argv[1] ?? '--apply';
    if ($mode === '--rollback' || $mode === '--down') { vcw_review_provenance_rollback($db); } else { vcw_review_provenance_apply($db); }
    echo "MIGRATION_RESULT=PASS\nMIGRATION_ID=" . DOCLINC_CLINICAL_REVIEW_PROVENANCE_MIGRATION_ID . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "MIGRATION_RESULT=FAIL\nSAFE_ERROR_CODE=" . preg_replace('/[^a-z0-9_]/i', '_', $e->getMessage()) . "\n"); exit(1);
}
