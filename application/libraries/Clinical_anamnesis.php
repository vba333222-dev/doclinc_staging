<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_anamnesis
{
	const MAX_LENGTH = 5000;

	public static function normalize($value)
	{
		if ($value === null) {
			$value = '';
		}
		if (!is_string($value)) {
			return array('valid' => false, 'value' => null, 'error' => 'invalid_payload_type');
		}

		$value = str_replace(array("\r\n", "\r"), "\n", $value);
		if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
			return array('valid' => false, 'value' => null, 'error' => 'invalid_control_character');
		}
		$value = trim($value);
		$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
		if ($length > self::MAX_LENGTH) {
			return array('valid' => false, 'value' => null, 'error' => 'value_too_long');
		}

		return array('valid' => true, 'value' => $value === '' ? null : $value, 'error' => null);
	}

	public static function schema_ready($db)
	{
		return is_object($db)
			&& method_exists($db, 'table_exists')
			&& method_exists($db, 'field_exists')
			&& $db->table_exists('medicalrecords')
			&& $db->field_exists('record_id', 'medicalrecords')
			&& $db->field_exists('request_id', 'medicalrecords')
			&& $db->field_exists('anamnesis', 'medicalrecords');
	}
}
