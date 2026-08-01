<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_csrf_bootstrap_markup')) {
	function doclinc_csrf_bootstrap_markup()
	{
		$CI = &get_instance();
		if ($CI->config->item('csrf_protection') !== true || !isset($CI->security)) {
			return '';
		}

		$token_name = (string) $CI->security->get_csrf_token_name();
		$token_hash = (string) $CI->security->get_csrf_hash();
		if ($token_name === '' || preg_match('/^[0-9a-f]{32}$/i', $token_hash) !== 1) {
			return '';
		}

		return '<meta name="doclinc-csrf-name" content="' . html_escape($token_name) . '">' . "\n"
			. '<meta name="doclinc-csrf-token" content="' . html_escape($token_hash) . '">' . "\n"
			. '<script src="' . html_escape(base_url('assets/js/doclinc-csrf.js')) . '"></script>';
	}
}
