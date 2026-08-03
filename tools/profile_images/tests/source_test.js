'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, name) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${name}\n`);
  } else {
    failed += 1;
    process.stdout.write(`FAIL ${name}\n`);
  }
}

const config = read('application/config/config.php');
const routes = read('application/config/routes.php');
const helper = read('application/helpers/profile_image_helper.php');
const controller = read('application/controllers/Profile_media.php');
const policy = read('application/libraries/Profile_image_policy.php');
const storage = read('application/libraries/Profile_image_storage.php');
const homeController = read('application/modules/home/controllers/Home.php');
const homeModel = read('application/modules/home/models/Home_m.php');
const nakesController = read('application/modules/home_nakes/controllers/Home_nakes.php');
const homeView = read('application/modules/home/views/home_v.php');
const nakesView = read('application/modules/home_nakes/views/home_nakes_v.php');
const avatar = read('application/modules/home_nakes/views/partials/nakes_avatar_v.php');
const chatView = read('application/modules/chat/views/thread_v.php');
const nginx = read('tools/profile_images/nginx/doclinc-private-profile-images.conf');

expect(routes.includes("$route['profile/photo/(:num)'] = 'profile_media/photo/$1';"), 'authorized_route_registered');
expect(helper.includes("base_url('profile/photo/' . $user_id)"), 'helper_uses_authorized_route');
expect(!helper.includes("base_url(rtrim($uploadBase"), 'helper_never_builds_public_upload_url');
expect(controller.includes("$policy->can_view($actor, $target)"), 'controller_enforces_policy');
expect(controller.includes("Cache-Control: private, no-store"), 'controller_private_no_store');
expect(controller.includes("X-Content-Type-Options: nosniff"), 'controller_nosniff');
expect(controller.includes("Cross-Origin-Resource-Policy: same-origin"), 'controller_same_origin_resource_policy');
expect(policy.includes("array('warga', 'dokter')"), 'policy_excludes_admin');
expect(policy.includes("$actor_id === $target_id"), 'self_access_supported');
expect(policy.includes("doclinc_request_is_handled_by_nakes"), 'consultation_relationship_required');
expect(policy.includes("is_command_center") && policy.includes("puskesmas_code"), 'command_center_tenant_scope_required');
expect(policy.includes("must_change_password") && policy.includes("status === 'aktif'"), 'actor_prerequisites_enforced');
expect(storage.includes("const PRIVATE_PREFIX = 'profile-images/'"), 'private_key_contract');
expect(storage.includes("0700") && storage.includes("path_is_within"), 'private_storage_confinement');
expect(storage.includes("finfo_file") && storage.includes("image/webp"), 'content_mime_allowlist');
expect(config.includes('DOCLINC_PROFILE_IMAGE_STORAGE_PATH') && config.includes("private' . DIRECTORY_SEPARATOR . 'profile-images"), 'private_storage_configured_outside_site');
expect(homeController.includes('public function update_profile_photo()'), 'warga_upload_endpoint_present');
expect(homeController.includes("$this->session->userdata('role') !== 'warga'"), 'warga_upload_role_bound');
expect(homeController.includes("$storage->upload_is_valid"), 'warga_upload_content_validated');
expect(homeModel.includes("->where('role', 'warga')") && homeModel.includes("->where('status', 'aktif')"), 'warga_update_scoped');
expect(nakesController.includes("$profile_storage->upload_is_valid"), 'nakes_upload_content_validated');
expect(nakesController.includes("'upload_path']   = $upload_directory"), 'nakes_upload_uses_private_storage');
expect(homeView.includes('wargaProfilePhotoForm') && homeView.includes("base_url('home/update_profile_photo')"), 'warga_photo_ui_connected');
expect(nakesView.includes('doclinc_profile_image_src') && avatar.includes('avatar_user_id'), 'nakes_views_use_subject_identity');
expect(chatView.includes("base_url('profile/photo/' . $partner_user_id)") && (chatView.match(/\$partner_photo_src/g) || []).length >= 4, 'chat_and_call_use_authorized_partner_photo');
expect(nginx.includes('location ^~ /uploads/profile/') && nginx.includes('return 404;'), 'legacy_direct_http_denied');

const applicationFiles = [
  'application/modules/home/views/home_v.php',
  'application/modules/home_nakes/views/home_nakes_v.php',
  'application/modules/home_nakes/views/partials/nakes_avatar_v.php',
  'application/modules/chat/views/chat.php',
  'application/modules/chat/views/thread_v.php'
].map(read).join('\n');
expect(!/base_url\s*\([^)]*uploads\/profile/i.test(applicationFiles), 'active_views_have_no_direct_profile_upload_url');

process.stdout.write(`PROFILE_IMAGE_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PROFILE_IMAGE_SOURCE_FAILED=${failed}\n`);
process.exit(failed > 0 ? 1 : 0);
