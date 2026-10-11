<?php
/**
 * Backfill penanggung jawab layanan (PIC) dua arah — alat lokal, bukan bagian aplikasi.
 *
 * Menyinkronkan dua sumber data PIC agar peralihan mode alur (legacy <-> care-team)
 * tidak kehilangan data penugasan:
 *
 *   arah A (legacy -> care-team):
 *     request dengan penugasan staf aktif tetapi responsible_doctor_user_id NULL
 *     -> isi responsible_doctor_user_id dari user_id staf yang ditugaskan.
 *
 *   arah B (care-team -> legacy):
 *     request dengan responsible_doctor_user_id terisi tetapi tanpa penugasan staf aktif
 *     -> buat baris request_staff_assignments (status 'aktif').
 *
 * Penggunaan (dari root repo):
 *   php tools/puskesmas_operations/backfill_pic.php                  # dry-run (tanpa menulis)
 *   php tools/puskesmas_operations/backfill_pic.php --apply          # tulis perubahan
 *   php tools/puskesmas_operations/backfill_pic.php --puskesmas=10280101
 *
 * Koneksi DB memakai env yang sama dengan aplikasi: DB_HOST, DB_NAME, DB_USER, DB_PASS.
 * Idempotent: menjalankan ulang setelah --apply tidak mengubah apa pun.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Hanya dapat dijalankan dari CLI.\n");
    exit(2);
}

$apply = false;
$puskesmas_filter = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } elseif (preg_match('/^--puskesmas=(.+)$/', $arg, $m)) {
        $puskesmas_filter = trim($m[1]);
    } else {
        fwrite(STDERR, "Argumen tidak dikenal: {$arg}\n");
        fwrite(STDERR, "Penggunaan: php backfill_pic.php [--apply] [--puskesmas=KODE]\n");
        exit(2);
    }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$name = getenv('DB_NAME') ?: 'doclinc_local';
$user = getenv('DB_USER') ?: 'root';
$pass = (string) (getenv('DB_PASS') ?: '');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli($host, $user, $pass, $name, 3306);
} catch (Throwable $e) {
    fwrite(STDERR, 'Koneksi DB gagal: ' . $e->getMessage() . "\n");
    exit(1);
}
$db->set_charset('utf8mb4');

echo "Mode: " . ($apply ? "TERAPKAN (--apply)" : "DRY-RUN (tidak menulis; pakai --apply untuk menerapkan)") . "\n";
echo "Database: {$name} @ {$host}\n";
if ($puskesmas_filter !== '') {
    echo "Filter puskesmas: {$puskesmas_filter}\n";
}

function column_exists($db, $table, $column)
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int) $count > 0;
}

if (!column_exists($db, 'requests', 'responsible_doctor_user_id')
    || !column_exists($db, 'requests', 'consultation_mode')
    || !column_exists($db, 'requests', 'visit_performer_user_id')) {
    fwrite(STDERR, "Kolom care-team belum ada di tabel requests — tidak ada yang perlu disinkronkan.\n");
    $db->close();
    exit(0);
}
$table_ready = column_exists($db, 'request_staff_assignments', 'staff_id');

$tenant_where = '';
$tenant_params = array();
if ($puskesmas_filter !== '') {
    $tenant_where = ' AND r.assigned_puskesmas_code = ?';
    $tenant_params[] = $puskesmas_filter;
}

$changes = 0;
$conflicts = 0;

// ---------- Arah A: legacy -> care-team ----------
echo "\n=== Arah A: penugasan staf aktif -> responsible_doctor_user_id ===\n";
if (!$table_ready) {
    echo "Tabel request_staff_assignments tidak ada; dilewati.\n";
} else {
    $sql = 'SELECT r.request_id, r.assigned_puskesmas_code, r.responsible_doctor_user_id,
                   COUNT(rsa.assignment_id) AS active_count,
                   GROUP_CONCAT(ps.user_id ORDER BY rsa.assignment_id) AS staff_user_ids
            FROM requests r
            JOIN request_staff_assignments rsa ON rsa.request_id = r.request_id AND rsa.status = \'aktif\'
            LEFT JOIN puskesmas_staff ps ON ps.staff_id = rsa.staff_id
            WHERE r.request_status IN (\'Accepted\', \'Completed\')
              AND (r.responsible_doctor_user_id IS NULL OR r.responsible_doctor_user_id < 1)'
        . $tenant_where
        . ' GROUP BY r.request_id, r.assigned_puskesmas_code, r.responsible_doctor_user_id
           ORDER BY r.request_id';
    $stmt = $db->prepare($sql);
    if ($tenant_params) {
        $stmt->bind_param(str_repeat('s', count($tenant_params)), ...$tenant_params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $user_ids = array_values(array_filter(array_map('trim', explode(',', (string) $row['staff_user_ids'])), function ($v) {
            return $v !== '' && $v !== '0' && $v !== null;
        }));
        if ((int) $row['active_count'] !== 1 || count($user_ids) !== 1) {
            $conflicts++;
            echo "  KONFLIK request {$row['request_id']}: " . (int) $row['active_count'] . " penugasan aktif (user: " . ($row['staff_user_ids'] ?: '-') . ") — lewati.\n";
            continue;
        }
        $user_id = (int) $user_ids[0];
        $line = "  request {$row['request_id']} ({$row['assigned_puskesmas_code']}): responsible_doctor_user_id "
            . (var_export($row['responsible_doctor_user_id'], true)) . ' -> ' . $user_id;
        if ($apply) {
            $upd = $db->prepare('UPDATE requests SET responsible_doctor_user_id = ? WHERE request_id = ? AND (responsible_doctor_user_id IS NULL OR responsible_doctor_user_id < 1)');
            $upd->bind_param('ii', $user_id, $row['request_id']);
            $upd->execute();
            $changed = $upd->affected_rows > 0;
            $upd->close();
            echo $line . ($changed ? " — DITERAPKAN.\n" : " — tidak berubah (sudah terisi).\n");
            if ($changed) {
                $changes++;
            }
        } else {
            echo $line . "\n";
            $changes++;
        }
    }
    if (!$rows) {
        echo "  Tidak ada request yang perlu di-backfill (arah A).\n";
    }
}

// ---------- Arah B: care-team -> legacy ----------
echo "\n=== Arah B: responsible_doctor_user_id -> penugasan staf aktif ===\n";
if (!$table_ready) {
    echo "Tabel request_staff_assignments tidak ada; dilewati.\n";
} else {
    $sql = 'SELECT r.request_id, r.assigned_puskesmas_code, r.responsible_doctor_user_id
            FROM requests r
            WHERE r.request_status IN (\'Accepted\', \'Completed\')
              AND r.responsible_doctor_user_id > 0
              AND NOT EXISTS (
                  SELECT 1 FROM request_staff_assignments x
                  WHERE x.request_id = r.request_id AND x.status = \'aktif\'
              )'
        . $tenant_where
        . ' ORDER BY r.request_id';
    $stmt = $db->prepare($sql);
    if ($tenant_params) {
        $stmt->bind_param(str_repeat('s', count($tenant_params)), ...$tenant_params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $find = $db->prepare('SELECT staff_id FROM puskesmas_staff WHERE user_id = ? AND status = \'aktif\' ORDER BY staff_id LIMIT 1');
        $find->bind_param('i', $row['responsible_doctor_user_id']);
        $find->execute();
        $staff = $find->get_result()->fetch_assoc();
        $find->close();

        if (!$staff) {
            $conflicts++;
            echo "  KONFLIK request {$row['request_id']}: user {$row['responsible_doctor_user_id']} bukan staf aktif — lewati.\n";
            continue;
        }
        $line = "  request {$row['request_id']} ({$row['assigned_puskesmas_code']}): buat penugasan staf {$staff['staff_id']} (user {$row['responsible_doctor_user_id']})";
        if ($apply) {
            $next = $db->query('SELECT COALESCE(MAX(assignment_id), 0) + 1 AS next_id FROM request_staff_assignments')->fetch_assoc();
            $assignment_id = (int) $next['next_id'];
            $ins = $db->prepare('INSERT INTO request_staff_assignments (assignment_id, request_id, staff_id, status) VALUES (?, ?, ?, \'aktif\')');
            $ins->bind_param('iii', $assignment_id, $row['request_id'], $staff['staff_id']);
            $ins->execute();
            $ins->close();
            echo $line . " — DITERAPKAN (assignment_id {$assignment_id}).\n";
            $changes++;
        } else {
            echo $line . "\n";
            $changes++;
        }
    }
    if (!$rows) {
        echo "  Tidak ada request yang perlu di-backfill (arah B).\n";
    }
}

echo "\nRingkasan: " . $changes . " perubahan " . ($apply ? "diterapkan" : "(akan diterapkan)") . ", " . $conflicts . " konflik dilewati.\n";
$db->close();
exit($conflicts > 0 ? 0 : 0);
