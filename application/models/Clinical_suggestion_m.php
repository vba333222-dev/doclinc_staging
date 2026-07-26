<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Clinical_suggestion_m extends CI_Model
{
	const MAX_RESULTS = 10;

	public function is_ready()
	{
		return $this->db->table_exists('clinical_suggestion_terms')
			&& $this->db->table_exists('clinical_suggestion_aliases')
			&& $this->db->table_exists('clinical_suggestion_import_batches');
	}

	public function search($type, $query, $environment, $limit = self::MAX_RESULTS)
	{
		$limit = max(1, min(self::MAX_RESULTS, (int) $limit));
		$normalized = self::normalize($query);
		if (self::text_length($normalized) < 2 || !$this->is_ready()) {
			return array();
		}

		$escaped = $this->db->escape_like_str($normalized);
		$prefix = $escaped . '%';
		$contains = '%' . $escaped . '%';
		$sql = "SELECT
			t.suggestion_term_id,
			t.reference_key,
			t.term_type,
			t.term_code,
			t.preferred_label,
			t.source_name,
			t.source_version
		FROM clinical_suggestion_terms AS t
		INNER JOIN clinical_suggestion_import_batches AS b
			ON b.suggestion_import_batch_id = t.suggestion_import_batch_id
		WHERE t.term_type = ?
			AND t.environment_scope = ?
			AND t.active_state = 1
			AND b.batch_state = 'applied'
			AND (
				t.term_code = ?
				OR t.term_code LIKE ? ESCAPE '!'
				OR t.normalized_label LIKE ? ESCAPE '!'
				OR t.normalized_label LIKE ? ESCAPE '!'
				OR EXISTS (
					SELECT 1
					FROM clinical_suggestion_aliases AS a
					WHERE a.suggestion_term_id = t.suggestion_term_id
						AND a.active_state = 1
						AND a.normalized_alias LIKE ? ESCAPE '!'
				)
			)
		ORDER BY CASE
			WHEN t.term_code = ? THEN 1
			WHEN t.term_code LIKE ? ESCAPE '!' THEN 2
			WHEN t.normalized_label LIKE ? ESCAPE '!' THEN 3
			WHEN EXISTS (
				SELECT 1
				FROM clinical_suggestion_aliases AS ar
				WHERE ar.suggestion_term_id = t.suggestion_term_id
					AND ar.active_state = 1
					AND ar.normalized_alias LIKE ? ESCAPE '!'
			) THEN 4
			ELSE 5
		END, t.normalized_label ASC, t.term_code ASC
		LIMIT " . ($limit + 1);

		$bindings = array(
			$type, $environment,
			$normalized, $prefix, $prefix, $contains, $prefix,
			$normalized, $prefix, $prefix, $prefix,
		);
		return $this->db->query($sql, $bindings)->result_array();
	}

	public static function normalize($value)
	{
		$value = preg_replace('/\s+/u', ' ', trim((string) $value));
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	public static function text_length($value)
	{
		return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
	}
}
