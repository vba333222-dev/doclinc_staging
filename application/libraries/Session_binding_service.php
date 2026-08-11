<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Session_binding_policy.php';

class Session_binding_service
{
	private $db;
	private $policy;

	public function __construct($db = null)
	{
		if ($db === null) {
			$CI = &get_instance();
			$db = $CI->db;
		}
		$this->db = $db;
		$this->policy = new Session_binding_policy();
	}

	public function schemaReady()
	{
		if (!$this->db->table_exists('users') || !$this->db->table_exists('user_login_bindings')) {
			return false;
		}
		foreach (array('user_id', 'token_hash', 'issued_at', 'revoked_at', 'created_at', 'updated_at') as $field) {
			if (!$this->db->field_exists($field, 'user_login_bindings')) {
				return false;
			}
		}
		return $this->db->field_exists('userId', 'users')
			&& $this->db->field_exists('status', 'users');
	}

	public function issue($user_id, $expected_password_hash = null)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->schemaReady()
			|| ($expected_password_hash !== null && (!is_string($expected_password_hash) || $expected_password_hash === '' || !$this->db->field_exists('password', 'users')))) {
			return null;
		}
		$token = $this->policy->issueToken();
		$token_hash = $this->policy->tokenHash($token);
		if ($token_hash === null || !$this->db->trans_begin()) {
			return null;
		}
		$transaction_open = true;
		try {
			$user_query = $this->db->query(
				'SELECT userId, status' . ($expected_password_hash !== null ? ', password' : '') . ' FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
				array($user_id)
			);
			$user = $user_query ? $user_query->row() : null;
			if (!$user || (string) $user->status !== 'aktif'
				|| ($expected_password_hash !== null && !hash_equals($expected_password_hash, (string) $user->password))) {
				$this->db->trans_rollback();
				$transaction_open = false;
				return null;
			}
			$now = date('Y-m-d H:i:s');
			$written = $this->db->query(
				'INSERT INTO ' . $this->db->dbprefix('user_login_bindings')
				. ' (user_id, token_hash, issued_at, revoked_at, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?) '
				. 'ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), issued_at = VALUES(issued_at), revoked_at = NULL, updated_at = VALUES(updated_at)',
				array($user_id, $token_hash, $now, $now, $now)
			);
			if (!$written || $this->db->trans_status() === false || !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				$transaction_open = false;
				return null;
			}
			$transaction_open = false;
			return $token;
		} catch (Throwable $exception) {
			return null;
		} finally {
			if ($transaction_open) {
				$this->db->trans_rollback();
			}
			$token_hash = null;
		}
	}

	public function rotatePassword($user_id, $current_token, $expected_password_hash, $new_password_hash)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->policy->tokenValid($current_token)
			|| !is_string($expected_password_hash) || $expected_password_hash === ''
			|| !is_string($new_password_hash) || $new_password_hash === ''
			|| !$this->schemaReady() || !$this->db->field_exists('password', 'users')) {
			return null;
		}
		if (!$this->db->trans_begin()) {
			return null;
		}
		$transaction_open = true;
		$new_token = null;
		try {
			$user_query = $this->db->query(
				'SELECT userId, status, password FROM ' . $this->db->dbprefix('users') . ' WHERE userId = ? FOR UPDATE',
				array($user_id)
			);
			$user = $user_query ? $user_query->row() : null;
			if (!$user || (string) $user->status !== 'aktif'
				|| !hash_equals($expected_password_hash, (string) $user->password)
				|| !$this->validateLocked($user_id, $current_token)) {
				$this->db->trans_rollback();
				$transaction_open = false;
				return null;
			}
			$now = date('Y-m-d H:i:s');
			$password_updated = $this->db->where('userId', $user_id)->where('password', $expected_password_hash)
				->update('users', array('password' => $new_password_hash, 'updated_at' => $now));
			if (!$password_updated || $this->db->affected_rows() !== 1) {
				$this->db->trans_rollback();
				$transaction_open = false;
				return null;
			}
			$new_token = $this->rotateLocked($user_id, $current_token);
			if (!is_string($new_token)
				|| $this->db->trans_status() === false || !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				$transaction_open = false;
				return null;
			}
			$transaction_open = false;
			return $new_token;
		} catch (Throwable $exception) {
			return null;
		} finally {
			if ($transaction_open) {
				$this->db->trans_rollback();
			}
		}
	}

	public function rotateLocked($user_id, $current_token)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->policy->tokenValid($current_token) || !$this->schemaReady()
			|| !$this->validateLocked($user_id, $current_token)) {
			return null;
		}
		$new_token = $this->policy->issueToken();
		$new_hash = $this->policy->tokenHash($new_token);
		$current_hash = $this->policy->tokenHash($current_token);
		if ($new_hash === null || $current_hash === null) {
			return null;
		}
		$now = date('Y-m-d H:i:s');
		$updated = $this->db->where('user_id', $user_id)
			->where('token_hash', $current_hash)
			->where('revoked_at IS NULL', null, false)
			->update('user_login_bindings', array(
				'token_hash' => $new_hash,
				'issued_at' => $now,
				'revoked_at' => null,
				'updated_at' => $now,
			));
		$new_hash = null;
		$current_hash = null;
		return $updated && $this->db->affected_rows() === 1 && $this->db->trans_status() !== false
			? $new_token
			: null;
	}

	public function validate($user_id, $token)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->policy->tokenValid($token) || !$this->schemaReady()) {
			return false;
		}
		$row = $this->db
			->select('user_login_bindings.token_hash, user_login_bindings.revoked_at, users.status')
			->from('user_login_bindings')
			->join('users', 'users.userId = user_login_bindings.user_id', 'inner')
			->where('user_id', $user_id)
			->limit(1)
			->get()
			->row();
		return $row
			&& (string) $row->status === 'aktif'
			&& $row->revoked_at === null
			&& $this->policy->matches($token, $row->token_hash);
	}

	public function validateLocked($user_id, $token)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->policy->tokenValid($token) || !$this->schemaReady()) {
			return false;
		}
		$query = $this->db->query(
			'SELECT bindings.token_hash, bindings.revoked_at, users.status FROM '
			. $this->db->dbprefix('user_login_bindings') . ' bindings INNER JOIN '
			. $this->db->dbprefix('users') . ' users ON users.userId = bindings.user_id '
			. 'WHERE bindings.user_id = ? FOR UPDATE',
			array($user_id)
		);
		$row = $query ? $query->row() : null;
		return $row
			&& (string) $row->status === 'aktif'
			&& $row->revoked_at === null
			&& $this->policy->matches($token, $row->token_hash);
	}

	public function revokeCurrent($user_id, $token)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->policy->tokenValid($token) || !$this->schemaReady()) {
			return false;
		}
		if (!$this->db->trans_begin()) {
			return false;
		}
		try {
			$row_query = $this->db->query(
				'SELECT token_hash, revoked_at FROM ' . $this->db->dbprefix('user_login_bindings') . ' WHERE user_id = ? FOR UPDATE',
				array($user_id)
			);
			$row = $row_query ? $row_query->row() : null;
			if (!$row || $row->revoked_at !== null || !$this->policy->matches($token, $row->token_hash)) {
				$this->db->trans_rollback();
				return false;
			}
			if (!$this->revokeLocked($user_id) || !$this->db->trans_commit()) {
				$this->db->trans_rollback();
				return false;
			}
			return true;
		} catch (Throwable $exception) {
			$this->db->trans_rollback();
			return false;
		}
	}

	public function revokeLocked($user_id)
	{
		$user_id = (int) $user_id;
		if ($user_id < 1 || !$this->schemaReady()) {
			return false;
		}
		$now = date('Y-m-d H:i:s');
		$updated = $this->db
			->where('user_id', $user_id)
			->where('revoked_at IS NULL', null, false)
			->update('user_login_bindings', array(
				'token_hash' => null,
				'revoked_at' => $now,
				'updated_at' => $now,
			));
		return $updated && $this->db->trans_status() !== false;
	}
}
