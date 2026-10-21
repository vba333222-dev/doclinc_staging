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
	return array(
		'primary' => $primary !== '' ? $primary : ($personal_short($text, 150) !== '' ? $personal_short($text, 150) : 'Keluhan tersimpan'),
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
?>
<div class="dl-personal-home">
	<header class="dl-personal-intro">
		<p class="dl-personal-greeting">
			<span>Selamat bertugas, <strong><?= html_escape(trim((string) (isset($nakes_name) ? $nakes_name : '')) !== '' ? $nakes_name : 'Nakes'); ?></strong>.</span>
			<span class="dl-personal-date"><?= html_escape($personal_today_label); ?></span>
		</p>
		<h2 class="dl-personal-title">Konsultasi yang Anda tangani</h2>
		<div class="dl-personal-stats" aria-label="Ringkasan konsultasi Anda">
			<div class="dl-personal-stat dl-personal-stat--focus">
				<span>Diterima</span>
				<strong><?= $personal_active_count; ?></strong>
				<small>aktif</small>
			</div>
			<div class="dl-personal-stat">
				<span>Selesai</span>
				<strong><?= $personal_completed_count; ?></strong>
				<small>total</small>
			</div>
		</div>
	</header>

	<div class="dl-personal-grid">
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
	<section class="dl-personal-task" aria-label="Konsultasi aktif">
		<div class="dl-personal-patient">
			<?php if ($primary_photo !== '') : ?>
			<img class="dl-personal-avatar dl-personal-avatar--photo" src="<?= html_escape(doclinc_profile_image_src($primary_user_id, $primary_photo)); ?>" width="52" height="52" alt="">
			<?php else : ?>
			<span class="dl-personal-avatar" aria-hidden="true"><?= html_escape($personal_initials($primary_name)); ?></span>
			<?php endif; ?>
			<div class="dl-personal-identity">
				<h3 class="dl-personal-name"><?= html_escape($primary_name); ?></h3>
				<p class="dl-personal-meta">
					<?php if ($primary_queue !== '') : ?><span class="dl-personal-mono"><?= html_escape($primary_queue); ?></span><?php endif; ?>
					<?php if ($primary_date !== '') : ?><span aria-hidden="true">·</span><span class="dl-personal-mono"><?= html_escape($primary_date); ?></span><?php endif; ?>
					<?php if ($primary_mode !== '') : ?><span aria-hidden="true">·</span><span><?= html_escape($primary_mode); ?></span><?php endif; ?>
				</p>
			</div>
		</div>

		<div class="dl-personal-complaint">
			<span class="dl-personal-label">Keluhan utama</span>
			<p><?= html_escape($primary_summary['primary']); ?></p>
			<?php if ($primary_summary['duration'] !== '') : ?>
			<span class="dl-personal-complaint__meta">Lama keluhan: <?= html_escape($primary_summary['duration']); ?></span>
			<?php endif; ?>
		</div>

		<div class="dl-personal-actions">
			<a class="btn btn-success dl-personal-primary" href="<?= html_escape($primary_url); ?>">Buka konsultasi</a>
		</div>
		<?php if ($personal_other_count > 0) : ?>
		<p class="dl-personal-other"><?= (int) $personal_other_count; ?> konsultasi lain ada di menu Konsultasi.</p>
		<?php endif; ?>
	</section>
	<?php else : ?>
	<section class="dl-personal-task dl-personal-task--empty" aria-label="Konsultasi aktif">
		<div>
			<h3 class="dl-personal-empty__title">Belum ada konsultasi aktif</h3>
			<p class="dl-personal-empty__copy">Konsultasi baru akan muncul di sini setelah ditugaskan kepada Anda.</p>
		</div>
	</section>
	<?php endif; ?>

	<section class="dl-personal-log" aria-labelledby="personalLogTitle">
		<div class="dl-personal-log__head">
			<h3 class="dl-personal-log__title" id="personalLogTitle">Selesai terakhir</h3>
		</div>
		<?php if (!empty($personal_completed_rows)) : ?>
		<ol class="dl-personal-log__list">
			<?php foreach ($personal_completed_rows as $personal_completed) :
				$log_name = trim((string) (isset($personal_completed->nama) ? $personal_completed->nama : ''));
				$log_name = $log_name !== '' ? $log_name : 'Warga';
				$log_summary = $personal_summary($personal_plain(isset($personal_completed->request_description) ? $personal_completed->request_description : ''));
				$log_date = $personal_date(isset($personal_completed->created_at) ? $personal_completed->created_at : '');
				$log_result = '';
				foreach (array('diagnoses_display', 'diagnosa', 'diagnosis', 'treatment', 'saran') as $log_result_key) {
					if (isset($personal_completed->{$log_result_key}) && trim((string) $personal_completed->{$log_result_key}) !== '') {
						$log_result = trim((string) $personal_completed->{$log_result_key});
						break;
					}
				}
			?>
			<li class="dl-personal-log__row">
				<time class="dl-personal-log__date dl-personal-mono" datetime="<?= html_escape(isset($personal_completed->created_at) ? (string) $personal_completed->created_at : ''); ?>"><?= html_escape($log_date !== '' ? $log_date : 'Tanpa tanggal'); ?></time>
				<div class="dl-personal-log__body">
					<strong><?= html_escape($log_name); ?></strong>
					<span><?= html_escape($personal_short($log_summary['primary'], 90)); ?></span>
				</div>
				<?php if ($log_result !== '') : ?>
				<span class="dl-personal-log__result"><?= html_escape($personal_short($log_result, 70)); ?></span>
				<?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ol>
		<?php else : ?>
		<p class="dl-personal-log__empty">Belum ada konsultasi selesai.</p>
		<?php endif; ?>
	</section>
	</div>
</div>
