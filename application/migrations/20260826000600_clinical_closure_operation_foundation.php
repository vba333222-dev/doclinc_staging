<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('DOCLINC_CLINICAL_CLOSURE_OPERATION_MIGRATION_ID', '20260826000600_clinical_closure_operation_foundation');

function clinical_closure_operation_db()
{
    $host = getenv('VCW_DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('VCW_DB_PORT') ?: 33317);
    $name = getenv('VCW_DB_NAME') ?: 'doclinc_visit_test';
    $user = getenv('VCW_DB_USER') ?: 'doclinc_test';
    $pass = getenv('VCW_DB_PASSWORD') ?: 'doclinc_test_only';
    if (!in_array($host, array('127.0.0.1', 'localhost'), true) || $name !== 'doclinc_visit_test') {
        throw new RuntimeException('Clinical closure operation migration requires the disposable loopback database');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, $user, $pass, $name, $port);
    $db->set_charset('utf8mb4');
    return $db;
}

try {
    $db = clinical_closure_operation_db();
    $exists = $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_closure_operations'")->fetch_assoc();
    if ((int) $exists['c'] === 0) {
        $db->query("CREATE TABLE clinical_closure_operations (
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
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    echo "MIGRATION_RESULT=PASS\n";
    echo "MIGRATION_ID=" . DOCLINC_CLINICAL_CLOSURE_OPERATION_MIGRATION_ID . "\n";
    echo "DESTRUCTIVE_STATEMENTS=0\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "MIGRATION_RESULT=FAIL message=" . preg_replace('/[\r\n]+/', ' ', $exception->getMessage()) . "\n");
    exit(1);
}
