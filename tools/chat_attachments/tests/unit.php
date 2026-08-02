<?php
define('BASEPATH', __DIR__ . '/');

$sandbox = sys_get_temp_dir() . '/doclinc-chat-attachment-' . bin2hex(random_bytes(8));
$public_root = $sandbox . '/public';
$private_root = $sandbox . '/private/chat-attachments';
mkdir($public_root . '/uploads/chat_images', 0700, true);
define('FCPATH', $public_root . '/');

require_once dirname(__DIR__, 3) . '/application/libraries/Chat_attachment_storage.php';

$passed = 0;
$failed = 0;

function attachment_expect($condition, $label)
{
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS {$label}\n";
		return;
	}
	$failed++;
	echo "FAIL {$label}\n";
}

function attachment_cleanup($path)
{
	if (!is_dir($path)) {
		return;
	}
	$entries = scandir($path);
	foreach ($entries as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$target = $path . DIRECTORY_SEPARATOR . $entry;
		if (is_dir($target) && !is_link($target)) {
			attachment_cleanup($target);
		} else {
			@unlink($target);
		}
	}
	@rmdir($path);
}

try {
	$storage = new Chat_attachment_storage(array(
		'storage_path' => $private_root,
		'public_root' => $public_root,
	));
	$upload_path = $storage->ensure_storage_directory();
	attachment_expect(is_string($upload_path) && is_dir($upload_path), 'private_storage_created');
	attachment_expect((fileperms($private_root) & 0777) === 0700, 'private_storage_mode_0700');

	$file_name = str_repeat('a', 32) . '.png';
	$key = $storage->stored_key_from_upload(array('file_name' => $file_name));
	attachment_expect($key === 'chat-images/' . $file_name, 'upload_maps_to_private_key');
	attachment_expect($storage->safe_stored_key($key) === $key, 'private_key_accepted');
	attachment_expect($storage->safe_stored_key('../' . $key) === '', 'traversal_key_rejected');
	attachment_expect($storage->safe_stored_key('https://example.invalid/file.png') === '', 'absolute_url_rejected');
	attachment_expect($storage->safe_stored_key('chat-images/not-hashed.png') === '', 'unguessable_filename_required');

	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
	file_put_contents($private_root . '/' . $file_name, $png);
	attachment_expect($storage->resolve_stored_file($key) === realpath($private_root . '/' . $file_name), 'private_file_resolved');
	attachment_expect($storage->allowed_mime($private_root . '/' . $file_name) === 'image/png', 'mime_read_from_file_content');

	$legacy_name = str_repeat('b', 32) . '.jpg';
	file_put_contents($public_root . '/uploads/chat_images/' . $legacy_name, $png);
	attachment_expect(
		$storage->resolve_stored_file('uploads/chat_images/' . $legacy_name) === realpath($public_root . '/uploads/chat_images/' . $legacy_name),
		'legacy_file_resolved_for_protected_endpoint'
	);

	$unsafe = new Chat_attachment_storage(array(
		'storage_path' => $public_root . '/private-chat',
		'public_root' => $public_root,
	));
	attachment_expect($unsafe->ensure_storage_directory() === false, 'public_web_root_storage_rejected');

	$relative = new Chat_attachment_storage(array(
		'storage_path' => 'relative/chat',
		'public_root' => $public_root,
	));
	attachment_expect($relative->ensure_storage_directory() === false, 'relative_storage_rejected');
} finally {
	attachment_cleanup($sandbox);
}

echo "CHAT_ATTACHMENT_UNIT_PASSED={$passed}\n";
echo "CHAT_ATTACHMENT_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
