<?php
require_once __DIR__ . '/assert.php';
$controller = file_get_contents(dirname(__DIR__, 3) . '/application/modules/home_nakes/controllers/Home_nakes.php');
$routes = file_get_contents(dirname(__DIR__, 3) . '/application/config/routes.php');
$closure = file_get_contents(dirname(__DIR__, 3) . '/application/libraries/Clinical_closure_service.php');
$config = file_get_contents(dirname(__DIR__, 3) . '/application/config/config.php');
foreach (array('clinical_workflow','clinical_review','clinical_finalize','clinical_amend','clinical_close') as $method) vcw_assert_true(strpos($controller, 'function ' . $method) !== false, 'controller method ' . $method);
foreach (array("'clinical/review'] = 'home_nakes/clinical_review'","'clinical/finalize'] = 'home_nakes/clinical_finalize'","'clinical/amend'] = 'home_nakes/clinical_amend'","'clinical/close'] = 'home_nakes/clinical_close'") as $route) vcw_assert_true(strpos($routes,$route)!==false,'route '.$route);
vcw_assert_true(strpos($controller,'trans_begin')===false,'controller does not own transaction'); vcw_assert_true(strpos($controller,'$this->session->userdata(\'id\')')!==false,'actor is session-derived');
vcw_assert_true(strpos($controller,'closeClinical(')!==false,'closure mode delegated to service'); $closePos=strpos($controller,'public function clinical_close'); $closeBlock=$closePos===false?'':substr($controller,$closePos); vcw_assert_true(strpos($closeBlock,'->db->')===false,'closure adapter has no direct database access'); vcw_assert_true(strpos($closure,'function closeClinical(')!==false,'service owns mode resolution'); vcw_assert_true(strpos($config,"csrf_protection'] = TRUE")!==false,'global CSRF protection enabled'); echo "CLINICAL_HTTP_SURFACE=PASS\n";
