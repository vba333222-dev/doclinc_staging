<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$cc_pending = isset($nakes_pending_count) ? (int) $nakes_pending_count : 0;
$cc_active = isset($nakes_active_count) ? (int) $nakes_active_count : 0;
$cc_completed = isset($nakes_completed_count) ? (int) $nakes_completed_count : 0;
if (!empty($visit_clinical_workflow_enabled) && isset($clinical_action_counts['completed_today'])) {
	$cc_completed = (int) $clinical_action_counts['completed_today'];
}
$cc_needs = isset($clinical_action_counts['needs_action']) ? (int) $clinical_action_counts['needs_action'] : 0;
$cc_waiting_review = isset($clinical_action_counts['waiting_review']) ? (int) $clinical_action_counts['waiting_review'] : 0;
$cc_ready_close = isset($clinical_action_counts['ready_to_close']) ? (int) $clinical_action_counts['ready_to_close'] : 0;
$cc_staff_total = isset($puskesmas_staff_list) && is_array($puskesmas_staff_list) ? count($puskesmas_staff_list) : 0;
$cc_on_duty = isset($nakes_team_duty_counts['on_duty']) ? (int) $nakes_team_duty_counts['on_duty'] : 0;
$cc_exceptions = isset($puskesmas_operation_exceptions) && is_array($puskesmas_operation_exceptions) ? $puskesmas_operation_exceptions : array();

$cc_attention = array();
if (!empty($cc_exceptions['pending_requests'])) {
	$cc_attention[] = array('label' => 'Permintaan menunggu diterima', 'count' => (int) $cc_exceptions['pending_requests'], 'icon' => 'bi-inbox', 'href' => '#req_konsul');
}
if (!empty($cc_exceptions['accepted_without_pic'])) {
	$cc_attention[] = array('label' => 'Kunjungan tanpa penanggung jawab', 'count' => (int) $cc_exceptions['accepted_without_pic'], 'icon' => 'bi-person-exclamation', 'href' => '#req_konsul');
}
if (!empty($cc_exceptions['clinical_stalled'])) {
	$cc_attention[] = array('label' => 'Konsultasi berhenti di tahap klinis', 'count' => (int) $cc_exceptions['clinical_stalled'], 'icon' => 'bi-hourglass-split', 'href' => '#riwayat_konsul');
}
if (!empty($cc_exceptions['staff_data_attention'])) {
	$cc_attention[] = array('label' => 'Data staf belum lengkap', 'count' => (int) $cc_exceptions['staff_data_attention'], 'icon' => 'bi-clipboard2-check', 'href' => '#profile');
}
$cc_attention_count = count($cc_attention);

$cc_primary_href = !empty($puskesmas_operations_enabled) ? site_url('puskesmas/operations') : '#req_konsul';
$cc_primary_label = !empty($puskesmas_operations_enabled) ? 'Buka papan operasional' : 'Buka antrean permintaan';

/* Jam dashboard memakai WIB, bukan jam server. Server dapat berjalan di UTC;
   angka "Diperbarui" yang meleset delapan jam membuat staf ragu apakah data
   ini masih hidup. */
