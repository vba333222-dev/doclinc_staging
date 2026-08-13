<?php

define('BASEPATH', __DIR__);

class FakeLoad
{
	public function helper($name)
	{
		return $name;
	}
}

class FakeConfig
{
	public $care_team_enabled = true;

	public function item($key)
	{
		return $key === 'care_team_workflow_enabled' ? $this->care_team_enabled : null;
	}
}

class FakeChatResult
{
	private $row;
	private $rows;

	public function __construct($row = null, $rows = array())
	{
		$this->row = $row;
		$this->rows = $rows;
	}

	public function row() { return $this->row; }
	public function result() { return $this->rows; }
}

class FakeChatDb
{
	public $canonical = true;
	public $people;
	public $message_senders = array();
	public $users = array();
	public $where_in_values = array();
	private $from_table = '';
	private $user_id = 0;

	public function field_exists($field, $table)
	{
		return $this->canonical && in_array($field, array('responsible_doctor_user_id', 'visit_performer_user_id'), true);
	}

	public function table_exists($table)
	{
		return $table === 'consultation_messages';
	}

	public function select($value) { return $this; }
	public function from($value) { $this->from_table = $value; return $this; }
	public function join($table, $condition, $type = '') { return $this; }
	public function where($key, $value = null, $escape = null) { if ($key === 'userId') $this->user_id = (int) $value; return $this; }
	public function order_by($key, $direction) { return $this; }
	public function group_by($key) { return $this; }
	public function limit($value) { return $this; }
	public function where_in($key, $values) { $this->where_in_values = array_map('intval', $values); return $this; }

	public function get($table = null)
	{
		$table = $table ?: $this->from_table;
		if ($table === 'consultation_messages') {
			$rows = array_map(static function ($user_id) { return (object) array('sender_user_id' => $user_id); }, array_slice(array_values(array_unique($this->message_senders)), 0, 2));
			return new FakeChatResult(isset($rows[0]) ? $rows[0] : null, $rows);
		}
		if ($table === 'users') {
			return new FakeChatResult(isset($this->users[$this->user_id]) ? $this->users[$this->user_id] : null);
		}
		return new FakeChatResult($this->people);
	}
}

class CI_Model
{
	public $load;
	public $db;
	public $config;

	public function __construct()
	{
		$this->load = new FakeLoad();
		$this->db = $GLOBALS['phase7_chat_db'];
		$this->config = $GLOBALS['phase7_chat_config'];
	}
}

function base_url($path = '')
{
	return '/' . ltrim($path, '/');
}

function doclinc_request_row($request_id)
{
	return clone $GLOBALS['phase7_chat_request'];
}

function doclinc_request_handling_nakes_id($request)
{
	if ($GLOBALS['phase7_chat_config']->care_team_enabled) {
		foreach (array('responsible_doctor_user_id', 'visit_performer_user_id') as $field) {
			if (isset($request->{$field}) && (int) $request->{$field} > 0) return (int) $request->{$field};
		}
		return null;
	}
	foreach (array('assigned_nakes_user_id', 'accepted_by_user_id', 'dokter_id') as $field) {
		if (isset($request->{$field}) && (int) $request->{$field} > 0) return (int) $request->{$field};
	}
	return null;
}

function doclinc_dokter_identity_context($user_id, $refresh = false)
{
	return isset($GLOBALS['phase7_chat_identities'][(int) $user_id])
		? $GLOBALS['phase7_chat_identities'][(int) $user_id]
		: array('valid' => false, 'account_type' => 'unclassified', 'puskesmas_code' => '');
}

require_once dirname(__DIR__, 2) . '/application/modules/chat/models/Chat_m.php';

$passed = 0;
$failed = 0;

function phase7_expect($condition, $name)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$name}\n";
		return;
	}
	$failed++;
	echo "FAIL {$name}\n";
}

function phase7_identity($account_type, $facility = 'PKM01')
{
	return array('valid' => true, 'account_type' => $account_type, 'puskesmas_code' => $facility);
}

