<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_safe_profile_image_src')) {
	function doclinc_safe_profile_image_src($path, $uploadBase = 'uploads/profile/', $fallbackPath = 'assets/doclinc/img/default-profile.png')
	{
		$path = trim((string) $path);

		if (
			$path === ''
			|| preg_match('/[<>"\']/', $path)
			|| preg_match('/(?:javascript|data)\s*:/i', $path)
		) {
			return html_escape(base_url($fallbackPath));
		}

		$path = str_replace('\\', '/', $path);
		$path = ltrim($path, '/');

		if (
			strpos($path, '..') !== false
			|| !preg_match('/^[A-Za-z0-9._\/-]+$/', $path)
		) {
			return html_escape(base_url($fallbackPath));
		}

		$segments = array_map('rawurlencode', explode('/', $path));
		$safePath = implode('/', $segments);

		return html_escape(base_url(rtrim($uploadBase, '/') . '/' . $safePath));
	}
}
