<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$clinical_counts = isset($clinical_action_counts) && is_array($clinical_action_counts) ? $clinical_action_counts : array();
if (empty($clinical_counts)) {
	return;
}
$clinical_stats = array(
	array('label' => 'Perlu tindakan', 'value' => (int) ($clinical_counts['needs_action'] ?? 0), 'tone' => 'warning'),
	array('label' => 'Menunggu review', 'value' => (int) ($clinical_counts['waiting_review'] ?? 0), 'tone' => 'info'),
	array('label' => 'Siap ditutup', 'value' => (int) ($clinical_counts['ready_to_close'] ?? 0), 'tone' => 'success'),
	array('label' => 'Selesai hari ini', 'value' => (int) ($clinical_counts['completed_today'] ?? 0), 'tone' => 'neutral'),
);
?>
<div class="cw-stats" role="list" aria-label="Ringkasan tindakan klinis">
	<?php foreach ($clinical_stats as $clinical_stat) : ?>
		<div class="cw-stat is-<?= html_escape($clinical_stat['tone']); ?>" role="listitem">
			<span class="cw-stat-label"><?= html_escape($clinical_stat['label']); ?></span>
			<strong class="cw-stat-value"><?= (int) $clinical_stat['value']; ?></strong>
		</div>
	<?php endforeach; ?>
</div>
