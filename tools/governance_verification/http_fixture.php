<?php
declare(strict_types=1);

function fixture_db(): mysqli
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASS'), (string)getenv('DB_NAME'));
    if ($db->connect_errno) {
        throw new RuntimeException('fixture_db_connect_failed:' . $db->connect_errno . ':' . $db->connect_error);
    }
    $db->set_charset('utf8mb4');
    return $db;
}

function fixture_exec(mysqli $db, string $sql, array $params = []): void
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('fixture_prepare_failed');
    if ($params) {
        $types = '';
        foreach ($params as $value) $types .= is_int($value) ? 'i' : 's';
        $refs = [$types];
        foreach ($params as $key => $value) $refs[] = &$params[$key];
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) throw new RuntimeException('fixture_bind_failed');
    }
    if (!$stmt->execute()) throw new RuntimeException('fixture_execute_failed:' . $stmt->errno);
    $stmt->close();
}

function insert_request_fixture(mysqli $db, int $requestId, int $ownerUserId, ?string $field, ?int $fieldOwner): void
{
    fixture_exec($db, 'INSERT INTO requests (request_id,user_id,request_description,request_status,date,location,assigned_puskesmas_code,assigned_puskesmas_name,visit_status) VALUES (?,?,?,?,?,?,?,?,?)', [$requestId, 9903, 'synthetic governance request', 'Accepted', '2026-01-01 00:00:00', 'Synthetic fixture location', 'PKM-SOURCE', 'Synthetic Source', 'not_started']);
        if ($field !== null) {
            if (!in_array($field, ['responsible_doctor_user_id', 'visit_performer_user_id', 'assigned_nakes_user_id'], true)) throw new RuntimeException('fixture_owner_field_invalid');
            fixture_exec($db, "UPDATE requests SET {$field}=? WHERE request_id=?", [$fieldOwner ?? $ownerUserId, $requestId]);
        }
        $row = $db->query('SELECT request_id,request_status,location,responsible_doctor_user_id,visit_performer_user_id,assigned_nakes_user_id FROM requests WHERE request_id=' . $requestId)->fetch_assoc();
        if (!$row || $row['request_status'] !== 'Accepted' || trim((string)$row['location']) === '') throw new RuntimeException('fixture_request_validation_failed');
        if ($field !== null && (int)$row[$field] !== (int)($fieldOwner ?? $ownerUserId)) throw new RuntimeException('fixture_owner_validation_failed');
}

