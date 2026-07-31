<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Realtime_outbox_exception.php';

class Realtime_outbox_contract
{
	private const EVENT_AGGREGATES = array(
		'notification.created' => array('notification', 'notifications'),
		'request.created' => array('request', 'requests'),
		'request.accepted' => array('request', 'requests'),
		'request.cancelled' => array('request', 'requests'),
		'request.completed' => array('request', 'requests'),
		'request.pic_assigned' => array('request', 'assignment'),
		'request.pic_reassigned' => array('request', 'assignment'),
		'request.pic_cleared' => array('request', 'assignment'),
		'request.assignment.changed' => array('request', 'assignment'),
		'nakes.presence.changed' => array('nakes', 'presence'),
		'visit.route.changed' => array('visit', 'route'),
		'visit.media.changed' => array('visit', 'media'),
		'medicalrecord.diagnoses.changed' => array('medicalrecord', 'diagnoses'),
	);
	private const EVENT_KEYS = array(
		'event_id', 'event_type', 'aggregate_type', 'aggregate_id',
		'version', 'invalidation', 'audience',
	);

	private $maximum_payload_bytes;

	public function __construct($maximum_payload_bytes = 4096)
	{
		$maximum_payload_bytes = (int) $maximum_payload_bytes;
		if ($maximum_payload_bytes < 64 || $maximum_payload_bytes > 16384) {
			throw new Realtime_outbox_exception('payload_limit_invalid');
		}
		$this->maximum_payload_bytes = $maximum_payload_bytes;
	}

	public function prepare(array $event, array $known_puskesmas_codes = array())
	{
		$keys = array_keys($event);
		sort($keys);
		$expected_keys = self::EVENT_KEYS;
		sort($expected_keys);
		if ($keys !== $expected_keys) {
			throw new Realtime_outbox_exception('payload_key_not_allowed');
		}

		foreach ($event as $value) {
			if (is_array($value) || is_object($value) || is_resource($value)) {
				throw new Realtime_outbox_exception('payload_value_invalid');
			}
			if (is_string($value) && preg_match('//u', $value) !== 1) {
				throw new Realtime_outbox_exception('payload_utf8_invalid');
			}
		}

		$event_type = $this->asciiValue($event['event_type'], 64, 'event_type_invalid');
		if (!array_key_exists($event_type, self::EVENT_AGGREGATES)) {
			throw new Realtime_outbox_exception('event_type_invalid');
		}
		$aggregate_type = $this->asciiValue($event['aggregate_type'], 32, 'aggregate_type_invalid');
		$expected = self::EVENT_AGGREGATES[$event_type];
		if (!hash_equals($expected[0], $aggregate_type)) {
			throw new Realtime_outbox_exception('aggregate_type_invalid');
		}
		$aggregate_id = $this->positiveIdentifier($event['aggregate_id'], 'aggregate_id_invalid');
		$event_id = $this->technicalReference($event['event_id']);
		$version = $this->positiveVersion($event['version']);
		$invalidation = $this->asciiValue($event['invalidation'], 32, 'invalidation_invalid');
		if (!hash_equals($expected[1], $invalidation)) {
			throw new Realtime_outbox_exception('invalidation_invalid');
		}
		$audience = self::validateAudience($event['audience'], $known_puskesmas_codes);

		$payload = array(
			'aggregate_id' => $aggregate_id,
			'audience' => $audience['key'],
			'event_id' => $event_id,
			'event_type' => $event_type,
			'invalidation' => $invalidation,
			'version' => $version,
		);
		try {
			$payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		} catch (Throwable $exception) {
			throw new Realtime_outbox_exception('payload_json_invalid');
		}
		if (!is_string($payload_json) || strlen($payload_json) > $this->maximum_payload_bytes) {
			throw new Realtime_outbox_exception('payload_too_large');
		}

		$idempotency_source = implode("\n", array(
			'doclinc-realtime-outbox-v1', $aggregate_type, $payload_json,
		));
		return array(
			'event_type' => $event_type,
			'aggregate_type' => $aggregate_type,
			'aggregate_id' => $aggregate_id,
			'audience_type' => $audience['type'],
			'audience_key' => $audience['key'],
			'payload_json' => $payload_json,
			'event_version' => $version,
			'idempotency_key' => hash('sha256', $idempotency_source),
		);
	}

	public static function validateAudience($audience, array $known_puskesmas_codes = array())
	{
		if (!is_string($audience) || preg_match('//u', $audience) !== 1 || trim($audience) !== $audience) {
			throw new Realtime_outbox_exception('audience_invalid');
		}
		if (preg_match('/\Auser:([1-9][0-9]*)\z/D', $audience, $match) === 1) {
			return array('type' => 'user', 'key' => 'user:' . self::boundedPositiveDigits($match[1]));
		}
		if (preg_match('/\Arequest:([1-9][0-9]*)\z/D', $audience, $match) === 1) {
			return array('type' => 'request', 'key' => 'request:' . self::boundedPositiveDigits($match[1]));
		}
		if (preg_match('/\Apuskesmas:([A-Za-z0-9][A-Za-z0-9._-]{0,99}):ops\z/D', $audience, $match) === 1) {
			$code = $match[1];
			if (strtoupper($code) === 'DEFAULT' || !in_array($code, $known_puskesmas_codes, true)) {
				throw new Realtime_outbox_exception('audience_puskesmas_unknown');
			}
			return array('type' => 'puskesmas', 'key' => 'puskesmas:' . $code . ':ops');
		}
		throw new Realtime_outbox_exception('audience_invalid');
	}

	private static function boundedPositiveDigits($value)
	{
		if (strlen($value) > 18) {
			throw new Realtime_outbox_exception('audience_identifier_invalid');
		}
		return $value;
	}

	private function positiveIdentifier($value, $safe_code)
	{
		$value = is_int($value) ? (string) $value : $value;
		if (!is_string($value) || preg_match('/\A[1-9][0-9]{0,17}\z/D', $value) !== 1) {
			throw new Realtime_outbox_exception($safe_code);
		}
		return $value;
	}

	private function positiveVersion($value)
	{
		if (!is_int($value) || $value < 1 || $value > PHP_INT_MAX) {
			throw new Realtime_outbox_exception('event_version_invalid');
		}
		return $value;
	}

	private function technicalReference($value)
	{
		$value = $this->asciiValue($value, 128, 'event_id_invalid');
		if (preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,31}[.:][A-Za-z0-9][A-Za-z0-9._:-]*\z/D', $value) !== 1) {
			throw new Realtime_outbox_exception('event_id_invalid');
		}
		return $value;
	}

	private function asciiValue($value, $maximum_length, $safe_code)
	{
		if (!is_string($value) || $value === '' || strlen($value) > $maximum_length
			|| preg_match('/\A[\x20-\x7E]+\z/D', $value) !== 1 || trim($value) !== $value) {
			throw new Realtime_outbox_exception($safe_code);
		}
		return $value;
	}
}
