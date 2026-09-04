<?php
/**
 * Focused Task 11 RED/GREEN test for the Command Center workflow read boundary.
 * The fixture intentionally keeps users.role=dokter and has no personal staff
 * link; canonical identity classification must determine access.
 */
require_once __DIR__ . '/clinical_http_acceptance_support.php';

$base = getenv('VCW_HTTP_BASE_URL') ?: '';
task11_assert($base !== '', 'VCW_HTTP_BASE_URL_required');

$factory = new Task11FixtureFactory();
$command = $factory->actor('COMMAND_CENTER');
$role = (string) task11_scalar('SELECT role FROM users WHERE userId=?', array($command['id']));
$staffLinks = (int) task11_scalar('SELECT COUNT(*) FROM puskesmas_staff WHERE user_id=?', array($command['id']));
task11_assert($role === 'dokter', 'canonical_command_center_role_preserved');
task11_assert($staffLinks === 0, 'canonical_command_center_has_no_personal_staff_link');

$requestId = $factory->request('visit');
$client = new Task11HttpClient($base);
$client->login($command['username'], $factory->password());
$response = $client->request('GET', '/puskesmas/operations/clinical-workflow/' . $requestId, array(), false);
task11_safe_response($response);
$body = task11_json($response['body']);
task11_assert($response['status'] === 200 && !empty($body['success']), 'canonical_command_center_surface_allowed status=' . $response['status'] . ' body=' . substr($response['body'], 0, 240));
task11_assert(isset($body['data']['actor_context']), 'canonical_command_center_actor_context_present');
task11_assert(($body['data']['actor_context']['type'] ?? '') === 'command_center', 'canonical_command_center_actor_context_type');

$personal = $factory->actor('RD');
$personalClient = new Task11HttpClient($base);
$personalClient->login($personal['username'], $factory->password());
$personalResponse = $personalClient->request('GET', '/puskesmas/operations/clinical-workflow/' . $requestId, array(), false);
task11_safe_response($personalResponse);
$personalBody = task11_json($personalResponse['body']);
task11_assert($personalResponse['status'] === 403 && empty($personalBody['success']), 'ordinary_personal_doctor_denied');

$spoofResponse = $personalClient->request('GET', '/puskesmas/operations/clinical-workflow/' . $requestId . '?role=command_center', array(), false);
task11_safe_response($spoofResponse);
task11_assert($spoofResponse['status'] === 403, 'query_role_spoof_denied');

$warga = $factory->actor('WARGA');
$wargaClient = new Task11HttpClient($base);
$wargaClient->login($warga['username'], $factory->password());
$wargaResponse = $wargaClient->request('GET', '/puskesmas/operations/clinical-workflow/' . $requestId, array(), false);
task11_safe_response($wargaResponse);
$wargaBody = task11_json($wargaResponse['body']);
task11_assert($wargaResponse['status'] === 403 && empty($wargaBody['success']), 'warga_denied');


$anonymous = new Task11HttpClient($base);
$anonymousResponse = $anonymous->request('GET', '/puskesmas/operations/clinical-workflow/' . $requestId, array(), false);
task11_safe_response($anonymousResponse);
task11_assert($anonymousResponse['status'] !== 200, 'unauthenticated_denied');

$mutationPaths = array(
    '/clinical/disposition' => array('request_id' => $requestId, 'decision' => 'visit'),
    '/clinical/review' => array('request_id' => $requestId, 'decision' => 'approve'),
    '/clinical/finalize' => array('request_id' => $requestId),
    '/clinical/amend' => array('request_id' => $requestId, 'record_id' => 1, 'reason' => 'Task11 test'),
    '/clinical/close' => array('request_id' => $requestId),
);
foreach ($mutationPaths as $path => $fields) {
    list($mutationResponse, $mutationBody) = task11_post($client, $path, $fields);
    task11_assert($mutationResponse['status'] !== 200 && empty($mutationBody['success']), 'command_center_mutation_allowed_' . $path);
}

echo "COMMAND_CENTER_SURFACE_AUTH_GREEN=PASS\n";
echo "ORDINARY_DOCTOR_COMMAND_CENTER_SURFACE_DENIED=PASS\n";
echo "WARGA_COMMAND_CENTER_SURFACE_DENIED=PASS\n";
echo "UNAUTHENTICATED_COMMAND_CENTER_SURFACE_DENIED=PASS\n";
echo "COMMAND_CENTER_ROLE_SPOOF_DENIED=PASS\n";
echo "COMMAND_CENTER_MUTATION_DENIALS=PASS\n";
