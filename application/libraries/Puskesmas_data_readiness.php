<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Puskesmas_data_readiness
{
	public function summarize(array $role_state, array $staff_rows)
	{
		require_once __DIR__ . '/Nakes_profile_readiness_policy.php';
		$profile_policy = new Nakes_profile_readiness_policy();
		$managed_fields = isset($role_state['managed_fields']) && is_array($role_state['managed_fields'])
			? array_values(array_unique($role_state['managed_fields']))
			: array();
		$facility_fields = array_values(array_filter($managed_fields, function ($field) {
			return $field === 'puskesmas' || strpos((string) $field, 'facility_') === 0;
		}));

		$summary = array(
			'facility_complete' => empty($facility_fields),
			'facility_missing_count' => count($facility_fields),
			'staff_total' => count($staff_rows),
			'staff_ready' => 0,
			'staff_missing_sip' => 0,
			'staff_sip_active' => 0,
			'staff_sip_expiring' => 0,
			'staff_sip_expired' => 0,
			'staff_missing_core_data' => 0,
			'staff_missing_title' => 0,
			'staff_linked' => 0,
			'staff_unlinked' => 0,
			'staff_invalid_account' => 0,
			'attention_count' => count($facility_fields),
			'complete' => false,
		);

		foreach ($staff_rows as $staff) {
			$account_state = $this->value($staff, 'personal_account_state');
			$state = $profile_policy->evaluate(array(
				'name' => $this->value($staff, 'account_name'),
				'title' => $this->value($staff, 'gelar'),
				'birthdate' => $this->value($staff, 'account_birthdate'),
				'gender' => $this->value($staff, 'account_gender'),
				'profession' => $this->value($staff, 'profesi'),
				'registration_number' => $this->value($staff, 'nomor_sip'),
				'registration_expires_at' => $this->value($staff, 'sip_expired_at'),
				'phone' => $this->value($staff, 'account_phone'),
				'account_state' => $account_state,
				'staff_status' => $this->value($staff, 'status'),
				'account_status' => $this->value($staff, 'account_status'),
				'facility_status' => 'aktif',
			));
			if (array_intersect(array('name', 'birthdate', 'gender', 'phone', 'profession'), $state['missing_fields'])) {
				$summary['staff_missing_core_data']++;
			}
			if (in_array('title', $state['missing_fields'], true)) {
				$summary['staff_missing_title']++;
			}
			if ($state['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_MISSING || $state['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_INVALID) {
				$summary['staff_missing_sip']++;
			} elseif ($state['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_EXPIRED) {
				$summary['staff_sip_expired']++;
			} elseif ($state['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_EXPIRING) {
				$summary['staff_sip_expiring']++;
			} elseif ($state['sip_state'] === Nakes_profile_readiness_policy::SIP_STATE_ACTIVE) {
				$summary['staff_sip_active']++;
			}
			if ($account_state === 'linked') {
				$summary['staff_linked']++;
			} elseif ($account_state === 'unlinked') {
				$summary['staff_unlinked']++;
			} else {
				$summary['staff_invalid_account']++;
			}

			if ($state['operationally_ready']) {
				$summary['staff_ready']++;
			}
		}

		$summary['attention_count'] += $summary['staff_missing_sip']
			+ $summary['staff_sip_expiring']
			+ $summary['staff_sip_expired']
			+ $summary['staff_missing_core_data']
			+ $summary['staff_missing_title']
			+ $summary['staff_unlinked']
			+ $summary['staff_invalid_account'];
		$summary['complete'] = $summary['facility_complete']
			&& $summary['staff_total'] > 0
			&& $summary['staff_ready'] === $summary['staff_total'];

		return $summary;
	}

	private function value($row, $field)
	{
		if (is_object($row) && isset($row->{$field})) {
			return trim((string) $row->{$field});
		}
		if (is_array($row) && isset($row[$field])) {
			return trim((string) $row[$field]);
		}
		return '';
	}
}
