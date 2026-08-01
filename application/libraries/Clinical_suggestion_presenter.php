<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_suggestion_presenter
{
	const WHO_ICD10_DATASET = '20b_master_diagnosis_icd10_who_2019.json';
	const WHO_ICD10_STANDARD = 'WHO ICD-10';
	const WHO_ICD10_EDITION = '2019';
	const FORNAS_DATASET = '21_master_obat_fornas.json';
	const FORNAS_SOURCE = 'e-Fornas Kementerian Kesehatan RI';
	const FORNAS_STANDARD = 'Formularium Nasional';

	public static function present(array $row)
	{
		$type = strtolower(trim((string) ($row['term_type'] ?? '')));
		if (!in_array($type, array('complaint', 'symptom', 'diagnosis', 'medicine'), true)) {
			return null;
		}

		$label = self::cleanLabel($row['preferred_label'] ?? '');
		$reference_key = trim((string) ($row['reference_key'] ?? ''));
		if ($label === null || $reference_key === '' || strlen($reference_key) > 128) {
			return null;
		}

		$code = trim((string) ($row['term_code'] ?? ''));
		$item = array(
			'id' => $type . ':' . $reference_key,
			'type' => $type,
			'code' => $code,
			'label' => $label,
			'display' => $label,
			'value' => $label,
			'meta' => '',
			'supporting' => '',
		);

		if ($type === 'medicine') {
			if (trim((string) ($row['source_dataset'] ?? '')) !== self::FORNAS_DATASET
				|| trim((string) ($row['source_name'] ?? '')) !== self::FORNAS_SOURCE) {
				return null;
			}
			$item['meta'] = self::FORNAS_STANDARD;
			$item['standard'] = self::FORNAS_STANDARD;
			return $item;
		}

		if ($type !== 'diagnosis') {
			return $item;
		}

		$code = strtoupper($code);
		$dataset = trim((string) ($row['source_dataset'] ?? ''));
		if ($dataset !== self::WHO_ICD10_DATASET
			|| trim((string) ($row['source_version'] ?? '')) !== self::WHO_ICD10_EDITION
			|| preg_match('/\A[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?[†*]?\z/u', $code) !== 1) {
			return null;
		}

		$matched_alias = self::cleanLabel($row['matched_alias'] ?? '');
		if ($matched_alias !== null && self::normalize($matched_alias) !== self::normalize($label)) {
			$item['display'] = $matched_alias;
			$item['supporting'] = $label;
		}
		$item['code'] = $code;
		$item['value'] = $code . ' — ' . $label;
		$item['meta'] = 'ICD-10 ' . $code . ' · WHO ' . self::WHO_ICD10_EDITION;
		$item['official_label'] = $label;
		$item['standard'] = self::WHO_ICD10_STANDARD;
		$item['edition'] = self::WHO_ICD10_EDITION;
		return $item;
	}

	private static function cleanLabel($value)
	{
		$label = preg_replace('/\s+/u', ' ', trim((string) $value));
		if (!is_string($label) || $label === '' || preg_match('//u', $label) !== 1
			|| preg_match('/[\x00-\x1F\x7F]/u', $label) === 1
			|| preg_match('/(?:doclinc|doklinc|doclink)/iu', $label) === 1) {
			return null;
		}
		$length = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
		return $length <= 255 ? $label : null;
	}

	private static function normalize($value)
	{
		$value = preg_replace('/\s+/u', ' ', trim((string) $value));
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}
}
