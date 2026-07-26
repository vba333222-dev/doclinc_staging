<?php

require_once __DIR__ . '/NakesImportException.php';
require_once __DIR__ . '/XlsxReader.php';

class DoclincNakesSource
{
	public const SHA256 = 'cd9cd8678065e89a4ab8f55049f479f73fe6294bc99a61312720edc0ecefca6c';
	public const SIZE = 19146;
	public const TOTAL = 26;
	public const SHEETS = array('Cibeber', 'Citangkil', 'Cilegon', 'Purwakarta', 'Pulomerak', 'Jombang', 'Grogol', 'Ciwandan', 'Citangkil II');
	public const COUNTS = array('Cibeber' => 4, 'Citangkil' => 3, 'Cilegon' => 3, 'Purwakarta' => 2, 'Pulomerak' => 2, 'Jombang' => 3, 'Grogol' => 3, 'Ciwandan' => 3, 'Citangkil II' => 3);
	public const CODES = array('Cibeber' => '10280201', 'Cilegon' => '10280101', 'Citangkil' => '10280501', 'Citangkil II' => '2241001', 'Ciwandan' => '10280301', 'Grogol' => '10280402', 'Jombang' => '10280701', 'Pulomerak' => '10280401', 'Purwakarta' => '10280601');

	public function inspect($path, $enforceFilePolicy = true)
	{
		$resolved = $this->validatePath($path, $enforceFilePolicy);
		$data=file_get_contents($resolved);
		if (!is_string($data) || strlen($data) !== self::SIZE) {
			throw new NakesImportException('source_size_mismatch');
		}
		if (!hash_equals(self::SHA256, strtolower(hash('sha256', $data)))) {
			throw new NakesImportException('source_checksum_mismatch');
		}
		$reader = new DoclincXlsxReader();
		$result=$this->validateWorkbook($reader->readData($data));$data=null;return $result;
	}

	public function validateWorkbook(array $sheets)
	{
		if (array_column($sheets, 'name') !== self::SHEETS) {
			throw new NakesImportException('sheet_contract_mismatch');
		}
		$records = array();
		$names = array();
		$phones = array();
		$inferences = 0;
		$assignment_normalizations = 0;
		$sheet_counts = array();
		foreach ($sheets as $sheet) {
			$name = $sheet['name'];
			$header_row = null;
			foreach ($sheet['rows'] as $row_number => $values) {
				$normalized = array();
				for ($column = 1; $column <= 5; $column++) {
					$normalized[] = $this->clean($values[$column] ?? '');
				}
				if ($normalized === array('NO', 'NAMA', 'PROFESI', 'PENUGASAN', 'NO. TELPON')) {
					if ($header_row !== null) throw new NakesImportException('duplicate_header_row');
					$header_row = (int) $row_number;
				}
			}
			if ($header_row === null) throw new NakesImportException('header_contract_mismatch');
			foreach($sheet['merged_ranges']??array()as $range)if((int)$range['end_row']>=$header_row&&(int)$range['start_column']<=5&&(int)$range['end_column']>=1)throw new NakesImportException('merged_data_region_rejected');

			$sequence = 0;
			foreach ($sheet['rows'] as $row_number => $values) {
				if ((int) $row_number <= $header_row) continue;
				foreach ($values as $column => $value) {
					if ($column > 5 && $this->clean($value) !== '') throw new NakesImportException('extra_source_column');
				}
				$cells = array();
				for ($column = 1; $column <= 5; $column++) $cells[] = $this->clean($values[$column] ?? '');
				if (count(array_filter($cells, static function ($value) { return $value !== ''; })) === 0) continue;
				$sequence++;
				if ($cells[0] !== (string) $sequence || $cells[1] === '' || $cells[4] === '') throw new NakesImportException('source_row_contract_mismatch');
				$profession = $cells[2];
				$assignment = $cells[3] === '' ? null : $cells[3];
				$inferred = false;
				if ($profession === '' && $name === 'Grogol') {
					$profession = 'Dokter';
					$assignment = null;
					$inferred = true;
					$inferences++;
				}
				if (strcasecmp($profession, 'Dokter') !== 0 || ($assignment === null && $name !== 'Grogol')) throw new NakesImportException('profession_assignment_contract_mismatch');
				if ($assignment !== null && preg_match('/Ketua Tim\s*Pelayanan Doklinc/iu', $assignment) === 1) {
					$fixed = preg_replace('/Ketua Tim\s*Pelayanan Doklinc/iu', 'Ketua Tim Pelayanan Doklinc', $assignment);
					if ($fixed !== $assignment) $assignment_normalizations++;
					$assignment = $fixed;
				}
				$phone = $this->phone($cells[4]);
				$name_key = $this->normalizedNameKey($cells[1]);
				if (isset($names[$name_key])) throw new NakesImportException('duplicate_name');
				if (isset($phones[$phone])) throw new NakesImportException('duplicate_phone');
				$names[$name_key] = true;
				$phones[$phone] = true;
				$records[] = array('sheet' => $name, 'kode_pkm' => self::CODES[$name], 'nama' => $cells[1], 'profesi' => 'Dokter', 'penugasan' => $assignment, 'no_hp' => $phone, 'source_inference' => $inferred);
			}
			$sheet_counts[$name] = $sequence;
			if ($sequence !== self::COUNTS[$name]) throw new NakesImportException('sheet_record_count_mismatch');
		}
		if (count($records) !== self::TOTAL || $inferences !== 3) throw new NakesImportException('source_record_count_mismatch');
		return array('records' => $records, 'sheet_counts' => $sheet_counts, 'source_inference_count' => $inferences, 'nullable_assignment_count' => 3, 'assignment_normalization_count' => $assignment_normalizations);
	}

