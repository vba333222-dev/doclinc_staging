<?php

define('BASEPATH', __DIR__);

function html_escape($value)
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url($path = '')
{
	return '/' . ltrim((string) $path, '/');
}

function doclinc_warga_visit_timeline($timeline)
{
	return empty($timeline) ? '' : '<div class="doclinc-visit-timeline">Linimasa kunjungan</div>';
}

function doclinc_request_queue_code($request)
{
	return 'A-' . (int) $request->request_id;
}

function doclinc_consultation_mode_label($mode)
{
	return $mode === 'visit' ? 'Kunjungan' : ($mode === 'non_visit' ? 'Tanpa kunjungan' : 'Belum dipilih');
}

function doclinc_normalize_visit_status($status)
{
	$status = strtolower(trim((string) $status));
	return in_array($status, array('not_started', 'en_route', 'arrived', 'in_service', 'completed'), true) ? $status : '';
}

class MX_Controller {}

class Phase7Config
{
	public $enabled = false;
	public function item($key) { return $key === 'care_team_workflow_enabled' ? $this->enabled : null; }
}

class Phase7SchemaDb
{
	public $schema_ready = true;
	public function field_exists($field, $table)
	{
		return $this->schema_ready && $table === 'requests'
			&& in_array($field, array('responsible_doctor_user_id', 'visit_performer_user_id'), true);
	}
}

require_once dirname(__DIR__, 2) . '/application/modules/home/models/Home_m.php';

class Phase7HomeModel extends Home_m
{
	public $config;
	public function __construct($db, $config) { $this->db = $db; $this->config = $config; }
	public function careTeamDisplayReady()
	{
		$method = new ReflectionMethod(Home_m::class, 'care_team_display_ready');
		$method->setAccessible(true);
		return $method->invoke($this);
	}
}

class Phase7Session
{
	public $user_id = 0;
	public function userdata($key) { return $key === 'id' ? $this->user_id : null; }
}

class Phase7ViewContext
{
	public $session;
	public function __construct($user_id) { $this->session = new Phase7Session(); $this->session->user_id = $user_id; }
}

$passed = 0;
$failed = 0;

function phase7_render_expect($condition, $name)
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

function render_warga_visit($values)
{
	extract(array_merge(array(
		'request_id' => 20,
		'request_status' => 'Accepted',
		'is_visit' => false,
		'visit_location_available' => false,
		'visit_label' => '',
		'visit_updated' => '',
		'visit_timeline' => array(),
	), $values));
	ob_start();
	include dirname(__DIR__, 2) . '/application/modules/home/views/partials/warga_visit_progress_v.php';
	return trim(ob_get_clean());
}

function render_nakes_next_action($request, $user_id)
{
	$context = new Phase7ViewContext($user_id);
	$render = function ($request) {
		$nakes_primary_active = $request;
		$request_staff_assignment_map = array();
		$staff_assignment_ready = false;
		$puskesmas_staff_options = array();
		$request_event_map = array();
		$care_team_workflow_enabled = true;
		$can_coordinate_staff = false;
		ob_start();
		include dirname(__DIR__, 2) . '/application/modules/home_nakes/views/partials/nakes_active_task_card_v.php';
		return ob_get_clean();
	};
	return $render->call($context, $request);
}

$schema = new Phase7SchemaDb();
$config = new Phase7Config();
$home = new Phase7HomeModel($schema, $config);
phase7_render_expect($home->careTeamDisplayReady() === false, 'feature_off_does_not_activate_installed_canonical_schema');
$config->enabled = true;
phase7_render_expect($home->careTeamDisplayReady() === true, 'feature_on_with_schema_uses_canonical_identity');
$schema->schema_ready = false;
phase7_render_expect($home->careTeamDisplayReady() === false, 'feature_on_without_schema_fails_closed');
phase7_render_expect($home->warga_top_status_label('Accepted', 'arrived', 'non_visit') === 'Konsultasi tanpa kunjungan', 'non_visit_ignores_stale_visit_status');
phase7_render_expect($home->warga_top_status_label('Accepted', 'arrived', 'visit') === 'Sudah tiba', 'active_visit_uses_visit_status');
phase7_render_expect($home->warga_top_status_label('Accepted', 'arrived', null) === 'Sedang ditangani', 'legacy_null_mode_degrades_without_visit_claim');

$pending = render_warga_visit(array('request_status' => 'Pending'));
$non_visit = render_warga_visit(array('is_visit' => false, 'visit_label' => 'Petugas sudah tiba', 'visit_location_available' => true));
$active_visit = render_warga_visit(array('is_visit' => true, 'visit_label' => 'Petugas sedang menuju lokasi', 'visit_location_available' => true, 'visit_timeline' => array(array('label' => 'Dalam perjalanan'))));
$completed_visit = render_warga_visit(array('is_visit' => true, 'visit_label' => 'Kunjungan selesai', 'visit_location_available' => false));
$cancelled = render_warga_visit(array('request_status' => 'Cancelled', 'is_visit' => true, 'visit_location_available' => true));
phase7_render_expect($pending === '' && $cancelled === '', 'pending_and_cancelled_requests_render_no_active_visit_ui');
phase7_render_expect(strpos($non_visit, 'Buka chat') !== false && strpos($non_visit, 'doclinc-visit-summary') === false && strpos($non_visit, 'visit-location-toggle') === false, 'accepted_non_visit_keeps_chat_without_visit_ui');
phase7_render_expect(strpos($active_visit, 'Petugas sedang menuju lokasi') !== false && strpos($active_visit, 'visit-location-toggle') !== false, 'accepted_active_visit_renders_progress_and_location');
phase7_render_expect(strpos($completed_visit, 'Kunjungan selesai') !== false && strpos($completed_visit, 'visit-location-toggle') === false, 'completed_visit_has_history_without_active_location_control');

$base = array(
	'request_id' => 30,
	'nama' => 'Pasien Uji',
	'consultation_mode' => '',
	'responsible_doctor_user_id' => 101,
	'visit_performer_user_id' => 102,
	'visit_status' => 'not_started',
	'responsible_doctor_name' => 'Dokter Uji',
);
$doctor = render_nakes_next_action((object) $base, 101);
$performer_request = $base;
$performer_request['consultation_mode'] = 'visit';
$performer_request['visit_status'] = 'en_route';
$performer = render_nakes_next_action((object) $performer_request, 102);
$unassigned = render_nakes_next_action((object) $performer_request, 999);
$completed_request = $performer_request;
$completed_request['visit_status'] = 'completed';
$completed = render_nakes_next_action((object) $completed_request, 102);
phase7_render_expect(strpos($doctor, 'Pilih jenis layanan') !== false && strpos($doctor, 'data-primary-next-action="responsible_doctor"') !== false, 'responsible_doctor_gets_valid_primary_next_action');
phase7_render_expect(strpos($performer, 'Tiba di lokasi') !== false && strpos($performer, 'data-primary-next-action="visit_performer"') !== false, 'visit_performer_gets_status_specific_next_action');
phase7_render_expect(strpos($unassigned, 'data-primary-next-action=') === false && strpos($unassigned, 'choose_service_mode') === false, 'unassigned_nakes_has_no_mutation_action');
phase7_render_expect(strpos($completed, 'Kunjungan selesai') !== false && strpos($completed, 'data-visit-status=') === false, 'completed_visit_has_no_stale_status_mutation');

echo "PHASE7_RENDER_PASS={$passed}\n";
echo "PHASE7_RENDER_FAIL={$failed}\n";
exit($failed === 0 ? 0 : 1);
