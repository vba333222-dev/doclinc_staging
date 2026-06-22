<?php
defined('BASEPATH') or exit('No direct script access allowed');

function doclinc_password_is_hash($stored)
{
	if (!is_string($stored) || $stored === '') {
		return false;
	}

	$info = password_get_info($stored);
	return !empty($info['algo']);
}

function doclinc_password_verify($plain, $stored)
{
	if (!is_string($plain) || !is_string($stored) || $stored === '') {
		return false;
	}

	if (doclinc_password_is_hash($stored)) {
		return password_verify($plain, $stored);
	}

	if (preg_match('/^[a-f0-9]{40}$/i', $stored)) {
		return hash_equals(strtolower($stored), sha1($plain));
	}

	return false;
}

function doclinc_password_needs_rehash($stored)
{
	return !doclinc_password_is_hash($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT);
}

function doclinc_password_hash($plain)
{
	return password_hash($plain, PASSWORD_DEFAULT);
}
