<?php
/**
 * Fixture database lokal untuk panel admin DocLink.
 *
 * Tujuan: satu perintah yang menghasilkan database yang cukup lengkap untuk
 * membuka SEMUA rute panel admin (dasbor + 18 modul) tanpa menyentuh staging.
 * Repo ini tidak menyimpan dump SQL (`*.sql` di-.gitignore), jadi skema di sini
 * disusun dari sumber yang benar-benar dipakai aplikasi:
 *
 *   - `tools/nakes_import/tests/reset_import_integration.php`  (23 tabel dasar)
 *   - `tools/realtime_requests/tests/model_integration.php`    (bentuk `requests`)
 *   - `tools/care_operations_foundation/CareOperationsFixture.php` (assignment)
 *   - `tools/admin_panel/tests/monitoring_dashboard_unit.php`  (kontrak dasbor)
 *
 * Kolom `requests` sengaja superset: `Home_m` menyaring nama kolom lewat
 * `field_exists_cached()`, jadi kolom yang tidak ada membuat sebagian kartu
 * jatuh ke mode "sumber tidak tersedia" - dan itu bukan keadaan yang ingin
 * diuji secara visual.
 *
 * Jalankan di dalam container aplikasi:
 *   docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
 *     php /var/www/html/tools/admin_panel/local/bootstrap.php --fresh
 *
 * Penjaga: nama database wajib memuat "stage" DAN "test". Alasannya bukan
 * formalitas - `tools/admin_panel/tests/wilayah_seed_test.php` menolak database
 * tanpa "stage", dan `tools/nakes_import/tests/reset_import_integration.php`
 * menolak database tanpa "test". Nama `doclinc_stage_local_test` memenuhi
 * keduanya sehingga kedua uji itu tetap bisa dijalankan pada fixture yang sama.
 */

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "refuse: hanya untuk CLI\n");
	exit(2);
}

/** Nilai env dengan fallback berurutan, mengikuti gaya `config/database.php`. */
function fixture_env($names, $fallback = '')
{
	foreach ((array) $names as $name) {
		$value = getenv($name);
		if ($value !== false && $value !== '') {
			return $value;
		}
	}
	return $fallback;
}

function fixture_fail($message)
{
	fwrite(STDERR, "REFUSE: {$message}\n");
	exit(2);
}

function fixture_arg($name)
{
	return in_array($name, $_SERVER['argv'], true);
}

if (getenv('ALLOW_LOCAL_FIXTURE') !== '1') {
	fixture_fail('set ALLOW_LOCAL_FIXTURE=1 (penjaga supaya tidak jalan tanpa sengaja)');
}

$host = fixture_env(array('ADMIN_DB_HOST', 'DB_HOST'), '127.0.0.1');
$name = fixture_env(array('ADMIN_DB_NAME', 'DB_NAME'));
$user = fixture_env(array('ADMIN_DB_USER', 'DB_USER'));
$pass = fixture_env(array('ADMIN_DB_PASS', 'DB_PASS'));
$port = (int) fixture_env(array('ADMIN_DB_PORT', 'DB_PORT'), 3306);

if ($name === '') {
	fixture_fail('ADMIN_DB_NAME/DB_NAME kosong');
}
if (stripos($name, 'stage') === false || stripos($name, 'test') === false) {
	fixture_fail("nama database '{$name}' wajib memuat 'stage' dan 'test'");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
	$db = new mysqli($host, $user, $pass, $name, $port);
} catch (mysqli_sql_exception $e) {
	fixture_fail('koneksi gagal: ' . $e->getMessage());
}
$db->set_charset('utf8mb4');

/* ------------------------------------------------------------------------- */
/* Skema                                                                      */
/* ------------------------------------------------------------------------- */

/**
 * Daftar tabel sengaja datar (bukan migrasi bertahap) supaya satu perintah
 * selalu menghasilkan bentuk akhir yang sama. Setiap kali dijalankan, tabel
 * yang ada di daftar ini DIBUAT ULANG - data lokal tidak pernah berharga.
 */
$schema = array();

/* `tgl` dan `gender` wajib ada: `Kelola_staff_puskesmas_m` menyaring calon akun
   dengan `tgl IS NOT NULL` dan `gender IN ('Laki-laki','Perempuan')`. Tanpa
   keduanya, halaman staf melempar "Unknown column 'tgl'" dan sapuan rute
   berhenti di 500 - bukan sekadar daftar kosong. */
