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
		return $profession !== '';
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

	/**
	 * Bolehkah jenis layanan diubah?
	 *
	 * Tiga sumber konflik yang dijaga di sini:
	 *   - kunjungan sudah berjalan (atau selesai) sehingga hasilnya sudah terikat
	 *     pada penugasan yang ada;
	 *   - disposisi dokter sudah menetapkan jenis layanan (mode mengikuti disposisi);
	 *   - masih ada Nakes yang ditugaskan (batalkan dulu, jangan diam-diam dilepas).
	 *
	 * Mengembalikan kode galat, atau null bila perubahan diizinkan.
	 */
	public function modeChangeFailure(array $request, $disposition, $active_performer_count, $workflow_enabled, $next_mode)
	{
		$current = strtolower(trim((string) ($request['consultation_mode'] ?? '')));
		$next = strtolower(trim((string) $next_mode));
		// Kunjungan yang sudah berjalan tidak boleh diubah jenis layanannya,
		// bahkan bila pengguna mengirim mode yang sama: jalur non_visit akan
		// membatalkan penugasan Nakes yang sedang bertugas.
		if ($this->visitStarted($request)) {
			return 'VISIT_ALREADY_STARTED';
		}
		if ($next !== '' && $current === $next) {
			return null;
		}
		if ($workflow_enabled && !empty($disposition)) {
			return 'MODE_LOCKED_BY_DISPOSITION';
		}
		if ($workflow_enabled && (int) $active_performer_count > 0) {
			return 'PERFORMER_LOCKED';
		}
		return null;
	}

	/** Nakes hanya boleh diganti sebelum kunjungan dimulai. */
	public function performerChangeFailure(array $request)
	{
		return $this->visitStarted($request) ? 'VISIT_ALREADY_STARTED' : null;
	}

	/** Dokter penanggung jawab hanya boleh diganti sebelum disposisi/kunjungan. */
	public function handoverFailure(array $request, $disposition)
	{
		if ($this->visitStarted($request)) {
			return 'HANDOVER_LOCKED';
		}
		return !empty($disposition) ? 'HANDOVER_LOCKED' : null;
	}

	private function visitStarted(array $request)
	{
		$status = strtolower(trim((string) ($request['visit_status'] ?? '')));
		return in_array($status, array('en_route', 'arrived', 'in_service', 'completed'), true);
	}
}
