<?php
/**
 * Puskesmas may only assign a doctor who is currently on duty.
 *
 * Product rule: "puskesmas cukup melakukan assignment/penunjukan hanya kepada
 * dokter yang terdaftar di puskesmas mereka yang statusnya online."
 *
 * The presence machinery already existed (nakes_presence table, heartbeat,
 * badge UI, presence joined into the staff list) but nothing enforced it, so
 * the status was displayed without meaning. An offline doctor could be assigned
 * and the citizen's request then sat with nobody working it - and with no
 * acceptance deadline it sat there forever.
 *
 * The check must be CONDITIONAL. Presence data only exists when the presence
 * feature is enabled; enforcing "must be online" while the feature is off would
 * block every assignment and break the whole care-team flow.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

$source = file_get_contents(APPPATH . 'libraries/Care_team_service.php');
$passed = 0;
$failed = 0;

function duty_check($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "  ok   - " . $label . "\n";
		return true;
	}
	$failed++;
	echo "  FAIL - " . $label . "\n";
	return false;
}

/** Returns the body of a method, from its signature to the next top-level brace close. */
function duty_method_body($source, $signature)
{
	$start = strpos($source, $signature);
	if ($start === false) { return null; }
	$depth = 0;
	$cursor = $start;
	$opened = false;
	for (; $cursor < strlen($source); $cursor++) {
		if ($source[$cursor] === '{') { $depth++; $opened = true; }
		elseif ($source[$cursor] === '}') {
			$depth--;
			if ($opened && $depth === 0) { return substr($source, $start, $cursor - $start + 1); }
		}
	}
	return null;
}

echo "== presence must NOT be part of care-team authorization ==\n";

// The project deliberately keeps presence out of authorization: a momentary
// network blip must not strip a legitimate doctor of authority. Presence is
// selection information, offered when the puskesmas picks a doctor - not a gate
// on the action itself. An existing suite locks this invariant
// (presence_is_not_care_team_authorization); this test documents the reasoning
// next to the requirement that motivated the change.
$assign = duty_method_body($source, 'public function assignResponsibleDoctor(');
duty_check($assign !== null, 'assignResponsibleDoctor is present');
duty_check($assign !== null && strpos($assign, 'nakes_presence') === false,
	'authorization does not consult the presence table');
duty_check($assign !== null && strpos($assign, 'responsibleDoctorAvailable(') === false,
	'no on-duty gate was added to the authorization path');
duty_check($assign !== null && strpos($assign, 'responsibleDoctorEligible(') !== false,
	'the stable eligibility check is still the gate');
duty_check(strpos($source, 'nakes_presence') === false,
	'the service never references presence at all');

echo "\n== the reason must be recorded so nobody re-adds the gate ==\n";

duty_check(strpos($assign, 'status online sengaja TIDAK diperiksa') !== false,
	'the authorization path explains why presence is excluded');
duty_check(strpos($assign, 'get_active_staff_options_by_code') !== false,
	'it points to where on-duty filtering actually belongs');

echo "\n== on-duty filtering belongs to candidate selection ==\n";

$model = file_get_contents(APPPATH . 'modules/home_nakes/models/Home_nakes_m.php');
duty_check($model !== false && strpos($model, 'nakes_presence') !== false,
	'the staff option query is where presence is joined');
duty_check($model !== false && strpos($model, 'last_seen_at') !== false,
	'candidate staleness is derived from last_seen_at');
duty_check($model !== false && strpos($model, 'AS is_online') !== false,
	'the query exposes an is_online flag');

// Availability is a separate field from eligibility, so authorization keeps
// using the stable one while the offered list can hide absent doctors.
duty_check($model !== false && strpos($model, 'responsible_doctor_available') !== false,
	'the model exposes responsible_doctor_available');
duty_check($model !== false
	&& preg_match('/responsible_doctor_available\s*=\s*\$staff->responsible_doctor_eligible/', $model) === 1,
	'availability builds on eligibility rather than replacing it');
duty_check($model !== false && preg_match('/!\$presence_ready\s*\|\|/', $model) === 1,
	'availability is skipped entirely when presence data is unavailable');

echo "\n== the offered list must filter on availability, not raw eligibility ==\n";

$taskCard = file_get_contents(APPPATH . 'modules/home_nakes/views/partials/nakes_active_task_card_v.php');
$history = file_get_contents(APPPATH . 'modules/home_nakes/views/partials/nakes_history_v.php');
duty_check($taskCard !== false && strpos($taskCard, 'empty($staff_option->responsible_doctor_available)') !== false,
	'the active task card offers only available doctors');
duty_check($history !== false && strpos($history, 'empty($staff_option->responsible_doctor_available)') !== false,
	'the history card offers only available doctors');
duty_check($taskCard !== false && strpos($taskCard, 'empty($staff_option->responsible_doctor_eligible)') === false,
	'the active task card no longer filters on raw eligibility');
duty_check($history !== false && strpos($history, 'empty($staff_option->responsible_doctor_eligible)') === false,
	'the history card no longer filters on raw eligibility');

// The performer list keeps its own predicate; it must not be swapped by mistake.
duty_check($taskCard !== false && strpos($taskCard, 'empty($staff_option->visit_performer_eligible)') !== false,
	'the visit performer list still filters on performer eligibility');
duty_check($history !== false && strpos($history, 'empty($staff_option->visit_performer_eligible)') !== false,
	'the visit performer list in history is unchanged');

echo "\nPUSKESMAS_ON_DUTY_ASSIGNMENT=PASSED=$passed, FAILED=$failed\n";
exit($failed === 0 ? 0 : 1);