$schema['users'] = "CREATE TABLE users (
	userId int(11) NOT NULL AUTO_INCREMENT,
	nama varchar(100) NOT NULL,
	email varchar(100) NOT NULL,
	username varchar(100) NULL,
	password varchar(255) NOT NULL,
	role enum('admin','dokter','warga','') NOT NULL DEFAULT '',
	status enum('aktif','nonaktif') NULL DEFAULT 'aktif',
	must_change_password tinyint(1) NOT NULL DEFAULT 0,
	password_changed_at datetime NULL,
	remark varchar(100) NULL,
	no_hp varchar(50) NULL,
	nik varchar(20) NULL,
	foto varchar(255) NULL,
	tgl date NULL,
	gender varchar(20) NULL,
	created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (userId),
	UNIQUE KEY uq_users_email (email),
	UNIQUE KEY uq_users_username (username),
	KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['m_puskesmas'] = "CREATE TABLE m_puskesmas (
	kode_pkm varchar(100) NOT NULL,
	nama_puskesmas varchar(150) NOT NULL,
	status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
	alamat varchar(255) NULL,
	kota varchar(100) NULL,
	provinsi varchar(100) NULL,
	telepon varchar(50) NULL,
	email varchar(150) NULL,
	kepala_puskesmas varchar(150) NULL,
	latitude decimal(10,7) NULL,
	longitude decimal(10,7) NULL,
	created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (kode_pkm),
	KEY idx_mp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['puskesmas_staff'] = "CREATE TABLE puskesmas_staff (
	staff_id int(10) unsigned NOT NULL AUTO_INCREMENT,
	kode_pkm varchar(100) NOT NULL,
	nama varchar(150) NOT NULL,
	no_hp varchar(50) NULL,
	profesi varchar(100) NULL,
	penugasan varchar(150) NULL,
	nomor_sip varchar(100) NULL,
	user_id int(11) NULL,
	status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
	created_at datetime NOT NULL,
	updated_at datetime NULL,
	created_by_user_id int(11) NULL,
	updated_by_user_id int(11) NULL,
	PRIMARY KEY (staff_id),
	KEY idx_ps_user (user_id),
	KEY idx_ps_puskesmas (kode_pkm, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* Bentuk kolom mengikuti `tools/realtime_requests/tests/model_integration.php`
   ditambah `completed_at` (dipakai seluruh agregat dasbor) dan `date`
   (kolom lama yang masih dibaca `request_date_expr()` bila `created_at` hilang). */
$schema['requests'] = "CREATE TABLE requests (
	request_id int(11) NOT NULL AUTO_INCREMENT,
	user_id int(11) NOT NULL,
	dokter_id int(11) NULL,
	request_description text NULL,
	request_status enum('Pending','Accepted','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
	location text NULL,
	lattitude varchar(50) NULL,
	longitude varchar(50) NULL,
	patient_latitude decimal(10,7) NULL,
	patient_longitude decimal(10,7) NULL,
	assigned_puskesmas_code varchar(100) NULL,
	assigned_puskesmas_name varchar(150) NULL,
	assigned_nakes_user_id int(11) NULL,
	assigned_nakes_at datetime NULL,
	assigned_nakes_by_user_id int(11) NULL,
	accepted_by_user_id int(11) NULL,
	responsible_doctor_user_id int(11) NULL,
	visit_performer_user_id int(11) NULL,
	consultation_mode varchar(30) NULL,
	visit_status varchar(30) NULL,
	visit_completed_at datetime NULL,
	completed_at datetime NULL,
	date datetime NULL,
	file varchar(255) NULL,
	created_at datetime NULL,
	updated_at datetime NULL,
	PRIMARY KEY (request_id),
	KEY idx_requests_owner (user_id),
	KEY idx_requests_status_created (request_status, created_at),
	KEY idx_requests_puskesmas (assigned_puskesmas_code, request_status, request_id),
	KEY idx_requests_completed (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['request_staff_assignments'] = "CREATE TABLE request_staff_assignments (
	assignment_id int(11) NOT NULL AUTO_INCREMENT,
	request_id int(11) NOT NULL,
	staff_id int(11) NOT NULL,
	kode_pkm varchar(100) NOT NULL,
	assigned_by_user_id int(11) NOT NULL,
	status varchar(20) NOT NULL,
	note text NULL,
	assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	ended_at datetime NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at datetime NULL,
	PRIMARY KEY (assignment_id),
	KEY idx_rsa_request_status (request_id, status),
	KEY idx_rsa_staff_status (staff_id, status),
	KEY idx_rsa_kode_status (kode_pkm, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['request_events'] = "CREATE TABLE request_events (
	event_id int(11) NOT NULL AUTO_INCREMENT,
	request_id int(11) NOT NULL,
	event_type varchar(64) NOT NULL,
	actor_user_id int(11) NULL,
	actor_staff_id int(11) NULL,
	payload text NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY (event_id),
	KEY idx_re_request_type (request_id, event_type),
	KEY idx_re_type_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['medicalrecords'] = "CREATE TABLE medicalrecords (
	record_id int(11) NOT NULL AUTO_INCREMENT,
	request_id int(11) NOT NULL,
	responsible_doctor_user_id int(11) NULL,
	recorded_by_user_id int(11) NULL,
	anamnesis text NULL,
	diagnosis text NULL,
	treatment text NULL,
	recommendations text NULL,
	created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at datetime NULL,
	PRIMARY KEY (record_id),
	KEY idx_mr_request (request_id),
	CONSTRAINT fk_local_medicalrecord_request FOREIGN KEY (request_id)
		REFERENCES requests (request_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* Diagnosis terstruktur. Tanpa tabel ini, infografis penyakit terbanyak dan
   peta sebarannya hanya bisa menampilkan "belum tersedia", sehingga tidak ada
   satu pun angka yang bisa diperiksa secara visual - termasuk peta per
   kecamatan. Kolom mengikuti migrasi `20260728000100_care_operations_foundation`
   ditambah `coding_state` dari `20261022000100_patients_registry_foundation`.
   Kolom waktu kanonik sengaja tidak ada di sini: `dateExpression()` membaca
   `m.clinical_finalized_at`, `r.visit_completed_at`, lalu `r.completed_at`. */
$schema['medicalrecord_diagnoses'] = "CREATE TABLE medicalrecord_diagnoses (
	diagnosis_id int(11) NOT NULL AUTO_INCREMENT,
	medicalrecord_id int(11) NOT NULL,
	request_id int(11) NOT NULL,
	position tinyint unsigned NOT NULL DEFAULT 1,
	diagnosis_role varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'primary',
	diagnosis_code varchar(32) NULL,
	diagnosis_label varchar(255) NOT NULL,
	coding_state enum('uncoded','coded') NOT NULL DEFAULT 'uncoded',
	display_text varchar(320) NOT NULL,
	suggestion_term_id bigint unsigned NULL,
	reference_source varchar(128) NULL,
	reference_version varchar(64) NULL,
	created_by_user_id int(11) NOT NULL,
	created_at datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
	updated_at datetime(6) NULL,
	PRIMARY KEY (diagnosis_id),
	UNIQUE KEY uq_medicalrecord_diagnosis_position (medicalrecord_id, position),
	KEY idx_medicalrecord_diagnosis_request (request_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";


/* `monitoring_cache` butuh PRIMARY KEY pada `cache_key`: model memakai
   `INSERT ... ON DUPLICATE KEY UPDATE`, jadi tanpa kunci unik setiap muat
   dasbor menambah baris baru alih-alih memperbarui satu baris. */
$schema['monitoring_cache'] = "CREATE TABLE monitoring_cache (
	cache_key varchar(191) NOT NULL,
	payload longtext NOT NULL,
	computed_at datetime NOT NULL,
	PRIMARY KEY (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* `request_id` sebagai kunci utama: model mengandalkan hubungan 1:1 saat
   menggabungkan penanda ke himpunan pengecualian, kalau tidak hitungannya
   bisa berlipat. */
$schema['monitoring_followups'] = "CREATE TABLE monitoring_followups (
	request_id int(11) NOT NULL,
	marked_by_user_id int(11) NOT NULL,
	note text NULL,
	marked_at datetime NOT NULL,
	PRIMARY KEY (request_id),
	KEY idx_mf_marked_by (marked_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['audit_logs'] = "CREATE TABLE audit_logs (
	id bigint unsigned NOT NULL AUTO_INCREMENT,
	actor_user_id int(11) NULL,
	action varchar(100) NOT NULL,
	entity_type varchar(100) NULL,
	entity_id varchar(100) NULL,
	ip_address varchar(45) NULL,
	user_agent varchar(255) NULL,
	metadata_json text NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY (id),
	KEY idx_audit_actor_created (actor_user_id, created_at),
	KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['notifications'] = "CREATE TABLE notifications (
	notification_id int(11) NOT NULL AUTO_INCREMENT,
	recipient_user_id int(11) NULL,
	recipient_role varchar(32) NULL,
	recipient_puskesmas_code varchar(100) NULL,
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
	KEY idx_notif_user_read (recipient_user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['admin_notification_reads'] = "CREATE TABLE admin_notification_reads (
	user_id int(11) NOT NULL,
	last_seen_event_id int(11) NULL,
	PRIMARY KEY (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['admin_user_capabilities'] = "CREATE TABLE admin_user_capabilities (
	user_id int(11) NOT NULL,
	capability_code varchar(64) NOT NULL,
	granted_by_user_id int(11) NULL,
	granted_at datetime NULL,
	PRIMARY KEY (user_id, capability_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* Dua tabel wilayah di bawah berasal dari `Wilayah_seed`: penamaan kolomnya
   (`id_kecamatan`, `nama_kecamatan`) berbeda dari `m_puskesmas`, dan keduanya
   dipakai bersama oleh halaman Wilayah dan uji seed wilayah. */
$schema['kecamatan'] = "CREATE TABLE kecamatan (
	id_kecamatan int(11) NOT NULL AUTO_INCREMENT,
	nama_kecamatan varchar(100) NOT NULL,
	PRIMARY KEY (id_kecamatan),
	KEY idx_kecamatan_nama (nama_kecamatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['puskesmas'] = "CREATE TABLE puskesmas (
	id_puskesmas int(11) NOT NULL AUTO_INCREMENT,
	kode_pkm varchar(100) NULL,
	nama_puskesmas varchar(150) NULL,
	id_kecamatan int(11) NULL,
	PRIMARY KEY (id_puskesmas),
	KEY idx_puskesmas_kode (kode_pkm),
	KEY idx_puskesmas_kecamatan (id_kecamatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['keluhan'] = "CREATE TABLE keluhan (
	id_keluhan int(11) NOT NULL AUTO_INCREMENT,
	kategori varchar(100) NULL,
	nama_keluhan varchar(150) NULL,
	deskripsi text NULL,
	remark varchar(100) NULL,
	status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
	create_user varchar(100) NULL,
	create_date datetime NULL,
	modify_user varchar(100) NULL,
	modify_date datetime NULL,
	PRIMARY KEY (id_keluhan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* Nama kolom mengikuti `Landing_content::blocks()` yang mengurutkan `block_key`,
   bukan `kode`: salah nama di sini membuat halaman konten landing gagal di
   ORDER BY, bukan sekadar tampil kosong. */
$schema['landing_blocks'] = "CREATE TABLE landing_blocks (
	id int(11) NOT NULL AUTO_INCREMENT,
	block_key varchar(64) NOT NULL,
	judul varchar(200) NULL,
	isi text NULL,
	urutan int(11) NOT NULL DEFAULT 0,
	aktif tinyint(1) NOT NULL DEFAULT 1,
	updated_by_user_id int(11) NULL,
	updated_at datetime NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_landing_block_key (block_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['landing_faqs'] = "CREATE TABLE landing_faqs (
	id int(11) NOT NULL AUTO_INCREMENT,
	pertanyaan varchar(255) NOT NULL,
	jawaban text NOT NULL,
	urutan int(11) NOT NULL DEFAULT 0,
	aktif tinyint(1) NOT NULL DEFAULT 1,
	updated_by_user_id int(11) NULL,
	updated_at datetime NULL,
	PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['konsultasi'] = "CREATE TABLE konsultasi (
	konsul_id int(11) NOT NULL AUTO_INCREMENT,
	request_id int(11) NOT NULL,
	diagnosa text NULL,
	saran text NULL,
	kriteria varchar(100) NULL,
	rujukan varchar(255) NULL,
	foto varchar(255) NULL,
	create_date datetime NULL,
	create_user varchar(100) NULL,
	modify_date datetime NULL,
	modify_user varchar(100) NULL,
	PRIMARY KEY (konsul_id),
	UNIQUE KEY uq_konsultasi_request (request_id),
	KEY idx_konsultasi_kriteria (kriteria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['terapi'] = "CREATE TABLE terapi (
	terapi_id int(11) NOT NULL AUTO_INCREMENT,
	konsul_id int(11) NOT NULL,
	terapi varchar(255) NULL,
	PRIMARY KEY (terapi_id),
	KEY idx_terapi_konsul (konsul_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['consultation_messages'] = "CREATE TABLE consultation_messages (
	message_id int(11) NOT NULL AUTO_INCREMENT,
	request_id int(11) NOT NULL,
	sender_user_id int(11) NOT NULL,
	body text NULL,
	attachment_path varchar(255) NULL,
	created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (message_id),
	KEY idx_cm_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['call_sessions'] = "CREATE TABLE call_sessions (
	call_id bigint unsigned NOT NULL AUTO_INCREMENT,
	request_id int(11) NOT NULL,
	caller_user_id int(11) NOT NULL,
	callee_user_id int(11) NOT NULL,
	started_at datetime NULL,
	ended_at datetime NULL,
	PRIMARY KEY (call_id),
	KEY idx_cs_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['m_dokter'] = "CREATE TABLE m_dokter (
	professional_id int(11) NOT NULL AUTO_INCREMENT,
	name varchar(50) NOT NULL,
	phone varchar(20) NOT NULL,
	email varchar(100) NOT NULL,
	password varchar(255) NOT NULL,
	status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
	PRIMARY KEY (professional_id),
	UNIQUE KEY uq_mdokter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['feeds'] = "CREATE TABLE feeds (
	feedId int(11) NOT NULL AUTO_INCREMENT,
	subject varchar(255) NOT NULL,
	body text NULL,
	status enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
	created_at datetime NULL,
	PRIMARY KEY (feedId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['t_pengaduan'] = "CREATE TABLE t_pengaduan (
	id_pengaduan int(11) NOT NULL AUTO_INCREMENT,
	judul varchar(200) NULL,
	isi text NULL,
	status varchar(30) NULL,
	created_at datetime NULL,
	PRIMARY KEY (id_pengaduan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$schema['clinical_schema_migrations'] = "CREATE TABLE clinical_schema_migrations (
	migration_id varchar(100) NOT NULL,
	state varchar(20) NOT NULL,
	PRIMARY KEY (migration_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

/* Semua tabel dibuat ulang setiap kali dijalankan: fixture lokal tidak pernah
   menyimpan data yang layak dipertahankan, dan sisa tabel dari bentuk skema
   lama justru menyembunyikan kesalahan kolom. Foreign key dimatikan saat
   menjatuhkan tabel supaya urutan `array_reverse` tidak perlu sempurna. */
$db->query('SET FOREIGN_KEY_CHECKS = 0');
foreach (array_reverse(array_keys($schema)) as $table) {
	$db->query('DROP TABLE IF EXISTS `' . $table . '`');
}
foreach ($schema as $ddl) {
	$db->query($ddl);
}
$db->query('SET FOREIGN_KEY_CHECKS = 1');

echo '== skema: ' . count($schema) . " tabel dibuat di {$name} ==\n";

/* ------------------------------------------------------------------------- */
/* Seed                                                                       */
/* ------------------------------------------------------------------------- */

/**
 * Satu baris masuk lewat prepared statement. Disengaja: seed memuat teks
 * Indonesia bertanda kutip dan nama fasilitas, jadi menyusun SQL dengan
 * penggabungan string akan rapuh.
 *
 * `$pk` wajib diisi untuk tabel yang kunci utamanya ditulis eksplisit:
 * `mysqli::insert_id` bernilai 0 pada INSERT dengan nilai AUTO_INCREMENT
 * eksplisit, sehingga tanpa ini peta staf/kecamatan/konsultasi menunjuk 0.
 */
$insert = function ($table, array $row, $pk = null) use ($db) {
	$columns = array_keys($row);
	$sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES ('
		. implode(',', array_fill(0, count($columns), '?')) . ')';
	$stmt = $db->prepare($sql);
	$types = '';
	foreach ($row as $value) {
		$types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
	}
	$values = array_values($row);
	$stmt->bind_param($types, ...$values);
	$stmt->execute();
	$id = $pk !== null && isset($row[$pk]) ? (int) $row[$pk] : (int) $stmt->insert_id;
	$stmt->close();
	return $id;
};

/* `--fresh` diterima tetapi tidak mengubah perilaku: skrip ini memang selalu
   membuat ulang seluruh tabel. Parameternya dipertahankan supaya perintah yang
   tertulis di dokumentasi tidak mengejutkan siapa pun yang menyalinnya. */
if (fixture_arg('--fresh')) {
	echo "== mode: --fresh (default; tabel selalu dibuat ulang) ==\n";
}

/* Waktu acuan tunggal: seluruh seed bergeser bersama, jadi "hari ini" di layar
   selalu berarti hari ini, bukan tanggal ketika fixture ini ditulis. */
$now = time();
$stamp = function ($offset_seconds) use ($now) {
	return date('Y-m-d H:i:s', $now + (int) $offset_seconds);
};

$password_hash = password_hash('Lokal#2026!', PASSWORD_DEFAULT);

/* 12 fasilitas: 10 aktif (satu di antaranya tanpa staf) dan 2 nonaktif.
   Fasilitas nonaktif disengaja - dasbor punya pemeriksaan khusus "permintaan
   menunjuk Puskesmas nonaktif" yang tidak akan pernah muncul bila semua baris
   master berstatus aktif.

   Daftar ini sengaja diletakkan paling awal karena kode fasilitas dipakai di
   empat tempat: `m_puskesmas`, `puskesmas_staff.kode_pkm`, `users.remark`, dan
   `requests.assigned_puskesmas_code`. Sembilan baris pertama adalah sembilan
   Puskesmas inti Kota Cilegon dengan kode resmi Kemenkes
   (`application/libraries/Core_puskesmas.php`); kode itulah yang ada di master
   wilayah (`puskesmas.kode_pkm`), sehingga infografis penyakit terbanyak dapat
   menjumlahkan per kecamatan lewat join
   `requests.assigned_puskesmas_code = puskesmas.kode_pkm`. Selama dua master
   itu memakai kode berbeda (PKMxx vs 1028xxxx), rincian per kecamatan selalu
   nol dan peta sebarannya kosong - panelnya tampak "belum ada data" tanpa
   sebab yang terlihat. Tiga baris terakhir sengaja di luar daftar inti: Merak
   aktif tanpa staf, Anyer dan Bojonegara nonaktif. */
$facilities = array(
	array('10280101', 'Puskesmas Cilegon', 'aktif', 'Jl. Merdeka No. 12'),
	array('10280201', 'Puskesmas Cibeber', 'aktif', 'Jl. Raya Cibeber No. 1'),
	array('10280301', 'Puskesmas Ciwandan', 'aktif', 'Jl. Ciwandan No. 21'),
	array('10280401', 'Puskesmas Pulomerak', 'aktif', 'Jl. Pulomerak No. 9'),
	array('10280402', 'Puskesmas Gerogol', 'aktif', 'Jl. Gerogol No. 5'),
	array('10280501', 'Puskesmas Citangkil', 'aktif', 'Jl. Citangkil No. 8'),
	array('10280601', 'Puskesmas Purwakarta', 'aktif', 'Jl. Purwakarta No. 17'),
	array('10280701', 'Puskesmas Jombang', 'aktif', 'Jl. Jombang No. 33'),
	array('2241001', 'Puskesmas Citangkil II', 'aktif', 'Jl. Citangkil II No. 4'),
	array('PKM10', 'Puskesmas Merak', 'aktif', 'Jl. Merak No. 2'),
	array('PKM11', 'Puskesmas Anyer', 'nonaktif', 'Jl. Anyer No. 44'),
	array('PKM12', 'Puskesmas Bojonegara', 'nonaktif', 'Jl. Bojonegara No. 6'),
);

/* Akun admin: 1 dipakai `sweep.php` untuk masuk, 1 lagi supaya tabel pengguna
   dan halaman kapabilitas punya lebih dari satu baris admin. */
$insert('users', array(
	'userId' => 1, 'nama' => 'Admin Dinkes', 'email' => 'admin@lokal.test',
	'username' => 'admin', 'password' => $password_hash, 'role' => 'admin',
	'status' => 'aktif', 'must_change_password' => 0, 'no_hp' => '081200000001',
	'nik' => '3671000000000001', 'created_at' => $stamp(-90 * 86400),
));
$insert('users', array(
	'userId' => 2, 'nama' => 'Verifikator Wilayah', 'email' => 'verifikator@lokal.test',
	'username' => 'verifikator', 'password' => $password_hash, 'role' => 'admin',
	'status' => 'aktif', 'must_change_password' => 0, 'no_hp' => '081200000002',
	'nik' => '3671000000000002', 'created_at' => $stamp(-60 * 86400),
));

/* Akun nakes: 101-109 aktif dan tertaut staf, 110 nonaktif supaya perhitungan
   "staf tanpa akun aktif" punya satu kasus batas yang nyata. */
for ($i = 1; $i <= 10; $i++) {
	$insert('users', array(
		'userId' => 100 + $i,
		'nama' => 'dr. Nakes ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
		'email' => 'nakes' . $i . '@lokal.test',
		'username' => 'nakes' . $i,
		'password' => $password_hash,
		'role' => 'dokter',
		'status' => $i === 10 ? 'nonaktif' : 'aktif',
		'must_change_password' => 0,
		'no_hp' => '0813000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
		/* `tgl`/`gender` wajib terisi: keduanya dipakai sebagai syarat calon
		   akun staf, jadi akun yang tidak punya keduanya tidak akan pernah
		   muncul di modal penautan. `remark` adalah kode Puskesmas tempat
		   akun itu bertugas (lihat `get_eligible_account_candidates`). */
		'tgl' => date('Y-m-d', strtotime('-' . (30 + $i) . ' years -' . ($i * 37) . ' days')),
		'gender' => $i % 2 === 0 ? 'Perempuan' : 'Laki-laki',
		'remark' => $facilities[$i - 1][0],
		'created_at' => $stamp(-45 * 86400),
	));
}

/* Dua akun dokter tanpa baris staf: inilah calon yang harus muncul di modal
   "Tautkan akun". Tanpa keduanya modal hanya punya keadaan kosong, sehingga
   daftar calon - bagian yang paling mudah rusak saat view disunting - tidak
   pernah benar-benar dirender. */
for ($i = 11; $i <= 12; $i++) {
	$insert('users', array(
		'userId' => 100 + $i,
		'nama' => 'dr. Calon ' . str_pad((string) ($i - 10), 2, '0', STR_PAD_LEFT),
		'email' => 'calon' . ($i - 10) . '@lokal.test',
		'username' => 'calon' . ($i - 10),
		'password' => $password_hash,
		'role' => 'dokter',
		'status' => 'aktif',
		'must_change_password' => 0,
		'no_hp' => '0814000000' . str_pad((string) ($i - 10), 2, '0', STR_PAD_LEFT),
		'tgl' => date('Y-m-d', strtotime('-' . (28 + $i) . ' years')),
		'gender' => $i % 2 === 0 ? 'Perempuan' : 'Laki-laki',
		'remark' => $facilities[$i - 1][0],
		'created_at' => $stamp(-20 * 86400),
	));
}

/* Warga: 40 akun, cukup untuk membuat 200 permintaan tanpa satu pun pemilik
   yang mendominasi daftar. */
for ($i = 1; $i <= 40; $i++) {
	$insert('users', array(
		'userId' => 200 + $i,
		'nama' => 'Warga Contoh ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
		'email' => 'warga' . $i . '@lokal.test',
		'username' => 'warga' . $i,
		'password' => $password_hash,
		'role' => 'warga',
		'status' => 'aktif',
		'must_change_password' => 0,
		'nik' => '36710000000' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
		'created_at' => $stamp(-30 * 86400),
	));
}

/* Master fasilitas ditulis dari daftar `$facilities` di awal seed.
   `email` dan `kepala_puskesmas` sengaja tidak seragam: satu baris inti
   dibiarkan tanpa nama kepala supaya keadaan "belum diisi" pada layar detail
   pernah benar-benar dirender, bukan hanya ada di teori. */
$facility_name = array();
$kepala_names = array(
	'dr. Ratna Sari', 'dr. Bagus Prakoso', 'dr. Lestari Widodo', 'dr. Ahmad Fauzi',
	'dr. Sinta Marlina', 'dr. Hendra Wijaya', 'dr. Nurlaila', 'dr. Yoga Pratama',
);
foreach ($facilities as $index => $facility) {
	$insert('m_puskesmas', array(
		'kode_pkm' => $facility[0],
		'nama_puskesmas' => $facility[1],
		'status' => $facility[2],
		'alamat' => $facility[3],
		'kota' => 'Cilegon',
		'provinsi' => 'Banten',
		'telepon' => '0254' . str_pad((string) (100000 + $index), 6, '0', STR_PAD_LEFT),
		'email' => preg_replace('/[^a-z0-9]+/', '.', strtolower($facility[1])) . '@cilegon.go.id',
		'kepala_puskesmas' => isset($kepala_names[$index]) ? $kepala_names[$index] : null,
		'latitude' => -6.0 - ($index / 1000),
		'longitude' => 106.0 + ($index / 1000),
		'created_at' => $stamp(-120 * 86400),
	));
	$facility_name[$facility[0]] = $facility[1];
}

/* Staf: PKM01-PKM09 punya satu dokter aktif tertaut akun. PKM10 sengaja tanpa
   staf, PKM11/PKM12 punya staf aktif yang belum tertaut akun - dua keadaan yang
   dilaporkan terpisah oleh kesiapan fasilitas di dasbor. */
$staff_of = array();
for ($i = 1; $i <= 9; $i++) {
	$code = $facilities[$i - 1][0];
	$staff_id = $insert('puskesmas_staff', array(
		'staff_id' => $i,
		'kode_pkm' => $code,
		'nama' => 'dr. Nakes ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
		'no_hp' => '0813000000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
		'profesi' => 'Dokter',
		'penugasan' => 'Klinik umum',
		'nomor_sip' => 'SIP-503/2026/' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
		'user_id' => 100 + $i,
		'status' => 'aktif',
		'created_at' => $stamp(-40 * 86400),
		'created_by_user_id' => 1,
	), 'staff_id');
	$staff_of[$code] = $staff_id;
}
foreach (array($facilities[10][0] => 10, $facilities[11][0] => 11) as $code => $staff_id) {
	$insert('puskesmas_staff', array(
		'staff_id' => $staff_id,
		'kode_pkm' => $code,
		'nama' => 'Staf Belum Tertaut ' . $staff_id,
		'no_hp' => '0813000001' . str_pad((string) $staff_id, 2, '0', STR_PAD_LEFT),
		'profesi' => 'Perawat',
		'penugasan' => 'Rawat jalan',
		'user_id' => null,
		'status' => 'aktif',
		'created_at' => $stamp(-38 * 86400),
		'created_by_user_id' => 1,
	));
}
$insert('puskesmas_staff', array(
	'staff_id' => 12,
	'kode_pkm' => $facilities[0][0],
	'nama' => 'Staf Sudah Nonaktif',
	'profesi' => 'Bidan',
	'user_id' => 110,
	'status' => 'nonaktif',
	'created_at' => $stamp(-70 * 86400),
	'created_by_user_id' => 1,
));

/* 200 permintaan dalam 30 hari terakhir. Komposisi status ditulis eksplisit
   (bukan acak) supaya angka di dasbor sama setiap kali fixture dijalankan:
   perbandingan tangkapan layar hanya berguna bila datanya tetap. */
$complaints = array(
	'Demam tiga hari disertai nyeri tenggorokan',
	'Batuk berdahak lebih dari seminggu',
	'Nyeri ulu hati setelah makan',
	'Sakit kepala berulang sejak seminggu terakhir',
	'Gatal dan ruam pada lengan',
	'Nyeri sendi lutut saat berjalan',
	'Sesak napas ringan saat aktivitas',
	'Diare sejak dua hari lalu',
);
$locations = array(
	'Kecamatan Cibeber', 'Kecamatan Cilegon', 'Kecamatan Citangkil',
	'Kecamatan Ciwandan', 'Kecamatan Gerogol', 'Kecamatan Jombang',
	'Kecamatan Pulomerak', 'Kecamatan Purwakarta',
);

$status_pool = array_merge(
	array_fill(0, 120, 'Completed'),
	array_fill(0, 35, 'Accepted'),
	array_fill(0, 42, 'Pending'),
	array_fill(0, 3, 'Cancelled')
);
mt_srand(20261025);
shuffle($status_pool);
$status_pool = array_values($status_pool);

/**
 * Meta per permintaan disimpan selama seed berjalan: tabel jejak peristiwa,
 * penugasan PIC, dan rekam medis dibangun dari baris yang sama, jadi tidak ada
 * kemungkinan jejak menunjuk permintaan yang tidak ada.
 */
$request_meta = array();
foreach ($status_pool as $index => $status) {
	$request_no = $index + 1;
	$day = intdiv(($request_no - 1) * 29, 200);

	$created_ts = strtotime(date('Y-m-d', $now) . ' 08:00:00')
		- ($day * 86400)
		+ (($request_no * 37) % 10) * 3600
		+ (($request_no * 53) % 60) * 60;

	/* Permintaan Pending sengaja "baru saja masuk" dalam hitungan jam: itulah
	   satu-satunya cara panel pengecualian menampilkan baris kritis tanpa
	   memalsukan ambang waktu di konfigurasi. */
	if ($status === 'Pending') {
		$created_ts = $now - ((($request_no % 9) + 1) * 3600);
	}

	$code = $facilities[($request_no - 1) % 9][0];
	if (in_array($request_no, array(7, 63, 128, 190), true)) {
		$code = null;                                   // tanpa Puskesmas tujuan
	}
	if (in_array($request_no, array(21, 154), true)) {
		$code = 'PKM99';                                // kode tidak ada di master
	}
	if (in_array($request_no, array(33, 97, 176), true)) {
		$code = $request_no % 2 === 1 ? $facilities[10][0] : $facilities[11][0]; // fasilitas nonaktif
	}
	if (in_array($request_no, array(45, 88, 132, 177), true)) {
		$code = $facilities[9][0];                        // fasilitas tanpa staf
	}

	$doctor = 101 + ($request_no % 9);
	$mode = ($request_no % 5) < 3 ? 'visit' : 'non_visit';
	$completed_ts = null;
	$visit_status = 'not_started';

	if ($status === 'Completed') {
		$completed_ts = $created_ts + (30 + (($request_no * 17) % 540)) * 60;
		$visit_status = 'completed';

		/* Tiga baris selesai tetapi waktu selesainya kosong: dasbor melaporkan
		   ini sebagai celah data, bukan sebagai nol. */
		if (in_array($request_no, array(59, 143), true)) {
			$completed_ts = null;
		}

		/* Tiga baris masuk SEBELUM periode tetapi selesai DI DALAM periode:
		   tanpa baris seperti ini, kartu "selesai dari periode sebelumnya"
		   selalu nol dan kesalahannya tidak akan terlihat. */
		if (in_array($request_no, array(12, 101, 158), true)) {
			$created_ts = $now - (38 + ($request_no % 5)) * 86400;
			$completed_ts = $now - (5 + ($request_no % 10)) * 86400;
		}
	} elseif ($status === 'Accepted') {
		$visit_status = $mode === 'visit' ? array('en_route', 'arrived', 'in_service')[$request_no % 3] : 'not_started';
	}

	$accepted = ($status === 'Accepted' || $status === 'Completed');

	$insert('requests', array(
		'request_id' => $request_no,
		'user_id' => 201 + ($request_no % 40),
		'dokter_id' => $doctor,
		'request_description' => $complaints[$request_no % count($complaints)],
		'request_status' => $status,
		'location' => $locations[$request_no % count($locations)],
		'lattitude' => (string) (-6.0 - ($request_no / 10000)),
		'longitude' => (string) (106.0 + ($request_no / 10000)),
		'patient_latitude' => -6.0 - ($request_no / 10000),
		'patient_longitude' => 106.0 + ($request_no / 10000),
		'assigned_puskesmas_code' => $code,
		'assigned_puskesmas_name' => $code !== null && isset($facility_name[$code]) ? $facility_name[$code] : null,
		'assigned_nakes_user_id' => $accepted && $mode === 'visit' ? $doctor : null,
		'assigned_nakes_at' => $accepted ? date('Y-m-d H:i:s', $created_ts + 900) : null,
		'assigned_nakes_by_user_id' => $accepted ? 1 : null,
		'accepted_by_user_id' => $accepted ? $doctor : null,
		'responsible_doctor_user_id' => $accepted ? $doctor : null,
		'visit_performer_user_id' => $accepted && $mode === 'visit' ? $doctor : null,
		'consultation_mode' => $mode,
		'visit_status' => $visit_status,
		'visit_completed_at' => $mode === 'visit' ? ($completed_ts === null ? null : date('Y-m-d H:i:s', $completed_ts)) : null,
		'completed_at' => $completed_ts === null ? null : date('Y-m-d H:i:s', $completed_ts),
		'date' => date('Y-m-d H:i:s', $created_ts),
		'created_at' => date('Y-m-d H:i:s', $created_ts),
		'updated_at' => date('Y-m-d H:i:s', $completed_ts === null ? $created_ts : $completed_ts),
	));

	$request_meta[$request_no] = array(
		'status' => $status,
		'code' => $code,
		'created' => $created_ts,
		'completed' => $completed_ts,
		'doctor' => $doctor,
		'mode' => $mode,
		/* Satu dari empat permintaan Accepted dibiarkan tanpa PIC aktif:
		   keadaan itu dilaporkan sebagai "informasi" di panel pengecualian. */
		'needs_pic' => $status === 'Accepted' && $request_no % 4 === 0 && $code !== null,
	);
}

/* Jejak peristiwa. Waktu `request_created` sengaja sama persis dengan
   `requests.created_at`: pemeriksaan kualitas data dasbor menandai selisih
   lebih dari lima menit sebagai kerangka jam yang tidak sah, dan fixture ini
   harus bersih dari kesalahan yang tidak sengaja dibuat. */
$assignment_total = 0;
foreach ($request_meta as $request_id => $meta) {
	$insert('request_events', array(
		'request_id' => $request_id,
		'event_type' => 'request_created',
		'actor_user_id' => 201 + ($request_id % 40),
		'created_at' => date('Y-m-d H:i:s', $meta['created']),
	));

	if ($meta['status'] !== 'Accepted' && $meta['status'] !== 'Completed') {
		$wait = $now - $meta['created'];
		if ($meta['status'] === 'Pending' && $wait >= 4 * 3600 && $request_id % 5 === 1) {
			$insert('request_events', array(
				'request_id' => $request_id,
				'event_type' => 'request.accept_overdue',
				'actor_user_id' => null,
				'created_at' => date('Y-m-d H:i:s', $meta['created'] + 4 * 3600),
			));
		}
		continue;
	}

	$accepted_ts = $meta['created'] + 900;
	$insert('request_events', array(
		'request_id' => $request_id,
		'event_type' => 'request_accepted',
		'actor_user_id' => $meta['doctor'],
		'created_at' => date('Y-m-d H:i:s', $accepted_ts),
	));

	if ($meta['mode'] === 'visit') {
		$insert('request_events', array(
			'request_id' => $request_id,
			'event_type' => 'visit_started',
			'actor_user_id' => $meta['doctor'],
			'created_at' => date('Y-m-d H:i:s', $accepted_ts + 1800),
		));
	}

	if ($meta['completed'] !== null) {
		$insert('request_events', array(
			'request_id' => $request_id,
			'event_type' => $meta['mode'] === 'visit' ? 'visit_completed' : 'request_completed',
			'actor_user_id' => $meta['doctor'],
			'created_at' => date('Y-m-d H:i:s', $meta['completed']),
		));
	}

	/* Penugasan PIC hanya untuk fasilitas yang punya staf tertaut. Permintaan
	   yang ditandai "butuh PIC" memang tidak diberi penugasan: itulah yang
	   membuat panel pengecualian punya baris informasi. */
	if (!$meta['needs_pic'] && $meta['code'] !== null && isset($staff_of[$meta['code']])) {
		$closed = $meta['status'] === 'Completed';
		$insert('request_staff_assignments', array(
			'request_id' => $request_id,
			'staff_id' => $staff_of[$meta['code']],
			'kode_pkm' => $meta['code'],
			'assigned_by_user_id' => 1,
			'status' => $closed ? 'selesai' : 'aktif',
			'note' => null,
			'assigned_at' => date('Y-m-d H:i:s', $accepted_ts),
			'ended_at' => $closed && $meta['completed'] !== null ? date('Y-m-d H:i:s', $meta['completed']) : null,
			'created_at' => date('Y-m-d H:i:s', $accepted_ts),
		));
		$assignment_total++;
	}
}

/* Rekam medis: hanya untuk permintaan yang benar-benar selesai dan punya waktu
   selesai. Sebagian sengaja dilewati (tiap kesepuluh) supaya hitungan diagnosis
   tidak rata dan grafiknya punya bentuk. */
$diagnoses = array(
	'ISPA (J06.9)' => 34,
	'Dispepsia (K29.7)' => 26,
	'Hipertensi primer (I10)' => 21,
	'Dermatitis atopik (L20.9)' => 16,
	'Gastroenteritis akut (A09)' => 12,
	'Migrain (G43.9)' => 9,
);
$diagnosis_names = array_keys($diagnoses);
$record_total = 0;
$diagnosis_total = 0;
foreach ($request_meta as $request_id => $meta) {
	if ($meta['status'] !== 'Completed' || $meta['completed'] === null || $request_id % 10 === 0) {
		continue;
	}
	$label = $diagnosis_names[$request_id % count($diagnosis_names)];
	$record_id = $insert('medicalrecords', array(
		'request_id' => $request_id,
		'responsible_doctor_user_id' => $meta['doctor'],
		'recorded_by_user_id' => $meta['doctor'],
		'anamnesis' => 'Keluhan disampaikan pasien saat kunjungan.',
		'diagnosis' => $label,
		'treatment' => 'Terapi simtomatik sesuai keluhan.',
		'recommendations' => 'Kontrol ulang bila keluhan menetap.',
		'created_at' => date('Y-m-d H:i:s', $meta['completed']),
	));
	$record_total++;

	/* Diagnosis terstruktur: satu diagnosis utama per rekam medis, dan satu
	 * diagnosis sekunder tiap rekam ketujuh supaya kartu "rekam medis terpisah"
	 * benar-benar berbeda dari jumlah baris diagnosis. Satu dari lima baris
	 * sengaja dibiarkan belum dikode - keranjang "belum dikode" hanya bisa diuji
	 * kalau ada isinya. */
	$primary_code = '';
	if (preg_match('/\(([A-Z][0-9]{2}(?:\.[0-9A-Z]+)?)\)\s*$/', $label, $code_match) === 1) {
		$primary_code = $code_match[1];
	}
	$coded = ($request_id % 5) !== 0;
	$insert('medicalrecord_diagnoses', array(
		'medicalrecord_id' => $record_id,
		'request_id' => $request_id,
		'position' => 1,
		'diagnosis_role' => 'primary',
		'diagnosis_code' => $coded ? $primary_code : null,
		'diagnosis_label' => $coded ? trim(preg_replace('/\s*\([A-Z][0-9]{2}(?:\.[0-9A-Z]+)?\)\s*$/', '', $label)) : $label,
		'coding_state' => $coded ? 'coded' : 'uncoded',
		'display_text' => $label,
		'created_by_user_id' => $meta['doctor'],
		'created_at' => date('Y-m-d H:i:s', $meta['completed']),
	));
	$diagnosis_total++;

	if ($request_id % 7 === 0) {
		$secondary_label = $diagnosis_names[($request_id + 2) % count($diagnosis_names)];
		$secondary_code = '';
		if (preg_match('/\(([A-Z][0-9]{2}(?:\.[0-9A-Z]+)?)\)\s*$/', $secondary_label, $secondary_match) === 1) {
			$secondary_code = $secondary_match[1];
		}
		$insert('medicalrecord_diagnoses', array(
			'medicalrecord_id' => $record_id,
			'request_id' => $request_id,
			'position' => 2,
			'diagnosis_role' => 'secondary',
			'diagnosis_code' => $secondary_code,
			'diagnosis_label' => trim(preg_replace('/\s*\([A-Z][0-9]{2}(?:\.[0-9A-Z]+)?\)\s*$/', '', $secondary_label)),
			'coding_state' => 'coded',
			'display_text' => $secondary_label,
			'created_by_user_id' => $meta['doctor'],
			'created_at' => date('Y-m-d H:i:s', $meta['completed']),
		));
		$diagnosis_total++;
	}
}

/* Tiga pengecualian kritis ditandai "sudah ditindaklanjuti". Tanpa baris ini,
   keadaan "sudah ditandai" tidak pernah terlihat dan tombol tindak lanjut tidak
   pernah teruji. */
$followup_total = 0;
foreach ($request_meta as $request_id => $meta) {
	if ($followup_total >= 3) {
		break;
	}
	if ($meta['status'] !== 'Pending' || ($now - $meta['created']) < 4 * 3600) {
		continue;
	}
	$insert('monitoring_followups', array(
		'request_id' => $request_id,
		'marked_by_user_id' => 1,
		'note' => 'Sudah dihubungi lewat telepon; menunggu konfirmasi Puskesmas.',
		'marked_at' => $stamp(-3600 * ($followup_total + 1)),
	));
	$followup_total++;
}

/* Audit: cukup untuk membuat halaman audit dan riwayat kapabilitas punya isi. */
$audit_actions = array(
	'admin.login' => 'auth',
	'monitoring.followup_marked' => 'request',
	'puskesmas.staff_created' => 'puskesmas_staff',
	'puskesmas.staff_updated' => 'puskesmas_staff',
	'users.status_changed' => 'users',
	'landing_faq_created' => 'landing_faqs',
	'keluhan.updated' => 'keluhan',
);
$audit_index = 0;
foreach ($audit_actions as $action => $entity) {
	$audit_index++;
	$insert('audit_logs', array(
		'actor_user_id' => $audit_index % 2 === 0 ? 2 : 1,
		'action' => $action,
		'entity_type' => $entity,
		'entity_id' => (string) ($audit_index * 3),
		'ip_address' => '127.0.0.1',
		'user_agent' => 'fixture/lokal',
		'created_at' => $stamp(-$audit_index * 5400),
	));
}

/* Notifikasi admin: 6 belum dibaca supaya lencana di topbar tidak nol. */
for ($i = 1; $i <= 15; $i++) {
	$insert('notifications', array(
		'recipient_user_id' => 1,
		'recipient_role' => 'admin',
		'actor_user_id' => 201 + ($i % 40),
		'event_type' => 'request_created',
		'entity_type' => 'request',
		'entity_id' => (string) ($i * 11),
		'title' => 'Permintaan baru #' . ($i * 11),
		'message' => 'Permintaan baru masuk dan menunggu penugasan Puskesmas.',
		'is_read' => $i <= 9 ? 1 : 0,
		'read_at' => $i <= 9 ? $stamp(-$i * 3600) : null,
		'created_at' => $stamp(-$i * 7200),
	));
}
$insert('admin_notification_reads', array('user_id' => 1, 'last_seen_event_id' => 12));

/* Konten landing: nama blok mengikuti kunci yang dipakai `Landing_content`,
   sehingga halaman editor dan halaman publik membaca baris yang sama. */
$landing_blocks = array(
	array('hero', 'Layanan kesehatan jarak jauh', 'Konsultasi dengan tenaga kesehatan tanpa harus datang ke fasilitas.', 1),
	array('about', 'Tentang DocLink', 'DocLink menghubungkan warga dengan Puskesmas di wilayahnya.', 2),
	array('services', 'Layanan yang tersedia', 'Konsultasi umum, kunjungan rumah, dan pemantauan tindak lanjut.', 3),
	array('contact', 'Hubungi kami', 'Dinas Kesehatan Kota Cilegon, Jl. Merdeka No. 1.', 4),
);
foreach ($landing_blocks as $block) {
	$insert('landing_blocks', array(
		'block_key' => $block[0],
		'judul' => $block[1],
		'isi' => $block[2],
		'urutan' => $block[3],
		'aktif' => 1,
		'updated_by_user_id' => 1,
		'updated_at' => $stamp(-3 * 86400),
	));
}

$faqs = array(
	array('Apakah konsultasi DocLink berbayar?', 'Tidak. Layanan konsultasi dasar tidak dipungut biaya.', 1),
	array('Bagaimana cara memilih Puskesmas?', 'Sistem memilih Puskesmas terdekat berdasarkan lokasi Anda.', 2),
	array('Apakah data saya aman?', 'Data hanya dapat diakses tenaga kesehatan yang menangani permintaan Anda.', 3),
	array('Bisakah saya meminta kunjungan rumah?', 'Bisa, selama wilayah Anda masuk cakupan Puskesmas tujuan.', 4),
);
foreach ($faqs as $faq) {
	$insert('landing_faqs', array(
		'pertanyaan' => $faq[0],
		'jawaban' => $faq[1],
		'urutan' => $faq[2],
		'aktif' => 1,
		'updated_by_user_id' => 1,
		'updated_at' => $stamp(-4 * 86400),
	));
}

$keluhan_list = array(
	array('Umum', 'Demam', 'Demam lebih dari tiga hari'),
	array('Umum', 'Batuk', 'Batuk berdahak maupun kering'),
	array('Pencernaan', 'Diare', 'Buang air besar cair lebih dari tiga kali'),
	array('Pencernaan', 'Nyeri ulu hati', 'Nyeri atau perih di ulu hati'),
	array('Kulit', 'Ruam', 'Ruam atau gatal pada kulit'),
	array('Muskuloskeletal', 'Nyeri sendi', 'Nyeri pada sendi tanpa riwayat trauma'),
	array('Saraf', 'Sakit kepala', 'Sakit kepala berulang'),
	array('Pernapasan', 'Sesak napas', 'Sesak napas saat aktivitas ringan'),
);
foreach ($keluhan_list as $index => $row) {
	$insert('keluhan', array(
		'id_keluhan' => $index + 1,
		'kategori' => $row[0],
		'nama_keluhan' => $row[1],
		'deskripsi' => $row[2],
		'status' => $index === 7 ? 'nonaktif' : 'aktif',
		'create_user' => 'admin@lokal.test',
		'create_date' => $stamp(-20 * 86400),
	));
}

/* Wilayah: 8 kecamatan Kota Cilegon dan 9 Puskesmas dengan kode resmi. Kode
   resmi dipakai (bukan PKMxx) karena `Wilayah_seed` memetakan poligon ke kode
   ini; menyimpang dari daftar itu membuat uji seed wilayah gagal pada fixture
   yang seharusnya netral. */
$kecamatan_rows = array(
	'Cilegon' => array('10280101'),
	'Cibeber' => array('10280201'),
	'Ciwandan' => array('10280301'),
	'Pulomerak' => array('10280401'),
	'Gerogol' => array('10280402'),
	'Citangkil' => array('10280501', '2241001'),
	'Purwakarta' => array('10280601'),
	'Jombang' => array('10280701'),
);
$kecamatan_id = array();
$kecamatan_index = 0;
foreach ($kecamatan_rows as $nama => $codes) {
	$kecamatan_index++;
	$kecamatan_id[$nama] = $insert('kecamatan', array(
		'id_kecamatan' => $kecamatan_index,
		'nama_kecamatan' => $nama,
	), 'id_kecamatan');
}
$wilayah_names = array(
	'10280101' => 'Puskesmas Cilegon',
	'10280201' => 'Puskesmas Cibeber',
	'10280301' => 'Puskesmas Ciwandan',
	'10280401' => 'Puskesmas Pulomerak',
	'10280402' => 'Puskesmas Gerogol',
	'10280501' => 'Puskesmas Citangkil',
	'10280601' => 'Puskesmas Purwakarta',
	'10280701' => 'Puskesmas Jombang',
	'2241001' => 'Puskesmas Citangkil II',
);
$puskesmas_index = 0;
foreach ($kecamatan_rows as $nama => $codes) {
	foreach ($codes as $code) {
		$puskesmas_index++;
		$insert('puskesmas', array(
			'id_puskesmas' => $puskesmas_index,
			'kode_pkm' => $code,
			'nama_puskesmas' => $wilayah_names[$code],
			'id_kecamatan' => $kecamatan_id[$nama],
		));
	}
}

/* Konsultasi + terapi untuk 10 permintaan selesai, supaya halaman rekam medis
   punya baris yang bisa dibuka. */
$konsul_total = 0;
foreach ($request_meta as $request_id => $meta) {
	if ($konsul_total >= 10 || $meta['status'] !== 'Completed') {
		continue;
	}
	$konsul_id = $insert('konsultasi', array(
		'request_id' => $request_id,
		'diagnosa' => $diagnosis_names[$request_id % count($diagnosis_names)],
		'saran' => 'Istirahat cukup dan kontrol ulang bila keluhan menetap.',
		/* `kriteria` dipilih bergantian supaya halaman konsultasi punya dua
		   ragam baris (konsultasi biasa dan rujukan), bukan satu nilai seragam. */
		'kriteria' => $konsul_total % 3 === 0 ? 'Rujukan' : 'Konsultasi',
		'create_date' => $stamp(-2 * 86400),
		'create_user' => 'admin',
	), 'konsul_id');
	$insert('terapi', array(
		'konsul_id' => $konsul_id,
		'terapi' => 'Paracetamol 500 mg sesuai kebutuhan',
	));
	$konsul_total++;
}

for ($i = 1; $i <= 6; $i++) {
	$insert('feeds', array(
		'feedId' => $i,
		'subject' => 'Pengumuman layanan ' . $i,
		'body' => 'Informasi terbaru seputar layanan DocLink untuk warga.',
		'status' => 'aktif',
		'created_at' => $stamp(-$i * 86400),
	));
}

for ($i = 1; $i <= 5; $i++) {
	$insert('t_pengaduan', array(
		'judul' => 'Pengaduan contoh ' . $i,
		'isi' => 'Ringkasan pengaduan untuk mengisi tabel laporan.',
		'status' => 'baru',
		'created_at' => $stamp(-$i * 43200),
	));
}

$insert('clinical_schema_migrations', array('migration_id' => 'clinical_requests', 'state' => 'applied'));
$insert('clinical_schema_migrations', array('migration_id' => 'clinical_medicalrecords', 'state' => 'applied'));

/* ------------------------------------------------------------------------- */
/* Ringkasan                                                                  */
/* ------------------------------------------------------------------------- */

$counts = array();
foreach (array(
	'users' => 'akun', 'm_puskesmas' => 'fasilitas', 'puskesmas_staff' => 'staf',
	'requests' => 'permintaan', 'request_events' => 'peristiwa',
	'request_staff_assignments' => 'penugasan', 'medicalrecords' => 'rekam medis',
	'medicalrecord_diagnoses' => 'diagnosis',
	'monitoring_followups' => 'tindak lanjut', 'kecamatan' => 'kecamatan',
) as $table => $label) {
	$row = $db->query('SELECT COUNT(*) AS total FROM `' . $table . '`')->fetch_assoc();
	$counts[] = $label . '=' . (int) $row['total'];
}

$status_rows = $db->query(
	"SELECT request_status, COUNT(*) AS total FROM requests GROUP BY request_status ORDER BY request_status"
);
$status_summary = array();
while ($row = $status_rows->fetch_assoc()) {
	$status_summary[] = $row['request_status'] . '=' . (int) $row['total'];
}

echo '== seed: ' . implode(', ', $counts) . " ==\n";
echo '== status: ' . implode(', ', $status_summary) . " ==\n";
echo "== masuk: admin@lokal.test / Lokal#2026! ==\n";
echo "== selesai: fixture siap dipakai ==\n";

$db->close();
exit(0);






