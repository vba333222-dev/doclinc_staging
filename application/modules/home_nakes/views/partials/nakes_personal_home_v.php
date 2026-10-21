<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$personal_active_rows = isset($nakes_active_rows) && is_array($nakes_active_rows) ? $nakes_active_rows : array();
$personal_completed_rows = array();
if (isset($data_request_completed) && is_object($data_request_completed)) {
	$personal_completed_rows = array_slice($data_request_completed->result(), 0, 3);
}
$personal_primary = !empty($personal_active_rows) ? $personal_active_rows[0] : null;
$personal_other_count = max(0, count($personal_active_rows) - 1);
$personal_active_count = isset($nakes_active_count) ? (int) $nakes_active_count : count($personal_active_rows);
$personal_completed_count = isset($nakes_completed_count) ? (int) $nakes_completed_count : count($personal_completed_rows);

$CI = &get_instance();
$CI->load->library('encryption');

$personal_plain = static function ($raw) use ($CI) {
	try {
		return trim((string) $CI->encryption->decrypt(base64_decode((string) $raw)));
	} catch (Exception $e) {
		return '';
	}
};

$personal_short = static function ($value, $limit = 150) {
	$text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
	if ($text === '') {
		return '';
	}
	if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $limit) {
		return mb_substr($text, 0, max(0, $limit - 1), 'UTF-8') . '…';
	}
	if (!function_exists('mb_strlen') && strlen($text) > $limit) {
		return substr($text, 0, max(0, $limit - 1)) . '...';
	}
	return $text;
};

$personal_summary = static function ($raw) use ($personal_short) {
	$text = trim(str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $raw)));
	$primary = '';
	$duration = '';
	foreach (preg_split('/\n+/', $text) as $line) {
		$line = trim(str_replace(array('**', '__'), '', $line));
		if (preg_match('/^keluhan utama\s*:\s*(.+)$/i', $line, $match)) {
			$primary = trim($match[1]);
		} elseif (preg_match('/^lama keluhan\s*:\s*(.+)$/i', $line, $match)) {
			$duration = trim($match[1]);
		}
	}
	if ($primary === '') {
		$primary = $personal_short($text, 150);
	}
	return array(
		'primary' => $primary !== '' ? $primary : 'Keluhan tersimpan',
		'duration' => $personal_short($duration, 60),
	);
};
$personal_initials = static function ($name) {
	$name = trim((string) $name);
	if ($name === '') {
		return 'NA';
	}
	$parts = preg_split('/\s+/', $name);
	$initials = '';
	foreach ($parts as $part) {
		if ($part === '') {
			continue;
		}
		$initials .= function_exists('mb_substr') ? mb_substr($part, 0, 1, 'UTF-8') : substr($part, 0, 1);
		if ((function_exists('mb_strlen') ? mb_strlen($initials, 'UTF-8') : strlen($initials)) >= 2) {
			break;
		}
	}
	return strtoupper($initials);
};

$personal_photo = static function ($row) {
	foreach (array('foto', 'photo', 'profile_photo', 'profile_image', 'patient_photo', 'avatar') as $key) {
		if (isset($row->{$key}) && trim((string) $row->{$key}) !== '') {
			return trim((string) $row->{$key});
		}
	}
	return '';
};

$personal_date = static function ($value, $with_time = false) {
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}
	$timestamp = strtotime($value);
	if ($timestamp === false) {
		return $value;
	}
	$months = array(1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember');
	$label = date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
	return $with_time ? $label . ' · ' . date('H.i', $timestamp) : $label;
};

