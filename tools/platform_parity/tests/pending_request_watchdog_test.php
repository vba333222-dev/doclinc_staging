<?php
/**
 * Permintaan yang tidak pernah diterima puskesmas harus terlihat, bukan hilang.
 *
 * Sebelum ini, permintaan `Pending` yang tidak direspons puskesmas tergantung
 * SELAMANYA: tidak ada batas waktu, tidak ada pengingat, tidak ada layar yang
 * menunjukkan ada permintaan menumpuk. Warga menunggu tanpa kepastian dan
 * dinas kesehatan tidak punya cara mengetahuinya. Itu kegagalan senyap yang
 * mengenai warga langsung.
 *
 * Pengawas ini sengaja TIDAK memindahkan permintaan ke puskesmas lain: puskesmas
 * tujuan ditentukan oleh area layanan lokasi warga, dan memindahkannya akan
 * melanggar batas catchment yang memang disengaja. Yang dilakukan adalah
 * menandai, mengingatkan, dan menaikkan ke dinas kesehatan - meniru alur nyata
 * bahwa puskesmas bertanggung jawab kepada dinas kesehatan.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

$source = file_get_contents(APPPATH . 'libraries/Pending_request_watchdog.php');
$passed = 0;
$failed = 0;

function watchdog_check($condition, $label)
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

echo "== the watchdog exists and is self-contained ==\n";

watchdog_check(strpos($source, 'class Pending_request_watchdog') !== false, 'the watchdog class is present');
watchdog_check(strpos($source, 'public function sweep(') !== false, 'it exposes a sweep entry point');
watchdog_check(strpos($source, 'public function waitingMinutes(') !== false, 'it can report how long a request has waited');
watchdog_check(strpos($source, 'log_message(') !== false, 'failures are logged, not swallowed');

echo "\n== it must NOT move the request to another puskesmas ==\n";

// Reassigning would break the deliberate service-area catchment policy.
watchdog_check(stripos($source, 'update(') === false, 'the watchdog never updates rows');
watchdog_check(stripos($source, 'assigned_puskesmas_code =') === false, 'it never rewrites the assigned puskesmas');
watchdog_check(stripos($source, 'UPDATE requests') === false, 'it never rewrites the request row');

echo "\n== escalation must be recorded and idempotent ==\n";

watchdog_check(strpos($source, "doclinc_append_request_event") !== false, 'escalation is recorded as a request event');
watchdog_check(strpos($source, "'request.accept_overdue'") !== false, 'the overdue event has a stable name');
watchdog_check(strpos($source, "'request.accept_reminder'") !== false, 'the reminder event has a stable name');
watchdog_check(strpos($source, 'private function hasEvent(') !== false, 'prior escalation is checked before repeating');
watchdog_check(preg_match('/hasEvent\(\$request_id, \'accept-overdue\'\)/', $source) === 1,
	'overdue escalation is guarded by the recorded event');
watchdog_check(preg_match('/hasEvent\(\$request_id, \'accept-reminder\'\)/', $source) === 1,
	'the reminder is guarded too, so it does not repeat every sweep');
watchdog_check(strpos($source, 'acquireSweepLock') === false,
	'the no-op sweep lock was removed rather than left in place');

echo "\n== thresholds must be configurable, not hardcoded ==\n";

watchdog_check(strpos($source, 'request_accept_reminder_minutes') !== false, 'the reminder threshold is configurable');
watchdog_check(strpos($source, 'request_accept_overdue_minutes') !== false, 'the overdue threshold is configurable');
watchdog_check(preg_match('/REMINDER_DEFAULT_MINUTES\s*=\s*10/', $source) === 1, 'the reminder defaults to 10 minutes');
watchdog_check(preg_match('/OVERDUE_DEFAULT_MINUTES\s*=\s*20/', $source) === 1, 'the overdue default is later than the reminder');
watchdog_check(strpos($source, 'REMINDER_DEFAULT_MINUTES') !== false && strpos($source, 'OVERDUE_DEFAULT_MINUTES') !== false,
	'both defaults are used as fallbacks');

echo "\n== it must only look at requests still waiting for acceptance ==\n";

watchdog_check(preg_match("/where\('request_status', 'Pending'\)/", $source) === 1,
	'only Pending requests are considered');
watchdog_check(strpos($source, 'waiting_minutes') !== false, 'waiting time is computed from the database clock');
watchdog_check(strpos($source, 'TIMESTAMPDIFF(MINUTE') !== false, 'waiting time uses the same clock as the records');

echo "\n== it must be safe when the schema is not ready ==\n";

watchdog_check(preg_match('/if \(!\$this->db->table_exists\(\'requests\'\)\)/', $source) === 1,
	'the sweep exits early when the requests table is missing');
watchdog_check(strpos($source, "table_exists('request_events')") !== false,
	'event lookups are guarded by a table check');
watchdog_check(strpos($source, 'catch (Throwable') !== false, 'a failure during sweep does not break the page');

echo "\n== it must actually be wired in, not just exist ==\n";

// A watchdog nobody calls protects nobody. It has to run where a puskesmas
// actually looks at its queue.
$controller = file_get_contents(APPPATH . 'modules/home_nakes/controllers/Home_nakes.php');
watchdog_check(strpos($controller, "load->library('Pending_request_watchdog')") !== false,
	'the puskesmas dashboard loads the watchdog');
watchdog_check(strpos($controller, 'pending_request_watchdog->sweep(') !== false,
	'the puskesmas dashboard runs a sweep');
watchdog_check(strpos($controller, 'pending_request_watchdog->sweep($puskesmas_code)') !== false,
	'the sweep is scoped to the facility being viewed');

// The sweep must happen before the queue is loaded, otherwise the request it
// just escalated is not the one rendered.
$sweepPos = strpos($controller, 'pending_request_watchdog->sweep(');
$listPos = strpos($controller, 'request_keluhan_command_center(');
watchdog_check($sweepPos !== false && $listPos !== false && $sweepPos < $listPos,
	'the sweep runs before the request list is loaded');

echo "\nPENDING_REQUEST_WATCHDOG=PASSED=$passed, FAILED=$failed\n";
exit($failed === 0 ? 0 : 1);
