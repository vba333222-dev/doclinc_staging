<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<aside class="nakes-context-rail" id="konteks-pasien" aria-label="Konteks pasien dan konsultasi">
	<details class="cw-context-details" open>
		<summary class="cw-context-summary">Konteks pasien</summary>
	<section class="consult-card">
		<h2 class="section-heading"><i class="bi bi-person-vcard" aria-hidden="true"></i> Ringkasan Pasien</h2>
		<div class="summary-grid">
			<div class="summary-item">
				<span class="summary-label">Nama Pasien</span>
				<span class="summary-value"><?= html_escape(strtoupper($nama_pasien)); ?></span>
			</div>
			<div class="summary-item">
				<span class="summary-label">Umur</span>
				<span class="summary-value"><?= html_escape($umur); ?> Tahun</span>
			</div>
			<div class="summary-item">
				<span class="summary-label">No. Antrian</span>
				<span class="summary-value"><?= html_escape($queue_label); ?></span>
			</div>
			<div class="summary-item">
				<span class="summary-label">Jenis Layanan</span>
				<span class="summary-value"><?= html_escape($kriteria !== '' ? $kriteria : 'Belum ditentukan'); ?></span>
			</div>
		</div>
	</section>

	<section class="consult-card">
		<h2 class="section-heading"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i> Keluhan Pasien</h2>
		<?= formatComplaintText($keluhan_pasien); ?>
	</section>

	<?php if ($care_team_workflow_enabled && $consultation_next_action !== '') : ?>
		<section class="consult-card" data-clinical-next-action="<?= !empty($is_visit_performer) ? 'visit_performer' : 'responsible_doctor'; ?>">
			<span class="summary-label">Peran Anda · <?= html_escape($consultation_role_label); ?></span>
			<strong class="d-block mt-1" data-clinical-next-action-label><?= html_escape($consultation_next_action); ?></strong>
		</section>
	<?php endif; ?>

	<?php if (!empty($can_open_patient_chat)) : ?>
		<section class="consult-card chat-card">
			<h2 class="section-heading text-white"><img class="dl-semantic-icon" src="<?= html_escape(doclinc_ui_asset('chat')); ?>" alt="" aria-hidden="true"> Chat Konsultasi</h2>
			<p class="chat-copy">Hubungi warga untuk melanjutkan konsultasi.</p>
			<a href="<?= html_escape(base_url('chat?request_id=' . (int) $request_id)); ?>" class="chat-button">
				<img class="dl-semantic-icon dl-semantic-icon--sm" src="<?= html_escape(doclinc_ui_asset('chat')); ?>" alt="" aria-hidden="true"> Buka chat
			</a>
		</section>
	<?php endif; ?>
	<?php if ($auto_visit_location) : ?>
		<div class="small text-muted mb-3" id="consultationVisitLocationStatus" role="status" aria-live="polite"></div>
	<?php endif; ?>
	</details>
</aside>
