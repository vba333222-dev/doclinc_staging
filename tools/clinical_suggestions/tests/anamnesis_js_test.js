'use strict';

global.window = {};
global.document = { addEventListener: function () {} };
require('../../../assets/js/doclinc-clinical-suggestions.js');

var api = global.window.DoclincClinicalSuggestions;
var failures = 0;
function check(name, condition) {
	if (condition) {
		console.log('PASS ' + name);
	} else {
		failures += 1;
		console.log('FAIL ' + name);
	}
}

check('symptom_selection_preserves_free_text', api.mergeSymptomText('Catatan awal\nbat', 'Batuk', 'bat') === 'Catatan awal\nBatuk');
check('symptom_selection_replaces_only_active_query', api.mergeSymptomText('Demam; ses', 'Sesak napas', 'ses') === 'Demam; Sesak napas');
check('duplicate_suggestion_prevented', api.mergeSymptomText('Demam\nBatuk', 'batuk', 'bat') === 'Demam\nBatuk');
check('symptom_query_uses_active_line_only', api.symptomQuery('Demam\nses') === 'ses');
var internalSourceFixture = {
	display: 'Asma, tidak spesifik',
	label: 'Asthma',
	value: 'J45.9 — Asthma, unspecified',
	meta: 'ICD-10 J45.9 · WHO 2019',
	supporting: 'Asthma, unspecified',
	source: 'Doclinc internal source',
	version: 'internal'
};
check('clinical_primary_display_ignores_internal_source', api.displayText(internalSourceFixture) === 'Asma, tidak spesifik');
check('clinical_metadata_uses_public_who_contract_only', api.metadataText(internalSourceFixture) === 'ICD-10 J45.9 · WHO 2019');
check('clinical_metadata_never_falls_back_to_internal_source', api.metadataText({ source: 'Doclinc internal source' }) === '');
check('clinical_supporting_text_uses_official_who_title', api.supportingText(internalSourceFixture) === 'Asthma, unspecified');
check('diagnosis_selection_preserves_canonical_code_and_who_title', api.selectedValue('diagnosis', internalSourceFixture) === 'J45.9 — Asthma, unspecified');
check('medicine_selection_uses_human_label_only', api.selectedValue('medicine', {
	label: 'Paracetamol 500 mg',
	code: 'INTERNAL-123',
	source: 'Doclinc internal source'
}) === 'Paracetamol 500 mg');
process.exit(failures === 0 ? 0 : 1);
