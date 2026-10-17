<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('DOCLINC_LEGACY_RUNTIME_TABLES_MIGRATION_ID', '20261012000100_legacy_runtime_tables');

/**
 * Creates the three legacy runtime tables the application still queries but which
 * no earlier migration defines: locations, tbl_riwayat and fcm_tokens.
 *
 * Every consumer guards these with table_exists(), so their absence degrades
 * silently rather than failing. Column names come from the call sites:
 *   locations   -> Home_m::joinDoctorLocations/getAllDataLocations,
 *                  Home_nakes_m::check_ip_exists/update_location/save_location/get_location_user,
 *                  Login_m::save_location
 *   tbl_riwayat -> Home_nakes_m::join_riwayat_or_default, Konsultasi_m::getDataPenunjangById
 *   fcm_tokens  -> Konsultasi_m::getDataTokenDoctor
 */
function legacy_runtime_db()
{
    $host = getenv('VCW_DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('VCW_DB_PORT') ?: 33317);
    $name = getenv('VCW_DB_NAME') ?: 'doclinc_visit_test';
    $user = getenv('VCW_DB_USER') ?: 'doclinc_test';
    $pass = getenv('VCW_DB_PASSWORD') ?: 'doclinc_test_only';
    if (!in_array($host, array('127.0.0.1', 'localhost'), true) || $name !== 'doclinc_visit_test') {
        throw new RuntimeException('Legacy runtime tables migration requires the disposable loopback database');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($host, $user, $pass, $name, $port);
    $db->set_charset('utf8mb4');
    return $db;
}

function legacy_runtime_table_exists(mysqli $db, $table)
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && (int) $row['c'] === 1;
}

function legacy_runtime_column_exists(mysqli $db, $table, $column)
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && (int) $row['c'] === 1;
}

$options = getopt('', array('apply', 'environment:'));
if (!array_key_exists('apply', $options)) {
    echo 'MIGRATION_ID=' . DOCLINC_LEGACY_RUNTIME_TABLES_MIGRATION_ID . "\nEXECUTION_MODE=PLAN\nDATABASE_WRITE_EXECUTED=false\nPLANNED_TABLE_COUNT=3\nDESTRUCTIVE_STATEMENTS=0\n";
    exit(0);
}

try {
    $db = legacy_runtime_db();
    $lock = $db->query("SELECT GET_LOCK('doclinc_legacy_runtime_tables', 10) AS acquired")->fetch_assoc();
    if (!$lock || (int) $lock['acquired'] !== 1) {
        throw new RuntimeException('migration_lock_unavailable');
    }

    foreach (array('users', 'requests') as $base) {
        if (!legacy_runtime_table_exists($db, $base)) {
            throw new RuntimeException('base_schema_mismatch:' . $base);
        }
    }

    $created = 0;

    if (!legacy_runtime_table_exists($db, 'locations')) {
        $db->query("CREATE TABLE locations (
            location_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            id_user INT(11) NOT NULL,
            name VARCHAR(100) NULL,
            location TEXT NULL,
            latitude VARCHAR(50) NULL,
            longitude VARCHAR(50) NULL,
            ip_address VARCHAR(64) NULL,
            create_date DATETIME NULL,
            PRIMARY KEY (location_id),
            KEY idx_locations_user (id_user),
            KEY idx_locations_create_date (create_date),
            KEY idx_locations_ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $created++;
    }

    if (!legacy_runtime_table_exists($db, 'tbl_riwayat')) {
        $db->query("CREATE TABLE tbl_riwayat (
            riwayat_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            idUser INT(11) NOT NULL,
            riwayat LONGTEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (riwayat_id),
            UNIQUE KEY uq_tbl_riwayat_user (idUser)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $created++;
    }

    if (!legacy_runtime_table_exists($db, 'fcm_tokens')) {
        $db->query("CREATE TABLE fcm_tokens (
            token_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            phone VARCHAR(50) NOT NULL,
            token TEXT NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (token_id),
            KEY idx_fcm_tokens_phone (phone)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $created++;
    }

    $required = array(
        'locations' => array('location_id', 'id_user', 'name', 'location', 'latitude', 'longitude', 'ip_address', 'create_date'),
        'tbl_riwayat' => array('riwayat_id', 'idUser', 'riwayat', 'created_at', 'updated_at'),
        'fcm_tokens' => array('token_id', 'phone', 'token', 'created_at', 'updated_at'),
    );
    foreach ($required as $table => $columns) {
        if (!legacy_runtime_table_exists($db, $table)) {
            throw new RuntimeException('post_apply_verification_failed:missing_table:' . $table);
        }
        foreach ($columns as $column) {
            if (!legacy_runtime_column_exists($db, $table, $column)) {
                throw new RuntimeException('post_apply_verification_failed:missing_column:' . $table . '.' . $column);
            }
        }
    }

    echo "MIGRATION_RESULT=PASS\n";
    echo 'MIGRATION_ID=' . DOCLINC_LEGACY_RUNTIME_TABLES_MIGRATION_ID . "\n";
    echo 'TABLES_CREATED=' . $created . "\n";
    echo "DESTRUCTIVE_STATEMENTS=0\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'MIGRATION_RESULT=FAIL message=' . preg_replace('/[\r\n]+/', ' ', $exception->getMessage()) . "\n");
    exit(1);
}

