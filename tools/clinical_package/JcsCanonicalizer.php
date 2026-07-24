<?php

class JcsCanonicalizer
{
	/*
	 * doclink-package-jcs-v1 is an RFC 8785-compatible restricted projection:
	 * strings, booleans, null, objects, arrays, and interoperable integers are
	 * supported. Floating-point values are deliberately outside this profile.
	 */
	public function canonicalize($value)
	{
		if ($value === null) {
			return 'null';
		}
		if ($value === true) {
			return 'true';
		}
		if ($value === false) {
			return 'false';
		}
		if (is_int($value)) {
			if ($value < -9007199254740991 || $value > 9007199254740991) {
				throw new ClinicalPackageException('jcs_integer_out_of_range', 'Semantic package projection integer exceeds the interoperable exact range.');
			}
			return (string) $value;
		}
		if (is_float($value)) {
			throw new ClinicalPackageException('jcs_number_unsupported', 'Semantic package projections use interoperable integers only.');
		}
		if (is_string($value)) {
			if (preg_match('//u', $value) !== 1) {
				throw new ClinicalPackageException('jcs_string_invalid_utf8', 'JCS projection string is not valid UTF-8.');
			}
			return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
		}
		if (is_object($value)) {
			return $this->canonicalizeObject(get_object_vars($value));
		}
		if (!is_array($value)) {
			throw new ClinicalPackageException('jcs_type_unsupported', 'JCS projection contains an unsupported value type.');
		}
		if ($this->isList($value)) {
			$items = array();
			foreach ($value as $item) {
				$items[] = $this->canonicalize($item);
			}
			return '[' . implode(',', $items) . ']';
		}
		return $this->canonicalizeObject($value);
	}

	private function canonicalizeObject(array $value)
	{
		$keys = array_keys($value);
		foreach ($keys as $key) {
			if (!is_string($key)) {
				throw new ClinicalPackageException('jcs_object_key_invalid', 'JCS object keys must be strings.');
			}
		}
		usort($keys, array($this, 'compareUtf16Keys'));
		$members = array();
		foreach ($keys as $key) {
			$members[] = $this->canonicalize($key) . ':' . $this->canonicalize($value[$key]);
		}
		return '{' . implode(',', $members) . '}';
	}

	public function compareUtf16Keys($left, $right)
	{
		if (!is_string($left) || !is_string($right) || preg_match('//u', $left) !== 1 || preg_match('//u', $right) !== 1) {
			throw new ClinicalPackageException('jcs_object_key_invalid', 'JCS object keys must be valid UTF-8 strings.');
		}
		$leftUtf16 = iconv('UTF-8', 'UTF-16BE', $left);
		$rightUtf16 = iconv('UTF-8', 'UTF-16BE', $right);
		if ($leftUtf16 === false || $rightUtf16 === false) {
			throw new ClinicalPackageException('jcs_object_key_invalid', 'JCS object key conversion failed.');
		}
		return strcmp($leftUtf16, $rightUtf16);
	}

	private function isList(array $value)
	{
		$index = 0;
		foreach ($value as $key => $unused) {
			if ($key !== $index) {
				return false;
			}
			$index++;
		}
		return true;
	}
}
