<?php
/**
 * Kecamatan Citangkil nearest-puskesmas routing regression test.
 *
 * Seven of the eight Cilegon coverage polygons map to one puskesmas code. The
 * eighth, PuskesmasCitangkil.geojson, covers TWO facilities:
 *   10280501 Puskesmas Citangkil
 *   2241001  Puskesmas Citangkil II
 *
 * Because both share a single ring, the polygon test cannot tell them apart.
 * The documented product rule is that the patient goes to the NEAREST one.
 * Before this fix the resolver returned the first code carrying a command
 * centre, so Puskesmas Citangkil II never received a single request while
 * Puskesmas Citangkil was staffed - a silent starvation with no error anywhere.
 *
 * The test drives the real resolver against a stubbed database so the distance
 * decision, the eligibility filter and the fallbacks are all exercised.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }
if (!defined('FCPATH')) { define('FCPATH', dirname(__DIR__, 3) . '/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }

function log_message($level, $message) { return true; }

/**
 * Minimal CI database double. The routing helper only ever asks whether a table
 * or column exists, then builds a where/order_by/limit/get chain, so the double
 * records the WHERE constraints and answers from fixture rows.
 */
class Citangkil_fake_db
{
	public $rows = array();
	public $dbprefix = '';
	private $wheres = array();
	private $order = null;
	private $limit = null;

	public function __construct(array $rows) { $this->rows = $rows; }

	public function table_exists($table) { return true; }

	public function field_exists($field, $table) { return true; }

	public function escape($value) { return "'" . str_replace("'", "''", (string) $value) . "'"; }

	public function where($key, $value = null, $escape = null)
	{
		if (is_string($key) && preg_match('/TRIM\((\w+)\)\s*=\s*(.+)$/i', $key, $m)) {
			$this->wheres[] = array('trim_column' => $m[1], 'raw_value' => trim($m[2], "'"));
			return $this;
		}
		if (is_string($key)) { $this->wheres[] = array('column' => $key, 'value' => $value); }
		return $this;
	}

	public function order_by($column, $direction = 'ASC') { $this->order = $column; return $this; }

	public function limit($n) { $this->limit = (int) $n; return $this; }

	public function get($table)
	{
		$matched = array();
		foreach ($this->rows as $row) {
			if (!isset($row['__table']) || $row['__table'] !== $table) { continue; }
			$ok = true;
			foreach ($this->wheres as $where) {
				if (isset($where['column'])) {
					$column = $where['column'];
					if (!array_key_exists($column, $row) || (string) $row[$column] !== (string) $where['value']) { $ok = false; break; }
				} else {
					$column = $where['trim_column'];
					if (!array_key_exists($column, $row) || trim((string) $row[$column]) !== (string) $where['raw_value']) { $ok = false; break; }
				}
			}
			if ($ok) { $matched[] = $row; }
		}
		$this->wheres = array();
		$order = $this->order;
		$this->order = null;
		$limit = $this->limit;
		$this->limit = null;
		if ($order !== null) {
			usort($matched, function ($a, $b) use ($order) {
				return strcmp((string) ($a[$order] ?? ''), (string) ($b[$order] ?? ''));
			});
		}
		if ($limit !== null) { $matched = array_slice($matched, 0, $limit); }
		return new Citangkil_fake_result($matched);
	}
}

class Citangkil_fake_result
{
	private $rows;
	public function __construct(array $rows) { $this->rows = $rows; }
	public function row() { return empty($this->rows) ? null : (object) $this->rows[0]; }
	public function result() { return array_map(function ($r) { return (object) $r; }, $this->rows); }
	public function num_rows() { return count($this->rows); }
}

$GLOBALS['citangkil_db'] = null;

/** Mirrors CI's real signature so `$CI = &get_instance()` does not warn. */
function &get_instance()
{
	static $instance = null;
	if ($instance === null) { $instance = new stdClass(); }
	$instance->db = $GLOBALS['citangkil_db'];
	return $instance;
}

require_once APPPATH . 'helpers/puskesmas_routing_helper.php';

$passed = 0;
$failed = 0;

function citangkil_check($condition, $label)
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

/** Seed the fixture: the two Citangkil facilities plus one single-code area. */
function citangkil_seed($command_centre_codes)
{
	$rows = array();
	$facilities = array(
		array('kode_pkm' => '10280501', 'nama_puskesmas' => 'Puskesmas Citangkil', 'latitude' => '-6.017040', 'longitude' => '106.018410', 'status' => 'aktif'),
		array('kode_pkm' => '2241001', 'nama_puskesmas' => 'Puskesmas Citangkil II', 'latitude' => '-6.012550', 'longitude' => '106.007620', 'status' => 'aktif'),
		array('kode_pkm' => '10280101', 'nama_puskesmas' => 'Puskesmas Cilegon', 'latitude' => '-6.027077', 'longitude' => '106.040176', 'status' => 'aktif'),
	);
	foreach ($facilities as $facility) {
		$facility['__table'] = 'm_puskesmas';
		$rows[] = $facility;
	}
	foreach ($command_centre_codes as $index => $code) {
		$rows[] = array(
			'__table' => 'users',
			'userId' => 900 + $index,
			'role' => 'dokter',
			'status' => 'aktif',
			'remark' => $code,
		);
	}
	$GLOBALS['citangkil_db'] = new Citangkil_fake_db($rows);
}

