<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Nakes_placement_policy
{
	public static function canUsePlacement($identity, $enabled)
	{
		return $enabled === true && is_array($identity)
			&& !empty($identity['valid'])
			&& (string) ($identity['account_type'] ?? '') === 'personal'
			&& (string) ($identity['role'] ?? '') === 'dokter'
			&& (string) ($identity['status'] ?? ($identity['user_status'] ?? '')) === 'aktif';
	}

	public static function canManageTransfer($actor)
	{
		return is_array($actor)
			&& (int) ($actor['user_id'] ?? 0) > 0
			&& (string) ($actor['role'] ?? '') === 'admin'
			&& (string) ($actor['status'] ?? '') === 'aktif';
	}

	public static function validDestination($destination, $currentFacility)
	{
		$code = strtoupper(trim((string) ($destination['kode_pkm'] ?? '')));
		$current = strtoupper(trim((string) $currentFacility));
		return $code !== '' && $code !== 'DEFAULT' && $code !== $current
			&& (string) ($destination['status'] ?? '') === 'aktif';
	}

	public static function canDirectEditFacility($current, $requested, $enabled)
	{
		return !$enabled || strtoupper(trim((string) $current)) === strtoupper(trim((string) $requested));
	}

	public static function transferBlockers($blockers)
	{
		foreach ((array) $blockers as $blocker) {
			if (is_array($blocker) && !empty($blocker['active'])) return false;
		}
		return true;
	}

	public static function canActivate($now, $effectiveAt, $blockers)
	{
		$nowTime = strtotime((string) $now);
		$effectiveTime = strtotime((string) $effectiveAt);
		return $nowTime !== false && $effectiveTime !== false && $nowTime >= $effectiveTime
			&& self::transferBlockers($blockers);
	}

	public static function normalizeStatus($status)
	{
		$status = strtolower(trim((string) $status));
		return in_array($status, array('active', 'ended', 'scheduled', 'completed', 'cancelled'), true) ? $status : '';
	}

	public static function safeBlockerSummary($blockers)
	{
		$labels = array(
			'responsible_doctor' => 'Responsible Doctor masih menangani tugas aktif',
			'visit_performer' => 'Petugas kunjungan masih menangani Visit aktif',
			'staff_assignment' => 'Ada penugasan operasional aktif',
			'accepted_request' => 'Ada request Accepted yang masih menjadi tanggung jawab Nakes',
		);
		$result = array();
		foreach ((array) $blockers as $blocker) {
			$kind = is_array($blocker) ? (string) ($blocker['kind'] ?? '') : '';
			if ($kind !== '' && isset($labels[$kind]) && !in_array($labels[$kind], $result, true)) $result[] = $labels[$kind];
		}
		return $result;
	}
}
