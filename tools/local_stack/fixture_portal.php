<?php
/**
 * Fixture sisi portal (warga, nakes, koordinator puskesmas) untuk stack lokal.
 *
 * Harness panel admin (`tools/admin_panel/local/bootstrap.php`) sengaja membuat
 * skema yang lebih ramping daripada produksi: cukup untuk seluruh halaman
 * panel. Portal warga & nakes di akar repo membaca kolom yang tidak dipakai
 * panel, dan tanpa kolom itu halamannya berhenti dengan HTTP 500 tanpa badan -
 * pesan yang menyesatkan, seolah stack-nya rusak:
 *
 *   patients                                     registri pasien ber-NIK
 *   medicalrecords.patient_id                    relasi rekam -> pasien
 *   medicalrecords.clinical_finalized_at         waktu selesai kanonik
 *   medicalrecords.clinical_finalized_by_user_id
 *   request_events.domain_event_key              idempotensi peristiwa klinis
 *   consultation_messages.*                      utas chat + lampiran
 *   call_sessions.*                              sesi panggilan LiveKit
 *
 * `bootstrap.php` membuat `consultation_messages` dan `call_sessions` dalam
 * bentuk yang jauh lebih ramping daripada migrasinya. Halaman
 * `/chat?request_id=...` memilih `deleted_at` tanpa memeriksa keberadaan kolom,
 * jadi tanpa kolom itu jawabannya HTTP 500 tanpa badan - tampak seperti stack
 * rusak padahal hanya skema yang kurang. Skrip ini juga menambahkan SATU
 * permintaan berstatus `Accepted` (lihat bagian 2b): chat hanya bisa dibuka
 * untuk permintaan yang sudah diterima, dan tombol kirim/panggilan hanya hidup
 * pada status itu.
 *
 * Kenapa DDL-nya disalin, bukan menjalankan migrasinya:
 * `20261022000100_patients_registry_foundation.php` memang boleh diarahkan ke
 * database mana pun, tetapi indeks yang dibuatnya menyebut
 * `medicalrecords.clinical_finalized_at` - kolom yang baru lahir dari
 * `20260826000100_visit_clinical_workflow_foundation.php`. Migrasi terakhir itu
 * menolak berjalan di luar database sekali pakai `doclinc_visit_test` dan
 * menuntut tabel yang tidak ada di fixture ini. Jadi salinan DDL di bawah
 * adalah pilihan yang disengaja, sama seperti `fixture_clinical.php`, dan
 * bentuk hasilnya diperiksa ulang alih-alih diasumsikan.
 *
 * Selain skema, skrip ini membuat akun yang tidak ada di `bootstrap.php`:
 * koordinator puskesmas. Aturan aplikasi (`doclinc_dokter_identity_context`)
 * menetapkan command center sebagai akun dokter ber-`remark` = kode puskesmas
 * dengan userId TERKECIL yang TIDAK tertaut baris `puskesmas_staff`. Bootstrap
 * hanya membuat akun staf (userId 101-109, semuanya tertaut), sehingga tanpa
 * akun tambahan setiap akun nakes berakhir "identitas belum valid" dan peran
 * puskesmas tidak bisa diuji sama sekali. `kord1..kord9` (userId 91-99) mengisi
 * posisi itu; akun staf tidak diubah dan tetap menjadi akun personal.
 *
 * Pemakaian (di dalam kontainer, env DB_* sudah ada di sana):
 *
 *   docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
 *     php /var/www/html/tools/local_stack/fixture_portal.php
 *
 * atau tanpa Docker, dengan PHP + MariaDB lokal:
 *
 *   ALLOW_LOCAL_FIXTURE=1 DB_HOST=127.0.0.1 DB_PORT=3307 \
 *   DB_NAME=doclinc_stage_local_test DB_USER=doclinc DB_PASS=doclinclokal \
 *     php tools/local_stack/fixture_portal.php
 *
 * Penjaga sama seperti `bootstrap.php`/`fixture_clinical.php`: host wajib
 * loopback atau nama layanan compose (`db`/`mariadb`), nama database wajib
 * memuat "stage" DAN "test", dan `ALLOW_LOCAL_FIXTURE=1` wajib disetel.
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "refuse: hanya untuk CLI\n");
	exit(2);
}

/** Nilai env dengan fallback berurutan, mengikuti gaya `bootstrap.php`. */
function portal_env($names, $fallback = '')
{
	foreach ((array) $names as $name) {
		$value = getenv($name);
		if ($value !== false && $value !== '') {
			return $value;
		}
	}
	return $fallback;
}

