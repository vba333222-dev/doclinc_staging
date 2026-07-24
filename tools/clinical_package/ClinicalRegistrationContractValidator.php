<?php

class ClinicalRegistrationContractValidator
{
	private static $capabilities = array('audit_import','canonical_storage','redistribution','production_deployment','runtime_reference','clinical_selection','prescribing','decision_support');
	private static $governance = array('unreviewed','in_review','approved','rejected','retired');
	private static $licenses = array('not_declared','review_required','cleared','restricted','rejected');

	public function validatePreChecksum(array $model, $profile)
	{
		$copy = $model;
		$copy['package']['persisted']['package_checksum'] = str_repeat('0', 64);
		$copy['package']['persisted']['package_checksum_profile'] = $profile;
		return $this->validate($copy);
	}

	public function validate(array $model)
	{
		$matrix = $this->matrixByEntity();
		$count = 0;
		$this->validateRow('package', $model['package']['persisted'], $matrix['package']);
		$count += count($matrix['package']);
		foreach ($model['datasets'] as $dataset) {
			$this->validateRow('dataset', $dataset['persisted'], $matrix['dataset']);
			$count += count($matrix['dataset']);
			foreach ($dataset['capabilities'] as $row) {
				$this->validateRow('dataset_capability', $row['persisted'], $matrix['dataset_capability']);
				$count += count($matrix['dataset_capability']);
			}
			foreach ($dataset['field_contracts'] as $row) {
				$this->validateRow('field_contract', $row['persisted'], $matrix['field_contract']);
				$count += count($matrix['field_contract']);
			}
		}
		foreach ($model['package_capabilities'] as $row) {
			$this->validateRow('package_capability', $row['persisted'], $matrix['package_capability']);
			$count += count($matrix['package_capability']);
		}
		$this->assertInitialState($model);
		return $count;
	}

	public function fieldContracts()
	{
		$result = array();
		foreach ($this->matrixByEntity() as $entity => $fields) {
			foreach ($fields as $field => $contract) {
				$copy = $contract;
				unset($copy['kind'], $copy['pattern'], $copy['non_empty'], $copy['exact']);
				$result[] = array('entity' => $entity, 'field' => $field) + $copy;
			}
		}
		return $result;
	}

	public function fieldContractCount()
	{
		return count($this->fieldContracts());
	}