$GLOBALS['phase7_chat_db'] = new FakeChatDb();
$GLOBALS['phase7_chat_config'] = new FakeConfig();
$GLOBALS['phase7_chat_request'] = (object) array(
	'request_id' => 10,
	'assigned_puskesmas_code' => 'PKM01',
	'dokter_id' => 900,
	'assigned_nakes_user_id' => 101,
	'accepted_by_user_id' => 0,
	'responsible_doctor_user_id' => 101,
	'visit_performer_user_id' => 102,
);
$GLOBALS['phase7_chat_identities'] = array(
	101 => phase7_identity('personal'),
	102 => phase7_identity('personal'),
	900 => phase7_identity('command_center'),
);
$GLOBALS['phase7_chat_db']->users = array(
	101 => (object) array('userId' => 101, 'nama' => 'Dokter Lama'),
	102 => (object) array('userId' => 102, 'nama' => 'Perawat Dua'),
	900 => (object) array('userId' => 900, 'nama' => 'Puskesmas PKM01'),
);
$GLOBALS['phase7_chat_db']->people = (object) array(
	'responsible_doctor_user_id' => 101,
	'visit_performer_user_id' => 102,
	'responsible_doctor_name' => 'Dokter Satu',
	'visit_performer_name' => 'Perawat Dua',
);
$GLOBALS['phase7_chat_db']->message_senders = array(102);
$model = new Chat_m();
$request = $model->get_request_for_chat(10);
phase7_expect($request->chat_clinician_user_id === 102 && $request->chat_clinician_name === 'Perawat Dua', 'feature_on_uses_canonical_personal_sender');
phase7_expect($GLOBALS['phase7_chat_db']->where_in_values === array(101, 102), 'sender_query_is_limited_to_canonical_personal_clinicians');

$GLOBALS['phase7_chat_db']->message_senders = array(101);
$initial = $model->get_chat_participant_payload(10);
$GLOBALS['phase7_chat_db']->message_senders = array(101, 102);
$changed = $model->get_chat_participant_payload(10);
phase7_expect($initial['name'] === 'Dokter Satu' && $changed['name'] === 'Tenaga kesehatan' && $changed['neutral'] === true, 'clinician_change_becomes_neutral_instead_of_stale');
phase7_expect(array_keys($changed) === array('name', 'photo_url', 'neutral'), 'polling_payload_exposes_no_participant_id');

$GLOBALS['phase7_chat_db']->people = (object) array(
	'responsible_doctor_user_id' => 900,
	'visit_performer_user_id' => 102,
	'responsible_doctor_name' => 'Puskesmas PKM01',
	'visit_performer_name' => 'Perawat Dua',
);
$GLOBALS['phase7_chat_db']->message_senders = array();
$request = $model->get_request_for_chat(10);
phase7_expect($request->chat_clinician_user_id === 102 && $request->chat_clinician_name === 'Perawat Dua', 'command_center_is_excluded_from_personal_chat_identity');

$GLOBALS['phase7_chat_identities'][102] = phase7_identity('personal', 'PKM02');
$request = $model->get_request_for_chat(10);
phase7_expect($request->chat_clinician_user_id === 0 && $request->chat_clinician_name === 'Tenaga kesehatan', 'cross_facility_identity_uses_neutral_fallback');
$GLOBALS['phase7_chat_identities'][102] = phase7_identity('personal');

$GLOBALS['phase7_chat_config']->care_team_enabled = false;
$GLOBALS['phase7_chat_db']->canonical = true;
$GLOBALS['phase7_chat_db']->people = (object) array(
	'responsible_doctor_user_id' => 102,
	'visit_performer_user_id' => 0,
	'responsible_doctor_name' => 'Nama Kanonis',
	'visit_performer_name' => null,
);
$request = $model->get_request_for_chat(10);
phase7_expect($request->chat_clinician_user_id === 101 && $request->chat_clinician_name === 'Dokter Lama', 'feature_off_ignores_populated_canonical_columns');

$GLOBALS['phase7_chat_request']->responsible_doctor_user_id = null;
$GLOBALS['phase7_chat_request']->visit_performer_user_id = null;
$request = $model->get_request_for_chat(10);
phase7_expect($request->chat_clinician_user_id === 101 && $request->chat_clinician_name === 'Dokter Lama', 'feature_off_nullable_canonical_columns_keep_legacy_identity');

$GLOBALS['phase7_chat_config']->care_team_enabled = true;
$GLOBALS['phase7_chat_db']->people = (object) array(
	'responsible_doctor_user_id' => null,
	'visit_performer_user_id' => null,
	'responsible_doctor_name' => null,
	'visit_performer_name' => null,
);
$request = $model->get_request_for_chat(10);
phase7_expect($request->chat_clinician_user_id === 0 && $request->chat_clinician_name === 'Tenaga kesehatan', 'feature_on_nullable_canonical_identity_fails_neutral');

$GLOBALS['phase7_chat_db']->canonical = false;
$request = $model->get_request_for_chat(10);
phase7_expect($request->chat_clinician_user_id === 0 && $request->chat_clinician_name === 'Tenaga kesehatan', 'feature_on_missing_schema_does_not_restore_legacy_identity');

echo "PHASE7_CHAT_IDENTITY_PASS={$passed}\n";
echo "PHASE7_CHAT_IDENTITY_FAIL={$failed}\n";
exit($failed === 0 ? 0 : 1);
