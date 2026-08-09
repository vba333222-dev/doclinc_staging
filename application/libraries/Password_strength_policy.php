<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Password_strength_policy
{
	public function validate($password, $confirmation = null)
	{
		if (!is_string($password) || ($confirmation !== null && !is_string($confirmation))) {
			return 'invalid_payload';
		}
		if ($confirmation !== null && !hash_equals($password, $confirmation)) {
			return 'confirmation_mismatch';
		}
		if (strlen($password) < 8 || strlen($password) > 72) {
			return 'invalid_length';
		}
		if (preg_match('/\s/', $password) === 1 || preg_match('/[\x00-\x1F\x7F]/', $password) === 1) {
			return 'whitespace_not_allowed';
		}
		if (preg_match('/[A-Z]/', $password) !== 1) {
			return 'uppercase_required';
		}
		if (preg_match('/[a-z]/', $password) !== 1) {
			return 'lowercase_required';
		}
		if (preg_match('/[0-9]/', $password) !== 1) {
			return 'number_required';
		}
		if (preg_match('/[^A-Za-z0-9\s]/', $password) !== 1) {
			return 'special_required';
		}
		return null;
	}

	public function message($code)
	{
		if ($code === 'confirmation_mismatch') {
			return 'Konfirmasi password tidak sama.';
		}
		if ($code === 'invalid_length') {
			return 'Password harus terdiri dari 8 sampai 72 karakter.';
		}
		if ($code === 'whitespace_not_allowed') {
			return 'Password tidak boleh mengandung spasi.';
		}
		if ($code === 'uppercase_required') {
			return 'Password harus memiliki setidaknya satu huruf besar.';
		}
		if ($code === 'lowercase_required') {
			return 'Password harus memiliki setidaknya satu huruf kecil.';
		}
		if ($code === 'number_required') {
			return 'Password harus memiliki setidaknya satu angka.';
		}
		if ($code === 'special_required') {
			return 'Password harus memiliki setidaknya satu karakter khusus.';
		}
		return 'Password belum memenuhi ketentuan keamanan.';
	}
}
