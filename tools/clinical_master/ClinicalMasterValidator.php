<?php

class ClinicalMasterValidator
{
    private $packageRoot;
    private $rules;
    private $errors = array();
    private $warnings = array();
    private $checks = array();
    private $diagnostics = array();
    private $manifest = null;
    private $manifestTyped = null;
    private $schema = null;
    private $schemaTyped = null;
    private $datasetById = array();
    private $indexes = array();
    private $icdNodes = array();
    private $icdDuplicateCodes = 0;
    private $mappingIndex = array();
    private $relationSources = array();
    private $issueLimit = 1000;
    private $issuesTruncated = false;

    public function __construct($packageRoot, array $rules)
    {
        $this->packageRoot = rtrim($packageRoot, '/\\');
        $this->rules = $rules;
    }

    public function validate()
    {
        $this->resetState();

        $schemaBytes = $this->readPackageFile('00_manifest.schema.json', 'schema_read_failed');
        $manifestBytes = $this->readPackageFile('00_manifest.json', 'manifest_read_failed');
        if ($schemaBytes === null || $manifestBytes === null) {
            return $this->finalize('validate', true);
        }

        $before = count($this->errors);
        $schemaDocument = $this->decodeDocument($schemaBytes, 'schema_json_invalid');
        if ($schemaDocument !== null) {
            $this->schema = $schemaDocument['assoc'];
            $this->schemaTyped = $schemaDocument['typed'];
        }
        if ($this->schema !== null) {
            $this->validateSchemaDocument();
        }
        $this->finishCheck('focused_schema', $before);

        $before = count($this->errors);
        $manifestDocument = $this->decodeDocument($manifestBytes, 'manifest_json_invalid');
        if ($manifestDocument !== null) {
            $this->manifest = $manifestDocument['assoc'];
            $this->manifestTyped = $manifestDocument['typed'];
        }
        if ($this->manifest !== null && $this->schema !== null) {
            $this->validateManifestStructure();
        }
        $this->finishCheck('manifest_structure', $before);

        if ($this->manifest === null || $this->schema === null || !isset($this->manifest['datasets']) || !is_array($this->manifest['datasets'])) {
            return $this->finalize('validate', true);
        }

        $before = count($this->errors);
        $this->validateGovernance();
        $this->finishCheck('review_governance', $before);

        $before = count($this->errors);
        $this->validateDependencies();
        $this->finishCheck('dependency_policy_and_graph', $before);

        $before = count($this->errors);
        $this->validateDatasetsSequentially();
        $this->finishCheck('dataset_files', $before);

        $before = count($this->errors);
        $this->validateCollectedRelations();
        $this->validateIcdHierarchy();
        $this->finishCheck('explicit_relations_and_icd_hierarchy', $before);

        $before = count($this->errors);
        $this->validateSubmanifests();
        $this->finishCheck('icd_submanifest', $before);

        $before = count($this->errors);
        $this->validateRuntimeSafety();
        $this->finishCheck('runtime_safety', $before);

        $this->addGovernanceWarnings();
        return $this->finalize('validate', true);
    }

    public function status()
    {
        $this->resetState();
        $manifestBytes = $this->readPackageFile('00_manifest.json', 'manifest_read_failed');
        if ($manifestBytes === null) {
            return $this->finalize('status', false);
        }

        $manifestDocument = $this->decodeDocument($manifestBytes, 'manifest_json_invalid');
        if ($manifestDocument !== null) {
            $this->manifest = $manifestDocument['assoc'];
            $this->manifestTyped = $manifestDocument['typed'];
        }
        if ($this->manifest === null) {
            return $this->finalize('status', false);
        }

        $datasets = isset($this->manifest['datasets']) && is_array($this->manifest['datasets'])
            ? $this->manifest['datasets']
            : array();
        $runtimeEnabled = 0;
        $runtimeSelectable = 0;
        $licenses = array();
        foreach ($datasets as $dataset) {
            if (!is_array($dataset)) {
                continue;
            }
            if (isset($dataset['runtime_enabled']) && $dataset['runtime_enabled'] === true) {
                $runtimeEnabled++;
            }
            if (isset($dataset['runtime_selectable']) && $dataset['runtime_selectable'] === true) {
                $runtimeSelectable++;
            }
            $license = isset($dataset['license_status']) && is_string($dataset['license_status'])
                ? $dataset['license_status']
                : 'missing';
            if (!isset($licenses[$license])) {
                $licenses[$license] = 0;
            }
            $licenses[$license]++;
        }
        ksort($licenses, SORT_STRING);

        $productionReady = isset($this->manifest['production_ready']) && $this->manifest['production_ready'] === true;
        $packageRuntime = isset($this->manifest['runtime_enabled']) && $this->manifest['runtime_enabled'] === true;
        $this->diagnostics = array(
            'full_validation_executed' => false,
            'license_status_summary' => $licenses,
            'manifest_available' => true,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'runtime_eligible' => $productionReady && $packageRuntime,
            'runtime_enabled_dataset_count' => $runtimeEnabled,
            'runtime_selectable_dataset_count' => $runtimeSelectable,
            'schema_available' => $this->resolveDeclaredFile('00_manifest.schema.json') !== null,
        );
        $this->finishCheck('lightweight_status', 0);
        $this->addGovernanceWarnings();
        return $this->finalize('status', false);
    }

    private function resetState()
    {
        $this->errors = array();
        $this->warnings = array();
        $this->checks = array();
        $this->diagnostics = array(
            'full_validation_executed' => false,
            'peak_memory_bytes' => 0,
        );
        $this->manifest = null;
        $this->manifestTyped = null;
        $this->schema = null;
        $this->schemaTyped = null;
        $this->datasetById = array();
        $this->indexes = array();
        $this->icdNodes = array();
        $this->icdDuplicateCodes = 0;
        $this->mappingIndex = array();
        $this->relationSources = array();
        $this->issuesTruncated = false;
    }

    private function validateSchemaDocument()
    {
        $declared = isset($this->schema['$schema']) ? $this->schema['$schema'] : null;
        if (!is_string($declared) || strpos($declared, 'draft/2020-12/schema') === false) {
            $this->addError('schema_draft_not_2020_12');
        }
        if (!isset($this->schema['$defs']) || !is_array($this->schema['$defs'])) {
            $this->addError('schema_definitions_missing');
            return;
        }
        foreach ($this->rules['expected_schema_definitions'] as $definition) {
            if (!array_key_exists($definition, $this->schema['$defs'])) {
                $this->addError('schema_definition_missing', array('fields' => array($definition)));
            }
        }
        if (!isset($this->schema['properties']) || !is_array($this->schema['properties'])) {
            $this->addError('schema_manifest_properties_missing');
        }
        if (!isset($this->schema['$defs']['dataset']['properties']) || !is_array($this->schema['$defs']['dataset']['properties'])) {
            $this->addError('schema_dataset_properties_missing');
        }
    }

