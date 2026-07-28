<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Care_operations_policy
{
	public static function wargaOwnsRequest(array $session, array $request)
	{
		return !self::mustChangePassword($session)
			&& self::activeRole($session, 'warga')
			&& self::actorId($session) === self::positiveId($request, 'user_id');
	}

	public static function personalNakesCanHandle(array $identity, array $request_access, array $request)
	{
		$user_id = self::positiveId($identity, 'user_id');
		$ownership_source = isset($request_access['ownership_source'])
			? (string) $request_access['ownership_source']
			: '';
		$personal_sources = array(
			'staff_assignment',
			'staff_assignment_command_center_bridge',
			'assigned_nakes_user_id',
			'accepted_by_user_id',
			'dokter_id',
		);
		return !self::mustChangePassword($identity)
			&& !empty($identity['valid'])
			&& ($identity['account_type'] ?? '') === 'personal'
			&& ($identity['user_status'] ?? '') === 'aktif'
			&& ($identity['staff_status'] ?? '') === 'aktif'
			&& $user_id > 0
			&& ($request['request_status'] ?? '') === 'Accepted'
			&& !empty($request_access['tenant_match'])
			&& !empty($request_access['can_handle'])
			&& in_array($ownership_source, $personal_sources, true);
	}

	public static function commandCenterCanCoordinate(array $identity, array $request)
	{
		$identity_code = self::puskesmasCode($identity['puskesmas_code'] ?? '');
		$request_code = self::puskesmasCode($request['assigned_puskesmas_code'] ?? '');
		return !self::mustChangePassword($identity)
			&& !empty($identity['valid'])
			&& ($identity['account_type'] ?? '') === 'command_center'
			&& ($identity['user_status'] ?? '') === 'aktif'
			&& ($request['request_status'] ?? '') === 'Accepted'
			&& $identity_code !== ''
			&& hash_equals($identity_code, $request_code);
	}

	public static function adminCanRead(array $session, $read_only)
	{
		return $read_only === true
			&& !self::mustChangePassword($session)
			&& self::activeRole($session, 'admin');
	}

	public static function eligiblePresenceTarget(array $identity)
	{
		return !self::mustChangePassword($identity)
			&& !empty($identity['valid'])
			&& ($identity['account_type'] ?? '') === 'personal'
			&& ($identity['user_status'] ?? '') === 'aktif'
			&& ($identity['staff_status'] ?? '') === 'aktif'
			&& self::positiveId($identity, 'user_id') > 0
			&& self::positiveId($identity, 'staff_id') > 0
			&& self::puskesmasCode($identity['puskesmas_code'] ?? '') !== '';
	}

	public static function mustChangePassword(array $context)
	{
		return isset($context['must_change_password']) && (int) $context['must_change_password'] === 1;
	}

	private static function activeRole(array $session, $role)
	{
		return ($session['logged_in'] ?? false) === true
			&& ($session['role'] ?? '') === $role
			&& ($session['status'] ?? 'aktif') === 'aktif'
			&& self::actorId($session) > 0;
	}

	private static function actorId(array $session)
	{
		$user_id = self::positiveId($session, 'user_id');
		return $user_id > 0 ? $user_id : self::positiveId($session, 'id');
	}

	private static function positiveId(array $row, $key)
	{
		$value = isset($row[$key]) ? (int) $row[$key] : 0;
		return $value > 0 ? $value : 0;
	}

	private static function puskesmasCode($value)
	{
		$value = is_string($value) ? trim($value) : '';
		return $value !== '' && strtoupper($value) !== 'DEFAULT' ? $value : '';
	}
}
