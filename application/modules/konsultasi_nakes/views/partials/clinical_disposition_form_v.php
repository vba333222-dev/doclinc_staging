<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$expected_version = isset($expected_version) ? (int) $expected_version : 0;
$current_decision = isset($decision) ? (string) $decision : '';
$current_urgency = isset($urgency) ? (string) $urgency : '';
$current_profession = isset($required_profession) ? (string) $required_profession : '';
$current_instructions = isset($instructions) ? (string) $instructions : '';
$current_rationale = isset($rationale) ? (string) $rationale : '';
$current_staff_id = isset($selected_staff_id) ? (int) $selected_staff_id : 0;
$staff_options = isset($staff_options) && is_array($staff_options) ? $staff_options : array();
$staff_groups = array();
foreach ($staff_options as $staff_option) {
	$staff_id = isset($staff_option['staff_id']) ? (int) $staff_option['staff_id'] : 0;
	$staff_name = isset($staff_option['nama']) ? trim((string) $staff_option['nama']) : '';
	if ($staff_id < 1 || $staff_name === '') {
		continue;
	}
	$profession = isset($staff_option['profesi']) ? trim((string) $staff_option['profesi']) : '';
	$group = $profession !== '' ? $profession : 'Nakes lainnya';
	$staff_groups[$group][] = array(
		'staff_id' => $staff_id,
		'nama' => $staff_name,
		'profesi' => $profession,
	);
}
ksort($staff_groups, SORT_NATURAL | SORT_FLAG_CASE);
?>
<form class="clinical-workflow-form"
	data-clinical-form
	data-clinical-endpoint="<?= html_escape(base_url($endpoint)); ?>"
	data-clinical-success="Disposisi disimpan.">
	<input type="hidden" name="request_id" value="<?= html_escape((int) $request_id); ?>">
	<?php if ($expected_version > 0) : ?>
		<input type="hidden" name="expected_version" value="<?= html_escape($expected_version); ?>">
	<?php endif; ?>
	<input type="hidden" name="idempotency_key" value="" data-clinical-idempotency>

	<div class="mb-3">
		<label class="form-label fw-semibold" for="clinicalDispositionDecision<?= html_escape((int) $request_id); ?>">Keputusan</label>
		<select class="form-select" id="clinicalDispositionDecision<?= html_escape((int) $request_id); ?>" name="decision" required data-disposition-decision>
			<option value="">Pilih jenis layanan</option>
			<option value="visit" <?= $current_decision === 'visit' ? 'selected' : ''; ?>>Kunjungan Nakes</option>
			<option value="non_visit" <?= $current_decision === 'non_visit' ? 'selected' : ''; ?>>Tanpa kunjungan</option>
		</select>
	</div>

	<div data-disposition-visit-fields class="<?= $current_decision === 'visit' ? '' : 'd-none'; ?>">
		<div class="mb-3">
			<label class="form-label fw-semibold" for="clinicalDispositionUrgency<?= html_escape((int) $request_id); ?>">Urgensi</label>
			<select class="form-select" id="clinicalDispositionUrgency<?= html_escape((int) $request_id); ?>" name="urgency">
				<option value="">Pilih urgensi</option>
				<option value="routine" <?= $current_urgency === 'routine' ? 'selected' : ''; ?>>Rutin</option>
				<option value="priority" <?= $current_urgency === 'priority' ? 'selected' : ''; ?>>Prioritas</option>
				<option value="urgent" <?= $current_urgency === 'urgent' ? 'selected' : ''; ?>>Mendesak</option>
			</select>
		</div>
		<div class="mb-3" data-disposition-staff-field>
			<label class="form-label fw-semibold" for="clinicalDispositionStaff<?= html_escape((int) $request_id); ?>">Nakes yang ditunjuk</label>
			<select class="form-select" id="clinicalDispositionStaff<?= html_escape((int) $request_id); ?>" name="staff_id" data-disposition-staff data-required-when-visit="1">
				<option value="">Pilih Nakes</option>
				<?php foreach ($staff_groups as $profession => $staff_group) : ?>
					<optgroup label="<?= html_escape($profession); ?>">
						<?php foreach ($staff_group as $staff_option) : ?>
							<option value="<?= html_escape((int) $staff_option['staff_id']); ?>" data-profesi="<?= html_escape($staff_option['profesi']); ?>" <?= (int) $staff_option['staff_id'] === $current_staff_id ? 'selected' : ''; ?>>
								<?= html_escape($staff_option['nama']); ?><?= $staff_option['profesi'] !== '' ? ' — ' . html_escape($staff_option['profesi']) : ''; ?>
							</option>
						<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>
			<input type="hidden" name="required_profession" value="<?= html_escape($current_profession); ?>" data-disposition-profession>
			<?php if (empty($staff_groups)) : ?>
				<div class="alert alert-warning py-2 px-3 mt-2 mb-0" role="status">Belum ada Nakes yang dapat ditunjuk di Puskesmas ini. Lanjutkan tanpa kunjungan atau hubungi Puskesmas.</div>
			<?php else : ?>
				<div class="form-text">Pilih langsung Nakes yang akan ditunjuk. Pilihan ini yang menerima tugas kunjungan.</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="mb-3">
		<label class="form-label fw-semibold" for="clinicalDispositionInstructions<?= html_escape((int) $request_id); ?>">Instruksi untuk Nakes</label>
		<textarea class="form-control" id="clinicalDispositionInstructions<?= html_escape((int) $request_id); ?>" name="instructions" rows="3" maxlength="5000"><?= html_escape($current_instructions); ?></textarea>
	</div>

	<div class="mb-3">
		<label class="form-label fw-semibold" for="clinicalDispositionRationale<?= html_escape((int) $request_id); ?>">Pertimbangan klinis</label>
		<textarea class="form-control" id="clinicalDispositionRationale<?= html_escape((int) $request_id); ?>" name="rationale" rows="2" maxlength="5000"><?= html_escape($current_rationale); ?></textarea>
	</div>

	<button type="submit" class="btn btn-success rounded-pill clinical-workflow-submit"><?= html_escape(isset($submit_label) ? $submit_label : 'Simpan disposisi'); ?></button>
	<div class="small text-danger mt-2" data-clinical-message role="status" aria-live="polite"></div>
</form>
