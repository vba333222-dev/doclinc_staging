<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Care_team_policy
{
	const NON_VISIT = 'non_visit';
	const VISIT = 'visit';

	public function mode($value)
	{
		$value = strtolower(trim((string) $value));
		return in_array($value, array(self::NON_VISIT, self::VISIT), true) ? $value : null;
	}

	public function personalEligible(array $identity, $puskesmas_code)
	{
		return !empty($identity['valid'])
			&& (string) ($identity['account_type'] ?? '') === 'personal'
			&& (int) ($identity['user_id'] ?? 0) > 0
			&& (int) ($identity['staff_id'] ?? 0) > 0
			&& (string) ($identity['user_status'] ?? '') === 'aktif'
			&& (string) ($identity['staff_status'] ?? '') === 'aktif'
			&& trim((string) ($identity['puskesmas_code'] ?? '')) === trim((string) $puskesmas_code);
	}

	public function responsibleDoctorEligible(array $identity, $puskesmas_code)
	{
		if (!$this->personalEligible($identity, $puskesmas_code)) {
			return false;
		}
		return $this->doctorProfession($identity['staff_profesi'] ?? '');
	}

	public function visitPerformerEligible(array $identity, $puskesmas_code)
	{
		if (!$this->personalEligible($identity, $puskesmas_code)) {
			return false;
		}
		$profession = trim((string) ($identity['staff_profesi'] ?? ''));
		return $profession !== '' && !$this->doctorProfession($profession);
	}

	public function doctorProfession($profession)
	{
		$profession = strtolower(trim((string) $profession));
		return $profession !== '' && preg_match('/(^|\s)dokter($|\s)/u', $profession) === 1;
	}

	public function commandCenterEligible(array $identity, $puskesmas_code)
	{
		return !empty($identity['valid'])
			&& (string) ($identity['account_type'] ?? '') === 'command_center'
			&& !empty($identity['is_command_center'])
			&& (int) ($identity['user_id'] ?? 0) > 0
			&& trim((string) ($identity['puskesmas_code'] ?? '')) === trim((string) $puskesmas_code);
	}
}
