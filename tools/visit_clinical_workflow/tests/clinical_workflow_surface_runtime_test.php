<?php
define('BASEPATH', __DIR__);
define('APPPATH', dirname(__DIR__, 3) . '/application/');
require_once __DIR__ . '/assert.php';

class ClinicalSurfaceRuntimeConfig { public function item($key) { return $key === 'visit_clinical_workflow_enabled'; } }
class ClinicalSurfaceRuntimeDb { public function query($sql, $params = array()) { return null; } }
class ClinicalSurfaceRuntimeCi { public $db; public $config; public function __construct() { $this->db = new ClinicalSurfaceRuntimeDb(); $this->config = new ClinicalSurfaceRuntimeConfig(); } }
function get_instance() { static $ci; if (!$ci) { $ci = new ClinicalSurfaceRuntimeCi(); } return $ci; }
require_once APPPATH . 'libraries/Clinical_workflow_surface.php';

$surface = new Clinical_workflow_surface();
$reflection = new ReflectionClass($surface);
foreach (array('db', 'policy', 'resolver', 'presenter') as $property) {
    $field = $reflection->getProperty($property);
    $field->setAccessible(true);
    vcw_assert_true($field->getValue($surface) !== null, 'CI3 construction supplies ' . $property);
}
echo "CLINICAL_WORKFLOW_SURFACE_RUNTIME=PASS\n";
