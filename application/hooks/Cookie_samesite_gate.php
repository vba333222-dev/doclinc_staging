<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Cookie_samesite_gate
{
	public function enforce()
	{
		if (PHP_SAPI === 'cli' || headers_sent()) {
			return;
		}

		$CI = &get_instance();
		$policy = (string) $CI->config->item('cookie_samesite');
		if (!in_array($policy, array('Lax', 'Strict', 'None'), true)) {
			$policy = 'Lax';
		}
		if ($policy === 'None' && $CI->config->item('cookie_secure') !== true) {
			$policy = 'Lax';
		}

		$cookie_headers = array_values(array_filter(headers_list(), function ($header) {
			return stripos((string) $header, 'Set-Cookie:') === 0;
		}));
		if (empty($cookie_headers)) {
			return;
		}

		header_remove('Set-Cookie');
		foreach ($cookie_headers as $header) {
			if (stripos($header, '; SameSite=') === false) {
				$header .= '; SameSite=' . $policy;
			}
			header($header, false);
		}
	}
}
