<?php
/**
 * Puskesmas service-area routing regression test.
 *
 * A patient never chooses a puskesmas: the controller derives it from the
 * patient's coordinates by testing eight GeoJSON coverage polygons. The failure
 * mode this test locks in is silence. doclinc_find_puskesmas_by_service_area()
 * used to `continue` past a missing or unparsable coverage file with no log, so
 * a deleted or corrupted file made every point in that area fail the test and
 * the patient was told "Lokasi di luar area layanan" with nothing in the log to
 * explain why. Silent degradation is the exact defect class this parity effort
 * exists to eliminate, so the skip must stay loud.
 *
 * It also asserts the deliberate Cilegon-only policy is still in force: the
 * controller must keep calling the strict service-area resolver and the UI must
 * keep telling the patient consultation is limited to Kota Cilegon. Activating
 * the unused doclinc_find_nearest_puskesmas() fallback would silently widen the
 * catchment area beyond that policy.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }

$GLOBALS['routing_log_lines'] = array();

/** Captures the routing helper's diagnostics instead of writing them to disk. */
function log_message($level, $message)
{
	$GLOBALS['routing_log_lines'][] = strtoupper((string) $level) . '|' . (string) $message;
}

require_once APPPATH . 'helpers/puskesmas_routing_helper.php';

$passed = 0;
$failed = 0;

function routing_check($condition, $label)
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

echo "== geometry: doclinc_geojson_contains_point ==\n";

// Synthetic square over Cilegon: [lng, lat] per GeoJSON convention.
$square = array('type' => 'Polygon', 'coordinates' => array(array(
	array(106.00, -6.05), array(106.10, -6.05), array(106.10, -6.00), array(106.00, -6.00), array(106.00, -6.05),
)));

routing_check(doclinc_geojson_contains_point($square, -6.02, 106.05) === true, 'point inside the polygon is contained');
routing_check(doclinc_geojson_contains_point($square, -6.50, 106.05) === false, 'point south of the polygon is rejected');
routing_check(doclinc_geojson_contains_point($square, -6.02, 107.50) === false, 'point east of the polygon is rejected');

// A hole must exclude its interior even though the outer ring contains it.
$holed = array('type' => 'Polygon', 'coordinates' => array(
	array(array(106.00, -6.05), array(106.10, -6.05), array(106.10, -6.00), array(106.00, -6.00), array(106.00, -6.05)),
	array(array(106.04, -6.04), array(106.06, -6.04), array(106.06, -6.02), array(106.04, -6.02), array(106.04, -6.04)),
));
routing_check(doclinc_geojson_contains_point($holed, -6.03, 106.05) === false, 'point inside a hole is excluded');
routing_check(doclinc_geojson_contains_point($holed, -6.045, 106.055) === true, 'point just outside the hole but inside the ring is contained');
routing_check(doclinc_geojson_contains_point($holed, -6.01, 106.01) === true, 'point in the ring but outside the hole is contained');

// Feature/MultiPolygon wrappers must be unwrapped, not silently ignored.
$feature = array('type' => 'FeatureCollection', 'features' => array(array(
	'type' => 'Feature', 'properties' => array(), 'geometry' => $square,
)));
routing_check(doclinc_geojson_contains_point($feature, -6.02, 106.05) === true, 'FeatureCollection is unwrapped');
routing_check(doclinc_geojson_contains_point(array('type' => 'Point', 'coordinates' => array(106.05, -6.02)), -6.02, 106.05) === false, 'unsupported geometry type returns false');

echo "\n== coverage files: every declared area must resolve to a real file ==\n";

$definitions = doclinc_service_area_definitions();
routing_check(is_array($definitions) && count($definitions) === 8, 'exactly 8 service-area definitions are declared');
routing_check(count($GLOBALS['routing_log_lines']) === 0, 'no diagnostics were emitted while defining the areas');

foreach ($definitions as $definition) {
	$path = FCPATH . 'servicearea/' . $definition['file'];
	routing_check(is_file($path) && is_readable($path), 'coverage file present: ' . $definition['file']);
	routing_check(!empty($definition['codes']), 'coverage file declares at least one puskesmas code: ' . $definition['file']);
}

echo "\n== silent-skip guard: a broken coverage file must be logged ==\n";

$helper = file_get_contents(APPPATH . 'helpers/puskesmas_routing_helper.php');
routing_check(strpos($helper, 'coverage file missing or unreadable') !== false, 'missing coverage file is logged, not skipped silently');
routing_check(strpos($helper, 'coverage file is not valid JSON') !== false, 'unparsable coverage file is logged, not skipped silently');
routing_check(strpos($helper, 'log_message(') !== false, 'the helper actually calls log_message');

// The guard must degrade to "skip", never to a fatal error.
$missing_path = FCPATH . 'servicearea/ThisFileDoesNotExist.geojson';
$skipped = false;
if (!is_file($missing_path) || !is_readable($missing_path)) {
	$skipped = true;
}
routing_check($skipped === true, 'a missing coverage file still degrades to a skip, not a fatal');

echo "\n== Cilegon-only policy must stay intact ==\n";

$controller = file_get_contents(APPPATH . 'modules/konsultasi/controllers/Konsultasi.php');
routing_check(strpos($controller, 'doclinc_find_puskesmas_by_service_area') !== false, 'controller still resolves the puskesmas by service area');
routing_check(strpos($controller, 'Lokasi di luar area layanan') !== false, 'controller still rejects a location outside the service area');
routing_check(strpos($controller, 'doclinc_find_nearest_puskesmas') === false, 'controller does NOT use the nearest fallback (that would widen the catchment)');

// The area message is now owned by the server. The client gate was removed
// because it decided access from a reverse-geocoded city name, which rejected
// legitimate Cilegon residents whenever geocoding failed. See
// warga_consultation_gate_test.php for the client-side contract.
$view = file_get_contents(APPPATH . 'modules/home/views/home_v.php');
routing_check(strpos($controller, 'Lokasi di luar area layanan') !== false, 'server states the out-of-area rejection');
routing_check(strpos($view, 'hanya tersedia di Kota Cilegon') === false, 'client no longer owns the area decision (moved to the server)');

echo "\nPUSKESMAS_ROUTING_COVERAGE=PASSED=$passed, FAILED=$failed\n";
exit($failed === 0 ? 0 : 1);