	private function matrixByEntity()
	{
		$package = array(
			'package_key' => $this->text('VARCHAR(128)','ascii','ascii_bin',128,false,'manifest.package_id','lowercase machine key','ascii','/^[a-z][a-z0-9-]*$/D'),
			'package_version' => $this->text('VARCHAR(64)','ascii','ascii_bin',64,false,'manifest.package_version','safe version identifier','ascii','/^[A-Za-z0-9][A-Za-z0-9._-]*$/D'),
			'manifest_checksum' => $this->sha('raw 00_manifest.json bytes'),
			'manifest_schema_version' => $this->text('VARCHAR(32)','ascii','ascii_bin',32,true,'manifest.manifest_schema_version','ASCII schema version','ascii',null,true),
			'source_status' => $this->enumContract('VARCHAR(24)',24,false,'manifest.package_status',array('draft','in_review','approved','rejected','retired')),
			'governance_status' => $this->enumContract('VARCHAR(24)',24,false,'local initial governance',self::$governance),
			'production_ready' => $this->integer('TINYINT UNSIGNED','0..1',0,1,'manifest.production_ready'),
			'source_runtime_enabled' => $this->integer('TINYINT UNSIGNED','0..1',0,1,'manifest.runtime_enabled'),
			'license_disposition' => $this->enumContract('VARCHAR(24)',24,false,'aggregate dataset license_status',self::$licenses),
			'declared_dataset_count' => $this->integer('SMALLINT UNSIGNED','0..65535',0,65535,'manifest.package_summary.dataset_count'),
			'observed_dataset_count' => $this->integer('SMALLINT UNSIGNED','0..65535',0,65535,'observed inventory'),
			'declared_record_count' => $this->integer('BIGINT UNSIGNED','0..18446744073709551615',0,PHP_INT_MAX,'manifest.package_summary.record_count'),
			'observed_record_count' => $this->integer('BIGINT UNSIGNED','0..18446744073709551615',0,PHP_INT_MAX,'observed records'),
			'source_reference' => $this->text('VARCHAR(255)','utf8mb4','utf8mb4_bin',255,true,'fixed package-relative manifest reference','relative logical reference','source_reference',null,true),
			'package_checksum' => $this->sha('doclink-package-jcs-v1 canonical projection'),
			'package_checksum_profile' => $this->text('VARCHAR(32)','ascii','ascii_bin',32,false,'fixed checksum profile','exact profile identifier','exact',null,false,ClinicalPackageChecksumBuilder::PROFILE),
		);
		$dataset = array(
			'dataset_key' => $this->text('VARCHAR(128)','ascii','ascii_bin',128,false,'manifest.datasets[].dataset_id','ASCII dataset key','ascii','/^[A-Za-z][A-Za-z0-9_]*$/D'),
			'source_file' => $this->text('VARCHAR(255)','utf8mb4','utf8mb4_bin',255,false,'manifest.datasets[].path','strict relative package path','source_file'),
			'dataset_version' => $this->text('VARCHAR(64)','ascii','ascii_bin',64,false,'manifest.datasets[].version','ASCII dataset version','ascii'),
			'dataset_checksum' => $this->sha('observed dataset bytes'),
			'domain_key' => $this->text('VARCHAR(64)','ascii','ascii_bin',64,false,'manifest.datasets[].domain','ASCII domain key','ascii','/^[a-z][a-z0-9_]*$/D'),
			'entity_key' => $this->text('VARCHAR(64)','ascii','ascii_bin',64,true,'manifest.datasets[].entity','nullable ASCII entity key','ascii','/^[a-z][a-z0-9_]*$/D',true),
			'source_governance_status' => $this->text('VARCHAR(128)','ascii','ascii_bin',128,false,'manifest.datasets[].source_status','non-empty source evidence','ascii'),
			'governance_status' => $this->enumContract('VARCHAR(24)',24,false,'local initial governance',self::$governance),
			'source_runtime_enabled' => $this->integer('TINYINT UNSIGNED','0..1',0,1,'manifest.datasets[].runtime_enabled'),
			'license_disposition' => $this->enumContract('VARCHAR(24)',24,false,'manifest.datasets[].license_status',self::$licenses),
			'declared_record_count' => $this->integer('BIGINT UNSIGNED','0..18446744073709551615',0,PHP_INT_MAX,'manifest.datasets[].record_count'),
			'observed_record_count' => $this->integer('BIGINT UNSIGNED','0..18446744073709551615',0,PHP_INT_MAX,'observed records'),
			'seed_order' => $this->integer('SMALLINT UNSIGNED','0..65535',0,65535,'manifest.datasets[].seed_order'),
			'natural_key_contract' => $this->text('VARCHAR(255)','utf8mb4','utf8mb4_bin',255,true,'canonical manifest unique_key','valid canonical JSON array','natural_key',null,true),
			'hierarchy_mode' => $this->enumContract('VARCHAR(16)',16,false,'fixed initial hierarchy mode',array('none','tree','dag','self_parent')),
		);
		$capability = array(
			'capability' => $this->enumContract('VARCHAR(32)',32,false,'fixed supported capability set',self::$capabilities),
			'decision_status' => $this->enumContract('VARCHAR(16)',16,false,'initial blocked decision',array('blocked','pending','approved','revoked')),
			'decision_reason' => $this->text('VARCHAR(1000)','utf8mb4','utf8mb4_general_ci',1000,true,'fixed initial decision reason','nullable UTF-8 reason','utf8',null,true),
			'decision_actor' => $this->text('VARCHAR(128)','utf8mb4','utf8mb4_bin',128,true,'no initial actor','nullable UTF-8 actor','utf8',null,true),
			'decided_at' => $this->dateTime('no initial decision time'),
		);
		$field = array(
			'source_field' => $this->text('VARCHAR(128)','ascii','ascii_bin',128,false,'observed JSON object member','safe field identifier','ascii','/^[A-Za-z_][A-Za-z0-9_]*$/D'),
			'source_json_type' => $this->enumContract('VARCHAR(16)',16,false,'observed JSON type',array('string','integer','number','boolean','object','array','null','mixed')),
			'cardinality' => $this->enumContract('VARCHAR(16)',16,false,'observed scalar/array shape',array('scalar','array')),
			'required_flag' => $this->integer('TINYINT UNSIGNED','0..1',0,1,'observed field presence'),
			'nullable_flag' => $this->integer('TINYINT UNSIGNED','0..1',0,1,'observed null presence'),
			'identity_role' => $this->enumContract('VARCHAR(32)',32,false,'derived identity classification',array('none','primary_code','natural_key_component','parent_reference','label','provenance')),
			'target_domain' => $this->text('VARCHAR(64)','ascii','ascii_bin',64,true,'no initial canonical target','nullable lowercase target','ascii','/^[a-z][a-z0-9_]*$/D',true),
			'target_entity' => $this->text('VARCHAR(128)','ascii','ascii_bin',128,true,'no initial canonical target','nullable lowercase target','ascii','/^[a-z][a-z0-9_]*$/D',true),
			'target_attribute' => $this->text('VARCHAR(128)','ascii','ascii_bin',128,true,'no initial canonical target','nullable lowercase target','ascii','/^[a-z][a-z0-9_]*$/D',true),
			'transform_policy' => $this->enumContract('VARCHAR(32)',32,false,'initial blocked transform',array('identity','trim_only','unicode_nfc','lookup_fk','array_expand','manual_review','blocked')),
			'contract_status' => $this->enumContract('VARCHAR(24)',24,false,'initial draft contract',array('draft','in_review','approved','rejected','retired')),
			'review_reason' => $this->text('VARCHAR(1000)','utf8mb4','utf8mb4_general_ci',1000,true,'derived initial review reason','nullable UTF-8 reason','utf8',null,true),
			'decision_actor' => $this->text('VARCHAR(128)','utf8mb4','utf8mb4_bin',128,true,'no initial actor','nullable UTF-8 actor','utf8',null,true),
			'decided_at' => $this->dateTime('no initial decision time'),
		);
		$matrix = array('package'=>$package,'dataset'=>$dataset,'package_capability'=>$capability,'dataset_capability'=>$capability,'field_contract'=>$field);
		foreach ($matrix as $entity => &$fields) {
			foreach ($fields as $name => &$contract) $contract['safe_error_code'] = 'registration_contract_'.$entity.'_'.$name.'_invalid';
			unset($contract);
		}
		unset($fields);
		return $matrix;
	}

