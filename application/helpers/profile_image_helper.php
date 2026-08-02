<?php
defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('doclinc_profile_image_fallback_src')) {
	function doclinc_profile_image_fallback_src($fallbackPath = 'assets/doclinc/img/default-profile.png')
	{
		return html_escape(base_url($fallbackPath));
	}
}

if (!function_exists('doclinc_profile_image_src')) {
	function doclinc_profile_image_src($user_id, $path, $fallbackPath = 'assets/doclinc/img/default-profile.png')
	{
		$user_id = (int) $user_id;
		$path = trim(str_replace('\\', '/', (string) $path));
		if ($user_id < 1 || $path === '' || strpos($path, '..') !== false || preg_match('/[<>"\']/', $path)) {
			return doclinc_profile_image_fallback_src($fallbackPath);
		}
		$private_key = preg_match('#^profile-images/[a-f0-9]{16,64}\.(?:jpe?g|png|webp)$#i', $path) === 1;
		$legacy_key = strlen($path) <= 176
			&& preg_match('#^(?:uploads/profile/)?[A-Za-z0-9][A-Za-z0-9._-]*\.(?:jpe?g|png|webp)$#i', $path) === 1;
		if (!$private_key && !$legacy_key) {
			return doclinc_profile_image_fallback_src($fallbackPath);
		}
		return html_escape(base_url('profile/photo/' . $user_id));
	}
}

if (!function_exists('doclinc_safe_profile_image_src')) {
	function doclinc_safe_profile_image_src($path, $uploadBase = '', $fallbackPath = 'assets/doclinc/img/default-profile.png')
	{
		// Compatibility shim: callers without an explicit subject identity must not
		// expose legacy uploads directly. Migrate them to doclinc_profile_image_src().
		return doclinc_profile_image_fallback_src($fallbackPath);
	}
}
