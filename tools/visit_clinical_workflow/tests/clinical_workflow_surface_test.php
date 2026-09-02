<?php
defined('BASEPATH') or define('BASEPATH', __DIR__);
defined('APPPATH') or define('APPPATH', dirname(__DIR__, 3) . '/application/');
require_once __DIR__ . '/assert.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Clinical_workflow_surface.php';
if (!function_exists('get_instance')) {
    class SurfaceTestCiConfig { public function item($key) { return false; } }
    class SurfaceTestCi { public $config; public function __construct() { $this->config = new SurfaceTestCiConfig(); } }
    function get_instance() { static $ci; return $ci ?: ($ci = new SurfaceTestCi()); }
}
class SurfaceFakeQuery { private $row; public function __construct($row){$this->row=$row;} public function row_array(){return $this->row;} }
class SurfaceFakeDb { public function query($sql,$params){ if(strpos($sql,'SELECT user_id FROM requests')!==false)return new SurfaceFakeQuery(array('user_id'=>77)); if(strpos($sql,'FROM requests')!==false)return new SurfaceFakeQuery(array('request_id'=>9,'request_status'=>'Accepted','consultation_mode'=>'visit','visit_status'=>'completed','assigned_puskesmas_code'=>'PKM','user_id'=>77)); if(strpos($sql,'SELECT role FROM users')!==false)return new SurfaceFakeQuery(array('role'=>'warga')); if(strpos($sql,'FROM clinical_reviews')!==false)return new SurfaceFakeQuery(array('review_id'=>1,'visit_result_id'=>2,'decision'=>'correction_required','correction_reason'=>'internal')); return new SurfaceFakeQuery(null); } }
class SurfaceFakeResolver { public function resolve($id){return array('request_id'=>$id,'state'=>'WAITING_CLINICAL_FINALIZATION','managed'=>true,'enrolled'=>true);} }
class SurfaceFakePresenter { public function present(array $state,$actor){return array('state'=>$state['state'],'actor'=>$actor,'correction_reason'=>null);} }
class SurfaceFakePolicy { public function canView($requestId,$actorId){return $requestId===9 && $actorId===77;} }
$surface=new Clinical_workflow_surface(new SurfaceFakeDb(),new SurfaceFakeResolver(),new SurfaceFakePresenter(),new SurfaceFakePolicy()); $result=$surface->forRequest(9,77);
vcw_assert_true($result['status']==='success','surface success'); vcw_assert_true($result['data']['workflow_state']['state']==='WAITING_CLINICAL_FINALIZATION','resolver delegation'); vcw_assert_true($result['data']['presentation']['actor']==='warga','presenter delegation'); vcw_assert_true(is_array($result['data']['available_actions']),'actions projection'); vcw_assert_true($result['data']['review_summary']['correction_reason']===null,'Warga correction detail masked');
$denied=(new Clinical_workflow_surface(new SurfaceFakeDb(),new SurfaceFakeResolver(),new SurfaceFakePresenter(),new class { public function canView($requestId,$actorId){return false;} }))->forRequest(9,88); vcw_assert_true($denied['safe_error_code']==='ACCESS_DENIED','unauthorized actor denied');
echo "CLINICAL_WORKFLOW_SURFACE=PASS\n";
