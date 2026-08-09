<?php
define('BASEPATH', __DIR__ . '/');

$root = dirname(__DIR__, 3);
require_once $root . '/application/libraries/Reverse_geocoding_service.php';

$passed = 0;
$failed = 0;
function location_server_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$label}\n";
		return;
	}
	$failed++;
	echo "FAIL {$label}\n";
}

$cache = sys_get_temp_dir() . '/doclinc-location-unit-' . bin2hex(random_bytes(5));
$requests = array();
$payload = json_encode(array(
	'display_name' => 'Jalan Sultan Ageng Tirtayasa No. 1, Cilegon, Banten, Indonesia',
	'address' => array('suburb' => 'Jombang Wetan', 'city' => 'Cilegon'),
));
$service = new Reverse_geocoding_service(array(
	'cache_path' => $cache,
	'endpoint' => 'https://nominatim.openstreetmap.org/reverse',
	'allowed_hosts' => array('nominatim.openstreetmap.org'),
	'user_agent' => 'Doclinc-Test/1.0',
	'http_get' => function ($url, $user_agent) use (&$requests, $payload) {
		$requests[] = array($url, $user_agent);
		return $payload;
	},
	'now' => function () { return 1000; },
	'sleep' => function () {},
));

$first = $service->resolve(-6.0021, 106.0534);
$second = $service->resolve(-6.002101, 106.053401);
location_server_expect($first['available'] === true && strpos($first['address'], 'Jalan Sultan') === 0, 'precise_address_returned');
location_server_expect($first['locality'] === 'Jombang Wetan' && $first['provider'] === 'openstreetmap', 'canonical_locality_and_provider');
location_server_expect($first['attribution'] === '© OpenStreetMap contributors', 'required_attribution_returned');
location_server_expect(count($requests) === 1 && $second === $first, 'coordinate_cell_cache_prevents_duplicate_provider_request');
location_server_expect(strpos($requests[0][0], 'format=jsonv2') !== false && strpos($requests[0][0], 'addressdetails=1') !== false, 'provider_request_is_bounded');
location_server_expect($requests[0][1] === 'Doclinc-Test/1.0', 'provider_user_agent_is_explicit');
location_server_expect((fileperms($cache) & 0777) === 0700, 'cache_directory_is_private');

$invalid_calls = 0;
$invalid = new Reverse_geocoding_service(array(
	'cache_path' => $cache . '-invalid',
	'endpoint' => 'https://evil.example/reverse',
	'allowed_hosts' => array('nominatim.openstreetmap.org'),
	'user_agent' => 'Doclinc-Test/1.0',
	'http_get' => function () use (&$invalid_calls) { $invalid_calls++; return '{}'; },
));
location_server_expect($invalid->resolve(-6, 106)['available'] === false && $invalid_calls === 0, 'non_allowlisted_provider_rejected_before_network');
location_server_expect($service->resolve(91, 106)['available'] === false && count($requests) === 1, 'invalid_coordinate_rejected_before_network');

foreach (glob($cache . '/*') ?: array() as $file) @unlink($file);
@rmdir($cache);

echo "LOCATION_SERVER_UNIT_PASSED={$passed}\n";
echo "LOCATION_SERVER_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
