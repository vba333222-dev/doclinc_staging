<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
if (empty($disposition) || !is_object($disposition)) {
	return;
}
$summary_decision = (string) ($disposition->decision ?? '');
$summary_label = $summary_decision === 'visit' ? 'Kunjungan Nakes' : 'Tanpa kunjungan';
$summary_urgency_labels = array('routine' => 'Rutin', 'priority' => 'Prioritas', 'urgent' => 'Mendesak');
$summary_urgency = (string) ($disposition->urgency ?? '');
$summary_profession = trim((string) ($disposition->required_profession ?? ''));
$summary_instructions = trim((string) ($disposition->instructions ?? ''));
?>
<div class="mb-2" data-disposition-summary="<?= html_escape((int) ($disposition->request_id ?? 0)); ?>">
	<div class="d-flex flex-wrap gap-2 align-items-center">
		<span class="badge <?= $summary_decision === 'visit' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= html_escape($summary_label); ?></span>
		<?php if ($summary_decision === 'visit' && isset($summary_urgency_labels[$summary_urgency])) : ?>
			<span class="small text-muted">Urgensi: <?= html_escape($summary_urgency_labels[$summary_urgency]); ?></span>
		<?php endif; ?>
		<?php if ($summary_profession !== '') : ?>
			<span class="small text-muted">Profesi: <?= html_escape($summary_profession); ?></span>
		<?php endif; ?>
	</div>
	<?php if ($summary_instructions !== '') : ?>
		<div class="small mt-1"><span class="fw-semibold">Instruksi dokter:</span> <?= nl2br(html_escape($summary_instructions)); ?></div>
	<?php endif; ?>
</div>
