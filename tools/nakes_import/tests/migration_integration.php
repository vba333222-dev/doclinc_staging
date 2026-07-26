<?php

$root = dirname(__DIR__, 3);
$migration = $root . '/application/migrations/20260727000300_nakes_import_account_foundation.php';
$database = getenv('DOCLINC_NAKES_SCHEMA_DB_NAME') ?: '';
if (preg_match('/(?:^|[_-])test(?:$|[_-])/', strtolower($database)) !== 1 || !in_array(strtolower((string) getenv('DOCLINC_NAKES_DISPOSABLE_TEST')), array('1','true','yes','on'), true)) {
	fwrite(STDERR, "NAKES_MIGRATION_INTEGRATION=FAIL\nSAFE_ERROR_CODE=disposable_test_database_required\n"); exit(2);
}

function migration_run($file, array $args)
{
	$p = proc_open(array_merge(array(PHP_BINARY, $file), $args), array(1=>array('pipe','w'),2=>array('pipe','w')), $pipes, null, null, array('bypass_shell'=>true));
	if (!is_resource($p)) return array(255,'','');
	$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);return array(proc_close($p),$out,$err);
}
function migration_expect($condition,$code){if(!$condition)throw new RuntimeException($code);}
function migration_fixture(mysqli $db)
{
	$db->query('DROP TABLE IF EXISTS puskesmas_staff');$db->query('DROP TABLE IF EXISTS users');
	$db->query("CREATE TABLE users (userId int(11) NOT NULL AUTO_INCREMENT,nama varchar(100) NOT NULL,email varchar(100) NOT NULL,username varchar(100) NULL,password varchar(100) NOT NULL,role enum('admin','dokter','warga','') NOT NULL,status enum('aktif','nonaktif') NULL DEFAULT 'aktif',remark varchar(100) NULL,no_hp varchar(50) NULL,created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(userId),UNIQUE KEY(email)) ENGINE=InnoDB DEFAULT CHARSET=latin1");
	$db->query("CREATE TABLE puskesmas_staff (staff_id int(11) NOT NULL AUTO_INCREMENT,kode_pkm varchar(100) NOT NULL,nama varchar(150) NOT NULL,no_hp varchar(50) NULL,profesi varchar(100) NULL,nomor_sip varchar(100) NULL,user_id int(11) NULL,status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',created_at datetime NOT NULL,updated_at datetime NULL,created_by_user_id int(11) NULL,updated_by_user_id int(11) NULL,PRIMARY KEY(staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$existingPassword = implode('', array('Unrelated', '-', 'Existing', '!', '42'));
	$hash=password_hash($existingPassword,PASSWORD_DEFAULT);$existingPassword=null;$stmt=$db->prepare("INSERT INTO users (nama,email,username,password,role,status,remark,no_hp) VALUES ('ADMIN-TEST','admin-test@example.invalid','admin-test',?,'admin','aktif',NULL,NULL)");$stmt->bind_param('s',$hash);$stmt->execute();$stmt->close();
}

function migration_writer(mysqli $db, $database)
{
	$user = (string) getenv('DOCLINC_NAKES_SCHEMA_DB_USER');
	$password = getenv('DOCLINC_NAKES_SCHEMA_DB_PASSWORD');
	if (preg_match('/\A[a-zA-Z0-9_]+\z/', $user) !== 1 || !is_string($password) || $password === '') throw new RuntimeException('migration_writer_fixture');
	$escaped = $db->real_escape_string($password);
	$db->query("DROP USER IF EXISTS `$user`@'%'");
	$db->query("CREATE USER `$user`@'%' IDENTIFIED BY '$escaped'");
	$db->query("GRANT SELECT, ALTER ON `$database`.`users` TO `$user`@'%'");
	$db->query("GRANT SELECT, ALTER ON `$database`.`puskesmas_staff` TO `$user`@'%'");
}

$stage='connect';
try{
	mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
	$db=new mysqli(getenv('DOCLINC_NAKES_SCHEMA_DB_HOST')?:'localhost',getenv('DOCLINC_NAKES_TEST_ADMIN_DB_USER')?:'root',getenv('DOCLINC_NAKES_TEST_ADMIN_DB_PASSWORD')?:'',$database,(int)(getenv('DOCLINC_NAKES_SCHEMA_DB_PORT')?:3306));$db->set_charset('utf8mb4');
	migration_fixture($db);migration_writer($db,$database);$before=$db->query('SELECT userId,nama,email,username,password,role,status,remark,no_hp FROM users')->fetch_all(MYSQLI_ASSOC);
	$stage='plan';list($code,$out)=migration_run($migration,array());migration_expect($code===0&&strpos($out,'DDL_EXECUTED=false')!==false&&!$db->query("SHOW COLUMNS FROM users LIKE 'must_change_password'")->fetch_assoc(),'plan_zero_write');
	$args=array('--apply','--confirm-database='.$database,'--environment=uat','--backup-reference=disposable-test-backup');
	$stage='apply';list($code,$out,$err)=migration_run($migration,$args);if($code!==0)fwrite(STDERR,$err);migration_expect($code===0&&strpos($out,'DDL_EXECUTED=true')!==false,'apply');
	$after=$db->query('SELECT userId,nama,email,username,password,role,status,remark,no_hp FROM users')->fetch_all(MYSQLI_ASSOC);migration_expect($before===$after,'existing_rows');
	$row=$db->query('SELECT must_change_password,password_changed_at FROM users')->fetch_assoc();migration_expect((int)$row['must_change_password']===0&&$row['password_changed_at']===null,'defaults');
	$stage='idempotency';list($code,$out)=migration_run($migration,$args);migration_expect($code===0&&strpos($out,'ALREADY_APPLIED=true')!==false&&strpos($out,'DDL_EXECUTED=false')!==false,'idempotency');
	$stage='partial';migration_fixture($db);migration_writer($db,$database);$db->query('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status');list($code,$out,$err)=migration_run($migration,$args);migration_expect($code!==0&&strpos($err,'SAFE_ERROR_CODE=partial_schema_detected')!==false&&!$db->query("SHOW COLUMNS FROM puskesmas_staff LIKE 'penugasan'")->fetch_assoc(),'partial');
	$stage='excessive_grant';migration_fixture($db);migration_writer($db,$database);$user=(string)getenv('DOCLINC_NAKES_SCHEMA_DB_USER');$db->query("GRANT UPDATE ON `$database`.`users` TO `$user`@'%'");list($code,$out,$err)=migration_run($migration,$args);migration_expect($code!==0&&strpos($err,'SAFE_ERROR_CODE=schema_writer_grants_excessive')!==false,'excessive_grant');
	echo "NAKES_MIGRATION_INTEGRATION=PASS\nMIGRATION_PLAN_ZERO_WRITE=PASS\nMIGRATION_APPLY=PASS\nMIGRATION_IDEMPOTENCY=PASS\nMIGRATION_SCHEMA_SIGNATURE=PASS\nMIGRATION_PARTIAL_SCHEMA_NEGATIVE=PASS\nMIGRATION_EXISTING_ROWS_UNCHANGED=PASS\nMIGRATION_WRITER_GRANTS=PASS\nEXISTING_PASSWORDS_UNCHANGED=PASS\n";
}catch(Throwable $e){fwrite(STDERR,"NAKES_MIGRATION_INTEGRATION=FAIL\nSAFE_ERROR_CODE=migration_{$stage}_failed\n");exit(1);}finally{if(isset($db)&&$db instanceof mysqli)$db->close();}
