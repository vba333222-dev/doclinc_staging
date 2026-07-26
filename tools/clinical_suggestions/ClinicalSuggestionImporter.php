<?php

class ClinicalSuggestionImporter
{
	private $db;
	private $plannedExistingTerms = array();

	public static function connectFromEnvironment($apply)
	{
		$host = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_HOST') ?: 'localhost';
		$port = (int) (getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PORT') ?: 3306);
		$database = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_NAME') ?: '';
		$user = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_USER') ?: '';
		$password = getenv('DOCLINC_CLINICAL_SUGGESTION_DB_PASSWORD');
		$allowed = array_filter(array_map('trim', explode(',', getenv('DOCLINC_CLINICAL_SUGGESTION_ALLOWED_USERS') ?: '')));
		if ($database === '' || $user === '' || $password === false || $password === '' || !in_array($user, $allowed, true)) {
			throw new ClinicalSuggestionPackageException('database_identity_not_allowed');
		}
		if ($apply && !filter_var(getenv('DOCLINC_CLINICAL_SUGGESTION_WRITE_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN)) {
			throw new ClinicalSuggestionPackageException('suggestion_write_disabled');
		}
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		try {
			$db = new mysqli($host, $user, $password, $database, $port);
			$db->set_charset('utf8mb4');
		} catch (Throwable $exception) {
			throw new ClinicalSuggestionPackageException('database_connection_failed');
		} finally {
			$password = null;
		}
		return new self($db);
	}

	public function __construct(mysqli $db)
	{
		$this->db = $db;
		foreach (array('clinical_suggestion_import_batches', 'clinical_suggestion_terms', 'clinical_suggestion_aliases') as $table) {
			$stmt = $db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
			$stmt->bind_param('s', $table);
			$stmt->execute();
			if ($stmt->get_result()->num_rows !== 1) throw new ClinicalSuggestionPackageException('suggestion_schema_not_ready');
		}
	}

	public function plan(array $package, $environment, $batchReference)
	{
		$counts = array('inserted' => 0, 'updated' => 0, 'skipped' => 0, 'duplicate' => $package['counts']['duplicates'], 'rejected' => $package['counts']['rejected'], 'aliases_inserted' => 0);
		$batch = $this->batch($batchReference);
		if ($batch) {
			if ($batch['batch_state'] !== 'applied' || !hash_equals($batch['package_checksum'], $package['package_checksum'])
				|| !hash_equals($batch['package_snapshot_sha256'], $package['package_snapshot_sha256']) || $batch['environment_scope'] !== $environment) {
				throw new ClinicalSuggestionPackageException('batch_reference_conflict');
			}
			$counts['skipped'] = count($package['terms']);
			return array('counts' => $counts, 'idempotent' => true, 'batch' => $batch);
		}

		$this->plannedExistingTerms = $this->existingTerms();
		foreach ($package['terms'] as $term) {
			$naturalKey = $term['type'] . "\0" . $term['reference_key'];
			$existing = isset($this->plannedExistingTerms[$naturalKey]) ? $this->plannedExistingTerms[$naturalKey] : null;
			if (!$existing) {
				$counts['inserted']++;
				$counts['aliases_inserted'] += count($term['aliases']);
				continue;
			}
			if ((int) $existing['suggestion_import_batch_id'] > 0) {
				throw new ClinicalSuggestionPackageException('existing_term_owned_by_other_batch');
			}
			if (!$this->sameTerm($existing, $term, $environment)) throw new ClinicalSuggestionPackageException('existing_term_conflict');
			$counts['skipped']++;
			$this->assertAliasesExact((int) $existing['suggestion_term_id'], $term['aliases']);
		}
		return array('counts' => $counts, 'idempotent' => false, 'batch' => null);
	}

	public function apply(array $package, $environment, $batchReference)
	{
		$plan = $this->plan($package, $environment, $batchReference);
		if ($plan['idempotent']) return array('counts' => $plan['counts'], 'write_executed' => false, 'transaction_committed' => false, 'idempotent' => true);
		$this->db->begin_transaction();
		try {
			$stmt = $this->db->prepare("INSERT INTO clinical_suggestion_import_batches (batch_reference,package_id,package_version,package_checksum,package_snapshot_sha256,environment_scope,batch_state,term_count,alias_count) VALUES (?,?,?,?,?,?,'applied',0,0)");
			$stmt->bind_param('ssssss', $batchReference, $package['package_id'], $package['package_version'], $package['package_checksum'], $package['package_snapshot_sha256'], $environment);
			$stmt->execute();
			$batchId = (int) $this->db->insert_id;
			$insertedTerms = 0;
			$insertedAliases = 0;
			foreach ($package['terms'] as $term) {
				$naturalKey = $term['type'] . "\0" . $term['reference_key'];
				if (isset($this->plannedExistingTerms[$naturalKey])) continue;
				$stmt = $this->db->prepare("INSERT INTO clinical_suggestion_terms (suggestion_import_batch_id,term_type,reference_key,term_code,preferred_label,normalized_label,source_name,source_version,source_dataset,source_governance_status,environment_scope,active_state) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
				$stmt->bind_param('issssssssss', $batchId, $term['type'], $term['reference_key'], $term['code'], $term['label'], $term['normalized_label'], $term['source_name'], $term['source_version'], $term['source_dataset'], $term['source_governance_status'], $environment);
				$stmt->execute();
				$termId = (int) $this->db->insert_id;
				$insertedTerms++;
				foreach ($term['aliases'] as $normalized => $alias) {
					$language = 'id';
					$stmt = $this->db->prepare('INSERT INTO clinical_suggestion_aliases (suggestion_term_id,alias_label,normalized_alias,source_name,source_version,source_dataset,source_governance_status,language_code,active_state) VALUES (?,?,?,?,?,?,?,?,1)');
					$stmt->bind_param('isssssss', $termId, $alias['label'], $normalized, $alias['source_name'], $alias['source_version'], $alias['source_dataset'], $alias['source_governance_status'], $language);
					$stmt->execute();
					$insertedAliases++;
				}
			}
			$stmt = $this->db->prepare('UPDATE clinical_suggestion_import_batches SET term_count=?, alias_count=? WHERE suggestion_import_batch_id=? AND batch_state=\'applied\'');
			$stmt->bind_param('iii', $insertedTerms, $insertedAliases, $batchId);
			$stmt->execute();
			$this->db->commit();
			$plan['counts']['inserted'] = $insertedTerms;
			$plan['counts']['aliases_inserted'] = $insertedAliases;
			return array('counts' => $plan['counts'], 'write_executed' => true, 'transaction_committed' => true, 'idempotent' => false);
		} catch (Throwable $exception) {
			$this->db->rollback();
			throw new ClinicalSuggestionPackageException('import_transaction_rolled_back');
		}
	}

	public function rollback($batchReference, $environment, $apply, $packageChecksum = null, $packageSnapshot = null)
	{
		$batch = $this->batch($batchReference);
		if (!$batch || $batch['environment_scope'] !== $environment || $batch['batch_state'] !== 'applied') {
			throw new ClinicalSuggestionPackageException('rollback_batch_not_found');
		}
		$result = array('terms' => (int) $batch['term_count'], 'aliases' => (int) $batch['alias_count'], 'write_executed' => false, 'transaction_committed' => false);
		if (!$apply) return $result;
		if (!is_string($packageChecksum) || !is_string($packageSnapshot)
			|| !hash_equals($batch['package_checksum'], $packageChecksum)
			|| !hash_equals($batch['package_snapshot_sha256'], $packageSnapshot)) {
			throw new ClinicalSuggestionPackageException('rollback_package_confirmation_mismatch');
		}
		$batchId = (int) $batch['suggestion_import_batch_id'];
		$this->db->begin_transaction();
		try {
			$stmt = $this->db->prepare('DELETE a FROM clinical_suggestion_aliases a INNER JOIN clinical_suggestion_terms t ON t.suggestion_term_id=a.suggestion_term_id WHERE t.suggestion_import_batch_id=?');
			$stmt->bind_param('i', $batchId);
			$stmt->execute();
			$stmt = $this->db->prepare('DELETE FROM clinical_suggestion_terms WHERE suggestion_import_batch_id=?');
			$stmt->bind_param('i', $batchId);
			$stmt->execute();
			$stmt = $this->db->prepare("UPDATE clinical_suggestion_import_batches SET batch_state='rolled_back', rolled_back_at=CURRENT_TIMESTAMP(6) WHERE suggestion_import_batch_id=? AND batch_state='applied'");
			$stmt->bind_param('i', $batchId);
			$stmt->execute();
			if ($stmt->affected_rows !== 1) throw new RuntimeException('batch_state_conflict');
			$this->db->commit();
			$result['write_executed'] = true;
			$result['transaction_committed'] = true;
			return $result;
		} catch (Throwable $exception) {
			$this->db->rollback();
			throw new ClinicalSuggestionPackageException('rollback_transaction_rolled_back');
		}
	}

	public function close()
	{
		$this->db->close();
	}

	private function batch($reference)
	{
		$stmt = $this->db->prepare('SELECT * FROM clinical_suggestion_import_batches WHERE batch_reference=? LIMIT 1');
		$stmt->bind_param('s', $reference);
		$stmt->execute();
		return $stmt->get_result()->fetch_assoc() ?: null;
	}

	private function term($type, $key)
	{
		$stmt = $this->db->prepare('SELECT * FROM clinical_suggestion_terms WHERE term_type=? AND reference_key=? LIMIT 1');
		$stmt->bind_param('ss', $type, $key);
		$stmt->execute();
		return $stmt->get_result()->fetch_assoc() ?: null;
	}

	private function existingTerms()
	{
		$map = array();
		$result = $this->db->query('SELECT * FROM clinical_suggestion_terms ORDER BY term_type,reference_key');
		foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
			$map[$row['term_type'] . "\0" . $row['reference_key']] = $row;
		}
		return $map;
	}

	private function sameTerm(array $existing, array $term, $environment)
	{
		$expected = array('term_code' => $term['code'], 'preferred_label' => $term['label'], 'normalized_label' => $term['normalized_label'], 'source_name' => $term['source_name'], 'source_version' => $term['source_version'], 'source_dataset' => $term['source_dataset'], 'source_governance_status' => $term['source_governance_status'], 'environment_scope' => $environment, 'active_state' => '1');
		foreach ($expected as $field => $value) if ((string) $existing[$field] !== (string) $value) return false;
		return true;
	}

	private function assertAliasesExact($termId, array $aliases)
	{
		$stmt = $this->db->prepare('SELECT normalized_alias,alias_label,source_name,source_version,source_dataset,source_governance_status FROM clinical_suggestion_aliases WHERE suggestion_term_id=? AND active_state=1 ORDER BY normalized_alias');
		$stmt->bind_param('i', $termId);
		$stmt->execute();
		$actual = array();
		foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
			$actual[$row['normalized_alias']] = array(
				'label' => $row['alias_label'],
				'source_name' => $row['source_name'],
				'source_version' => $row['source_version'],
				'source_dataset' => $row['source_dataset'],
				'source_governance_status' => $row['source_governance_status'],
			);
		}
		if ($actual !== $aliases) throw new ClinicalSuggestionPackageException('existing_alias_conflict');
	}
}