$personal_today = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$personal_day_names = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
$personal_month_names = array(1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember');
$personal_today_label = $personal_day_names[(int) $personal_today->format('w')] . ', ' . (int) $personal_today->format('j') . ' ' . $personal_month_names[(int) $personal_today->format('n')] . ' ' . $personal_today->format('Y');

$personal_puskesmas_name = isset($nakes_puskesmas_name) ? trim((string) $nakes_puskesmas_name) : '';
$personal_puskesmas_code = isset($nakes_puskesmas_code) ? trim((string) $nakes_puskesmas_code) : '';
$personal_identity = isset($nakes_identity_staff) && is_array($nakes_identity_staff) ? $nakes_identity_staff : array();
$personal_profesi = isset($personal_identity['staff_profesi']) ? trim((string) $personal_identity['staff_profesi']) : '';
$personal_sip = isset($personal_identity['staff_nomor_sip']) ? trim((string) $personal_identity['staff_nomor_sip']) : '';
$personal_sip_expiry = isset($personal_identity['staff_sip_expired_at']) ? $personal_date($personal_identity['staff_sip_expired_at']) : '';
$personal_readiness_known = isset($role_prerequisite_state) && is_array($role_prerequisite_state) && array_key_exists('complete', $role_prerequisite_state);
$personal_ready = $personal_readiness_known && !empty($role_prerequisite_state['complete']);
$personal_ready_label = !$personal_readiness_known ? 'Belum tersedia' : ($personal_ready ? 'Profil lengkap' : 'Perlu dilengkapi');
$personal_ready_class = !$personal_readiness_known ? 'is-unknown' : ($personal_ready ? 'is-ready' : 'is-incomplete');
$personal_blocked = isset($role_prerequisite_state['enforced']) && !empty($role_prerequisite_state['enforced']) && empty($role_prerequisite_state['allowed']);
$personal_missing_labels = isset($role_prerequisite_state['missing_labels']) && is_array($role_prerequisite_state['missing_labels'])
	? array_slice($role_prerequisite_state['missing_labels'], 0, 3)
	: array();
$personal_missing_extra = isset($role_prerequisite_state['missing_labels']) && is_array($role_prerequisite_state['missing_labels'])
	? max(0, count($role_prerequisite_state['missing_labels']) - count($personal_missing_labels))
	: 0;
$personal_missing_summary = !empty($personal_missing_labels)
	? implode(', ', $personal_missing_labels) . ($personal_missing_extra > 0 ? ', serta ' . $personal_missing_extra . ' data lainnya' : '')
	: '';
?>
<div class="dl-personal-home">
	<header class="dl-personal-intro">
		<div class="dl-personal-intro__copy">
			<p class="dl-personal-eyebrow">Ruang kerja · <?= html_escape($personal_today_label); ?></p>
			<h2 class="dl-personal-title">Konsultasi yang Anda tangani</h2>
			<p class="dl-personal-lede">Hanya konsultasi yang ditugaskan kepada Anda yang tampil di sini.</p>
		</div>
		<div class="dl-personal-stats" aria-label="Ringkasan konsultasi Anda">
			<a class="dl-personal-stat dl-personal-stat--focus" href="#riwayat_konsul" onclick="showContent('riwayat_konsul')" aria-label="Diterima: <?= $personal_active_count; ?> konsultasi aktif">
				<span>Diterima</span>
				<strong><?= $personal_active_count; ?></strong>
				<small>aktif</small>
			</a>
			<a class="dl-personal-stat" href="#riwayat_konsul" onclick="showContent('riwayat_konsul')" aria-label="Selesai: <?= $personal_completed_count; ?> konsultasi">
				<span>Selesai</span>
				<strong><?= $personal_completed_count; ?></strong>
				<small>total</small>
			</a>
		</div>
	</header>
	<div class="dl-personal-grid">
		<div class="dl-personal-main">
			<section class="dl-personal-panel dl-personal-panel--active" aria-labelledby="personalActiveTitle">
				<div class="dl-personal-panel__head">
					<div>
						<h3 class="dl-personal-panel__title" id="personalActiveTitle">Konsultasi aktif</h3>
						<p class="dl-personal-panel__meta">Ditugaskan kepada Anda</p>
					</div>
					<a class="dl-personal-link" href="#riwayat_konsul" onclick="showContent('riwayat_konsul')">Riwayat</a>
				</div>

				<?php if ($personal_primary) :
					$primary_description = $personal_plain(isset($personal_primary->request_description) ? $personal_primary->request_description : '');
					$primary_summary = $personal_summary($primary_description);
					$primary_name = trim((string) (isset($personal_primary->nama) ? $personal_primary->nama : ''));
					$primary_name = $primary_name !== '' ? $primary_name : 'Warga';
					$primary_photo = $personal_photo($personal_primary);
					$primary_user_id = isset($personal_primary->user_id) ? (int) $personal_primary->user_id : 0;
					$primary_mode = doclinc_consultation_mode_label(isset($personal_primary->consultation_mode) ? $personal_primary->consultation_mode : '');
					$primary_queue = doclinc_request_queue_number_label($personal_primary);
					$primary_date = $personal_date(isset($personal_primary->created_at) ? $personal_primary->created_at : '', true);
					$primary_url = base_url('konsultasi_nakes/konsultasi/' . (int) $personal_primary->request_id);
				?>
				<article class="dl-personal-focus">
					<div class="dl-personal-focus__head">
						<?php if ($primary_photo !== '') : ?>
						<img class="dl-personal-avatar dl-personal-avatar--photo" src="<?= html_escape(doclinc_profile_image_src($primary_user_id, $primary_photo)); ?>" width="52" height="52" alt="">
						<?php else : ?>
						<span class="dl-personal-avatar" aria-hidden="true"><?= html_escape($personal_initials($primary_name)); ?></span>
						<?php endif; ?>
						<div class="dl-personal-focus__identity">
							<h4 class="dl-personal-focus__name"><?= html_escape($primary_name); ?></h4>
							<p class="dl-personal-focus__meta">
								<?php if ($primary_queue !== '') : ?><span class="dl-personal-mono"><?= html_escape($primary_queue); ?></span><?php endif; ?>
								<?php if ($primary_date !== '') : ?><span aria-hidden="true">·</span><span class="dl-personal-mono"><?= html_escape($primary_date); ?></span><?php endif; ?>
								<?php if ($primary_mode !== '') : ?><span aria-hidden="true">·</span><span><?= html_escape($primary_mode); ?></span><?php endif; ?>
							</p>
						</div>
						<span class="dl-personal-pill is-active">Aktif</span>
					</div>

					<div class="dl-personal-complaint">
						<span class="dl-personal-label">Keluhan utama</span>
						<p><?= html_escape($primary_summary['primary']); ?></p>
						<?php if ($primary_summary['duration'] !== '') : ?>
						<span class="dl-personal-complaint__meta">Lama keluhan: <?= html_escape($primary_summary['duration']); ?></span>
						<?php endif; ?>
					</div>

					<div class="dl-personal-focus__actions">
						<a class="btn btn-success dl-personal-primary" href="<?= html_escape($primary_url); ?>">Buka konsultasi</a>
						<?php if ($personal_other_count > 0) : ?>
						<a class="btn btn-outline-secondary dl-personal-secondary" href="#riwayat_konsul" onclick="showContent('riwayat_konsul')">Lihat <?= (int) $personal_other_count; ?> lainnya</a>
						<?php endif; ?>
					</div>
				</article>
				<?php else : ?>
				<div class="dl-personal-empty">
					<span class="dl-personal-empty__icon" aria-hidden="true"><i class="fas fa-comment-medical"></i></span>
					<div>
						<h4 class="dl-personal-empty__title">Belum ada konsultasi aktif</h4>
						<p class="dl-personal-empty__copy">Konsultasi baru akan muncul di sini setelah ditugaskan kepada Anda.</p>
						<div class="dl-personal-empty__actions">
							<a class="btn btn-outline-success dl-personal-secondary" href="#riwayat_konsul" onclick="showContent('riwayat_konsul')">Buka riwayat</a>
							<a class="dl-personal-link" href="#profile" onclick="showContent('profile')">Periksa profil</a>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</section>
			<section class="dl-personal-panel dl-personal-panel--recent" aria-labelledby="personalRecentTitle">
				<div class="dl-personal-panel__head">
					<div>
						<h3 class="dl-personal-panel__title" id="personalRecentTitle">Aktivitas terakhir</h3>
						<p class="dl-personal-panel__meta">Konsultasi yang sudah selesai</p>
					</div>
					<a class="dl-personal-link" href="#riwayat_konsul" onclick="showContent('riwayat_konsul')">Semua</a>
				</div>

				<?php if (!empty($personal_completed_rows)) : ?>
				<div class="dl-personal-recent">
					<?php foreach ($personal_completed_rows as $personal_completed) :
						$completed_name = trim((string) (isset($personal_completed->nama) ? $personal_completed->nama : ''));
						$completed_name = $completed_name !== '' ? $completed_name : 'Warga';
						$completed_summary = $personal_summary($personal_plain(isset($personal_completed->request_description) ? $personal_completed->request_description : ''));
						$completed_date = $personal_date(isset($personal_completed->created_at) ? $personal_completed->created_at : '');
					?>
					<article class="dl-personal-recent__row">
						<time class="dl-personal-recent__date dl-personal-mono" datetime="<?= html_escape(isset($personal_completed->created_at) ? (string) $personal_completed->created_at : ''); ?>"><?= html_escape($completed_date !== '' ? $completed_date : 'Tanpa tanggal'); ?></time>
						<div class="dl-personal-recent__body">
							<strong><?= html_escape($completed_name); ?></strong>
							<p><?= html_escape($personal_short($completed_summary['primary'], 120)); ?></p>
						</div>
						<span class="dl-personal-pill is-done">Selesai</span>
					</article>
					<?php endforeach; ?>
				</div>
				<?php else : ?>
				<p class="dl-personal-recent__empty">Belum ada konsultasi yang selesai.</p>
				<?php endif; ?>
			</section>
		</div>
		<aside class="dl-personal-side">
			<section class="dl-personal-panel dl-personal-panel--readiness" aria-labelledby="personalReadinessTitle">
				<div class="dl-personal-panel__head">
					<div>
						<h3 class="dl-personal-panel__title" id="personalReadinessTitle">Kesiapan bertugas</h3>
						<p class="dl-personal-panel__meta">Dari profil dan penugasan</p>
					</div>
					<span class="dl-personal-pill <?= html_escape($personal_ready_class); ?>"><?= html_escape($personal_ready_label); ?></span>
				</div>

				<dl class="dl-personal-facts">
					<div>
						<dt>Puskesmas</dt>
						<dd><?= html_escape($personal_puskesmas_name !== '' ? $personal_puskesmas_name : 'Belum ditugaskan'); ?><?php if ($personal_puskesmas_code !== '') : ?> <span class="dl-personal-mono">· <?= html_escape($personal_puskesmas_code); ?></span><?php endif; ?></dd>
					</div>
					<div>
						<dt>Profesi</dt>
						<dd><?= html_escape($personal_profesi !== '' ? $personal_profesi : 'Belum tercatat'); ?></dd>
					</div>
					<div>
						<dt>Nomor SIP</dt>
						<dd class="dl-personal-mono"><?= html_escape($personal_sip !== '' ? $personal_sip : 'Belum tercatat'); ?></dd>
					</div>
					<div>
						<dt>Berlaku sampai</dt>
						<dd class="dl-personal-mono"><?= html_escape($personal_sip_expiry !== '' ? $personal_sip_expiry : 'Belum tercatat'); ?></dd>
					</div>
				</dl>

				<?php if (!$personal_ready && $personal_missing_summary !== '') : ?>
				<p class="dl-personal-warning" role="status"><?= $personal_blocked ? 'Wajib dilengkapi: ' : 'Belum tercatat: '; ?><?= html_escape($personal_missing_summary); ?>.</p>
				<?php endif; ?>

				<p class="dl-personal-note">Data kepegawaian dan SIP dikelola Admin Dinas Kesehatan.</p>
				<a class="btn btn-outline-success dl-personal-secondary dl-personal-profile-link" href="#profile" onclick="showContent('profile')"><?= $personal_ready ? 'Lihat profil' : 'Lengkapi profil'; ?></a>
			</section>
		</aside>
	</div>
</div>
