<?php
define('BASEPATH', __DIR__ . '/');

$passed = 0;
$failed = 0;
function classification_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) { $passed++; echo "PASS {$label}\n"; return; }
	$failed++; echo "FAIL {$label}\n";
}

class ClassificationResult
{
	private $rows;
	public function __construct(array $rows) { $this->rows = $rows; }
	public function row() { return $this->rows ? $this->rows[0] : null; }
	public function result() { return $this->rows; }
}

class ClassificationDb
{
	public $db_debug = true;
	private $filters = array();
	private $limit_value = null;
	private $order_field = '';
	public $rows;
	private $schemas;

	public function __construct()
	{
		$this->rows = array(
			'users' => array(
				(object) array('userId' => 1, 'role' => 'dokter', 'status' => 'aktif', 'remark' => 'PKM01'),
				(object) array('userId' => 2, 'role' => 'dokter', 'status' => 'aktif', 'remark' => 'PKM01'),
				(object) array('userId' => 3, 'role' => 'dokter', 'status' => 'aktif', 'remark' => 'PKM01'),
			),
			'm_puskesmas' => array((object) array('kode_pkm' => 'PKM01', 'status' => 'aktif')),
			'puskesmas_staff' => array((object) array('staff_id' => 10, 'user_id' => 2, 'kode_pkm' => 'PKM01', 'status' => 'aktif')),
		);
		$this->schemas = array(
			'users' => array('userId', 'role', 'status', 'remark'),
			'm_puskesmas' => array('kode_pkm', 'nama_puskesmas', 'status'),
			'puskesmas_staff' => array('staff_id', 'user_id', 'kode_pkm', 'status'),
		);
	}

	public function table_exists($table) { return isset($this->schemas[$table]); }
	public function field_exists($field, $table) { return isset($this->schemas[$table]) && in_array($field, $this->schemas[$table], true); }
	public function select($fields, $escape = null) { return $this; }
	public function where($field, $value = null, $escape = null) { $this->filters[] = array($field, $value); return $this; }
	public function order_by($field, $direction) { $this->order_field = $field; return $this; }
	public function limit($limit) { $this->limit_value = (int) $limit; return $this; }
	public function escape($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }

	public function get($table)
	{
		$rows = $this->rows[$table] ?? array();
		foreach ($this->filters as $filter) {
			list($field, $value) = $filter;
			if (strpos($field, 'TRIM(remark) = ') === 0) {
				$value = trim(substr($field, strlen('TRIM(remark) = ')), "'");
				$field = 'remark';
			}
			$rows = array_values(array_filter($rows, function ($row) use ($field, $value) {
				return isset($row->{$field}) && (string) $row->{$field} === (string) $value;
			}));
		}
		if ($this->order_field !== '') {
			$field = $this->order_field;
			usort($rows, function ($left, $right) use ($field) { return ((int) $left->{$field}) <=> ((int) $right->{$field}); });
		}
		if ($this->limit_value !== null) {
			$rows = array_slice($rows, 0, $this->limit_value);
		}
		$this->filters = array();
		$this->limit_value = null;
		$this->order_field = '';
		return new ClassificationResult($rows);
	}
}

class ClassificationLoader
{
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function database($group = 'default', $return = true) { return $this->db; }
}

$classification_ci = (object) array();
$classification_ci->load = new ClassificationLoader(new ClassificationDb());
function &get_instance()
{
	global $classification_ci;
	return $classification_ci;
}

require_once dirname(__DIR__, 3) . '/application/helpers/request_authz_helper.php';

$command_center = doclinc_dokter_identity_context(1, true);
$personal = doclinc_dokter_identity_context(2, true);
$remark_only = doclinc_dokter_identity_context(3, true);

classification_expect($command_center['valid'] === true && $command_center['account_type'] === 'command_center', 'canonical_first_account_is_command_center');
classification_expect($personal['valid'] === true && $personal['account_type'] === 'personal' && $personal['staff_id'] === 10, 'same_remark_linked_staff_is_personal');
classification_expect($remark_only['valid'] === false && $remark_only['account_type'] === 'unclassified', 'remark_without_staff_link_is_not_personal');

echo "ROLE_CLASSIFICATION_UNIT_PASSED={$passed}\n";
echo "ROLE_CLASSIFICATION_UNIT_FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
