<?php
if (PHP_SAPI !== 'cli') exit('No direct script access allowed');
$dbName = getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_NAME');
if (!is_string($dbName) || !preg_match('/\Adoclinc_nakes_placement_test_[a-z0-9_]+\z/', strtolower($dbName))) { fwrite(STDERR, "DISPOSABLE_DB_RESULT=BLOCKED\n"); exit(2); }
mysqli_report(MYSQLI_REPORT_OFF);
$db = new mysqli(getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_HOST') ?: '127.0.0.1', getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_USER'), getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_PASSWORD'), $dbName, (int)(getenv('DOCLINC_NAKES_PLACEMENT_SCHEMA_DB_PORT') ?: 3306));
$passed=0; $failed=0; function db_expect($ok,$label){global $passed,$failed;echo ($ok?'PASS ':'FAIL ').$label."\n";if($ok)$passed++;else$failed++;}
$db->query("INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (10,'PKM01','2026-01-01 00:00:00','active',1)"); db_expect($db->affected_rows===1,'active_placement_inserted');
$duplicate = $db->query("INSERT INTO nakes_facility_placements (staff_id,facility_code,effective_from,status,created_by_user_id) VALUES (10,'PKM02','2026-02-01 00:00:00','active',1)"); db_expect($duplicate===false,'one_active_placement_constraint');
$db->query("INSERT INTO nakes_facility_transfers (staff_id,from_placement_id,destination_facility_code,effective_at,reason,status,requested_by_user_id) VALUES (10,1,'PKM02','2026-09-01 00:00:00','uji','scheduled',1)"); db_expect($db->affected_rows===1,'scheduled_transfer_inserted');
$duplicateTransfer = $db->query("INSERT INTO nakes_facility_transfers (staff_id,from_placement_id,destination_facility_code,effective_at,reason,status,requested_by_user_id) VALUES (10,1,'PKM02','2026-10-01 00:00:00','uji2','scheduled',1)"); db_expect($duplicateTransfer===false,'one_scheduled_transfer_constraint');
$placementId=(int)$db->query("SELECT placement_id FROM nakes_facility_placements WHERE staff_id=10 ORDER BY placement_id DESC LIMIT 1")->fetch_assoc()['placement_id']; $db->begin_transaction(); $db->query("UPDATE nakes_facility_placements SET status='ended',active_staff_key=NULL WHERE placement_id={$placementId}"); $db->rollback(); $row=$db->query("SELECT status,active_staff_key FROM nakes_facility_placements WHERE placement_id={$placementId}")->fetch_assoc(); db_expect($row['status']==='active' && (int)$row['active_staff_key']===10,'rollback_preserves_placement');
echo "DISPOSABLE_DB_PASSED={$passed}\nDISPOSABLE_DB_FAILED={$failed}\n"; exit($failed?1:0);
