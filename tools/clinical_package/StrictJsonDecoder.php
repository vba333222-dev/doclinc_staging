<?php

class StrictJsonDecoder
{
	private $bytes;
	private $length;
	private $position;
	private $relativePath;

	public function decodeFile($absolutePath, $relativePath)
	{
		$bytes = @file_get_contents($absolutePath);
		if ($bytes === false) {
			throw new ClinicalPackageException('package_json_read_failed', 'A package JSON file could not be read.', array('path' => $relativePath));
		}
		return $this->decode($bytes, $relativePath);
	}

	public function decode($bytes, $relativePath = 'document.json')
	{
		if (!is_string($bytes) || preg_match('//u', $bytes) !== 1) {
			throw new ClinicalPackageException('json_invalid_utf8', 'Package JSON must be valid UTF-8.', array('path' => $relativePath));
		}
		$this->bytes = $bytes;
		$this->length = strlen($bytes);
		$this->position = 0;
		$this->relativePath = (string) $relativePath;
		$this->skipWhitespace();
		$this->scanValue(0);
		$this->skipWhitespace();
		if ($this->position !== $this->length) {
			$this->error('json_trailing_data', 'Package JSON contains trailing non-whitespace data.');
		}
		try {
			return json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			$this->error('json_malformed', 'Package JSON is malformed.');
		}
	}

	private function scanValue($depth)
	{
		if ($depth > 512 || $this->position >= $this->length) {
			$this->error('json_malformed', 'Package JSON is malformed.');
		}
		$byte = $this->bytes[$this->position];
		if ($byte === '{') {
			$this->scanObject($depth + 1);
		} elseif ($byte === '[') {
			$this->scanArray($depth + 1);
		} elseif ($byte === '"') {
			$this->scanString();
		} elseif ($byte === 't') {
			$this->scanLiteral('true');
		} elseif ($byte === 'f') {
			$this->scanLiteral('false');
		} elseif ($byte === 'n') {
			$this->scanLiteral('null');
		} elseif ($byte === '-' || ($byte >= '0' && $byte <= '9')) {
			$this->scanNumber();
		} else {
			$this->error('json_malformed', 'Package JSON contains an invalid value.');
		}
	}

	private function scanObject($depth)
	{
		$this->position++;
		$this->skipWhitespace();
		$keys = array();
		if ($this->consume('}')) {
			return;
		}
		while (true) {
			if ($this->position >= $this->length || $this->bytes[$this->position] !== '"') {
				$this->error('json_malformed', 'JSON object keys must be strings.');
			}
			$key = $this->scanString();
			if (isset($keys[$key])) {
				$this->error('json_duplicate_object_key', 'Duplicate JSON object keys are prohibited.');
			}
			$keys[$key] = true;
			$this->skipWhitespace();
			if (!$this->consume(':')) {
				$this->error('json_malformed', 'JSON object member separator is missing.');
			}
			$this->skipWhitespace();
			$this->scanValue($depth);
			$this->skipWhitespace();
			if ($this->consume('}')) {
				return;
			}
			if (!$this->consume(',')) {
				$this->error('json_malformed', 'JSON object delimiter is invalid.');
			}
			$this->skipWhitespace();
		}
	}

	private function scanArray($depth)
	{
		$this->position++;
		$this->skipWhitespace();
		if ($this->consume(']')) {
			return;
		}
		while (true) {
			$this->scanValue($depth);
			$this->skipWhitespace();
			if ($this->consume(']')) {
				return;
			}
			if (!$this->consume(',')) {
				$this->error('json_malformed', 'JSON array delimiter is invalid.');
			}
			$this->skipWhitespace();
		}
	}

	private function scanString()
	{
		$start = $this->position;
		$this->position++;
		while ($this->position < $this->length) {
			$code = ord($this->bytes[$this->position]);
			if ($code < 0x20) {
				$this->error('json_malformed', 'JSON strings may not contain literal control characters.');
			}
			if ($this->bytes[$this->position] === '"') {
				$this->position++;
				$token = substr($this->bytes, $start, $this->position - $start);
				try {
					$value = json_decode($token, false, 4, JSON_THROW_ON_ERROR);
				} catch (Throwable $exception) {
					$this->error('json_malformed', 'JSON string escaping is invalid.');
				}
				if (!is_string($value)) {
					$this->error('json_malformed', 'JSON string decoding failed.');
				}
				return $value;
			}
			if ($this->bytes[$this->position] === '\\') {
				$this->position++;
				if ($this->position >= $this->length) {
					$this->error('json_malformed', 'JSON escape is incomplete.');
				}
				$escape = $this->bytes[$this->position];
				if ($escape === 'u') {
					$hex = substr($this->bytes, $this->position + 1, 4);
					if (strlen($hex) !== 4 || preg_match('/^[0-9A-Fa-f]{4}$/', $hex) !== 1) {
						$this->error('json_malformed', 'JSON Unicode escape is invalid.');
					}
					$this->position += 5;
					continue;
				}
				if (strpos('"\\/bfnrt', $escape) === false) {
					$this->error('json_malformed', 'JSON escape is invalid.');
				}
			}
			$this->position++;
		}
		$this->error('json_malformed', 'JSON string is unterminated.');
	}

	private function scanNumber()
	{
		if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $this->bytes, $match, 0, $this->position) !== 1) {
			$this->error('json_number_unsupported', 'JSON number representation is unsupported.');
		}
		$token = $match[0];
		$next = $this->position + strlen($token);
		if ($next < $this->length && strpos(" \t\r\n,]}", $this->bytes[$next]) === false) {
			$this->error('json_number_unsupported', 'JSON number representation is unsupported.');
		}
		if (strpos($token, '.') === false && stripos($token, 'e') === false) {
			$unsigned = ltrim($token, '-');
			$trimmed = ltrim($unsigned, '0');
			$trimmed = $trimmed === '' ? '0' : $trimmed;
			if (strlen($trimmed) > 16 || (strlen($trimmed) === 16 && strcmp($trimmed, '9007199254740991') > 0)) {
				$this->error('json_number_unsupported', 'JSON integers must be exactly representable in the interoperable range.');
			}
		} else {
			$value = (float) $token;
			if (is_infinite($value) || is_nan($value)) {
				$this->error('json_number_unsupported', 'JSON number is not finite.');
			}
		}
		$this->position = $next;
	}

	private function scanLiteral($literal)
	{
		if (substr($this->bytes, $this->position, strlen($literal)) !== $literal) {
			$this->error('json_malformed', 'JSON literal is invalid.');
		}
		$this->position += strlen($literal);
	}

	private function skipWhitespace()
	{
		while ($this->position < $this->length && strpos(" \t\r\n", $this->bytes[$this->position]) !== false) {
			$this->position++;
		}
	}

	private function consume($byte)
	{
		if ($this->position < $this->length && $this->bytes[$this->position] === $byte) {
			$this->position++;
			return true;
		}
		return false;
	}

	private function error($code, $message)
	{
		throw new ClinicalPackageException($code, $message, array('path' => $this->relativePath));
	}
}
