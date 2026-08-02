<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Puskesmas_data_readiness
{
	public function summarize(array $role_state, array $staff_rows)
	{
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
			'staff_missing_nip' => 0,
			'staff_missing_core_data' => 0,
			'staff_linked' => 0,
			'staff_unlinked' => 0,
			'staff_invalid_account' => 0,
			'attention_count' => count($facility_fields),
			'complete' => false,
		);

		foreach ($staff_rows as $staff) {
			$name = $this->value($staff, 'nama');
			$phone = preg_replace('/[^0-9+]/', '', $this->value($staff, 'no_hp'));
			$profession = $this->value($staff, 'profesi');
			$sip = $this->value($staff, 'nomor_sip');
			$nip_ready = (int) $this->value($staff, 'nip_ready') === 1;
			$account_state = $this->value($staff, 'personal_account_state');
			$core_ready = $name !== '' && $profession !== '' && preg_match('/^\+?[0-9]{8,20}$/', $phone) === 1;

			if (!$core_ready) {
				$summary['staff_missing_core_data']++;
			}
			if ($sip === '') {
				$summary['staff_missing_sip']++;
			}
			if (!$nip_ready) {
				$summary['staff_missing_nip']++;
			}
			if ($account_state === 'linked') {
				$summary['staff_linked']++;
			} elseif ($account_state === 'unlinked') {
				$summary['staff_unlinked']++;
			} else {
				$summary['staff_invalid_account']++;
			}

			if ($core_ready && $sip !== '' && $nip_ready && $account_state === 'linked') {
				$summary['staff_ready']++;
			}
		}

		$summary['attention_count'] += $summary['staff_missing_sip']
			+ $summary['staff_missing_nip']
			+ $summary['staff_missing_core_data']
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
