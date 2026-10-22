<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Bolehkah sesi lama "diadopsi" saat fitur sesi-tunggal baru dinyalakan?
 *
 * Sesi yang dibuat sebelum fitur aktif tidak punya `normal_session_token`, jadi
 * gate akan menendang SEMUA pengguna serentak pada permintaan berikutnya. Adopsi
 * memberi sesi itu satu binding baru supaya penegakan mulai berlaku tanpa
 * memaksa semua orang login ulang di menit yang sama.
 *
 * Hanya 'binding_missing' yang boleh diadopsi, yaitu saat akun belum punya baris
 * binding sama sekali. Bila baris sudah ada, baris itu milik sesi yang login
 * belakangan - sesi lama harus tetap kalah. Aturan ini fail-closed: keraguan
 * berarti tendang, bukan adopsi.
 */
class Session_binding_adoption_policy
{
	const ADOPTABLE_DIAGNOSIS = 'binding_missing';

	public function shouldAdopt($adoption_enabled, $diagnosis)
	{
		return $adoption_enabled === true
			&& (string) $diagnosis === self::ADOPTABLE_DIAGNOSIS;
	}
}
