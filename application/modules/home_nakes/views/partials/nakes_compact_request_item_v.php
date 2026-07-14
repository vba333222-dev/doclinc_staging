<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$latest_text = trim(str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $latest_keluhan)));
$latest_lines = preg_split('/\n+/', $latest_text);
$latest_fields = array();
foreach ($latest_lines as $latest_line) {
	$latest_line = trim(str_replace(array('**', '__'), '', $latest_line));
	if (preg_match('/^([^:]+):\s*(.+)$/', $latest_line, $matches)) {
		$latest_fields[strtolower(trim($matches[1]))] = trim($matches[2]);
	}
}
$latest_primary = isset($latest_fields['keluhan utama']) ? $latest_fields['keluhan utama'] : '';
$latest_duration = isset($latest_fields['lama keluhan']) ? $latest_fields['lama keluhan'] : '';
$latest_weak_value = static function ($value) {
	$value = trim(strip_tags((string) $value));
	return $value === '' || in_array(strtolower($value), array('n/a', 'na', '-', 'belum ditentukan'), true);
};
if ($latest_primary === '') {
	$latest_primary = doclinc_nakes_short_text(str_replace(array('**', '__'), '', $latest_text), 92);
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
		'avatar_alt' => 'Foto pasien',
		'avatar_class' => 'nk-avatar--sm nk-avatar--patient',
	));
	?>
	<div class="flex-grow-1 min-w-0">
		<p class="dl-latest-name"><?= html_escape(strtoupper((string) $latest_request->nama)); ?></p>
		<div class="dl-latest-meta">No. Antrian: <?= html_escape($latest_queue_code); ?></div>
		<div class="dl-latest-complaint nk-complaint">
			<span class="nk-complaint__title">Keluhan utama</span>
			<strong class="nk-complaint__main"><?= html_escape($latest_primary); ?></strong>
			<?php if (!$latest_weak_value($latest_duration)) : ?>
				<div class="nk-fact-list">
					<div class="nk-fact-row"><span class="nk-fact-label">Lama</span><strong class="nk-fact-value"><?= html_escape($latest_duration); ?></strong></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<a href="#req_konsul" class="btn btn-outline-success btn-sm rounded-pill fw-bold" onclick="showContent('req_konsul')">Lihat permintaan</a>
</div>