    private function validateManifestStructure()
    {
        if (!is_object($this->manifestTyped)) {
            $this->addError('manifest_root_not_object');
            return;
        }
        $allowedTop = isset($this->schema['properties']) && is_array($this->schema['properties'])
            ? array_keys($this->schema['properties'])
            : $this->rules['required_manifest_fields'];
        $this->checkRequiredAndUnknown(
            $this->manifest,
            $this->rules['required_manifest_fields'],
            $allowedTop,
            'manifest_required_field_missing',
            'manifest_unknown_field'
        );

        if (!isset($this->manifest['package_status']) || !in_array($this->manifest['package_status'], $this->rules['canonical_statuses'], true)) {
            $this->addError('package_status_invalid');
        }
        foreach (array('production_ready', 'runtime_enabled', 'contains_patient_data', 'contains_personal_data', 'contains_credentials') as $field) {
            if (!array_key_exists($field, $this->manifest) || !is_bool($this->manifest[$field])) {
                $this->addError('manifest_boolean_invalid', array('fields' => array($field)));
            }
        }
        $typedDatasets = property_exists($this->manifestTyped, 'datasets') ? $this->manifestTyped->datasets : null;
        if (!isset($this->manifest['datasets']) || !is_array($this->manifest['datasets']) || !is_array($typedDatasets)) {
            $this->addError('dataset_list_invalid', array('fields' => array('datasets')));
            return;
        }
        $typedSubmanifests = property_exists($this->manifestTyped, 'submanifests') ? $this->manifestTyped->submanifests : null;
        if (!isset($this->manifest['submanifests']) || !is_array($this->manifest['submanifests']) || !is_array($typedSubmanifests)) {
            $this->addError('submanifest_list_invalid', array('fields' => array('submanifests')));
        }
        if (isset($this->manifest['package_summary']) && is_array($this->manifest['package_summary'])) {
            if (!property_exists($this->manifestTyped, 'package_summary') || !is_object($this->manifestTyped->package_summary)) {
                $this->addError('package_summary_not_object', array('fields' => array('package_summary')));
            }
            $summaryAllowed = isset($this->schema['properties']['package_summary']['properties'])
                ? array_keys($this->schema['properties']['package_summary']['properties'])
                : array('product_name', 'dataset_count', 'record_count', 'master_reference_only');
            $summaryRequired = isset($this->schema['properties']['package_summary']['required'])
                ? $this->schema['properties']['package_summary']['required']
                : $summaryAllowed;
            $this->checkRequiredAndUnknown($this->manifest['package_summary'], $summaryRequired, $summaryAllowed, 'package_summary_field_missing', 'package_summary_unknown_field');
            if (!isset($this->manifest['package_summary']['master_reference_only'])
                || !is_bool($this->manifest['package_summary']['master_reference_only'])) {
                $this->addError('package_summary_boolean_invalid', array('fields' => array('master_reference_only')));
            }
        }

        $datasetAllowed = isset($this->schema['$defs']['dataset']['properties'])
            ? array_keys($this->schema['$defs']['dataset']['properties'])
            : $this->rules['required_dataset_fields'];
        $ids = array();
        $paths = array();
        foreach ($this->manifest['datasets'] as $index => $dataset) {
            $typedDataset = isset($typedDatasets[$index]) ? $typedDatasets[$index] : null;
            if (!is_array($dataset) || !$this->isAssociative($dataset) || !is_object($typedDataset)) {
                $this->addError('dataset_entry_not_object', array('record_index' => $index));
                continue;
            }
            $datasetId = isset($dataset['dataset_id']) && is_string($dataset['dataset_id']) ? $dataset['dataset_id'] : null;
            $path = isset($dataset['path']) && is_string($dataset['path']) ? $dataset['path'] : null;
            $context = array('dataset_id' => $datasetId, 'path' => $path);
            $this->checkRequiredAndUnknown(
                $dataset,
                $this->rules['required_dataset_fields'],
                $datasetAllowed,
                'dataset_required_field_missing',
                'dataset_unknown_field',
                $context
            );
            if ($datasetId === null || $datasetId === '') {
                $this->addError('dataset_id_invalid', $context);
            } elseif (isset($ids[$datasetId])) {
                $this->addError('dataset_id_duplicate', $context);
            } else {
                $ids[$datasetId] = true;
                $this->datasetById[$datasetId] = $dataset;
            }
            if ($path === null || !$this->isSafeRelativePath($path)) {
                $this->addError('dataset_path_invalid', $context);
            } elseif (isset($paths[$path])) {
                $this->addError('dataset_path_duplicate', $context);
            } else {
                $paths[$path] = true;
            }
            if (!isset($dataset['sha256']) || !is_string($dataset['sha256']) || preg_match('/^[0-9a-f]{64}$/D', $dataset['sha256']) !== 1) {
                $this->addError('dataset_sha256_invalid', $context);
            }
            if (!isset($dataset['record_count']) || !$this->isNonNegativeInteger($dataset['record_count'])) {
                $this->addError('dataset_record_count_invalid', $context);
            }
            if (!isset($dataset['seed_order']) || !$this->isNonNegativeInteger($dataset['seed_order'])) {
                $this->addError('dataset_seed_order_invalid', $context);
            }
            if (!isset($dataset['unique_key']) || !$this->isValidUniqueKey($dataset['unique_key'])) {
                $this->addError('dataset_unique_key_invalid', $context);
            }
            if (!property_exists($typedDataset, 'unique_key')
                || (!is_string($typedDataset->unique_key) && !is_array($typedDataset->unique_key))) {
                $this->addError('dataset_unique_key_type_invalid', $context);
            }
            if (!property_exists($typedDataset, 'dependencies') || !is_array($typedDataset->dependencies)) {
                $this->addError('dataset_dependencies_type_invalid', $context);
            }
            if (!property_exists($typedDataset, 'canonical_status_source') || !is_object($typedDataset->canonical_status_source)) {
                $this->addError('canonical_status_source_not_object', $context);
            }
            if (property_exists($typedDataset, 'dependency_details') && !is_array($typedDataset->dependency_details)) {
                $this->addError('dependency_details_type_invalid', $context);
            }
            if (!isset($dataset['review_type']) || !in_array($dataset['review_type'], $this->rules['review_types'], true)) {
                $this->addError('dataset_review_type_invalid', $context);
            }
            if (!isset($dataset['canonical_review_status']) || !in_array($dataset['canonical_review_status'], $this->rules['canonical_statuses'], true)) {
                $this->addError('dataset_canonical_status_invalid', $context);
            }
            foreach (array('runtime_enabled', 'runtime_selectable', 'clinical_review_required', 'pharmacy_review_required', 'master_reference_only') as $field) {
                if (!array_key_exists($field, $dataset) || !is_bool($dataset[$field])) {
                    $this->addError('dataset_boolean_invalid', $context + array('fields' => array($field)));
                }
            }
            if (isset($dataset['runtime_enabled'], $dataset['canonical_review_status'])
                && $dataset['runtime_enabled'] === true && $dataset['canonical_review_status'] !== 'approved') {
                $this->addError('runtime_enabled_dataset_not_approved', $context);
            }
            if (isset($dataset['runtime_selectable'], $dataset['canonical_review_status'])
                && $dataset['runtime_selectable'] === true && $dataset['canonical_review_status'] !== 'approved') {
                $this->addError('runtime_selectable_dataset_not_approved', $context);
            }
        }

        $this->validateSubmanifestDeclarations();
        if (isset($this->manifest['package_summary']) && is_array($this->manifest['package_summary'])) {
            $summary = $this->manifest['package_summary'];
            if (!isset($summary['dataset_count']) || !$this->isNonNegativeInteger($summary['dataset_count'])) {
                $this->addError('package_dataset_count_invalid');
            } elseif ($summary['dataset_count'] !== count($this->manifest['datasets'])) {
                $this->addError('package_dataset_count_mismatch', array('expected_count' => $summary['dataset_count'], 'actual_count' => count($this->manifest['datasets'])));
            }
            if (!isset($summary['record_count']) || !$this->isNonNegativeInteger($summary['record_count'])) {
                $this->addError('package_record_count_invalid');
            }
        }
    }

    private function validateSubmanifestDeclarations()
    {
        if (!isset($this->manifest['submanifests']) || !is_array($this->manifest['submanifests'])) {
            return;
        }
        $typedEntries = is_object($this->manifestTyped) && property_exists($this->manifestTyped, 'submanifests')
            && is_array($this->manifestTyped->submanifests) ? $this->manifestTyped->submanifests : array();
        $allowed = isset($this->schema['$defs']['submanifest']['properties'])
            ? array_keys($this->schema['$defs']['submanifest']['properties'])
            : array('submanifest_id', 'path', 'manifest_type', 'sha256', 'dataset_count', 'record_count', 'dataset_ids');
        $required = isset($this->schema['$defs']['submanifest']['required'])
            ? $this->schema['$defs']['submanifest']['required']
            : $allowed;
        $ids = array();
        $paths = array();
        foreach ($this->manifest['submanifests'] as $index => $entry) {
            $typedEntry = isset($typedEntries[$index]) ? $typedEntries[$index] : null;
            if (!is_array($entry) || !$this->isAssociative($entry) || !is_object($typedEntry)) {
                $this->addError('submanifest_entry_not_object', array('record_index' => $index));
                continue;
            }
            $id = isset($entry['submanifest_id']) && is_string($entry['submanifest_id']) ? $entry['submanifest_id'] : null;
            $path = isset($entry['path']) && is_string($entry['path']) ? $entry['path'] : null;
            $context = array('dataset_id' => $id, 'path' => $path);
            $this->checkRequiredAndUnknown($entry, $required, $allowed, 'submanifest_required_field_missing', 'submanifest_unknown_field', $context);
            if ($id === null || $id === '' || isset($ids[$id])) {
                $this->addError($id !== null && isset($ids[$id]) ? 'submanifest_id_duplicate' : 'submanifest_id_invalid', $context);
            } else {
                $ids[$id] = true;
            }
            if ($path === null || !$this->isSafeRelativePath($path)) {
                $this->addError('submanifest_path_invalid', $context);
            } elseif (isset($paths[$path])) {
                $this->addError('submanifest_path_duplicate', $context);
            } else {
                $paths[$path] = true;
            }
            if (!isset($entry['sha256']) || !is_string($entry['sha256']) || preg_match('/^[0-9a-f]{64}$/D', $entry['sha256']) !== 1) {
                $this->addError('submanifest_sha256_invalid', $context);
            }
            foreach (array('dataset_count', 'record_count') as $field) {
                if (!isset($entry[$field]) || !$this->isNonNegativeInteger($entry[$field])) {
                    $this->addError('submanifest_count_invalid', $context + array('fields' => array($field)));
                }
            }
            if (!isset($entry['dataset_ids']) || !is_array($entry['dataset_ids']) || count($entry['dataset_ids']) === 0) {
                $this->addError('submanifest_dataset_ids_invalid', $context);
            } elseif (!property_exists($typedEntry, 'dataset_ids') || !is_array($typedEntry->dataset_ids)) {
                $this->addError('submanifest_dataset_ids_type_invalid', $context);
            } elseif (count($entry['dataset_ids']) !== count(array_unique($entry['dataset_ids'], SORT_STRING))) {
                $this->addError('submanifest_dataset_ids_duplicate', $context);
            }
        }
    }