	public function username($name, $code, $phone, array $reserved)
	{
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
		$slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', is_string($ascii) ? $ascii : $name));
		$slug = trim($slug, '-');
		if ($slug === '') throw new NakesImportException('username_slug_empty');
		$max = 77;
		$base = 'nakes.' . $slug . '.' . $code;
		$candidate = substr($base, 0, $max);
		$key = strtolower($candidate);
		if (isset($reserved[$key])) {
			$suffix = '.' . substr(hash('sha256', strtolower($name) . '|' . $code . '|' . $phone), 0, 10);
			$candidate = substr($base, 0, $max - strlen($suffix)) . $suffix;
			$key = strtolower($candidate);
			if (isset($reserved[$key])) throw new NakesImportException('deterministic_username_collision');
		}
		return $candidate;
	}

	private function validatePath($path, $enforce)
	{
		if (!is_string($path) || $path === '' || !$this->isAbsolute($path) || is_link($path)) throw new NakesImportException('source_path_rejected');
		$resolved = realpath($path);
		if ($resolved === false || !is_file($resolved) || is_link($resolved)) throw new NakesImportException('source_file_rejected');
		$input_normalized = strtolower(str_replace('\\', '/', rtrim($path, '/\\')));
		$resolved_normalized = strtolower(str_replace('\\', '/', rtrim($resolved, '/\\')));
		if ($input_normalized !== $resolved_normalized || preg_match('#(?:^|/)[.]{1,2}(?:/|$)#', str_replace('\\', '/', $path)) === 1) throw new NakesImportException('source_symlink_component_rejected');
		$root = realpath(dirname(__DIR__, 3));
		if ($root !== false && $this->contains($root, $resolved)) throw new NakesImportException('source_inside_repository');
		$allowed = array_filter(array_map('trim', explode(PATH_SEPARATOR, (string) (getenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_ROOTS') ?: ''))));
		if ($enforce && empty($allowed)) throw new NakesImportException('allowed_source_roots_required');
		if ($enforce) {
			$matched = false;
			foreach ($allowed as $allowed_root) {
				$allowed_real = realpath($allowed_root);
				if ($allowed_real !== false && $this->contains($allowed_real, $resolved)) $matched = true;
			}
			if (!$matched) throw new NakesImportException('source_root_not_allowed');
		}
		$test_mount_override = in_array(strtolower(trim((string) (getenv('DOCLINC_NAKES_DISPOSABLE_TEST') ?: ''))), array('1','true','yes','on'), true)
			&& in_array(strtolower(trim((string) (getenv('DOCLINC_NAKES_ALLOW_BIND_MOUNT_SOURCE_FOR_TEST') ?: ''))), array('1','true','yes','on'), true);
		if (DIRECTORY_SEPARATOR === '/' && $enforce && !$test_mount_override) {
			$mode = fileperms($resolved) & 0777;
			if (($mode & 0137) !== 0 || ($mode & 0400) === 0) throw new NakesImportException('source_permissions_rejected');
			$allowed_uids = array_filter(array_map('intval', explode(',', (string) (getenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_UIDS') ?: ''))));
			if (empty($allowed_uids) || !in_array(fileowner($resolved), $allowed_uids, true)) throw new NakesImportException('source_owner_rejected');
			$allowed_gids = array_filter(array_map('intval', explode(',', (string) (getenv('DOCLINC_NAKES_IMPORT_ALLOWED_SOURCE_GIDS') ?: ''))));
			if (empty($allowed_gids) || !in_array(filegroup($resolved), $allowed_gids, true)) throw new NakesImportException('source_group_rejected');
			$parent = dirname($resolved);
			$parent_mode = fileperms($parent) & 0777;
			if (($parent_mode & 0027) !== 0 || ($parent_mode & 0500) !== 0500) throw new NakesImportException('source_directory_permissions_rejected');
		}
		return $resolved;
	}

	private function contains($root, $path)
	{
		$root = rtrim(str_replace('\\', '/', strtolower($root)), '/') . '/';
		$path = str_replace('\\', '/', strtolower($path));
		return strpos($path . '/', $root) === 0;
	}

	private function isAbsolute($path)
	{
		return preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $path) === 1;
	}

	private function clean($value)
	{
		$value = trim((string) $value);
		return preg_replace('/\s+/u', ' ', $value);
	}

	private function phone($value)
	{
		$value=trim((string)$value);
		if(preg_match('/[eE]/',$value)===1||preg_match('/\A[+0-9(). -]+\z/',$value)!==1)throw new NakesImportException('invalid_phone');
		$value = preg_replace('/[(). -]/', '', $value);
		if (strpos($value, '0') === 0) $value = '+62' . substr($value, 1);
		elseif (strpos($value, '62') === 0) $value = '+' . $value;
		if (preg_match('/^\+628[0-9]{7,11}$/', $value) !== 1) throw new NakesImportException('invalid_phone');
		return $value;
	}

	private function normalizedNameKey($value)
	{
		$ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',(string)$value);$key=strtolower(is_string($ascii)?$ascii:(string)$value);$key=trim(preg_replace('/[^a-z0-9]+/',' ',$key));if($key==='')throw new NakesImportException('invalid_name');return $key;
	}
}
