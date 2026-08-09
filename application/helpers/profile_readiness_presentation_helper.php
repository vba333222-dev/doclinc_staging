<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_profile_readiness_label')) {
	function doclinc_profile_readiness_label($state)
	{
		$labels = array(
			'COMPLETE' => 'Lengkap',
			'INCOMPLETE' => 'Belum lengkap',
			'SIP_MISSING' => 'Belum lengkap',
			'SIP_EXPIRING' => 'Lengkap, SIP mendekati kedaluwarsa',
			'SIP_EXPIRED' => 'Belum lengkap, SIP kedaluwarsa',
		);
		$key = strtoupper(trim((string) $state));

		return isset($labels[$key]) ? $labels[$key] : 'Belum tersedia';
	}
}

if (!function_exists('doclinc_sip_state_label')) {
	function doclinc_sip_state_label($state)
	{
		$labels = array(
			'MISSING' => 'Belum tercatat',
			'ACTIVE' => 'Aktif',
			'EXPIRING' => 'Mendekati kedaluwarsa',
			'EXPIRED' => 'Kedaluwarsa',
			'INVALID' => 'Perlu diperiksa',
			'NOT_APPLICABLE' => 'Tidak berlaku',
		);
		$key = strtoupper(trim((string) $state));

		return isset($labels[$key]) ? $labels[$key] : 'Belum tersedia';
	}
}
