<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$clinical_steps = isset($clinical_ui['steps']) && is_array($clinical_ui['steps']) ? $clinical_ui['steps'] : array();
if (empty($clinical_steps)) {
	return;
}
?>
<ol class="cw-thread" aria-label="Tahapan alur klinis">
	<?php foreach ($clinical_steps as $clinical_step) :
		$step_state = isset($clinical_step['state']) ? (string) $clinical_step['state'] : 'pending';
		$step_label = isset($clinical_step['label']) ? (string) $clinical_step['label'] : '';
		$step_meta = isset($clinical_step['meta']) ? (string) $clinical_step['meta'] : '';
	?><li class="cw-step is-<?= html_escape($step_state); ?>"<?= $step_state === 'current' ? ' aria-current="step"' : ''; ?>>
		<span class="cw-step-label"><?= html_escape($step_label); ?></span>
		<span class="cw-step-meta"><?= html_escape($step_meta); ?></span>
	</li><?php endforeach; ?>
</ol>
