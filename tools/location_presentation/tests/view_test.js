'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const wargaView = fs.readFileSync(path.join(root, 'application/modules/home/views/home_v.php'), 'utf8');
const nakesView = fs.readFileSync(path.join(root, 'application/modules/home_nakes/views/home_nakes_v.php'), 'utf8');

let passed = 0;
let failed = 0;
function expect(condition, name) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${name}\n`);
    return;
  }
  failed += 1;
  process.stdout.write(`FAIL ${name}\n`);
}

expect(wargaView.includes("base_url('assets/js/doclinc-location-address.js')"), 'warga_loads_address_presenter');
expect(wargaView.includes('id="userLocationAddress"'), 'warga_has_human_address_target');
expect(wargaView.includes('id="userLocationMapLink"'), 'warga_has_safe_map_link');
expect(!/coordinateEl\.textContent\s*=\s*[^;]*(lat|lng|latitude|longitude)/i.test(wargaView), 'warga_header_does_not_render_coordinates');
expect((wargaView.match(/id="address"/g) || []).length === 1, 'warga_address_id_is_unique');
expect(nakesView.includes("base_url('assets/js/doclinc-location-address.js')"), 'nakes_loads_address_presenter');
expect(nakesView.includes('id="nakesVisitPatientAddress"'), 'monitor_has_patient_address_target');
expect(nakesView.includes('id="nakesVisitNakesAddress"'), 'monitor_has_nakes_address_target');
expect(nakesView.includes('setVisitAddressPresentations(requestId, patientLocation, nakesLocation);'), 'monitor_resolves_both_addresses');
expect(nakesView.includes("'Alamat belum dapat dikenali'"), 'monitor_has_human_safe_fallback');
expect(!/nakesVisit(?:Patient|Nakes)Address[^\n]*(latitude|longitude|lat|lng)/i.test(nakesView), 'monitor_address_text_not_raw_coordinates');

process.stdout.write(`LOCATION_VIEW_PASSED=${passed}\n`);
process.stdout.write(`LOCATION_VIEW_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
