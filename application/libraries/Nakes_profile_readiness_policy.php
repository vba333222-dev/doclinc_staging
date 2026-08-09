<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Nakes_profile_readiness_policy
{
	const COMPLETE = 'COMPLETE';
	const INCOMPLETE = 'INCOMPLETE';
	const SIP_MISSING = 'SIP_MISSING';
	const SIP_EXPIRING = 'SIP_EXPIRING';
	const SIP_EXPIRED = 'SIP_EXPIRED';

	const SIP_STATE_MISSING = 'MISSING';
	const SIP_STATE_ACTIVE = 'ACTIVE';
	const SIP_STATE_EXPIRING = 'EXPIRING';
	const SIP_STATE_EXPIRED = 'EXPIRED';
	const SIP_STATE_INVALID = 'INVALID';

	const DEFAULT_EXPIRING_DAYS = 90;

	private $today;
	private $expiring_days;

	public function __construct($today = null, $expiring_days = self::DEFAULT_EXPIRING_DAYS)
	{
		$this->today = $this->valid_date($today ?: date('Y-m-d'));
		if (!$this->today) {
			$this->today = new DateTimeImmutable('today');
		}
		$this->expiring_days = max(1, (int) $expiring_days);
	}

	public function evaluate(array $profile)
	{
		$missing = array();
		$this->require_text($profile, 'name', 'name', 2, $missing);
		$this->require_text($profile, 'title', 'title', 1, $missing);
		$this->require_birthdate($profile, $missing);
		$this->require_gender($profile, $missing);
		$this->require_text($profile, 'profession', 'profession', 2, $missing);
		$this->require_phone($profile, $missing);

		if (($profile['account_state'] ?? '') !== 'linked') {
			$missing[] = 'staff_link';
		}
		if (($profile['staff_status'] ?? '') !== 'aktif') {
			$missing[] = 'staff_status';
		}
		if (($profile['account_status'] ?? '') !== 'aktif') {
			$missing[] = 'account_status';
		}
		if (($profile['facility_status'] ?? '') !== 'aktif') {
			$missing[] = 'puskesmas';
		}

		$registration_number = trim((string) ($profile['registration_number'] ?? ''));
		$registration_expiry = trim((string) ($profile['registration_expires_at'] ?? ''));
		$sip_state = self::SIP_STATE_MISSING;
		if ($registration_number === '') {
			$missing[] = 'registration_number';
		}
		if ($registration_expiry === '') {
			$missing[] = 'registration_expiry';
		} elseif ($registration_number !== '') {
			$expiry = $this->valid_date($registration_expiry);
			if (!$expiry) {
				$sip_state = self::SIP_STATE_INVALID;
				$missing[] = 'registration_expiry';
			} elseif ($expiry < $this->today) {
				$sip_state = self::SIP_STATE_EXPIRED;
			} else {
				$warning_date = $this->today->modify('+' . $this->expiring_days . ' days');
				$sip_state = $expiry <= $warning_date ? self::SIP_STATE_EXPIRING : self::SIP_STATE_ACTIVE;
			}
		}

		$missing = array_values(array_unique($missing));
		$ready = empty($missing) && in_array($sip_state, array(self::SIP_STATE_ACTIVE, self::SIP_STATE_EXPIRING), true);
		if ($sip_state === self::SIP_STATE_EXPIRED) {
			$state = self::SIP_EXPIRED;
		} elseif ($registration_number === '' || $registration_expiry === '') {
			$state = self::SIP_MISSING;
		} elseif (!$ready) {
			$state = self::INCOMPLETE;
		} elseif ($sip_state === self::SIP_STATE_EXPIRING) {
			$state = self::SIP_EXPIRING;
		} else {
			$state = self::COMPLETE;
		}

		return array(
			'state' => $state,
			'sip_state' => $sip_state,
			'complete' => $ready,
			'operationally_ready' => $ready,
			'warning' => $sip_state === self::SIP_STATE_EXPIRING,
			'missing_fields' => $missing,
			'expiring_days' => $this->expiring_days,
		);
	}

	public function evaluateStaffRecord(array $staff)
	{
		$missing = array();
		$this->require_text($staff, 'name', 'name', 2, $missing);
		$this->require_text($staff, 'title', 'title', 1, $missing);
		$this->require_text($staff, 'profession', 'profession', 2, $missing);
		$this->require_phone($staff, $missing);
		if (($staff['facility_status'] ?? '') !== 'aktif') {
			$missing[] = 'puskesmas';
		}
		$number = trim((string) ($staff['registration_number'] ?? ''));
		$expiry_value = trim((string) ($staff['registration_expires_at'] ?? ''));
		$sip_state = self::SIP_STATE_MISSING;
		if ($number === '') {
			$missing[] = 'registration_number';
		}
		if ($expiry_value === '') {
			$missing[] = 'registration_expiry';
		} elseif ($number !== '') {
			$expiry = $this->valid_date($expiry_value);
			if (!$expiry) {
				$sip_state = self::SIP_STATE_INVALID;
				$missing[] = 'registration_expiry';
			} elseif ($expiry < $this->today) {
				$sip_state = self::SIP_STATE_EXPIRED;
			} else {
				$sip_state = $expiry <= $this->today->modify('+' . $this->expiring_days . ' days')
					? self::SIP_STATE_EXPIRING
					: self::SIP_STATE_ACTIVE;
			}
		}
		$missing = array_values(array_unique($missing));
		$ready = empty($missing) && in_array($sip_state, array(self::SIP_STATE_ACTIVE, self::SIP_STATE_EXPIRING), true);
		return array(
			'complete' => $ready,
			'operationally_ready' => $ready,
			'sip_state' => $sip_state,
			'warning' => $sip_state === self::SIP_STATE_EXPIRING,
			'missing_fields' => $missing,
		);
	}

	private function require_text(array $profile, $source, $code, $minimum, array &$missing)
	{
		$value = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($profile[$source] ?? ''))));
		$length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
		if ($length < (int) $minimum || in_array(strtolower($value), array('n/a', 'na', '-', 'default', 'belum ditentukan'), true)) {
			$missing[] = $code;
		}
	}

	private function require_birthdate(array $profile, array &$missing)
	{
		$date = $this->valid_date($profile['birthdate'] ?? '');
		if (!$date || $date > $this->today) {
			$missing[] = 'birthdate';
		}
	}

	private function require_gender(array $profile, array &$missing)
	{
		$gender = strtolower(trim((string) ($profile['gender'] ?? '')));
		if (!in_array($gender, array('l', 'p', 'laki-laki', 'perempuan'), true)) {
			$missing[] = 'gender';
		}
	}

	private function require_phone(array $profile, array &$missing)
	{
		$phone = preg_replace('/[^0-9+]/', '', trim((string) ($profile['phone'] ?? '')));
		if (preg_match('/^\+?[0-9]{8,20}$/', $phone) !== 1) {
			$missing[] = 'phone';
		}
	}

	private function valid_date($value)
	{
		$value = trim((string) $value);
		$date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		return $date && $date->format('Y-m-d') === $value ? $date : null;
	}
}
