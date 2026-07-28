<?php

final class CareOperationsFixture
{
	public static function createSchema(mysqli $db, $staff_id_type = 'int(10) unsigned')
	{
		if (!in_array($staff_id_type, array('int(10) unsigned', 'int(10)'), true)) {
			throw new InvalidArgumentException('fixture_staff_id_type_invalid');
		}

		$statements = array(
			"CREATE TABLE users (
				userId int(11) NOT NULL AUTO_INCREMENT,
				nama varchar(100) NOT NULL,
				email varchar(100) NOT NULL,
				username varchar(100) NULL,
				password varchar(100) NOT NULL,
				role enum('admin','dokter','warga','') NOT NULL,
				status enum('aktif','nonaktif') NULL DEFAULT 'aktif',
				must_change_password tinyint(1) NOT NULL DEFAULT 0,
				password_changed_at datetime NULL,
				remark varchar(100) NULL,
				PRIMARY KEY (userId),
				UNIQUE KEY uq_users_email (email),
				UNIQUE KEY uq_users_username (username)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE m_puskesmas (
				kode_pkm varchar(100) NOT NULL,
				nama_puskesmas varchar(150) NOT NULL,
				status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
				PRIMARY KEY (kode_pkm)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE puskesmas_staff (
				staff_id {$staff_id_type} NOT NULL AUTO_INCREMENT,
				kode_pkm varchar(100) NOT NULL,
				user_id int(11) NULL,
				nama varchar(150) NOT NULL,
				no_hp varchar(50) NULL,
				profesi varchar(100) NULL,
				penugasan varchar(150) NULL,
				nomor_sip varchar(100) NULL,
				status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
				created_at datetime NOT NULL,
				updated_at datetime NULL,
				created_by_user_id int(11) NULL,
				PRIMARY KEY (staff_id),
				KEY idx_staff_user (user_id),
				KEY idx_staff_puskesmas (kode_pkm, status)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE requests (
				request_id int(11) NOT NULL AUTO_INCREMENT,
				user_id int(11) NOT NULL,
				dokter_id int(11) NULL,
				request_status enum('Pending','Accepted','Completed','Cancelled') NULL DEFAULT 'Pending',
				assigned_puskesmas_code varchar(100) NULL,
				assigned_nakes_user_id int(11) NULL,
				accepted_by_user_id int(11) NULL,
				visit_status varchar(30) NULL DEFAULT 'not_started',
				PRIMARY KEY (request_id),
				KEY idx_requests_owner (user_id),
				KEY idx_requests_nakes (assigned_nakes_user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE request_staff_assignments (
				assignment_id int(11) NOT NULL AUTO_INCREMENT,
				request_id int(11) NOT NULL,
				staff_id int(11) NOT NULL,
				kode_pkm varchar(100) NOT NULL,
				assigned_by_user_id int(11) NOT NULL,
				status varchar(20) NOT NULL,
				assigned_at datetime NOT NULL,
				PRIMARY KEY (assignment_id),
				KEY idx_assignment_request (request_id, status)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE medicalrecords (
				record_id int(11) NOT NULL AUTO_INCREMENT,
				request_id int(11) NOT NULL,
				diagnosis text NULL,
				treatment text NULL,
				recommendations text NULL,
				PRIMARY KEY (record_id),
				KEY idx_medicalrecord_request (request_id),
				CONSTRAINT fk_fixture_medicalrecord_request FOREIGN KEY (request_id)
					REFERENCES requests (request_id) ON UPDATE RESTRICT ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE notifications (
				notification_id int(11) NOT NULL AUTO_INCREMENT,
				recipient_user_id int(11) NULL,
				recipient_role varchar(32) NULL,
				recipient_puskesmas_code varchar(32) NULL,
				actor_user_id int(11) NULL,
				event_type varchar(64) NOT NULL,
				entity_type varchar(64) NOT NULL,
				entity_id varchar(64) NOT NULL,
				title varchar(160) NOT NULL,
				message text NULL,
				is_read tinyint(1) NOT NULL DEFAULT 0,
				read_at datetime NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (notification_id),
				KEY idx_notifications_user_read_created (recipient_user_id, is_read, created_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
			"CREATE TABLE clinical_suggestion_import_batches (
				suggestion_import_batch_id bigint unsigned NOT NULL AUTO_INCREMENT,
				batch_reference varchar(128) NOT NULL,
				PRIMARY KEY (suggestion_import_batch_id),
				UNIQUE KEY uq_fixture_batch_reference (batch_reference)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
			"CREATE TABLE clinical_suggestion_terms (
				suggestion_term_id bigint unsigned NOT NULL AUTO_INCREMENT,
				suggestion_import_batch_id bigint unsigned NOT NULL,
				reference_key varchar(128) NOT NULL,
				PRIMARY KEY (suggestion_term_id),
				UNIQUE KEY uq_fixture_term_reference (reference_key),
				CONSTRAINT fk_fixture_term_batch FOREIGN KEY (suggestion_import_batch_id)
					REFERENCES clinical_suggestion_import_batches (suggestion_import_batch_id)
					ON UPDATE RESTRICT ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
			"CREATE TABLE clinical_suggestion_aliases (
				suggestion_alias_id bigint unsigned NOT NULL AUTO_INCREMENT,
				suggestion_term_id bigint unsigned NOT NULL,
				alias_key varchar(128) NOT NULL,
				PRIMARY KEY (suggestion_alias_id),
				CONSTRAINT fk_fixture_alias_term FOREIGN KEY (suggestion_term_id)
					REFERENCES clinical_suggestion_terms (suggestion_term_id)
					ON UPDATE RESTRICT ON DELETE RESTRICT
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
			"CREATE TABLE clinical_schema_migrations (
				migration_id varchar(128) NOT NULL,
				state varchar(20) NOT NULL,
				PRIMARY KEY (migration_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
		);

		foreach ($statements as $statement) {
			$db->query($statement);
		}
	}

	public static function seedBaseline(mysqli $db)
	{
		for ($index = 1; $index <= 9; $index++) {
			$code = sprintf('PKM%02d', $index);
			$name = sprintf('UNIT-%02d', $index);
			$stmt = $db->prepare("INSERT INTO m_puskesmas (kode_pkm,nama_puskesmas,status) VALUES (?,?,'aktif')");
			$stmt->bind_param('ss', $code, $name);
			$stmt->execute();
			$stmt->close();
		}

		self::insertUser($db, 1, 'admin', 'aktif', null, 'ADMIN');
		for ($index = 1; $index <= 9; $index++) {
			self::insertUser($db, 1 + $index, 'dokter', 'aktif', sprintf('PKM%02d', $index), 'CENTER');
		}
		for ($index = 1; $index <= 25; $index++) {
			$user_id = 10 + $index;
			$code = sprintf('PKM%02d', (($index - 1) % 9) + 1);
			self::insertUser($db, $user_id, 'dokter', 'aktif', $code, 'PERSONAL');
			$stmt = $db->prepare("INSERT INTO puskesmas_staff
				(staff_id,kode_pkm,user_id,nama,profesi,status,created_at,created_by_user_id)
				VALUES (?,?,?,'SYNTHETIC','Dokter','aktif',CURRENT_TIMESTAMP,1)");
			$stmt->bind_param('isi', $index, $code, $user_id);
			$stmt->execute();
			$stmt->close();
		}
		$stmt = $db->prepare("INSERT INTO puskesmas_staff
			(staff_id,kode_pkm,user_id,nama,profesi,status,created_at,created_by_user_id)
			VALUES (31,?,NULL,'SYNTHETIC','Dokter','nonaktif',CURRENT_TIMESTAMP,1)");
		$inactive_code = 'PKM03';
		$stmt->bind_param('s', $inactive_code);
		$stmt->execute();
		$stmt->close();

		for ($index = 1; $index <= 16; $index++) {
			self::insertUser($db, 35 + $index, 'warga', 'aktif', null, 'WARGA');
		}

		for ($index = 1; $index <= 7; $index++) {
			$request_id = $index;
			$owner_id = 35 + $index;
			$nakes_id = 10 + $index;
			$staff_id = $index;
			$code = sprintf('PKM%02d', (($index - 1) % 9) + 1);
			$stmt = $db->prepare("INSERT INTO requests
				(request_id,user_id,dokter_id,request_status,assigned_puskesmas_code,assigned_nakes_user_id,accepted_by_user_id,visit_status)
				VALUES (?,?,NULL,'Completed',?,?,?,'completed')");
			$stmt->bind_param('iisii', $request_id, $owner_id, $code, $nakes_id, $nakes_id);
			$stmt->execute();
			$stmt->close();

			$stmt = $db->prepare("INSERT INTO request_staff_assignments
				(assignment_id,request_id,staff_id,kode_pkm,assigned_by_user_id,status,assigned_at)
				VALUES (?,?,?,?,2,'aktif',CURRENT_TIMESTAMP)");
			$stmt->bind_param('iiis', $request_id, $request_id, $staff_id, $code);
			$stmt->execute();
			$stmt->close();

			$stmt = $db->prepare("INSERT INTO medicalrecords (record_id,request_id,diagnosis)
				VALUES (?,?,NULL)");
			$stmt->bind_param('ii', $request_id, $request_id);
			$stmt->execute();
			$stmt->close();
		}

		for ($index = 1; $index <= 26; $index++) {
			$recipient_id = 10 + (($index - 1) % 25) + 1;
			$entity_id = (string) ((($index - 1) % 7) + 1);
			$stmt = $db->prepare("INSERT INTO notifications
				(recipient_user_id,recipient_role,recipient_puskesmas_code,actor_user_id,event_type,entity_type,entity_id,title,message,is_read)
				VALUES (?,'dokter','PKM01',2,'fixture_event','request',?,'SYNTHETIC',NULL,0)");
			$stmt->bind_param('is', $recipient_id, $entity_id);
			$stmt->execute();
			$stmt->close();
		}

		$db->query("INSERT INTO clinical_suggestion_import_batches (suggestion_import_batch_id,batch_reference) VALUES (1,'SYNTHETIC-BATCH')");
		$stmt = $db->prepare('INSERT INTO clinical_suggestion_terms (suggestion_import_batch_id,reference_key) VALUES (1,?)');
		for ($index = 1; $index <= 11450; $index++) {
			$key = sprintf('TERM-%05d', $index);
			$stmt->bind_param('s', $key);
			$stmt->execute();
		}
		$stmt->close();
		$stmt = $db->prepare('INSERT INTO clinical_suggestion_aliases (suggestion_term_id,alias_key) VALUES (?,?)');
		for ($index = 1; $index <= 701; $index++) {
			$key = sprintf('ALIAS-%04d', $index);
			$stmt->bind_param('is', $index, $key);
			$stmt->execute();
		}
		$stmt->close();
		$stmt = $db->prepare("INSERT INTO clinical_schema_migrations (migration_id,state) VALUES (?,'applied')");
		for ($index = 1; $index <= 6; $index++) {
			$migration_id = sprintf('SYNTHETIC-MIGRATION-%02d', $index);
			$stmt->bind_param('s', $migration_id);
			$stmt->execute();
		}
		$stmt->close();
	}

	public static function snapshot(mysqli $db)
	{
		$counts = array();
		foreach (array(
			'm_puskesmas', 'users', 'puskesmas_staff', 'requests',
			'request_staff_assignments', 'medicalrecords', 'notifications',
			'clinical_suggestion_import_batches', 'clinical_suggestion_terms',
			'clinical_suggestion_aliases', 'clinical_schema_migrations',
		) as $table) {
			$counts[$table] = (int) $db->query('SELECT COUNT(1) AS total FROM `' . $table . '`')->fetch_assoc()['total'];
		}

		$passwords = array();
		$result = $db->query('SELECT userId,password FROM users ORDER BY userId');
		while ($row = $result->fetch_assoc()) {
			$passwords[] = ((int) $row['userId']) . ':' . (string) $row['password'];
		}

		return array(
			'counts' => $counts,
			'password_digest' => hash('sha256', implode('|', $passwords)),
			'inactive_staff' => $db->query("SELECT staff_id,kode_pkm,user_id,status FROM puskesmas_staff WHERE staff_id=31")->fetch_assoc(),
			'command_center_count' => (int) $db->query("SELECT COUNT(1) AS total FROM users u
				WHERE u.role='dokter' AND u.status='aktif' AND u.userId IN (
					SELECT MIN(c.userId) FROM users c
					LEFT JOIN puskesmas_staff ps ON ps.user_id=c.userId
					WHERE c.role='dokter' AND c.status='aktif' AND c.remark IN (SELECT kode_pkm FROM m_puskesmas WHERE status='aktif')
					AND ps.staff_id IS NULL GROUP BY c.remark
				)")->fetch_assoc()['total'],
			'personal_presence_target_count' => (int) $db->query("SELECT COUNT(1) AS total FROM puskesmas_staff ps
				INNER JOIN users u ON u.userId=ps.user_id
				WHERE ps.status='aktif' AND u.status='aktif' AND u.role='dokter'")->fetch_assoc()['total'],
		);
	}

	private static function insertUser(mysqli $db, $user_id, $role, $status, $remark, $kind)
	{
		$name = $kind . '-' . sprintf('%03d', $user_id);
		$username = strtolower($kind) . '.' . sprintf('%03d', $user_id);
		$email = $username . '@invalid.example';
		$password = hash('sha256', random_bytes(32));
		$stmt = $db->prepare('INSERT INTO users
			(userId,nama,email,username,password,role,status,must_change_password,password_changed_at,remark)
			VALUES (?,?,?,?,?,?,?,0,NULL,?)');
		$stmt->bind_param('isssssss', $user_id, $name, $email, $username, $password, $role, $status, $remark);
		$stmt->execute();
		$stmt->close();
		$password = null;
	}
}
