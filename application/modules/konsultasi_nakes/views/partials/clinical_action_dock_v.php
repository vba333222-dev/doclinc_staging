<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
if (empty($clinical_ui) || !is_array($clinical_ui) || empty($clinical_ui['state'])) {
	return;
}
$dock_action = isset($clinical_ui['next_action']) ? trim((string) $clinical_ui['next_action']) : '';
$dock_url = isset($clinical_ui['next_action_url']) ? trim((string) $clinical_ui['next_action_url']) : '';
if ($dock_action === '') {
	return;
}
?>
<div class="cw-dock" id="aksi-klinis" role="region" aria-label="Aksi klinis berikutnya">
	<div>
		<span class="cw-eyebrow">Langkah berikutnya</span>
		<strong class="d-block"><?= html_escape($dock_action); ?></strong>
	</div>
	<div class="cw-actions">
		<?php if ($dock_url !== '') : ?>
			<a class="cw-btn cw-btn-primary" href="<?= html_escape($dock_url); ?>"><?= html_escape($dock_action); ?></a>
		<?php endif; ?>
	</div>
</div>