function portal_fail($message)
{
	fwrite(STDERR, "REFUSE: {$message}\n");
	exit(2);
}

if (getenv('ALLOW_LOCAL_FIXTURE') !== '1') {
	portal_fail('set ALLOW_LOCAL_FIXTURE=1 (penjaga supaya tidak jalan tanpa sengaja)');
}

$host = strtolower(trim((string) portal_env(array('DB_HOST', 'ADMIN_DB_HOST'), '127.0.0.1')));
$database = (string) portal_env(array('DB_NAME', 'ADMIN_DB_NAME'));
$user = (string) portal_env(array('DB_USER', 'ADMIN_DB_USER'));
$password = (string) portal_env(array('DB_PASS', 'ADMIN_DB_PASS'));
$port = (int) portal_env(array('DB_PORT', 'ADMIN_DB_PORT'), 3306);

if (!in_array($host, array('127.0.0.1', 'localhost', 'db', 'mariadb'), true)) {
	portal_fail("host database '{$host}' bukan loopback/layanan compose");
}
if ($database === '') {
	portal_fail('DB_NAME/ADMIN_DB_NAME kosong');
}
if (stripos($database, 'stage') === false || stripos($database, 'test') === false) {
	portal_fail("nama database '{$database}' wajib memuat 'stage' dan 'test'");
}
if ($user === '') {
	portal_fail('DB_USER/ADMIN_DB_USER kosong');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $password, $database, $port);
} catch (mysqli_sql_exception $e) {
	portal_fail('koneksi gagal: ' . $e->getMessage());
}
$db->set_charset('utf8mb4');

function portal_table($db, $table)
{
	$stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
	$stmt->bind_param('s', $table);
	$stmt->execute();
	$count = (int) $stmt->get_result()->fetch_row()[0];
	$stmt->close();
	return $count === 1;
}

function portal_column($db, $table, $column)
{
	$stmt = $db->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
	$stmt->bind_param('ss', $table, $column);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $row ? (string) $row['COLUMN_TYPE'] : null;
}

function portal_index($db, $table, $index)
{
	$stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
	$stmt->bind_param('ss', $table, $index);
	$stmt->execute();
	$count = (int) $stmt->get_result()->fetch_row()[0];
	$stmt->close();
	return $count > 0;
}

/**
 * Foreign key diperiksa di TABLE_CONSTRAINTS, bukan STATISTICS: MariaDB memakai
 * ulang indeks yang sudah ada (`idx_mr_patient_date`) alih-alih membuat indeks
 * bernama sama dengan constraint. Memeriksa STATISTICS membuat skrip ini
 * mencoba menambah `fk_mr_patient` untuk kedua kalinya dan berhenti dengan
 * errno 121 (nama constraint sudah dipakai).
 */
function portal_foreign_key($db, $table, $constraint)
{
	$stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=? AND CONSTRAINT_TYPE='FOREIGN KEY'");
	$stmt->bind_param('ss', $table, $constraint);
	$stmt->execute();
	$count = (int) $stmt->get_result()->fetch_row()[0];
	$stmt->close();
	return $count > 0;
}

/** `int(11)` dan `bigint unsigned` ditulis berbeda oleh MariaDB/MySQL. */
function portal_type($type)
{
	return preg_replace('/\(\d+\)/', '', strtolower(trim((string) $type)));
}

foreach (array('users', 'requests', 'medicalrecords', 'medicalrecord_diagnoses', 'request_events', 'puskesmas_staff', 'm_puskesmas') as $required) {
	if (!portal_table($db, $required)) {
		portal_fail("tabel '{$required}' belum ada - jalankan bootstrap.php --fresh lebih dulu");
	}
}