$citangkil = null;
foreach (doclinc_service_area_definitions() as $definition) {
	if ($definition['file'] === 'PuskesmasCitangkil.geojson') { $citangkil = $definition; }
}

echo "== the shared Citangkil polygon must declare both facilities ==\n";

citangkil_check($citangkil !== null, 'PuskesmasCitangkil.geojson is a declared coverage area');
citangkil_check($citangkil && $citangkil['codes'] === array('10280501', '2241001'), 'it declares exactly Puskesmas Citangkil and Citangkil II');

$multi = 0;
foreach (doclinc_service_area_definitions() as $definition) {
	if (count($definition['codes']) > 1) { $multi++; }
}
citangkil_check($multi === 1, 'exactly one coverage area maps to more than one facility');

echo "\n== the nearest facility wins ==\n";

// Patient standing at the Puskesmas Citangkil coordinates.
citangkil_seed(array('10280501', '2241001'));
$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.017040, 106.018410);
citangkil_check($resolved && (string) $resolved->kode_pkm === '10280501', 'patient at Citangkil coordinates resolves to Puskesmas Citangkil');

// Patient standing at the Puskesmas Citangkil II coordinates. This is the case
// that used to be unreachable: Citangkil II was starved because 10280501 was
// declared first and always won.
$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.012550, 106.007620);
citangkil_check($resolved && (string) $resolved->kode_pkm === '2241001', 'patient at Citangkil II coordinates resolves to Puskesmas Citangkil II');

$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.012000, 106.008000);
citangkil_check($resolved && (string) $resolved->kode_pkm === '2241001', 'a point nearer Citangkil II resolves to Citangkil II');

$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.018000, 106.019000);
citangkil_check($resolved && (string) $resolved->kode_pkm === '10280501', 'a point nearer Citangkil resolves to Citangkil');
echo "\n== only facilities that can accept are considered ==\n";

// Citangkil II alone is staffed: the patient must go there even though the
// declaration order names Citangkil first.
citangkil_seed(array('2241001'));
$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.017040, 106.018410);
citangkil_check($resolved && (string) $resolved->kode_pkm === '2241001', 'an unstaffed Citangkil is skipped even when it is nearer');

citangkil_seed(array('10280501'));
$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.012550, 106.007620);
citangkil_check($resolved && (string) $resolved->kode_pkm === '10280501', 'an unstaffed Citangkil II is skipped even when it is nearer');

echo "\n== fallbacks stay backward compatible ==\n";

// Neither facility is staffed. The request is unroutable either way, but the
// nearest is still the most sensible answer because it matches where the
// patient physically is - distance outranks declaration order whenever two
// distances can be compared.
citangkil_seed(array());
$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.012550, 106.007620);
citangkil_check($resolved && (string) $resolved->kode_pkm === '2241001', 'with no command centre distance still outranks declaration order');

$resolved = doclinc_resolve_service_area_puskesmas($citangkil, -6.017040, 106.018410);
citangkil_check($resolved && (string) $resolved->kode_pkm === '10280501', 'with no command centre the nearer Citangkil still wins from its own side');

// No coordinates supplied: distance cannot be computed, so declaration order.
citangkil_seed(array('10280501', '2241001'));
$resolved = doclinc_resolve_service_area_puskesmas($citangkil);
citangkil_check($resolved && (string) $resolved->kode_pkm === '10280501', 'without coordinates the declaration order still decides');

$resolved = doclinc_resolve_service_area_puskesmas($citangkil, 'not-a-number', null);
citangkil_check($resolved && (string) $resolved->kode_pkm === '10280501', 'non-numeric coordinates fall back to declaration order');

echo "\n== single-facility areas are unaffected ==\n";

$cilegon = null;
foreach (doclinc_service_area_definitions() as $definition) {
	if ($definition['file'] === 'PuskesmasCilegon.geojson') { $cilegon = $definition; }
}
citangkil_seed(array('10280101', '10280501', '2241001'));
$resolved = doclinc_resolve_service_area_puskesmas($cilegon, -6.012550, 106.007620);
citangkil_check($resolved && (string) $resolved->kode_pkm === '10280101', 'a single-facility area ignores the patient coordinates');

echo "\n== the catchment boundary itself must not widen ==\n";

$helper = file_get_contents(APPPATH . 'helpers/puskesmas_routing_helper.php');
citangkil_check(strpos($helper, 'doclinc_resolve_service_area_puskesmas($definition, $lat, $lng)') !== false, 'the resolver receives the patient coordinates');
citangkil_check(strpos($helper, 'doclinc_haversine_km') !== false, 'the tie-break uses the shared haversine helper');

$controller = file_get_contents(APPPATH . 'modules/konsultasi/controllers/Konsultasi.php');
citangkil_check(strpos($controller, 'doclinc_find_nearest_puskesmas') === false, 'the controller still does NOT use the global nearest fallback (catchment stays Cilegon-only)');
citangkil_check(strpos($controller, 'doclinc_find_puskesmas_by_service_area') !== false, 'the controller still gates on the service-area polygon test');

echo "\nCITANGKIL_NEAREST_ROUTING=PASSED=$passed, FAILED=$failed\n";
exit($failed === 0 ? 0 : 1);


