<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$section_kicker = isset($section_kicker) ? (string) $section_kicker : '';
$section_title = isset($section_title) ? (string) $section_title : '';
$section_meta = isset($section_meta) ? (string) $section_meta : '';
$section_link_label = isset($section_link_label) ? (string) $section_link_label : '';
$section_link_target = isset($section_link_target) ? (string) $section_link_target : '';
$section_class = isset($section_class) ? (string) $section_class : '';
?>
<div class="dl-section-header <?= html_escape($section_class); ?>">
	<div class="min-w-0">
		<?php if ($section_kicker !== '') : ?>
			<div class="dl-card-kicker"><?= html_escape($section_kicker); ?></div>
		<?php endif; ?>
		<h2 class="dl-section-title"><?= html_escape($section_title); ?></h2>
	</div>
	<?php if ($section_meta !== '') : ?>
		<span class="dl-header-count"><?= html_escape($section_meta); ?></span>
	<?php elseif ($section_link_label !== '' && $section_link_target !== '') : ?>
		<a href="<?= html_escape($section_link_target); ?>" class="dl-nakes-link" onclick="showContent('<?= html_escape(ltrim($section_link_target, '#')); ?>')"><?= html_escape($section_link_label); ?></a>
	<?php endif; ?>
</div>
