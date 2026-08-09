<?php

final class ProductionReadinessReport
{
	private const ACTOR_TYPES = array('warga', 'personal', 'command_center', 'denied');
	private const REMEDIATION_MODES = array('none', 'self_service', 'managed', 'mixed');
	private const DENIAL_REASONS = array('password_change_required', 'identity_invalid');
	private const MANAGED_GAPS = array(
		'facility_name', 'facility_address', 'facility_latitude', 'facility_longitude',
		'facility_command_center', 'staff_name', 'staff_phone', 'staff_profession',
		'staff_registration_number', 'staff_nip', 'staff_identity',
	);
	private const SAFE_FIELDS = array(
		'name', 'email', 'phone', 'address', 'birthdate', 'gender', 'photo',
		'nik', 'family_card_number', 'bpjs_number', 'nakes_identity', 'staff_link',
		'staff_name', 'staff_phone', 'profession', 'registration_number', 'nip',
		'puskesmas', 'facility_name', 'facility_address', 'facility_latitude',
		'facility_longitude',
	);

	public static function compile(array $states, array $schema_gaps, array $storage_state, array $operational_state = array())
	{
		$report = array(
			'actor_total' => 0,
			'actor_complete' => 0,
			'actor_incomplete' => 0,
			'actor_denied' => 0,
			'actor_password_change_required' => 0,
			'actor_first_login_pending' => 0,
			'actor_password_reset_pending' => 0,
			'actor_identity_denied' => 0,
			'actor_profile_evaluated' => 0,
			'actor_self_service_gap_count' => 0,
			'actor_managed_gap_count' => 0,
			'actor_type_counts' => array_fill_keys(self::ACTOR_TYPES, 0),
			'actor_type_complete' => array_fill_keys(self::ACTOR_TYPES, 0),
			'remediation_counts' => array_fill_keys(self::REMEDIATION_MODES, 0),
			'missing_field_counts' => array_fill_keys(self::SAFE_FIELDS, 0),
			'unknown_missing_field_count' => 0,
			'schema_gaps' => self::safeCodes($schema_gaps),
			'storage_ready' => !empty($storage_state['ready']),
			'storage_private' => !empty($storage_state['private']),
			'storage_mode_0700' => !empty($storage_state['mode_0700']),
			'storage_owner_ready' => !empty($storage_state['owner_ready']),
			'facility_total' => max(0, (int) ($operational_state['facility_total'] ?? 0)),
			'facility_complete' => 0,
			'facility_command_center_ready' => 0,
			'staff_total' => max(0, (int) ($operational_state['staff_total'] ?? 0)),
			'staff_ready' => 0,
			'managed_gap_counts' => array_fill_keys(self::MANAGED_GAPS, 0),
			'managed_gap_total' => 0,
			'managed_data_ready' => false,
			'controlled_enforcement_ready' => false,
			'activation_ready' => false,
			'blocker_count' => 0,
		);
		$report['facility_complete'] = min(
			$report['facility_total'],
			max(0, (int) ($operational_state['facility_complete'] ?? 0))
		);
		$report['facility_command_center_ready'] = min(
			$report['facility_total'],
			max(0, (int) ($operational_state['facility_command_center_ready'] ?? 0))
		);
		$report['staff_ready'] = min(
			$report['staff_total'],
			max(0, (int) ($operational_state['staff_ready'] ?? 0))
		);
		foreach (self::MANAGED_GAPS as $gap) {
			$maximum = strpos($gap, 'facility_') === 0 ? $report['facility_total'] : $report['staff_total'];
			$report['managed_gap_counts'][$gap] = max(
				0,
				min($maximum, (int) (($operational_state['managed_gap_counts'] ?? array())[$gap] ?? 0))
			);
		}
		$report['managed_gap_total'] = array_sum($report['managed_gap_counts']);

		foreach ($states as $state) {
			if (!is_array($state)) {
				continue;
			}
			$report['actor_total']++;
			$type = in_array((string) ($state['actor_type'] ?? ''), self::ACTOR_TYPES, true)
				? (string) $state['actor_type']
				: 'denied';
			$report['actor_type_counts'][$type]++;
			$complete = !empty($state['complete']);
			if ($complete) {
				$report['actor_complete']++;
				$report['actor_type_complete'][$type]++;
			} else {
				$report['actor_incomplete']++;
			}
			$denied = $type === 'denied' || (string) ($state['safe_error_code'] ?? '') === 'actor_denied';
			if ($denied) {
				$report['actor_denied']++;
			}
			$denial_reason = (string) ($state['audit_denial_reason'] ?? '');
			if (!in_array($denial_reason, self::DENIAL_REASONS, true)) {
				$denial_reason = $denied ? 'identity_invalid' : '';
			}
			if ($denial_reason === 'password_change_required' && !$denied) {
				$denial_reason = '';
			}
			if ($denial_reason === 'password_change_required') {
				$report['actor_password_change_required']++;
				$credential_state = (string) ($state['audit_credential_state'] ?? '');
				if ($credential_state === 'first_login_pending') {
					$report['actor_first_login_pending']++;
				} elseif ($credential_state === 'admin_reset_pending') {
					$report['actor_password_reset_pending']++;
				}
			} else {
				if ($denial_reason === 'identity_invalid') {
					$report['actor_identity_denied']++;
				} else {
					$report['actor_profile_evaluated']++;
				}
			}
			if (!empty($state['self_service_fields'])) {
				$report['actor_self_service_gap_count']++;
			}
			if (!empty($state['managed_fields'])) {
				$report['actor_managed_gap_count']++;
			}

			$mode = in_array((string) ($state['remediation_mode'] ?? ''), self::REMEDIATION_MODES, true)
				? (string) $state['remediation_mode']
				: 'none';
			$report['remediation_counts'][$mode]++;
			foreach ((array) ($state['missing_fields'] ?? array()) as $field) {
				$field = (string) $field;
				if (array_key_exists($field, $report['missing_field_counts'])) {
					$report['missing_field_counts'][$field]++;
				} else {
					$report['unknown_missing_field_count']++;
				}
			}
			$report['schema_gaps'] = array_values(array_unique(array_merge(
				$report['schema_gaps'],
				self::safeCodes((array) ($state['schema_gaps'] ?? array()))
			)));
		}

		sort($report['schema_gaps']);
		$operational_ready = $report['facility_total'] > 0
			&& $report['facility_complete'] === $report['facility_total']
			&& $report['facility_command_center_ready'] === $report['facility_total']
			&& $report['staff_total'] > 0
			&& $report['staff_ready'] === $report['staff_total'];
		$storage_ready = $report['storage_ready'] && $report['storage_private']
			&& $report['storage_mode_0700'] && $report['storage_owner_ready'];
		$report['managed_data_ready'] = $operational_ready
			&& $report['managed_gap_total'] === 0
			&& $report['actor_managed_gap_count'] === 0
			&& $report['actor_identity_denied'] === 0;
		$report['controlled_enforcement_ready'] = $report['actor_total'] > 0
			&& empty($report['schema_gaps'])
			&& $storage_ready
			&& $report['managed_data_ready']
			&& $report['actor_denied'] === $report['actor_password_change_required'];
		$report['blocker_count'] = $report['actor_incomplete']
			+ count($report['schema_gaps'])
			+ ($storage_ready ? 0 : 1)
			+ ($report['facility_total'] - $report['facility_complete'])
			+ ($report['facility_total'] - $report['facility_command_center_ready'])
			+ ($report['staff_total'] - $report['staff_ready'])
			+ ($operational_ready ? 0 : (($report['facility_total'] > 0 && $report['staff_total'] > 0) ? 0 : 1));
		$report['activation_ready'] = $report['actor_total'] > 0
			&& $report['actor_complete'] === $report['actor_total']
			&& $report['actor_denied'] === 0
			&& empty($report['schema_gaps'])
			&& $storage_ready
			&& $operational_ready;
		return $report;
	}

