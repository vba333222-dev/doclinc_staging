<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Panel review hasil kunjungan untuk dokter penanggung jawab. */
if (empty($care_team_workflow_enabled) || empty($visit_clinical_workflow_enabled) || empty($visit_workflow_enrolled) || empty($is_responsible_doctor)) {
	return;
}
$review_actions = isset($workflow_actions) && is_array($workflow_actions) ? $workflow_actions : array();
$review_workflow = isset($visit_workflow) && is_array($visit_workflow) ? $visit_workflow : array();
$review_submitted = isset($visit_result_submitted) && is_object($visit_result_submitted) ? $visit_result_submitted : null;
$review_record = isset($review_workflow['review_summary']) && is_array($review_workflow['review_summary']) ? $review_workflow['review_summary'] : null;
$review_history = isset($review_history) && is_array($review_history) ? $review_history : array();
$review_vitals = isset($latest_vital_signs) && is_object($latest_vital_signs) ? $latest_vital_signs : null;
$review_submitted_at = $review_submitted && !empty($review_submitted->submitted_at) ? strtotime((string) $review_submitted->submitted_at) : false;
$render_review_items = function ($items) {
	if (empty($items)) {
		return;
	}
	echo '<ul class="mb-2">';
	foreach ((array) $items as $item) {
		$label = isset($item['label']) ? trim((string) $item['label']) : '';
		$value = isset($item['value']) ? trim((string) $item['value']) : '';
		echo '<li><span class="fw-semibold">' . html_escape($label !== '' ? $label : 'Catatan') . ':</span> ' . html_escape($value) . '</li>';
	}
	echo '</ul>';
};
?>
<section class="consult-card" id="review-hasil">
	<h2 class="section-heading"><i class="bi bi-clipboard2-pulse"></i> Review Hasil Kunjungan</h2>

	<?php if (!$review_submitted) : ?>
		<p class="small text-muted mb-0">Menunggu Nakes mengirim hasil kunjungan.</p>
	<?php else : ?>
		<?php if (!empty($review_submitted->observation_summary)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Ringkasan pemeriksaan</span><?= nl2br(html_escape((string) $review_submitted->observation_summary)); ?></div>
		<?php endif; ?>
		<?php if (!empty($review_submitted->anamnesis)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Anamnesis (Nakes)</span><?= nl2br(html_escape((string) $review_submitted->anamnesis)); ?></div>
		<?php endif; ?>
		<?php if (!empty($review_submitted->therapy)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Terapi / tindakan (Nakes)</span><?= nl2br(html_escape((string) $review_submitted->therapy)); ?></div>
		<?php endif; ?>
		<?php if (!empty($review_submitted->findings)) : ?>
			<div class="mb-1 fw-semibold">Temuan</div><?php $render_review_items($review_submitted->findings); ?>
		<?php endif; ?>
		<?php if (!empty($review_submitted->actions)) : ?>
			<div class="mb-1 fw-semibold">Tindakan</div><?php $render_review_items($review_submitted->actions); ?>
		<?php endif; ?>
		<?php if (!empty($review_submitted->performer_notes)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Catatan Nakes</span><?= nl2br(html_escape((string) $review_submitted->performer_notes)); ?></div>
		<?php endif; ?>
		<p class="small text-muted">Hasil versi <?= html_escape((int) $review_submitted->version_no); ?><?= $review_submitted_at ? ' · dikirim ' . html_escape(date('d M Y H:i', $review_submitted_at)) : ''; ?>.</p>
		<div class="cw-source-note mb-3">
			<span class="cw-source-label">Tanda vital</span>
			<?php if ($review_vitals) : ?>
				<p>
					<?= html_escape(($review_vitals->systolic ?? '-') . '/' . ($review_vitals->diastolic ?? '-')); ?> mmHg
					· N <?= html_escape($review_vitals->pulse ?? '-'); ?>/menit
					· RR <?= html_escape($review_vitals->respiratory_rate ?? '-'); ?>/menit
					· <?= html_escape($review_vitals->temperature_c ?? '-'); ?> °C
					· SpO2 <?= html_escape($review_vitals->oxygen_saturation ?? '-'); ?>%
				</p>
			<?php else : ?>
				<p>Belum ada tanda vital tercatat.</p>
			<?php endif; ?>
		</div>

		<?php if ($review_record && (int) ($review_record['visit_result_id'] ?? 0) === (int) $review_submitted->visit_result_id) : ?>
			<div class="alert <?= (string) $review_record['decision'] === 'approved' ? 'alert-success' : 'alert-warning'; ?> mb-0" role="status">
				<strong><?= (string) $review_record['decision'] === 'approved' ? 'Hasil disetujui' : 'Menunggu perbaikan Nakes'; ?></strong>
				<?php if (!empty($review_record['correction_reason'])) : ?>
					<span class="d-block small mt-1"><?= nl2br(html_escape((string) $review_record['correction_reason'])); ?></span>
				<?php endif; ?>
				<?php if ((string) $review_record['decision'] === 'approved') : ?>
					<a class="d-block small fw-semibold mt-1" href="#rekam-medis">Lanjut ke rekam medis</a>
				<?php endif; ?>
			</div>
		<?php elseif (!empty($review_actions['review'])) : ?>
			<form id="reviewForm" class="clinical-workflow-form mt-2" data-clinical-form data-clinical-endpoint="<?= html_escape(base_url('clinical/review')); ?>" data-clinical-success="Review tersimpan.">
				<input type="hidden" name="request_id" value="<?= html_escape((int) $request_id); ?>">
				<input type="hidden" name="visit_result_id" value="<?= html_escape((int) $review_submitted->visit_result_id); ?>">
				<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>
				<fieldset class="cw-field">
					<legend class="cw-label">Keputusan</legend>
					<div class="cw-choice-grid" role="radiogroup" aria-describedby="reviewDecisionHelp">
						<label class="cw-choice">
							<input type="radio" name="decision" value="approved" data-review-decision required>
							<span>
								<span class="cw-choice-title">Setujui hasil</span>
								<span class="cw-choice-text">Hasil dapat dilanjutkan ke finalisasi rekam medis.</span>
							</span>
						</label>
						<label class="cw-choice">
							<input type="radio" name="decision" value="correction_required" data-review-decision required>
							<span>
								<span class="cw-choice-title">Minta perbaikan</span>
								<span class="cw-choice-text">Nakes akan menerima alasan dan dapat mengirim ulang.</span>
							</span>
						</label>
					</div>
					<div class="cw-help" id="reviewDecisionHelp">Pilih satu keputusan sebelum menyimpan review.</div>
				</fieldset>
				<div class="cw-field d-none" data-review-reason>
					<label class="cw-label" for="reviewReason">Alasan perbaikan</label>
					<textarea class="form-control" id="reviewReason" name="correction_reason" rows="2" maxlength="5000" aria-describedby="reviewReasonHelp"></textarea>
					<div class="cw-help" id="reviewReasonHelp">Jelaskan bagian yang perlu diperbaiki agar Nakes dapat menindaklanjuti.</div>
				</div>
				<div class="cw-field">
					<label class="cw-label" for="reviewNotes">Catatan review</label>
					<textarea class="form-control" id="reviewNotes" name="review_notes" rows="2" maxlength="5000"></textarea>
				</div>
				<div class="cw-actions cw-result-actions">
					<button type="submit" class="btn btn-success rounded-pill clinical-workflow-submit">Simpan review</button>
				</div>
				<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
			</form>
		<?php endif; ?>

		<?php if (!empty($review_history)) : ?>
			<section class="cw-card mt-3" aria-labelledby="reviewHistoryTitle">
				<div class="cw-card-head">
					<h3 class="cw-section-title" id="reviewHistoryTitle">Riwayat review</h3>
				</div>
				<div class="cw-card-body">
					<div class="cw-action-items" role="list">
						<?php foreach ($review_history as $history_item) :
							$history_decision = isset($history_item['decision']) ? (string) $history_item['decision'] : '';
							$history_time = !empty($history_item['reviewed_at']) && strtotime((string) $history_item['reviewed_at'])
								? date('d M Y H:i', strtotime((string) $history_item['reviewed_at']))
								: '';
							$history_reason = isset($history_item['correction_reason']) ? trim((string) $history_item['correction_reason']) : '';
							$history_version = isset($history_item['version_no']) ? (int) $history_item['version_no'] : 0;
							$history_reviewer = isset($history_item['reviewer_name']) ? trim((string) $history_item['reviewer_name']) : '';
						?>
						<div class="cw-action-item" role="listitem">
							<span class="cw-action-item-main">
								<strong><?= $history_decision === 'approved' ? 'Disetujui' : 'Minta perbaikan'; ?><?= $history_version > 0 ? ' · versi ' . $history_version : ''; ?></strong>
								<span class="cw-meta"><?= $history_time !== '' ? html_escape($history_time) : ''; ?><?= $history_reviewer !== '' ? ' · ' . html_escape($history_reviewer) : ''; ?></span>
								<?php if ($history_reason !== '') : ?><span class="cw-meta"><?= nl2br(html_escape($history_reason)); ?></span><?php endif; ?>
							</span>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</section>