try {
	$cc_now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
} catch (Exception $cc_clock_error) {
	$cc_now = new DateTime('now');
}
$cc_day_names = array(1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu');
$cc_month_names = array(1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember');
$cc_date_label = $cc_day_names[(int) $cc_now->format('N')] . ', ' . $cc_now->format('j') . ' ' . $cc_month_names[(int) $cc_now->format('n')] . ' ' . $cc_now->format('Y');
$cc_updated_at = $cc_now->format('H.i');
$cc_iso_date = $cc_now->format('Y-m-d');

$cc_pending_note = $cc_pending > 0 ? 'belum diterima' : 'tidak ada';
$cc_active_note = $cc_active > 0 ? 'sedang berjalan' : 'tidak ada';
$cc_needs_note = $cc_needs > 0 ? 'perlu ditinjau' : 'tidak ada';
$cc_completed_note = $cc_completed > 0 ? 'hari ini' : 'belum ada';
$cc_review_note = $cc_waiting_review > 0 ? $cc_waiting_review . ' menunggu review' : 'Tidak ada review tertunda';
$cc_close_note = $cc_ready_close > 0 ? $cc_ready_close . ' siap ditutup' : 'Belum ada yang siap ditutup';
?>
<section class="cc-deck" aria-labelledby="ccDeckTitle">
	<header class="cc-head">
		<div class="cc-head-copy">
			<h2 class="cc-title" id="ccDeckTitle">Jaga hari ini</h2>
			<p class="cc-subtitle">
				<time datetime="<?= html_escape($cc_iso_date); ?>"><?= html_escape($cc_date_label); ?></time>
				<span class="cc-dot" aria-hidden="true">·</span>
				<span>Diperbarui <?= html_escape($cc_updated_at); ?> WIB</span>
				<span class="cc-dot" aria-hidden="true">·</span>
				<span><?= (int) $cc_on_duty; ?> dari <?= (int) $cc_staff_total; ?> staf bertugas</span>
			</p>
		</div>
		<a class="cc-action" href="<?= html_escape($cc_primary_href); ?>"<?= $cc_primary_href === '#req_konsul' ? ' onclick="showContent(\'req_konsul\'); return false;"' : ''; ?>><span><?= html_escape($cc_primary_label); ?></span> <span class="cc-action-icon"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></span></a>
	</header>

	<div class="cc-tally" role="group" aria-label="Ringkasan permintaan hari ini">
		<a class="cc-tally-cell<?= $cc_pending > 0 ? ' is-waiting' : ''; ?>" href="#req_konsul" onclick="showContent('req_konsul'); return false;">
			<span class="cc-tally-label">Menunggu</span>
			<strong class="cc-tally-count"><?= html_escape(str_pad((string) $cc_pending, 2, '0', STR_PAD_LEFT)); ?></strong>
			<span class="cc-tally-note"><?= html_escape($cc_pending_note); ?></span>
		</a>
		<a class="cc-tally-cell" href="#req_konsul" onclick="showContent('req_konsul'); return false;">
			<span class="cc-tally-label">Ditangani</span>
			<strong class="cc-tally-count"><?= html_escape(str_pad((string) $cc_active, 2, '0', STR_PAD_LEFT)); ?></strong>
			<span class="cc-tally-note"><?= html_escape($cc_active_note); ?></span>
		</a>
		<a class="cc-tally-cell<?= $cc_needs > 0 ? ' is-attention' : ''; ?>" href="#riwayat_konsul" onclick="showContent('riwayat_konsul'); return false;">
			<span class="cc-tally-label">Perlu tindakan</span>
			<strong class="cc-tally-count"><?= html_escape(str_pad((string) $cc_needs, 2, '0', STR_PAD_LEFT)); ?></strong>
			<span class="cc-tally-note"><?= html_escape($cc_needs_note); ?></span>
		</a>
		<a class="cc-tally-cell" href="#riwayat_konsul" onclick="showContent('riwayat_konsul'); return false;">
			<span class="cc-tally-label">Selesai</span>
			<strong class="cc-tally-count"><?= html_escape(str_pad((string) $cc_completed, 2, '0', STR_PAD_LEFT)); ?></strong>
			<span class="cc-tally-note"><?= html_escape($cc_completed_note); ?></span>
		</a>
	</div>

	<aside class="cc-attention" aria-labelledby="ccAttentionTitle">
		<div class="cc-attention-head">
			<h2 class="cc-attention-title" id="ccAttentionTitle">Perlu perhatian</h2>
			<span class="cc-attention-count"><?= (int) $cc_attention_count; ?> jenis</span>
		</div>
		<?php if ($cc_attention_count > 0) : ?>
			<ul class="cc-attention-list">
				<?php foreach ($cc_attention as $cc_item) : ?>
					<li>
						<a href="<?= html_escape($cc_item['href']); ?>"<?= $cc_item['href'][0] === '#' ? ' onclick="showContent(\'' . html_escape(substr($cc_item['href'], 1)) . '\'); return false;"' : ''; ?>>
							<span class="cc-attention-icon"><i class="bi <?= html_escape($cc_item['icon']); ?>" aria-hidden="true"></i></span>
							<span class="cc-attention-label"><?= html_escape($cc_item['label']); ?></span>
							<strong><?= (int) $cc_item['count']; ?></strong>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<div class="cc-clear">
				<i class="bi bi-check2-circle" aria-hidden="true"></i>
				<div>
					<strong>Tidak ada yang tertahan</strong>
					<span>Semua permintaan sudah punya penanggung jawab, dan tidak ada tahap klinis yang macet.</span>
				</div>
			</div>
		<?php endif; ?>
		<p class="cc-attention-foot">
			<span><?= html_escape($cc_review_note); ?></span>
			<span><?= html_escape($cc_close_note); ?></span>
		</p>
	</aside>
</section>
