<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Role_identity_policy
{
	public function normalize($value)
	{
		return preg_replace('/\D+/', '', trim((string) $value));
	}

	public function warga(array $values)
	{
		$raw = array(
			'nik' => trim((string) (isset($values['nik']) ? $values['nik'] : '')),
			'nomor_kk' => trim((string) (isset($values['nomor_kk']) ? $values['nomor_kk'] : '')),
			'nomor_bpjs_kis' => trim((string) (isset($values['nomor_bpjs_kis']) ? $values['nomor_bpjs_kis'] : '')),
		);
		$normalized = array(
			'nik' => $this->normalize($raw['nik']),
			'nomor_kk' => $this->normalize($raw['nomor_kk']),
			'nomor_bpjs_kis' => $this->normalize($raw['nomor_bpjs_kis']),
		);
		$errors = array();
		if (preg_match('/^[0-9 .-]+$/D', $raw['nik']) !== 1 || preg_match('/^[0-9]{16}$/D', $normalized['nik']) !== 1) {
			$errors['nik'] = 'NIK harus terdiri dari 16 angka.';
		}
		if (preg_match('/^[0-9 .-]+$/D', $raw['nomor_kk']) !== 1 || preg_match('/^[0-9]{16}$/D', $normalized['nomor_kk']) !== 1) {
			$errors['nomor_kk'] = 'Nomor Kartu Keluarga harus terdiri dari 16 angka.';
		}
		if (preg_match('/^[0-9 .-]+$/D', $raw['nomor_bpjs_kis']) !== 1 || preg_match('/^[0-9]{13}$/D', $normalized['nomor_bpjs_kis']) !== 1) {
			$errors['nomor_bpjs_kis'] = 'Nomor kartu BPJS/KIS harus terdiri dari 13 angka.';
		}

		return array('valid' => empty($errors), 'values' => $normalized, 'field_errors' => $errors);
	}

	public function nip($value)
	{
		$raw = trim((string) $value);
		$normalized = $this->normalize($value);
		return array(
			'valid' => preg_match('/^[0-9 .-]+$/D', $raw) === 1 && preg_match('/^[0-9]{18}$/D', $normalized) === 1,
			'value' => $normalized,
			'message' => 'NIP harus terdiri dari 18 angka.',
		);
	}

	public function mask($value, $visible_suffix = 4)
	{
		$value = $this->normalize($value);
		$visible_suffix = max(0, min(strlen($value), (int) $visible_suffix));
		if ($value === '') {
			return '';
		}
		return str_repeat('•', max(0, strlen($value) - $visible_suffix)) . substr($value, -$visible_suffix);
	}
}
