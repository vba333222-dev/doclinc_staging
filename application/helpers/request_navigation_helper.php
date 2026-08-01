<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_request_return_target')) {
	/**
	 * Resolve a request navigation destination from trusted actor/request state.
	 *
	 * User-supplied URLs are deliberately not accepted here. The returned value is
	 * always one of the application's known internal history destinations.
	 */
	function doclinc_request_return_target($role, $request_status = '')
	{
		$role = strtolower(trim((string) $role));
		$request_status = strtolower(trim((string) $request_status));

		if (in_array($role, array('dokter', 'nakes'), true)) {
			if (in_array($request_status, array('completed', 'cancelled'), true)) {
				return 'home_nakes#riwayat_konsul_selesai';
			}

			return 'home_nakes#riwayat_konsul';
		}

		return 'home#riwayat';
	}
}

if (!function_exists('doclinc_request_return_url')) {
	function doclinc_request_return_url($role, $request_status = '')
	{
		return base_url(doclinc_request_return_target($role, $request_status));
	}
}
