<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Panel hasil kunjungan untuk Nakes pelaksana. */
if (empty($care_team_workflow_enabled) || empty($visit_clinical_workflow_enabled) || empty($visit_workflow_enrolled) || empty($is_visit_performer)) {
	return;
}
$result_actions = isset($workflow_actions) && is_array($workflow_actions) ? $workflow_actions : array();
$draft = isset($visit_result_draft) && is_object($visit_result_draft) ? $visit_result_draft : null;
$submitted = isset($visit_result_submitted) && is_object($visit_result_submitted) ? $visit_result_submitted : null;
$measurement_id = isset($latest_measurement_id) ? (int) $latest_measurement_id : 0;
$visit_state = isset($visit_status) ? (string) $visit_status : '';
$items_to_lines = function ($items) {
	$lines = array();
	foreach ((array) $items as $item) {
		$label = isset($item['label']) ? trim((string) $item['label']) : '';
		$value = isset($item['value']) ? trim((string) $item['value']) : '';
		if ($value === '' && $label === '') {
			continue;
		}
		$lines[] = $value !== '' && $label !== '' && $value !== $label ? $label . ': ' . $value : ($value !== '' ? $value : $label);
	}
	return implode("\n", $lines);
};
$render_items = function ($items) {
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
$items_to_array = function ($items) {
	$normalized = array();
	foreach ((array) $items as $item) {
		$label = isset($item['label']) ? trim((string) $item['label']) : '';
		$value = isset($item['value']) ? trim((string) $item['value']) : '';
		if ($label === '' && $value === '') {
			continue;
		}
		$normalized[] = array('label' => $label, 'value' => $value);
	}
	return $normalized;
};
$render_item_repeater = function ($list_name, $title, $help, $items) use ($items_to_array, $items_to_lines) {
	$safe_list = preg_replace('/[^a-z_]/', '', (string) $list_name);
	$fallback_id = $safe_list === 'findings' ? 'resultFindings' : 'resultActions';
	$row_html = function ($index, $label = '', $value = '') use ($safe_list, $title) {
		$suffix = $safe_list . '_' . preg_replace('/[^a-z0-9_]/', '', (string) $index);
		return '<div class="cw-repeater-row" data-cw-repeater-row>'
			. '<div class="cw-field"><label class="cw-label" for="' . $safe_list . 'Label' . $suffix . '">' . html_escape($title) . '</label>'
			. '<input class="form-control" id="' . $safe_list . 'Label' . $suffix . '" type="text" maxlength="120" value="' . html_escape($label) . '" data-cw-result-label placeholder="Nama ' . html_escape(strtolower($title)) . '"></div>'
			. '<div class="cw-field"><label class="cw-label" for="' . $safe_list . 'Value' . $suffix . '">Nilai / catatan</label>'
			. '<input class="form-control" id="' . $safe_list . 'Value' . $suffix . '" type="text" maxlength="2000" value="' . html_escape($value) . '" data-cw-result-value placeholder="Nilai atau catatan singkat"></div>'
			. '<button type="button" class="cw-btn cw-btn-ghost" data-cw-repeater-remove>Hapus</button>'
			. '</div>';
	};
	$safe_items = $items_to_array($items);
	echo '<div class="cw-field" data-cw-repeater data-cw-result-list="' . html_escape($safe_list) . '">';
	echo '<span class="cw-label">' . html_escape($title) . '</span>';
	echo '<span class="cw-help">' . html_escape($help) . '</span>';
	echo '<div class="cw-repeater" data-cw-repeater-list>';
	foreach ($safe_items as $index => $item) {
		echo $row_html($index, $item['label'], $item['value']);
	}
	echo '</div>';
	echo '<div><button type="button" class="cw-btn cw-btn-secondary" data-cw-repeater-add><i class="bi bi-plus-circle" aria-hidden="true"></i> Tambah baris</button></div>';
	echo '<details class="cw-result-fallback"><summary>Tulis cepat satu per baris</summary><textarea class="form-control" id="' . $fallback_id . '" data-result-list="' . html_escape($safe_list) . '" rows="3" aria-label="' . html_escape($title) . ' satu per baris">' . html_escape($items_to_lines($items)) . '</textarea></details>';
	echo '<template data-cw-repeater-template>' . $row_html('__INDEX__') . '</template>';
	echo '</div>';
};
$result_vitals = isset($latest_vital_signs) && is_object($latest_vital_signs) ? $latest_vital_signs : null;
$result_correction_reason = isset($visit_workflow['review_summary']['correction_reason'])
	? trim((string) $visit_workflow['review_summary']['correction_reason'])
	: '';
$draft_updated_at = $draft && !empty($draft->updated_at) ? strtotime((string) $draft->updated_at) : false;
$draft_status = $draft
	? ((int) $draft->draft_revision > 0 ? 'Draf revisi ' . (int) $draft->draft_revision : 'Draf baru') . ($draft_updated_at ? ' Â· diperbarui ' . date('d M H:i', $draft_updated_at) : '')
	: '';
?>
<section class="consult-card" id="hasil-kunjungan">
	<h2 class="section-heading"><i class="bi bi-clipboard2-check"></i> Hasil Kunjungan</h2>
	<?php if ($result_correction_reason !== '') : ?>
		<div class="alert alert-warning" role="status">
			<strong>Perbaikan diminta dokter.</strong>
			<span class="d-block mt-1"><?= nl2br(html_escape($result_correction_reason)); ?></span>
			<?php if (!empty($draft->supersedes_result_id)) : ?>
				<span class="d-block small mt-1">Draf ini menggantikan hasil yang sudah dikirim sebelumnya.</span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ($submitted && !$draft) : ?>
		<?php if (!empty($submitted->observation_summary)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Ringkasan pemeriksaan</span><?= nl2br(html_escape((string) $submitted->observation_summary)); ?></div>
		<?php endif; ?>
		<?php if (!empty($submitted->anamnesis)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Anamnesis</span><?= nl2br(html_escape((string) $submitted->anamnesis)); ?></div>
		<?php endif; ?>
		<?php if (!empty($submitted->therapy)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Terapi / tindakan</span><?= nl2br(html_escape((string) $submitted->therapy)); ?></div>
		<?php endif; ?>
		<?php if (!empty($submitted->findings)) : ?>
			<div class="mb-1 fw-semibold">Temuan</div><?php $render_items($submitted->findings); ?>
		<?php endif; ?>
		<?php if (!empty($submitted->actions)) : ?>
			<div class="mb-1 fw-semibold">Tindakan</div><?php $render_items($submitted->actions); ?>
		<?php endif; ?>
		<?php if (!empty($submitted->performer_notes)) : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Catatan Nakes</span><?= nl2br(html_escape((string) $submitted->performer_notes)); ?></div>
		<?php endif; ?>
		<p class="small text-muted">Hasil versi <?= html_escape((int) $submitted->version_no); ?> sudah dikirim.</p>
		<?php if (!empty($result_actions['correct_result'])) : ?>
			<form class="clinical-workflow-form" data-clinical-form data-clinical-endpoint="<?= html_escape(base_url('konsultasi_nakes/visit_result_correction')); ?>" data-clinical-success="Draf koreksi dibuat.">
				<input type="hidden" name="request_id" value="<?= html_escape((int) $request_id); ?>">
				<input type="hidden" name="superseded_result_id" value="<?= html_escape((int) $submitted->visit_result_id); ?>">
				<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>
				<button type="submit" class="btn btn-outline-success rounded-pill clinical-workflow-submit">Buat draf koreksi</button>
				<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
			</form>
		<?php endif; ?>
	<?php elseif ($draft) : ?>
		<div class="cw-source-note mb-3">
			<span class="cw-source-label">Tanda vital</span>
			<?php if ($result_vitals) : ?>
				<p>
					<?= html_escape(($result_vitals->systolic ?? '-') . '/' . ($result_vitals->diastolic ?? '-')); ?> mmHg
					Â· N <?= html_escape($result_vitals->pulse ?? '-'); ?>/menit
					Â· RR <?= html_escape($result_vitals->respiratory_rate ?? '-'); ?>/menit
					Â· <?= html_escape($result_vitals->temperature_c ?? '-'); ?> Â°C
					Â· SpO2 <?= html_escape($result_vitals->oxygen_saturation ?? '-'); ?>%
				</p>
			<?php else : ?>
				<p>Belum ada tanda vital. Catat dulu sebelum mengirim hasil.</p>
			<?php endif; ?>
			<div><a href="#vital-signs" class="small fw-semibold"><?= $result_vitals ? 'Perbarui tanda vital' : 'Catat tanda vital'; ?></a></div>
		</div>
		<?php if ($draft_status !== '') : ?>
			<p class="cw-meta" role="status" aria-live="polite"><?= html_escape($draft_status); ?></p>
		<?php endif; ?>
		<form class="clinical-workflow-form" data-clinical-form data-clinical-endpoint="<?= html_escape(base_url('konsultasi_nakes/visit_result_draft')); ?>" data-clinical-success="Draf hasil tersimpan.">
			<input type="hidden" name="visit_result_id" value="<?= html_escape((int) $draft->visit_result_id); ?>">
			<input type="hidden" name="expected_revision" value="<?= html_escape((int) $draft->draft_revision); ?>">
			<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>

			<fieldset class="cw-result-editor">
				<legend class="cw-section-title">Catatan klinis</legend>
				<div class="cw-field">
					<label class="cw-label" for="resultObservation">Ringkasan pemeriksaan</label>
					<textarea class="form-control" id="resultObservation" name="observation_summary" rows="3" maxlength="5000"><?= html_escape((string) ($draft->observation_summary ?? '')); ?></textarea>
				</div>
				<div class="cw-field">
					<label class="cw-label" for="resultAnamnesis">Anamnesis</label>
					<textarea class="form-control" id="resultAnamnesis" name="anamnesis" rows="3" maxlength="5000" placeholder="Riwayat keluhan, onset, faktor pemicuâ€¦"><?= html_escape((string) ($draft->anamnesis ?? '')); ?></textarea>
					<div class="cw-help">Catatan ini menjadi bahan review dokter; rekam medis tetap disusun dan ditandatangani dokter.</div>
				</div>
				<div class="cw-field">
					<label class="cw-label" for="resultTherapy">Terapi / tindakan yang diberikan</label>
					<textarea class="form-control" id="resultTherapy" name="therapy" rows="2" maxlength="5000" placeholder="Obat, dosis, atau tindakan yang diberikanâ€¦"><?= html_escape((string) ($draft->therapy ?? '')); ?></textarea>
				</div>
			</fieldset>

			<?php $render_item_repeater('findings', 'Temuan', 'Tambahkan temuan pemeriksaan satu per baris.', $draft->findings); ?>
			<?php $render_item_repeater('actions', 'Tindakan', 'Tambahkan tindakan yang dilakukan satu per baris.', $draft->actions); ?>

			<div class="cw-field">
				<label class="cw-label" for="resultNotes">Catatan Nakes</label>
				<textarea class="form-control" id="resultNotes" name="performer_notes" rows="2" maxlength="5000"><?= html_escape((string) ($draft->performer_notes ?? '')); ?></textarea>
			</div>
			<div class="cw-actions cw-result-actions">
				<button type="submit" class="btn btn-outline-success rounded-pill clinical-workflow-submit">Simpan draf</button>
				<?php if (!empty($result_actions['submit_result'])) : ?>
					<?php if ($visit_state !== 'completed') : ?>
						<button type="button" class="btn btn-secondary rounded-pill" disabled aria-describedby="resultSubmitVisitReason">Simpan &amp; kirim hasil</button>
						<p class="small text-muted mb-0 mt-1" id="resultSubmitVisitReason">Selesaikan kunjungan terlebih dahulu sebelum mengirim hasil.</p>
					<?php elseif ($measurement_id > 0) : ?>
						<button type="button" class="btn btn-success rounded-pill" data-clinical-save-then-submit="#resultSubmitForm">Simpan &amp; kirim hasil</button>
					<?php else : ?>
						<button type="button" class="btn btn-secondary rounded-pill" disabled aria-describedby="resultSubmitReason">Kirim hasil kunjungan</button>
						<p class="small text-muted mb-0 mt-1" id="resultSubmitReason">Catat tanda vital terlebih dahulu sebelum mengirim hasil. <a href="#vital-signs">Catat sekarang</a>.</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
		</form>

		<?php if (!empty($result_actions['submit_result'])) : ?>
			<form id="resultSubmitForm" class="clinical-workflow-form mt-1" data-clinical-form data-clinical-endpoint="<?= html_escape(base_url('konsultasi_nakes/visit_result_submit')); ?>" data-clinical-success="Hasil kunjungan terkirim.">
				<input type="hidden" name="visit_result_id" value="<?= html_escape((int) $draft->visit_result_id); ?>">
				<input type="hidden" name="submission_key" value="" data-clinical-submission-key>
				<?php if ($measurement_id > 0) : ?>
					<input type="hidden" name="measurement_ids[]" value="<?= html_escape($measurement_id); ?>">
				<?php endif; ?>
				<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
			</form>
		<?php endif; ?>
	<?php elseif (!in_array($visit_state, array('arrived', 'in_service', 'completed'), true)) : ?>
		<p class="small text-muted mb-0">Formulir hasil tersedia setelah Anda tiba di lokasi.</p>
	<?php else : ?>
		<p class="small text-muted mb-0">Formulir hasil belum dapat disiapkan. Muat ulang halaman atau hubungi pengelola.</p>
	<?php endif; ?>
</section>
