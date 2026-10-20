<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$row_queue = isset($queue_label) && $queue_label !== '' ? $queue_label : doclinc_request_queue_number_label($x);
$row_wait = isset($wait_label) && $wait_label !== '' ? $wait_label : 'Baru masuk';
$row_received = isset($received_label) && $received_label !== '' ? $received_label : 'Baru masuk';
$row_state = strtolower(trim((string) (isset($x->request_status) ? $x->request_status : '')));
$row_state_label = $row_state === 'accepted' ? 'Sudah diterima' : 'Menunggu diterima';
$row_patient_id = 'cqPatient' . (int) $x->request_id;
$request_card_photo = '';
foreach (array('patient_photo', 'foto', 'photo', 'profile_photo', 'profile_image', 'avatar') as $request_card_photo_key) {
	if (isset($x->{$request_card_photo_key}) && trim((string) $x->{$request_card_photo_key}) !== '') {
		$request_card_photo = trim((string) $x->{$request_card_photo_key});
		break;
	}
}
?>
<article class="dl-nakes-request-item cq-request dl-nakes-request-card request-card" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-visit-id="<?= html_escape((int) $x->request_id); ?>" data-visit-request-id="<?= html_escape((int) $x->request_id); ?>" data-patient-lat="<?= html_escape($x->lattitude); ?>" data-patient-lng="<?= html_escape($x->longitude); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>" aria-labelledby="<?= html_escape($row_patient_id); ?>">
	<div class="cq-request-rail">
		<span class="cq-request-queue"><?= html_escape($row_queue); ?></span>
		<span class="cq-request-received">Masuk <?= html_escape($row_received); ?></span>
		<span class="cq-request-wait"><?= $row_wait === 'Baru masuk' ? 'Baru masuk' : 'Menunggu ' . html_escape($row_wait); ?></span>
	</div>

	<div class="cq-request-person">
		<?php
		$this->load->view('partials/nakes_avatar_v', array(
			'avatar_name' => $x->nama,
			'avatar_photo' => $request_card_photo,
			'avatar_user_id' => (int) $x->user_id,
			'avatar_alt' => 'Foto pasien',
			'avatar_class' => 'nk-avatar--md nk-avatar--patient',
		));
		?>
		<div class="cq-request-identity">
			<h3 id="<?= html_escape($row_patient_id); ?>"><?= html_escape((string) $x->nama); ?></h3>
			<p>
				<?php if ($show_mode_label) : ?><span><?= html_escape($mode_label); ?></span><?php endif; ?>
				<?php if ($area_label !== '') : ?><span><?= html_escape($area_label); ?></span><?php endif; ?>
			</p>
		</div>
	</div>

	<div class="cq-request-clinical">
		<span class="cq-request-label">Keluhan utama</span>
		<?php if (!empty($complaint_lead)) : ?>
			<p class="cq-request-lead"><?= html_escape($complaint_lead); ?></p>
		<?php endif; ?>
		<p class="cq-request-complaint"><?= html_escape($complaint_primary !== '' ? $complaint_primary : 'Keluhan belum diisi.'); ?></p>
		<?php if ($show_complaint_duration || $show_complaint_symptoms || $show_complaint_description) : ?>
			<dl class="cq-request-facts">
				<?php if ($show_complaint_duration) : ?><div><dt>Lama</dt><dd><?= html_escape($complaint_duration); ?></dd></div><?php endif; ?>
				<?php if ($show_complaint_symptoms) : ?><div><dt>Gejala</dt><dd><?= html_escape($complaint_symptoms); ?></dd></div><?php endif; ?>
				<?php if ($show_complaint_description) : ?><div><dt>Catatan</dt><dd><?= html_escape($complaint_description); ?></dd></div><?php endif; ?>
			</dl>
		<?php endif; ?>
		<?php if ($show_address) : ?>
			<p class="cq-request-location"><i class="bi bi-geo-alt" aria-hidden="true"></i><?= doclinc_history_safe_text($x->location); ?></p>
		<?php endif; ?>
		<?php if (!empty($x->photos) || !empty($x->video)) : ?>
			<div class="cq-request-media">
				<?php if (!empty($x->photos)) : ?><button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#fotoModal_<?= html_escape((int) $x->user_id); ?>">Lihat foto</button><?php endif; ?>
				<?php if (!empty($x->video)) : ?><button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#videoModal_<?= html_escape((int) $x->user_id); ?>">Lihat video</button><?php endif; ?>
			</div>
		<?php endif; ?>
		<span class="distance dl-js-route-probe" aria-hidden="true"><?= html_escape($distance_label); ?></span>
		<span class="duration dl-js-route-probe" aria-hidden="true"></span>
	</div>

	<div class="cq-request-actions">
		<span class="cq-request-state" data-request-state="<?= html_escape($row_state); ?>"><?= html_escape($row_state_label); ?></span>
		<input type="hidden" id="id_request<?= (int) $i; ?>" value="<?= html_escape((int) $x->request_id); ?>">
		<button type="button" class="btn btn-success start-chat cq-primary" id="terimaKonsul<?= (int) $i; ?>" data-reqid="<?= html_escape((int) $x->request_id); ?>" data-userid="<?= html_escape((int) $x->user_id); ?>" data-dokterid="<?= html_escape((int) $x->dokter_id); ?>" data-namapasien="<?= html_escape($x->nama); ?>" data-riwayat="<?= html_escape($riwayat); ?>" data-keluhan="<?= html_escape($keluhan); ?>">Terima permintaan</button>
		<button type="button" class="btn btn-outline-success lihat-map cq-secondary" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMapTujuan" aria-controls="offcanvasMapTujuan" data-request-id="<?= html_escape((int) $x->request_id); ?>" data-lat="<?= html_escape($x->lattitude); ?>" data-lng="<?= html_escape($x->longitude); ?>">Lihat lokasi</button>
		<button type="button" class="btn btn-outline-danger cancel-nakes-request cq-danger" data-request-id="<?= html_escape((int) $x->request_id); ?>">Batalkan permintaan</button>
	</div>
</article>