function create_request_fixture(mysqli $db, int $requestId, int $ownerUserId, ?string $field, ?int $fieldOwner): void
{
    $db->begin_transaction();
    try {
        insert_request_fixture($db, $requestId, $ownerUserId, $field, $fieldOwner);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function create_assignment_blocker(mysqli $db, int $requestId, int $staffId, string $kind): void
{
    $db->begin_transaction();
    try {
        insert_request_fixture($db, $requestId, $staffId, null, null);
        if ($kind === 'staff') {
            fixture_exec($db, 'INSERT INTO request_staff_assignments (request_id,staff_id,kode_pkm,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,?)', [$requestId, $staffId, 'PKM-SOURCE', 9901, 'aktif', '2026-01-01 00:00:00']);
        } elseif ($kind === 'responsible') {
            fixture_exec($db, 'INSERT INTO request_responsible_doctor_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,?)', [$requestId, $staffId, $staffId, 9901, 'aktif', '2026-01-01 00:00:00']);
        } elseif ($kind === 'performer') {
            fixture_exec($db, 'INSERT INTO request_visit_performer_assignments (request_id,staff_id,user_id,assigned_by_user_id,status,assigned_at) VALUES (?,?,?,?,?,?)', [$requestId, $staffId, $staffId, 9901, 'aktif', '2026-01-01 00:00:00']);
        } else {
            throw new RuntimeException('fixture_assignment_kind_invalid');
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function create_nakes_fixture(mysqli $db, int $staffId): void
{
    $db->begin_transaction();
    try {
        $syntheticPasswordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        fixture_exec($db, 'INSERT INTO users (userId,nama,email,username,password,role,status,must_change_password,remark) VALUES (?,?,?,?,?,?,?,?,?)', [$staffId, 'Synthetic Nakes ' . $staffId, 'nakes' . $staffId . '@runner.invalid', 'nakes' . $staffId . '@runner.invalid', $syntheticPasswordHash, 'dokter', 'aktif', 0, 'PKM-SOURCE']);
        fixture_exec($db, 'INSERT INTO puskesmas_staff (staff_id,kode_pkm,user_id,nama,profesi,status) VALUES (?,?,?,?,?,?)', [$staffId, 'PKM-SOURCE', $staffId, 'Synthetic Nakes', 'Perawat', 'aktif']);
        fixture_exec($db, 'INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (?,?,?,?,?)', [$staffId, 'PKM-SOURCE', '2026-01-01', 'active', 9901]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function cleanup_request_fixture(mysqli $db, int $requestId): void
{
    $db->begin_transaction();
    try {
        foreach (array('request_staff_assignments', 'request_responsible_doctor_assignments', 'request_visit_performer_assignments') as $table) {
            if ($db->query("SHOW TABLES LIKE '" . $table . "'")->num_rows > 0) fixture_exec($db, "DELETE FROM {$table} WHERE request_id=?", [$requestId]);
        }
        fixture_exec($db, 'DELETE FROM requests WHERE request_id=?', [$requestId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

if (PHP_SAPI === 'cli') {
    $mode = $argv[1] ?? '';
    $db = fixture_db();
    try {
        if ($mode === 'base') create_request_fixture($db, (int)($argv[2] ?? 0), 9902, null, null);
        elseif ($mode === 'owner') { $owner = (int)($argv[4] ?? 9902); create_request_fixture($db, (int)($argv[2] ?? 0), $owner, (string)($argv[3] ?? ''), $owner); }
        elseif ($mode === 'assignment') create_assignment_blocker($db, (int)($argv[2] ?? 0), (int)($argv[3] ?? 0), (string)($argv[4] ?? ''));
        elseif ($mode === 'nakes') create_nakes_fixture($db, (int)($argv[2] ?? 0));
        elseif ($mode === 'cleanup') cleanup_request_fixture($db, (int)($argv[2] ?? 0));
        elseif ($mode === 'concurrency_due') {
            $staff=(int)($argv[2]??0); $dest=(string)($argv[3]??'PKM-DEST'); $actor=(int)($argv[4]??9901);
            create_nakes_fixture($db,$staff);
            $row=$db->query('SELECT placement_id FROM nakes_facility_placements WHERE staff_id='.$staff." AND status='active' ORDER BY placement_id DESC LIMIT 1")->fetch_assoc();
            if(!$row) throw new RuntimeException('fixture_due_placement_missing');
            fixture_exec($db,'INSERT INTO nakes_facility_transfers (staff_id,from_placement_id,destination_facility_code,effective_at,reason,status,requested_by_user_id,requested_at) VALUES (?,?,?,?,?,?,?,?)',[$staff,(int)$row['placement_id'],$dest,'2026-01-01 00:00:00','concurrency','scheduled',$actor,'2026-01-01 00:00:00']);
            $transferId=(int)$db->insert_id; $state=$db->query('SELECT t.status AS transfer_status,p.placement_id,p.status AS placement_status FROM nakes_facility_transfers t LEFT JOIN nakes_facility_placements p ON p.placement_id=t.from_placement_id WHERE t.transfer_id='.$transferId)->fetch_assoc(); $meta=$db->query('SELECT DATABASE() AS db_name, @@server_id AS server_id')->fetch_assoc();
            echo json_encode(['transfer_id'=>$transferId,'staff_id'=>$staff,'placement_id'=>(int)($state['placement_id']??0),'placement_status'=>(string)($state['placement_status']??''),'transfer_status'=>(string)($state['transfer_status']??''),'destination_facility'=>$dest,'actor_user_id'=>$actor,'now'=>date('Y-m-d H:i:s'),'database'=>$meta['db_name']??'','server_id'=>$meta['server_id']??''])."\n";
        }
        elseif ($mode === 'concurrency_schedule') {
            $staff=(int)($argv[2]??0); create_nakes_fixture($db,$staff); $meta=$db->query('SELECT DATABASE() AS db_name, @@server_id AS server_id')->fetch_assoc(); echo json_encode(['staff_id'=>$staff,'destination_facility'=>(string)($argv[3]??'PKM-DEST'),'effective_at'=>'2031-01-01 00:00:00','actor_user_id'=>(int)($argv[4]??9901),'database'=>$meta['db_name']??'','server_id'=>$meta['server_id']??''])."\n";
        }
        elseif ($mode === 'concurrency_due_verify') {
            $transfer=(int)($argv[2]??0); $staff=(int)($argv[3]??0); $row=$db->query('SELECT t.status AS transfer_status,t.effective_at,t.destination_facility_code,p.placement_id,p.status AS placement_status FROM nakes_facility_transfers t LEFT JOIN nakes_facility_placements p ON p.placement_id=t.from_placement_id WHERE t.transfer_id='.$transfer.' AND t.staff_id='.$staff.' LIMIT 1')->fetch_assoc(); $meta=$db->query('SELECT DATABASE() AS db_name, @@server_id AS server_id')->fetch_assoc(); $active=(int)$db->query("SELECT COUNT(*) AS c FROM nakes_facility_placements WHERE staff_id=$staff AND status='active'")->fetch_assoc()['c']; echo json_encode(['database'=>$meta['db_name']??'','server_id'=>$meta['server_id']??'','transfer_exists'=>!empty($row),'transfer_status'=>$row['transfer_status']??'','effective_at'=>$row['effective_at']??'','placement_id'=>(int)($row['placement_id']??0),'placement_status'=>$row['placement_status']??'','active_placement_count'=>$active])."\n";
        }
        elseif ($mode === 'concurrency_schedule_verify') {
            $staff=(int)($argv[2]??0); $meta=$db->query('SELECT DATABASE() AS db_name, @@server_id AS server_id')->fetch_assoc(); $active=(int)$db->query("SELECT COUNT(*) AS c FROM nakes_facility_placements WHERE staff_id=$staff AND status='active'")->fetch_assoc()['c']; $scheduled=(int)$db->query("SELECT COUNT(*) AS c FROM nakes_facility_transfers WHERE staff_id=$staff AND status='scheduled'")->fetch_assoc()['c']; echo json_encode(['database'=>$meta['db_name']??'','server_id'=>$meta['server_id']??'','active_placement_count'=>$active,'scheduled_transfer_count'=>$scheduled])."\n";
        }
        elseif ($mode === 'concurrency_due_final') {
            $transfer=(int)($argv[2]??0); $staff=(int)($argv[3]??0); $row=$db->query('SELECT status,completed_at FROM nakes_facility_transfers WHERE transfer_id='.$transfer.' AND staff_id='.$staff.' LIMIT 1')->fetch_assoc(); $active=(int)$db->query("SELECT COUNT(*) AS c FROM nakes_facility_placements WHERE staff_id=$staff AND status='active'")->fetch_assoc()['c']; $ended=(int)$db->query("SELECT COUNT(*) AS c FROM nakes_facility_placements WHERE staff_id=$staff AND status='ended'")->fetch_assoc()['c']; $auditRow=$db->query("SELECT created_at FROM audit_logs WHERE action='nakes_transfer_completed' AND entity_id='".$staff."' ORDER BY id DESC LIMIT 1")->fetch_assoc(); $audit=(int)$db->query("SELECT COUNT(*) AS c FROM audit_logs WHERE action='nakes_transfer_completed' AND entity_id='".$staff."'")->fetch_assoc()['c']; $proj=$db->query("SELECT ps.kode_pkm,u.remark FROM puskesmas_staff ps JOIN users u ON u.userId=ps.user_id WHERE ps.staff_id=$staff LIMIT 1")->fetch_assoc(); echo json_encode(['transfer_status'=>$row['status']??'','completed'=>!empty($row['completed_at']),'active_placements'=>$active,'ended_placements'=>$ended,'completion_audits'=>$audit,'completion_audit_created_at'=>$auditRow['created_at']??'','staff_projection'=>$proj['kode_pkm']??'','user_projection'=>$proj['remark']??''])."\n";
        }
        else throw new RuntimeException('fixture_mode_invalid');
        if (!in_array($mode, array('concurrency_due', 'concurrency_schedule'), true)) echo "FIXTURE_CREATED=YES\n";
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}
