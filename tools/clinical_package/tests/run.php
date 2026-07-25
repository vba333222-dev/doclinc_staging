<?php

require_once dirname(__DIR__) . '/ClinicalPackageException.php';
require_once dirname(__DIR__) . '/ClinicalPackageCli.php';
require_once dirname(__DIR__) . '/ClinicalPackagePathPolicy.php';
require_once dirname(__DIR__) . '/StrictJsonDecoder.php';
require_once dirname(__DIR__) . '/JcsCanonicalizer.php';
require_once dirname(__DIR__) . '/ClinicalPackageChecksumBuilder.php';
require_once dirname(__DIR__) . '/ClinicalPackageLoader.php';
require_once dirname(__DIR__) . '/ClinicalRegistrationContractValidator.php';
require_once dirname(__DIR__) . '/ClinicalRegistrationModelBuilder.php';
require_once dirname(__DIR__) . '/ClinicalRegistrationStateRepository.php';
require_once dirname(__DIR__) . '/ClinicalRegistrationPlanner.php';
require_once dirname(__DIR__) . '/ClinicalRegistrationWritePrivilegeValidator.php';
require_once dirname(__DIR__) . '/ClinicalRegistrationWriteRepository.php';
require_once dirname(__DIR__) . '/ClinicalRegistrationWriter.php';
require_once dirname(__DIR__) . '/ClinicalPackageReporter.php';

class ClinicalPackageTestSkip extends RuntimeException {}

class FakeClinicalRegistrationStateRepository implements ClinicalRegistrationStateRepository
{
	public $graph;
	public function __construct(array $graph) { $this->graph = $graph; }
	public function fetchGraph($packageKey, $packageVersion, $packageChecksum) { return $this->graph; }
	public function close() {}
}

class FakeClinicalRegistrationWriteRepository implements ClinicalRegistrationWriteRepository
{
	public $graph;
	public $audits = array();
	public $operations = array();
	public $failAudit = false;
	public $failRelease = false;
	public $connectionClosed = false;
	public $graphOnLock = null;
	private $workingGraph;
	private $workingAudits;
	private $database;
	private $nextPackageId = 1;
	private $nextDatasetId = 1;
	private $datasetKeys = array();
	private $inTransaction = false;
	private $lock = null;

	public function __construct(array $graph, $database = 'clinical_test')
	{
		$this->graph = $graph;
		$this->database = $database;
		foreach ($graph['package_identity_rows'] as $row) if (isset($row['clinical_master_package_id'])) $this->nextPackageId = max($this->nextPackageId, (int) $row['clinical_master_package_id'] + 1);
		foreach ($graph['datasets'] as $row) {
			if (isset($row['clinical_master_dataset_id'])) {
				$this->nextDatasetId = max($this->nextDatasetId, (int) $row['clinical_master_dataset_id'] + 1);
				$this->datasetKeys[(string) $row['clinical_master_dataset_id']] = $row['dataset_key'];
			}
		}
	}
	public function databaseName() { return $this->database; }
	public function fetchGraph($packageKey, $packageVersion, $packageChecksum) { return $this->inTransaction ? $this->workingGraph : $this->graph; }
	public function acquireLock($lockName, $timeoutSeconds) { if($this->lock!==null)throw new ClinicalPackageException('registration_lock_timeout','lock');$this->lock=$lockName;if($this->graphOnLock!==null)$this->graph=$this->graphOnLock;$this->operations[]='lock'; }
	public function releaseLock($lockName) { $this->operations[]='unlock_attempt';if($this->failRelease)throw new ClinicalPackageException('registration_lock_release_failed','lock release');$this->lock=null;$this->operations[]='unlock'; }
	public function begin() { if($this->inTransaction)throw new ClinicalPackageException('registration_transaction_failed','transaction');$this->workingGraph=$this->graph;$this->workingAudits=$this->audits;$this->inTransaction=true;$this->operations[]='begin'; }
	public function commit() { if(!$this->inTransaction)throw new ClinicalPackageException('registration_transaction_failed','transaction');$this->graph=$this->workingGraph;$this->audits=$this->workingAudits;$this->inTransaction=false;$this->operations[]='commit'; }
	public function rollback() { if($this->inTransaction){$this->inTransaction=false;$this->operations[]='rollback';} }
	public function insertPackage(array $row,$observedAt) { $id=(string)$this->nextPackageId++;$actual=$row;$actual['clinical_master_package_id']=$id;$this->workingGraph['package_identity_rows'][]=$actual;$this->workingGraph['package_checksum_rows'][]=array('clinical_master_package_id'=>$id,'package_key'=>$row['package_key'],'package_version'=>$row['package_version']);$this->operations[]='package';return $id; }
	public function insertPackageCapability($packageId,array $row) { $this->workingGraph['package_capabilities'][]=$row;$this->operations[]='package_capability'; }
	public function insertDataset($packageId,array $row) { $id=(string)$this->nextDatasetId++;$actual=$row;$actual['clinical_master_dataset_id']=$id;$this->workingGraph['datasets'][]=$actual;$this->datasetKeys[$id]=$row['dataset_key'];$this->operations[]='dataset';return $id; }
	public function insertDatasetCapability($datasetId,array $row) { $this->workingGraph['dataset_capabilities'][]=array('dataset_key'=>$this->datasetKeys[(string)$datasetId])+$row;$this->operations[]='dataset_capability'; }
	public function insertFieldContract($datasetId,array $row) { $this->workingGraph['field_contracts'][]=array('dataset_key'=>$this->datasetKeys[(string)$datasetId])+$row;$this->operations[]='field_contract'; }
	public function insertAudit(array $row) { if($this->failAudit)throw new ClinicalPackageException('metadata_audit_insert_failed','audit');$this->workingAudits[]=$row;$this->operations[]='audit'; }
	public function close() { if($this->inTransaction)$this->rollback();$this->lock=null;$this->connectionClosed=true;$this->operations[]='close'; }
	public function lockOwned() { return $this->lock!==null; }
}

class ClinicalPackageTests
{
	private $passes = 0;
	private $failures = 0;
	private $skips = 0;
	private $acceptedRoot;
	private $acceptedParent;
	private $fixtureParent;
	private $fixtureRoot;
	private $config;
	private $loader;
	private $builder;
	private $contractValidator;
	private $evidence;
	private $model;
	private $environment = array();

	public function run()
	{
		$this->setUp();
		try {
			$this->tests();
		} finally {
			$this->tearDown();
		}
		echo 'TESTS_PASSED=' . $this->passes . PHP_EOL;
		echo 'TESTS_FAILED=' . $this->failures . PHP_EOL;
		echo 'TESTS_SKIPPED=' . $this->skips . PHP_EOL;
		echo 'REGISTRATION_CONTRACT_FIELD_COUNT=' . $this->contractValidator->fieldContractCount() . PHP_EOL;
		echo 'REGISTRATION_CONTRACT_NEGATIVE_TEST_COUNT=23' . PHP_EOL;
		echo 'NULLABLE_COMPARISON_FIELD_COUNT=16' . PHP_EOL;
		echo 'DATETIME_NEGATIVE_TEST_COUNT=13' . PHP_EOL;
		echo 'SOURCE_REFERENCE_NEGATIVE_TEST_COUNT=18' . PHP_EOL;
		echo 'VALIDATION_RESULT=' . ($this->failures === 0 ? 'PASS' : 'FAIL') . PHP_EOL;
		return $this->failures === 0 ? 0 : 1;
	}

	private function setUp()
	{
		$this->config = require dirname(__DIR__) . '/config.php';
		$this->acceptedRoot = realpath(dirname(__DIR__, 3) . '/database/master_data/clinical/v1');
		$this->acceptedParent = realpath(dirname($this->acceptedRoot));
		if ($this->acceptedRoot === false) throw new RuntimeException('accepted_package_missing');
		$this->fixtureParent = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'doclink-clinical-package-' . bin2hex(random_bytes(8));
		$this->fixtureRoot = $this->fixtureParent . DIRECTORY_SEPARATOR . 'v1-copy';
		if (!mkdir($this->fixtureParent, 0700, true)) throw new RuntimeException('fixture_parent_create_failed');
		$this->copyTree($this->acceptedRoot, $this->fixtureRoot);
		$names = array_merge(
			array($this->config['allowed_roots_environment'],$this->config['metadata_write_enabled_environment'],$this->config['metadata_write_lock_timeout_environment']),
			array_values($this->config['database_environment_variables']),
			array_values($this->config['write_database_environment_variables'])
		);
		$names = array_values(array_unique($names));
		foreach ($names as $name) $this->environment[$name] = getenv($name);
		putenv($this->config['allowed_roots_environment'] . '=' . $this->acceptedParent);
		foreach ($this->config['database_environment_variables'] as $name) putenv($name);
		foreach ($this->config['write_database_environment_variables'] as $name) putenv($name);
		putenv($this->config['metadata_write_enabled_environment']);
		putenv($this->config['metadata_write_lock_timeout_environment']);
		$this->loader = $this->makeLoader();
		$this->contractValidator = new ClinicalRegistrationContractValidator();
		$this->builder = new ClinicalRegistrationModelBuilder(new ClinicalPackageChecksumBuilder(new JcsCanonicalizer()), new JcsCanonicalizer(), $this->contractValidator);
		$this->evidence = $this->loader->load($this->acceptedRoot);
		$this->model = $this->builder->build($this->evidence);
	}

