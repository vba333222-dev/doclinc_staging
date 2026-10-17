<?php
/**
 * Warga consultation-access gate regression test.
 *
 * The service area is an administrative boundary: the app belongs to the Cilegon
 * City government and a citizen outside the Cilegon service area must not be
 * able to start a consultation. The boundary is modelled as eight GeoJSON
 * polygons and the authoritative test is the server-side point-in-polygon check
 * in Konsultasi::save_konsultasi().
 *
 * The client used to add a second, competing gate: it compared the reverse-
 * geocoded city name against ['Cilegon', 'Kota Cilegon']. That gate decided
 * access from a display string, so a legitimate Cilegon resident was rejected
 * whenever reverse-geocoding failed ('Lokasi terdeteksi'), was still running, or
 * when location permission was denied ('Periksa izin lokasi perangkat'). It also
 * reported the wrong reason: "Di luar area layanan" instead of "enable GPS".
 *
 * The gate now checks only whether GPS produced coordinates, and leaves the area
 * decision to the server. This test locks that separation in, and asserts the
 * server-side GeoJSON authority is still in place.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }

$passed = 0;
$failed = 0;

function warga_gate_check($condition, $label)
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

$view = file_get_contents(APPPATH . 'modules/home/views/home_v.php');
$controller = file_get_contents(APPPATH . 'modules/konsultasi/controllers/Konsultasi.php');

echo "== client gate must not decide the area ==\n";

warga_gate_check(strpos($view, "['Cilegon', 'Kota Cilegon'].includes(kotaCilegon)") === false, 'client no longer compares the geocoded city name against Cilegon');
warga_gate_check(strpos($view, "getElementById('kota').textContent") === false, 'client no longer reads #kota to make an access decision');
warga_gate_check(strpos($view, 'Di luar area layanan') === false, 'client no longer shows the area message (that reason belongs to the server)');

echo "\n== client gate must require GPS coordinates ==\n";

warga_gate_check(strpos($view, "getElementById('latitude')") !== false, 'client reads the latitude field');
warga_gate_check(strpos($view, "getElementById('longitude')") !== false, 'client reads the longitude field');
warga_gate_check(strpos($view, 'Aktifkan GPS terlebih dahulu') !== false, 'client tells the citizen to enable GPS');
warga_gate_check(strpos($view, 'Number.isFinite(Number(value))') !== false, 'client validates the coordinate is numeric, not just non-empty');

echo "\n== GPS fields must be populated by a successful fix ==\n";

warga_gate_check(strpos($view, 'id="latitude"') !== false, 'latitude input exists in the view');
warga_gate_check(strpos($view, 'id="longitude"') !== false, 'longitude input exists in the view');
warga_gate_check(strpos($view, 'function setLocationFields(location)') !== false, 'setLocationFields exists');
warga_gate_check(preg_match('/latitudeEl\.value\s*=\s*location\.lat/', $view) === 1, 'setLocationFields writes latitude from the GPS fix');
warga_gate_check(preg_match('/longitudeEl\.value\s*=\s*location\.lng/', $view) === 1, 'setLocationFields writes longitude from the GPS fix');
warga_gate_check(strpos($view, 'navigator.geolocation.watchPosition') !== false, 'the view watches the GPS fix continuously, not once');


echo "\n== server keeps the GeoJSON authority ==\n";

warga_gate_check(strpos($controller, 'doclinc_find_puskesmas_by_service_area') !== false, 'controller still resolves the area from the GeoJSON polygons');
warga_gate_check(strpos($controller, 'Lokasi di luar area layanan') !== false, 'controller still rejects a point outside the service area');
warga_gate_check(strpos($controller, 'doclinc_find_nearest_puskesmas') === false, 'controller does not use the nearest fallback (it would widen the catchment)');

$helper = file_get_contents(APPPATH . 'helpers/puskesmas_routing_helper.php');
warga_gate_check(strpos($helper, 'doclinc_geojson_contains_point') !== false, 'the point-in-polygon helper is present');
warga_gate_check(strpos($helper, 'coverage file missing or unreadable') !== false, 'a broken coverage file is still logged, not skipped silently');

echo "\n== the boundary files the server depends on must exist ==\n";

$definitions = array(
	'PuskesmasCilegon.geojson', 'PuskesmasCibeber.geojson', 'PuskesmasCiwandan.geojson',
	'PuskesmasPulomerak.geojson', 'PuskesmasGerogol.geojson', 'PuskesmasCitangkil.geojson',
	'PuskesmasPurwakarta.geojson', 'PuskesmasJombang.geojson',
);
foreach ($definitions as $file) {
	$path = FCPATH . 'servicearea/' . $file;
	warga_gate_check(is_file($path) && is_readable($path), 'boundary file present: ' . $file);
}

echo "\n== GPS must stay live for the whole session, not just at page load ==\n";

// Coordinates decide BOTH the destination puskesmas (service-area polygon) and
// where the visit performer must travel. A one-shot fix taken at page load
// goes stale as soon as the patient moves, so the citizen is routed to the
// wrong facility and the nurse is sent to a point the patient has left.
warga_gate_check(strpos($view, 'watchPosition') !== false, 'position is watched continuously');
warga_gate_check(strpos($view, 'clearWatch') !== false, 'the previous watch is cleared so watches cannot stack');
warga_gate_check(preg_match('/function startLocationWatch\(/', $view) === 1, 'a single watch helper owns the subscription');
warga_gate_check(preg_match('/maximumAge:\s*0\b/', $view) === 1, 'cached positions are refused (maximumAge 0) so routing uses the current point');
warga_gate_check(strpos($view, 'navigator.geolocation.getCurrentPosition') === false, 'the one-shot acquisition is gone');

echo "\n== a GPS failure must be visible and must not leave stale coordinates ==\n";

// The old handler switched on error.POSITION_UNAVAILABLE / error.TIMEOUT, which
// are undefined on a PositionError instance - every case missed, so the handler
// was a silent no-op and a patient whose GPS was switched off mid-session kept
// the last known coordinates and passed the gate with a stale position.
warga_gate_check(strpos($view, 'error.POSITION_UNAVAILABLE') === false, 'the never-matching property switch is gone');
warga_gate_check(strpos($view, 'error.TIMEOUT') === false, 'the never-matching timeout case is gone');
warga_gate_check(preg_match('/GEO_PERMISSION_DENIED\s*=\s*1/', $view) === 1, 'numeric geolocation error codes are used');
warga_gate_check(preg_match('/GEO_POSITION_UNAVAILABLE\s*=\s*2/', $view) === 1, 'position-unavailable is handled numerically');
warga_gate_check(strpos($view, 'function clearLocationFields()') !== false, 'a helper clears the coordinate fields');
warga_gate_check(strpos($view, "'Aktifkan GPS untuk melanjutkan.'") !== false, 'the citizen is told GPS must be enabled');
warga_gate_check(preg_match('/function showError\([\s\S]{0,900}clearLocationFields\(\)/', $view) === 1, 'revoked permission clears the stale coordinates');

echo "\n== watching must not spam the map or the geocoder ==\n";

warga_gate_check(strpos($view, 'locationCentered') !== false, 'the map is centred only on the first fix');
warga_gate_check(strpos($view, 'function locationMovedEnough(') !== false, 'reverse geocoding is throttled by distance');
warga_gate_check(preg_match('/locationMovedEnough\(\$newLocation|locationMovedEnough\(newLocation/', $view) === 1, 'the throttle is applied before resolving the address');

echo "\nWARGA_CONSULTATION_GATE=PASSED=$passed, FAILED=$failed\n";
exit($failed === 0 ? 0 : 1);
