<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$empty_title = isset($empty_title) ? (string) $empty_title : '';
$empty_message = isset($empty_message) ? (string) $empty_message : '';
$empty_image = isset($empty_image) ? (string) $empty_image : '';
$empty_class = isset($empty_class) ? (string) $empty_class : 'dl-soft-empty';
?>
<div class="<?= html_escape($empty_class); ?>">
	<?php if ($empty_image !== '') : ?>
		<img src="<?= html_escape($empty_image); ?>" width="140" alt="Tidak ada data">
	<?php endif; ?>
	<?php if ($empty_title !== '') : ?>
		<p class="dl-task-title mb-1"><?= html_escape($empty_title); ?></p>
	<?php endif; ?>
	<?php if ($empty_message !== '') : ?>
		<p class="dl-muted-copy mb-0"><?= html_escape($empty_message); ?></p>
	<?php endif; ?>
</div>
