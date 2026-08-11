<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_nakes_account_label')) {
	function doclinc_nakes_account_label($state)
	{
		require_once dirname(__DIR__) . '/libraries/Nakes_personal_account_policy.php';
		$labels = array(
			Nakes_personal_account_policy::STAFF_ONLY => 'Belum memiliki akun',
			Nakes_personal_account_policy::STAFF_WITH_UNLINKED_ACCOUNT => 'Akun tersedia untuk dihubungkan',
			Nakes_personal_account_policy::FIRST_LOGIN_PENDING => 'Menunggu aktivasi pertama',
			Nakes_personal_account_policy::ACTIVE => 'Akun aktif',
			Nakes_personal_account_policy::ADMIN_RESET_PENDING => 'Menunggu pembuatan password baru',
			Nakes_personal_account_policy::INVALID => 'Akun perlu diperiksa',
		);
		return $labels[(string) $state] ?? 'Akun perlu diperiksa';
	}
}