    private function validateGovernance()
    {
        $typedGovernance = is_object($this->manifestTyped) && property_exists($this->manifestTyped, 'review_governance')
            ? $this->manifestTyped->review_governance : null;
        if (!isset($this->manifest['review_governance']) || !is_array($this->manifest['review_governance']) || !is_object($typedGovernance)) {
            $this->addError('review_governance_missing');
            return;
        }
        $governance = $this->manifest['review_governance'];
        $governanceAllowed = isset($this->schema['$defs']['reviewGovernance']['properties'])
            ? array_keys($this->schema['$defs']['reviewGovernance']['properties'])
            : array('canonical_statuses', 'source_status_mappings', 'default_when_missing', 'principles');
        $governanceRequired = isset($this->schema['$defs']['reviewGovernance']['required'])
            ? $this->schema['$defs']['reviewGovernance']['required']
            : $governanceAllowed;
        $this->checkRequiredAndUnknown($governance, $governanceRequired, $governanceAllowed, 'review_governance_field_missing', 'review_governance_unknown_field');
        if (!isset($governance['canonical_statuses']) || $governance['canonical_statuses'] !== $this->rules['canonical_statuses']
            || !property_exists($typedGovernance, 'canonical_statuses') || !is_array($typedGovernance->canonical_statuses)) {
            $this->addError('canonical_status_order_invalid');
        }
        if (!isset($governance['source_status_mappings']) || !is_array($governance['source_status_mappings'])
            || !property_exists($typedGovernance, 'source_status_mappings') || !is_array($typedGovernance->source_status_mappings)) {
            $this->addError('source_status_mappings_invalid');
            return;
        }

        $required = array('source_field', 'source_value', 'canonical_status', 'requires_domain_review', 'review_types', 'notes');
        $allowed = $required;
        if (isset($this->schema['$defs']['sourceStatusMapping']['properties'])) {
            $allowed = array_keys($this->schema['$defs']['sourceStatusMapping']['properties']);
        }
        foreach ($governance['source_status_mappings'] as $index => $mapping) {
            $typedMapping = isset($typedGovernance->source_status_mappings[$index])
                ? $typedGovernance->source_status_mappings[$index] : null;
            if (!is_array($mapping) || !$this->isAssociative($mapping) || !is_object($typedMapping)) {
                $this->addError('source_mapping_not_object', array('record_index' => $index));
                continue;
            }
            $this->checkRequiredAndUnknown($mapping, $required, $allowed, 'source_mapping_required_field_missing', 'source_mapping_unknown_field', array('record_index' => $index));
            $field = isset($mapping['source_field']) && is_string($mapping['source_field']) ? $mapping['source_field'] : null;
            $value = isset($mapping['source_value']) && is_string($mapping['source_value']) ? $mapping['source_value'] : null;
            if ($field === null || $field === '' || $value === null || $value === '') {
                $this->addError('source_mapping_context_invalid', array('record_index' => $index));
                continue;
            }
            $key = $this->encodeComponents(array($field, $value));
            if (isset($this->mappingIndex[$key])) {
                $this->mappingIndex[$key]['count']++;
                $this->addError('source_mapping_ambiguous', array('record_index' => $index, 'fields' => array('source_field', 'source_value')));
            } else {
                $this->mappingIndex[$key] = array('count' => 1, 'mapping' => $mapping);
            }
            if (!isset($mapping['canonical_status']) || !in_array($mapping['canonical_status'], $this->rules['canonical_statuses'], true)) {
                $this->addError('source_mapping_canonical_status_invalid', array('record_index' => $index));
            }
            if (!isset($mapping['requires_domain_review']) || !is_bool($mapping['requires_domain_review'])) {
                $this->addError('source_mapping_review_boolean_invalid', array('record_index' => $index));
            }
            if (!isset($mapping['review_types']) || !is_array($mapping['review_types']) || count($mapping['review_types']) === 0) {
                $this->addError('source_mapping_review_types_invalid', array('record_index' => $index));
            } elseif (!property_exists($typedMapping, 'review_types') || !is_array($typedMapping->review_types)) {
                $this->addError('source_mapping_review_types_type_invalid', array('record_index' => $index));
            } else {
                if (count($mapping['review_types']) !== count(array_unique($mapping['review_types'], SORT_STRING))) {
                    $this->addError('source_mapping_review_types_duplicate', array('record_index' => $index));
                }
                foreach ($mapping['review_types'] as $reviewType) {
                    if (!in_array($reviewType, $this->rules['review_types'], true)) {
                        $this->addError('source_mapping_review_type_invalid', array('record_index' => $index));
                    }
                }
            }
            if (!isset($mapping['notes']) || !is_string($mapping['notes']) || trim($mapping['notes']) === '') {
                $this->addError('source_mapping_notes_invalid', array('record_index' => $index));
            }
            if ($value === 'not_available' && $field !== 'status_terjemahan_indonesia') {
                $this->addError('not_available_mapping_not_translation_scoped', array('record_index' => $index, 'fields' => array($field)));
            }
            if ($field === 'status' && $value === 'not_available') {
                $this->addError('generic_status_not_available_prohibited', array('record_index' => $index));
            }
        }

        $notAvailableKey = $this->encodeComponents(array('status_terjemahan_indonesia', 'not_available'));
        if (!isset($this->mappingIndex[$notAvailableKey]) || $this->mappingIndex[$notAvailableKey]['count'] !== 1) {
            $this->addError('translation_not_available_mapping_missing');
        }
        $defaultAllowed = array('canonical_status', 'requires_domain_review', 'notes');
        if (!isset($governance['default_when_missing']) || !is_array($governance['default_when_missing'])
            || !property_exists($typedGovernance, 'default_when_missing') || !is_object($typedGovernance->default_when_missing)) {
            $this->addError('missing_review_default_invalid');
        } else {
            $this->checkRequiredAndUnknown($governance['default_when_missing'], $defaultAllowed, $defaultAllowed, 'missing_review_default_field_missing', 'missing_review_default_unknown_field');
        }
        if (!isset($governance['default_when_missing']['canonical_status'])
            || $governance['default_when_missing']['canonical_status'] === 'approved'
            || !isset($governance['default_when_missing']['requires_domain_review'])
            || $governance['default_when_missing']['requires_domain_review'] !== true
            || !isset($governance['default_when_missing']['notes'])
            || !is_string($governance['default_when_missing']['notes'])
            || trim($governance['default_when_missing']['notes']) === '') {
            $this->addError('missing_review_status_must_be_nonapproved');
        }

        foreach ($this->manifest['datasets'] as $dataset) {
            if (!is_array($dataset) || !isset($dataset['dataset_id'])) {
                continue;
            }
            $context = array('dataset_id' => $dataset['dataset_id'], 'path' => isset($dataset['path']) ? $dataset['path'] : null);
            if (!isset($dataset['canonical_status_source']) || !is_array($dataset['canonical_status_source'])) {
                $this->addError('dataset_canonical_status_source_missing', $context);
                continue;
            }
            $source = $dataset['canonical_status_source'];
            $allowedSource = array('field', 'value');
            $this->checkRequiredAndUnknown($source, $allowedSource, $allowedSource, 'canonical_status_source_field_missing', 'canonical_status_source_unknown_field', $context);
            if (!isset($source['field'], $source['value']) || !is_string($source['field']) || !is_string($source['value'])
                || $source['field'] === '' || $source['value'] === '') {
                $this->addError('dataset_canonical_status_source_invalid', $context);
                continue;
            }
            $key = $this->encodeComponents(array($source['field'], $source['value']));
            if (!isset($this->mappingIndex[$key]) || $this->mappingIndex[$key]['count'] !== 1) {
                $this->addError('dataset_canonical_status_source_unresolved', $context);
                continue;
            }
            $resolved = isset($this->mappingIndex[$key]['mapping']['canonical_status'])
                ? $this->mappingIndex[$key]['mapping']['canonical_status']
                : null;
            if (!isset($dataset['canonical_review_status']) || $resolved !== $dataset['canonical_review_status']) {
                $this->addError('dataset_canonical_status_resolution_mismatch', $context);
            }
            $sourceName = isset($dataset['source']) && is_string($dataset['source']) ? $dataset['source'] : '';
            if ((stripos($sourceName, 'WHO') !== false || stripos($sourceName, 'Fornas') !== false)
                && isset($dataset['canonical_review_status']) && $dataset['canonical_review_status'] === 'approved') {
                $this->addError('provenance_does_not_imply_approval', $context);
            }
        }
    }

