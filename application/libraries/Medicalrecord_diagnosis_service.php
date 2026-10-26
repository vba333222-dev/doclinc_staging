<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Medicalrecord_diagnosis_service
{
	const MAX_DIAGNOSES = 3;
	const MAX_LABEL_LENGTH = 255;

	public static function normalize($primary, $additional = array(), $additional_enabled = false)
	{
		if (!is_string($primary)) {
			return self::invalid('invalid_primary_type');
		}
		if ($additional_enabled && !is_array($additional)) {
			return self::invalid('invalid_additional_type');
		}

		$values = array($primary);
		if ($additional_enabled) {
			$values = array_merge($values, $additional);
		}
		if (count($values) > self::MAX_DIAGNOSES) {
			return self::invalid('too_many_diagnoses');
		}

		$diagnoses = array();
		$seen = array();
		foreach ($values as $index => $value) {
			if (!is_string($value)) {
				return self::invalid('invalid_diagnosis_type');
			}
			$value = trim(str_replace(array("\r\n", "\r"), "\n", $value));
			if ($value === '') {
				if ($index === 0) {
					return self::invalid('primary_required');
				}
				continue;
			}
			if (preg_match('//u', $value) !== 1
				|| preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
				return self::invalid('invalid_characters');
			}
			$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
			if ($length > self::MAX_LABEL_LENGTH) {
				return self::invalid('diagnosis_too_long');
			}
			$key = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
			if (isset($seen[$key])) {
				return self::invalid('duplicate_diagnosis');
			}
			$seen[$key] = true;
			$diagnoses[] = $value;
		}

		return array('valid' => true, 'diagnoses' => $diagnoses, 'error' => null);
	}

	public static function schemaReady($db)
	{
		if (!is_object($db) || !method_exists($db, 'table_exists') || !method_exists($db, 'field_exists')
			|| !$db->table_exists('medicalrecords') || !$db->field_exists('record_id', 'medicalrecords')
			|| !$db->table_exists('medicalrecord_diagnoses')) {
			return false;
		}
		foreach (array(
			'diagnosis_id', 'medicalrecord_id', 'request_id', 'position', 'diagnosis_role',
			'diagnosis_code', 'diagnosis_label', 'display_text', 'suggestion_term_id',
			'reference_source', 'reference_version', 'created_by_user_id',
		) as $field) {
			if (!$db->field_exists($field, 'medicalrecord_diagnoses')) {
				return false;
			}
		}
		return true;
	}

	public static function replace($db, $medicalrecord_id, $request_id, $created_by_user_id, array $diagnoses)
	{
		$medicalrecord_id = (int) $medicalrecord_id;
		$request_id = (int) $request_id;
		$created_by_user_id = (int) $created_by_user_id;
		if ($medicalrecord_id < 1 || $request_id < 1 || $created_by_user_id < 1
			|| count($diagnoses) < 1 || count($diagnoses) > self::MAX_DIAGNOSES
			|| !self::schemaReady($db)) {
			return false;
		}

		$db->where('medicalrecord_id', $medicalrecord_id);
		if (!$db->delete('medicalrecord_diagnoses')) {
			return false;
		}
		foreach (array_values($diagnoses) as $index => $diagnosis) {
			if (!is_string($diagnosis) || trim($diagnosis) === '') {
				return false;
			}
			// Diagnosis dari picker datang sebagai "KODE — label". Kode divalidasi
			// terhadap master ICD-10; bila tidak dikenal, teks disimpan apa adanya
			// dengan coding_state 'uncoded' supaya finalisasi menolaknya.
			$coded = self::resolveCode($db, $diagnosis);
			$row = array(
				'medicalrecord_id' => $medicalrecord_id,
				'request_id' => $request_id,
				'position' => $index + 1,
				'diagnosis_role' => $index === 0 ? 'primary' : 'secondary',
				'diagnosis_code' => $coded['code'],
				'diagnosis_label' => $coded['label'],
				'display_text' => $coded['label'],
				'suggestion_term_id' => $coded['term_id'],
				'coding_state' => $coded['code'] !== null ? 'coded' : 'uncoded',
				'reference_source' => $coded['source'],
				'reference_version' => $coded['version'],
				'created_by_user_id' => $created_by_user_id,
			);
			if (!$db->insert('medicalrecord_diagnoses', $row)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Memisahkan kode ICD-10 dari teks diagnosis dan memverifikasinya ke master.
	 * Hanya kode yang benar-benar ada di master yang dianggap terkode; selain itu
	 * diagnosis disimpan sebagai teks bebas (uncoded) dan tidak bisa difinalisasi.
	 */
	private static function resolveCode($db, $text)
	{
		$text = trim((string) $text);
		$plain = array('code' => null, 'label' => $text, 'term_id' => null, 'source' => null, 'version' => null);

		/* Pemisahan kode dari label ada di satu tempat (`Icd10_code_text`) supaya
		 * bentuk "Dermatitis atopik (L20.9)" yang diketik tangan ikut dikenali,
		 * bukan hanya "L20.9 — Dermatitis atopik" dari pemilih. */
		require_once __DIR__ . '/Icd10_code_text.php';
		$parsed = Icd10_code_text::extract($text);
		$code = $parsed['code'];
		$label = $parsed['label'];
		if ($code === null) {
			return $plain;
		}
		if (!is_object($db) || !method_exists($db, 'query') || !method_exists($db, 'table_exists')
			|| !$db->table_exists('clinical_suggestion_terms')) {
			return $plain;
		}
		$term = $db->query(
			"SELECT suggestion_term_id, preferred_label, source_dataset, source_version
			 FROM clinical_suggestion_terms
			 WHERE term_type='diagnosis' AND term_code=?
			 ORDER BY suggestion_term_id ASC LIMIT 1",
			array($code)
		)->row();
		if (!$term) {
			return $plain;
		}
		if ($label === null || $label === '') {
			$label = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $term->preferred_label));
		}
		if ($label === '') {
			return $plain;
		}
		return array(
			'code' => $code,
			'label' => $label,
			'term_id' => (int) $term->suggestion_term_id,
			'source' => (string) $term->source_dataset,
			'version' => (string) $term->source_version,
		);
	}

	private static function invalid($error)
	{
		return array('valid' => false, 'diagnoses' => array(), 'error' => $error);
	}
}