	private function validateRow($entity, array $row, array $contracts)
	{
		$unexpected = array_diff(array_keys($row), array_keys($contracts));
		$missing = array_diff(array_keys($contracts), array_keys($row));
		if (count($unexpected) > 0 || count($missing) > 0) {
			throw new ClinicalPackageException('registration_contract_unvalidated_field', 'Persisted registration fields do not exactly match the fixed target-schema contract.', array('entity'=>$entity));
		}
		foreach ($contracts as $field => $contract) $this->validateValue($entity, $field, $row[$field], $contract);
	}

	private function validateValue($entity, $field, $value, array $contract)
	{
		$code = $contract['safe_error_code'];
		if ($value === null) {
			if (!$contract['nullable']) throw new ClinicalPackageException($code, 'A required persisted registration value is null.');
			return;
		}
		if ($contract['kind'] === 'integer') {
			if (!is_int($value) || $value < $contract['min'] || $value > $contract['max']) throw new ClinicalPackageException($code, 'A persisted numeric value is outside the target-schema range.');
			return;
		}
		if (!is_string($value) || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) throw new ClinicalPackageException($code, 'A persisted text value has an invalid representation.');
		if ($contract['kind'] === 'sha256') {
			if (preg_match('/^[0-9a-f]{64}$/D', $value) !== 1) throw new ClinicalPackageException($code, 'A persisted checksum is not lowercase SHA-256.');
			return;
		}
		if ($contract['kind'] === 'datetime') {
			if (!$this->isValidDateTime6($value)) throw new ClinicalPackageException($code, 'A persisted decision timestamp is invalid.');
			return;
		}
		if ($contract['kind'] === 'enum') {
			if (!in_array($value, $contract['enum_or_check_values'], true)) throw new ClinicalPackageException($code, 'A persisted value is outside the target-schema CHECK set.');
			return;
		}
		if ($contract['kind'] === 'exact' && !hash_equals($contract['exact'], $value)) throw new ClinicalPackageException($code, 'A persisted fixed value is invalid.');
		if ($contract['charset'] === 'ascii' && preg_match('/^[\x00-\x7F]*$/D', $value) !== 1) throw new ClinicalPackageException($code, 'A persisted ASCII value contains non-ASCII characters.');
		if ($contract['non_empty'] && $value === '') throw new ClinicalPackageException($code, 'A persisted text value is empty.');
		if ($this->characterLength($value) > $contract['max_length']) throw new ClinicalPackageException($code, 'A persisted text value exceeds the target column capacity.');
		if ($contract['pattern'] !== null && preg_match($contract['pattern'], $value) !== 1) throw new ClinicalPackageException($code, 'A persisted text value violates the target-schema format.');
		if ($contract['kind'] === 'source_file' && !$this->isSafeSourceFile($value)) throw new ClinicalPackageException($code, 'A persisted source path violates the strict relative-path contract.');
		if ($contract['kind'] === 'source_reference' && !$this->isSafeSourceReference($value)) throw new ClinicalPackageException($code, 'A persisted package source reference is invalid.');
		if ($contract['kind'] === 'natural_key') {
			try { $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR); }
			catch (Throwable $exception) { throw new ClinicalPackageException($code, 'A persisted natural-key contract is not canonical JSON.'); }
			if (!is_array($decoded) || count($decoded) === 0 || array_keys($decoded) !== range(0,count($decoded)-1)) throw new ClinicalPackageException($code, 'A persisted natural-key contract must be a non-empty JSON array.');
			foreach ($decoded as $fieldName) if (!is_string($fieldName) || $fieldName === '') throw new ClinicalPackageException($code, 'A persisted natural-key contract contains an invalid field name.');
			if ((new JcsCanonicalizer())->canonicalize($decoded) !== $value) throw new ClinicalPackageException($code, 'A persisted natural-key contract is not in canonical form.');
		}
	}

	private function assertInitialState(array $model)
	{
		$package = $model['package']['persisted'];
		if ($package['governance_status'] !== 'unreviewed' || $package['production_ready'] !== 0 || $package['source_runtime_enabled'] !== 0) throw new ClinicalPackageException('registration_contract_initial_package_state_invalid', 'Initial package registration state is not fail-closed.');
		foreach ($model['package_capabilities'] as $row) $this->assertBlockedCapability($row['persisted']);
		foreach ($model['datasets'] as $dataset) {
			if ($dataset['persisted']['governance_status'] !== 'unreviewed' || $dataset['persisted']['source_runtime_enabled'] !== 0) throw new ClinicalPackageException('registration_contract_initial_dataset_state_invalid', 'Initial dataset registration state is not fail-closed.');
			foreach ($dataset['capabilities'] as $row) $this->assertBlockedCapability($row['persisted']);
			foreach ($dataset['field_contracts'] as $row) {
				$p = $row['persisted'];
				if ($p['transform_policy'] !== 'blocked' || $p['contract_status'] !== 'draft' || $p['decision_actor'] !== null || $p['decided_at'] !== null) throw new ClinicalPackageException('registration_contract_initial_field_state_invalid', 'Initial field contract is not fail-closed.');
			}
		}
	}

	private function assertBlockedCapability(array $row)
	{
		if ($row['decision_status'] !== 'blocked' || $row['decision_actor'] !== null || $row['decided_at'] !== null) throw new ClinicalPackageException('registration_contract_initial_capability_state_invalid', 'Initial capability decision is not blocked.');
	}

	private function text($type,$charset,$collation,$max,$nullable,$source,$rule,$kind,$pattern=null,$nonEmpty=true,$exact=null)
	{
		return array('database_type'=>$type,'charset'=>$charset,'collation'=>$collation,'max_length_or_range'=>(string)$max,'nullable'=>$nullable,'enum_or_check_values'=>array(),'source'=>$source,'validation_rule'=>$rule,'safe_error_code'=>'registration_contract_'.strtolower(str_replace(array('(',')',' '),array('_','','_'),$type)).'_value_invalid','kind'=>$kind,'pattern'=>$pattern,'non_empty'=>$nonEmpty,'exact'=>$exact,'max_length'=>$max);
	}

	private function sha($source)
	{
		return array('database_type'=>'CHAR(64)','charset'=>'ascii','collation'=>'ascii_bin','max_length_or_range'=>'64','nullable'=>false,'enum_or_check_values'=>array('^[0-9a-f]{64}$'),'source'=>$source,'validation_rule'=>'lowercase SHA-256','safe_error_code'=>'registration_contract_sha256_invalid','kind'=>'sha256','pattern'=>null,'non_empty'=>true,'exact'=>null,'max_length'=>64);
	}

	private function enumContract($type,$max,$nullable,$source,array $values)
	{
		return array('database_type'=>$type,'charset'=>'ascii','collation'=>'ascii_bin','max_length_or_range'=>(string)$max,'nullable'=>$nullable,'enum_or_check_values'=>$values,'source'=>$source,'validation_rule'=>'exact CHECK value','safe_error_code'=>'registration_contract_enum_value_invalid','kind'=>'enum','pattern'=>null,'non_empty'=>true,'exact'=>null,'max_length'=>$max);
	}

	private function integer($type,$range,$min,$max,$source)
	{
		return array('database_type'=>$type,'charset'=>null,'collation'=>null,'max_length_or_range'=>$range,'nullable'=>false,'enum_or_check_values'=>array(),'source'=>$source,'validation_rule'=>'exact PHP integer in unsigned target range','safe_error_code'=>'registration_contract_integer_value_invalid','kind'=>'integer','pattern'=>null,'non_empty'=>false,'exact'=>null,'max_length'=>null,'min'=>$min,'max'=>$max);
	}

	private function dateTime($source)
	{
		return array('database_type'=>'DATETIME(6)','charset'=>null,'collation'=>null,'max_length_or_range'=>'microsecond precision','nullable'=>true,'enum_or_check_values'=>array(),'source'=>$source,'validation_rule'=>'null or exact DATETIME(6) text','safe_error_code'=>'registration_contract_datetime_invalid','kind'=>'datetime','pattern'=>null,'non_empty'=>false,'exact'=>null,'max_length'=>null);
	}

	private function characterLength($value)
	{
		$result = preg_match_all('/./us', $value, $unused);
		if ($result === false) throw new ClinicalPackageException('registration_contract_utf8_invalid', 'Persisted UTF-8 character length is unavailable.');
		return $result;
	}

	private function isSafeSourceFile($value)
	{
		return $value !== '' && trim($value) === $value && $value[0] !== '/' && strpos($value,'\\') === false
			&& preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) !== 1 && $value !== '.' && $value !== '..'
			&& strpos($value,'../') !== 0 && strpos($value,'/../') === false && substr($value,-3) !== '/..';
	}

	private function isSafeSourceReference($value)
	{
		if ($value === '' || preg_match('/^\s|\s$/u', $value) === 1 || $value[0] === '/' || strpos($value, '\\') !== false
			|| preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/D', $value) === 1) return false;
		foreach (explode('/', $value) as $component) {
			if ($component === '' || $component === '.' || $component === '..') return false;
		}
		return true;
	}

	private function isValidDateTime6($value)
	{
		if (preg_match('/^([0-9]{4})-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}$/D', $value, $match) !== 1) return false;
		$year = (int) $match[1];
		if ($year < 1000 || $year > 9999) return false;
		$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
		$errors = DateTimeImmutable::getLastErrors();
		if ($date === false || (is_array($errors) && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))) return false;
		return $date->format('Y-m-d H:i:s.u') === $value;
	}
}
