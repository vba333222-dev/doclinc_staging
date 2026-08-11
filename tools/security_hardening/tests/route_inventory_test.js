'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
let passed = 0;
let failed = 0;

function expect(condition, label) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${label}\n`);
    return;
  }
  failed += 1;
  process.stdout.write(`FAIL ${label}\n`);
}

function source(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function publicMethods(contents) {
  const methods = [];
  const expression = /^\s*public function\s+([A-Za-z0-9_]+)\s*\(/gm;
  let match;
  while ((match = expression.exec(contents)) !== null) {
    if (match[1] !== '__construct') methods.push(match[1]);
  }
  return methods.sort();
}

function methodBody(contents, methodName) {
  const expression = new RegExp(`^\\s*public function\\s+${methodName}\\s*\\(`, 'm');
  const match = expression.exec(contents);
  if (!match) return '';
  const open = contents.indexOf('{', match.index);
  let depth = 0;
  for (let index = open; index < contents.length; index += 1) {
    if (contents[index] === '{') depth += 1;
    if (contents[index] === '}') {
      depth -= 1;
      if (depth === 0) return contents.slice(open + 1, index);
    }
  }
  return '';
}

const inventory = {
  'application/modules/login/controllers/Login.php': {
    index: 'READ', auth: 'POST', logout: 'POST', activate: 'GET', activate_auth: 'POST', change_password: 'GET', update_password: 'POST',
  },
  'application/modules/sign_up/controllers/Sign_up.php': {
    index: 'READ', save_user: 'POST',
  },
  'application/modules/chat/controllers/Chat.php': {
    index: 'READ', messages: 'GET', send: 'POST', mark_read: 'POST', foto: 'POST_UPLOAD', attachment: 'GET', video: 'RETIRED_VIDEO',
  },
  'application/modules/chat/controllers/upload.php': {
    index: 'TOMBSTONE', foto: 'TOMBSTONE', video: 'TOMBSTONE',
  },
  'application/modules/home/controllers/Home.php': {
    livekit_token: 'POST_LIVEKIT', livekit_incoming_call: 'GET', answer_livekit_call: 'POST_LIVEKIT',
    reject_livekit_call: 'POST_LIVEKIT', livekit_call_status: 'POST_LIVEKIT', index: 'READ',
    save_konsultasi: 'TOMBSTONE', updateRequestById: 'POST_HELPER', deleterequestbyid: 'POST_HELPER',
    cancel_request: 'POST_HELPER', visit_location: 'GET', submit_rating: 'POST_HELPER',
    getDokterRating: 'POST_HELPER', getDuration: 'POST_HELPER', update_profile: 'POST_HELPER', update_profile_photo: 'POST',
  },
  'application/modules/home/controllers/Notification.php': {
    index: 'TOMBSTONE', test: 'TOMBSTONE', send: 'TOMBSTONE', service_worker: 'TOMBSTONE',
  },
  'application/modules/home_nakes/controllers/Home_nakes.php': {
    livekit_token: 'POST_LIVEKIT', start_livekit_call: 'POST_LIVEKIT', end_livekit_call: 'POST_LIVEKIT',
    livekit_call_status: 'POST_LIVEKIT', presence_heartbeat: 'POST', presence_snapshot: 'GET', index: 'READ',
    assign_staff: 'POST', clear_staff_assignment: 'POST', tes_save_lokasi: 'TOMBSTONE', save_location: 'TOMBSTONE',
    accept_request: 'POST', cancel_request: 'POST_HELPER', visit_location: 'GET', update_visit_location: 'POST',
    update_visit_status: 'POST', get_location_user: 'TOMBSTONE', updateprofile: 'POST_HELPER', operations: 'READ',
  },
  'application/modules/konsultasi/controllers/Konsultasi.php': {
    index: 'READ', chat: 'TOMBSTONE', save_konsultasi: 'POST', send: 'TOMBSTONE', service_worker: 'TOMBSTONE',
  },
  'application/modules/konsultasi/controllers/Notification.php': {
    index: 'TOMBSTONE', test: 'TOMBSTONE', send: 'TOMBSTONE', service_worker: 'TOMBSTONE',
  },
  'application/modules/konsultasi_nakes/controllers/Konsultasi_nakes.php': {
    index: 'READ', konsultasi: 'READ', get_terapi: 'GET', chat: 'TOMBSTONE', save_konsultasi_nakes: 'POST', getICD_json: 'TOMBSTONE',
  },
  'application/modules/notifikasi/controllers/Notifikasi.php': {
    index: 'READ_DELEGATE', list_json: 'GET', snapshot: 'GET', mark_read: 'POST',
  },
  'application/controllers/Realtime_access.php': {
    connection_token: 'GET', subscription_token: 'POST',
  },
  'application/controllers/Realtime_requests.php': {
    snapshot: 'GET',
  },
  'application/controllers/Profile_media.php': {
    photo: 'GET', update_photo: 'POST',
  },
  'application/controllers/Profile_requirements.php': {
    status: 'GET',
  },
  'application/controllers/Location_address.php': {
    address: 'POST',
  },
  'application/controllers/Profile_completion.php': {
    index: 'GET',
  },
  'application/controllers/Puskesmas_operations.php': {
    snapshot: 'GET',
  },
  'application/controllers/Clinical_suggestions.php': {
    search: 'GET',
  },
  'application/controllers/Doclinc_pic_nakes_migration.php': {
    index: 'CLI_ONLY',
  },
  'application/controllers/Doclinc_queue_number_migration.php': {
    index: 'CLI_ONLY',
  },
  'application/controllers/Doclinc_visit_workflow_migration.php': {
    index: 'CLI_ONLY',
  },
  'application/controllers/Welcome.php': {
    index: 'TOMBSTONE',
  },
  'application/modules/splash_screen/controllers/Splash_screen.php': {
    index: 'GET', b: 'GET',
  },
  'application/modules/super_app/controllers/Super_app.php': {
    index: 'GET', b: 'GET',
  },
};

function controllerFiles(directory) {
  const found = [];
  fs.readdirSync(path.join(root, directory), { withFileTypes: true }).forEach((entry) => {
    const relative = path.join(directory, entry.name).replace(/\\/g, '/');
    if (entry.isDirectory()) {
      found.push(...controllerFiles(relative));
    } else if (entry.isFile() && entry.name.endsWith('.php') && relative.includes('/controllers/')) {
      found.push(relative);
    }
  });
  return found;
}

const discoveredControllers = [
  ...controllerFiles('application/controllers'),
  ...controllerFiles('application/modules'),
].sort();
expect(
  JSON.stringify(discoveredControllers) === JSON.stringify(Object.keys(inventory).sort()),
  'all_non_admin_controller_files_are_manifested'
);

function hasMethodGuard(body, method) {
  return body.includes(`method(TRUE) !== '${method}'`) || body.includes(`method(true) !== '${method}'`);
}

Object.keys(inventory).forEach((file) => {
  const contents = source(file);
  const expected = Object.keys(inventory[file]).sort();
  const actual = publicMethods(contents);
  expect(JSON.stringify(actual) === JSON.stringify(expected), `exact_public_route_inventory_${path.basename(file)}`);

  expected.forEach((method) => {
    const body = methodBody(contents, method);
    const contract = inventory[file][method];
    let valid = body !== '';
    if (contract === 'GET' || contract === 'POST') valid = hasMethodGuard(body, contract);
    if (contract === 'POST_HELPER') valid = body.includes('require_post_json()');
    if (contract === 'POST_LIVEKIT') valid = body.includes('require_livekit_post()');
    if (contract === 'POST_UPLOAD') valid = body.includes('authorize_upload_request()');
    if (contract === 'TOMBSTONE') valid = body.includes('show_404();');
    if (contract === 'CLI_ONLY') valid = body.includes('is_cli_request()') && body.includes('show_404();');
    if (contract === 'RETIRED_VIDEO') valid = body.includes('Upload video belum tersedia') && body.includes('set_status_header(400)');
    if (contract === 'READ_DELEGATE') valid = body.includes('list_json()');
    expect(valid, `route_contract_${path.basename(file)}_${method}_${contract.toLowerCase()}`);
  });
});

process.stdout.write(`ROUTE_INVENTORY_PASSED=${passed}\n`);
process.stdout.write(`ROUTE_INVENTORY_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
