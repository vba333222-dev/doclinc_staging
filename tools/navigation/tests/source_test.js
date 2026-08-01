'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const helper = read('application/helpers/request_navigation_helper.php');
const chatController = read('application/modules/chat/controllers/Chat.php');
const chatView = read('application/modules/chat/views/thread_v.php');
const consultationController = read('application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php');
const consultationView = read('application/modules/konsultasi_nakes/views/konsultasi_nakes_v.php');

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

expect(helper.includes("return 'home#riwayat';"), 'warga_destination_allowlisted');
expect(helper.includes("return 'home_nakes#riwayat_konsul';"), 'active_nakes_destination_allowlisted');
expect(helper.includes("return 'home_nakes#riwayat_konsul_selesai';"), 'completed_nakes_destination_allowlisted');
expect(!/(return_to|HTTP_REFERER|\$\_GET|\$\_POST|input->)/.test(helper), 'resolver_has_no_user_url_input');
expect(chatController.includes("helper('request_navigation')"), 'chat_loads_navigation_helper');
expect(chatController.includes("'back_url' => doclinc_request_return_url("), 'chat_passes_resolved_back_url');
expect(chatController.includes('isset($request->request_status) ? $request->request_status'), 'chat_uses_persisted_status');
expect(chatView.includes('html_escape($back_url)'), 'chat_escapes_back_url');
expect(!chatView.includes("base_url('home_nakes') : base_url('home#riwayat')"), 'chat_generic_dashboard_back_removed');
expect(consultationController.includes("'request_navigation'"), 'consultation_loads_navigation_helper');
expect(consultationController.includes("$x['completed_return_url'] = doclinc_request_return_url('dokter', 'Completed');"), 'completion_destination_server_resolved');
expect(consultationView.includes("isset($return_url) ? $return_url"), 'consultation_back_uses_resolved_url');
expect(!consultationView.includes("'../../home_nakes#riwayat_konsul_selesai'"), 'relative_completion_redirect_removed');

process.stdout.write(`NAVIGATION_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`NAVIGATION_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
