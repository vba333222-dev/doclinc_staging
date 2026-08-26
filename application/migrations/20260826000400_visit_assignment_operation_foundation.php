<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('DOCLINC_VISIT_ASSIGNMENT_OPERATION_MIGRATION_ID', '20260826000400_visit_assignment_operation_foundation');

function visit_assignment_operation_db()
{
    $host = getenv('VCW_DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('VCW_DB_PORT') ?: 33317);
    $name = getenv('VCW_DB_NAME') ?: 'doclinc_visit_test';
    $user = getenv('VCW_DB_USER') ?: 'doclinc_test';
    $pass = getenv('VCW_DB_PASSWORD') ?: 'doclinc_test_only';
    if (!in_array($host, array('127.0.0.1', 'localhost'), true) || $name !== 'doclinc_visit_test') {
        throw new RuntimeException('Visit assignment operation migration requires the disposable loopback database');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, $user, $pass, $name, $port);
    $db->set_charset('utf8mb4');
    return $db;
}

try {
    $db = visit_assignment_operation_db();
    $exists = $db->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'visit_assignment_operations'")->fetch_assoc();
    if ((int) $exists['c'] === 0) {
        $db->query("CREATE TABLE visit_assignment_operations (
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
        ) ENGINE=InnoDB DEFAULT CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    echo "MIGRATION_RESULT=PASS\n";
    echo "MIGRATION_ID=" . DOCLINC_VISIT_ASSIGNMENT_OPERATION_MIGRATION_ID . "\n";
    echo "DESTRUCTIVE_STATEMENTS=0\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "MIGRATION_RESULT=FAIL message=" . preg_replace('/[\r\n]+/', ' ', $exception->getMessage()) . "\n");
    exit(1);
}
