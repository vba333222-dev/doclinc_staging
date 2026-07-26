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
process.exit(failures === 0 ? 0 : 1);