	public static function lines(array $report)
	{
		$lines = array(
			'ACTOR_TOTAL=' . (int) $report['actor_total'],
			'ACTOR_COMPLETE=' . (int) $report['actor_complete'],
			'ACTOR_INCOMPLETE=' . (int) $report['actor_incomplete'],
			'ACTOR_DENIED=' . (int) $report['actor_denied'],
			'ACTOR_PASSWORD_CHANGE_REQUIRED=' . (int) $report['actor_password_change_required'],
			'ACTOR_FIRST_LOGIN_PENDING=' . (int) $report['actor_first_login_pending'],
			'ACTOR_PASSWORD_RESET_PENDING=' . (int) $report['actor_password_reset_pending'],
			'ACTOR_IDENTITY_DENIED=' . (int) $report['actor_identity_denied'],
			'ACTOR_PROFILE_EVALUATED=' . (int) $report['actor_profile_evaluated'],
			'ACTOR_SELF_SERVICE_GAP_COUNT=' . (int) $report['actor_self_service_gap_count'],
			'ACTOR_MANAGED_GAP_COUNT=' . (int) $report['actor_managed_gap_count'],
		);
		foreach (self::ACTOR_TYPES as $type) {
			$key = strtoupper($type);
			$lines[] = 'ACTOR_TYPE_' . $key . '_TOTAL=' . (int) $report['actor_type_counts'][$type];
			$lines[] = 'ACTOR_TYPE_' . $key . '_COMPLETE=' . (int) $report['actor_type_complete'][$type];
		}
		foreach (self::REMEDIATION_MODES as $mode) {
			$lines[] = 'REMEDIATION_' . strtoupper($mode) . '=' . (int) $report['remediation_counts'][$mode];
		}
		foreach (self::SAFE_FIELDS as $field) {
			$count = (int) $report['missing_field_counts'][$field];
			if ($count > 0) {
				$lines[] = 'MISSING_' . strtoupper($field) . '=' . $count;
			}
		}
		$lines[] = 'UNKNOWN_MISSING_FIELD_COUNT=' . (int) $report['unknown_missing_field_count'];
		$lines[] = 'SCHEMA_GAP_COUNT=' . count($report['schema_gaps']);
		foreach ($report['schema_gaps'] as $index => $gap) {
			$lines[] = 'SCHEMA_GAP_' . ($index + 1) . '=' . $gap;
		}
		$lines[] = 'PROFILE_STORAGE_READY=' . ($report['storage_ready'] ? 'true' : 'false');
		$lines[] = 'PROFILE_STORAGE_PRIVATE=' . ($report['storage_private'] ? 'true' : 'false');
		$lines[] = 'PROFILE_STORAGE_MODE_0700=' . ($report['storage_mode_0700'] ? 'true' : 'false');
		$lines[] = 'PROFILE_STORAGE_OWNER_READY=' . ($report['storage_owner_ready'] ? 'true' : 'false');
		$lines[] = 'ACTIVE_FACILITY_TOTAL=' . (int) $report['facility_total'];
		$lines[] = 'ACTIVE_FACILITY_COMPLETE=' . (int) $report['facility_complete'];
		$lines[] = 'ACTIVE_FACILITY_COMMAND_CENTER_READY=' . (int) $report['facility_command_center_ready'];
		$lines[] = 'ACTIVE_STAFF_TOTAL=' . (int) $report['staff_total'];
		$lines[] = 'ACTIVE_STAFF_READY=' . (int) $report['staff_ready'];
		foreach (self::MANAGED_GAPS as $gap) {
			$lines[] = 'MANAGED_GAP_' . strtoupper($gap) . '=' . (int) $report['managed_gap_counts'][$gap];
		}
		$lines[] = 'MANAGED_GAP_TOTAL=' . (int) $report['managed_gap_total'];
		$lines[] = 'BLOCKER_COUNT=' . (int) $report['blocker_count'];
		$lines[] = 'MANAGED_DATA_READY=' . ($report['managed_data_ready'] ? 'true' : 'false');
		$lines[] = 'CONTROLLED_ENFORCEMENT_READY=' . ($report['controlled_enforcement_ready'] ? 'true' : 'false');
		$lines[] = 'PRODUCTION_ACTIVATION_READY=' . ($report['activation_ready'] ? 'true' : 'false');
		return $lines;
	}

	private static function safeCodes(array $codes)
	{
		$safe = array();
		foreach ($codes as $code) {
			$code = (string) $code;
			if (preg_match('/\A[a-z0-9_.-]{1,80}\z/D', $code) === 1) {
				$safe[] = $code;
			}
		}
		return array_values(array_unique($safe));
	}
}
