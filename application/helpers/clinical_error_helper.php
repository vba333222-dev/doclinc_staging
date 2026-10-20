<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_clinical_error_catalog')) {
	/**
	 * Allow-list kode galat alur klinis.
	 *
	 * Endpoint klinis sebelumnya hanya membalas "Operasi tidak dapat diproses."
	 * sehingga pengguna tidak tahu langkah berikutnya. Katalog ini memetakan
	 * kode internal ke pesan Indonesia yang bisa ditindaklanjuti.
	 */
	function doclinc_clinical_error_catalog()
	{
		return array(
			'schema_unavailable' => array(503, 'Fitur klinis belum siap di server. Hubungi pengelola.'),
			'WRITE_FAILED' => array(500, 'Data klinis belum dapat disimpan. Coba lagi.'),
			'RESULT_WRITE_FAILED' => array(500, 'Hasil kunjungan belum dapat disimpan. Coba lagi.'),
			'DISPOSITION_CONFLICT' => array(409, 'Disposisi belum dapat disimpan. Coba lagi.'),
			'ASSIGNMENT_CONFLICT' => array(409, 'Penugasan belum dapat disimpan. Coba lagi.'),
			'WORKFLOW_ENROLLMENT_DISABLED' => array(409, 'Alur klinis belum diaktifkan untuk layanan ini.'),
			'WORKFLOW_NOT_ENROLLED' => array(409, 'Permintaan belum masuk alur klinis.'),
			'DISPOSITION_REQUIRED' => array(409, 'Dokter penanggung jawab belum menetapkan disposisi.'),
			'DISPOSITION_ALREADY_EXISTS' => array(409, 'Disposisi sudah ditetapkan. Gunakan revisi bila perlu diubah.'),
			'DISPOSITION_LOCKED' => array(423, 'Disposisi tidak dapat diubah karena kunjungan sudah berjalan.'),
			'DISPOSITION_VERSION_CONFLICT' => array(409, 'Disposisi sudah berubah. Muat ulang halaman.'),
			'DISPOSITION_NOT_FOUND' => array(404, 'Disposisi belum ditemukan.'),
			'MODE_LOCKED_BY_DISPOSITION' => array(409, 'Jenis layanan mengikuti disposisi dokter dan tidak dapat diubah di sini.'),
			'VISIT_ALREADY_STARTED' => array(409, 'Kunjungan sudah berjalan. Nakes tidak dapat diganti.'),
			'PERFORMER_LOCKED' => array(409, 'Batalkan atau selesaikan penugasan Nakes sebelum mengubah jenis layanan.'),
			'HANDOVER_LOCKED' => array(409, 'Dokter penanggung jawab tidak dapat diganti setelah disposisi atau kunjungan dimulai.'),
			'REQUIRED_PROFESSION_MISMATCH' => array(422, 'Pilih Nakes dengan profesi yang diminta disposisi.'),
			'PLACEMENT_MISSING' => array(422, 'Nakes belum terdaftar aktif di Puskesmas ini.'),
			'CORRECTION_REASON_REQUIRED' => array(422, 'Isi alasan koreksi.'),
			'CLINICAL_REVIEW_REQUIRED' => array(409, 'Hasil kunjungan harus disetujui dokter terlebih dahulu.'),
			'CLINICAL_CLOSURE_REQUIRED' => array(409, 'Selesaikan melalui alur klinis: hasil kunjungan, review, finalisasi, lalu tutup.'),
			'CLINICAL_CLOSURE_NOT_ENROLLED' => array(409, 'Permintaan belum masuk alur klinis.'),
			'CLINICAL_CLOSURE_NOT_READY' => array(409, 'Alur klinis belum siap ditutup.'),
			'CLINICAL_ALREADY_CLOSED' => array(409, 'Konsultasi sudah ditutup.'),
			'CLINICAL_RECORD_NOT_FOUND' => array(404, 'Rekam medis belum tersedia.'),
			'CLINICAL_RECORD_ALREADY_FINALIZED' => array(409, 'Rekam medis sudah difinalisasi.'),
			'CLINICAL_RECORD_NOT_FINALIZED' => array(409, 'Finalisasi rekam medis terlebih dahulu.'),
			'ACCESS_DENIED' => array(403, 'Anda tidak memiliki akses.'),
			'RESULT_NOT_FOUND' => array(404, 'Hasil kunjungan tidak ditemukan.'),
			'RESULT_CONFLICT' => array(409, 'Draf hasil sudah berubah. Muat ulang halaman lalu coba lagi.'),
			'STALE_DRAFT_REVISION' => array(409, 'Draf sudah diperbarui. Muat ulang halaman lalu coba lagi.'),
			'RESULT_ALREADY_SUBMITTED' => array(409, 'Hasil sudah dikirim. Gunakan koreksi bila perlu diperbaiki.'),
			'RESULT_NOT_LATEST' => array(409, 'Hasil kunjungan sudah berubah. Muat ulang halaman.'),
			'INVALID_RESULT_PAYLOAD' => array(422, 'Format hasil kunjungan tidak valid.'),
			'INVALID_MEASUREMENT_REFERENCES' => array(422, 'Tanda vital yang dipilih tidak sesuai penugasan.'),
			'SUBMISSION_KEY_CONFLICT' => array(409, 'Pengiriman sebelumnya berbeda. Muat ulang halaman.'),
			'INVALID_WORKFLOW_STATE' => array(409, 'Status kunjungan belum mengizinkan langkah ini.'),
			'VISIT_NOT_REQUIRED' => array(409, 'Disposisi dokter tidak meminta kunjungan.'),
			'REQUEST_NOT_ACCEPTED' => array(409, 'Permintaan belum diterima Puskesmas.'),
			'REQUEST_NOT_FOUND' => array(404, 'Permintaan tidak ditemukan.'),
			'REQUEST_CANCELLED' => array(409, 'Permintaan sudah dibatalkan.'),
			'NOT_RESPONSIBLE_DOCTOR' => array(403, 'Hanya dokter penanggung jawab yang dapat melakukan langkah ini.'),
			'NOT_COMMAND_CENTER' => array(403, 'Hanya akun Puskesmas yang dapat melakukan langkah ini.'),
			'IDEMPOTENCY_KEY_CONFLICT' => array(409, 'Permintaan sebelumnya berbeda. Muat ulang halaman.'),
			'REVIEW_ALREADY_EXISTS' => array(409, 'Hasil ini sudah direview.'),
			'REVIEW_KEY_CONFLICT' => array(409, 'Review sebelumnya berbeda. Muat ulang halaman.'),
			'INVALID_REVIEW_INPUT' => array(422, 'Data review tidak valid.'),
			'INVALID_AMENDMENT_INPUT' => array(422, 'Data koreksi rekam medis tidak valid.'),
			'INVALID_AMENDMENT_FIELD' => array(422, 'Kolom rekam medis tidak dapat dikoreksi.'),
			'NO_CLINICAL_CHANGE' => array(422, 'Nilai koreksi sama dengan nilai sebelumnya.'),
			'AMENDMENT_KEY_CONFLICT' => array(409, 'Koreksi sebelumnya berbeda. Muat ulang halaman.'),
			'INVALID_FINALIZATION_INPUT' => array(422, 'Data finalisasi tidak valid.'),
			'DIAGNOSIS_REQUIRED' => array(422, 'Isi diagnosis utama minimal 2 karakter.'),
			'INVALID_CLINICAL_RECORD_INPUT' => array(422, 'Data rekam medis tidak valid.'),
			'FINALIZATION_KEY_CONFLICT' => array(409, 'Finalisasi sebelumnya berbeda. Muat ulang halaman.'),
			'INVALID_CONSULTATION_MODE' => array(409, 'Jenis layanan belum ditetapkan.'),
			'CLINICAL_CLOSURE_MODE_MISMATCH' => array(409, 'Jenis layanan berubah. Muat ulang halaman.'),
			'CLINICAL_CLOSURE_FORBIDDEN' => array(403, 'Hanya dokter penanggung jawab yang dapat menutup konsultasi.'),
			'method_not_allowed' => array(405, 'Metode tidak diizinkan.'),
		);
	}
}

if (!function_exists('doclinc_clinical_error_message')) {
	function doclinc_clinical_error_message($code)
	{
		$code = (string) $code;
		$catalog = doclinc_clinical_error_catalog();
		if (isset($catalog[$code])) {
			return $catalog[$code][1];
		}
		return 'Langkah klinis belum dapat diproses. Coba lagi atau muat ulang halaman.';
	}
}

if (!function_exists('doclinc_clinical_http_status')) {
	function doclinc_clinical_http_status($code)
	{
		$code = (string) $code;
		$catalog = doclinc_clinical_error_catalog();
		if (isset($catalog[$code])) {
			return (int) $catalog[$code][0];
		}
		return 422;
	}
}

if (!function_exists('doclinc_clinical_error_payload')) {
	function doclinc_clinical_error_payload($code, array $context = array())
	{
		$code = (string) $code;
		$payload = array(
			'success' => false,
			'safe_error_code' => $code,
			'message' => doclinc_clinical_error_message($code),
		);
		if (!empty($context['field'])) {
			$payload['field'] = (string) $context['field'];
		}
		return $payload;
	}
}
