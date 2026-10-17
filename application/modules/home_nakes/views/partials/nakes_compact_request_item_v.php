<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$latest_text = trim(str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $latest_keluhan)));
/* Tidak setiap baris punya label. Baris pertama sering berupa judul bagian
   ("Anamnesa"), dan baris tanpa titik dua setelah sebuah label adalah lanjutan
   nilai label itu - "Gejala tambahan: pusing" lalu "Tidak Nafsu Makan". Dulu
   hanya baris berlabel yang dibaca dan sisanya digabung menjadi satu kalimat,
   sehingga teks utuh terbaca sebagai judul keluhan. */
$latest_fields = array();
$latest_lead = '';
$latest_last_key = null;
foreach (preg_split('/\n+/', $latest_text) as $latest_line) {
	$latest_line = trim(str_replace(array('**', '__'), '', $latest_line));
	if ($latest_line === '') {
		continue;
	}
	if (preg_match('/^([^:]{1,40}):\s*(.+)$/s', $latest_line, $matches)) {
		$latest_last_key = strtolower(trim($matches[1]));
		$latest_fields[$latest_last_key] = trim($matches[2]);
	} elseif ($latest_last_key !== null) {
		$latest_fields[$latest_last_key] .= ', ' . $latest_line;
	} elseif ($latest_lead === '') {
		$latest_lead = $latest_line;
	}
}
$latest_primary = isset($latest_fields['keluhan utama']) ? $latest_fields['keluhan utama'] : '';
$latest_duration = isset($latest_fields['lama keluhan']) ? $latest_fields['lama keluhan'] : '';
$latest_symptoms = isset($latest_fields['gejala tambahan']) ? $latest_fields['gejala tambahan'] : '';
$latest_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan'), true);
};
/* Baris berlabel sudah tampil sebagai fakta di bawah judul, jadi mengulang
   seluruh teks sebagai satu kalimat hanya menggandakan isi yang sama. Teks
   bebas tanpa satu pun label tetap dipakai apa adanya. */
if ($latest_primary === '' && empty($latest_fields)) {
	$latest_primary = doclinc_nakes_short_text(str_replace(array('**', '__'), '', $latest_text), 92);
}
if ($latest_primary === '' && $latest_lead === '') {
	$latest_lead = 'Keluhan belum diisi.';
}
$latest_patient_photo = '';
foreach (array('foto', 'photo', 'profile_photo', 'profile_image', 'patient_photo', 'avatar') as $latest_photo_key) {
	if (isset($latest_request->{$latest_photo_key}) && trim((string) $latest_request->{$latest_photo_key}) !== '') {
		$latest_patient_photo = trim((string) $latest_request->{$latest_photo_key});
		break;
	}
}
?>
<div class="dl-latest-row <?= empty($is_last_latest_request) ? 'mb-2 pb-2 border-bottom' : ''; ?>">
	<?php
	$this->load->view('partials/nakes_avatar_v', array(
		'avatar_name' => $latest_request->nama,
		'avatar_photo' => $latest_patient_photo,
		'avatar_user_id' => (int) $latest_request->user_id,
		'avatar_alt' => 'Foto pasien',
		'avatar_class' => 'nk-avatar--sm nk-avatar--patient',
	));
	?>
	<div class="flex-grow-1 min-w-0">
		<p class="dl-latest-name"><?= html_escape(strtoupper((string) $latest_request->nama)); ?></p>
		<div class="dl-latest-meta"><?= html_escape($latest_queue_label); ?></div>
		<div class="dl-latest-complaint nk-complaint">
			<span class="nk-complaint__title">Keluhan utama</span>
			<?php if ($latest_lead !== '') : ?>
				<span class="nk-complaint__lead"><?= html_escape($latest_lead); ?></span>
			<?php endif; ?>
			<?php if ($latest_primary !== '') : ?>
				<strong class="nk-complaint__main"><?= html_escape($latest_primary); ?></strong>
			<?php endif; ?>
			<?php if (!$latest_weak_value($latest_duration) || !$latest_weak_value($latest_symptoms)) : ?>
				<div class="nk-fact-list">
					<?php if (!$latest_weak_value($latest_duration)) : ?>
						<div class="nk-fact-row"><span class="nk-fact-label">Lama</span><strong class="nk-fact-value"><?= html_escape($latest_duration); ?></strong></div>
					<?php endif; ?>
					<?php if (!$latest_weak_value($latest_symptoms)) : ?>
						<div class="nk-fact-row"><span class="nk-fact-label">Gejala</span><strong class="nk-fact-value"><?= html_escape($latest_symptoms); ?></strong></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<a href="#req_konsul" class="btn btn-outline-success btn-sm rounded-pill fw-bold" onclick="showContent('req_konsul')">Lihat</a>
</div>
