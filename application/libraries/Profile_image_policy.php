<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile_image_policy
{
	private $db;

	public function __construct($db = null)
	{
		$this->db = $db ?: get_instance()->db;
	}

	public function can_view($actor, $target)
	{
		if (!$this->valid_user($actor) || !$this->valid_user($target)) {
			return false;
		}

		$actor_id = (int) $actor->userId;
		$target_id = (int) $target->userId;
		if ($actor_id === $target_id) {
			return true;
		}

		if ((string) $actor->role === 'warga' && (string) $target->role === 'dokter') {
			return $this->consultation_relationship($actor_id, $target_id, false);
		}
		if ((string) $actor->role === 'dokter' && (string) $target->role === 'warga') {
			return $this->consultation_relationship($target_id, $actor_id, true);
		}
		if ((string) $actor->role === 'dokter' && (string) $target->role === 'dokter') {
			$actor_identity = doclinc_dokter_identity_context($actor_id, true);
			$target_identity = doclinc_dokter_identity_context($target_id, true);
			return !empty($actor_identity['valid'])
				&& !empty($actor_identity['is_command_center'])
				&& !empty($target_identity['valid'])
				&& !empty($target_identity['is_personal'])
				&& (string) $actor_identity['puskesmas_code'] === (string) $target_identity['puskesmas_code'];
		}

		return false;
	}

	private function consultation_relationship($warga_id, $nakes_id, $allow_command_center)
	{
		if ($warga_id < 1 || $nakes_id < 1 || !$this->db->table_exists('requests')) {
			return false;
		}

		$rows = $this->db
			->where('user_id', $warga_id)
			->where_in('request_status', array('Accepted', 'Completed', 'Cancelled'))
			->order_by('request_id', 'DESC')
			->limit(100)
			->get('requests')
			->result();
		$identity = $allow_command_center ? doclinc_dokter_identity_context($nakes_id, true) : null;
		foreach ($rows as $request) {
			if (doclinc_request_is_handled_by_nakes($request, $nakes_id)) {
				return true;
			}
			if ($allow_command_center
				&& is_array($identity)
				&& !empty($identity['valid'])
				&& !empty($identity['is_command_center'])
				&& doclinc_request_assigned_puskesmas_code($request) !== ''
				&& (string) doclinc_request_assigned_puskesmas_code($request) === (string) $identity['puskesmas_code']) {
				return true;
			}
		}

		return false;
	}

	private function valid_user($user)
	{
		return is_object($user)
			&& isset($user->userId, $user->role, $user->status, $user->must_change_password)
			&& (int) $user->userId > 0
			&& in_array((string) $user->role, array('warga', 'dokter'), true)
			&& (string) $user->status === 'aktif'
			&& (int) $user->must_change_password === 0;
	}
}
