<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Visit_vital_signs_policy
{
	private $ranges = array(
		'systolic' => array(50, 300),
		'diastolic' => array(30, 200),
		'pulse' => array(20, 250),
		'respiratory_rate' => array(5, 80),
		'temperature_c' => array(30, 45),
		'oxygen_saturation' => array(50, 100),
	);

	public function normalize(array $input)
	{
		$result = array();
		$has_value = false;
		foreach ($this->ranges as $field => $range) {
			$value = isset($input[$field]) ? trim((string) $input[$field]) : '';
			if ($value === '') {
				$result[$field] = null;
				continue;
			}
			if (!is_numeric($value)) {
				return $this->failure('Masukkan angka yang sesuai.');
			}
			$number = (float) $value;
			if ($field !== 'temperature_c' && floor($number) !== $number) {
				return $this->failure('Masukkan angka yang sesuai.');
			}
			if ($number < $range[0] || $number > $range[1]) {
				return $this->failure('Nilai tanda vital di luar batas.');
			}
			$result[$field] = $field === 'temperature_c' ? round($number, 1) : (int) round($number);
			$has_value = true;
		}

		$notes = trim((string) ($input['notes'] ?? ''));
		if (strlen($notes) > 1000) {
			return $this->failure('Catatan maksimal 1.000 karakter.');
		}
		if (!$has_value && $notes === '') {
			return $this->failure('Isi sedikitnya satu hasil pemeriksaan.');
		}
		$result['notes'] = $notes === '' ? null : $notes;
		return array('valid' => true, 'values' => $result);
	}

	private function failure($message)
	{
		return array('valid' => false, 'message' => (string) $message);
	}
}