	private function tests()
	{
		$this->test('001_accepted_package_inspection', function () {
			$this->same(51, $this->evidence['package_file_count']);
			$this->same(47, $this->evidence['observed_dataset_count']);
			$this->same(14869, $this->evidence['observed_record_count']);
		});
		$this->test('002_accepted_snapshot_exact', function () {
			$this->same('e9373c66742caa172d83e05f265f76130e3343f298d13f9bb19cf16e22b6d493', $this->evidence['package_snapshot_sha256']);
		});
		$this->test('003_manifest_checksum_is_raw_bytes', function () {
			$this->same(hash_file('sha256', $this->acceptedRoot . '/00_manifest.json'), $this->evidence['manifest_checksum']);
		});
		$this->test('004_checksum_deterministic', function () {
			$this->same($this->model['package_checksum'], $this->builder->build($this->evidence)['package_checksum']);
			$this->matches('/^[0-9a-f]{64}$/', $this->model['package_checksum']);
		});
		$this->test('005_copy_at_other_path_same_checksum', function () {
			putenv($this->config['allowed_roots_environment'] . '=' . $this->fixtureParent);
			$copyModel = $this->builder->build($this->makeLoader()->load($this->fixtureRoot));
			$this->same($this->model['package_checksum'], $copyModel['package_checksum']);
			putenv($this->config['allowed_roots_environment'] . '=' . $this->acceptedParent);
		});
		$this->test('006_changed_dataset_byte_changes_checksum', function () {
			$this->resetFixture();
			$file = $this->fixtureRoot . '/43_master_jenis_resep.json';
			file_put_contents($file, file_get_contents($file) . "\n");
			$manifest = $this->fixtureManifest();
			foreach ($manifest['datasets'] as &$dataset) if ($dataset['path'] === '43_master_jenis_resep.json') $dataset['sha256'] = hash_file('sha256', $file);
			unset($dataset);
			$this->writeFixtureManifest($manifest);
			putenv($this->config['allowed_roots_environment'] . '=' . $this->fixtureParent);
			$changed = $this->builder->build($this->makeLoader()->load($this->fixtureRoot));
			$this->notSame($this->model['package_checksum'], $changed['package_checksum']);
			putenv($this->config['allowed_roots_environment'] . '=' . $this->acceptedParent);
		});
		$this->test('006a_manifest_indentation_does_not_change_semantic_checksum', function () {
			$this->resetFixture();
			$manifest = $this->fixtureManifest();
			file_put_contents($this->fixtureRoot.'/00_manifest.json',json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
			putenv($this->config['allowed_roots_environment'] . '=' . $this->fixtureParent);
			$changed = $this->builder->build($this->makeLoader()->load($this->fixtureRoot));
			$this->same($this->model['package_checksum'], $changed['package_checksum']);
			$this->notSame($this->model['package']['persisted']['manifest_checksum'], $changed['package']['persisted']['manifest_checksum']);
			putenv($this->config['allowed_roots_environment'] . '=' . $this->acceptedParent);
		});

		$baseProjection = array('profile' => ClinicalPackageChecksumBuilder::PROFILE, 'package' => array('license' => 'not_declared'), 'package_capabilities' => array(array('capability' => 'audit_import', 'decision_status' => 'blocked')), 'datasets' => array(array('field_contracts' => array(array('source_field' => 'kode', 'required_flag' => 1)))), 'governance' => array('status' => 'draft'));
		$this->test('007_changed_capability_changes_checksum', function () use ($baseProjection) { $changed=$baseProjection;$changed['package_capabilities'][0]['decision_status']='pending';$this->checksumDiffers($baseProjection,$changed); });
		$this->test('008_changed_field_contract_changes_checksum', function () use ($baseProjection) { $changed=$baseProjection;$changed['datasets'][0]['field_contracts'][0]['required_flag']=0;$this->checksumDiffers($baseProjection,$changed); });
		$this->test('009_changed_governance_changes_checksum', function () use ($baseProjection) { $changed=$baseProjection;$changed['governance']['status']='in_review';$this->checksumDiffers($baseProjection,$changed); });
		$this->test('010_changed_license_changes_checksum', function () use ($baseProjection) { $changed=$baseProjection;$changed['package']['license']='cleared';$this->checksumDiffers($baseProjection,$changed); });
		$this->test('011_jcs_object_order_deterministic', function () { $j=new JcsCanonicalizer();$this->same($j->canonicalize(array('b'=>1,'a'=>2)),$j->canonicalize(array('a'=>2,'b'=>1))); });
		$this->test('012_profile_exact', function () { $this->same('doclink-package-jcs-v1',$this->model['package_checksum_profile']); });
		$this->test('012a_semantic_governance_notice_member_order_is_irrelevant', function () { $a=$this->evidence;$b=$this->evidence;$a['manifest']['governance_notices'][0]=array('code'=>'notice','required'=>true);$b['manifest']['governance_notices'][0]=array('required'=>true,'code'=>'notice');$this->same($this->builder->build($a)['package_checksum'],$this->builder->build($b)['package_checksum']); });
		$this->test('012b_semantic_review_principle_member_order_is_irrelevant', function () { $a=$this->evidence;$b=$this->evidence;$a['manifest']['review_governance']['principles'][0]=array('code'=>'principle','required'=>true);$b['manifest']['review_governance']['principles'][0]=array('required'=>true,'code'=>'principle');$this->same($this->builder->build($a)['package_checksum'],$this->builder->build($b)['package_checksum']); });
		$this->test('012c_semantic_submanifest_member_order_is_irrelevant', function () { $a=$this->evidence;$b=$this->evidence;$b['manifest']['submanifests'][0]=$this->reverseObjectMembers($b['manifest']['submanifests'][0]);$this->same($this->builder->build($a)['package_checksum'],$this->builder->build($b)['package_checksum']); });
		$this->test('012d_unordered_list_order_is_irrelevant', function () { $changed=$this->evidence;$changed['manifest']['governance_notices']=array_reverse($changed['manifest']['governance_notices']);$changed['manifest']['review_governance']['principles']=array_reverse($changed['manifest']['review_governance']['principles']);$changed['manifest']['review_governance']['source_status_mappings']=array_reverse($changed['manifest']['review_governance']['source_status_mappings']);$this->same($this->model['package_checksum'],$this->builder->build($changed)['package_checksum']); });
		$this->test('012e_semantic_value_change_changes_checksum', function () { $changed=$this->evidence;$changed['manifest']['governance_notices'][0].=' changed';$this->notSame($this->model['package_checksum'],$this->builder->build($changed)['package_checksum']); });
		$this->test('012f_duplicate_semantic_unordered_entry_rejected', function () { $changed=$this->evidence;$changed['manifest']['governance_notices'][]=$changed['manifest']['governance_notices'][0];$this->expectCode(function () use($changed){$this->builder->build($changed);},'duplicate_semantic_governance_notice'); });
		$this->test('012g_jcs_u2028_is_not_escaped', function () { $j=new JcsCanonicalizer();$this->same('"'."\u{2028}".'"',$j->canonicalize("\u{2028}")); });
		$this->test('012h_jcs_u2029_is_not_escaped', function () { $j=new JcsCanonicalizer();$this->same('"'."\u{2029}".'"',$j->canonicalize("\u{2029}")); });
		$this->test('012i_jcs_control_characters_are_escaped', function () { $j=new JcsCanonicalizer();$this->same('"\\u0000\\n\\t"',$j->canonicalize("\0\n\t")); });
		$this->test('012j_jcs_utf16_object_key_order', function () { $j=new JcsCanonicalizer();$this->same('{"😀":1,"":2}',$j->canonicalize(array(''=>2,'😀'=>1))); });
		$this->test('012k_jcs_equivalent_object_key_order', function () { $j=new JcsCanonicalizer();$this->same($j->canonicalize((object)array('z'=>1,'a'=>2)),$j->canonicalize((object)array('a'=>2,'z'=>1))); });
		$this->test('012l_jcs_float_rejected', function () { $this->expectCode(function(){(new JcsCanonicalizer())->canonicalize(1.0);},'jcs_number_unsupported'); });
		$this->test('012m_jcs_interoperable_integer_boundaries_accepted', function () { $j=new JcsCanonicalizer();$this->same('-9007199254740991',$j->canonicalize(-9007199254740991));$this->same('9007199254740991',$j->canonicalize(9007199254740991));$this->same('0',$j->canonicalize(0)); });
		$this->test('012n_jcs_out_of_range_integers_rejected', function () { $j=new JcsCanonicalizer();foreach(array(-9007199254740992,9007199254740992,PHP_INT_MIN,PHP_INT_MAX)as$value)$this->expectCode(function()use($j,$value){$j->canonicalize($value);},'jcs_integer_out_of_range'); });
		$this->test('012o_negative_zero_is_strict_json_decoder_governed', function () { $decoded=(new StrictJsonDecoder())->decode('-0');$this->same(0,$decoded);$this->same('0',(new JcsCanonicalizer())->canonicalize($decoded)); });

		$this->test('013_manifest_count_mismatch_rejected', function () { $this->mutateManifest(function (&$m) { $m['package_summary']['record_count']++; });$this->expectLoaderCode('package_record_count_mismatch'); });
		$this->test('014_dataset_checksum_mismatch_rejected', function () { $this->resetFixture();file_put_contents($this->fixtureRoot.'/43_master_jenis_resep.json',file_get_contents($this->fixtureRoot.'/43_master_jenis_resep.json')." ");$this->expectLoaderCode('dataset_checksum_mismatch'); });
		$this->test('015_duplicate_dataset_key_rejected', function () { $this->mutateManifest(function (&$m) { $m['datasets'][1]['dataset_id']=$m['datasets'][0]['dataset_id']; });$this->expectLoaderCode('duplicate_dataset_key'); });
		$this->test('016_duplicate_dataset_path_rejected', function () { $this->mutateManifest(function (&$m) { $m['datasets'][1]['path']=$m['datasets'][0]['path']; });$this->expectLoaderCode('duplicate_dataset_path'); });
		$this->test('017_manifest_path_traversal_rejected', function () { $this->mutateManifest(function (&$m) { $m['datasets'][0]['path']='../outside.json'; });$this->expectLoaderCode('package_relative_path_invalid'); });
		$this->test('018_unknown_manifest_field_rejected', function () { $this->mutateManifest(function (&$m) { $m['unexpected_registration_field']=true; });$this->expectLoaderCode('established_package_validation_failed'); });
		$this->test('019_missing_manifest_field_rejected', function () { $this->mutateManifest(function (&$m) { unset($m['package_status']); });$this->expectLoaderCode('established_package_validation_failed'); });
		$this->test('020_undeclared_file_rejected', function () { $this->resetFixture();file_put_contents($this->fixtureRoot.'/extra.txt','x');$this->expectLoaderCode('undeclared_package_file'); });
		$this->test('021_missing_declared_file_rejected', function () { $this->resetFixture();unlink($this->fixtureRoot.'/43_master_jenis_resep.json');$this->expectLoaderCode('declared_package_file_missing'); });

		$json = new StrictJsonDecoder();
		$this->test('022_malformed_json_rejected', function () use ($json) { $this->expectCode(function () use ($json) { $json->decode('{"a":}', 'fixture.json'); }, 'json_malformed'); });
		$this->test('023_duplicate_json_key_rejected', function () use ($json) { $this->expectCode(function () use ($json) { $json->decode('{"a":1,"a":2}', 'fixture.json'); }, 'json_duplicate_object_key'); });
		$this->test('024_invalid_utf8_rejected', function () use ($json) { $this->expectCode(function () use ($json) { $json->decode("{\"a\":\"\xFF\"}", 'fixture.json'); }, 'json_invalid_utf8'); });
		$this->test('025_unsupported_number_rejected', function () use ($json) { $this->expectCode(function () use ($json) { $json->decode('{"a":9007199254740992}', 'fixture.json'); }, 'json_number_unsupported'); });
		$this->test('026_nonfinite_number_rejected', function () use ($json) { $this->expectCode(function () use ($json) { $json->decode('{"a":1e400}', 'fixture.json'); }, 'json_number_unsupported'); });
		$this->test('027_trailing_data_rejected', function () use ($json) { $this->expectCode(function () use ($json) { $json->decode('{"a":1} x', 'fixture.json'); }, 'json_trailing_data'); });
		$this->test('028_type_preservation', function () use ($json) { $o=$json->decode('{"b":false,"i":1,"s":"1","n":null}');$this->same(false,$o->b);$this->same(1,$o->i);$this->same('1',$o->s);$this->same(null,$o->n); });

		$this->test('029_missing_allowlist_rejected', function () { putenv($this->config['allowed_roots_environment']);$this->expectCode(function () { $this->makeLoader()->load($this->acceptedRoot); },'package_allowed_roots_missing');putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent); });
		$this->test('030_outside_allowlist_rejected', function () { putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);$this->expectCode(function () { $this->makeLoader()->load($this->acceptedRoot); },'package_root_not_allowed');putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent); });
		$this->test('031_explicit_traversal_rejected', function () { $p=$this->acceptedRoot.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'v1';$this->expectCode(function () use ($p) { $this->makeLoader()->load($p); },'package_path_invalid'); });
		$this->test('032_symlink_root_rejected_where_supported', function () { $this->symlinkTest(true); });
		$this->test('033_nested_symlink_rejected_where_supported', function () { $this->symlinkTest(false); });
		$this->test('033a_intermediate_symlink_component_rejected_where_supported', function () { $this->intermediateSymlinkTest(1); });
		$this->test('033b_two_level_intermediate_symlink_component_rejected_where_supported', function () { $this->intermediateSymlinkTest(2); });
		$this->test('033c_outside_intermediate_symlink_rejected_where_supported', function () { $this->intermediateSymlinkTest(1,true); });
		$this->test('034_executable_file_rejected', function () { $this->resetFixture();$p=$this->fixtureRoot.'/fixture.exe';file_put_contents($p,'not executable content');@chmod($p,0755);putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);$this->expectCode(function () { $this->makeLoader()->load($this->fixtureRoot); },'package_executable_file_rejected');putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent); });
		$this->test('035_special_file_rejected_where_supported', function () { if (!function_exists('posix_mkfifo')) throw new ClinicalPackageTestSkip('fifo unsupported');$this->resetFixture();$p=$this->fixtureRoot.'/pipe';if(!posix_mkfifo($p,0600))throw new ClinicalPackageTestSkip('fifo creation unavailable');putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);$this->expectCode(function () { $this->makeLoader()->load($this->fixtureRoot); },'package_special_file_rejected');putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent); });

		$this->test('036_exact_plan_counts', function () { $c=$this->model['counts'];$this->same(1,$c['package_rows']);$this->same(47,$c['dataset_rows']);$this->same(8,$c['package_capabilities']);$this->same(376,$c['dataset_capabilities']);$this->same(458,$c['field_contracts']); });
		$this->test('037_no_record_or_import_plan', function () { $this->same(0,$this->model['audit_plan']['canonical_record_plan_count']);$this->same(0,$this->model['audit_plan']['import_item_row_plan_count']);$this->same(false,$this->model['audit_plan']['record_import_eligible']); });
		$this->test('038_all_capabilities_blocked', function () { foreach($this->model['package_capabilities'] as $r)$this->same('blocked',$r['persisted']['decision_status']);foreach($this->model['datasets'] as $d)foreach($d['capabilities'] as $r)$this->same('blocked',$r['persisted']['decision_status']); });
		$this->test('039_duplicate_package_capability_plan_rejected', function () { $m=$this->model;$m['package_capabilities'][]=$m['package_capabilities'][0];$this->expectCode(function () use ($m) { $this->builder->assertPlanIntegrity($m); },'duplicate_package_capability_plan'); });
		$this->test('040_duplicate_field_contract_plan_rejected', function () { $m=$this->model;$m['datasets'][0]['field_contracts'][]=$m['datasets'][0]['field_contracts'][0];$this->expectCode(function () use ($m) { $this->builder->assertPlanIntegrity($m); },'duplicate_field_contract_plan'); });
		$this->test('041_human_label_not_identity', function () { foreach($this->model['datasets'] as $d)if($d['persisted']['dataset_key']==='MASTER_DIAGNOSIS_ALIAS_INDONESIA_STARTER')foreach($d['field_contracts'] as $f)if($f['persisted']['source_field']==='nama_indonesia'){$this->same('label',$f['persisted']['identity_role']);$this->same(true,$f['observation']['identity_blocked']);return;}throw new RuntimeException('alias label contract missing'); });
		$this->test('042_offline_plan', function () { $r=(new ClinicalRegistrationPlanner())->offline($this->model);$this->same('NOT_REQUESTED',$r['database_comparison']);$this->same('not_compared',$r['package_state']);$this->same(0,$r['conflict_entity_count']); });
		$this->test('043_database_new_classification', function () { $r=$this->compareGraph($this->emptyGraph());$this->same('new',$r['package_state']);$this->same(890,$r['new_entity_count']); });
		$this->test('044_database_exact_noop_classification', function () { $r=$this->compareGraph($this->exactGraph());$this->same('exact_match',$r['package_state']);$this->same(890,$r['exact_match_entity_count']);$this->same(0,$r['conflict_entity_count']); });
		$this->test('045_conflicting_checksum', function () { $g=$this->exactGraph();$g['package_identity_rows'][0]['package_checksum']=str_repeat('0',64);$r=$this->compareGraph($g);$this->same('conflict',$r['package_state']); });
		$this->test('046_partial_graph_conflict', function () { $g=$this->exactGraph();array_pop($g['field_contracts']);$r=$this->compareGraph($g);$this->same('conflict',$r['package_state']); });
		$this->test('047_duplicate_persisted_natural_key_conflict', function () { $g=$this->exactGraph();$g['datasets'][]=$g['datasets'][0];$r=$this->compareGraph($g);$this->same('conflict',$r['package_state']); });
		$this->test('048_unsupported_governance_conflict', function () { $g=$this->exactGraph();$g['package_identity_rows'][0]['governance_status']='unexpected';$r=$this->compareGraph($g);$this->same('conflict',$r['package_state']); });
		$this->test('049_later_capability_governance_is_conflict', function () { $g=$this->exactGraph();$g['package_capabilities'][0]['decision_status']='approved';$g['package_capabilities'][0]['decision_actor']='reviewer';$g['package_capabilities'][0]['decided_at']='2026-01-01 00:00:00.000000';$r=$this->compareGraph($g);$this->same('conflict',$r['package_state']); });
		$this->test('050_duplicate_checksum_other_identity_conflict', function () { $g=$this->emptyGraph();$g['package_checksum_rows'][]=array('package_key'=>'other','package_version'=>'v1');$r=$this->compareGraph($g);$this->same('conflict',$r['package_state']); });
		$this->test('050a_package_governance_difference_conflict', function () { $this->graphFieldConflict('package_identity_rows',0,'governance_status','in_review'); });
		$this->test('050b_package_license_difference_conflict', function () { $this->graphFieldConflict('package_identity_rows',0,'license_disposition','cleared'); });
		$this->test('050c_dataset_governance_difference_conflict', function () { $this->graphFieldConflict('datasets',0,'governance_status','in_review'); });
		$this->test('050d_dataset_license_difference_conflict', function () { $this->graphFieldConflict('datasets',0,'license_disposition','cleared'); });
		$this->test('050e_capability_status_difference_conflict', function () { $this->graphFieldConflict('package_capabilities',0,'decision_status','pending'); });
		$this->test('050f_capability_reason_difference_conflict', function () { $this->graphFieldConflict('package_capabilities',0,'decision_reason','different'); });
		$this->test('050g_capability_actor_time_difference_conflict', function () { $this->graphFieldConflict('package_capabilities',0,'decision_actor','reviewer');$this->graphFieldConflict('package_capabilities',0,'decided_at','2026-01-01 00:00:00.000000'); });
		$this->test('050h_field_target_domain_difference_conflict', function () { $this->graphFieldConflict('field_contracts',0,'target_domain','different'); });
		$this->test('050i_field_target_entity_difference_conflict', function () { $this->graphFieldConflict('field_contracts',0,'target_entity','different'); });
		$this->test('050j_field_target_attribute_difference_conflict', function () { $this->graphFieldConflict('field_contracts',0,'target_attribute','different'); });
		$this->test('050k_field_transform_policy_difference_conflict', function () { $this->graphFieldConflict('field_contracts',0,'transform_policy','manual_review'); });
		$this->test('050l_field_contract_status_difference_conflict', function () { $this->graphFieldConflict('field_contracts',0,'contract_status','in_review'); });
		$this->test('050m_field_review_reason_difference_conflict', function () { $this->graphFieldConflict('field_contracts',0,'review_reason','different'); });
		$this->test('050n_field_actor_time_difference_conflict', function () { $this->graphFieldConflict('field_contracts',0,'decision_actor','reviewer');$this->graphFieldConflict('field_contracts',0,'decided_at','2026-01-01 00:00:00.000000'); });
		$this->test('050o_fully_identical_persisted_fields_exact_match', function () { $r=$this->compareGraph($this->exactGraph());$this->same('exact_match',$r['package_state']);$this->same(890,$r['exact_match_entity_count']); });
		$this->test('050p_persisted_type_comparison_is_strict', function () { $g=$this->exactGraph();$g['datasets'][0]['source_runtime_enabled']=false;$this->same('conflict',$this->compareGraph($g)['package_state']);$g=$this->exactGraph();$g['datasets'][0]['source_runtime_enabled']='0';$this->same('exact_match',$this->compareGraph($g)['package_state']);$g=$this->exactGraph();$g['field_contracts'][0]['review_reason']='';$this->same('conflict',$this->compareGraph($g)['package_state']); });
		$this->test('050q_missing_or_duplicate_checksum_graph_row_conflict', function () { $g=$this->exactGraph();$g['package_checksum_rows']=array();$this->same('conflict',$this->compareGraph($g)['package_state']);$g=$this->exactGraph();$g['package_checksum_rows'][]=$g['package_checksum_rows'][0];$this->same('conflict',$this->compareGraph($g)['package_state']); });

		$this->test('051_text_inspect_output_contract', function () { $report=ClinicalPackageReporter::success('inspect',$this->model);ob_start();$rc=ClinicalPackageReporter::render($report,'text');$out=ob_get_clean();$this->same(0,$rc);foreach(array('COMMAND=inspect','PACKAGE_FILE_COUNT=51','DML_EXECUTED=false','PACKAGE_DATA_IMPORTED=false','RUNTIME_ENABLED=false','VALIDATION_RESULT=PASS')as$n)$this->contains($n,$out); });
		$this->test('052_text_plan_output_contract', function () { $comparison=(new ClinicalRegistrationPlanner())->offline($this->model);$report=ClinicalPackageReporter::success('plan-registration',$this->model,$comparison);ob_start();ClinicalPackageReporter::render($report,'text');$out=ob_get_clean();foreach(array('DATABASE_COMPARISON=NOT_REQUESTED','PACKAGE_ROW_PLAN_COUNT=1','FIELD_CONTRACT_PLAN_COUNT=458','CANONICAL_RECORD_PLAN_COUNT=0','IMPORT_ITEM_PLAN_COUNT=0')as$n)$this->contains($n,$out); });
		$this->test('053_json_output_contract_and_order', function () { $report=ClinicalPackageReporter::success('inspect',$this->model);ob_start();ClinicalPackageReporter::render($report,'json');$out=ob_get_clean();$decoded=json_decode($out,true);$this->same('inspect',$decoded['command']);$this->same(false,$decoded['dml_executed']);$this->same('PASS',$decoded['validation_result']);$this->matches('/^\{"command":/',trim($out)); });
		$this->test('054_failure_output_redacts_absolute_path', function () { $r=ClinicalPackageReporter::failure('inspect','safe_error');ob_start();ClinicalPackageReporter::render($r,'text');$out=ob_get_clean();$this->same(false,strpos($out,$this->acceptedRoot)!==false);$this->contains('VALIDATION_RESULT=FAIL',$out); });
		$this->test('054a_all_usage_failures_are_fail_in_text_and_json', function () { foreach(array('invalid_command','missing_package_root','malformed_option','unknown_option','unsupported_format')as$code){$this->assertFailureResult($code,2,'text');$this->assertFailureResult($code,2,'json');}foreach(array('text','json')as$format)$this->assertFailureResult('package_validation_failed',1,$format); });
		$this->test('054b_registration_conflict_is_fail_in_text_and_json', function () { $g=$this->exactGraph();$g['package_identity_rows'][0]['package_checksum']=str_repeat('0',64);$comparison=$this->compareGraph($g);$report=ClinicalPackageReporter::success('plan-registration',$this->model,$comparison);foreach(array('text','json')as$format){ob_start();$exit=ClinicalPackageReporter::render($report,$format);$out=ob_get_clean();$this->same(1,$exit);$this->contains($format==='text'?'VALIDATION_RESULT=FAIL':'"validation_result":"FAIL"',$out);} });
		$this->test('055_cli_only_public_commands', function () { foreach(array('inspect','plan-registration')as$c)$this->same($c,ClinicalPackageCli::parse(array('tool',$c,'--package-root='.$this->acceptedRoot))['command']);foreach(array('register','apply','import','resume','rollback')as$c)$this->expectCode(function () use ($c) { ClinicalPackageCli::parse(array('tool',$c,'--package-root=x')); },'invalid_command'); });
		$this->test('056_unknown_option_fails_closed', function () { $this->expectCode(function () { ClinicalPackageCli::parse(array('tool','inspect','--package-root=x','--write=true')); },'unknown_option'); });
		$this->test('057_database_not_requested_when_environment_absent', function () { $this->same(false,MysqliClinicalRegistrationStateRepository::requested(array_values($this->config['database_environment_variables']))); });
		$this->test('058_repository_interface_has_no_write_method', function () { $methods=get_class_methods('ClinicalRegistrationStateRepository');sort($methods);$this->same(array('close','fetchGraph'),$methods); });
		$this->test('059_production_sql_literals_select_only', function () { $source=file_get_contents(dirname(__DIR__).'/ClinicalRegistrationStateRepository.php');$this->same(0,preg_match('/[\'\"]\s*(?:INSERT|UPDATE|DELETE|REPLACE|LOAD\s+DATA|ALTER|CREATE|DROP|TRUNCATE|CALL)\b/i',$source)); });
		$this->test('060_production_source_has_no_package_path_hardcode', function () { foreach(glob(dirname(__DIR__).'/*.php')as$f)$this->same(false,strpos(file_get_contents($f),'database/master_data/clinical/v1')!==false); });
		$this->test('061_read_only_privilege_evidence_passes', function () { $this->same(true,MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($this->readOnlyPrivilegeEvidence())); });
		$this->test('062_direct_write_privilege_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['privileges'][]=array('privilege_surface'=>'global','privilege_type'=>'INSERT','is_grantable'=>'NO');$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('063_column_write_privilege_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['privileges'][]=array('privilege_surface'=>'column','privilege_type'=>'UPDATE','is_grantable'=>'NO');$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('064_routine_execute_privilege_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['privileges'][]=array('privilege_surface'=>'routine','privilege_type'=>'EXECUTE','is_grantable'=>'NO');$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('064a_procedure_execute_grant_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT EXECUTE ON PROCEDURE `clinical`.`probe_proc` TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('064b_function_execute_grant_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT EXECUTE ON FUNCTION `clinical`.`probe_func` TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('064c_column_update_grant_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT UPDATE (`value_text`) ON `clinical`.`probe_table` TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('064d_select_plus_column_update_grant_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT SELECT, UPDATE (`value_text`) ON `clinical`.`probe_table` TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('065_active_role_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['active_roles'][]=array('role_name'=>'writer');$e['current_role']='writer';$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_active_role_rejected'); });
		$this->test('066_proxy_identity_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['current_user']='proxied@%';$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_proxy_identity_rejected'); });
		$this->test('067_proxy_grant_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT PROXY ON 'writer'@'%' TO 'reader'@'%'";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('068_missing_select_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['privileges']=array(array('privilege_surface'=>'global','privilege_type'=>'USAGE','is_grantable'=>'NO'));$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_select_privilege_missing'); });
		$this->test('069_uninterpretable_privilege_metadata_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();unset($e['privileges'][0]['is_grantable']);$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_privilege_metadata_unavailable'); });
		$this->test('070_grant_option_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][1].=' WITH GRANT OPTION';$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('070a_malformed_no_on_grant_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT arbitrary words TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_privilege_metadata_unavailable'); });
		$this->test('070b_inactive_reconciled_role_grant_accepted', function () { $e=$this->readOnlyPrivilegeEvidence();$e['applicable_roles'][]=array('role_name'=>'reviewer','is_grantable'=>'NO','is_default'=>'NO');$e['grant_statements'][]="GRANT `reviewer` TO `reader`@`%`";$this->same(true,MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e)); });
		$this->test('070c_unknown_role_grant_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT `unknown_role` TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_privilege_metadata_unavailable'); });
		$this->test('070d_default_role_metadata_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['applicable_roles'][]=array('role_name'=>'reviewer','is_grantable'=>'NO','is_default'=>'YES');$e['grant_statements'][]="GRANT `reviewer` TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_active_role_rejected'); });
		$this->test('070e_select_only_grant_shapes_pass', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements']=array("GRANT USAGE ON *.* TO `reader`@`%` IDENTIFIED BY PASSWORD '*0123456789012345678901234567890123456789'","GRANT SELECT, SHOW VIEW ON `clinical`.* TO `reader`@`%`","GRANT SELECT ON `clinical`.`table_name` TO `reader`@`%`");$this->same(true,MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e)); });
		$this->test('070f_unknown_grant_privilege_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['grant_statements'][]="GRANT MADE_UP_PRIVILEGE ON `clinical`.* TO `reader`@`%`";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('070g_grantable_role_metadata_rejected', function () { $e=$this->readOnlyPrivilegeEvidence();$e['applicable_roles'][]=array('role_name'=>'reviewer','is_grantable'=>'YES','is_default'=>'NO');$e['grant_statements'][]="GRANT `reviewer` TO `reader`@`%` WITH ADMIN OPTION";$this->expectCode(function()use($e){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($e);},'database_not_read_only'); });
		$this->test('070h_object_grant_missing_to_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `clinical`.*",'database_privilege_metadata_unavailable'); });
		$this->test('070i_object_grant_garbage_tail_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `clinical`.* GARBAGE",'database_privilege_metadata_unavailable'); });
		$this->test('070j_object_grant_empty_recipient_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `clinical`.* TO",'database_privilege_metadata_unavailable'); });
		$this->test('070k_object_grant_empty_object_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070l_object_grant_empty_privilege_rejected', function () { $this->assertGrantRejected("GRANT ON `clinical`.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070m_object_grant_unknown_suffix_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `clinical`.* TO `reader`@`%` UNKNOWN CLAUSE",'database_privilege_metadata_unavailable'); });
		$this->test('070n_appended_second_grant_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `clinical`.* TO `reader`@`%`; GRANT UPDATE ON `clinical`.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070o_comment_bearing_grant_rejected', function () { $this->assertGrantRejected("GRANT SELECT /* comment */ ON `clinical`.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070p_unmatched_parenthesis_rejected', function () { $this->assertGrantRejected("GRANT SELECT (`a`, `b` ON `clinical`.`table_name` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070q_unmatched_backtick_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `clinical`.* TO `reader`@`%",'database_privilege_metadata_unavailable'); });
		$this->test('070r_one_column_select_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`a`) ON `clinical`.`table_name` TO `reader`@`%`")); });
		$this->test('070s_two_column_select_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`a`, `b`) ON `clinical`.`table_name` TO `reader`@`%`")); });
		$this->test('070t_column_select_plus_show_view_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`a`, `b`), SHOW VIEW ON `clinical`.`table_name` TO `reader`@`%`")); });
		$this->test('070u_multi_column_update_rejected', function () { $this->assertGrantRejected("GRANT UPDATE (`a`, `b`) ON `clinical`.`table_name` TO `reader`@`%`",'database_not_read_only'); });
		$this->test('070v_select_plus_column_update_rejected', function () { $this->assertGrantRejected("GRANT SELECT (`a`), UPDATE (`b`) ON `clinical`.`table_name` TO `reader`@`%`",'database_not_read_only'); });
		$this->test('070w_procedure_execute_full_statement_rejected', function () { $this->assertGrantRejected("GRANT EXECUTE ON PROCEDURE `clinical`.`probe_proc` TO `reader`@`%`",'database_not_read_only'); });
		$this->test('070x_function_execute_full_statement_rejected', function () { $this->assertGrantRejected("GRANT EXECUTE ON FUNCTION `clinical`.`probe_func` TO `reader`@`%`",'database_not_read_only'); });
		$this->test('070y_all_privileges_rejected', function () { $this->assertGrantRejected("GRANT ALL PRIVILEGES ON `clinical`.* TO `reader`@`%`",'database_not_read_only'); });
		$this->test('070z_schema_select_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `clinical`.* TO `reader`@`%`")); });
		$this->test('070za_usage_password_hash_accepted', function () { $this->assertGrantAccepted(array("GRANT USAGE ON *.* TO `reader`@`%` IDENTIFIED BY PASSWORD '*0123456789012345678901234567890123456789'")); });
		$this->test('070zb_reconciled_inactive_role_accepted', function () { $this->assertGrantAccepted(array("GRANT `reviewer` TO `reader`@`%`"),array(array('role_name'=>'reviewer','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zc_unknown_role_full_statement_rejected', function () { $this->assertGrantRejected("GRANT `unknown_role` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zd_duplicate_role_rejected', function () { $this->assertGrantRejected("GRANT `reviewer`, `reviewer` TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'reviewer','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070ze_role_admin_option_rejected', function () { $this->assertGrantRejected("GRANT `reviewer` TO `reader`@`%` WITH ADMIN OPTION",'database_not_read_only',array(array('role_name'=>'reviewer','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zf_target_account_mismatch_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `clinical`.* TO `other_reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zg_select_on_procedure_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON PROCEDURE `clinical`.`p` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zh_select_on_function_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON FUNCTION `clinical`.`f` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zi_show_view_on_procedure_scope_rejected', function () { $this->assertGrantRejected("GRANT SHOW VIEW ON PROCEDURE `clinical`.`p` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zj_show_view_on_function_scope_rejected', function () { $this->assertGrantRejected("GRANT SHOW VIEW ON FUNCTION `clinical`.`f` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zk_usage_on_procedure_scope_rejected', function () { $this->assertGrantRejected("GRANT USAGE ON PROCEDURE `clinical`.`p` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zl_column_select_on_global_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT (`a`) ON *.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zm_column_select_on_schema_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT (`a`) ON `clinical`.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zn_three_component_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON a.b.c TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zo_empty_middle_scope_component_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON a..b TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zp_empty_leading_scope_component_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON .b TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zq_empty_trailing_scope_component_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON a. TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zr_leading_hyphen_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON -bad.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zs_unquoted_hyphen_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON a-b.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zt_slash_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON a/b.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zu_backslash_scope_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON a\\b.* TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zv_execute_on_procedure_scope_rejected_as_authority', function () { $this->assertGrantRejected("GRANT EXECUTE ON PROCEDURE `clinical`.`p` TO `reader`@`%`",'database_not_read_only'); });
		$this->test('070zw_execute_on_function_scope_rejected_as_authority', function () { $this->assertGrantRejected("GRANT EXECUTE ON FUNCTION `clinical`.`f` TO `reader`@`%`",'database_not_read_only'); });
		$this->test('070zx_schema_select_scope_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `clinical`.* TO `reader`@`%`")); });
		$this->test('070zy_table_select_scope_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `clinical`.`terms` TO `reader`@`%`")); });
		$this->test('070zz_one_column_table_select_scope_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`a`) ON `clinical`.`terms` TO `reader`@`%`")); });
		$this->test('070zza_multi_column_table_select_scope_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`a`, `b`) ON `clinical`.`terms` TO `reader`@`%`")); });
		$this->test('070zzb_schema_show_view_scope_accepted', function () { $this->assertGrantAccepted(array("GRANT SHOW VIEW ON `clinical`.* TO `reader`@`%`")); });
		$this->test('070zzc_table_show_view_scope_accepted', function () { $this->assertGrantAccepted(array("GRANT SHOW VIEW ON `clinical`.`terms` TO `reader`@`%`")); });
		$this->test('070zzd_global_usage_scope_accepted', function () { $this->assertGrantAccepted(array("GRANT USAGE ON *.* TO `reader`@`%`")); });
		$this->test('070zze_quoted_special_scope_identifiers_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `clinical-prod`.`term.name``archive` TO `reader`@`%`")); });
		$this->test('070zzf_r5_unquoted_hyphen_column_rejected', function () { $this->assertGrantRejected("GRANT SELECT (a-b) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzg_r5_unquoted_dotted_column_rejected', function () { $this->assertGrantRejected("GRANT SELECT (a.b) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzh_r5_unquoted_numeric_column_rejected', function () { $this->assertGrantRejected("GRANT SELECT (123) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzi_r5_unquoted_exponent_column_rejected', function () { $this->assertGrantRejected("GRANT SELECT (5e6) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzj_r5_quoted_hyphen_column_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`a-b`) ON db.tbl TO `reader`@`%`")); });
		$this->test('070zzk_r5_quoted_dotted_column_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`a.b`) ON db.tbl TO `reader`@`%`")); });
		$this->test('070zzl_r5_quoted_numeric_column_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`123`) ON db.tbl TO `reader`@`%`")); });
		$this->test('070zzm_r5_quoted_exponent_column_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`5e6`) ON db.tbl TO `reader`@`%`")); });
		$this->test('070zzn_r5_duplicate_exact_column_rejected', function () { $this->assertGrantRejected("GRANT SELECT (a, a) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzo_r5_duplicate_ascii_case_column_rejected', function () { $this->assertGrantRejected("GRANT SELECT (`ColumnName`, `columnname`) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzp_r5_distinct_columns_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (column_a, column_b) ON db.tbl TO `reader`@`%`")); });
		$this->test('070zzq_r5_overlong_column_rejected', function () { $column=str_repeat('a',65);$this->assertGrantRejected("GRANT SELECT (`$column`) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzr_r5_supplementary_column_rejected', function () { $this->assertGrantRejected("GRANT SELECT (`column\u{1F600}`) ON db.tbl TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzs_r5_bmp_column_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT (`kolom\u{00F1}`) ON db.tbl TO `reader`@`%`")); });
		$this->test('070zzt_r5_numeric_database_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON 123.table_name TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzu_r5_numeric_table_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON database_name.123 TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzv_r5_exponent_database_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON 5e6.table_name TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzw_r5_exponent_table_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON database_name.9e TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzx_r5_quoted_numeric_database_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `123`.`table_name` TO `reader`@`%`")); });
		$this->test('070zzy_r5_quoted_numeric_table_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `database_name`.`456` TO `reader`@`%`")); });
		$this->test('070zzz_r5_quoted_hyphen_objects_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `database-name`.`table-name` TO `reader`@`%`")); });
		$this->test('070zzza_r5_quoted_dotted_objects_accepted', function () { $this->assertGrantAccepted(array("GRANT SELECT ON `database.name`.`table.name` TO `reader`@`%`")); });
		$this->test('070zzzb_r5_overlong_database_rejected', function () { $database=str_repeat('a',65);$this->assertGrantRejected("GRANT SELECT ON `$database`.`table_name` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzzc_r5_overlong_table_rejected', function () { $table=str_repeat('a',65);$this->assertGrantRejected("GRANT SELECT ON `database_name`.`$table` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzzd_r5_supplementary_object_rejected', function () { $this->assertGrantRejected("GRANT SELECT ON `database\u{1F600}`.`table_name` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzze_r5_unquoted_hyphen_role_rejected', function () { $this->assertGrantRejected("GRANT bad-role TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'bad-role','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzf_r5_unquoted_dotted_role_rejected', function () { $this->assertGrantRejected("GRANT bad.role TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'bad.role','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzg_r5_unquoted_numeric_role_rejected', function () { $this->assertGrantRejected("GRANT 123 TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'123','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzh_r5_unquoted_exponent_role_rejected', function () { $this->assertGrantRejected("GRANT 5e6 TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'5e6','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzi_r5_quoted_hyphen_role_accepted', function () { $this->assertGrantAccepted(array("GRANT `bad-role` TO `reader`@`%`"),array(array('role_name'=>'bad-role','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzj_r5_quoted_dotted_role_accepted', function () { $this->assertGrantAccepted(array("GRANT `bad.role` TO `reader`@`%`"),array(array('role_name'=>'bad.role','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzk_r5_quoted_numeric_role_accepted', function () { $this->assertGrantAccepted(array("GRANT `123` TO `reader`@`%`"),array(array('role_name'=>'123','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzl_r5_unknown_quoted_role_rejected', function () { $this->assertGrantRejected("GRANT `unknown-role` TO `reader`@`%`",'database_privilege_metadata_unavailable'); });
		$this->test('070zzzm_r5_duplicate_role_rejected', function () { $this->assertGrantRejected("GRANT `role_name`, `role_name` TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'role_name','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzn_r5_overlong_role_rejected', function () { $role=str_repeat('r',129);$this->assertGrantRejected("GRANT `$role` TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>$role,'is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzo_r5_public_role_rejected', function () { $this->assertGrantRejected("GRANT `PUBLIC` TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'PUBLIC','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('070zzzp_r5_none_role_rejected', function () { $this->assertGrantRejected("GRANT NONE TO `reader`@`%`",'database_privilege_metadata_unavailable',array(array('role_name'=>'NONE','is_grantable'=>'NO','is_default'=>'NO'))); });
		$this->test('071_online_comparison_starts_read_only_transaction', function () { $source=file_get_contents(dirname(__DIR__).'/ClinicalRegistrationStateRepository.php');$this->contains("START TRANSACTION READ ONLY",$source);foreach(array('USER_PRIVILEGES','SCHEMA_PRIVILEGES','TABLE_PRIVILEGES','COLUMN_PRIVILEGES','ENABLED_ROLES','APPLICABLE_ROLES','SHOW GRANTS FOR CURRENT_USER')as$surface)$this->contains($surface,$source);$this->same(false,strpos($source,'ROUTINE_PRIVILEGES')!==false);$this->contains('WHERE role_name IS NOT NULL',$source);$this->contains('$roleGrantee = $parts[0]',$source);$this->contains('array($roleGrantee)',$source);$this->contains('CURRENT_USER() AS effective_user',$source);$this->contains('CURRENT_ROLE() AS effective_role',$source);$this->same(false,strpos($source,'CURRENT_USER() AS current_user')!==false);$this->same(false,strpos($source,'CURRENT_ROLE() AS current_role')!==false); });

		$this->test('072_failure_format_discovery_contract', function () { $c='ClinicalPackageCli';$this->same('text',$c::discoverFailureFormat(array('tool','inspect')));$this->same('json',$c::discoverFailureFormat(array('tool','inspect','--format=json')));$this->same('text',$c::discoverFailureFormat(array('tool','inspect','--format=text')));$this->same('text',$c::discoverFailureFormat(array('tool','inspect','--format=json','--format=json')));$this->same('text',$c::discoverFailureFormat(array('tool','inspect','--format=')));$this->same('text',$c::discoverFailureFormat(array('tool','inspect','--format=yaml')));$this->same('text',$c::discoverFailureFormat(array('tool','inspect','--formatting=json')));$this->same('json',$c::discoverFailureFormat(array('tool','inspect','--format=json','--formatting=text')));$this->same('text',$c::discoverFailureFormat(array('tool','inspect','--package-root=--format=json'))); });
		$this->test('072a_unrelated_format_prefix_preserves_valid_json', function () { $this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--format=json','--formatting=text'),'json','unknown_option'); });
		$this->test('073_subprocess_invalid_command_json', function () { $this->assertProductionFailure(array('invalid-command','--package-root='.$this->acceptedRoot,'--format=json'),'json','invalid_command'); });
		$this->test('074_subprocess_missing_package_root_json', function () { $this->assertProductionFailure(array('inspect','--format=json'),'json','missing_package_root'); });
		$this->test('075_subprocess_unknown_option_json', function () { $this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--format=json','--write=true'),'json','unknown_option'); });
		$this->test('076_subprocess_malformed_unrelated_option_json', function () { $this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--format=json','--unknown'),'json','malformed_option'); });
		$this->test('077_subprocess_duplicate_package_root_json', function () { $this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--package-root='.$this->acceptedRoot,'--format=json'),'json','duplicate_option'); });
		$this->test('078_subprocess_invalid_command_text', function () { $this->assertProductionFailure(array('invalid-command','--package-root='.$this->acceptedRoot,'--format=text'),'text','invalid_command'); });
		$this->test('079_subprocess_missing_package_root_text', function () { $this->assertProductionFailure(array('inspect','--format=text'),'text','missing_package_root'); });
		$this->test('080_subprocess_unsupported_format_falls_back_text', function () { $this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--format=yaml'),'text','unsupported_format'); });
		$this->test('081_subprocess_duplicate_format_falls_back_text', function () { $this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--format=json','--format=json'),'text','duplicate_option'); });
		$this->test('082_subprocess_empty_format_falls_back_text', function () { $this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--format='),'text','malformed_option'); });
		$this->test('083_subprocess_package_validation_failure_json', function () { $this->mutateManifest(function(&$m){$m['package_status']='unsupported';});putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);try{$this->assertProductionFailure(array('inspect','--package-root='.$this->fixtureRoot,'--format=json'),'json');}finally{putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent);} });
		$this->test('084_subprocess_unexpected_warning_boundary', function () { $old=getenv($this->config['allowed_roots_environment']);$prefix=DIRECTORY_SEPARATOR==='\\'?'C:\\':'/';putenv($this->config['allowed_roots_environment'].'='.$prefix.str_repeat('x',5000));try{$this->assertProductionFailure(array('inspect','--package-root='.$this->acceptedRoot,'--format=json'),'json',null,array($this->acceptedRoot,dirname(__DIR__)));}finally{if($old===false)putenv($this->config['allowed_roots_environment']);else putenv($this->config['allowed_roots_environment'].'='.$old);} });
		$this->test('085_subprocess_unreadable_nested_directory_where_supported', function () { $this->permissionFailureSubprocess('directory'); });
		$this->test('086_subprocess_unreadable_json_where_supported', function () { $this->permissionFailureSubprocess('json'); });
		$this->test('087_subprocess_unreadable_regular_hash_input_where_supported', function () { $this->permissionFailureSubprocess('regular'); });
		$this->test('088_established_validator_dependency_unavailable_is_safe', function () { $loader=new ClinicalPackageLoader(new ClinicalPackagePathPolicy($this->config['allowed_roots_environment']),new StrictJsonDecoder(),$this->fixtureParent.'/missing-validator.php',$this->fixtureParent.'/missing-rules.php');$this->expectCode(function()use($loader){$loader->load($this->acceptedRoot);},'established_validator_dependency_unavailable'); });
		$this->test('089_warning_boundary_and_safe_wrappers_present', function () { $entry=file_get_contents(dirname(__DIR__).'/clinical_package.php');$this->contains('set_error_handler',$entry);$this->contains('restore_error_handler',$entry);foreach(array('scandir','filetype','filesize')as$call)$this->contains('@'.$call,file_get_contents(dirname(__DIR__).'/ClinicalPackagePathPolicy.php'));$this->contains('@hash_file',file_get_contents(dirname(__DIR__).'/ClinicalPackageLoader.php'));$this->contains('@file_get_contents',file_get_contents(dirname(__DIR__).'/StrictJsonDecoder.php')); });

		$this->test('090_registration_contract_matrix_complete', function () { $this->same(55,$this->contractValidator->fieldContractCount());$contracts=$this->contractValidator->fieldContracts();foreach($contracts as$c)foreach(array('entity','field','database_type','charset','collation','max_length_or_range','nullable','enum_or_check_values','source','validation_rule','safe_error_code')as$key)$this->same(true,array_key_exists($key,$c));$this->contractValidator->validate($this->model); });
		$this->test('091_contract_overlong_package_version_rejected', function () { $this->contractMutation('package','package_version',str_repeat('v',65)); });
		$this->test('092_contract_overlong_manifest_schema_version_rejected', function () { $this->contractMutation('package','manifest_schema_version',str_repeat('1',33)); });
		$this->test('093_contract_invalid_package_source_status_rejected', function () { $this->contractMutation('package','source_status','unknown'); });
		$this->test('094_contract_invalid_package_license_rejected', function () { $this->contractMutation('package','license_disposition','unknown'); });
		$this->test('095_contract_overlong_dataset_key_rejected', function () { $this->contractMutation('dataset','dataset_key','D'.str_repeat('A',128)); });
		$this->test('096_contract_overlong_dataset_version_rejected', function () { $this->contractMutation('dataset','dataset_version',str_repeat('v',65)); });
		$this->test('097_contract_overlong_domain_key_rejected', function () { $this->contractMutation('dataset','domain_key','d'.str_repeat('a',64)); });
		$this->test('098_contract_overlong_entity_key_rejected', function () { $this->contractMutation('dataset','entity_key','e'.str_repeat('a',64)); });
		$this->test('099_contract_non_ascii_source_governance_rejected', function () { $this->contractMutation('dataset','source_governance_status','draft-ñ'); });
		$this->test('100_contract_overlong_source_governance_rejected', function () { $this->contractMutation('dataset','source_governance_status',str_repeat('a',129)); });
		$this->test('101_contract_invalid_dataset_license_rejected', function () { $this->contractMutation('dataset','license_disposition','unknown'); });
		$this->test('102_contract_negative_record_count_rejected', function () { $this->contractMutation('dataset','observed_record_count',-1); });
		$this->test('103_contract_overflowing_seed_order_rejected', function () { $this->contractMutation('dataset','seed_order',65536); });
		$this->test('104_contract_overlong_natural_key_rejected', function () { $this->contractMutation('dataset','natural_key_contract',str_repeat('x',256)); });
		$this->test('105_contract_invalid_capability_rejected', function () { $this->contractMutation('package_capability','capability','unknown'); });
		$this->test('106_contract_invalid_decision_status_rejected', function () { $this->contractMutation('package_capability','decision_status','unknown'); });
		$this->test('107_contract_overlong_decision_reason_rejected', function () { $this->contractMutation('package_capability','decision_reason',str_repeat('x',1001)); });
		$this->test('108_contract_invalid_source_json_type_rejected', function () { $this->contractMutation('field_contract','source_json_type','unknown'); });
		$this->test('109_contract_invalid_cardinality_rejected', function () { $this->contractMutation('field_contract','cardinality','unknown'); });
		$this->test('110_contract_invalid_identity_role_rejected', function () { $this->contractMutation('field_contract','identity_role','unknown'); });
		$this->test('111_contract_invalid_transform_policy_rejected', function () { $this->contractMutation('field_contract','transform_policy','unknown'); });
		$this->test('112_contract_invalid_contract_status_rejected', function () { $this->contractMutation('field_contract','contract_status','unknown'); });
		$this->test('113_contract_invalid_nullable_target_representation_rejected', function () { $this->contractMutation('field_contract','target_domain',123); });
		$this->test('114_contract_ascii_capacity_boundaries_accepted', function () { $m=$this->model;$m['package']['persisted']['package_version']='v'.str_repeat('a',63);$m['package']['persisted']['manifest_schema_version']=str_repeat('1',32);$m['datasets'][0]['persisted']['dataset_key']='D'.str_repeat('A',127);$m['datasets'][0]['persisted']['dataset_version']=str_repeat('v',64);$m['datasets'][0]['persisted']['domain_key']='d'.str_repeat('a',63);$m['datasets'][0]['persisted']['entity_key']='e'.str_repeat('a',63);$m['datasets'][0]['persisted']['source_governance_status']=str_repeat('s',128);$this->contractValidator->validate($m); });
		$this->test('115_contract_utf8_character_capacity_boundaries_accepted', function () { $m=$this->model;$m['package_capabilities'][0]['persisted']['decision_reason']=str_repeat('ñ',1000);$m['datasets'][0]['field_contracts'][0]['persisted']['review_reason']=str_repeat('ñ',1000);$m['datasets'][0]['persisted']['natural_key_contract']='["'.str_repeat('x',251).'"]';$m['datasets'][0]['persisted']['source_file']='a/'.str_repeat('ñ',253);$this->contractValidator->validate($m); });
		$this->test('116_contract_numeric_boundaries_accepted', function () { $m=$this->model;$m['package']['persisted']['declared_dataset_count']=65535;$m['package']['persisted']['observed_dataset_count']=65535;$m['package']['persisted']['declared_record_count']=9007199254740991;$m['package']['persisted']['observed_record_count']=9007199254740991;$m['datasets'][0]['persisted']['seed_order']=65535;$m['datasets'][0]['persisted']['declared_record_count']=9007199254740991;$m['datasets'][0]['persisted']['observed_record_count']=9007199254740991;$this->contractValidator->validate($m); });
		$this->test('117_contract_unknown_persisted_field_rejected', function () { $m=$this->model;$m['package']['persisted']['unvalidated']=true;$this->expectCode(function()use($m){$this->contractValidator->validate($m);},'registration_contract_unvalidated_field'); });
		$this->test('118_accepted_package_checksum_gate', function () { $this->same('fa512de0909e5e1f4fe6d394ca3b8103f5cefd98389f39ae016c4438e992fc34',$this->model['package_checksum']); });
		$this->test('119_nullable_manifest_schema_version_comparison', function () { $this->assertNullableComparisonCombinations('package','manifest_schema_version','1.0'); });
		$this->test('120_nullable_source_reference_comparison', function () { $this->assertNullableComparisonCombinations('package','source_reference','00_manifest.json'); });
		$this->test('121_nullable_entity_key_comparison', function () { $this->assertNullableComparisonCombinations('dataset','entity_key','entity'); });
		$this->test('122_nullable_natural_key_contract_comparison', function () { $this->assertNullableComparisonCombinations('dataset','natural_key_contract','["kode"]'); });
		$this->test('123_all_nullable_persisted_text_uses_nullable_comparison', function () {
			$method=new ReflectionMethod(new ClinicalRegistrationPlanner(),'comparisonFieldTypes');
			$types=$method->invoke(new ClinicalRegistrationPlanner());$nullableCount=0;
			foreach($this->contractValidator->fieldContracts()as$contract){
				$isText=preg_match('/^(?:VAR)?CHAR\(|^TEXT|^DATETIME\(/',$contract['database_type'])===1;
				if(!$contract['nullable']||!$isText)continue;$nullableCount++;
				$this->same('nullable_string',$types[$contract['entity']][$contract['field']]);
			}
			$this->same(16,$nullableCount);
		});
		$this->test('124_datetime6_invalid_calendar_and_format_values_rejected', function () {
			$invalid=array(
				'2026-99-99 99:99:99.999999','2026-02-30 12:00:00.000000','2025-02-29 12:00:00.000000',
				'2026-00-01 12:00:00.000000','2026-01-00 12:00:00.000000','2026-01-01 24:00:00.000000',
				'2026-01-01 12:60:00.000000','2026-01-01 12:00:60.000000','2026-01-01 12:00:00',
				'2026-01-01 12:00:00.00000','2026-01-01 12:00:00.0000000','2026-01-01 12:00:00.000000Z',
				'0999-12-31 23:59:59.999999',
			);
			foreach($invalid as$value)$this->expectCode(function()use($value){$this->validateContractValue('package_capability','decided_at',$value);},'registration_contract_package_capability_decided_at_invalid');
		});
		$this->test('125_datetime6_valid_values_and_boundaries_accepted', function () {
			foreach(array('2026-07-22 12:34:56.123456','2024-02-29 23:59:59.999999','1000-01-01 00:00:00.000000','9999-12-31 23:59:59.999999')as$value)$this->validateContractValue('package_capability','decided_at',$value);
			$this->validateContractValue('package_capability','decided_at',null);
		});
		$this->test('126_null_rejected_for_nonnullable_contract_field', function () { $this->expectCode(function(){ $this->validateContractValue('package','package_version',null); },'registration_contract_package_package_version_invalid'); });
		$this->test('127_source_reference_strict_relative_path_rejections', function () {
			$invalid=array('', '   ', "\u{00A0}", ' 00_manifest.json', '00_manifest.json ', "\u{00A0}00_manifest.json", "00_manifest\n.json",
				'../outside.json','a/../../outside.json','./00_manifest.json','a/./b.json','/absolute.json',
				'C:/absolute.json','C:\\absolute.json','\\\\server\\share\\file.json','https://example.invalid/file.json','a//b.json','a\\b.json');
			foreach($invalid as$value)$this->expectCode(function()use($value){$this->validateContractValue('package','source_reference',$value);},'registration_contract_package_source_reference_invalid');
		});
		$this->test('128_source_reference_accepted_value_and_null_preserved', function () { $this->validateContractValue('package','source_reference','00_manifest.json');$this->validateContractValue('package','source_reference',null);$this->same('00_manifest.json',$this->model['package']['persisted']['source_reference']); });

		$this->test('129_register_metadata_cli_defaults_to_dry_run', function () {
			$parsed=ClinicalPackageCli::parse(array('clinical_package.php','register-metadata','--package-root='.$this->acceptedRoot,'--format=json'));
			$this->same(false,$parsed['options']['apply']);$this->same(null,$parsed['options']['registration_reference']);
			$this->expectCode(function(){ClinicalPackageCli::parse(array('clinical_package.php','register-metadata','--package-root='.$this->acceptedRoot,'--apply=true'));},'malformed_option');
		});
		$this->test('130_apply_requires_write_enabled_environment', function () {
			$options=$this->writerOptions(true);putenv($this->config['write_database_environment_variables']['database'].'=clinical_test');
			try{$this->expectCode(function()use($options){ClinicalRegistrationWriter::assertPreConnectionApplyGates($this->model,$options,$this->config);},'metadata_write_disabled');}
			finally{putenv($this->config['write_database_environment_variables']['database']);}
		});
		$this->test('131_apply_confirmation_gates_fail_closed', function () {
			$options=$this->writerOptions(true);
			putenv($this->config['metadata_write_enabled_environment'].'=true');putenv($this->config['write_database_environment_variables']['database'].'=clinical_test');
			try{
				foreach(array(
					array('confirm_database','wrong','database_confirmation_mismatch'),
					array('confirm_package_checksum',str_repeat('0',64),'package_checksum_confirmation_mismatch'),
					array('confirm_package_snapshot',str_repeat('0',64),'package_snapshot_confirmation_mismatch'),
					array('confirm_metadata_entity_count','889','metadata_entity_count_confirmation_mismatch'),
					array('registration_reference','bad/reference','registration_reference_invalid'),
				)as$case){$changed=$options;$changed[$case[0]]=$case[1];$this->expectCode(function()use($changed){ClinicalRegistrationWriter::assertPreConnectionApplyGates($this->model,$changed,$this->config);},$case[2]);}
				$missing=$options;$missing['registration_reference']=null;$this->expectCode(function()use($missing){ClinicalRegistrationWriter::assertPreConnectionApplyGates($this->model,$missing,$this->config);},'apply_confirmation_missing');
			}finally{putenv($this->config['metadata_write_enabled_environment']);putenv($this->config['write_database_environment_variables']['database']);}
		});
		$this->test('132_dry_run_performs_zero_writes', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$result=$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(false),10);
			$this->same('new',$result['registration_result']);$this->same(890,$result['planned_metadata_rows']);$this->same(0,$result['existing_metadata_rows']);$this->same(false,$result['write_executed']);$this->same(array(),$repo->operations);$this->same(array(),$repo->audits);
		});
		$this->test('133_new_registration_writes_exact_graph_and_committed_audit', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$result=$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);
			$this->same('committed',$result['registration_result']);$this->same(890,$result['metadata_rows_written']);$this->same(1,$result['audit_rows_written']);$this->same(true,$result['transaction_committed']);
			$this->same(array(1,47,8,376,458),$this->graphCounts($repo->graph));$this->same(1,count($repo->audits));$this->same('committed',$repo->audits[0]['result']);$this->same(890,$repo->audits[0]['metadata_rows_inserted']);
		});
		$this->test('134_exact_match_is_idempotent_metadata_noop', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->exactGraph());$result=$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);
			$this->same('idempotent_noop',$result['registration_result']);$this->same(0,$result['metadata_rows_written']);$this->same(1,$result['audit_rows_written']);$this->same(array(1,47,8,376,458),$this->graphCounts($repo->graph));$this->same('idempotent_noop',$repo->audits[0]['result']);
		});
		$this->test('135_package_level_conflict_rejected_without_metadata_mutation', function () { $g=$this->exactGraph();$g['package_identity_rows'][0]['source_status']='approved';$this->assertWriterConflict($g); });
		$this->test('136_dataset_level_conflict_rejected_without_metadata_mutation', function () { $g=$this->exactGraph();$g['datasets'][0]['domain_key']='conflict';$this->assertWriterConflict($g); });
		$this->test('137_capability_level_conflict_rejected_without_metadata_mutation', function () { $g=$this->exactGraph();$g['dataset_capabilities'][0]['decision_status']='pending';$this->assertWriterConflict($g); });
		$this->test('138_field_contract_level_conflict_rejected_without_metadata_mutation', function () { $g=$this->exactGraph();$g['field_contracts'][0]['contract_status']='rejected';$this->assertWriterConflict($g); });
		$this->test('139_duplicate_logical_identity_is_conflict', function () { $g=$this->exactGraph();$g['datasets'][]=$g['datasets'][0];$this->assertWriterConflict($g); });
		$this->test('140_failure_in_each_entity_phase_rolls_back_all_metadata', function () {
			foreach(array('after_package','after_package_capabilities','after_datasets','after_dataset_capabilities','after_field_contracts')as$point){
				$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$writer=$this->makeWriter($repo,function($actual)use($point){if($actual===$point)throw new RuntimeException('injected');});
				try{$writer->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);throw new RuntimeException('injection not reached');}
				catch(ClinicalPackageException $e){$this->same('registration_transaction_failed',$e->getSafeCode());}
				$this->same(array(0,0,0,0,0),$this->graphCounts($repo->graph));$this->same(1,count($repo->audits));$this->same('rolled_back',$repo->audits[0]['result']);
			}
		});
		$this->test('141_mid_write_failure_leaves_zero_partial_rows', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$writer=$this->makeWriter($repo,function($point){if($point==='field_contract_midpoint')throw new RuntimeException('injected');});
			try{$writer->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);}catch(ClinicalPackageException $e){$this->same('registration_transaction_failed',$e->getSafeCode());}
			$this->same(array(0,0,0,0,0),$this->graphCounts($repo->graph));$this->same('rolled_back',$repo->audits[0]['result']);
		});
		$this->test('142_failed_rollback_audit_preserves_primary_error', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$repo->failAudit=true;$writer=$this->makeWriter($repo,function($point){if($point==='after_package')throw new RuntimeException('injected');});
			try{$writer->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);throw new RuntimeException('failure expected');}
			catch(ClinicalPackageException $e){$this->same('registration_transaction_failed',$e->getSafeCode());$this->same('metadata_rollback_audit_failed',$e->getSafeContext()['secondary_error']);}
			$this->same(array(0,0,0,0,0),$this->graphCounts($repo->graph));$this->same(0,count($repo->audits));
		});
		$this->test('143_writer_privilege_allowlist_accepts_exact_authority', function () { $this->same(true,ClinicalRegistrationWritePrivilegeValidator::assertEvidence($this->writerPrivilegeEvidence())); });
		$this->test('144_invalid_privilege_set_is_rejected_before_audit', function () {
			$e=$this->writerPrivilegeEvidence();$e['privileges'][]=array('privilege_surface'=>'table','table_schema'=>'clinical_test','table_name'=>'clinical_master_packages','column_name'=>null,'privilege_type'=>'UPDATE','is_grantable'=>'NO');
			$this->expectCode(function()use($e){ClinicalRegistrationWritePrivilegeValidator::assertEvidence($e);},'database_writer_privilege_rejected');
		});
		$this->test('145_active_role_and_grant_option_rejected', function () {
			$e=$this->writerPrivilegeEvidence();$e['active_roles'][]=array('role_name'=>'writer_role');$this->expectCode(function()use($e){ClinicalRegistrationWritePrivilegeValidator::assertEvidence($e);},'database_active_role_rejected');
			$e=$this->writerPrivilegeEvidence();$e['privileges'][1]['is_grantable']='YES';$this->expectCode(function()use($e){ClinicalRegistrationWritePrivilegeValidator::assertEvidence($e);},'database_writer_privilege_rejected');
			$e=$this->writerPrivilegeEvidence();$e['grant_statements'][]=$e['grant_statements'][0];$this->expectCode(function()use($e){ClinicalRegistrationWritePrivilegeValidator::assertEvidence($e);},'database_privilege_metadata_unavailable');
		});
		$this->test('146_missing_select_or_insert_privilege_rejected', function () {
			foreach(array('SELECT','INSERT')as$missing){$e=$this->writerPrivilegeEvidence();foreach($e['privileges']as$i=>$row)if($row['table_name']==='clinical_master_packages'&&$row['privilege_type']===$missing){unset($e['privileges'][$i]);break;}$this->expectCode(function()use($e){ClinicalRegistrationWritePrivilegeValidator::assertEvidence($e);},$missing==='SELECT'?'database_select_privilege_missing':'database_insert_privilege_missing');}
		});
		$this->test('147_runtime_database_account_remains_hard_rejected', function () { $this->same(true,in_array('doclinc-staging-user',$this->config['hard_rejected_database_users'],true));$this->contains('doclinc-staging-user',file_get_contents(dirname(__DIR__).'/ClinicalRegistrationWriteRepository.php')); });
		$this->test('148_invalid_package_pin_and_checksum_perform_zero_writes', function () {
			$model=$this->model;$model['package_checksum']=str_repeat('0',64);$model['package']['persisted']['package_checksum']=$model['package_checksum'];$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());
			$this->expectCode(function()use($model,$repo){$this->makeWriter($repo)->execute($model,function()use($model){return $model;},$this->writerOptions(true),10);},'metadata_package_pin_mismatch');$this->same(array(),$repo->operations);
		});
		$this->test('149_wrong_metadata_total_and_reordered_shape_rejected', function () {
			$model=$this->model;$model['counts']['field_contracts']=457;$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$this->expectCode(function()use($model,$repo){$this->makeWriter($repo)->execute($model,function()use($model){return $model;},$this->writerOptions(true),10);},'metadata_entity_count_invalid');
			$model=array_reverse($this->model,true);$this->expectCode(function()use($model,$repo){$this->makeWriter($repo)->execute($model,function()use($model){return $model;},$this->writerOptions(true),10);},'metadata_plan_shape_invalid');
			$model=$this->model;$model['datasets'][0]['persisted']['unexpected']='x';$this->expectCode(function()use($model,$repo){$this->makeWriter($repo)->execute($model,function()use($model){return $model;},$this->writerOptions(true),10);},'metadata_plan_shape_invalid');
		});
		$this->test('150_audit_result_columns_are_consistent', function () {
			$new=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$this->makeWriter($new)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);$a=$new->audits[0];$this->same(array('new','exact_match','committed',890,0,1,null),array($a['state_before'],$a['state_after'],$a['result'],$a['metadata_rows_inserted'],$a['idempotent_noop'],$a['metadata_transaction_committed'],$a['safe_failure_code']));
			$exact=new FakeClinicalRegistrationWriteRepository($this->exactGraph());$this->makeWriter($exact)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);$a=$exact->audits[0];$this->same(array('exact_match','exact_match','idempotent_noop',0,1,1,null),array($a['state_before'],$a['state_after'],$a['result'],$a['metadata_rows_inserted'],$a['idempotent_noop'],$a['metadata_transaction_committed'],$a['safe_failure_code']));
		});
		$this->test('151_retry_after_rolled_back_attempt_commits', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$failed=$this->makeWriter($repo,function($point){if($point==='dataset_midpoint')throw new RuntimeException('injected');});try{$failed->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);}catch(ClinicalPackageException $ignored){}
			$result=$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);$this->same('committed',$result['registration_result']);$this->same(array(1,47,8,376,458),$this->graphCounts($repo->graph));$this->same(array('rolled_back','committed'),array_column($repo->audits,'result'));
		});
		$this->test('152_package_change_before_commit_rolls_back', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$calls=0;$changed=$this->model;$changed['package_snapshot_sha256']=str_repeat('0',64);
			$callback=function()use(&$calls,$changed){$calls++;return $calls>=3?$changed:$this->model;};
			try{$this->makeWriter($repo)->execute($this->model,$callback,$this->writerOptions(true),10);}catch(ClinicalPackageException $e){$this->same('package_changed_during_registration',$e->getSafeCode());}
			$this->same(array(0,0,0,0,0),$this->graphCounts($repo->graph));$this->same('rolled_back',$repo->audits[0]['result']);
		});
		$this->test('153_insert_sql_is_prepared_explicit_and_metadata_only', function () {
			$source=file_get_contents(dirname(__DIR__).'/ClinicalRegistrationWriteRepository.php');
			foreach(array('INSERT INTO clinical_master_packages (','INSERT INTO clinical_master_package_capabilities (','INSERT INTO clinical_master_datasets (','INSERT INTO clinical_master_dataset_capabilities (','INSERT INTO clinical_master_dataset_field_contracts (','INSERT INTO clinical_master_metadata_registration_events (')as$sql)$this->contains($sql,$source);
			$this->same(false,strpos($source,"query('INSERT")!==false);$this->contains('prepare($sql)',$source);$this->contains('bind_param',$source);$this->same(false,strpos($source,'INSERT IGNORE')!==false);$this->same(false,strpos($source,'ON DUPLICATE KEY')!==false);$this->same(false,strpos($source,'REPLACE INTO')!==false);
			$auditSql=substr($source,strpos($source,'INSERT INTO clinical_master_metadata_registration_events'),700);$this->same(false,strpos($auditSql,'metadata_registration_event_id')!==false);$this->same(false,strpos($auditSql,'occurred_at')!==false);
		});
		$this->test('154_package_files_remain_byte_for_byte_unchanged', function () { $before=$this->treeDigest($this->acceptedRoot);$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(false),10);$this->same($before,$this->treeDigest($this->acceptedRoot)); });
		$this->test('155_apply_existing_rows_are_recomputed_inside_lock', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$repo->graphOnLock=$this->exactGraph();
			$result=$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);
			$this->same('idempotent_noop',$result['registration_result']);$this->same(890,$result['existing_metadata_rows']);$this->same(0,$result['metadata_rows_written']);
		});
		$this->test('156_multiple_conflicts_report_exact_entity_count', function () {
			$graph=$this->exactGraph();
			$graph['package_identity_rows'][0]['source_status']='approved';
			$graph['datasets'][0]['domain_key']='conflict';
			$graph['dataset_capabilities'][0]['decision_status']='pending';
			$graph['field_contracts'][0]['contract_status']='rejected';
			$repo=new FakeClinicalRegistrationWriteRepository($graph);
			try{$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);throw new RuntimeException('conflict expected');}
			catch(ClinicalPackageException $e){$this->same('registration_state_conflict',$e->getSafeCode());$this->same(4,$e->getSafeContext()['conflict_count']);}
			$this->same('rejected',$repo->audits[0]['result']);
		});
		$this->test('157_lock_release_failure_preserves_committed_outcome', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$repo->failRelease=true;
			$result=$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);
			$this->same('committed',$result['registration_result']);$this->same(890,$result['metadata_rows_written']);$this->same(1,$result['audit_rows_written']);$this->same(true,$result['transaction_committed']);
			$this->same('connection_closed',$result['lock_cleanup_status']);$this->same('registration_lock_release_unconfirmed',$result['cleanup_warning']);
			$this->same(array(1,47,8,376,458),$this->graphCounts($repo->graph));$this->same('committed',$repo->audits[0]['result']);$this->same(true,$repo->connectionClosed);$this->same(false,$repo->lockOwned());
		});
		$this->test('158_lock_release_failure_preserves_idempotent_outcome', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->exactGraph());$repo->failRelease=true;
			$result=$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);
			$this->same('idempotent_noop',$result['registration_result']);$this->same(890,$result['existing_metadata_rows']);$this->same(0,$result['metadata_rows_written']);$this->same(1,$result['audit_rows_written']);
			$this->same('connection_closed',$result['lock_cleanup_status']);$this->same('idempotent_noop',$repo->audits[0]['result']);$this->same(false,$repo->lockOwned());
		});
		$this->test('159_lock_release_failure_preserves_rejected_primary_error', function () {
			$graph=$this->exactGraph();$graph['datasets'][0]['domain_key']='conflict';$repo=new FakeClinicalRegistrationWriteRepository($graph);$repo->failRelease=true;
			try{$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);throw new RuntimeException('conflict expected');}
			catch(ClinicalPackageException $e){$this->same('registration_state_conflict',$e->getSafeCode());$this->same('registration_lock_release_unconfirmed',$e->getSafeContext()['secondary_error']);$this->same('connection_closed',$e->getSafeContext()['lock_cleanup_status']);}
			$this->same('rejected',$repo->audits[0]['result']);$this->same(true,$repo->connectionClosed);$this->same(false,$repo->lockOwned());
		});
		$this->test('160_lock_release_failure_preserves_rolled_back_primary_error', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$repo->failRelease=true;
			$writer=$this->makeWriter($repo,function($point){if($point==='after_package')throw new RuntimeException('injected');});
			try{$writer->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);throw new RuntimeException('failure expected');}
			catch(ClinicalPackageException $e){$this->same('registration_transaction_failed',$e->getSafeCode());$this->same('registration_lock_release_unconfirmed',$e->getSafeContext()['secondary_error']);$this->same('connection_closed',$e->getSafeContext()['lock_cleanup_status']);}
			$this->same(array(0,0,0,0,0),$this->graphCounts($repo->graph));$this->same('rolled_back',$repo->audits[0]['result']);$this->same(false,$repo->lockOwned());
		});
		$this->test('161_precomparison_revalidation_failure_audits_unknown_state', function () {
			$repo=new FakeClinicalRegistrationWriteRepository($this->emptyGraph());$calls=0;
			$callback=function()use(&$calls){$calls++;if($calls===2)throw new ClinicalPackageException('package_revalidation_failed','revalidation');return $this->model;};
			try{$this->makeWriter($repo)->execute($this->model,$callback,$this->writerOptions(true),10);throw new RuntimeException('failure expected');}
			catch(ClinicalPackageException $e){$this->same('package_revalidation_failed',$e->getSafeCode());}
			$this->same('rolled_back',$repo->audits[0]['result']);$this->same(null,$repo->audits[0]['state_before']);$this->same(null,$repo->audits[0]['state_after']);
		});
		$this->test('162_exact_match_final_revalidation_change_rolls_back_noop', function () {
			$graph=$this->exactGraph();$before=hash('sha256',serialize($graph));$repo=new FakeClinicalRegistrationWriteRepository($graph);$calls=0;
			$changed=$this->model;$changed['package']['persisted']['source_status']='reviewed';
			$callback=function()use(&$calls,$changed){$calls++;return $calls===3?$changed:$this->model;};
			try{$this->makeWriter($repo)->execute($this->model,$callback,$this->writerOptions(true),10);throw new RuntimeException('failure expected');}
			catch(ClinicalPackageException $e){
				$this->same('package_changed_during_registration',$e->getSafeCode());$this->same(890,$e->getSafeContext()['existing_metadata_rows']);$this->same(0,$e->getSafeContext()['conflict_count']);
			}
			$this->same(3,$calls);$this->same($before,hash('sha256',serialize($repo->graph)));$this->same(array(1,47,8,376,458),$this->graphCounts($repo->graph));
			$this->same(1,count($repo->audits));$audit=$repo->audits[0];
			$this->same(array('exact_match',null,'rolled_back',0,0,'package_changed_during_registration'),array($audit['state_before'],$audit['state_after'],$audit['result'],$audit['metadata_rows_inserted'],$audit['metadata_transaction_committed'],$audit['safe_failure_code']));
			$this->same(0,count(array_filter($repo->audits,function($row){return $row['result']==='idempotent_noop';})));$this->same(false,$repo->lockOwned());$this->same('unlock',end($repo->operations));
		});
	}

	private function makeWriter(FakeClinicalRegistrationWriteRepository $repository,$injector=null)
	{
		return new ClinicalRegistrationWriter($repository,new ClinicalRegistrationPlanner(),$this->config['metadata_registration_pins'],$injector);
	}

	private function writerOptions($apply)
	{
		return array(
			'apply'=>$apply,
			'confirm_database'=>'clinical_test',
			'confirm_package_checksum'=>$this->model['package_checksum'],
			'confirm_package_snapshot'=>$this->model['package_snapshot_sha256'],
			'confirm_metadata_entity_count'=>'890',
			'registration_reference'=>'pass-2c1d1-i2-test',
		);
	}

	private function graphCounts(array $graph)
	{
		return array(count($graph['package_identity_rows']),count($graph['datasets']),count($graph['package_capabilities']),count($graph['dataset_capabilities']),count($graph['field_contracts']));
	}

	private function assertWriterConflict(array $graph)
	{
		$before=hash('sha256',serialize($graph));$repo=new FakeClinicalRegistrationWriteRepository($graph);
		try{$this->makeWriter($repo)->execute($this->model,function(){return $this->model;},$this->writerOptions(true),10);throw new RuntimeException('conflict expected');}
		catch(ClinicalPackageException $e){$this->same('registration_state_conflict',$e->getSafeCode());}
		$this->same($before,hash('sha256',serialize($repo->graph)));$this->same(1,count($repo->audits));$this->same('rejected',$repo->audits[0]['result']);$this->same(0,$repo->audits[0]['metadata_rows_inserted']);
	}

	private function writerPrivilegeEvidence()
	{
		$tables=array('clinical_master_packages','clinical_master_package_capabilities','clinical_master_datasets','clinical_master_dataset_capabilities','clinical_master_dataset_field_contracts');
		$privileges=array(array('privilege_surface'=>'global','table_schema'=>null,'table_name'=>null,'column_name'=>null,'privilege_type'=>'USAGE','is_grantable'=>'NO'));
		$grants=array("GRANT USAGE ON *.* TO `writer`@`%` IDENTIFIED BY PASSWORD '*0123456789ABCDEF0123456789ABCDEF01234567'");
		foreach($tables as$table){
			foreach(array('SELECT','INSERT')as$privilege)$privileges[]=array('privilege_surface'=>'table','table_schema'=>'clinical_test','table_name'=>$table,'column_name'=>null,'privilege_type'=>$privilege,'is_grantable'=>'NO');
			$grants[]="GRANT SELECT, INSERT ON `clinical_test`.`$table` TO `writer`@`%`";
		}
		$privileges[]=array('privilege_surface'=>'table','table_schema'=>'clinical_test','table_name'=>'clinical_master_metadata_registration_events','column_name'=>null,'privilege_type'=>'INSERT','is_grantable'=>'NO');
		$grants[]="GRANT INSERT ON `clinical_test`.`clinical_master_metadata_registration_events` TO `writer`@`%`";
		return array('configured_user'=>'writer','authenticated_user'=>'writer@localhost','current_user'=>'writer@%','current_role'=>null,'database_name'=>'clinical_test','privileges'=>$privileges,'active_roles'=>array(),'applicable_roles'=>array(),'grant_statements'=>$grants);
	}

	private function treeDigest($root)
	{
		$files=array();$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
		foreach($iterator as$file)if($file->isFile()){$relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));$files[$relative]=hash_file('sha256',$file->getPathname());}
		ksort($files,SORT_STRING);return hash('sha256',json_encode($files,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
	}

	private function makeLoader()
	{
		return new ClinicalPackageLoader(new ClinicalPackagePathPolicy($this->config['allowed_roots_environment']),new StrictJsonDecoder(),$this->config['established_validator_file'],$this->config['established_rules_file']);
	}

	private function assertProductionFailure(array $arguments,$expectedFormat,$expectedCode=null,array $forbiddenPaths=array())
	{
		$command=array_merge(array(PHP_BINARY,dirname(__DIR__).'/clinical_package.php'),$arguments);
		$descriptors=array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w'));
		$process=proc_open($command,$descriptors,$pipes,null,null,array('bypass_shell'=>true));
		if(!is_resource($process))throw new RuntimeException('production subprocess unavailable');
		fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
		if($exit===0)throw new RuntimeException('production failure subprocess unexpectedly passed');
		$this->same('',$stderr);
		$forbiddenPaths=array_merge($forbiddenPaths,array($this->acceptedRoot,dirname(__DIR__,2)));
		foreach($forbiddenPaths as$path)if(is_string($path)&&$path!=='')$this->same(false,strpos($stdout,$path)!==false);
		$this->same(false,strpos($stdout,'PHP Warning')!==false);$this->same(false,strpos($stdout,'Stack trace')!==false);
		if($expectedFormat==='json'){
			$trimmed=trim($stdout);$this->same('{',$trimmed[0]);$this->same('}',substr($trimmed,-1));
			$decoded=json_decode($trimmed,true,512,JSON_THROW_ON_ERROR);$this->same(true,is_array($decoded));$this->same('FAIL',$decoded['validation_result']);$this->same(false,$decoded['dml_executed']);$this->same(false,$decoded['package_data_imported']);$this->same(false,$decoded['runtime_enabled']);if($expectedCode!==null)$this->same($expectedCode,$decoded['error_1']);
		}else{
			$this->contains('VALIDATION_RESULT=FAIL',$stdout);$this->contains('DML_EXECUTED=false',$stdout);$this->contains('PACKAGE_DATA_IMPORTED=false',$stdout);$this->contains('RUNTIME_ENABLED=false',$stdout);if($expectedCode!==null)$this->contains('ERROR_1='.$expectedCode,$stdout);$this->same(false,strpos(ltrim($stdout),'{')===0);
		}
		return array('stdout'=>$stdout,'stderr'=>$stderr,'exit_code'=>$exit);
	}

	private function permissionFailureSubprocess($type)
	{
		if(DIRECTORY_SEPARATOR==='\\')throw new ClinicalPackageTestSkip('POSIX permission semantics unavailable');
		$this->resetFixture();
		if($type==='directory'){$path=$this->fixtureRoot.'/blocked-directory';if(!mkdir($path,0700))throw new RuntimeException('permission fixture create failed');}
		else{$path=$this->fixtureRoot.'/43_master_jenis_resep.json';}
		if(!chmod($path,0000))throw new ClinicalPackageTestSkip('permission mutation unavailable');
		$blocked=$type==='directory'?(@scandir($path)===false):(@file_get_contents($path)===false);
		if(!$blocked){chmod($path,$type==='directory'?0700:0600);throw new ClinicalPackageTestSkip('filesystem does not enforce unreadable fixture');}
		putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);
		try{$this->assertProductionFailure(array('inspect','--package-root='.$this->fixtureRoot,'--format=json'),'json',null,array($this->fixtureRoot,dirname(__DIR__)));}
		finally{chmod($path,$type==='directory'?0700:0600);putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent);}
	}

	private function contractMutation($entity,$field,$value)
	{
		$model=$this->model;
		if($entity==='package')$model['package']['persisted'][$field]=$value;
		elseif($entity==='dataset')$model['datasets'][0]['persisted'][$field]=$value;
		elseif($entity==='package_capability')$model['package_capabilities'][0]['persisted'][$field]=$value;
		elseif($entity==='dataset_capability')$model['datasets'][0]['capabilities'][0]['persisted'][$field]=$value;
		elseif($entity==='field_contract')$model['datasets'][0]['field_contracts'][0]['persisted'][$field]=$value;
		else throw new RuntimeException('unknown contract mutation entity');
		try{$this->contractValidator->validate($model);}catch(ClinicalPackageException $exception){$this->same(0,strpos($exception->getSafeCode(),'registration_contract_'));return;}
		throw new RuntimeException('invalid target-schema value was accepted');
	}

	private function validateContractValue($entity,$field,$value)
	{
		$matrixMethod=new ReflectionMethod($this->contractValidator,'matrixByEntity');
		$validateMethod=new ReflectionMethod($this->contractValidator,'validateValue');
		$matrix=$matrixMethod->invoke($this->contractValidator);
		$validateMethod->invoke($this->contractValidator,$entity,$field,$value,$matrix[$entity][$field]);
	}

	private function assertNullableComparisonCombinations($entity,$field,$nonNull)
	{
		foreach(array(array(null,null,'exact_match'),array(null,$nonNull,'conflict'),array($nonNull,null,'conflict'),array($nonNull,$nonNull,'exact_match'))as$case){
			$model=$this->model;
			if($entity==='package')$model['package']['persisted'][$field]=$case[0];else$model['datasets'][0]['persisted'][$field]=$case[0];
			$graph=$this->exactGraphForModel($model);
			if($entity==='package')$graph['package_identity_rows'][0][$field]=$case[1];else$graph['datasets'][0][$field]=$case[1];
			$result=(new ClinicalRegistrationPlanner())->compare($model,new FakeClinicalRegistrationStateRepository($graph));
			$this->same($case[2],$result['package_state']);
		}
	}

	private function compareGraph(array $graph)
	{
		return (new ClinicalRegistrationPlanner())->compare($this->model,new FakeClinicalRegistrationStateRepository($graph));
	}

	private function graphFieldConflict($collection,$index,$field,$value)
	{
		$graph=$this->exactGraph();
		$graph[$collection][$index][$field]=$value;
		$this->same('conflict',$this->compareGraph($graph)['package_state']);
	}

	private function reverseObjectMembers(array $object)
	{
		$result=array();
		foreach(array_reverse(array_keys($object))as$key)$result[$key]=$object[$key];
		return $result;
	}

	private function assertFailureResult($code,$exitCode,$format)
	{
		$report=ClinicalPackageReporter::failure('inspect',$code,array(),$exitCode);
		ob_start();$actualExit=ClinicalPackageReporter::render($report,$format);$output=ob_get_clean();
		$this->same($exitCode,$actualExit);
		$this->contains($format==='text'?'VALIDATION_RESULT=FAIL':'"validation_result":"FAIL"',$output);
	}

	private function assertGrantAccepted(array $statements, array $applicableRoles = array())
	{
		$evidence=$this->readOnlyPrivilegeEvidence();
		$evidence['grant_statements']=$statements;
		$evidence['applicable_roles']=$applicableRoles;
		$this->same(true,MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($evidence));
	}

	private function assertGrantRejected($statement, $errorCode, array $applicableRoles = array())
	{
		$evidence=$this->readOnlyPrivilegeEvidence();
		$evidence['grant_statements']=array($statement);
		$evidence['applicable_roles']=$applicableRoles;
		$this->expectCode(function()use($evidence){MysqliClinicalRegistrationStateRepository::assertReadOnlyPrivilegeEvidence($evidence);},$errorCode);
	}

	private function readOnlyPrivilegeEvidence()
	{
		return array(
			'configured_user'=>'reader',
			'authenticated_user'=>'reader@localhost',
			'current_user'=>'reader@%',
			'current_role'=>null,
			'privileges'=>array(
				array('privilege_surface'=>'global','privilege_type'=>'USAGE','is_grantable'=>'NO'),
				array('privilege_surface'=>'schema','privilege_type'=>'SELECT','is_grantable'=>'NO'),
			),
			'active_roles'=>array(),
			'applicable_roles'=>array(),
			'grant_statements'=>array(
				"GRANT USAGE ON *.* TO 'reader'@'%'",
				"GRANT SELECT ON `clinical`.* TO 'reader'@'%'",
			),
		);
	}

	private function emptyGraph()
	{
		return array('package_identity_rows'=>array(),'package_checksum_rows'=>array(),'datasets'=>array(),'package_capabilities'=>array(),'dataset_capabilities'=>array(),'field_contracts'=>array());
	}

	private function exactGraph()
	{
		return $this->exactGraphForModel($this->model);
	}

	private function exactGraphForModel(array $model)
	{
		$graph=$this->emptyGraph();
		$package=$model['package']['persisted'];$package['clinical_master_package_id']='1';
		$graph['package_identity_rows'][]=$package;
		$graph['package_checksum_rows'][]=array('clinical_master_package_id'=>'1','package_key'=>$package['package_key'],'package_version'=>$package['package_version']);
		foreach($model['package_capabilities']as$r)$graph['package_capabilities'][]=$r['persisted'];
		$id=1;
		foreach($model['datasets']as$d){$row=$d['persisted'];$row['clinical_master_dataset_id']=(string)$id++;$graph['datasets'][]=$row;foreach($d['capabilities']as$r)$graph['dataset_capabilities'][]=array('dataset_key'=>$d['persisted']['dataset_key'])+$r['persisted'];foreach($d['field_contracts']as$r)$graph['field_contracts'][]=array('dataset_key'=>$d['persisted']['dataset_key'])+$r['persisted'];}
		return $graph;
	}

	private function mutateManifest($callback)
	{
		$this->resetFixture();$m=$this->fixtureManifest();call_user_func_array($callback,array(&$m));$this->writeFixtureManifest($m);
	}

	private function expectLoaderCode($code)
	{
		putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);
		try { $this->expectCode(function () { $this->makeLoader()->load($this->fixtureRoot); },$code); }
		finally { putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent); }
	}

	private function fixtureManifest() { return json_decode(file_get_contents($this->fixtureRoot.'/00_manifest.json'),true,512,JSON_THROW_ON_ERROR); }
	private function writeFixtureManifest(array $manifest) { file_put_contents($this->fixtureRoot.'/00_manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n"); }

	private function resetFixture()
	{
		$this->removeTree($this->fixtureRoot);
		$this->copyTree($this->acceptedRoot,$this->fixtureRoot);
	}

	private function symlinkTest($rootLink)
	{
		if (!function_exists('symlink')) throw new ClinicalPackageTestSkip('symlink unsupported');
		$this->resetFixture();
		if ($rootLink) {
			$link=$this->fixtureParent.'/root-link';@unlink($link);
			if(!@symlink($this->fixtureRoot,$link))throw new ClinicalPackageTestSkip('symlink creation unavailable');
			putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);
			try{$this->expectCode(function () use($link){$this->makeLoader()->load($link);},'package_root_symlink_rejected');}finally{@unlink($link);putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent);}
		} else {
			$link=$this->fixtureRoot.'/nested-link';if(!@symlink($this->fixtureRoot.'/43_master_jenis_resep.json',$link))throw new ClinicalPackageTestSkip('symlink creation unavailable');
			putenv($this->config['allowed_roots_environment'].'='.$this->fixtureParent);
			try{$this->expectCode(function (){$this->makeLoader()->load($this->fixtureRoot);},'package_symlink_rejected');}finally{@unlink($link);putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent);}
		}
	}

	private function intermediateSymlinkTest($depth,$outside=false)
	{
		if (!function_exists('symlink')) throw new ClinicalPackageTestSkip('symlink unsupported');
		$base=$this->fixtureParent.'/component-links-'.$depth.($outside?'-outside':'');
		$this->removeTree($base);
		$allowed=$base.'/allowed';
		$target=$outside?$base.'/outside':$allowed.'/real';
		$package=$target.'/pkg';
		if(!mkdir($target,0700,true))throw new RuntimeException('symlink target create failed');
		$this->copyTree($this->acceptedRoot,$package);
		$linkParent=$depth===1?$allowed:$allowed.'/level-one';
		if(!is_dir($linkParent)&&!mkdir($linkParent,0700,true))throw new RuntimeException('symlink parent create failed');
		$link=$linkParent.'/link';
		if(!@symlink($target,$link))throw new ClinicalPackageTestSkip('symlink creation unavailable');
		$request=$link.'/pkg';
		putenv($this->config['allowed_roots_environment'].'='.$allowed);
		try{$this->expectCode(function()use($request){$this->makeLoader()->load($request);},'package_root_symlink_rejected');}
		finally{$this->removeTree($base);putenv($this->config['allowed_roots_environment'].'='.$this->acceptedParent);}
	}

	private function checksumDiffers(array $left,array $right)
	{
		$b=new ClinicalPackageChecksumBuilder(new JcsCanonicalizer());$this->notSame($b->checksum($left),$b->checksum($right));
	}

	private function test($name,$callback)
	{
		try { call_user_func($callback);$this->passes++;echo 'PASS '.$name.PHP_EOL; }
		catch(ClinicalPackageTestSkip $e){$this->skips++;echo 'SKIP '.$name.' '.$e->getMessage().PHP_EOL;}
		catch(Throwable $e){$this->failures++;echo 'FAIL '.$name.' '.get_class($e).' '.$e->getMessage().PHP_EOL;}
	}

	private function expectCode($callback,$expected)
	{
		try{call_user_func($callback);}catch(ClinicalPackageException $e){$this->same($expected,$e->getSafeCode());return;}throw new RuntimeException('expected exception '.$expected);
	}
	private function same($expected,$actual){if($expected!==$actual)throw new RuntimeException('expected '.var_export($expected,true).' got '.var_export($actual,true));}
	private function notSame($left,$right){if($left===$right)throw new RuntimeException('values unexpectedly equal');}
	private function contains($needle,$haystack){if(strpos($haystack,$needle)===false)throw new RuntimeException('missing '.$needle);}
	private function matches($pattern,$value){if(preg_match($pattern,$value)!==1)throw new RuntimeException('pattern mismatch '.$pattern);}

	private function copyTree($source,$destination)
	{
		if(!is_dir($destination)&&!mkdir($destination,0700,true))throw new RuntimeException('copy directory failed');
		foreach(scandir($source)as$name){if($name==='.'||$name==='..')continue;$from=$source.DIRECTORY_SEPARATOR.$name;$to=$destination.DIRECTORY_SEPARATOR.$name;if(is_dir($from))$this->copyTree($from,$to);elseif(!copy($from,$to))throw new RuntimeException('copy file failed');}
	}

	private function removeTree($path)
	{
		if(!file_exists($path)&&!is_link($path))return;if(is_link($path)||is_file($path)){unlink($path);return;}foreach(scandir($path)as$name){if($name==='.'||$name==='..')continue;$this->removeTree($path.DIRECTORY_SEPARATOR.$name);}rmdir($path);
	}

	private function tearDown()
	{
		$this->removeTree($this->fixtureParent);
		foreach($this->environment as$name=>$value){if($value===false)putenv($name);else putenv($name.'='.$value);}
	}
}

exit((new ClinicalPackageTests())->run());
