<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$workspace_clinical = isset($clinical_ui) && is_array($clinical_ui) && !empty($clinical_ui['state'])
	? $clinical_ui
	: null;
$workspace_label = $workspace_clinical
	? (isset($workspace_clinical['label']) && trim((string) $workspace_clinical['label']) !== '' ? (string) $workspace_clinical['label'] : (string) $workspace_clinical['state'])
	: '';
?>
<a class="cw-skip-link" href="#main">Lewati ke konten utama</a>
<header class="consult-header cw-workspace-header" id="status-konsultasi">
	<a href="<?= html_escape(isset($return_url) ? $return_url : base_url('home_nakes#riwayat_konsul')); ?>" class="consult-back" aria-label="Kembali ke daftar konsultasi">
		<i class="fas fa-arrow-left" aria-hidden="true"></i>
	</a>
	<div class="consult-title">
		<h1 id="consultationTitle"><?= $monitor_hide_form ? 'Detail Konsultasi' : 'Pemeriksaan Pasien'; ?></h1>
		<p class="consult-subtitle"><?= html_escape($queue_label); ?><?= $workspace_label !== '' ? ' · ' . html_escape($workspace_label) : ''; ?></p>
	</div>
	<?php if ($workspace_clinical) : ?>
		<?php
		$workspace_tone = isset($workspace_clinical['tone']) ? (string) $workspace_clinical['tone'] : 'neutral';
		if (!in_array($workspace_tone, array('success', 'info', 'warning', 'danger', 'neutral'), true)) {
			$workspace_tone = 'neutral';
		}
		?>
		<span class="cw-state is-<?= html_escape($workspace_tone); ?>" data-clinical-state="<?= html_escape((string) $workspace_clinical['state']); ?>"><?= html_escape($workspace_label); ?></span>
	<?php else : ?>
		<span class="status-badge"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Diterima</span>
	<?php endif; ?>
</header>
<?php if ($workspace_clinical && !empty($workspace_clinical['steps'])) : ?>
	<section class="cw-card cw-workspace-thread" id="alur-klinis" aria-labelledby="cwThreadTitle">
		<div class="cw-card-head">
			<div>
				<h2 class="cw-section-title" id="cwThreadTitle">Alur klinis</h2>
				<p class="cw-meta"><?= html_escape(isset($workspace_clinical['next_action']) && $workspace_clinical['next_action'] !== '' ? (string) $workspace_clinical['next_action'] : 'Pantau langkah berikutnya.'); ?></p>
			</div>
		</div>
		<div class="cw-card-body">
			<?php $this->load->view('partials/clinical_thread_v', array('clinical_ui' => $workspace_clinical)); ?>
		</div>
	</section>
<?php endif; ?>
