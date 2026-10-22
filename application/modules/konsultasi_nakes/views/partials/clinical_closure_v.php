<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Rekam medis klinis, finalisasi, amendemen, dan penutupan. */
if (empty($care_team_workflow_enabled) || empty($visit_clinical_workflow_enabled) || empty($visit_workflow_enrolled) || empty($is_responsible_doctor)) {
	return;
}
$closure_actions = isset($workflow_actions) && is_array($workflow_actions) ? $workflow_actions : array();
$closure_workflow = isset($visit_workflow) && is_array($visit_workflow) ? $visit_workflow : array();
$clinical_record = isset($clinical_record) && is_object($clinical_record) ? $clinical_record : null;
$record_finalized = $clinical_record && $clinical_record->clinical_finalized_at !== null;
$closure_summary = isset($closure_workflow['closure_summary']) && is_array($closure_workflow['closure_summary']) ? $closure_workflow['closure_summary'] : null;
$closure_state = isset($closure_workflow['state']) ? (string) $closure_workflow['state'] : '';
$closure_review = isset($closure_workflow['review_summary']) && is_array($closure_workflow['review_summary']) ? $closure_workflow['review_summary'] : null;
$closure_disposition_decision = isset($visit_disposition->decision)
	? (string) $visit_disposition->decision
	: (isset($closure_workflow['disposition_summary']['decision']) ? (string) $closure_workflow['disposition_summary']['decision'] : '');
$closure_non_visit = $closure_disposition_decision === 'non_visit';
$closure_review_decision = $closure_review && isset($closure_review['decision']) ? (string) $closure_review['decision'] : '';
$amendment_history = isset($amendment_history) && is_array($amendment_history) ? $amendment_history : array();
$record_effective_values = isset($record_effective_values) && is_array($record_effective_values) ? $record_effective_values : array();
$record_display_values = array(
	'diagnosis' => isset($record_effective_values['diagnosis']) ? (string) $record_effective_values['diagnosis'] : (string) ($clinical_record->diagnosis ?? ''),
	'treatment' => isset($record_effective_values['treatment']) ? (string) $record_effective_values['treatment'] : (string) ($clinical_record->treatment ?? ''),
	'recommendations' => isset($record_effective_values['recommendations']) ? (string) $record_effective_values['recommendations'] : (string) ($clinical_record->recommendations ?? ''),
	'anamnesis' => isset($record_effective_values['anamnesis']) ? (string) $record_effective_values['anamnesis'] : (string) ($clinical_record->anamnesis ?? ''),
);
$record_finalized_by_name = isset($clinical_record_finalized_by_name) ? trim((string) $clinical_record_finalized_by_name) : '';
$closure_diagnosis_ready = $clinical_record && trim((string) ($clinical_record->diagnosis ?? '')) !== '';
$closure_diagnosis_excerpt = $clinical_record
	? (function_exists('mb_substr') ? mb_substr((string) $clinical_record->diagnosis, 0, 80) : substr((string) $clinical_record->diagnosis, 0, 80))
	: '';
$closure_review_ready = $closure_non_visit || $closure_review_decision === 'approved';
$closure_finalize_ready = $closure_diagnosis_ready && $closure_review_ready;
$amendment_groups = array();
foreach ($amendment_history as $amendment_row) {
	$amendment_id = (int) ($amendment_row['amendment_id'] ?? 0);
	if ($amendment_id < 1) {
		continue;
	}
	if (!isset($amendment_groups[$amendment_id])) {
		$amendment_groups[$amendment_id] = array(
			'sequence_no' => (int) ($amendment_row['sequence_no'] ?? 0),
			'reason' => (string) ($amendment_row['reason'] ?? ''),
			'created_at' => (string) ($amendment_row['created_at'] ?? ''),
			'created_by_name' => (string) ($amendment_row['created_by_name'] ?? ''),
			'items' => array(),
		);
	}
	if (!empty($amendment_row['field_key'])) {
		$amendment_groups[$amendment_id]['items'][] = array(
			'field_key' => (string) $amendment_row['field_key'],
			'previous_value' => (string) ($amendment_row['previous_value'] ?? ''),
			'corrected_value' => (string) ($amendment_row['corrected_value'] ?? ''),
		);
	}
}
$record_fields = array(
	'diagnosis' => 'Diagnosis',
	'treatment' => 'Tindakan / terapi',
	'recommendations' => 'Saran',
	'anamnesis' => 'Anamnesis',
);
$closure_clinical = isset($clinical_ui) && is_array($clinical_ui) && !empty($clinical_ui['state']) ? $clinical_ui : null;
$closure_clinical_label = $closure_clinical
	? (isset($closure_clinical['label']) ? (string) $closure_clinical['label'] : (string) $closure_clinical['state'])
	: '';
