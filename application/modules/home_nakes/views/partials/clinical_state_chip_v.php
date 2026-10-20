<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
if (empty($clinical_ui) || !is_array($clinical_ui) || empty($clinical_ui['state'])) {
	return;
}
$clinical_tone = isset($clinical_ui['tone']) ? (string) $clinical_ui['tone'] : 'neutral';
if (!in_array($clinical_tone, array('success', 'info', 'warning', 'danger', 'neutral'), true)) {
	$clinical_tone = 'neutral';
}
$clinical_label = isset($clinical_ui['label']) && trim((string) $clinical_ui['label']) !== ''
	? (string) $clinical_ui['label']
	: (string) $clinical_ui['state'];
?>
<span class="cw-state is-<?= html_escape($clinical_tone); ?>" data-clinical-state="<?= html_escape((string) $clinical_ui['state']); ?>"><?= html_escape($clinical_label); ?></span>
