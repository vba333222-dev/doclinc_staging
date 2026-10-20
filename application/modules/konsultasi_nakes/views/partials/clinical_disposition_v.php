<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Panel disposisi klinis dokter.
   Hanya tampil ketika alur klinis aktif. Disposisi bersifat opsional: dokter
   penanggung jawab dapat menetapkannya, melewatinya, atau memilih Nakes yang
   akan ditunjuk. Peran lain hanya melihat status tanpa formulir. */
if (empty($care_team_workflow_enabled) || empty($visit_clinical_workflow_enabled)) {
	return;
}
$disposition = isset($visit_disposition) && is_object($visit_disposition) ? $visit_disposition : null;
$disposition_locked = !empty($visit_disposition_locked);
$is_responsible = !empty($is_responsible_doctor);
$staff_options = isset($staff_options) && is_array($staff_options) ? $staff_options : array();
$selected_staff_id = isset($visit_performer_staff_id) ? (int) $visit_performer_staff_id : 0;
$assigned_performer_name = isset($visit_performer_name) ? trim((string) $visit_performer_name) : '';
$consultation_mode = isset($consultation_mode) ? strtolower(trim((string) $consultation_mode)) : '';
$disposition_skipped = !$disposition && $consultation_mode === 'non_visit';
$decision = $disposition ? (string) $disposition->decision : '';
$decision_label = $decision === 'visit' ? 'Kunjungan Nakes' : ($decision === 'non_visit' ? 'Tanpa kunjungan' : '');
$urgency_labels = array('routine' => 'Rutin', 'priority' => 'Prioritas', 'urgent' => 'Mendesak');
$urgency = $disposition ? (string) ($disposition->urgency ?? '') : '';
$required_profession = $disposition ? trim((string) ($disposition->required_profession ?? '')) : '';
$instructions = $disposition ? trim((string) ($disposition->instructions ?? '')) : '';
$rationale = $disposition ? trim((string) ($disposition->rationale ?? '')) : '';
$skip_message_id = 'clinicalDispositionSkipMessage' . (int) $request_id;
$optional_form_id = 'clinicalDispositionOptional' . (int) $request_id;
$create_form_args = array(
	'request_id' => $request_id,
	'endpoint' => 'clinical/disposition',
	'expected_version' => 0,
	'decision' => '',
	'urgency' => '',
	'required_profession' => '',
	'instructions' => '',
	'rationale' => '',
	'staff_options' => $staff_options,
	'selected_staff_id' => 0,
	'submit_label' => 'Simpan disposisi',
);
?>
<section class="consult-card" id="disposisi" data-clinical-disposition>
	<span id="penugasan" class="visually-hidden"></span>
	<h2 class="section-heading"><i class="bi bi-signpost-split"></i> Disposisi dokter <span class="badge text-bg-light align-middle">Opsional</span></h2>
	<p class="small text-muted">Disposisi hanya diperlukan bila pasien perlu dikunjungi Nakes. Dokter boleh langsung menyelesaikan konsultasi tanpa disposisi.</p>

	<?php if ($disposition) : ?>
		<div class="d-flex flex-wrap gap-2 align-items-center mb-2">
			<span class="badge <?= $decision === 'visit' ? 'text-bg-success' : 'text-bg-secondary'; ?>"><?= html_escape($decision_label); ?></span>
			<?php if ($decision === 'visit' && isset($urgency_labels[$urgency])) : ?>
				<span class="small text-muted">Urgensi: <?= html_escape($urgency_labels[$urgency]); ?></span>
			<?php endif; ?>
			<?php if ($assigned_performer_name !== '') : ?>
				<span class="small text-muted">Nakes: <?= html_escape($assigned_performer_name); ?><?= $required_profession !== '' ? ' (' . html_escape($required_profession) . ')' : ''; ?></span>
			<?php elseif ($decision === 'visit') : ?>
				<span class="small text-muted">Nakes: belum ditunjuk<?= $required_profession !== '' ? ' — ' . html_escape($required_profession) : ''; ?></span>
			<?php endif; ?>
		</div>
		<?php if ($instructions !== '') : ?>
			<div class="mb-2"><span class="fw-semibold d-block">Instruksi dokter</span><?= nl2br(html_escape($instructions)); ?></div>
		<?php endif; ?>
		<?php if ($rationale !== '') : ?>
			<div class="mb-2 small text-muted"><span class="fw-semibold d-block text-body">Pertimbangan</span><?= nl2br(html_escape($rationale)); ?></div>
		<?php endif; ?>
		<?php if ($disposition_locked) : ?>
			<p class="small text-muted mb-0">Disposisi terkunci karena kunjungan sudah berjalan.</p>
		<?php elseif ($is_responsible) : ?>
			<button class="btn btn-outline-success btn-sm rounded-pill mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#clinicalDispositionRevise" aria-expanded="false" aria-controls="clinicalDispositionRevise">Ubah disposisi</button>
			<div class="collapse mt-3" id="clinicalDispositionRevise">
				<?php $this->load->view('partials/clinical_disposition_form_v', array_merge($create_form_args, array(
					'endpoint' => 'clinical/disposition/revise',
					'expected_version' => (int) $disposition->version_no,
					'decision' => $decision,
					'urgency' => $urgency,
					'required_profession' => $required_profession,
					'instructions' => $instructions,
					'rationale' => $rationale,
					'selected_staff_id' => $selected_staff_id,
					'submit_label' => 'Simpan perubahan',
				))); ?>
			</div>
		<?php endif; ?>
	<?php elseif (!$is_responsible) : ?>
		<p class="small text-muted mb-0"><?= $disposition_skipped ? 'Dokter melanjutkan konsultasi tanpa disposisi.' : 'Dokter penanggung jawab dapat menetapkan disposisi atau melanjutkan tanpa kunjungan.'; ?></p>
	<?php elseif ($disposition_locked) : ?>
		<p class="small text-muted mb-0">Kunjungan sudah berjalan sehingga disposisi tidak dapat ditetapkan lagi.</p>
	<?php elseif ($disposition_skipped) : ?>
		<div class="alert alert-info py-2 px-3" role="status">Disposisi dilewati. Konsultasi dilanjutkan tanpa kunjungan; selesaikan pemeriksaan di bawah.</div>
		<button class="btn btn-outline-success btn-sm rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#<?= html_escape($optional_form_id); ?>" aria-expanded="false" aria-controls="<?= html_escape($optional_form_id); ?>">Tetapkan disposisi (opsional)</button>
		<div class="collapse mt-3" id="<?= html_escape($optional_form_id); ?>">
			<?php $this->load->view('partials/clinical_disposition_form_v', $create_form_args); ?>
		</div>
	<?php else : ?>
		<?php $this->load->view('partials/clinical_disposition_form_v', $create_form_args); ?>
		<div class="d-flex flex-wrap align-items-center gap-2 mt-3">
			<button class="btn btn-outline-secondary" type="button"
				data-skip-disposition
				data-skip-endpoint="<?= html_escape(base_url('home_nakes/choose_service_mode')); ?>"
				data-request-id="<?= html_escape((int) $request_id); ?>"
				data-skip-message="#<?= html_escape($skip_message_id); ?>">Lanjutkan tanpa disposisi</button>
			<span class="small text-muted">Konsultasi tetap dapat diselesaikan tanpa kunjungan Nakes.</span>
		</div>
		<div class="small mt-2" id="<?= html_escape($skip_message_id); ?>" data-skip-disposition-message role="status" aria-live="polite"></div>
	<?php endif; ?>
</section>