/* ---------------------------------------------------------------------------
 * 1. Skema portal
 *
 * DDL `patients` disalin dari application/migrations/
 * 20261022000100_patients_registry_foundation.php, termasuk kunci unik NIK dan
 * FK ke `users.userId`; hanya CHECK panjang NIK yang dilewati karena MariaDB
 * 10.11 sudah menegakkannya lewat CHAR(16) dan CHECK itu tidak menambah makna
 * di fixture.
 * ------------------------------------------------------------------------ */
$created_patients = false;
if (!portal_table($db, 'patients')) {
	$db->query("CREATE TABLE `patients` (
		`patient_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		`nik` CHAR(16) NOT NULL,
		`nama` VARCHAR(150) NOT NULL,
		`tgl_lahir` DATE NULL,
		`gender` ENUM('L','P') NULL,
		`no_hp` VARCHAR(50) NULL,
		`alamat` VARCHAR(255) NULL,
		`nomor_kk` CHAR(16) NULL,
		`nomor_bpjs_kis` CHAR(13) NULL,
		`primary_user_id` INT(11) NULL,
		`status` ENUM('aktif','nonaktif','digabung') NOT NULL DEFAULT 'aktif',
		`merged_into_patient_id` BIGINT UNSIGNED NULL,
		`merged_at` DATETIME(6) NULL,
		`created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
		`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
		PRIMARY KEY (`patient_id`),
		UNIQUE KEY `uq_patients_nik` (`nik`),
		KEY `idx_patients_nama` (`nama`),
		KEY `idx_patients_user` (`primary_user_id`),
		KEY `idx_patients_status` (`status`),
		CONSTRAINT `fk_patients_user` FOREIGN KEY (`primary_user_id`) REFERENCES `users` (`userId`) ON DELETE SET NULL ON UPDATE RESTRICT,
		CONSTRAINT `fk_patients_merged` FOREIGN KEY (`merged_into_patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	$created_patients = true;
}

$added_columns = array();
if (portal_column($db, 'medicalrecords', 'patient_id') === null) {
	$db->query('ALTER TABLE `medicalrecords` ADD COLUMN `patient_id` BIGINT UNSIGNED NULL AFTER `request_id`');
	$added_columns[] = 'medicalrecords.patient_id';
}
/* Dua kolom waktu ini berasal dari 20260826000100_visit_clinical_workflow_
   foundation.php. Model portal memilihnya langsung (bukan lewat column_exists),
   jadi ketiadaannya berakhir sebagai exception - bukan kolom kosong. */
if (portal_column($db, 'medicalrecords', 'clinical_finalized_at') === null) {
	$db->query('ALTER TABLE `medicalrecords` ADD COLUMN `clinical_finalized_at` DATETIME(6) NULL');
	$added_columns[] = 'medicalrecords.clinical_finalized_at';
}
if (portal_column($db, 'medicalrecords', 'clinical_finalized_by_user_id') === null) {
	$db->query('ALTER TABLE `medicalrecords` ADD COLUMN `clinical_finalized_by_user_id` INT(11) NULL');
	$added_columns[] = 'medicalrecords.clinical_finalized_by_user_id';
}
if (portal_column($db, 'request_events', 'domain_event_key') === null) {
	$db->query('ALTER TABLE `request_events` ADD COLUMN `domain_event_key` VARCHAR(191) NULL');
	$added_columns[] = 'request_events.domain_event_key';
}

$added_indexes = array();
if (!portal_index($db, 'medicalrecords', 'idx_mr_patient_date')) {
	$db->query('ALTER TABLE `medicalrecords` ADD KEY `idx_mr_patient_date` (`patient_id`, `clinical_finalized_at`, `record_id`)');
	$added_indexes[] = 'medicalrecords.idx_mr_patient_date';
}
if (!portal_foreign_key($db, 'medicalrecords', 'fk_mr_patient')) {
	$db->query('ALTER TABLE `medicalrecords` ADD CONSTRAINT `fk_mr_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE RESTRICT ON UPDATE RESTRICT');
	$added_indexes[] = 'medicalrecords.fk_mr_patient';
}
if (!portal_index($db, 'request_events', 'uq_request_events_domain_key')) {
	$db->query('ALTER TABLE `request_events` ADD UNIQUE KEY `uq_request_events_domain_key` (`domain_event_key`)');
	$added_indexes[] = 'request_events.uq_request_events_domain_key';
}

/* ---------------------------------------------------------------------------
 * 1b. Skema chat & panggilan
 *
 * Disalin dari application/migrations/20260623000200_consultation_messages.php
 * dan 20260701000100_call_sessions.php. Bentuk ramping `bootstrap.php` memakai
 * `body` (bukan `message_text`), tanpa `deleted_at`, dan `call_sessions` tanpa
 * `status`/`room_name`/`created_at` - padahal model portal memilih kolom-kolom
 * itu apa adanya lewat builder, sehingga ketiadaannya menjadi exception SQL dan
 * halaman `/chat` berakhir 500 tanpa badan.
 *
 * `body` sengaja TIDAK dihapus: isinya disalin ke `message_text` supaya pesan
 * lama tetap tampil, dan menghapus kolom warisan itu bukan urusan fixture.
 * ------------------------------------------------------------------------ */
$chat_columns = array(
	'sender_role' => "VARCHAR(32) NOT NULL DEFAULT 'warga' AFTER `sender_user_id`",
	'message_type' => "VARCHAR(32) NOT NULL DEFAULT 'text' AFTER `sender_role`",
	'message_text' => 'TEXT NULL AFTER `message_type`',
	'attachment_mime' => 'VARCHAR(120) NULL AFTER `attachment_path`',
	'attachment_original_name' => 'VARCHAR(180) NULL AFTER `attachment_mime`',
	'is_read' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `attachment_original_name`',
	'deleted_at' => 'DATETIME NULL AFTER `created_at`',
);
foreach ($chat_columns as $column => $definition) {
	if (portal_column($db, 'consultation_messages', $column) === null) {
		$db->query("ALTER TABLE `consultation_messages` ADD COLUMN `{$column}` {$definition}");
		$added_columns[] = 'consultation_messages.' . $column;
	}
}
if (portal_column($db, 'consultation_messages', 'body') !== null) {
	$db->query('UPDATE `consultation_messages` SET `message_text` = `body`
		WHERE `message_text` IS NULL AND `body` IS NOT NULL');
}

$chat_indexes = array(
	'idx_consultation_messages_request_created' => '(`request_id`, `created_at`)',
	'idx_consultation_messages_request_id' => '(`request_id`, `message_id`)',
	'idx_consultation_messages_sender_created' => '(`sender_user_id`, `created_at`)',
	'idx_consultation_messages_read' => '(`is_read`)',
	'idx_consultation_messages_deleted' => '(`deleted_at`)',
);
foreach ($chat_indexes as $index => $columns) {
	if (!portal_index($db, 'consultation_messages', $index)) {
		$db->query("ALTER TABLE `consultation_messages` ADD KEY `{$index}` {$columns}");
		$added_indexes[] = 'consultation_messages.' . $index;
	}
}

/* `created_at` ikut ditambahkan: `expire_stale_ringing_calls()` menyaring
   panggilan `ringing` yang belum punya `started_at` lewat kolom itu, dan
   migrasi produksi memang mendefinisikannya. */
$call_columns = array(
	'room_name' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER `request_id`",
	'call_type' => "ENUM('audio','video') NOT NULL DEFAULT 'video' AFTER `room_name`",
	'status' => "ENUM('ringing','answered','ended','rejected','missed','failed') NOT NULL DEFAULT 'ringing' AFTER `callee_user_id`",
	'answered_at' => 'DATETIME NULL AFTER `started_at`',
	'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `ended_at`',
	'updated_at' => 'DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
);
foreach ($call_columns as $column => $definition) {
	if (portal_column($db, 'call_sessions', $column) === null) {
		$db->query("ALTER TABLE `call_sessions` ADD COLUMN `{$column}` {$definition}");
		$added_columns[] = 'call_sessions.' . $column;
	}
}

$call_indexes = array(
	'idx_call_sessions_request_status' => '(`request_id`, `status`)',
	'idx_call_sessions_callee_status' => '(`callee_user_id`, `status`)',
	'idx_call_sessions_room_name' => '(`room_name`)',
);
foreach ($call_indexes as $index => $columns) {
	if (!portal_index($db, 'call_sessions', $index)) {
		$db->query("ALTER TABLE `call_sessions` ADD KEY `{$index}` {$columns}");
		$added_indexes[] = 'call_sessions.' . $index;
	}
}

/* ---------------------------------------------------------------------------
 * 2. Data pasien
 *
 * Sama seperti Doclinc_patients_backfill: pasien dibuat dari akun warga ber-NIK
 * 16 digit, lalu rekam medis lamanya ditautkan lewat request -> pemilik NIK.
 * Tanpa penautan itu arsip nakes menampilkan kolom NIK dan nama pasien kosong.
 * ------------------------------------------------------------------------ */
$db->query("INSERT INTO patients (nik, nama, tgl_lahir, gender, no_hp, primary_user_id, status)
	SELECT TRIM(u.nik), u.nama, u.tgl,
		CASE
			WHEN u.gender IN ('L','P') THEN u.gender
			WHEN LOWER(u.gender) IN ('laki-laki','laki laki','pria') THEN 'L'
			WHEN LOWER(u.gender) IN ('perempuan','wanita') THEN 'P'
			ELSE NULL
		END,
		u.no_hp, u.userId, 'aktif'
	FROM users u
	WHERE u.role='warga' AND TRIM(u.nik) REGEXP '^[0-9]{16}$'
	ON DUPLICATE KEY UPDATE primary_user_id = COALESCE(patients.primary_user_id, VALUES(primary_user_id))");
$patients = (int) $db->query('SELECT COUNT(*) AS total FROM patients')->fetch_assoc()['total'];

$db->query('UPDATE medicalrecords m
	JOIN requests r ON r.request_id = m.request_id
	JOIN users u ON u.userId = r.user_id
	JOIN patients p ON p.nik = TRIM(u.nik)
	SET m.patient_id = p.patient_id
	WHERE m.patient_id IS NULL');
$linked = (int) $db->affected_rows;

$db->query('UPDATE medicalrecords m
	JOIN requests r ON r.request_id = m.request_id
	SET m.clinical_finalized_at = COALESCE(r.visit_completed_at, r.completed_at)
	WHERE m.clinical_finalized_at IS NULL
		AND COALESCE(r.visit_completed_at, r.completed_at) IS NOT NULL');

/* ---------------------------------------------------------------------------
 * 2b. Satu konsultasi AKTIF untuk menguji chat, kunjungan, dan panggilan
 *
 * Chat hanya bisa dibuka untuk permintaan `Accepted`/`Completed`/`Cancelled`,
 * dan tombol kirim maupun panggilan menuntut `Accepted` plus nakes penanggung
 * jawab (`doclinc_can_send_chat`, `doclinc_livekit_authorized_request`).
 * Data demo `bootstrap.php` hanya berisi permintaan Pending/Completed/Cancelled,
 * sehingga utas chat selalu tampil read-only dan panggilan selalu ditolak 403.
 *
 * Baris di bawah menambah SATU permintaan `Accepted` milik warga pertama
 * (ber-NIK) dengan staf personal pertama sebagai penanggung jawab sekaligus
 * pelaksana kunjungan, `visit_status='not_started'` supaya alur kunjungan bisa
 * didemokan dari awal. Dibuat hanya bila warga itu belum punya permintaan
 * aktif, jadi mengulang skrip tidak menumpuk permintaan baru.
 * ------------------------------------------------------------------------ */
$active_request_id = 0;
$active_request_note = 'tidak dibuat';
$active_warga_id = 0;
$active_nakes_id = 0;

$warga_row = $db->query("SELECT userId FROM users
	WHERE role='warga' AND status='aktif' AND TRIM(nik) REGEXP '^[0-9]{16}$'
	ORDER BY userId LIMIT 1")->fetch_assoc();
$nakes_row = $db->query("SELECT s.user_id, s.kode_pkm FROM puskesmas_staff s
	JOIN users u ON u.userId = s.user_id
	WHERE s.status='aktif' AND u.status='aktif' AND u.username LIKE 'nakes%'
	ORDER BY s.user_id LIMIT 1")->fetch_assoc();

if ($warga_row && $nakes_row) {
	$active_warga_id = (int) $warga_row['userId'];
	$active_nakes_id = (int) $nakes_row['user_id'];

	$stmt = $db->prepare("SELECT request_id FROM requests
		WHERE user_id = ? AND request_status = 'Accepted'
			AND (responsible_doctor_user_id IS NOT NULL OR visit_performer_user_id IS NOT NULL)
		ORDER BY request_id DESC LIMIT 1");
	$stmt->bind_param('i', $active_warga_id);
	$stmt->execute();
	$found = $stmt->get_result()->fetch_assoc();
	$stmt->close();

	if ($found) {
		$active_request_id = (int) $found['request_id'];
		$active_request_note = 'sudah ada';
	} else {
		$facility_code = (string) $nakes_row['kode_pkm'];
		$facility_name = $facility_code;
		$stmt = $db->prepare('SELECT nama_puskesmas FROM m_puskesmas WHERE kode_pkm = ? LIMIT 1');
		$stmt->bind_param('s', $facility_code);
		$stmt->execute();
		$facility = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($facility && trim((string) $facility['nama_puskesmas']) !== '') {
			$facility_name = (string) $facility['nama_puskesmas'];
		}

		$description = '[uji lokal] Konsultasi aktif: chat, kunjungan, dan panggilan';
		$location = 'Kecamatan Cibeber';
		$latitude = '-6.0100';
		$longitude = '106.0100';
		$patient_latitude = -6.0100000;
		$patient_longitude = 106.0100000;
		$stmt = $db->prepare("INSERT INTO requests
			(user_id, dokter_id, request_description, request_status, location,
			lattitude, longitude, patient_latitude, patient_longitude,
			assigned_puskesmas_code, assigned_puskesmas_name, assigned_nakes_user_id,
			assigned_nakes_at, assigned_nakes_by_user_id, accepted_by_user_id,
			responsible_doctor_user_id, visit_performer_user_id, consultation_mode,
			visit_status, date, created_at, updated_at)
			VALUES (?, ?, ?, 'Accepted', ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, 'visit',
			'not_started', NOW(), NOW(), NOW())");
		$stmt->bind_param(
			'iissssddssiiiii',
			$active_warga_id,
			$active_nakes_id,
			$description,
			$location,
			$latitude,
			$longitude,
			$patient_latitude,
			$patient_longitude,
			$facility_code,
			$facility_name,
			$active_nakes_id,
			$active_nakes_id,
			$active_nakes_id,
			$active_nakes_id,
			$active_nakes_id
		);
		$stmt->execute();
		$active_request_id = (int) $db->insert_id;
		$stmt->close();
		$active_request_note = 'dibuat';
	}
}

/* ---------------------------------------------------------------------------
 * 3. Koordinator puskesmas (command center) per fasilitas
 *
 * userId 91-99 dipilih supaya SELALU lebih kecil dari akun staf 101-109: aturan
 * aplikasi memilih userId terkecil sebagai command center. Akun ini sengaja
 * tidak diberi baris `puskesmas_staff` - kalau tertaut, identitasnya ditolak
 * dengan `command_center_linked_to_staff`.
 * ------------------------------------------------------------------------ */
$password_hash = password_hash('Lokal#2026!', PASSWORD_DEFAULT);
$facilities = array();
$rows = $db->query("SELECT s.kode_pkm, MIN(p.nama_puskesmas) AS nama
	FROM puskesmas_staff s
	LEFT JOIN m_puskesmas p ON p.kode_pkm = s.kode_pkm
	WHERE s.user_id IS NOT NULL AND s.status='aktif'
	GROUP BY s.kode_pkm
	ORDER BY s.kode_pkm");
while ($row = $rows->fetch_assoc()) {
	$facilities[] = $row;
}

$created_coordinators = array();
foreach ($facilities as $offset => $facility) {
	$user_id = 91 + $offset;
	$username = 'kord' . ($offset + 1);
	$nama_fasilitas = trim((string) $facility['nama']) !== '' ? trim((string) $facility['nama']) : (string) $facility['kode_pkm'];

	$stmt = $db->prepare('SELECT userId FROM users WHERE username = ?');
	$stmt->bind_param('s', $username);
	$stmt->execute();
	$found = $stmt->get_result()->fetch_row();
	$stmt->close();
	if ($found) {
		continue;
	}

	$nama = 'Koordinator ' . $nama_fasilitas;
	$email = $username . '@lokal.test';
	$no_hp = '0812000000' . str_pad((string) $user_id, 2, '0', STR_PAD_LEFT);
	$nik = '3671000000000' . str_pad((string) $user_id, 3, '0', STR_PAD_LEFT);
	$stmt = $db->prepare("INSERT INTO users
		(userId, nama, email, username, password, role, status, must_change_password, no_hp, nik, tgl, gender, remark, created_at, updated_at)
		VALUES (?, ?, ?, ?, ?, 'dokter', 'aktif', 0, ?, ?, '1985-05-05', 'Laki-laki', ?, NOW(), NOW())");
	$stmt->bind_param('isssssss', $user_id, $nama, $email, $username, $password_hash, $no_hp, $nik, $facility['kode_pkm']);
	$stmt->execute();
	$stmt->close();
	$created_coordinators[] = $username;
}

/* ---------------------------------------------------------------------------
 * 4. Verifikasi: bentuk skema dan aturan identitas diperiksa, bukan diasumsikan
 * ------------------------------------------------------------------------ */
$problems = array();
$expected = array(
	array('medicalrecords', 'patient_id', 'bigint unsigned'),
	array('medicalrecords', 'clinical_finalized_at', 'datetime'),
	array('request_events', 'domain_event_key', 'varchar'),
	array('patients', 'nik', 'char'),
	array('patients', 'status', 'enum'),
	array('consultation_messages', 'sender_role', 'varchar'),
	array('consultation_messages', 'message_text', 'text'),
	array('consultation_messages', 'is_read', 'tinyint'),
	array('consultation_messages', 'deleted_at', 'datetime'),
	array('call_sessions', 'room_name', 'varchar'),
	array('call_sessions', 'call_type', 'enum'),
	array('call_sessions', 'status', 'enum'),
	array('call_sessions', 'created_at', 'datetime'),
);
foreach ($expected as $check) {
	list($table, $column, $type) = $check;
	$actual = portal_column($db, $table, $column);
	if ($actual === null) {
		$problems[] = "kolom {$table}.{$column} tidak ada";
	} elseif (strpos(portal_type($actual), $type) !== 0) {
		$problems[] = "kolom {$table}.{$column} bertipe {$actual}, seharusnya {$type}";
	}
}
if (!portal_index($db, 'request_events', 'uq_request_events_domain_key')) {
	$problems[] = 'indeks unik request_events.uq_request_events_domain_key tidak ada';
}
foreach (array('idx_consultation_messages_request_id', 'idx_consultation_messages_deleted') as $index) {
	if (!portal_index($db, 'consultation_messages', $index)) {
		$problems[] = "indeks consultation_messages.{$index} tidak ada";
	}
}
foreach (array('idx_call_sessions_request_status', 'idx_call_sessions_callee_status') as $index) {
	if (!portal_index($db, 'call_sessions', $index)) {
		$problems[] = "indeks call_sessions.{$index} tidak ada";
	}
}
if ($active_request_id < 1) {
	$problems[] = 'tidak ada permintaan Accepted untuk menguji chat/kunjungan/panggilan';
} else {
	$stmt = $db->prepare("SELECT request_status, visit_status, assigned_nakes_user_id,
			responsible_doctor_user_id, visit_performer_user_id
		FROM requests WHERE request_id = ?");
	$stmt->bind_param('i', $active_request_id);
	$stmt->execute();
	$active = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$active || $active['request_status'] !== 'Accepted') {
		$problems[] = "permintaan uji #{$active_request_id} tidak berstatus Accepted";
	} elseif ((int) $active['assigned_nakes_user_id'] !== $active_nakes_id
		|| (int) $active['responsible_doctor_user_id'] !== $active_nakes_id
		|| (int) $active['visit_performer_user_id'] !== $active_nakes_id) {
		$problems[] = "permintaan uji #{$active_request_id} tidak menunjuk nakes {$active_nakes_id}";
	} elseif ($active['visit_status'] !== 'not_started') {
		$problems[] = "permintaan uji #{$active_request_id} visit_status='{$active['visit_status']}', seharusnya not_started";
	}
}

$coordinators = array();
$rows = $db->query("SELECT u.userId, u.username, u.remark,
		(SELECT COUNT(*) FROM puskesmas_staff s WHERE s.user_id = u.userId) AS staff_links
	FROM users u
	WHERE u.role='dokter' AND u.status='aktif' AND u.username LIKE 'kord%'
	ORDER BY u.userId");
while ($row = $rows->fetch_assoc()) {
	$coordinators[] = $row;
}
if (count($coordinators) !== count($facilities)) {
	$problems[] = 'koordinator ' . count($coordinators) . ' akun, seharusnya ' . count($facilities);
}
foreach ($coordinators as $row) {
	if ((int) $row['staff_links'] !== 0) {
		$problems[] = "koordinator {$row['username']} tertaut puskesmas_staff ({$row['staff_links']} baris)";
	}
	$code = $db->real_escape_string((string) $row['remark']);
	$canonical = $db->query("SELECT userId FROM users WHERE role='dokter' AND status='aktif' AND TRIM(remark) = '{$code}' ORDER BY userId ASC LIMIT 1")->fetch_assoc();
	if (!$canonical || (string) $canonical['userId'] !== (string) $row['userId']) {
		$problems[] = "koordinator {$row['username']} bukan akun userId terkecil untuk kode {$row['remark']}";
	}
}

$staff_personal = 0;
$rows = $db->query("SELECT COUNT(*) AS total FROM puskesmas_staff s
	JOIN users u ON u.userId = s.user_id
	WHERE s.status='aktif' AND u.status='aktif' AND u.username LIKE 'nakes%'");
if ($rows) {
	$staff_personal = (int) $rows->fetch_assoc()['total'];
}
if ($staff_personal < 1) {
	$problems[] = 'tidak ada akun staf personal (nakes*) yang aktif dan tertaut';
}
if ($patients < 1) {
	$problems[] = 'tabel patients kosong (akun warga ber-NIK 16 digit tidak ditemukan)';
}

if ($problems) {
	fwrite(STDERR, "GAGAL: fixture portal belum sesuai harapan:\n");
	foreach ($problems as $problem) {
		fwrite(STDERR, "  - {$problem}\n");
	}
	exit(1);
}

echo 'tabel patients              : ' . ($created_patients ? 'dibuat' : 'sudah ada') . "\n";
echo 'kolom ditambahkan           : ' . ($added_columns ? implode(', ', $added_columns) : '(tidak ada)') . "\n";
echo 'indeks ditambahkan          : ' . ($added_indexes ? implode(', ', $added_indexes) : '(tidak ada)') . "\n";
echo "pasien                      : {$patients} baris\n";
echo "rekam tertaut pasien        : {$linked} baris baru\n";
echo 'koordinator puskesmas       : ' . count($coordinators) . ' akun'
	. ($created_coordinators ? ' (dibuat: ' . implode(', ', $created_coordinators) . ')' : ' (sudah ada)') . "\n";
echo "akun staf personal          : {$staff_personal}\n";
if ($active_request_id > 0) {
	echo "permintaan uji aktif        : #{$active_request_id} ({$active_request_note})"
		. " warga {$active_warga_id} <-> nakes {$active_nakes_id}"
		. " - chat, kunjungan, dan panggilan siap diuji\n";
} else {
	echo "permintaan uji aktif        : (tidak dibuat)\n";
}

echo "PORTAL_FIXTURE_RESULT=PASS\n";
$db->close();

