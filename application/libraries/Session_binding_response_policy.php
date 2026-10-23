<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Bentuk jawaban gate sesi, dipisahkan supaya bisa diuji tanpa menjalankan
 * framework.
 *
 * Isinya keputusan murni: sebab apa yang aman ditampilkan, ke mana halaman
 * dialihkan, apa isi badan JSON, dan apakah permintaan ini kehilangan isian
 * formulir. Gate tinggal menjalankan hasilnya.
 */
class Session_binding_response_policy
{
	/** Sebab yang aman ditampilkan, dipetakan dari hasil diagnose(). */
	public function reasonCode($diagnosis)
	{
		$map = array(
			'binding_superseded' => 'other_device',
			'binding_revoked' => 'signed_out',
			'account_inactive' => 'account_inactive',
		);
		return isset($map[$diagnosis]) ? $map[$diagnosis] : 'expired';
	}

	/**
	 * Permintaan formulir (bukan AJAX) kehilangan isian saat dialihkan ke
	 * login. Hanya POST yang dihitung: GET tidak membawa isian untuk hilang.
	 */
	public function isUnsaved($is_json, $http_method)
	{
		return $is_json !== true && strtoupper(trim((string) $http_method)) === 'POST';
	}

	public function redirectTarget($reason, $unsaved)
	{
		$target = 'login?session=expired&reason=' . rawurlencode((string) $reason);
		if ($unsaved === true) {
			$target .= '&unsaved=1';
		}
		return $target;
	}

	/** Badan jawaban untuk pemanggil skrip. Kode galat lama tetap dipertahankan. */
	public function jsonBody($reason)
	{
		return array(
			'success' => false,
			'safe_error_code' => 'session_expired',
			'reason' => (string) $reason,
		);
	}

	/**
	 * Metadata audit pencabutan sesi.
	 *
	 * Pencabutan dicatat pada SEMUA jalur, termasuk AJAX. Aturan lama ("hanya
	 * permintaan halaman") terdengar hemat, tetapi salah di lapangan: deteksi
	 * pertama hampir selalu datang dari polling AJAX - beranda memanggil
	 * home/livekit_incoming_call tiap 3 detik. Terbukti di staging 2026-10-23:
	 * tendangan `other_device` hanya menghasilkan 401, nol baris audit, jadi
	 * "siapa ditendang kapan dan oleh apa" tidak bisa ditelusuri.
	 *
	 * Banjir baris tidak terjadi karena sesi dihancurkan sebelum audit ditulis:
	 * permintaan berikutnya dari peramban yang sama tidak lagi membawa
	 * `logged_in`, sehingga gate berhenti lebih awal. `channel` disimpan supaya
	 * operator tahu jalur mana yang mendeteksi.
	 */
	public function auditMetadata($reason, $controller, $method, $is_json)
	{
		return array(
			'reason' => (string) $reason,
			'controller' => (string) $controller,
			'method' => (string) $method,
			'channel' => $is_json === true ? 'json' : 'html',
		);
	}

	/**
	 * Pesan di halaman login setelah sesi berakhir.
	 *
	 * Dipakai DUA aplikasi (aplikasi utama dan panel admin) supaya kalimat yang
	 * dibaca pengguna tidak berbeda antar-permukaan. Sebabnya dibedakan karena
	 * akibatnya berbeda: sesi yang diakhiri oleh login di perangkat lain adalah
	 * sinyal keamanan, bukan sekadar kedaluwarsa.
	 *
	 * Mengembalikan array('message' => string, 'level' => 'success|info|warning').
	 * Message kosong berarti tidak ada yang perlu ditampilkan.
	 */
	public function noticeFor($session_param, $reason, $unsaved)
	{
		if ((string) $session_param !== 'expired') {
			return array('message' => '', 'level' => 'success');
		}
		$reason = strtolower(trim((string) $reason));
		if ($reason === 'other_device') {
			$message = 'Sesi ini diakhiri karena akun Anda masuk di perangkat lain. Jika bukan Anda, segera ganti kata sandi.';
			$level = 'warning';
		} elseif ($reason === 'signed_out') {
			$message = 'Sesi Anda sudah diakhiri. Masuk lagi untuk melanjutkan.';
			$level = 'info';
		} elseif ($reason === 'account_inactive') {
			$message = 'Akun ini tidak aktif sehingga sesinya diakhiri. Hubungi admin puskesmas.';
			$level = 'warning';
		} else {
			$message = 'Sesi berakhir. Masuk lagi.';
			$level = 'success';
		}
		if ((string) $unsaved === '1') {
			$message .= ' Perubahan yang belum tersimpan dibatalkan.';
		}
		return array('message' => $message, 'level' => $level);
	}

	/** Nama cookie sesi yang harus dihapus; kosong berarti tidak ada yang dihapus. */
	public function sessionCookieName($configured_name)
	{
		return trim((string) $configured_name);
	}
}
