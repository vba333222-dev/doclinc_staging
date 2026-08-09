<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Reverse_geocoding_service
{
	private $cache_path;
	private $enabled;
	private $endpoint;
	private $allowed_hosts;
	private $user_agent;
	private $cache_ttl;
	private $http_get;
	private $now;
	private $sleep;

	public function __construct(array $options = array())
	{
		$this->cache_path = rtrim((string) ($options['cache_path'] ?? ''), "/\\") . DIRECTORY_SEPARATOR;
		$this->enabled = ($options['enabled'] ?? true) === true;
		$this->endpoint = (string) ($options['endpoint'] ?? '');
		$this->allowed_hosts = is_array($options['allowed_hosts'] ?? null) ? $options['allowed_hosts'] : array('nominatim.openstreetmap.org');
		$this->user_agent = trim((string) ($options['user_agent'] ?? 'Doclinc/1.0'));
		$this->cache_ttl = max(300, min(604800, (int) ($options['cache_ttl'] ?? 86400)));
		$this->http_get = $options['http_get'] ?? null;
		$this->now = $options['now'] ?? function () { return time(); };
		$this->sleep = $options['sleep'] ?? function ($microseconds) { usleep($microseconds); };
	}

	public function resolve($latitude, $longitude)
	{
		$location = $this->normalize($latitude, $longitude);
		if ($location === false || !$this->configuration_ready() || !$this->ensure_cache_path()) {
			return $this->unavailable();
		}

		$key = hash('sha256', sprintf('%.5F,%.5F', $location['lat'], $location['lng']));
		$cached = $this->read_cache($key);
		if ($cached !== false) {
			return $cached;
		}

		$lock_path = $this->cache_path . $key . '.lock';
		$lock = @fopen($lock_path, 'c+');
		if (!$lock || !flock($lock, LOCK_EX)) {
			if (is_resource($lock)) fclose($lock);
			return $this->unavailable();
		}
		@chmod($lock_path, 0600);

		try {
			$cached = $this->read_cache($key);
			if ($cached !== false) {
				return $cached;
			}
			$this->respect_global_interval();
			$url = $this->endpoint . '?' . http_build_query(array(
				'format' => 'jsonv2',
				'lat' => sprintf('%.7F', $location['lat']),
				'lon' => sprintf('%.7F', $location['lng']),
				'zoom' => 18,
				'addressdetails' => 1,
				'accept-language' => 'id',
			), '', '&', PHP_QUERY_RFC3986);
			$body = $this->request($url);
			$result = $this->parse($body);
			if ($result['available']) {
				$this->write_cache($key, $result);
			}
			return $result;
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private function configuration_ready()
	{
		$parts = parse_url($this->endpoint);
		$host = strtolower((string) (is_array($parts) ? ($parts['host'] ?? '') : ''));
		$allowed = array_values(array_filter(array_map(function ($value) {
			$value = strtolower(trim((string) $value));
			return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $value) ? $value : '';
		}, $this->allowed_hosts)));
		return $this->enabled
			&& is_array($parts)
			&& ($parts['scheme'] ?? '') === 'https'
			&& in_array($host, $allowed, true)
			&& ($parts['path'] ?? '') === '/reverse'
			&& $this->user_agent !== '';
	}

	private function normalize($latitude, $longitude)
	{
		if (!is_numeric($latitude) || !is_numeric($longitude)) return false;
		$lat = (float) $latitude;
		$lng = (float) $longitude;
		if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return false;
		return array('lat' => $lat, 'lng' => $lng);
	}

	private function ensure_cache_path()
	{
		if ($this->cache_path === DIRECTORY_SEPARATOR) return false;
		if (!is_dir($this->cache_path) && !@mkdir($this->cache_path, 0700, true)) return false;
		@chmod($this->cache_path, 0700);
		return is_dir($this->cache_path) && is_writable($this->cache_path);
	}

	private function read_cache($key)
	{
		$file = $this->cache_path . $key . '.json';
		if (!is_file($file) || filesize($file) > 4096) return false;
		$decoded = json_decode((string) @file_get_contents($file), true);
		if (!is_array($decoded) || (int) ($decoded['expires_at'] ?? 0) <= call_user_func($this->now)) return false;
		return $this->sanitize_result($decoded['result'] ?? array());
	}

	private function write_cache($key, array $result)
	{
		$payload = json_encode(array(
			'expires_at' => call_user_func($this->now) + $this->cache_ttl,
			'result' => $result,
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($payload) || strlen($payload) > 4096) return;
		$temp = @tempnam($this->cache_path, 'rg-');
		if ($temp === false) return;
		@chmod($temp, 0600);
		if (@file_put_contents($temp, $payload, LOCK_EX) === false || !@rename($temp, $this->cache_path . $key . '.json')) {
			@unlink($temp);
		}
	}

	private function respect_global_interval()
	{
		$file = $this->cache_path . 'provider-rate.lock';
		$handle = @fopen($file, 'c+');
		if (!$handle || !flock($handle, LOCK_EX)) {
			if (is_resource($handle)) fclose($handle);
			return;
		}
		@chmod($file, 0600);
		$last = (float) trim((string) stream_get_contents($handle));
		$now = microtime(true);
		$wait = max(0, 1.05 - ($now - $last));
		if ($wait > 0) call_user_func($this->sleep, (int) ceil($wait * 1000000));
		ftruncate($handle, 0);
		rewind($handle);
		fwrite($handle, sprintf('%.6F', microtime(true)));
		fflush($handle);
		flock($handle, LOCK_UN);
		fclose($handle);
	}

	private function request($url)
	{
		if (is_callable($this->http_get)) {
			return call_user_func($this->http_get, $url, $this->user_agent);
		}
		if (!function_exists('curl_init')) return false;
		$curl = curl_init($url);
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT_MS => 2500,
			CURLOPT_TIMEOUT_MS => 6000,
			CURLOPT_USERAGENT => $this->user_agent,
			CURLOPT_HTTPHEADER => array('Accept: application/json', 'Accept-Language: id'),
			CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
		));
		$body = curl_exec($curl);
		$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$content_type = strtolower((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
		curl_close($curl);
		return $status === 200 && strpos($content_type, 'application/json') !== false && is_string($body) && strlen($body) <= 65536
			? $body
			: false;
	}

	private function parse($body)
	{
		if (!is_string($body) || $body === '' || strlen($body) > 65536) return $this->unavailable();
		$payload = json_decode($body, true);
		if (!is_array($payload)) return $this->unavailable();
		$address = $this->safe_text($payload['display_name'] ?? '', 500);
		$parts = is_array($payload['address'] ?? null) ? $payload['address'] : array();
		$locality = '';
		foreach (array('village', 'suburb', 'town', 'city', 'county', 'state') as $field) {
			$locality = $this->safe_text($parts[$field] ?? '', 120);
			if ($locality !== '') break;
		}
		return $address === '' ? $this->unavailable() : array(
			'available' => true,
			'address' => $address,
			'locality' => $locality,
			'provider' => 'openstreetmap',
			'attribution' => '© OpenStreetMap contributors',
		);
	}

	private function sanitize_result($value)
	{
		if (!is_array($value) || empty($value['available'])) return false;
		$address = $this->safe_text($value['address'] ?? '', 500);
		if ($address === '') return false;
		return array(
			'available' => true,
			'address' => $address,
			'locality' => $this->safe_text($value['locality'] ?? '', 120),
			'provider' => 'openstreetmap',
			'attribution' => '© OpenStreetMap contributors',
		);
	}

	private function safe_text($value, $limit)
	{
		$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
		if ($text === '' || strlen($text) > $limit || preg_match('/[\x00-\x1F\x7F]/', $text)) return '';
		return $text;
	}

	private function unavailable()
	{
		return array('available' => false, 'address' => 'Alamat belum dapat dikenali', 'locality' => '', 'provider' => '', 'attribution' => '');
	}
}
