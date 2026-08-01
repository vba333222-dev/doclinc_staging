<?php
defined('BASEPATH') or exit('No direct script access allowed');

class MY_Security extends CI_Security
{
	public function csrf_set_cookie()
	{
		$secure_cookie = (bool) config_item('cookie_secure');
		if ($secure_cookie && !is_https()) {
			return false;
		}

		setcookie($this->_csrf_cookie_name, $this->_csrf_hash, array(
			'expires' => time() + $this->_csrf_expire,
			'path' => (string) config_item('cookie_path'),
			'domain' => (string) config_item('cookie_domain'),
			'secure' => $secure_cookie,
			'httponly' => (bool) config_item('cookie_httponly'),
			'samesite' => (string) (config_item('cookie_samesite') ?: 'Lax'),
		));
		log_message('info', 'CSRF cookie sent');

		return $this;
	}

	public function csrf_verify()
	{
		if (config_item('csrf_protection') === true
			&& strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST'
			&& !isset($_POST[$this->_csrf_token_name])) {
			$header_token = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
				? trim((string) $_SERVER['HTTP_X_CSRF_TOKEN'])
				: '';
			if (preg_match('/^[0-9a-f]{32}$/i', $header_token) === 1) {
				$_POST[$this->_csrf_token_name] = $header_token;
			}
		}

		return parent::csrf_verify();
	}

	public function csrf_show_error()
	{
		$accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
		$content_type = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
		$is_json = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
			|| stripos($accept, 'application/json') !== false
			|| stripos($content_type, 'application/json') !== false;

		if (!$is_json) {
			return parent::csrf_show_error();
		}

		http_response_code(403);
		header('Content-Type: application/json; charset=UTF-8');
		header('Cache-Control: no-store, private');
		echo json_encode(array(
			'success' => false,
			'safe_error_code' => 'csrf_validation_failed',
			'message' => 'Sesi keamanan sudah berubah. Muat ulang halaman lalu coba lagi.',
		));
		exit(7);
	}
}
