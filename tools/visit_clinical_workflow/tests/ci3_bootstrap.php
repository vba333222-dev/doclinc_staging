<?php
defined('BASEPATH') or define('BASEPATH', dirname(__DIR__, 3) . '/system/');
defined('APPPATH') or define('APPPATH', dirname(__DIR__, 3) . '/application/');
defined('FCPATH') or define('FCPATH', dirname(__DIR__, 3) . '/');
defined('ENVIRONMENT') or define('ENVIRONMENT', 'testing');
$host = getenv('VCW_DB_HOST') ?: '127.0.0.1'; $name = getenv('VCW_DB_NAME') ?: 'doclinc_visit_test';
if (!in_array($host, array('127.0.0.1','localhost'), true)) throw new RuntimeException('non_loopback_db');
if (preg_match('/staging|production|prod/i', $name)) throw new RuntimeException('non_disposable_db');
require_once BASEPATH . 'core/Common.php'; require_once BASEPATH . 'database/DB.php';
$db = DB(array('dsn'=>'','hostname'=>$host,'username'=>getenv('VCW_DB_USER') ?: 'doclinc_test','password'=>getenv('VCW_DB_PASSWORD') ?: 'doclinc_test_only','database'=>$name,'dbdriver'=>'mysqli','dbprefix'=>'','pconnect'=>false,'db_debug'=>false,'cache_on'=>false,'cachedir'=>'','char_set'=>'utf8','dbcollat'=>'utf8_general_ci','port'=>(int)(getenv('VCW_DB_PORT') ?: 33317),'stricton'=>false), true);
if (!$db) throw new RuntimeException('db_connection_failed');
class VcwConfig { private $items; public function __construct(array $items){$this->items=$items;} public function item($key){return $this->items[$key]??null;} }
class VcwLoader { private $db; public function __construct($db){$this->db=$db;} public function database($group='default',$return=false){return $this->db;} }
class VcwSession { public function userdata($key){return null;} }
class VcwApplication { public $db; public $load; public $config; public $session; public function __construct($db){$this->db=$db;$this->load=new VcwLoader($db);$this->config=new VcwConfig(array('visit_clinical_workflow_enabled'=>getenv('VCW_FEATURE_ENABLED')==='1'));$this->session=new VcwSession();} }
$GLOBALS['vcw_app'] = new VcwApplication($db);
if (!function_exists('get_instance')) { function &get_instance(){return $GLOBALS['vcw_app'];} }
require_once APPPATH . 'helpers/request_authz_helper.php'; require_once APPPATH . 'helpers/request_event_helper.php'; require_once APPPATH . 'libraries/Visit_workflow_policy.php'; require_once APPPATH . 'models/Visit_disposition_m.php'; require_once APPPATH . 'libraries/Visit_disposition_service.php';
return $GLOBALS['vcw_app'];
