<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$avatar_name = isset($avatar_name) ? trim((string) $avatar_name) : '';
$avatar_photo = isset($avatar_photo) ? trim((string) $avatar_photo) : '';
$avatar_alt = isset($avatar_alt) ? trim((string) $avatar_alt) : 'Foto profil';
$avatar_class = isset($avatar_class) ? trim((string) $avatar_class) : 'nk-avatar--md';
$avatar_icon = isset($avatar_icon) ? trim((string) $avatar_icon) : 'fas fa-user';
$avatar_initials = '';

if ($avatar_name !== '') {
	$avatar_parts = preg_split('/\s+/', $avatar_name);
	foreach ($avatar_parts as $avatar_part) {
		if ($avatar_part === '') {
			continue;
		}
		$avatar_initials .= function_exists('mb_substr') ? mb_substr($avatar_part, 0, 1, 'UTF-8') : substr($avatar_part, 0, 1);
		if ((function_exists('mb_strlen') ? mb_strlen($avatar_initials, 'UTF-8') : strlen($avatar_initials)) >= 2) {
			break;
		}
	}
	$avatar_initials = strtoupper($avatar_initials);
}
?>
<div class="nk-avatar <?= html_escape($avatar_class); ?>">
	<?php if ($avatar_photo !== '') : ?>
		<img class="nk-avatar__image" src="<?= html_escape(doclinc_safe_profile_image_src($avatar_photo)); ?>" alt="<?= html_escape($avatar_alt); ?>">
	<?php elseif ($avatar_initials !== '') : ?>
		<span class="nk-avatar__fallback"><?= html_escape($avatar_initials); ?></span>
	<?php else : ?>
		<i class="<?= html_escape($avatar_icon); ?>"></i>
	<?php endif; ?>
</div>