?>
<section class="consult-card" id="rekam-medis-klinis">
	<span id="rekam-medis" class="visually-hidden"></span>
	<div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
		<h2 class="section-heading"><i class="bi bi-journal-medical"></i> Rekam Medis &amp; Penutupan</h2>
		<?php if ($closure_clinical) : ?>
			<?php
			$closure_tone = isset($closure_clinical['tone']) ? (string) $closure_clinical['tone'] : 'neutral';
			if (!in_array($closure_tone, array('success', 'info', 'warning', 'danger', 'neutral'), true)) {
				$closure_tone = 'neutral';
			}
			?>
			<span class="cw-state is-<?= html_escape($closure_tone); ?>" data-clinical-state="<?= html_escape((string) $closure_clinical['state']); ?>"><?= html_escape($closure_clinical_label); ?></span>
		<?php endif; ?>
	</div>

	<?php if (!$clinical_record || !$record_finalized) : ?>
		<form class="clinical-workflow-form" data-clinical-form data-clinical-endpoint="<?= html_escape(base_url('clinical/record')); ?>" data-clinical-success="Rekam medis tersimpan.">
			<input type="hidden" name="request_id" value="<?= html_escape((int) $request_id); ?>">
			<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>
			<?php if (!empty($visit_result_submitted)) : ?>
				<div class="cw-field">
					<button type="button" class="cw-btn cw-btn-secondary"
						data-cw-copy-result
						data-copy-anamnesis="<?= html_escape((string) ($visit_result_submitted->anamnesis ?? '')); ?>"
						data-copy-treatment="<?= html_escape((string) ($visit_result_submitted->therapy ?? '')); ?>"
						data-copy-source="hasil kunjungan Nakes versi <?= html_escape((int) $visit_result_submitted->version_no); ?>">Salin dari hasil kunjungan</button>
					<div class="cw-help">Sumber: hasil kunjungan Nakes versi <?= html_escape((int) $visit_result_submitted->version_no); ?>. Field yang sudah terisi tidak akan ditimpa tanpa konfirmasi.</div>
					<div class="cw-help" data-cw-copy-status role="status" aria-live="polite"></div>
				</div>
			<?php endif; ?>
			<div class="mb-3">
				<label class="form-label fw-semibold" for="recordDiagnosis">Diagnosis utama *</label>
				<textarea class="form-control" id="recordDiagnosis" name="diagnosis" rows="2" maxlength="5000" required><?= html_escape((string) ($clinical_record->diagnosis ?? '')); ?></textarea>
			</div>
			<div class="mb-3">
				<label class="form-label fw-semibold" for="recordTreatment">Tindakan / terapi</label>
				<textarea class="form-control" id="recordTreatment" name="treatment" rows="3" maxlength="5000"><?= html_escape((string) ($clinical_record->treatment ?? '')); ?></textarea>
			</div>
			<div class="mb-3">
				<label class="form-label fw-semibold" for="recordRecommendations">Saran</label>
				<textarea class="form-control" id="recordRecommendations" name="recommendations" rows="2" maxlength="5000"><?= html_escape((string) ($clinical_record->recommendations ?? '')); ?></textarea>
			</div>
			<div class="mb-3">
				<label class="form-label fw-semibold" for="recordAnamnesis">Anamnesis</label>
				<textarea class="form-control" id="recordAnamnesis" name="anamnesis" rows="2" maxlength="5000"><?= html_escape((string) ($clinical_record->anamnesis ?? '')); ?></textarea>
			</div>
			<button type="submit" class="btn btn-outline-success rounded-pill clinical-workflow-submit">Simpan rekam medis</button>
			<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
		</form>
	<?php endif; ?>

	<?php if (!$record_finalized) : ?>
		<section class="cw-card mt-3" aria-labelledby="finalizeChecklistTitle">
			<div class="cw-card-head">
				<h3 class="cw-section-title" id="finalizeChecklistTitle">Checklist finalisasi</h3>
			</div>
			<div class="cw-card-body">
				<ul class="cw-checklist">
					<li class="<?= $closure_review_ready ? 'is-done' : 'is-pending'; ?>"><?= $closure_non_visit ? 'Disposisi tanpa kunjungan' : 'Review dokter disetujui'; ?></li>
					<li class="<?= $closure_diagnosis_ready ? 'is-done' : 'is-pending'; ?>">Diagnosis utama terisi</li>
					<li class="<?= $clinical_record ? 'is-done' : 'is-pending'; ?>">Rekam medis tersimpan</li>
				</ul>
				<?php if (!$closure_finalize_ready) : ?>
					<p class="cw-help mb-0">Finalisasi aktif setelah review disetujui dan diagnosis terisi.</p>
				<?php endif; ?>
				<p class="cw-help mb-0">Finalisasi hanya menerima diagnosis yang terkode ICD-10. Pilih salah satu saran dari daftar ICD-10 pada kolom Diagnosis, jangan mengetik teks bebas.</p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ($clinical_record) : ?>
		<p class="small text-muted mt-2 mb-1">
			<?= $record_finalized ? 'Rekam medis difinalisasi' : 'Rekam medis belum difinalisasi'; ?>
			<?php if ($record_finalized && !empty($clinical_record->clinical_finalized_at)) : ?>
				· <?= html_escape(date('d M Y H:i', strtotime((string) $clinical_record->clinical_finalized_at))); ?>
			<?php endif; ?>
			<?php if ($record_finalized && $record_finalized_by_name !== '') : ?>
				· oleh <?= html_escape($record_finalized_by_name); ?>
			<?php endif; ?>
		</p>
		<?php if ($record_finalized) : ?>
			<div class="mb-2">
				<div class="fw-semibold">Diagnosis</div><?= nl2br(html_escape($record_display_values['diagnosis'])); ?>
				<?php if ($record_display_values['treatment'] !== '') : ?><div class="fw-semibold mt-2">Tindakan / terapi</div><?= nl2br(html_escape($record_display_values['treatment'])); ?><?php endif; ?>
				<?php if ($record_display_values['recommendations'] !== '') : ?><div class="fw-semibold mt-2">Saran</div><?= nl2br(html_escape($record_display_values['recommendations'])); ?><?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if (!$record_finalized && !empty($closure_actions['finalize'])) : ?>
			<form class="clinical-workflow-form mt-2" data-clinical-form data-clinical-endpoint="<?= html_escape(base_url('clinical/finalize')); ?>" data-clinical-success="Rekam medis difinalisasi.">
				<input type="hidden" name="request_id" value="<?= html_escape((int) $request_id); ?>">
				<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>
				<button type="submit" class="btn btn-success rounded-pill clinical-workflow-submit"
					data-cw-confirm="Finalisasi rekam medis untuk diagnosis &quot;<?= html_escape($closure_diagnosis_excerpt); ?>&quot;? Setelah difinalisasi, perubahan harus melalui amendemen."
					data-cw-confirm-title="Finalisasi rekam medis?"
					data-cw-confirm-ok="Ya, finalisasi">Finalisasi rekam medis</button>
				<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
			</form>
		<?php endif; ?>

		<?php if ($record_finalized && !empty($closure_actions['amend'])) : ?>
			<?php if (!empty($record_effective_values)) : ?>
				<div class="cw-card mb-2">
					<div class="cw-card-body py-2">
						<span class="cw-eyebrow">Nilai saat ini</span>
						<dl class="cw-context-list mt-1">
							<?php foreach ($record_fields as $field_key => $field_label) : ?>
								<dt><?= html_escape($field_label); ?></dt>
								<dd><?= html_escape(trim((string) ($record_effective_values[$field_key] ?? '')) !== '' ? (string) $record_effective_values[$field_key] : '-'); ?></dd>
							<?php endforeach; ?>
						</dl>
					</div>
				</div>
			<?php endif; ?>
			<form class="clinical-workflow-form mt-2" data-clinical-form data-amend-form data-clinical-endpoint="<?= html_escape(base_url('clinical/amend')); ?>" data-clinical-success="Koreksi rekam medis tersimpan.">
				<input type="hidden" name="request_id" value="<?= html_escape((int) $request_id); ?>">
				<input type="hidden" name="record_id" value="<?= html_escape((int) $clinical_record->record_id); ?>">
				<input type="hidden" name="items" value="" data-amend-items>
				<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>
				<div class="mb-2">
					<label class="form-label fw-semibold" for="amendField">Kolom yang dikoreksi</label>
					<select class="form-select" id="amendField" name="field_key" required>
						<?php foreach ($record_fields as $field_key => $field_label) : ?>
							<option value="<?= html_escape($field_key); ?>"><?= html_escape($field_label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-2">
					<label class="form-label fw-semibold" for="amendValue">Nilai koreksi</label>
					<textarea class="form-control" id="amendValue" name="corrected_value" rows="2" maxlength="5000" required></textarea>
				</div>
				<div class="mb-2">
					<label class="form-label fw-semibold" for="amendReason">Alasan koreksi</label>
					<input type="text" class="form-control" id="amendReason" name="reason" maxlength="500" required>
				</div>
				<button type="submit" class="btn btn-outline-success rounded-pill clinical-workflow-submit"
					data-cw-confirm="Simpan koreksi rekam medis? Nilai sebelumnya tetap tersimpan di riwayat amendemen."
					data-cw-confirm-title="Simpan koreksi?"
					data-cw-confirm-ok="Ya, simpan koreksi">Simpan koreksi</button>
				<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
			</form>
		<?php endif; ?>

		<?php if (!empty($amendment_groups)) : ?>
			<section class="cw-card mt-3" aria-labelledby="amendmentHistoryTitle">
				<div class="cw-card-head">
					<h3 class="cw-section-title" id="amendmentHistoryTitle">Riwayat amendemen</h3>
				</div>
				<div class="cw-card-body">
					<div class="cw-action-items" role="list">
						<?php foreach ($amendment_groups as $amendment_group) :
							$amendment_time = !empty($amendment_group['created_at']) && strtotime((string) $amendment_group['created_at'])
								? date('d M Y H:i', strtotime((string) $amendment_group['created_at']))
								: '';
						?>
						<div class="cw-action-item" role="listitem">
							<span class="cw-action-item-main">
								<strong>Koreksi #<?= (int) $amendment_group['sequence_no']; ?><?= $amendment_time !== '' ? ' · ' . html_escape($amendment_time) : ''; ?></strong>
								<span class="cw-meta"><?= html_escape($amendment_group['created_by_name'] !== '' ? $amendment_group['created_by_name'] : 'Dokter'); ?><?= $amendment_group['reason'] !== '' ? ' · ' . html_escape($amendment_group['reason']) : ''; ?></span>
								<?php foreach ($amendment_group['items'] as $amendment_item) : ?>
									<span class="cw-meta"><strong><?= html_escape($record_fields[$amendment_item['field_key']] ?? $amendment_item['field_key']); ?>:</strong> <?= html_escape($amendment_item['corrected_value']); ?></span>
								<?php endforeach; ?>
							</span>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ($closure_summary) : ?>
			<div class="alert alert-success mt-2 mb-0" role="status">
				<strong>Konsultasi ditutup</strong>
				<?php if (!empty($closure_summary['closed_at'])) : ?>
					<span class="d-block small"><?= html_escape(date('d M Y H:i', strtotime((string) $closure_summary['closed_at']))); ?></span>
				<?php endif; ?>
			</div>
		<?php elseif (!empty($closure_actions['close'])) : ?>
			<span id="penutupan" class="visually-hidden"></span>
			<form class="clinical-workflow-form mt-2" data-clinical-form data-clinical-endpoint="<?= html_escape(base_url('clinical/close')); ?>" data-clinical-success="Konsultasi ditutup.">
				<input type="hidden" name="request_id" value="<?= html_escape((int) $request_id); ?>">
				<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>
				<button type="submit" class="btn btn-success rounded-pill clinical-workflow-submit"
					data-cw-confirm="<?= $closure_non_visit ? 'Konsultasi tanpa kunjungan akan ditutup. Rekam medis final tetap tersimpan.' : 'Kunjungan akan ditutup. Pastikan rekam medis final dan hasil kunjungan sudah benar.'; ?>"
					data-cw-confirm-title="<?= $closure_non_visit ? 'Tutup konsultasi tanpa kunjungan?' : 'Tutup konsultasi kunjungan?'; ?>"
					data-cw-confirm-ok="Ya, tutup konsultasi">Tutup konsultasi</button>
				<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</section>