    private function validateDependencies()
    {
        $expected = array(
            'dependencies_used_for_dataset_existence_validation' => true,
            'dependencies_used_for_seed_ordering' => true,
            'dependencies_automatically_define_database_foreign_keys' => false,
            'record_level_referential_validation_requires_explicit_validator_or_import_rules' => true,
            'contextual_review_dependencies_require_complete_reference_coverage' => false,
            'automatic_foreign_key_inference_prohibited' => true,
        );
        $typedPolicy = is_object($this->manifestTyped) && property_exists($this->manifestTyped, 'dependency_policy')
            ? $this->manifestTyped->dependency_policy : null;
        if (!isset($this->manifest['dependency_policy']) || !is_array($this->manifest['dependency_policy']) || !is_object($typedPolicy)) {
            $this->addError('dependency_policy_missing');
        } else {
            $policy = $this->manifest['dependency_policy'];
            $this->checkRequiredAndUnknown($policy, array_keys($expected), array_keys($expected), 'dependency_policy_field_missing', 'dependency_policy_unknown_field');
            foreach ($expected as $field => $value) {
                if (!array_key_exists($field, $policy) || !is_bool($policy[$field]) || $policy[$field] !== $value) {
                    $this->addError('dependency_policy_value_invalid', array('fields' => array($field)));
                }
            }
        }

        $graph = array();
        $typedDatasets = is_object($this->manifestTyped) && property_exists($this->manifestTyped, 'datasets')
            && is_array($this->manifestTyped->datasets) ? $this->manifestTyped->datasets : array();
        foreach ($this->manifest['datasets'] as $datasetIndex => $dataset) {
            if (!is_array($dataset) || !isset($dataset['dataset_id']) || !is_string($dataset['dataset_id'])) {
                continue;
            }
            $typedDataset = isset($typedDatasets[$datasetIndex]) ? $typedDatasets[$datasetIndex] : null;
            $id = $dataset['dataset_id'];
            $graph[$id] = array();
            if (!isset($dataset['dependencies']) || !is_array($dataset['dependencies'])
                || !is_object($typedDataset) || !property_exists($typedDataset, 'dependencies') || !is_array($typedDataset->dependencies)) {
                $this->addError('dataset_dependencies_invalid', array('dataset_id' => $id));
                continue;
            }
            if (count($dataset['dependencies']) !== count(array_unique($dataset['dependencies'], SORT_REGULAR))) {
                $this->addError('dataset_dependencies_duplicate', array('dataset_id' => $id));
            }
            foreach ($dataset['dependencies'] as $dependency) {
                if (!is_string($dependency) || $dependency === '') {
                    $this->addError('dependency_id_invalid', array('dataset_id' => $id));
                    continue;
                }
                $graph[$id][] = $dependency;
                if ($dependency === $id) {
                    $this->addError('dependency_self_reference', array('dataset_id' => $id));
                }
                if (!isset($this->datasetById[$dependency])) {
                    $this->addError('dependency_dataset_missing', array('dataset_id' => $id));
                    continue;
                }
                if (isset($dataset['seed_order'], $this->datasetById[$dependency]['seed_order'])
                    && is_int($dataset['seed_order']) && is_int($this->datasetById[$dependency]['seed_order'])
                    && $this->datasetById[$dependency]['seed_order'] >= $dataset['seed_order']) {
                    $this->addError('dependency_seed_order_invalid', array('dataset_id' => $id));
                }
            }
            if (isset($dataset['dependency_details']) && (!is_array($dataset['dependency_details'])
                || !is_object($typedDataset) || !property_exists($typedDataset, 'dependency_details') || !is_array($typedDataset->dependency_details))) {
                $this->addError('dependency_details_invalid', array('dataset_id' => $id));
            } elseif (isset($dataset['dependency_details']) && is_array($dataset['dependency_details'])) {
                $detailAllowed = isset($this->schema['$defs']['dependencyDetail']['properties'])
                    ? array_keys($this->schema['$defs']['dependencyDetail']['properties'])
                    : array('dataset_id', 'dependency_type', 'dependent_field', 'dependent_value_must_exist_in_dependency', 'import_into_dependency_dataset', 'automatic_foreign_key', 'notes');
                $detailRequired = isset($this->schema['$defs']['dependencyDetail']['required'])
                    ? $this->schema['$defs']['dependencyDetail']['required']
                    : $detailAllowed;
                foreach ($dataset['dependency_details'] as $detailIndex => $detail) {
                    $typedDetail = isset($typedDataset->dependency_details[$detailIndex])
                        ? $typedDataset->dependency_details[$detailIndex] : null;
                    if (!is_array($detail) || !$this->isAssociative($detail) || !is_object($typedDetail)) {
                        $this->addError('dependency_detail_not_object', array('dataset_id' => $id, 'record_index' => $detailIndex));
                        continue;
                    }
                    $this->checkRequiredAndUnknown($detail, $detailRequired, $detailAllowed, 'dependency_detail_field_missing', 'dependency_detail_unknown_field', array('dataset_id' => $id, 'record_index' => $detailIndex));
                    foreach (array('dependent_value_must_exist_in_dependency', 'import_into_dependency_dataset', 'automatic_foreign_key') as $booleanField) {
                        if (!array_key_exists($booleanField, $detail) || !is_bool($detail[$booleanField])) {
                            $this->addError('dependency_detail_boolean_invalid', array('dataset_id' => $id, 'record_index' => $detailIndex, 'fields' => array($booleanField)));
                        }
                    }
                    if (isset($detail['automatic_foreign_key']) && $detail['automatic_foreign_key'] !== false) {
                        $this->addError('dependency_detail_automatic_fk_prohibited', array('dataset_id' => $id));
                    }
                }
            }
        }
        $this->detectDependencyCycles($graph);
        $this->validateLegacyDependencyPolicy();
    }

    private function validateLegacyDependencyPolicy()
    {
        $legacyId = $this->rules['icd']['legacy_dataset'];
        $diagnosisId = $this->rules['icd']['diagnosis_dataset'];
        if (!isset($this->datasetById[$legacyId])) {
            $this->addError('legacy_review_dataset_missing', array('dataset_id' => $legacyId));
            return;
        }
        $legacy = $this->datasetById[$legacyId];
        if (!isset($legacy['dependencies']) || !in_array($diagnosisId, $legacy['dependencies'], true)) {
            $this->addError('legacy_context_dependency_missing', array('dataset_id' => $legacyId));
        }
        if (!isset($legacy['runtime_enabled']) || $legacy['runtime_enabled'] !== false) {
            $this->addError('legacy_runtime_enabled_prohibited', array('dataset_id' => $legacyId));
        }
        if (!isset($legacy['runtime_selectable']) || $legacy['runtime_selectable'] !== false) {
            $this->addError('legacy_runtime_selectable_prohibited', array('dataset_id' => $legacyId));
        }
        $matched = false;
        if (isset($legacy['dependency_details']) && is_array($legacy['dependency_details'])) {
            foreach ($legacy['dependency_details'] as $detail) {
                if (!is_array($detail) || !isset($detail['dataset_id']) || $detail['dataset_id'] !== $diagnosisId) {
                    continue;
                }
                $matched = true;
                $expected = array(
                    'dependency_type' => 'contextual_review_and_seed_order',
                    'dependent_field' => 'legacy_code',
                    'dependent_value_must_exist_in_dependency' => false,
                    'import_into_dependency_dataset' => false,
                    'automatic_foreign_key' => false,
                );
                foreach ($expected as $field => $value) {
                    if (!array_key_exists($field, $detail) || $detail[$field] !== $value) {
                        $this->addError('legacy_dependency_semantics_invalid', array('dataset_id' => $legacyId, 'fields' => array($field)));
                    }
                }
            }
        }
        if (!$matched) {
            $this->addError('legacy_dependency_detail_missing', array('dataset_id' => $legacyId));
        }
    }

    private function validateDatasetsSequentially()
    {
        $targetSpecs = $this->buildTargetSpecifications();
        $actualRecordTotal = 0;
        $datasetActualCounts = array();
        $this->relationSources = array();

        foreach ($this->manifest['datasets'] as $dataset) {
            if (!is_array($dataset) || !isset($dataset['dataset_id'], $dataset['path'])
                || !is_string($dataset['dataset_id']) || !is_string($dataset['path'])) {
                continue;
            }
            $id = $dataset['dataset_id'];
            $path = $dataset['path'];
            $context = array('dataset_id' => $id, 'path' => $path);
            if (!$this->isSafeRelativePath($path)) {
                continue;
            }
            $absolute = $this->resolveDeclaredFile($path);
            if ($absolute === null) {
                $this->addError('dataset_file_missing_or_escaped', $context);
                continue;
            }
            $bytes = $this->readAbsoluteFile($absolute, 'dataset_file_read_failed', $context);
            if ($bytes === null) {
                continue;
            }
            if (!isset($dataset['sha256']) || hash('sha256', $bytes) !== $dataset['sha256']) {
                $this->addError('dataset_checksum_mismatch', $context);
            }
            if (substr($bytes, 0, 3) === "\xEF\xBB\xBF") {
                $this->addError('dataset_utf8_bom_prohibited', $context);
                unset($bytes);
                continue;
            }
            if (preg_match('//u', $bytes) !== 1) {
                $this->addError('dataset_utf8_invalid', $context);
                unset($bytes);
                continue;
            }
            $typedDecoded = json_decode($bytes);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('dataset_json_invalid', $context);
                unset($bytes, $typedDecoded);
                continue;
            }
            $decoded = json_decode($bytes, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError('dataset_json_invalid', $context);
                unset($bytes, $typedDecoded, $decoded);
                continue;
            }
            unset($bytes);

            $records = null;
            $typedRecords = null;
            if (isset($dataset['json_root_type']) && $dataset['json_root_type'] === 'array') {
                if (!is_array($typedDecoded) || !is_array($decoded)) {
                    $this->addError('dataset_root_type_mismatch', $context);
                } else {
                    $records = $decoded;
                    $typedRecords = $typedDecoded;
                }
            } elseif (isset($dataset['json_root_type']) && $dataset['json_root_type'] === 'object_with_data_array') {
                if (!is_object($typedDecoded) || !property_exists($typedDecoded, 'data') || !is_array($typedDecoded->data)
                    || !is_array($decoded) || !array_key_exists('data', $decoded) || !is_array($decoded['data'])) {
                    $this->addError('dataset_root_type_mismatch', $context);
                } else {
                    $records = $decoded['data'];
                    $typedRecords = $typedDecoded->data;
                }
            } else {
                $this->addError('dataset_declared_root_type_invalid', $context);
            }
            if ($records === null) {
                unset($decoded, $typedDecoded);
                continue;
            }

            $actualCount = count($records);
            $datasetActualCounts[$id] = $actualCount;
            $actualRecordTotal += $actualCount;
            if (!isset($dataset['record_count']) || !is_int($dataset['record_count']) || $dataset['record_count'] !== $actualCount) {
                $this->addError('dataset_record_count_mismatch', $context + array(
                    'expected_count' => isset($dataset['record_count']) && is_int($dataset['record_count']) ? $dataset['record_count'] : null,
                    'actual_count' => $actualCount,
                ));
            }

            $uniqueFields = $this->normaliseUniqueKey(isset($dataset['unique_key']) ? $dataset['unique_key'] : null);
            $seenKeys = array();
            if (isset($targetSpecs[$id])) {
                foreach ($targetSpecs[$id] as $signature => $fields) {
                    $this->indexes[$id][$signature] = array();
                }
            }
            $sourceRules = $this->rulesForSource($id);

            foreach ($records as $recordIndex => $record) {
                $typedRecord = isset($typedRecords[$recordIndex]) ? $typedRecords[$recordIndex] : null;
                if (!is_array($record) || !$this->isAssociative($record) || !is_object($typedRecord)) {
                    $this->addError('dataset_record_not_object', $context + array('record_index' => $recordIndex));
                    continue;
                }
                if ($uniqueFields !== null) {
                    $components = $this->extractComponents($record, $uniqueFields, false, $id, $recordIndex, 'unique_key');
                    if ($components !== null) {
                        $encoded = $this->encodeComponents($components);
                        if (isset($seenKeys[$encoded])) {
                            $this->addError('dataset_unique_key_duplicate', $context + array('record_index' => $recordIndex, 'fields' => $uniqueFields));
                        } else {
                            $seenKeys[$encoded] = true;
                        }
                    }
                }
                if (isset($this->rules['required_local_fields'][$id])) {
                    foreach ($this->rules['required_local_fields'][$id] as $field) {
                        if (!array_key_exists($field, $record)) {
                            $this->addError('required_local_field_missing', $context + array('record_index' => $recordIndex, 'fields' => array($field)));
                        }
                    }
                }
                if (isset($targetSpecs[$id])) {
                    foreach ($targetSpecs[$id] as $signature => $fields) {
                        $components = $this->extractComponents($record, $fields, true, $id, $recordIndex, 'target_index');
                        if ($components !== null) {
                            $this->indexes[$id][$signature][$this->encodeComponents($components)] = true;
                        }
                    }
                }
                foreach ($sourceRules as $sourceRule) {
                    $this->collectRelationRecord($sourceRule['index'], $sourceRule['rule'], $record, $recordIndex);
                }
                if ($id === $this->rules['icd']['diagnosis_dataset']) {
                    $this->collectIcdNode($record, $recordIndex, $dataset);
                }
            }

            if (isset($dataset['canonical_status_source']['field'], $dataset['canonical_status_source']['value'])) {
                if (!$this->containsFieldValue($decoded, $dataset['canonical_status_source']['field'], $dataset['canonical_status_source']['value'])) {
                    $this->addError('dataset_status_source_not_found_in_file', $context + array('fields' => array($dataset['canonical_status_source']['field'])));
                }
            }
            unset($seenKeys, $records, $typedRecords, $decoded, $typedDecoded);
            if (function_exists('gc_collect_cycles') && memory_get_usage(true) > 67108864) {
                gc_collect_cycles();
            }
        }

