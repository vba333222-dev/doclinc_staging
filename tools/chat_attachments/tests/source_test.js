'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../../..');
let passed = 0;
let failed = 0;

function source(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

function expect(condition, label) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${label}\n`);
    return;
  }
  failed += 1;
  process.stdout.write(`FAIL ${label}\n`);
}

function functionSource(contents, name) {
  const marker = `function ${name}(`;
  const start = contents.indexOf(marker);
  if (start < 0) return '';
  const open = contents.indexOf('{', start);
  let depth = 0;
  for (let index = open; index < contents.length; index += 1) {
    if (contents[index] === '{') depth += 1;
    if (contents[index] === '}') {
      depth -= 1;
      if (depth === 0) return contents.slice(start, index + 1);
    }
  }
  return '';
}

const config = source('application/config/config.php');
const routes = source('application/config/routes.php');
const controller = source('application/modules/chat/controllers/Chat.php');
const model = source('application/modules/chat/models/Chat_m.php');
const storage = source('application/libraries/Chat_attachment_storage.php');
const client = source('application/modules/chat/views/thread_v.php');

expect(config.includes("getenv('DOCLINC_CHAT_ATTACHMENT_STORAGE_PATH')"), 'storage_path_can_be_externalized');
expect(config.includes("'private' . DIRECTORY_SEPARATOR . 'chat-attachments'"), 'default_storage_is_outside_site_root');
expect(routes.includes("$route['chat/attachment/(:num)'] = 'chat/chat/attachment/$1';"), 'authorized_route_is_explicit');
expect(controller.includes('get_attachment_for_user($message_id, $user_id)'), 'controller_uses_message_authorization');
expect(controller.includes('Cache-Control: private, no-store') && controller.includes('X-Content-Type-Options: nosniff') && controller.includes('Cross-Origin-Resource-Policy: same-origin'), 'download_has_private_headers');
expect(controller.includes("method(TRUE) !== 'GET'") && controller.includes('show_404();'), 'download_rejects_wrong_method_and_hides_existence');
expect(model.includes("'upload_path' => $upload_path") && !model.includes("'upload_path' => './uploads/chat_images/'"), 'new_uploads_use_private_storage');
expect(model.includes('$actual_mime = $storage->allowed_mime($stored_path)') && model.includes('$stored_size > $max_bytes'), 'upload_is_revalidated_before_database_insert');
expect(model.includes("base_url('chat/attachment/'") && !model.includes('base_url($attachment_path)'), 'api_exposes_controller_url_only');
expect(!model.includes("'attachment_path' => $attachment_path"), 'api_does_not_expose_storage_key');
expect(model.includes("in_array($attachment_mime, array('image/jpeg', 'image/png', 'image/webp'), true)") && model.includes('$this->safe_download_name('), 'api_sanitizes_attachment_metadata');
expect(model.includes('doclinc_can_view_chat((int) $row[\'request_id\'], $current_user_id)'), 'download_revalidates_request_authorization');
expect(storage.includes('path_is_within($this->storage_root, $this->public_root)'), 'public_root_is_rejected');
expect(storage.includes("array('image/jpeg', 'image/png', 'image/webp')"), 'served_mime_is_allowlisted');
expect(client.includes('/^\\/chat\\/attachment\\/[1-9][0-9]*\\/?$/'), 'client_accepts_authorized_route');
expect(!client.includes("parsed.pathname.indexOf('/uploads/chat_images/')"), 'client_rejects_direct_public_upload_url');

const validatorSource = functionSource(client, 'isSafeImageUrl');
const context = vm.createContext({ URL, window: { location: { origin: 'https://doclinc.example' } } });
vm.runInContext(`${validatorSource}; this.validateAttachmentUrl = isSafeImageUrl;`, context);
expect(context.validateAttachmentUrl('https://doclinc.example/chat/attachment/42'), 'same_origin_authorized_url_allowed');
expect(!context.validateAttachmentUrl('https://doclinc.example/uploads/chat_images/aaaaaaaaaaaaaaaa.jpg'), 'legacy_public_url_denied');
expect(!context.validateAttachmentUrl('https://evil.example/chat/attachment/42'), 'cross_origin_url_denied');
expect(!context.validateAttachmentUrl('https://doclinc.example/chat/attachment/42?download=1'), 'query_override_denied');
expect(!context.validateAttachmentUrl('https://doclinc.example/chat/attachment/0'), 'invalid_message_id_denied');

process.stdout.write(`CHAT_ATTACHMENT_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`CHAT_ATTACHMENT_SOURCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
