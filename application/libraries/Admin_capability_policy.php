<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Admin_capability_policy
{
	const SUPER_ADMIN = 'super_admin';
	const CLINICAL_AUDIT = 'clinical_audit';
	const CLINICAL_TAXONOMY_MANAGE = 'clinical_taxonomy_manage';

	public static function allowedCodes()
	{
		return array(self::SUPER_ADMIN, self::CLINICAL_AUDIT, self::CLINICAL_TAXONOMY_MANAGE);
	}

	public static function validCode($code)
	{
		return in_array((string) $code, self::allowedCodes(), true);
	}

	public static function activeAdmin(array $actor)
	{
		return (int) ($actor['user_id'] ?? 0) > 0
			&& (string) ($actor['role'] ?? '') === 'admin'
			&& (string) ($actor['status'] ?? '') === 'aktif';
	}

	public static function canManage(array $actor, $hasSuperAdmin)
	{
		return self::activeAdmin($actor) && $hasSuperAdmin === true;
	}

	public static function canRemoveLastSuperAdmin($activeSuperAdminCount)
	{
		return (int) $activeSuperAdminCount > 1;
	}
}
