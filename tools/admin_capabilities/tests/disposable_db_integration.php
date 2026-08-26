<?php
if (PHP_SAPI !== 'cli') exit('No direct script access allowed');
$database = getenv('DOCLINC_ADMIN_SCHEMA_DB_NAME');
if (!is_string($database) || !preg_match('/\Adoclinc_admin_capability_test_[a-z0-9_]+\z/', strtolower($database))) { fwrite(STDERR, "ADMIN_DB_RESULT=BLOCKED\n"); exit(2); }
mysqli_report(MYSQLI_REPORT_OFF); $db = new mysqli(getenv('DOCLINC_ADMIN_SCHEMA_DB_HOST') ?: '127.0.0.1', getenv('DOCLINC_ADMIN_SCHEMA_DB_USER'), getenv('DOCLINC_ADMIN_SCHEMA_DB_PASSWORD'), $database, (int)(getenv('DOCLINC_ADMIN_SCHEMA_DB_PORT') ?: 3306));
$passed=0;$failed=0;function admin_db_expect($ok,$label){global $passed,$failed;echo ($ok?'PASS ':'FAIL ').$label."\n";if($ok)$passed++;else$failed++;}
$db->query("INSERT INTO admin_user_capabilities (user_id,capability_code,granted_by_user_id,granted_at) VALUES (1,'super_admin',1,NOW()) ON DUPLICATE KEY UPDATE user_id=user_id"); admin_db_expect($db->affected_rows===1,'capability_grant');
$db->query("INSERT INTO admin_user_capabilities (user_id,capability_code,granted_by_user_id,granted_at) VALUES (1,'super_admin',1,NOW()) ON DUPLICATE KEY UPDATE user_id=user_id"); admin_db_expect($db->affected_rows===0,'duplicate_grant_idempotent');
$db->query("INSERT INTO clinical_access_audit (actor_user_id,record_id,request_id,patient_user_id,puskesmas_code,access_kind,audit_session_hash,first_access_in_session,reason_code,reason_text,ip_address,user_agent,created_at) VALUES (1,9,90,900,'PKM01','record_detail',REPEAT('a',64),1,'quality_review',NULL,'127.0.0.1','test',NOW())"); admin_db_expect($db->affected_rows===1,'clinical_access_metadata_row');
$row=$db->query("SELECT diagnosis,anamnesis,treatment,recommendations FROM clinical_access_audit LIMIT 1"); admin_db_expect($row===false,'clinical_content_columns_absent');
$db->begin_transaction();$db->query("DELETE FROM admin_user_capabilities WHERE user_id=1 AND capability_code='super_admin'");$db->rollback();$count=(int)$db->query("SELECT COUNT(*) c FROM admin_user_capabilities WHERE user_id=1 AND capability_code='super_admin'")->fetch_assoc()['c'];admin_db_expect($count===1,'transaction_rollback_preserves_capability');
echo "ADMIN_DB_PASSED={$passed}\nADMIN_DB_FAILED={$failed}\n";exit($failed?1:0);