        $this->diagnostics['actual_dataset_count'] = count($datasetActualCounts);
        $this->diagnostics['actual_record_count'] = $actualRecordTotal;
        if (isset($this->manifest['package_summary']['record_count'])
            && is_int($this->manifest['package_summary']['record_count'])
            && $this->manifest['package_summary']['record_count'] !== $actualRecordTotal) {
            $this->addError('package_record_count_mismatch', array(
                'expected_count' => $this->manifest['package_summary']['record_count'],
                'actual_count' => $actualRecordTotal,
            ));
        }
    }

    private function collectRelationRecord($ruleIndex, array $rule, array $record, $recordIndex)
    {
        $type = $rule['relation_type'];
        if ($type === 'icd_diagnosis_parent') {
            return;
        }
        if ($rule['missing_value_policy'] === 'allow_missing_or_null') {
            $components = array();
            foreach ($rule['source_fields'] as $field) {
                if (!array_key_exists($field, $record) || $record[$field] === null) {
                    return;
                }
                if (!is_string($record[$field]) || $record[$field] === '') {
                    $this->addError('optional_relation_source_invalid', array(
                        'dataset_id' => $rule['source_dataset'],
                        'record_index' => $recordIndex,
                        'fields' => array($field),
                        'relation_type' => $type,
                    ));
                    return;
                }
                $components[] = $record[$field];
            }
        } else {
            $components = $this->extractComponents(
                $record,
                $rule['source_fields'],
                false,
                $rule['source_dataset'],
                $recordIndex,
                'relation_source'
            );
        }
        if ($components === null) {
            return;
        }
        if ($type === 'contextual_review') {
            return;
        }
        if (!isset($this->relationSources[$ruleIndex])) {
            $this->relationSources[$ruleIndex] = array();
        }
        $this->relationSources[$ruleIndex][] = array(
            'record_index' => $recordIndex,
            'components' => $components,
        );
    }

    private function validateCollectedRelations()
    {
        $counters = array(
            'icd_alias_orphan_count' => 0,
            'icd_orphan_block_count' => 0,
            'icd_orphan_block_reference_count' => 0,
            'icd_orphan_chapter_reference_count' => 0,
            'relation_orphan_count' => 0,
        );
        foreach ($this->rules['relations'] as $ruleIndex => $rule) {
            if (!isset($this->relationSources[$ruleIndex])) {
                continue;
            }
            $signature = $this->fieldSignature($rule['target_fields']);
            foreach ($this->relationSources[$ruleIndex] as $source) {
                $targetExists = isset($this->indexes[$rule['target_dataset']][$signature]);
                $found = $targetExists && isset($this->indexes[$rule['target_dataset']][$signature][$this->encodeComponents($source['components'])]);
                if (!$found && $rule['strict'] === true) {
                    $this->addError('explicit_relation_orphan', array(
                        'dataset_id' => $rule['source_dataset'],
                        'record_index' => $source['record_index'],
                        'fields' => $rule['source_fields'],
                        'relation_type' => $rule['relation_type'],
                    ));
                    $counters['relation_orphan_count']++;
                    if ($rule['relation_type'] === 'icd_block_chapter_reference') {
                        $counters['icd_orphan_block_count']++;
                    } elseif ($rule['relation_type'] === 'icd_diagnosis_chapter_reference') {
                        $counters['icd_orphan_chapter_reference_count']++;
                    } elseif ($rule['relation_type'] === 'icd_diagnosis_block_reference') {
                        $counters['icd_orphan_block_reference_count']++;
                    } elseif ($rule['relation_type'] === 'icd_alias_reference') {
                        $counters['icd_alias_orphan_count']++;
                    }
                }
            }
        }
        foreach ($counters as $key => $value) {
            $this->diagnostics[$key] = $value;
        }
        $this->relationSources = array();
    }

    private function collectIcdNode(array $record, $recordIndex, array $dataset)
    {
        $required = array('kode_icd10', 'level', 'parent_code', 'block_code', 'chapter_code', 'aktif', 'is_leaf', 'dapat_dipilih_dalam_diagnosis');
        foreach ($required as $field) {
            if (!array_key_exists($field, $record)) {
                $this->addError('icd_hierarchy_field_missing', array(
                    'dataset_id' => $dataset['dataset_id'],
                    'record_index' => $recordIndex,
                    'fields' => array($field),
                ));
                return;
            }
        }
        $code = $record['kode_icd10'];
        if (!is_string($code) || $code === '') {
            return;
        }
        if (isset($this->icdNodes[$code])) {
            $this->icdDuplicateCodes++;
        }
        $this->icdNodes[$code] = array(
            'block' => $record['block_code'],
            'chapter' => $record['chapter_code'],
            'level' => $record['level'],
            'parent' => $record['parent_code'],
            'active' => $record['aktif'] === true,
            'leaf' => $record['is_leaf'] === true,
            'selectable' => $record['dapat_dipilih_dalam_diagnosis'] === true,
            'record_index' => $recordIndex,
        );
    }

    private function validateIcdHierarchy()
    {
        $diagnosisId = $this->rules['icd']['diagnosis_dataset'];
        $orphanParents = 0;
        $selfParents = 0;
        $hierarchyErrors = 0;
        $structuralLeafSelectable = 0;
        $parentEdges = array();
        foreach ($this->icdNodes as $code => $node) {
            if ($node['active'] && $node['leaf'] && $node['selectable']) {
                $structuralLeafSelectable++;
            }
            if ($node['parent'] === $code) {
                $selfParents++;
                $this->addError('icd_self_parent', array('dataset_id' => $diagnosisId, 'record_index' => $node['record_index'], 'fields' => array('parent_code')));
            }
            if ($node['level'] === 'category') {
                if ($node['parent'] !== $node['block']) {
                    $hierarchyErrors++;
                    $this->addError('icd_category_parent_not_block', array('dataset_id' => $diagnosisId, 'record_index' => $node['record_index'], 'fields' => array('parent_code', 'block_code')));
                }
            } elseif ($node['level'] === 'subcategory') {
                if (!isset($this->icdNodes[$node['parent']])) {
                    $orphanParents++;
                    $this->addError('icd_parent_diagnosis_missing', array('dataset_id' => $diagnosisId, 'record_index' => $node['record_index'], 'fields' => array('parent_code')));
                } else {
                    $parentEdges[$code] = $node['parent'];
                    $parentNode = $this->icdNodes[$node['parent']];
                    $parentLevelAllowed = $parentNode['level'] === 'category' || $parentNode['level'] === 'subcategory';
                    $sameBranch = $parentNode['block'] === $node['block'] && $parentNode['chapter'] === $node['chapter'];
                    $parentCodeIsPrefix = strpos($code, $node['parent']) === 0 && strlen($node['parent']) < strlen($code);
                    if (!$parentLevelAllowed || !$sameBranch || !$parentCodeIsPrefix) {
                        $hierarchyErrors++;
                        $this->addError('icd_parent_level_invalid', array('dataset_id' => $diagnosisId, 'record_index' => $node['record_index'], 'fields' => array('level', 'parent_code')));
                    }
                }
            } else {
                $hierarchyErrors++;
                $this->addError('icd_level_invalid', array('dataset_id' => $diagnosisId, 'record_index' => $node['record_index'], 'fields' => array('level')));
            }
        }
        $cycleCount = $this->countParentCycles($parentEdges, $diagnosisId);

        $diagnosisManifest = isset($this->datasetById[$diagnosisId]) ? $this->datasetById[$diagnosisId] : array();
        $packageRuntime = isset($this->manifest['runtime_enabled']) && $this->manifest['runtime_enabled'] === true;
        $datasetRuntime = isset($diagnosisManifest['runtime_enabled']) && $diagnosisManifest['runtime_enabled'] === true;
        $datasetSelectable = isset($diagnosisManifest['runtime_selectable']) && $diagnosisManifest['runtime_selectable'] === true;
        $runtimeSelectable = ($packageRuntime && $datasetRuntime && $datasetSelectable) ? $structuralLeafSelectable : 0;
        $approvedRuntimeSelectable = ($runtimeSelectable > 0
            && isset($diagnosisManifest['canonical_review_status'])
            && $diagnosisManifest['canonical_review_status'] === 'approved') ? $runtimeSelectable : 0;

        $this->diagnostics['icd_alias_count'] = $this->declaredCount($this->rules['icd']['alias_dataset']);
        $this->diagnostics['icd_approved_runtime_selectable_count'] = $approvedRuntimeSelectable;
        $this->diagnostics['icd_block_count'] = $this->declaredCount($this->rules['icd']['block_dataset']);
        $this->diagnostics['icd_chapter_count'] = $this->declaredCount($this->rules['icd']['chapter_dataset']);
        $this->diagnostics['icd_diagnosis_count'] = count($this->icdNodes);
        $this->diagnostics['icd_duplicate_diagnosis_code_count'] = $this->icdDuplicateCodes;
        $this->diagnostics['icd_hierarchy_consistency_error_count'] = $hierarchyErrors;
        $this->diagnostics['icd_legacy_review_count'] = $this->declaredCount($this->rules['icd']['legacy_dataset']);
        $this->diagnostics['icd_orphan_parent_diagnosis_count'] = $orphanParents;
        $this->diagnostics['icd_parent_cycle_count'] = $cycleCount;
        $this->diagnostics['icd_runtime_selectable_count'] = $runtimeSelectable;
        $this->diagnostics['icd_self_parent_count'] = $selfParents;
        $this->diagnostics['icd_structurally_leaf_selectable_count'] = $structuralLeafSelectable;

        if ($this->icdDuplicateCodes > 0) {
            $this->addError('icd_duplicate_diagnosis_codes', array('actual_count' => $this->icdDuplicateCodes));
        }
        if (isset($this->manifest['runtime_policies']['icd10_diagnosis_selection']['current_runtime_selectable_record_count'])
            && is_int($this->manifest['runtime_policies']['icd10_diagnosis_selection']['current_runtime_selectable_record_count'])
            && $this->manifest['runtime_policies']['icd10_diagnosis_selection']['current_runtime_selectable_record_count'] !== $runtimeSelectable) {
            $this->addError('icd_runtime_selectable_count_mismatch', array(
                'expected_count' => $this->manifest['runtime_policies']['icd10_diagnosis_selection']['current_runtime_selectable_record_count'],
                'actual_count' => $runtimeSelectable,
            ));
        }
    }

    private function validateSubmanifests()
    {
        if (!isset($this->manifest['submanifests']) || !is_array($this->manifest['submanifests'])) {
            return;
        }
        $configuredIcdIds = array(
            $this->rules['icd']['chapter_dataset'],
            $this->rules['icd']['block_dataset'],
            $this->rules['icd']['diagnosis_dataset'],
            $this->rules['icd']['alias_dataset'],
            $this->rules['icd']['legacy_dataset'],
        );
        $configuredIcdIds = array_values(array_unique($configuredIcdIds, SORT_STRING));
        sort($configuredIcdIds, SORT_STRING);
        foreach ($configuredIcdIds as $configuredId) {
            if (!isset($this->datasetById[$configuredId])) {
                $this->addError('configured_icd_dataset_missing', array('dataset_id' => $configuredId));
            }
        }
        $icdSubmanifestSeen = false;
        foreach ($this->manifest['submanifests'] as $entry) {
            if (!is_array($entry) || !isset($entry['path']) || !is_string($entry['path']) || !$this->isSafeRelativePath($entry['path'])) {
                continue;
            }
            $context = array('dataset_id' => isset($entry['submanifest_id']) ? $entry['submanifest_id'] : null, 'path' => $entry['path']);
            $isIcdSubmanifest = isset($this->rules['icd']['submanifest_path'])
                && $entry['path'] === $this->rules['icd']['submanifest_path'];
            if ($isIcdSubmanifest) {
                $icdSubmanifestSeen = true;
                $rootIcdIds = isset($entry['dataset_ids']) && is_array($entry['dataset_ids'])
                    ? array_values($entry['dataset_ids']) : array();
                if (count($rootIcdIds) !== count(array_unique($rootIcdIds, SORT_STRING))) {
                    $this->addError('root_icd_membership_duplicate', $context);
                }
                sort($rootIcdIds, SORT_STRING);
                if ($rootIcdIds !== $configuredIcdIds) {
                    $this->addError('root_icd_membership_not_configured_set', $context);
                }
            }
            $absolute = $this->resolveDeclaredFile($entry['path']);
            if ($absolute === null) {
                $this->addError('declared_submanifest_missing', $context);
                continue;
            }
            $bytes = $this->readAbsoluteFile($absolute, 'submanifest_read_failed', $context);
            if ($bytes === null) {
                continue;
            }
            if (isset($entry['sha256']) && hash('sha256', $bytes) !== $entry['sha256']) {
                $this->addError('submanifest_checksum_mismatch', $context);
            }
            $subTyped = json_decode($bytes);
            if (json_last_error() !== JSON_ERROR_NONE || !is_object($subTyped)) {
                $this->addError('submanifest_json_invalid', $context);
                unset($bytes, $subTyped);
                continue;
            }
            $sub = json_decode($bytes, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($sub)) {
                $this->addError('submanifest_json_invalid', $context);
                unset($bytes, $subTyped, $sub);
                continue;
            }
            unset($bytes);
            if (!isset($sub['files']) || !is_array($sub['files'])
                || !property_exists($subTyped, 'files') || !is_array($subTyped->files)) {
                $this->addError('submanifest_files_invalid', $context);
                unset($sub, $subTyped);
                continue;
            }
            $fileIds = array();
            $recordTotal = 0;
            foreach ($sub['files'] as $fileIndex => $file) {
                $typedFile = isset($subTyped->files[$fileIndex]) ? $subTyped->files[$fileIndex] : null;
                if (!is_array($file) || !is_object($typedFile)) {
                    $this->addError('submanifest_file_entry_invalid', $context + array('record_index' => $fileIndex));
                    continue;
                }
                $id = isset($file['dataset_code']) && is_string($file['dataset_code']) ? $file['dataset_code'] : null;
                $path = isset($file['file']) && is_string($file['file']) ? $file['file'] : null;
                if ($id === null || !isset($this->datasetById[$id])) {
                    $this->addError('submanifest_dataset_not_in_root', $context + array('record_index' => $fileIndex));
                    continue;
                }
                $fileIds[] = $id;
                $root = $this->datasetById[$id];
                if ($path !== $root['path']) {
                    $this->addError('submanifest_dataset_path_mismatch', array('dataset_id' => $id, 'path' => $path));
                }
                if (!isset($file['jumlah_data']) || $file['jumlah_data'] !== $root['record_count']) {
                    $this->addError('submanifest_dataset_count_mismatch', array('dataset_id' => $id, 'expected_count' => $root['record_count'], 'actual_count' => isset($file['jumlah_data']) && is_int($file['jumlah_data']) ? $file['jumlah_data'] : null));
                } else {
                    $recordTotal += $file['jumlah_data'];
                }
                if (!isset($file['sha256']) || $file['sha256'] !== $root['sha256']) {
                    $this->addError('submanifest_dataset_checksum_mismatch', array('dataset_id' => $id, 'path' => $path));
                }
            }
            $declaredIds = isset($entry['dataset_ids']) && is_array($entry['dataset_ids']) ? $entry['dataset_ids'] : array();
            if (count($fileIds) !== count(array_unique($fileIds, SORT_STRING))) {
                $this->addError('submanifest_icd_member_duplicate', $context);
            }
            sort($fileIds, SORT_STRING);
            sort($declaredIds, SORT_STRING);
            if ($fileIds !== $declaredIds) {
                $this->addError('submanifest_membership_mismatch', $context);
            }
            if ($isIcdSubmanifest && $fileIds !== $configuredIcdIds) {
                $this->addError('submanifest_icd_membership_not_configured_set', $context);
            }
            if (!isset($sub['file_count']) || $sub['file_count'] !== count($sub['files'])
                || !isset($entry['dataset_count']) || $entry['dataset_count'] !== count($sub['files'])) {
                $this->addError('submanifest_file_total_mismatch', $context + array('actual_count' => count($sub['files'])));
            }
            if (!isset($sub['total_records']) || $sub['total_records'] !== $recordTotal
                || !isset($entry['record_count']) || $entry['record_count'] !== $recordTotal) {
                $this->addError('submanifest_record_total_mismatch', $context + array('actual_count' => $recordTotal));
            }
            unset($sub, $subTyped);
        }
        if (!$icdSubmanifestSeen) {
            $this->addError('configured_icd_submanifest_missing', array('path' => $this->rules['icd']['submanifest_path']));
        }
    }

    private function validateRuntimeSafety()
    {
        if (!isset($this->manifest['production_ready']) || !is_bool($this->manifest['production_ready'])) {
            $this->addError('production_ready_boolean_invalid');
        }
        if (!isset($this->manifest['runtime_enabled']) || !is_bool($this->manifest['runtime_enabled'])) {
            $this->addError('package_runtime_enabled_boolean_invalid');
        }
        if (isset($this->manifest['production_ready'], $this->manifest['runtime_enabled'])
            && $this->manifest['production_ready'] === false && $this->manifest['runtime_enabled'] === true) {
            $this->addError('unsafe_package_runtime_enabled');
        }
        $typedPolicies = is_object($this->manifestTyped) && property_exists($this->manifestTyped, 'runtime_policies')
            ? $this->manifestTyped->runtime_policies : null;
        if (!isset($this->manifest['runtime_policies']) || !is_array($this->manifest['runtime_policies']) || !is_object($typedPolicies)) {
            $this->addError('runtime_policies_missing');
            return;
        }
        $policies = $this->manifest['runtime_policies'];
        $allowedPolicyNames = isset($this->schema['$defs']['runtimePolicies']['properties'])
            ? array_keys($this->schema['$defs']['runtimePolicies']['properties'])
            : array('icd10_diagnosis_selection', 'patient_intake_master_usage', 'medication_prescription_usage', 'red_flag_content');
        $requiredPolicyNames = isset($this->schema['$defs']['runtimePolicies']['required'])
            ? $this->schema['$defs']['runtimePolicies']['required']
            : $allowedPolicyNames;
        $this->checkRequiredAndUnknown($policies, $requiredPolicyNames, $allowedPolicyNames, 'runtime_policy_missing', 'runtime_policy_unknown');
        foreach ($allowedPolicyNames as $policyName) {
            $typedPolicy = is_object($typedPolicies) && property_exists($typedPolicies, $policyName)
                ? $typedPolicies->{$policyName} : null;
            if (!isset($policies[$policyName]) || !is_array($policies[$policyName]) || !is_object($typedPolicy)) {
                continue;
            }
            $policySchema = isset($this->schema['$defs']['runtimePolicies']['properties'][$policyName]['allOf'][1])
                ? $this->schema['$defs']['runtimePolicies']['properties'][$policyName]['allOf'][1]
                : array();
            $policyAllowed = isset($policySchema['properties']) ? array_keys($policySchema['properties']) : array_keys($policies[$policyName]);
            $policyRequired = isset($policySchema['required']) ? $policySchema['required'] : $policyAllowed;
            $this->checkRequiredAndUnknown($policies[$policyName], $policyRequired, $policyAllowed, 'runtime_policy_field_missing', 'runtime_policy_unknown_field', array('dataset_id' => $policyName));
            if (!isset($policies[$policyName]['runtime_enabled']) || !is_bool($policies[$policyName]['runtime_enabled'])) {
                $this->addError('runtime_policy_boolean_invalid', array('dataset_id' => $policyName, 'fields' => array('runtime_enabled')));
            }
            if (array_key_exists('requirements', $policies[$policyName])
                && (!property_exists($typedPolicy, 'requirements') || !is_array($typedPolicy->requirements))) {
                $this->addError('runtime_requirements_type_invalid', array('dataset_id' => $policyName, 'fields' => array('requirements')));
            }
            if (isset($this->manifest['runtime_enabled']) && $this->manifest['runtime_enabled'] === false
                && isset($policies[$policyName]['runtime_enabled']) && $policies[$policyName]['runtime_enabled'] === true) {
                $this->addError('runtime_policy_enabled_while_package_disabled', array('dataset_id' => $policyName));
            }
        }
        $icdRequirements = array('active_record', 'leaf_diagnosis', 'selectable_flag', 'canonical_review_status_approved');
        $intakeRequirements = array('active_record', 'patient_facing_approval', 'canonical_review_status_approved');
        if (!isset($policies['icd10_diagnosis_selection']['requirements'])
            || !$this->containsAll($policies['icd10_diagnosis_selection']['requirements'], $icdRequirements)) {
            $this->addError('icd_runtime_requirements_incomplete');
        }
        if (!isset($policies['patient_intake_master_usage']['requirements'])
            || !$this->containsAll($policies['patient_intake_master_usage']['requirements'], $intakeRequirements)) {
            $this->addError('patient_intake_runtime_requirements_incomplete');
        }
        $red = isset($policies['red_flag_content']) ? $policies['red_flag_content'] : array();
        if (!is_array($red) || !isset($red['runtime_enabled']) || $red['runtime_enabled'] !== false
            || !isset($red['screening_support_only']) || $red['screening_support_only'] !== true
            || !isset($red['diagnosis_engine']) || $red['diagnosis_engine'] !== false
            || !isset($red['substitute_for_clinical_assessment']) || $red['substitute_for_clinical_assessment'] !== false) {
            $this->addError('red_flag_safety_policy_invalid');
        }
        $medication = isset($policies['medication_prescription_usage']) ? $policies['medication_prescription_usage'] : array();
        if (!is_array($medication)
            || !isset($medication['runtime_enabled']) || $medication['runtime_enabled'] !== false
            || !isset($medication['prescription_ready']) || $medication['prescription_ready'] !== false
            || !isset($medication['fornas_name_reference_prescription_ready']) || $medication['fornas_name_reference_prescription_ready'] !== false) {
            $this->addError('medication_safety_policy_invalid');
        }
        if (isset($this->datasetById['MASTER_OBAT_FORNAS'])) {
            $medicine = $this->datasetById['MASTER_OBAT_FORNAS'];
            if (!isset($medicine['master_reference_only']) || $medicine['master_reference_only'] !== true
                || !isset($medicine['runtime_enabled']) || $medicine['runtime_enabled'] !== false
                || !isset($medicine['runtime_selectable']) || $medicine['runtime_selectable'] !== false) {
                $this->addError('medicine_name_dataset_not_reference_only', array('dataset_id' => 'MASTER_OBAT_FORNAS'));
            }
        }
    }

    private function addGovernanceWarnings()
    {
        if ($this->manifest === null) {
            return;
        }
        if (isset($this->manifest['package_status']) && $this->manifest['package_status'] === 'draft') {
            $this->addWarning('package_status_draft');
        }
        if (isset($this->manifest['production_ready']) && $this->manifest['production_ready'] === false) {
            $this->addWarning('package_not_production_ready');
        }
        if (isset($this->manifest['runtime_enabled']) && $this->manifest['runtime_enabled'] === false) {
            $this->addWarning('package_runtime_disabled');
        }
        $notDeclared = 0;
        if (isset($this->manifest['datasets']) && is_array($this->manifest['datasets'])) {
            foreach ($this->manifest['datasets'] as $dataset) {
                if (is_array($dataset) && isset($dataset['license_status']) && $dataset['license_status'] === 'not_declared') {
                    $notDeclared++;
                }
            }
        }
        if ($notDeclared > 0) {
            $this->addWarning('dataset_licenses_not_declared', array('actual_count' => $notDeclared));
        }
    }

    private function buildTargetSpecifications()
    {
        $specs = array();
        foreach ($this->rules['relations'] as $rule) {
            if ($rule['relation_type'] === 'icd_diagnosis_parent') {
                continue;
            }
            $id = $rule['target_dataset'];
            $signature = $this->fieldSignature($rule['target_fields']);
            $specs[$id][$signature] = $rule['target_fields'];
        }
        return $specs;
    }

    private function rulesForSource($datasetId)
    {
        $matches = array();
        foreach ($this->rules['relations'] as $index => $rule) {
            if ($rule['source_dataset'] === $datasetId) {
                $matches[] = array('index' => $index, 'rule' => $rule);
            }
        }
        return $matches;
    }

    private function extractComponents(array $record, array $fields, $allowNull, $datasetId, $recordIndex, $purpose)
    {
        $components = array();
        foreach ($fields as $field) {
            if (!array_key_exists($field, $record)) {
                $this->addError($purpose . '_field_missing', array('dataset_id' => $datasetId, 'record_index' => $recordIndex, 'fields' => array($field)));
                return null;
            }
            $value = $record[$field];
            if ($value === null) {
                if ($allowNull) {
                    $components[] = null;
                    continue;
                }
                $this->addError($purpose . '_null_component', array('dataset_id' => $datasetId, 'record_index' => $recordIndex, 'fields' => array($field)));
                return null;
            }
            if (is_array($value) || is_object($value) || is_resource($value)) {
                $this->addError($purpose . '_component_type_invalid', array('dataset_id' => $datasetId, 'record_index' => $recordIndex, 'fields' => array($field)));
                return null;
            }
            if (is_string($value) && $value === '' && !$this->emptyUniqueKeyAllowed($datasetId, $field)) {
                $this->addError($purpose . '_empty_component', array('dataset_id' => $datasetId, 'record_index' => $recordIndex, 'fields' => array($field)));
                return null;
            }
            $components[] = $value;
        }
        return $components;
    }

    private function containsFieldValue($value, $field, $expected)
    {
        if (!is_array($value)) {
            return false;
        }
        if (array_key_exists($field, $value) && $value[$field] === $expected) {
            return true;
        }
        foreach ($value as $child) {
            if (is_array($child) && $this->containsFieldValue($child, $field, $expected)) {
                return true;
            }
        }
        return false;
    }

    private function detectDependencyCycles(array $graph)
    {
        $state = array();
        $reported = array();
        $visit = function ($node) use (&$visit, &$state, &$reported, $graph) {
            $state[$node] = 1;
            $dependencies = isset($graph[$node]) ? $graph[$node] : array();
            foreach ($dependencies as $dependency) {
                if (!isset($graph[$dependency])) {
                    continue;
                }
                if (!isset($state[$dependency])) {
                    $visit($dependency);
                } elseif ($state[$dependency] === 1) {
                    $key = strcmp($node, $dependency) < 0 ? $node . '|' . $dependency : $dependency . '|' . $node;
                    if (!isset($reported[$key])) {
                        $reported[$key] = true;
                        $this->addError('dependency_cycle', array('dataset_id' => $node));
                    }
                }
            }
            $state[$node] = 2;
        };
        foreach (array_keys($graph) as $node) {
            if (!isset($state[$node])) {
                $visit($node);
            }
        }
    }

    private function countParentCycles(array $edges, $datasetId)
    {
        $state = array();
        $cycles = 0;
        $reported = array();
        $nodes = array_keys($edges);
        sort($nodes, SORT_STRING);
        foreach ($nodes as $start) {
            if (isset($state[$start]) && $state[$start] === 2) {
                continue;
            }
            $path = array();
            $position = array();
            $current = $start;
            while (isset($edges[$current])) {
                if (isset($state[$current]) && $state[$current] === 2) {
                    break;
                }
                if (isset($position[$current])) {
                    $cycleNodes = array_slice($path, $position[$current]);
                    sort($cycleNodes, SORT_STRING);
                    $cycleKey = $this->encodeComponents($cycleNodes);
                    if (!isset($reported[$cycleKey])) {
                        $reported[$cycleKey] = true;
                        $cycles++;
                        $this->addError('icd_parent_cycle', array('dataset_id' => $datasetId, 'fields' => array('parent_code')));
                    }
                    break;
                }
                $state[$current] = 1;
                $position[$current] = count($path);
                $path[] = $current;
                $current = $edges[$current];
            }
            foreach ($path as $visited) {
                $state[$visited] = 2;
            }
        }
        return $cycles;
    }

    private function readPackageFile($relativePath, $errorCode)
    {
        $absolute = $this->resolveDeclaredFile($relativePath);
        if ($absolute === null) {
            $this->addError($errorCode, array('path' => $relativePath));
            return null;
        }
        return $this->readAbsoluteFile($absolute, $errorCode, array('path' => $relativePath));
    }

    private function readAbsoluteFile($absolutePath, $errorCode, array $context)
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            $this->addError($errorCode, $context);
            return null;
        }
        $previous = set_error_handler(function ($severity, $message, $file, $line) {
            throw new ErrorException('controlled_file_read_failure', 0, $severity, $file, $line);
        }, E_WARNING);
        try {
            $bytes = file_get_contents($absolutePath);
        } catch (ErrorException $exception) {
            $bytes = false;
        } finally {
            restore_error_handler();
        }
        if ($bytes === false) {
            $this->addError($errorCode, $context);
            return null;
        }
        return $bytes;
    }

    private function decodeDocument($bytes, $errorCode)
    {
        $typed = json_decode($bytes);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($typed)) {
            $this->addError($errorCode);
            return null;
        }
        $assoc = json_decode($bytes, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($assoc)) {
            $this->addError($errorCode);
            return null;
        }
        return array('assoc' => $assoc, 'typed' => $typed);
    }

    private function resolveDeclaredFile($relativePath)
    {
        if (!$this->isSafeRelativePath($relativePath)) {
            return null;
        }
        $candidate = $this->packageRoot . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($candidate) || !is_readable($candidate)) {
            return null;
        }
        $real = realpath($candidate);
        if ($real === false || !$this->pathIsInsidePackage($real)) {
            return null;
        }
        return $real;
    }

    private function pathIsInsidePackage($path)
    {
        $root = $this->packageRoot;
        $candidate = $path;
        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $candidate = strtolower($candidate);
        }
        return $candidate === $root || strpos($candidate, $root . DIRECTORY_SEPARATOR) === 0;
    }

    private function isSafeRelativePath($path)
    {
        if (!is_string($path) || $path === '' || strpos($path, "\0") !== false) {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return false;
        }
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || preg_match('/^(?:\\\\\\\\|\/\/)/', $path) === 1) {
            return false;
        }
        $parts = preg_split('/[\\\\\/]+/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return true;
    }

    private function isValidUniqueKey($value)
    {
        if (is_string($value)) {
            return $value !== '';
        }
        if (!is_array($value) || count($value) === 0) {
            return false;
        }
        $seen = array();
        foreach ($value as $field) {
            if (!is_string($field) || $field === '' || isset($seen[$field])) {
                return false;
            }
            $seen[$field] = true;
        }
        return true;
    }

    private function normaliseUniqueKey($value)
    {
        if (!$this->isValidUniqueKey($value)) {
            return null;
        }
        return is_string($value) ? array($value) : array_values($value);
    }

    private function emptyUniqueKeyAllowed($datasetId, $field)
    {
        return isset($this->rules['allowed_empty_unique_key_components'][$datasetId])
            && in_array($field, $this->rules['allowed_empty_unique_key_components'][$datasetId], true);
    }

    private function encodeComponents(array $components)
    {
        $encoded = json_encode(array_values($components), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('component_encoding_failed');
        }
        return $encoded;
    }

    private function fieldSignature(array $fields)
    {
        return $this->encodeComponents($fields);
    }

    private function isNonNegativeInteger($value)
    {
        return is_int($value) && $value >= 0;
    }

    private function isAssociative(array $value)
    {
        if (count($value) === 0) {
            return false;
        }
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function checkRequiredAndUnknown(array $value, array $required, array $allowed, $missingCode, $unknownCode, array $context = array())
    {
        foreach ($required as $field) {
            if (!array_key_exists($field, $value)) {
                $this->addError($missingCode, $context + array('fields' => array($field)));
            }
        }
        $unknown = array_values(array_diff(array_keys($value), $allowed));
        if (count($unknown) > 0) {
            sort($unknown, SORT_STRING);
            $this->addError($unknownCode, $context + array('fields' => $unknown));
        }
    }

    private function containsAll($actual, array $expected)
    {
        if (!is_array($actual)) {
            return false;
        }
        foreach ($expected as $value) {
            if (!in_array($value, $actual, true)) {
                return false;
            }
        }
        return true;
    }

    private function declaredCount($datasetId)
    {
        return isset($this->datasetById[$datasetId]['record_count']) && is_int($this->datasetById[$datasetId]['record_count'])
            ? $this->datasetById[$datasetId]['record_count']
            : 0;
    }

    private function finishCheck($code, $errorCountBefore)
    {
        $this->checks[] = array(
            'code' => $code,
            'status' => count($this->errors) === $errorCountBefore ? 'PASS' : 'FAIL',
        );
    }

    private function addError($code, array $context = array())
    {
        if (count($this->errors) >= $this->issueLimit) {
            $this->issuesTruncated = true;
            return;
        }
        $this->errors[] = $this->makeIssue($code, $context);
    }

    private function addWarning($code, array $context = array())
    {
        $this->warnings[] = $this->makeIssue($code, $context);
    }

    private function makeIssue($code, array $context)
    {
        $allowed = array('dataset_id', 'path', 'record_index', 'fields', 'expected_count', 'actual_count', 'expected_value', 'actual_value', 'relation_type');
        $issue = array('code' => $code);
        foreach ($allowed as $field) {
            if (array_key_exists($field, $context) && $context[$field] !== null) {
                if ($field === 'path' && !$this->isSafeRelativePath($context[$field])) {
                    continue;
                }
                $issue[$field] = $context[$field];
            }
        }
        return $issue;
    }

    private function finalize($command, $fullValidationExecuted)
    {
        if ($this->issuesTruncated) {
            $this->addWarning('validation_issue_limit_reached', array('actual_count' => $this->issueLimit));
        }
        $peak = memory_get_peak_usage(true);
        $this->diagnostics['full_validation_executed'] = $fullValidationExecuted;
        $this->diagnostics['peak_memory_bytes'] = $peak;
        $threshold = isset($this->rules['memory_warning_threshold_bytes']) ? $this->rules['memory_warning_threshold_bytes'] : 0;
        $this->diagnostics['memory_warning_threshold_bytes'] = $threshold;
        if ($threshold > 0 && $peak > $threshold) {
            $this->addWarning('peak_memory_warning_threshold_exceeded', array('expected_count' => $threshold, 'actual_count' => $peak));
        }
        usort($this->errors, array($this, 'compareIssues'));
        usort($this->warnings, array($this, 'compareIssues'));
        ksort($this->diagnostics, SORT_STRING);

        $errorCount = count($this->errors);
        $exitCode = $errorCount === 0 ? 0 : 1;
        $result = $errorCount === 0 ? 'PASS' : 'FAIL';
        $summary = array(
            'dataset_count' => isset($this->manifest['package_summary']['dataset_count']) ? $this->manifest['package_summary']['dataset_count'] : null,
            'error_count' => $errorCount,
            'package_status' => isset($this->manifest['package_status']) ? $this->manifest['package_status'] : null,
            'production_ready' => isset($this->manifest['production_ready']) ? $this->manifest['production_ready'] : null,
            'record_count' => isset($this->manifest['package_summary']['record_count']) ? $this->manifest['package_summary']['record_count'] : null,
            'runtime_enabled' => isset($this->manifest['runtime_enabled']) ? $this->manifest['runtime_enabled'] : null,
            'warning_count' => count($this->warnings),
        );
        $package = array(
            'id' => isset($this->manifest['package_id']) ? $this->manifest['package_id'] : null,
            'version' => isset($this->manifest['package_version']) ? $this->manifest['package_version'] : null,
        );
        return array(
            'command' => $command,
            'package' => $package,
            'summary' => $summary,
            'checks' => $this->checks,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'diagnostics' => $this->diagnostics,
            'exit_code' => $exitCode,
            'validation_result' => $result,
        );
    }

    public function compareIssues($left, $right)
    {
        return strcmp(
            json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
