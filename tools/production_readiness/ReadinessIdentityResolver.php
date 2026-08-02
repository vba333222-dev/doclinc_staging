<?php

final class ReadinessIdentityResolver
{
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function resolve($user_id)
	{
		$user_id = (int) $user_id;
		$context = array(
			'valid' => false,
			'account_type' => 'unclassified',
			'user_id' => $user_id,
			'puskesmas_code' => '',
			'staff_id' => null,
			'is_command_center' => false,
			'is_personal' => false,
		);
		if ($user_id < 1) {
			return $context;
		}

		$user = $this->db->select('userId, role, status, remark')
			->where('userId', $user_id)->limit(1)->get('users')->row();
		if (!$user || (string) $user->role !== 'dokter' || (string) $user->status !== 'aktif') {
			return $context;
		}
		$code = trim((string) $user->remark);
		if ($code === '' || strtoupper($code) === 'DEFAULT') {
			return $context;
		}
		$facility = $this->db->select('kode_pkm, status')->where('kode_pkm', $code)->limit(1)->get('m_puskesmas')->row();
		if (!$facility || (string) $facility->status !== 'aktif') {
			return $context;
		}
		$context['puskesmas_code'] = $code;

		$canonical = $this->db->select('userId')->where('role', 'dokter')->where('status', 'aktif')
			->where('TRIM(remark) = ' . $this->db->escape($code), null, false)
			->order_by('userId', 'ASC')->limit(1)->get('users')->row();
		$staff_rows = $this->db->select('staff_id, user_id, kode_pkm, status')
			->where('user_id', $user_id)->order_by('staff_id', 'ASC')->get('puskesmas_staff')->result();

		if ($canonical && (int) $canonical->userId === $user_id) {
			if (count($staff_rows) !== 0) {
				return $context;
			}
			$context['valid'] = true;
			$context['account_type'] = 'command_center';
			$context['is_command_center'] = true;
			return $context;
		}
		if (count($staff_rows) !== 1) {
			return $context;
		}
		$staff = $staff_rows[0];
		if ((string) $staff->status !== 'aktif' || trim((string) $staff->kode_pkm) !== $code) {
			return $context;
		}
		$context['valid'] = true;
		$context['account_type'] = 'personal';
		$context['staff_id'] = (int) $staff->staff_id;
		$context['is_personal'] = true;
		return $context;
	}

	public function commandCenterReady($puskesmas_code)
	{
		$puskesmas_code = trim((string) $puskesmas_code);
		if ($puskesmas_code === '' || strtoupper($puskesmas_code) === 'DEFAULT') {
			return false;
		}
		$canonical = $this->db->select('userId')->where('role', 'dokter')->where('status', 'aktif')
			->where('TRIM(remark) = ' . $this->db->escape($puskesmas_code), null, false)
			->order_by('userId', 'ASC')->limit(1)->get('users')->row();
		if (!$canonical) {
			return false;
		}
		$identity = $this->resolve((int) $canonical->userId);
		return !empty($identity['valid'])
			&& (string) ($identity['account_type'] ?? '') === 'command_center'
			&& (string) ($identity['puskesmas_code'] ?? '') === $puskesmas_code;
	}
}
