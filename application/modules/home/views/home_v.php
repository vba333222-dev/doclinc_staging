<?php
$id_request = '';
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '#';
$map_provider = $this->config->item('map_provider') ?: 'none';
$mapbox_public_token = $this->config->item('mapbox_public_token') ?: '';
$clinical_suggestions_enabled = (bool) $this->config->item('clinical_suggestions_enabled');
$clinical_suggestions_endpoint = base_url('clinical-suggestions');
$master_gejala_keluhan_options = isset($master_gejala_keluhan_options) && is_array($master_gejala_keluhan_options)
	? $master_gejala_keluhan_options
	: array();
foreach ($data_profile->result() as $x) {
	$usia = $x->usia;
}

$dokter_id = []; // siapkan array kosong
foreach ($dataDoctor->result() as $doc) {
	$dokter_id[] = $doc->userId; // Identitas Nakes modern memakai users.userId.
}

if (!function_exists('doclinc_history_safe_text')) {
	function doclinc_history_safe_text($value, $fallback = '-')
	{
		$text = trim((string) $value);
		return html_escape($text !== '' ? $text : $fallback);
	}
}

if (!function_exists('doclinc_history_safe_lines')) {
	function doclinc_history_safe_lines($value, $fallback = '-')
	{
		$text = trim((string) $value);
		return nl2br(html_escape($text !== '' ? $text : $fallback), false);
	}
}

if (!function_exists('doclinc_warga_visit_timeline')) {
	function doclinc_warga_visit_timeline($timeline)
	{
		if (empty($timeline) || !is_array($timeline)) {
			return '';
		}

		$html = '<div class="doclinc-visit-timeline">';
		foreach ($timeline as $step) {
			$label = isset($step['label']) ? trim((string) $step['label']) : '';
			if ($label === '') {
				continue;
			}
			$time = isset($step['time']) ? trim((string) $step['time']) : '';
			$is_active = !empty($step['active']);
			$class = 'doclinc-visit-step' . ($is_active ? ' doclinc-visit-step--active' : '');
			$html .= '<div class="' . html_escape($class) . '">';
			$html .= '<span class="doclinc-visit-step-dot"></span>';
			$html .= '<div><strong>' . html_escape($label) . '</strong>';
			if ($time !== '' && strtotime($time)) {
				$html .= '<span class="doclinc-visit-meta">' . html_escape(date('d M Y H:i', strtotime($time))) . '</span>';
			}
			$html .= '</div></div>';
		}
		$html .= '</div>';

		return $html === '<div class="doclinc-visit-timeline"></div>' ? '' : $html;
	}
}

if (!function_exists('doclinc_history_format_complaint')) {
	function doclinc_history_format_complaint($value)
	{
		$text = trim((string) $value);
		if ($text === '') {
			return '<p class="history-empty-text mb-0">Detail keluhan belum tersedia.</p>';
		}

		$lines = preg_split('/\R/u', $text);
		$html = '';
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			if (preg_match('/^\*\*(.+?)\*\*$/', $line, $match)) {
				$html .= '<div class="history-complaint-heading">' . html_escape(trim($match[1])) . '</div>';
				continue;
			}

			if (strpos($line, ':') !== false) {
				list($label, $content) = explode(':', $line, 2);
				$html .= '<div class="history-complaint-row">';
				$html .= '<span class="history-complaint-label">' . html_escape(trim($label)) . '</span>';
				$html .= '<span class="history-complaint-value">' . html_escape(trim($content) !== '' ? trim($content) : '-') . '</span>';
				$html .= '</div>';
				continue;
			}

			$html .= '<p class="history-free-text mb-1">' . html_escape($line) . '</p>';
		}

		return $html !== '' ? $html : nl2br(html_escape($text), false);
	}
}

if (!function_exists('doclinc_warga_clean_complaint_text')) {
	function doclinc_warga_clean_complaint_text($value)
	{
		$text = trim((string) $value);
		$text = preg_replace('/\*\*(.*?)\*\*/u', '$1', $text);
		$text = preg_replace('/\s+/u', ' ', $text);
		return trim((string) $text);
	}
}

if (!function_exists('doclinc_warga_complaint_summary')) {
	function doclinc_warga_complaint_summary($value)
	{
		$text = trim((string) $value);
		if ($text === '') {
			return '<p class="dl-complaint-preview mb-0">Detail keluhan belum tersedia.</p>';
		}

		$fields = array();
		$lines = preg_split('/\R/u', $text);
		foreach ($lines as $line) {
			$line = trim(preg_replace('/\*\*(.*?)\*\*/u', '$1', (string) $line));
			if ($line === '' || strtolower($line) === 'anamnesa') {
				continue;
			}
			if (strpos($line, ':') === false) {
				continue;
			}
			list($label, $content) = explode(':', $line, 2);
			$key = strtolower(trim($label));
			$value = doclinc_warga_clean_complaint_text($content);
			if ($value !== '') {
				$fields[$key] = $value;
			}
		}

		$rows = array(
			'gejala/keluhan utama' => 'Gejala/Keluhan',
			'keluhan utama' => 'Keluhan utama',
			'lama keluhan' => 'Lama keluhan',
			'gejala tambahan' => 'Gejala',
			'catatan' => 'Catatan',
			'detail keluhan' => 'Catatan',
			'deskripsi keluhan' => 'Catatan',
		);

		$html = '<div class="dl-complaint-summary">';
		$has_rows = false;
		foreach ($rows as $key => $label) {
			if (!isset($fields[$key]) || $fields[$key] === '') {
				continue;
			}
			$html .= '<div class="dl-complaint-row"><span>' . html_escape($label) . '</span><strong>' . html_escape($fields[$key]) . '</strong></div>';
			$has_rows = true;
		}

		if (!$has_rows) {
			$preview = doclinc_warga_clean_complaint_text($text);
			if (function_exists('mb_strlen') && mb_strlen($preview, 'UTF-8') > 160) {
				$preview = mb_substr($preview, 0, 157, 'UTF-8') . '...';
			} elseif (strlen($preview) > 160) {
				$preview = substr($preview, 0, 157) . '...';
			}
			$html .= '<p class="dl-complaint-preview mb-0">' . html_escape($preview !== '' ? $preview : 'Detail keluhan belum tersedia.') . '</p>';
		}

		$html .= '</div>';
		return $html;
	}
}

if (!function_exists('doclinc_warga_parse_detail_rows')) {
	function doclinc_warga_parse_detail_rows($value)
	{
		$rows = array();
		$current = '';
		$lines = preg_split('/\R/u', (string) $value);
		foreach ($lines as $line) {
			$line = doclinc_warga_clean_complaint_text($line);
			$line = trim(preg_replace('/^-+\s*(.*?)\s*-+$/u', '$1', $line));
			if ($line === '' || strtolower($line) === 'dokumentasi tindakan') {
				continue;
			}
			if (strpos($line, ':') !== false) {
				list($label, $content) = explode(':', $line, 2);
				$current = strtolower(trim($label));
				$content = doclinc_warga_clean_complaint_text($content);
				if ($content !== '') {
					$rows[$current] = isset($rows[$current]) && $rows[$current] !== '' ? $rows[$current] . "\n" . $content : $content;
				} elseif (!isset($rows[$current])) {
					$rows[$current] = '';
				}
				continue;
			}
			if ($current !== '') {
				$rows[$current] = trim((isset($rows[$current]) ? $rows[$current] . "\n" : '') . $line);
			}
		}

		return $rows;
	}
}

if (!function_exists('doclinc_warga_clean_result_text')) {
	function doclinc_warga_clean_result_text($value)
	{
		$text = doclinc_warga_clean_complaint_text($value);
		$text = preg_replace('/-+\s*Dokumentasi\s+Tindakan\s*-+/iu', '', $text);
		return trim((string) $text);
	}
}

if (!function_exists('doclinc_warga_render_result_rows')) {
	function doclinc_warga_render_result_rows($rows, $labels)
	{
		$html = '<div class="dl-result-summary">';
		$has_rows = false;
		foreach ($labels as $key => $label) {
			if (!isset($rows[$key]) || trim((string) $rows[$key]) === '') {
				continue;
			}
			$html .= '<div class="dl-result-row"><span>' . html_escape($label) . '</span><strong>' . nl2br(html_escape(doclinc_warga_clean_result_text($rows[$key])), false) . '</strong></div>';
			$has_rows = true;
		}
		if (!$has_rows) {
			$html .= '<p class="history-empty-text mb-0">Belum ada catatan.</p>';
		}
		$html .= '</div>';

		return $html;
	}
}

$doclinc_active_request = isset($active_consultation_request) ? $active_consultation_request : null;
$doclinc_has_active_request = !empty($doclinc_active_request);
$doclinc_active_request_id = $doclinc_has_active_request && isset($doclinc_active_request->request_id) ? (int) $doclinc_active_request->request_id : 0;
?>

<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DocLink - Beranda</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<?php if ($clinical_suggestions_enabled) : ?>
		<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-clinical-suggestions.css')); ?>">
	<?php endif; ?>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
	<style type="text/css">
		.content {
			display: none;
			padding: 15px;
		}

		.content.active {
			display: block;
		}

		.card-header {
			background-color: #09AD74;
			color: white;
		}

		.history-result-card {
			border: 0;
			border-radius: 20px;
			overflow: hidden;
			background: #fff;
		}

		.history-result-card .card-header {
			background: #fff;
			color: #333;
			border-bottom: 1px solid #e8f3ee;
			padding: 14px 16px;
		}

		.history-request-id {
			color: #379A69;
			font-weight: 700;
			font-size: 13px;
		}

		.history-meta {
			color: #6c757d;
			font-size: 12px;
			line-height: 1.5;
		}

		.history-status-badge {
			background: #dff4ec;
			color: #379A69;
			border: 1px solid rgba(55, 154, 105, 0.24);
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
			padding: 6px 10px;
		}

		.history-section {
			border: 1px solid #e8f3ee;
			border-radius: 16px;
			padding: 13px;
			margin-bottom: 12px;
			background: #fff;
		}

		.history-section-title {
			color: #379A69;
			font-weight: 700;
			font-size: 13px;
			margin-bottom: 8px;
		}

		.history-result-label,
		.history-complaint-label {
			display: block;
			color: #6c757d;
			font-size: 12px;
			font-weight: 700;
			margin-bottom: 2px;
		}

		.history-result-value,
		.history-complaint-value,
		.history-free-text {
			color: #333;
			font-size: 14px;
			line-height: 1.5;
			word-break: break-word;
		}

		.history-complaint-heading {
			color: #379A69;
			font-weight: 700;
			font-size: 14px;
			margin: 10px 0 6px;
		}

		.history-complaint-heading:first-child {
			margin-top: 0;
		}

		.history-complaint-row,
		.history-result-row {
			padding: 8px 0;
			border-bottom: 1px solid #f0f0f0;
		}

		.history-complaint-row:last-child,
		.history-result-row:last-child {
			border-bottom: 0;
			padding-bottom: 0;
		}

		.history-therapy-list {
			display: flex;
			flex-direction: column;
			gap: 8px;
		}

		.history-therapy-item {
			border: 1px solid #e5e5e5;
			border-radius: 14px;
			padding: 10px;
			background: #fafafa;
		}

		.history-empty-text {
			color: #6c757d;
			font-size: 13px;
		}

		.dl-user-coordinate {
			display: inline-block;
			max-width: 100%;
			color: rgba(255, 255, 255, .86);
			font-size: 12px;
			font-weight: 700;
			line-height: 1.35;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.dl-complaint-summary {
			display: grid;
			gap: 0;
		}

		.dl-complaint-row {
			display: grid;
			grid-template-columns: 112px minmax(0, 1fr);
			gap: 10px;
			padding: 7px 0;
			border-bottom: 1px solid #eef4f1;
			align-items: start;
		}

		.dl-complaint-row:first-child {
			padding-top: 0;
		}

		.dl-complaint-row:last-child {
			padding-bottom: 0;
			border-bottom: 0;
		}

		.dl-complaint-row span {
			color: #6c757d;
			font-size: 12px;
			font-weight: 800;
			line-height: 1.4;
		}

		.dl-complaint-row strong,
		.dl-complaint-preview {
			color: #263a32;
			font-size: 13px;
			font-weight: 700;
			line-height: 1.45;
			overflow-wrap: anywhere;
		}

		.dl-complaint-preview {
			display: -webkit-box;
			-webkit-line-clamp: 3;
			-webkit-box-orient: vertical;
			overflow: hidden;
		}

		.dl-result-summary {
			display: grid;
			gap: 0;
		}

		.dl-result-row {
			display: grid;
			grid-template-columns: 132px minmax(0, 1fr);
			gap: 10px;
			padding: 8px 0;
			border-bottom: 1px solid #eef4f1;
			align-items: start;
		}

		.dl-result-row:first-child {
			padding-top: 0;
		}

		.dl-result-row:last-child {
			padding-bottom: 0;
			border-bottom: 0;
		}

		.dl-result-row span {
			color: #6c757d;
			font-size: 12px;
			font-weight: 800;
			line-height: 1.4;
		}

		.dl-result-row strong {
			color: #263a32;
			font-size: 13px;
			font-weight: 700;
			line-height: 1.45;
			overflow-wrap: anywhere;
		}

		.dl-profile-card {
			border: 1px solid #e1eee8;
			border-radius: 20px;
			background: #fff;
			box-shadow: 0 8px 24px rgba(15, 66, 42, .08);
			overflow: hidden;
		}

		.dl-profile-card__head {
			padding: 16px;
			border-bottom: 1px solid #eef4f1;
			background: #f8fbfa;
		}

		.dl-profile-card__head h4 {
			margin: 0;
			color: #183c2f;
			font-size: 18px;
			font-weight: 800;
			line-height: 1.25;
		}

		.dl-profile-card__head span {
			display: block;
			margin-top: 3px;
			color: #6c757d;
			font-size: 12px;
			font-weight: 700;
		}

		.dl-profile-fields {
			display: grid;
			gap: 9px;
			padding: 14px;
		}

		.dl-profile-field {
			display: grid;
			gap: 4px;
			padding: 10px 11px;
			border: 1px solid #e5eee9;
			border-radius: 13px;
			background: #fff;
		}

		.dl-profile-field label {
			margin: 0;
			color: #6c757d;
			font-size: 11px;
			font-weight: 800;
			line-height: 1.25;
		}

		.dl-profile-field input,
		.dl-profile-field textarea {
			width: 100%;
			padding: 0;
			border: 0;
			background: transparent;
			color: #183c2f;
			font-size: 13px;
			font-weight: 750;
			line-height: 1.4;
			box-shadow: none;
			resize: none;
		}

		.dl-profile-field textarea {
			min-height: 54px;
		}

		.dl-profile-actions {
			padding: 0 14px 14px;
		}

		.dl-profile-actions .btn {
			min-height: 42px;
			border-radius: 12px;
			font-size: 13px;
			font-weight: 800;
		}

		.doclinc-visit-map {
			width: 100%;
			height: min(66vh, 520px);
			min-height: 320px;
			border: 1px solid #d8eee5;
			border-radius: 12px;
			overflow: hidden;
			background: #eef5f2;
		}

		.visit-map-toolbar {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			justify-content: flex-end;
			margin-top: 10px;
		}

		.visit-map-toolbar .btn {
			border-radius: 999px;
			font-size: 12px;
			font-weight: 700;
			padding: 7px 12px;
		}

		.visit-route-card {
			border: 1px solid #d8eee5;
			border-radius: 12px;
			background: #fff;
			box-shadow: 0 8px 24px rgba(15, 66, 42, 0.08);
			padding: 12px;
		}

		.visit-route-card-title {
			color: #1f513c;
			font-size: 14px;
			font-weight: 800;
			margin-bottom: 8px;
		}

		.visit-route-card-row {
			display: flex;
			justify-content: space-between;
			gap: 12px;
			color: #5d6b66;
			font-size: 12px;
			line-height: 1.5;
			padding: 2px 0;
		}

		.visit-route-card-row strong {
			color: #1f513c;
			text-align: right;
		}

		.visit-route-status {
			display: inline-flex;
			align-items: center;
			border-radius: 999px;
			background: #e5f7ef;
			color: #087a52;
			font-size: 12px;
			font-weight: 800;
			padding: 5px 10px;
		}

		.visit-route-provider-note {
			color: #6c757d;
			font-size: 11px;
			line-height: 1.35;
		}

		@media (max-width: 575.98px) {
			.doclinc-visit-map {
				height: 58vh;
				min-height: 340px;
			}
		}

		.doclinc-visit-status {
			font-size: 12px;
			color: #6c757d;
		}

		.doclinc-visit-summary {
			border: 1px solid #d8eee5;
			border-radius: 12px;
			background: #f7fcfa;
			padding: 10px 12px;
		}

		.doclinc-visit-summary__label {
			margin: 0;
			color: #1f513c;
			font-size: 13px;
			font-weight: 800;
			line-height: 1.35;
		}

		.doclinc-visit-meta {
			display: block;
			color: #6c757d;
			font-size: 11px;
			line-height: 1.35;
		}

		.doclinc-visit-timeline {
			display: grid;
			gap: 8px;
			margin-top: 10px;
		}

		.doclinc-visit-step {
			display: grid;
			grid-template-columns: 16px minmax(0, 1fr);
			gap: 8px;
			align-items: flex-start;
			color: #6c757d;
			font-size: 12px;
		}

		.doclinc-visit-step strong {
			display: block;
			color: #43524c;
			font-size: 12px;
			line-height: 1.35;
		}

		.doclinc-visit-step-dot {
			width: 10px;
			height: 10px;
			margin-top: 3px;
			border: 2px solid #bfd9ce;
			border-radius: 999px;
			background: #fff;
		}

		.doclinc-visit-step--active strong {
			color: #1f513c;
		}

		.doclinc-visit-step--active .doclinc-visit-step-dot {
			border-color: #09ad74;
			background: #09ad74;
		}

		.doclinc-visit-marker {
			position: relative;
			width: 78px;
			height: 58px;
			pointer-events: auto;
		}

		.doclinc-visit-marker-pin {
			position: absolute;
			left: 50%;
			top: 24px;
			width: 18px;
			height: 18px;
			border: 3px solid #fff;
			border-radius: 50% 50% 50% 0;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.32);
			transform: translate(-50%, -50%) rotate(-45deg);
		}

		.doclinc-visit-marker-label {
			position: absolute;
			left: 50%;
			border-radius: 999px;
			color: #fff;
			font-size: 11px;
			font-weight: 700;
			line-height: 1;
			padding: 5px 8px;
			white-space: nowrap;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.22);
			transform: translateX(-50%);
		}

		.doclinc-visit-marker--patient .doclinc-visit-marker-pin,
		.doclinc-visit-marker--patient .doclinc-visit-marker-label {
			background: #0d6efd;
		}

		.doclinc-visit-marker--patient .doclinc-visit-marker-label {
			top: 39px;
		}

		.doclinc-visit-marker--nakes .doclinc-visit-marker-pin,
		.doclinc-visit-marker--nakes .doclinc-visit-marker-label {
			background: #dc3545;
		}

		.doclinc-visit-marker--nakes .doclinc-visit-marker-label {
			bottom: 39px;
		}

		.header {
			background-color: #09AD74;
			color: white;
			padding: 15px;
			text-align: center;
			font-size: 1.5rem;
			font-weight: bold;
		}

		/* Style untuk preloader */
		#preloader {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background-color: #09AD74;
			display: flex;
			justify-content: center;
			align-items: center;
			z-index: 9999;
			font-family: Arial, sans-serif;
			color: white;
		}

		/* Style untuk konten utama */
		#content-wrapper,
		#nav-bottom-wrapper {
			display: none;
		}

		.article-list {
			display: flex;
			flex-direction: column;
			gap: 20px;
		}

		.article {
			display: flex;
			flex-direction: column;
			background-color: white;
			border-radius: 10px;
			overflow: hidden;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
		}

		.article img {
			width: 100%;
			height: auto;
		}

		.article-content {
			padding: 15px;
		}

		.article-title {
			font-size: 18px;
			font-weight: bold;
			margin: 0 0 10px;
		}

		.article-author {
			font-size: 14px;
			color: gray;
			margin-bottom: 10px;
		}

		.article-link {
			text-decoration: none;
			color: #3498db;
		}

		/* Mobile responsive */
		@media(min-width: 768px) {
			.article {
				flex-direction: row;
				max-width: 600px;
				margin: auto;
			}

			.article img {
				width: 150px;
				height: 150px;
				object-fit: cover;
			}

			.article-content {
				padding: 15px;
				display: flex;
				flex-direction: column;
				justify-content: space-between;
			}

			.article-title {
				font-size: 20px;
			}

			.article-author {
				font-size: 16px;
			}
		}

		.notification {
			position: relative;
			display: inline-block;
		}

		.notification-item {
			padding: 1px;
			border-bottom: 1px solid #f1f1f1;
			cursor: pointer;
		}

		.notification-item:last-child {
			border-bottom: none;
		}

		.notification-item:hover {
			background: #f9f9f9;
		}

		.dl-dashboard-body #offcanvasNotif {
			top: 10px;
			left: 50%;
			right: auto;
			width: calc(100% - 24px);
			max-width: 430px;
			height: min(82vh, 680px);
			max-height: calc(100vh - 20px);
			transform: translate(-50%, -110%);
			border: 1px solid #dcece5;
			border-radius: 18px;
			background: #f8fbfa;
			box-shadow: 0 22px 48px rgba(24, 60, 47, .22);
			overflow: hidden;
		}

		.dl-dashboard-body #offcanvasNotif.show,
		.dl-dashboard-body #offcanvasNotif.showing {
			transform: translate(-50%, 0);
		}

		.dl-dashboard-body #offcanvasNotif.hiding {
			transform: translate(-50%, -110%);
		}

		.dl-notification-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 10px;
			padding: 12px;
			border-bottom: 1px solid #e4eee9;
			background: #fff;
		}

		.dl-notification-title {
			display: flex;
			align-items: center;
			gap: 8px;
			min-width: 0;
			color: #183c2f;
			font-size: 14px;
			font-weight: 800;
		}

		.dl-notification-title i {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 30px;
			height: 30px;
			border-radius: 10px;
			background: #e8f7f0;
			color: #087b55;
		}

		.dl-notification-close {
			width: 34px;
			height: 34px;
			border-radius: 12px;
			background-color: #edf5f1;
			opacity: 1;
		}

		.dl-notification-body {
			height: calc(100% - 59px);
			padding: 10px 12px 14px;
			overflow: hidden;
		}

		.dl-notification-list {
			height: 100%;
			display: grid;
			align-content: start;
			gap: 8px;
			overflow-y: auto;
			padding-right: 2px;
		}

		.dl-notification-item {
			display: grid;
			grid-template-columns: 34px minmax(0, 1fr);
			gap: 9px;
			align-items: start;
			padding: 10px;
			border: 1px solid #e3eee8;
			border-radius: 12px;
			background: #fff;
			cursor: pointer;
		}

		.dl-notification-icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 34px;
			height: 34px;
			border-radius: 11px;
			background: #e8f7f0;
			color: #087b55;
			font-size: 15px;
		}

		.dl-notification-content {
			min-width: 0;
			display: grid;
			gap: 2px;
		}

		.dl-notification-content strong {
			color: #183c2f;
			font-size: 13px;
			line-height: 1.35;
			overflow-wrap: anywhere;
		}

		.dl-notification-content small {
			color: #4c6258;
			font-size: 12px;
			font-weight: 600;
			line-height: 1.4;
			overflow-wrap: anywhere;
		}

		.dl-notification-content .dl-notification-time {
			color: #7d8d86;
			font-size: 11px;
			font-weight: 700;
		}

		.dl-notification-empty {
			display: grid;
			place-items: center;
			gap: 7px;
			min-height: 190px;
			color: #71837b;
			font-size: 13px;
			font-weight: 800;
			text-align: center;
		}

		@media (max-width: 480px) {
			.dl-dashboard-body #offcanvasNotif {
				top: 0;
				width: 100%;
				max-width: none;
				height: 82vh;
				max-height: 92vh;
				border-left: 0;
				border-right: 0;
				border-top: 0;
				border-radius: 0 0 18px 18px;
			}

			.dl-complaint-row,
			.dl-result-row {
				grid-template-columns: 98px minmax(0, 1fr);
				gap: 8px;
			}
		}

		.hero-card.disabled {
			pointer-events: none !important;
			/* Tidak bisa diklik */
			opacity: 0.5;
			/* Buat terlihat redup */
			cursor: not-allowed;
			/* Ubah kursor menjadi tanda larangan */
		}

		.hero-card.disabled a,
		.hero-card.disabled button {
			pointer-events: none !important;
			opacity: 0.5;
		}

		.popup-alert {
			position: fixed;
			top: 20px;
			right: 20px;
			background-color: #d4edda;
			color: #155724;
			border-left: 6px solid #28a745;
			padding: 16px 20px;
			z-index: 9999;
			border-radius: 8px;
			font-family: Arial, sans-serif;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			animation: dlPopupEnter 0.5s ease;
		}

		.close-btn {
			margin-left: 15px;
			color: #155724;
			font-weight: bold;
			float: right;
			font-size: 20px;
			cursor: pointer;
		}

		@keyframes dlPopupEnter {
			from {
				opacity: 0;
				top: 0;
			}

			to {
				opacity: 1;
				top: 20px;
			}
		}

		.glow-star {
			animation: glow 1.5s infinite alternate;
		}

		@keyframes glow {
			from {
				text-shadow: 0 0 5px gold, 0 0 10px gold, 0 0 15px orange;
			}

			to {
				text-shadow: 0 0 10px gold, 0 0 20px orange, 0 0 30px red;
			}
		}

		.rating-stars {
			display: flex;
			flex-direction: row-reverse;
			justify-content: center;
		}

		.rating-stars input[type="radio"] {
			display: none;
		}

		.rating-stars label {
			font-size: 2rem;
			color: #ccc;
			cursor: pointer;
		}

		.rating-stars input[type="radio"]:checked~label,
		.rating-stars label:hover,
		.rating-stars label:hover~label {
			color: #f5c518;
		}

		@media print {

			button,
			.card-header span {
				display: none !important;
			}

			.card {
				border: none !important;
				box-shadow: none !important;
			}
		}
	</style>
</head>

<body class="bg-light dl-dashboard-body">
	<div id="preloader">
		<div class="text-center">
			<img class="mb-3" src="<?= base_url(); ?>assets/images/doklincwhite.png" alt="" height="50px">
			<p class="mb-0">
			<div class="spinner-border spinner-border-sm text-light" role="status">
				<span class="visually-hidden">Loading...</span>
			</div>
			Memuat...
			</p>
		</div>
	</div>

	<div class="content-wrapper dl-shell" id="content-wrapper">
		<div class="contents">
			<div class="hero bg-success p-3 overflow-hidden dl-appbar">
				<a class="notify" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNotif" aria-controls="offcanvasNotif">
					<i class="bi bi-bell-fill fs-4"></i>
					<!-- kalo ada notif fetch datanya dari sini ya, bukan dari dalem elemen span nya -->
					<span class="notify-number" id="badgeNotif"></span>
					<!-- sampe sini -->
				</a>
				<div class="text-white mb-2 dl-location-row">
					<i class="fas fa-map-marker-alt me-2"></i><small><label for="" id="userLocationAddress">Menunggu lokasi...</label></small>

					<input type="hidden" id="id_user" value="<?= $this->session->userdata('id'); ?>">
					<input type="hidden" id="id_kabupaten" value="<?= $this->session->userdata('remark'); ?>">
					<input type="hidden" id="address" placeholder="Latitude">
					<input type="hidden" id="latitude" placeholder="Latitude">
					<input type="hidden" id="longitude" placeholder="Longitude">
				</div>
				<div class="d-flex dl-profile-row">
					<?php
					foreach ($data_profile->result() as $x) {
						$foto = $x->foto;
					}
					?>
					<div class="flex-shrink-0">
						<img class="rounded-4 shadow dl-profile-photo" id="previewFoto" src="<?= doclinc_safe_profile_image_src($foto); ?>" alt="Foto Profil" style="width: 100px; height: 100px; object-fit: cover;">
					</div>
					<div class="flex-grow-1 ms-3 text-white dl-profile-copy">
						<small>Selamat Datang</small>
						<h3 class="mb-0"><?= html_escape($this->session->userdata('nama')); ?></h3>
						<p class="mb-0"><?= html_escape($usia); ?></p>
						<p class="mb-0 dl-user-coordinate" id="userCoordinateLabel">Menunggu lokasi...</p>
						<span id="kota" class="d-none">Memuat...</span>
					</div>
				</div>
			</div>
			<svg id="wave" style="transform:rotate(180deg); transition: 0.3s" viewBox="0 0 1440 120" version="1.1" xmlns="http://www.w3.org/2000/svg">
				<defs>
					<linearGradient id="sw-gradient-0" x1="0" x2="0" y1="1" y2="0">
						<stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop>
						<stop stop-color="rgba(140.457, 255, 215.189, 1)" offset="100%"></stop>
					</linearGradient>
				</defs>
				<path style="transform:translate(0, 0px); opacity:1" fill="url(#sw-gradient-0)" d="M0,48L48,48C96,48,192,48,288,56C384,64,480,80,576,88C672,96,768,96,864,86C960,76,1056,56,1152,48C1248,40,1344,44,1440,42C1536,40,1632,32,1728,42C1824,52,1920,80,2016,78C2112,76,2208,44,2304,40C2400,36,2496,60,2592,76C2688,92,2784,100,2880,98C2976,96,3072,84,3168,74C3264,64,3360,56,3456,54C3552,52,3648,56,3744,54C3840,52,3936,44,4032,48C4128,52,4224,68,4320,64C4416,60,4512,36,4608,30C4704,24,4800,36,4896,44C4992,52,5088,56,5184,52C5280,48,5376,36,5472,44C5568,52,5664,80,5760,94C5856,108,5952,108,6048,98C6144,88,6240,68,6336,50C6432,32,6528,16,6624,18C6720,20,6816,40,6864,50L6912,60L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path>
				<defs>
					<linearGradient id="sw-gradient-1" x1="0" x2="0" y1="1" y2="0">
						<stop stop-color="rgba(9, 173, 116, 1)" offset="0%"></stop>
						<stop stop-color="rgba(9, 173, 116, 1)" offset="100%"></stop>
					</linearGradient>
				</defs>
				<path style="transform:translate(0, 50px); opacity:0.9" fill="url(#sw-gradient-1)" d="M0,60L48,54C96,48,192,36,288,28C384,20,480,16,576,24C672,32,768,52,864,62C960,72,1056,72,1152,64C1248,56,1344,40,1440,30C1536,20,1632,16,1728,30C1824,44,1920,76,2016,86C2112,96,2208,84,2304,68C2400,52,2496,32,2592,34C2688,36,2784,60,2880,68C2976,76,3072,68,3168,58C3264,48,3360,36,3456,26C3552,16,3648,8,3744,18C3840,28,3936,56,4032,68C4128,80,4224,76,4320,74C4416,72,4512,72,4608,72C4704,72,4800,72,4896,66C4992,60,5088,48,5184,44C5280,40,5376,44,5472,46C5568,48,5664,48,5760,46C5856,44,5952,40,6048,46C6144,52,6240,68,6336,72C6432,76,6528,68,6624,60C6720,52,6816,44,6864,40L6912,36L6912,120L6864,120C6816,120,6720,120,6624,120C6528,120,6432,120,6336,120C6240,120,6144,120,6048,120C5952,120,5856,120,5760,120C5664,120,5568,120,5472,120C5376,120,5280,120,5184,120C5088,120,4992,120,4896,120C4800,120,4704,120,4608,120C4512,120,4416,120,4320,120C4224,120,4128,120,4032,120C3936,120,3840,120,3744,120C3648,120,3552,120,3456,120C3360,120,3264,120,3168,120C3072,120,2976,120,2880,120C2784,120,2688,120,2592,120C2496,120,2400,120,2304,120C2208,120,2112,120,2016,120C1920,120,1824,120,1728,120C1632,120,1536,120,1440,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path>
			</svg>
			<div class="position-relative">
				<div id="beranda" class="content active">
					<section class="dl-hero">
						<div class="dl-hero-content">
							<h2 class="dl-hero-title">Mulai konsultasi</h2>
							<p class="dl-hero-text">Ceritakan keluhan Anda kepada Nakes.</p>
							<?php if ($doclinc_has_active_request) : ?>
								<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="dl-btn-secondary" onclick="openActiveConsultation(<?= html_escape($doclinc_active_request_id); ?>); return false;">
									Lihat konsultasi <i class="fas fa-clipboard-list"></i>
								</a>
								<p class="history-empty-text mt-2 mb-0">Selesaikan atau batalkan konsultasi aktif terlebih dahulu.</p>
							<?php else : ?>
								<a href="#" class="dl-btn-secondary" onclick="showContent('konsultasi_kesehatan')">
									Buat permintaan <i class="fas fa-plus-circle"></i>
								</a>
							<?php endif; ?>
						</div>
					</section>
					<div class="dl-feature-grid">
						<a href="<?= html_escape($doclinc_has_active_request ? base_url('home#riwayat') : '#'); ?>" class="feature-menu dl-feature-card<?= $doclinc_has_active_request ? ' disabled' : ''; ?>" onclick="<?= $doclinc_has_active_request ? 'openActiveConsultation(' . html_escape($doclinc_active_request_id) . '); return false;' : "showContent('konsultasi_kesehatan'); return false;"; ?>">
							<div class="icon-wrapper">
								<i class="fas fa-user-md"></i>
								<span class="filler"></span>
							</div>
							<span class="small"><?= $doclinc_has_active_request ? 'Konsultasi aktif' : 'Buat permintaan'; ?></span>
						</a>
						<a href="#" data-bs-toggle="modal" class="feature-menu dl-feature-card" onclick="showContent('riwayat')">
							<div class="icon-wrapper">
								<i class="fas fa-briefcase-medical"></i>
								<span class="filler"></span>
							</div>
							<span class="small">Catatan kesehatan</span>
						</a>
						<a href="#" class="feature-menu dl-feature-card">
							<div class="icon-wrapper bg-secondary">
								<i class="fas fa-bars"></i>
								<span class="filler"></span>
							</div>
							<span class="text-muted small">Lainnya</span>
						</a>
					</div>
					<section>
						<div class="dl-section-header">
							<h3 class="dl-section-title">Konsultasi saat ini</h3>
							<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="history-meta text-decoration-none" onclick="openActiveConsultation(<?= html_escape($doclinc_active_request_id); ?>); return false;">Lihat semua</a>
						</div>
						<?php if (!empty($getAllDataRequests)) : ?>
							<?php foreach ($getAllDataRequests as $dlCurrentRequest) :
								$dl_current_status = !empty($dlCurrentRequest->request_status) ? $dlCurrentRequest->request_status : '-';
								$dl_current_date = !empty($dlCurrentRequest->date) ? date('d-m-Y', strtotime($dlCurrentRequest->date)) : '-';
								$dl_current_keluhan = !empty($dlCurrentRequest->request_description) ? $dlCurrentRequest->request_description : 'Detail keluhan belum tersedia.';
								$dl_puskesmas_label = doclinc_request_puskesmas_label($dlCurrentRequest);
								$dl_queue_number_label = doclinc_request_queue_number_label($dlCurrentRequest);
								$dl_visit_status = isset($dlCurrentRequest->warga_visit_status) ? $dlCurrentRequest->warga_visit_status : (isset($dlCurrentRequest->visit_status) ? doclinc_normalize_visit_status($dlCurrentRequest->visit_status) : 'not_started');
								$dl_visit_label = isset($dlCurrentRequest->warga_visit_status_label) ? $dlCurrentRequest->warga_visit_status_label : ($dl_visit_status !== '' ? doclinc_visit_status_label($dl_visit_status) : 'Belum dimulai');
								$dl_status_label = isset($dlCurrentRequest->warga_top_status_label) ? $dlCurrentRequest->warga_top_status_label : ($dl_current_status === 'Pending' ? 'Menunggu konfirmasi Puskesmas' : $dl_visit_label);
								$dl_visit_updated = !empty($dlCurrentRequest->warga_visit_updated_at) && strtotime($dlCurrentRequest->warga_visit_updated_at) ? date('d M Y H:i', strtotime($dlCurrentRequest->warga_visit_updated_at)) : '';
								$dl_visit_timeline = isset($dlCurrentRequest->warga_visit_timeline) ? $dlCurrentRequest->warga_visit_timeline : array();
								$dl_pic_label = !empty($dlCurrentRequest->assigned_pic_label) ? $dlCurrentRequest->assigned_pic_label : 'PIC belum dipilih.';
							?>
								<div class="dl-card p-3" data-request-id="<?= (int) $dlCurrentRequest->request_id; ?>">
									<div class="d-flex justify-content-between align-items-start gap-3 mb-3">
										<div>
											<div class="d-flex align-items-center gap-2 mb-1">
												<strong><?= html_escape($dl_puskesmas_label); ?></strong>
												<span class="dl-badge px-2 py-1" data-warga-top-status="<?= html_escape((int) $dlCurrentRequest->request_id); ?>"><?= html_escape($dl_status_label); ?></span>
											</div>
											<div class="history-meta fw-bold"><?= html_escape($dl_queue_number_label); ?></div>
											<div class="history-meta"><i class="far fa-calendar-alt me-1"></i><?= html_escape($dl_current_date); ?></div>
											<div class="history-meta" data-warga-pic-label="<?= html_escape((int) $dlCurrentRequest->request_id); ?>"><?= html_escape($dl_pic_label); ?></div>
										</div>
										<div class="icon-wrapper">
											<i class="fas fa-clipboard-list"></i>
											<span class="filler"></span>
										</div>
									</div>
									<div class="history-section mb-3">
										<span class="history-result-label">Keluhan</span>
										<div class="history-result-value"><?= doclinc_warga_complaint_summary($dl_current_keluhan); ?></div>
									</div>
									<?php if ($dl_current_status === 'Accepted') : ?>
										<div class="doclinc-visit-summary mb-3">
											<p class="doclinc-visit-summary__label" data-visit-summary-label="<?= html_escape((int) $dlCurrentRequest->request_id); ?>"><?= html_escape($dl_visit_label); ?></p>
											<?php if ($dl_visit_updated !== '') : ?>
												<span class="doclinc-visit-meta" data-visit-summary-meta="<?= html_escape((int) $dlCurrentRequest->request_id); ?>">Diperbarui <?= html_escape($dl_visit_updated); ?></span>
											<?php endif; ?>
											<?= doclinc_warga_visit_timeline($dl_visit_timeline); ?>
										</div>
									<?php endif; ?>
									<div class="d-grid gap-2">
										<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="dl-btn-secondary w-100" onclick="openActiveConsultation(<?= html_escape((int) $dlCurrentRequest->request_id); ?>); return false;">Lihat detail</a>
										<?php if ($dl_current_status === 'Pending') : ?>
											<button type="button"
												class="btn btn-sm btn-outline-success rounded-pill edit-warga-request"
												data-request-id="<?= html_escape((int) $dlCurrentRequest->request_id); ?>"
												data-keluhan="<?= html_escape($dl_current_keluhan); ?>"
												data-location="<?= html_escape(isset($dlCurrentRequest->location) ? $dlCurrentRequest->location : ''); ?>"
												data-lat="<?= html_escape(isset($dlCurrentRequest->patient_latitude) && $dlCurrentRequest->patient_latitude !== null ? $dlCurrentRequest->patient_latitude : (isset($dlCurrentRequest->lattitude) ? $dlCurrentRequest->lattitude : '')); ?>"
												data-lng="<?= html_escape(isset($dlCurrentRequest->patient_longitude) && $dlCurrentRequest->patient_longitude !== null ? $dlCurrentRequest->patient_longitude : (isset($dlCurrentRequest->longitude) ? $dlCurrentRequest->longitude : '')); ?>">
												<i class="fas fa-edit me-1"></i> Tinjau permintaan
											</button>
											<button type="button" class="btn btn-sm btn-outline-danger rounded-pill cancel-warga-request" data-request-id="<?= html_escape((int) $dlCurrentRequest->request_id); ?>">
												<i class="fas fa-times-circle me-1"></i> Batalkan
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php break;
							endforeach; ?>
						<?php else : ?>
							<div class="dl-empty-state text-center">
								<p class="history-empty-text mb-0">Belum ada konsultasi.</p>
							</div>
						<?php endif; ?>
					</section>
					<div class="row">
						<div class="col">
							<div class="dl-section-header">
								<p class="dl-section-title">Berita</p>
								<div class="flex-grow-1">
									<hr class="m-0">
								</div>
							</div>
							<div class="owl-carousel owl-theme">
								<?php
								foreach ($data_feeds->result() as $x) {
								?>
									<div class="item">
										<!--<img src="<?= base_url('assets/images/feeds/' . $x->gambar); ?>">-->
										<img src="<?= base_url('admin_menu/uploads/feeds/' . $x->gambar); ?>">
									</div>
								<?php
								}
								?>
							</div>
						</div>
					</div>
				</div>
				<div id="konsultasi_kesehatan" class="content">
					<h2 class="dl-section-title mb-3">Buat permintaan</h2>
					<?php if ($doclinc_has_active_request) : ?>
						<div class="dl-empty-state text-center">
							<p class="history-empty-text mb-3">Selesaikan atau batalkan konsultasi aktif terlebih dahulu.</p>
							<a href="<?= html_escape(base_url('home#riwayat')); ?>" class="dl-btn-secondary" onclick="openActiveConsultation(<?= html_escape($doclinc_active_request_id); ?>); return false;">Lihat konsultasi</a>
						</div>
					<?php else : ?>
					<div class="card shadow mb-2 rounded-4 bg-white clickable-card dl-card"
						data-requestId=""
						data-userIdPasien=""
						data-status=""
						data-tanggal="<?= date('Y-m-d'); ?>"
						data-tanggal_loc="<?= date('Y-m-d H:i:s'); ?>"
						data-link="<?= base_url('konsultasi'); ?>">
						<div class="card-body p-3">
							<div class="d-flex hero-card">
								<img class="rounded-4" src="<?= html_escape(base_url('assets/doclinc/img/default-profile.png')); ?>" width="100px" height="auto" alt="Puskesmas">
								<div class="w-100 ms-2">
									<div class="d-flex">
										<p class="fw-bold mb-0 me-auto">Puskesmas Terdekat</p>
										<div class="end-content">
											<span class="badge rounded-pill status bg-success">Tersedia</span>
										</div>
									</div>
									<p class="mb-0 small">Konsultasi akan diarahkan otomatis ke puskesmas aktif sesuai lokasi Anda.</p>
									<i class="far fa-clock"></i> <em>Siap menerima konsultasi</em>
									<?php foreach ($getAllRequestJumlah->result() as $baris) { ?>
										<input type="hidden" name="jumlah" id="jumlah" value="<?= $baris->jumlah; ?>" />
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
					<?php endif; ?>
					<?php
					if (false && !$doclinc_has_active_request) :
					foreach ($getAllDataDoctor->result() as $row) {
						$userIdPasien = $row->user_id;
						$userId = $row->userId;
						$nama_dokter = $row->nama;
						$tanggal = $row->date;
						$status = $row->request_status;
						$tanggal_loc = $row->create_date;
						$foto = $row->foto;
					?>
						<div class="card shadow mb-2 rounded-4 bg-white clickable-card dl-card"
							data-requestId="<?= $row->request_id ?>"
							data-userIdPasien="<?= $userIdPasien ?>"
							data-status="<?= $status ?>"
							data-tanggal="<?= $tanggal ?>"
							data-tanggal_loc="<?= $tanggal_loc ?>"
							data-link="<?= base_url('konsultasi'); ?>?nama=<?= $userId; ?>">
							<div class="card-body p-3">
								<div class="d-flex hero-card">
									<img class="rounded-4" id="gambar" src="<?= doclinc_safe_profile_image_src($row->foto ?? ''); ?>" width="100px" height="auto" alt="Foto Profil">
									<div class="w-100 ms-2">
										<div class="d-flex">
											<p class="fw-bold mb-0 me-auto"><?= $nama_dokter; ?></p>
											<div class="end-content">
												<span class="badge rounded-pill status bg-success">Tersedia</span>
											</div>
										</div>

										<!-- Rating Dokter -->
										<div class="mb-1">
											<?php
											// Cari rating dokter ini berdasarkan ID dokter
											$totalRating = 0;
											$totalData = 0;

											foreach ($getAllRating->result() as $rat) {
												if ($rat->id_dokter == $row->dokter_id) {
													$totalRating += $rat->rating;
													$totalData++;
												}
											}

											$rating = ($totalData > 0) ? $totalRating / $totalData : 0;
											// Hitung bintang
											$fullStars = floor($rating);
											$halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
											$emptyStars = 5 - $fullStars - $halfStar;

											// Tambahkan kelas animasi kalau 5 bintang
											$glowClass = ($rating == 5) ? 'glow-star' : '';

											// Tampilkan bintang
											for ($i = 0; $i < $fullStars; $i++) {
												echo '<i class="fas fa-star text-warning"></i> ';
											}
											if ($halfStar) {
												echo '<i class="fas fa-star-half-alt text-warning"></i> ';
											}
											for ($i = 0; $i < $emptyStars; $i++) {
												echo '<i class="far fa-star text-warning"></i> ';
											}
											?>

										</div>

										<p class="mb-0" small>Estimasi :</p>
										<!-- <input type="hidden" name="latitude" id="latitude" placeholder="Latitude" />
										<input type="hidden" name="longitude" id="longitude" placeholder="Longitude" /> -->
										<i class="far fa-clock"></i> <em class="hasil"></em>
									</div>
								</div>
								<div></div>
								<?php
								foreach ($getAllRequestJumlah->result() as $baris) {
									// $baris->jumlah;
									$baris->user_id;
								?>
									<input type="hidden" name="jumlah" id="jumlah" value="<?= $baris->jumlah; ?>" />
								<?php }
								?>
							</div>
						</div>

					<?php }
					endif; ?>

					<div class=" card shadow mb-2 rounded-4 bg-white" hidden>
						<div class="card-body p-2">
							<div class="d-flex">
								<img class="rounded-4" src="<?= base_url('assets/images/ambulance.jpeg'); ?>" width="100px" height="auto">
								<div class="w-100 ms-2">
									<div class="d-flex">
										<p class="fw-bold mb-0 me-auto">AMBULANCE</p>
										<div class="end-content">
											<span class="badge rounded-pill bg-success">Tersedia</span>
										</div>
									</div>
									<p class="mb-0" small>Ambulance Shuttle</p>
									<i class="far fa-clock"></i> <em>25 minutes from you</em>
								</div>
							</div>
							<a class="stretched-link" href="<?= base_url('konsultasi'); ?>"></a>
						</div>
					</div>
				</div>
				<div id="riwayat" class="content">
					<h2 class="dl-section-title mb-3">Konsultasi saya</h2>
					<ul class="nav nav-tabs nav-justified mb-3 dl-tabs" id="myTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" id="proses-tab" data-bs-toggle="tab" data-bs-target="#proses-tab-pane" type="button" role="tab" aria-controls="proses-tab-pane" aria-selected="false">Saat ini</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" id="selesai-tab" data-bs-toggle="tab" data-bs-target="#selesai-tab-pane" type="button" role="tab" aria-controls="selesai-tab-pane" aria-selected="false">Riwayat</button>
						</li>
					</ul>
					<!-- Tab Konsultasi -->
					<div class="tab-content" id="myTabContent">
						<div class="tab-pane fade show active" id="proses-tab-pane" role="tabpanel" aria-labelledby="proses-tab" tabindex="0">
							<?php
							if (empty($getAllDataRequests)) :
							?>
								<div class="text-center py-4 dl-empty-state">
									<p class="history-empty-text mb-0">Belum ada konsultasi.</p>
								</div>
							<?php
							endif;
							foreach ($getAllDataRequests as $data) {
								$id_request = $data->request_id;
								$tanggal = $data->date;
								$keluhan = $data->request_description;
								$nama_dokter = $data->nama_dokter;
								$status = $data->request_status;
								$request_status = $status;
								$lat = $data->lattitude;
								$lng = $data->longitude;
								$puskesmas_label = doclinc_request_puskesmas_label($data);
								$queue_number_label = doclinc_request_queue_number_label($data);
								$visit_status = isset($data->visit_status) ? doclinc_normalize_visit_status($data->visit_status) : '';
								$visit_status = $visit_status !== '' ? $visit_status : 'not_started';
								$visit_label = isset($data->warga_visit_status_label) ? $data->warga_visit_status_label : doclinc_visit_status_label($visit_status);
								$top_status_label = isset($data->warga_top_status_label) ? $data->warga_top_status_label : ($request_status === 'Pending' ? 'Menunggu konfirmasi Puskesmas' : $visit_label);
								$visit_updated = !empty($data->warga_visit_updated_at) && strtotime($data->warga_visit_updated_at) ? date('d M Y H:i', strtotime($data->warga_visit_updated_at)) : '';
								$visit_timeline = isset($data->warga_visit_timeline) ? $data->warga_visit_timeline : array();
								$consultation_mode = isset($data->consultation_mode) ? trim((string) $data->consultation_mode) : '';
								$mode_label = doclinc_consultation_mode_label($consultation_mode);
								$handling_nakes_name = doclinc_request_handling_nakes_name($data);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Nakes belum tersedia.';
								$pic_label = !empty($data->assigned_pic_label) ? $data->assigned_pic_label : 'PIC belum dipilih.';

								// jadikan tanggal di atas formatnya jadi 11 November 2024
								$tanggal = date('d F Y', strtotime($tanggal));
							?>

								<!-- Popup HTML -->
								<div id="acceptedPopup" class="popup-alert" style="display: none;">
									<span class="close-btn" onclick="closePopup()">&times;</span>
									✅ Permintaan diterima.
								</div>

								<div class="card shadow mb-2 dl-card" data-request-id="<?= html_escape((int) $id_request); ?>">
									<div class="card-header d-flex align-items-center">
										<div>
											<p class="mb-0 fw-bold"><?= html_escape($puskesmas_label); ?></p>
											<p class="mb-0 history-meta"><?= html_escape($queue_number_label); ?> · <?= html_escape($tanggal); ?></p>
										</div>
										<span class="badge text-bg-warning ms-auto" data-warga-top-status="<?= html_escape((int) $id_request); ?>"><?= html_escape($top_status_label); ?></span>
									</div>
									<div class="card-body">
										<div class="mb-2">
											<span class="badge text-bg-light border"><?= html_escape($mode_label); ?></span>
											<?php if ($visit_label !== '') : ?>
												<span class="badge text-bg-light border"><?= html_escape($visit_label); ?></span>
											<?php endif; ?>
										</div>
										<div class="history-meta mb-2"><?= html_escape($handling_nakes_label); ?></div>
										<div class="history-meta mb-2" data-warga-pic-label="<?= html_escape((int) $id_request); ?>"><?= html_escape($pic_label); ?></div>
										<div class="history-section mb-3">
											<div class="history-section-title"><i class="fas fa-notes-medical me-1"></i> Keluhan</div>
											<?= doclinc_warga_complaint_summary($keluhan); ?>
										</div>
										<?php if ($request_status === 'Accepted') : ?>
											<div class="doclinc-visit-summary mb-3">
												<p class="doclinc-visit-summary__label" data-visit-summary-label="<?= html_escape((int) $id_request); ?>"><?= html_escape($visit_label); ?></p>
												<?php if ($visit_updated !== '') : ?>
													<span class="doclinc-visit-meta" data-visit-summary-meta="<?= html_escape((int) $id_request); ?>">Diperbarui <?= html_escape($visit_updated); ?></span>
												<?php endif; ?>
												<?= doclinc_warga_visit_timeline($visit_timeline); ?>
											</div>
										<?php endif; ?>
										<p class="mb-0 small fw-bold"><i class="fas fa-stethoscope fa-fw"></i> Nakes:</p>
										<p class="mb-0"><?= html_escape($handling_nakes_name !== '' ? $handling_nakes_name : 'Belum tersedia'); ?></p>
										<p class="mb-0 small fw-bold"><i class="far fa-clock fa-fw"></i> Estimasi:</p>
										<input type="text" name="latitudes" id="latitudes" value="<?= $lat; ?>" hidden />
										<input type="text" name="longitudes" id="longitudes" value="<?= $lng; ?>" hidden />
										<span id="estimasi"></span>
										<?php if ($request_status === 'Accepted') : ?>
											<div class="mt-3">
												<a href="<?= html_escape(base_url('chat?request_id=' . (int) $id_request)); ?>" class="btn btn-success btn-sm rounded-pill dl-btn-primary">
													<i class="fas fa-comments me-1"></i> Buka chat
												</a>
												<button type="button" class="btn btn-outline-success btn-sm rounded-pill ms-1 visit-location-toggle" data-request-id="<?= html_escape((int) $id_request); ?>" data-map-id="visit-map-<?= html_escape((int) $id_request); ?>">
													<i class="fas fa-map-marker-alt me-1"></i> Lihat lokasi
												</button>
												<div class="doclinc-visit-status mt-2" data-visit-status="<?= html_escape((int) $id_request); ?>"></div>
												<div class="visit-map-toolbar d-none" data-visit-toolbar="<?= html_escape((int) $id_request); ?>">
													<button type="button" class="btn btn-light border visit-route-recenter" data-request-id="<?= html_escape((int) $id_request); ?>" data-map-id="visit-map-<?= html_escape((int) $id_request); ?>">
														<i class="fas fa-crosshairs me-1"></i> Pusatkan
													</button>
													<button type="button" class="btn btn-success visit-route-refresh" data-request-id="<?= html_escape((int) $id_request); ?>" data-map-id="visit-map-<?= html_escape((int) $id_request); ?>">
														<i class="fas fa-sync-alt me-1"></i> Perbarui
													</button>
												</div>
												<div id="visit-map-<?= html_escape((int) $id_request); ?>" class="doclinc-visit-map mt-2 d-none"></div>
												<div class="visit-route-card mt-2 d-none" data-visit-route-card="<?= html_escape((int) $id_request); ?>">
													<div class="d-flex justify-content-between align-items-start gap-2 mb-1">
												<div class="visit-route-card-title">Dalam perjalanan</div>
														<span class="visit-route-status" data-visit-route-status="<?= html_escape((int) $id_request); ?>">Menghitung...</span>
													</div>
													<div class="visit-route-card-row">
														<span>Jarak</span>
														<strong data-visit-route-distance="<?= html_escape((int) $id_request); ?>">Menghitung...</strong>
													</div>
													<div class="visit-route-card-row">
														<span>Estimasi tiba</span>
														<strong data-visit-route-eta="<?= html_escape((int) $id_request); ?>">Menghitung...</strong>
													</div>
													<div class="visit-route-card-row">
														<span>Terakhir diperbarui</span>
														<strong data-visit-route-updated="<?= html_escape((int) $id_request); ?>">-</strong>
													</div>
													<div class="visit-route-provider-note mt-1 d-none" data-visit-route-provider-note="<?= html_escape((int) $id_request); ?>"></div>
													<div class="visit-route-provider-note mt-1 d-none" data-visit-arrival-message="<?= html_escape((int) $id_request); ?>"></div>
												</div>
											</div>
										<?php elseif ($request_status === 'Pending') : ?>
											<div class="mt-3">
												<button type="button"
													class="btn btn-outline-success btn-sm rounded-pill edit-warga-request me-1"
													data-request-id="<?= html_escape((int) $id_request); ?>"
													data-keluhan="<?= html_escape($keluhan); ?>"
													data-location="<?= html_escape(isset($data->location) ? $data->location : ''); ?>"
													data-lat="<?= html_escape(isset($data->patient_latitude) && $data->patient_latitude !== null ? $data->patient_latitude : (isset($data->lattitude) ? $data->lattitude : '')); ?>"
													data-lng="<?= html_escape(isset($data->patient_longitude) && $data->patient_longitude !== null ? $data->patient_longitude : (isset($data->longitude) ? $data->longitude : '')); ?>">
													<i class="fas fa-edit me-1"></i> Tinjau permintaan
												</button>
												<button type="button" class="btn btn-outline-danger btn-sm rounded-pill cancel-warga-request" data-request-id="<?= html_escape((int) $id_request); ?>">
													<i class="fas fa-times-circle me-1"></i> Batalkan
												</button>
											</div>
										<?php endif; ?>
									</div>
						</div>
					<?php
					}
					?>
				</div>
						<!-- Tab Riwayat -->
						<div class="tab-pane fade" id="selesai-tab-pane" role="tabpanel" aria-labelledby="selesai-tab" tabindex="0">
							<?php
							if (empty($getAllDataRequestsCompleted)) {
							?>
								<div class="text-center py-4 dl-empty-state">
									<img src="<?= html_escape(base_url('assets/images/not found.svg')); ?>" width="180" alt="Tidak ada data">
									<p class="mb-0 mt-3 text-muted">Belum ada riwayat.</p>
								</div>
							<?php
							}
							foreach ($getAllDataRequestsCompleted as $data) {
								$id_request = (int) $data->request_id;
								$tanggal = !empty($data->date) ? $data->date : '';
								$keluhan = !empty($data->request_description) ? $data->request_description : 'Detail keluhan belum tersedia.';
								$saran	 = !empty($data->recommendations) ? $data->recommendations : '-';
								$dokter_id = $data->dokter_id;
								$nama_dokter_riwayat = !empty($data->nama_dokter) ? $data->nama_dokter : 'Dokter';
								$handling_nakes_name = doclinc_request_handling_nakes_name($data);
								$handling_nakes_label = $handling_nakes_name !== '' ? 'Ditangani oleh: ' . $handling_nakes_name : 'Nakes belum tersedia.';
								$mode_label = doclinc_consultation_mode_label(isset($data->consultation_mode) ? $data->consultation_mode : '');
								$visit_status_completed = isset($data->warga_visit_status) ? $data->warga_visit_status : (isset($data->visit_status) ? doclinc_normalize_visit_status($data->visit_status) : 'not_started');
								$visit_status_completed = $visit_status_completed !== '' ? $visit_status_completed : 'not_started';
								$visit_label_completed = isset($data->warga_visit_status_label) ? $data->warga_visit_status_label : doclinc_visit_status_label($visit_status_completed);
								$visit_updated_completed = !empty($data->warga_visit_updated_at) && strtotime($data->warga_visit_updated_at) ? date('d M Y H:i', strtotime($data->warga_visit_updated_at)) : '';
								$visit_timeline_completed = isset($data->warga_visit_timeline) ? $data->warga_visit_timeline : array();
								$diagnosa = !empty($data->diagnosa) ? $data->diagnosa : (!empty($data->diagnosis) ? $data->diagnosis : '-');
								$anamnesis = isset($data->anamnesis) ? trim((string) $data->anamnesis) : '';
								$saran_dokter = !empty($data->saran) ? $data->saran : $saran;
								$card_id = !empty($data->konsul_id) ? $data->konsul_id : $id_request;
								$treatment = !empty($data->treatment) ? $data->treatment : '';
								$puskesmas = !empty($data->assigned_puskesmas_name) ? $data->assigned_puskesmas_name : '';
								$terapi_list = !empty($data->terapi_list) && is_array($data->terapi_list) ? $data->terapi_list : [];
								$tanggal_riwayat = !empty($tanggal) ? date('d F Y', strtotime($tanggal)) : '-';
								$puskesmas_label = doclinc_request_puskesmas_label($data);
								$queue_number_label = doclinc_request_queue_number_label($data);
								$hasil_rows = doclinc_warga_parse_detail_rows($treatment . "\n" . $saran_dokter);
								$dokumentasi_labels = array(
									'tindakan non-obat' => 'Tindakan non-obat',
									'pemeriksaan / monitoring' => 'Pemantauan',
									'follow-up / edukasi' => 'Edukasi lanjutan',
									'catatan tindakan lain' => 'Catatan tindakan lain',
								);
								$has_dokumentasi = false;
								foreach (array_keys($dokumentasi_labels) as $dok_key) {
									if (isset($hasil_rows[$dok_key]) && trim((string) $hasil_rows[$dok_key]) !== '') {
										$has_dokumentasi = true;
										break;
									}
								}
								$treatment_clean = doclinc_warga_clean_result_text($treatment);
								if (stripos($treatment, 'Dokumentasi Tindakan') !== false) {
									foreach (array_keys($dokumentasi_labels) as $dok_key) {
										if (isset($hasil_rows[$dok_key])) {
											unset($hasil_rows[$dok_key]);
										}
									}
									$treatment_clean = '';
								}
								$saran_clean = stripos($saran_dokter, 'Dokumentasi Tindakan') !== false ? '' : doclinc_warga_clean_result_text($saran_dokter);
							?>
								<div class="card shadow mb-3 history-result-card dl-card" id="card-<?= html_escape($card_id); ?>" data-request-id="<?= $id_request; ?>">
									<div class="card-header d-flex align-items-start gap-3">
										<div class="flex-grow-1">
											<div class="history-request-id"><?= html_escape($puskesmas_label); ?></div>
											<div class="history-meta fw-bold"><?= html_escape($queue_number_label); ?></div>
											<div class="history-meta">
												<?= doclinc_history_safe_text($tanggal_riwayat); ?><br>
												<?= html_escape($handling_nakes_label); ?><br>
												Mode: <?= html_escape($mode_label); ?>
											</div>
										</div>
										<span class="history-status-badge">Selesai</span>
									</div>
									<input type="hidden" id="reqIdRat" value="<?= $id_request; ?>">
									<div class="card-body">
										<div class="history-section">
											<div class="history-section-title"><i class="fas fa-notes-medical me-1"></i> Keluhan awal</div>
											<?= doclinc_warga_complaint_summary($keluhan); ?>
										</div>
										<?php if ($visit_status_completed !== 'not_started' || !empty($visit_timeline_completed)) : ?>
											<div class="history-section">
												<div class="history-section-title"><i class="fas fa-route me-1"></i> Status layanan</div>
												<div class="doclinc-visit-summary">
													<p class="doclinc-visit-summary__label"><?= html_escape($visit_label_completed); ?></p>
													<?php if ($visit_updated_completed !== '') : ?>
														<span class="doclinc-visit-meta">Diperbarui <?= html_escape($visit_updated_completed); ?></span>
													<?php endif; ?>
													<?= doclinc_warga_visit_timeline($visit_timeline_completed); ?>
												</div>
											</div>
										<?php endif; ?>
										<div class="history-section">
											<div class="history-section-title"><i class="fas fa-file-medical-alt me-1"></i> Hasil konsultasi</div>
											<div class="history-result-row">
												<span class="history-result-label">Diagnosis</span>
												<div class="history-result-value"><?= doclinc_history_safe_lines($diagnosa); ?></div>
											</div>
											<?php if ($anamnesis !== '') : ?>
												<div class="history-result-row">
													<span class="history-result-label">Anamnesis</span>
													<div class="history-result-value"><?= nl2br(html_escape($anamnesis), false); ?></div>
												</div>
											<?php endif; ?>
											<div class="history-result-row">
												<span class="history-result-label">Terapi, tindakan, obat</span>
												<?php if (!empty($terapi_list)) : ?>
													<div class="history-therapy-list">
														<?php foreach ($terapi_list as $index => $terapi) : ?>
															<div class="history-therapy-item">
																<div class="fw-bold"><?= html_escape(($index + 1) . '. ' . trim((string) ($terapi->terapi ?? '-'))); ?></div>
																<div class="history-meta"><?= doclinc_history_safe_text($terapi->signa ?? '-'); ?></div>
																<?php if (!empty($terapi->keterangan)) : ?>
																	<div class="history-result-value mt-1"><?= doclinc_history_safe_lines($terapi->keterangan); ?></div>
																<?php endif; ?>
															</div>
														<?php endforeach; ?>
													</div>
												<?php elseif ($treatment_clean !== '') : ?>
													<div class="history-result-value"><?= nl2br(html_escape($treatment_clean), false); ?></div>
												<?php elseif (!empty($hasil_rows)) : ?>
													<?= doclinc_warga_render_result_rows($hasil_rows, array_combine(array_keys($hasil_rows), array_map('ucwords', array_keys($hasil_rows)))); ?>
												<?php else : ?>
													<p class="history-empty-text mb-0">Belum ada terapi.</p>
												<?php endif; ?>
											</div>
											<div class="history-result-row">
												<span class="history-result-label">Saran</span>
												<div class="history-result-value"><?= $saran_clean !== '' ? nl2br(html_escape($saran_clean), false) : '<span class="history-empty-text">Belum ada rekomendasi tambahan.</span>'; ?></div>
											</div>
										</div>
										<?php if ($has_dokumentasi) : ?>
											<div class="history-section">
												<div class="history-section-title"><i class="fas fa-clipboard-check me-1"></i> Dokumentasi tindakan</div>
												<?= doclinc_warga_render_result_rows(doclinc_warga_parse_detail_rows($treatment . "\n" . $saran_dokter), $dokumentasi_labels); ?>
											</div>
										<?php endif; ?>
										<input type="hidden" id="doktId" value="<?= html_escape($dokter_id); ?>">
										<button class="btn btn-sm btn-outline-success mt-2 dl-btn-secondary" onclick="downloadCard('card-<?= html_escape($card_id); ?>')">
											<i class="fas fa-file-download"></i> Unduh resep
										</button>
										<a href="<?= html_escape(base_url('chat?request_id=' . (int) $id_request)); ?>" class="btn btn-sm btn-outline-secondary mt-2 dl-btn-secondary">
											<i class="fas fa-comments"></i> Buka chat
										</a>
									</div>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>

				<div id="profile" class="content">
					<?php
					$nama = '';
					$tgl = '';
					$jk = '';
					$no_hp = '';
					$alamat = '';
					foreach ($data_profile->result() as $x) {
						$nama = $x->nama;
						$tgl = $x->tgl;
						$jk = $x->gender;
						$no_hp = $x->no_hp;
						$alamat = $x->alamat;
						$role = $x->role;
					}
					?>
					<div class="dl-profile-card dl-card">
						<div class="dl-profile-card__head">
							<h4>Profil pengguna</h4>
							<span><?= html_escape($nama !== '' ? $nama : 'Pengguna DocLink'); ?></span>
						</div>
						<div class="dl-profile-fields">
							<div class="dl-profile-field">
								<label for="nama_lengkap"><i class="fas fa-user me-1"></i> Nama lengkap</label>
								<input type="text" id="nama_lengkap" value="<?= html_escape($nama); ?>" placeholder="Nama Lengkap" readonly>
							</div>
							<div class="dl-profile-field">
								<label for="tgl"><i class="fas fa-calendar-alt me-1"></i> Tanggal lahir</label>
								<input type="date" id="tgl" placeholder="Tanggal Lahir" value="<?= html_escape($tgl); ?>" readonly>
							</div>
							<div class="dl-profile-field">
								<label for="jk"><i class="fas fa-venus-mars me-1"></i> Jenis kelamin</label>
								<input type="text" id="jk" placeholder="Jenis Kelamin" value="<?= html_escape($jk); ?>" readonly>
							</div>
							<div class="dl-profile-field">
								<label for="no_hp"><i class="fas fa-phone-alt me-1"></i> Nomor HP</label>
								<input type="text" id="no_hp" value="<?= html_escape($no_hp); ?>" placeholder="Nomor HP" readonly>
							</div>
							<div class="dl-profile-field">
								<label for="alamat"><i class="fas fa-map-marker-alt me-1"></i> Alamat</label>
								<textarea id="alamat" readonly><?= html_escape($alamat); ?></textarea>
							</div>
						</div>
						<div class="dl-profile-actions">
							<button type="button" class="btn btn-outline-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#modalProfil" hidden>
								<i class="fas fa-edit me-1"></i> Ubah profil
							</button>
							<button type="button" class="btn btn-outline-danger w-100" id="btn-logout">
								<i class="fas fa-sign-out-alt me-1"></i> Keluar
							</button>
						</div>
					</div>
				</div>

				<div id="notifikasi" class="content">

				</div>
				<div id="pahlawan_1" class="content">

					<form action="" method="post">
						<div class="form-floating mb-2">
							<input type="hidden" class="form-control shadow border-success" id="nama" value="<?php echo $_SESSION['username']; ?>" placeholder="Nama Lengkap" readonly>
						</div>
						<div class="form-floating mb-2">
							<textarea name="keluhan" id="keluhan" class="form-control shadow border-success" placeholder="keluhan"></textarea>
							<label for="keluhan">Keluhan</label>
						</div>
						<div class="form-floating mb-2 d-none">
							<input type="hidden" class="form-control shadow border-success" id="no_hp" value="087775587778" placeholder="Nomor HP" readonly>
							<label for="no_hp">Nomor HP</label>
						</div>
						<div class="form-floating mb-2">

							<textarea id="address" class="form-control shadow border-success" placeholder="Alamat: ..." readonly style="height: 100px"></textarea>
							<label for="alamat">Alamat</label>
							<input type="text" class="d-none" id="latitudex" placeholder="Latitude" readonly>
							<input type="text" class="d-none" id="longitudex" placeholder="Longitude" readonly>
							<div id="map"></div>
						</div>
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="tanggal" value="<?php echo date('d-m-Y'); ?>" placeholder="tanggal" readonly>
							<label for="Tanggal">Tanggal </label>
						</div>
						<div class="d-grid">
							<button type="submit" class="btn btn-outline-success" id="save_konsul">Kirim</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>

	<!-- menubar bottom -->
	<div class="nav-bottom-wrapper shadow-lg rounded-top-4 dl-bottom-nav" id="nav-bottom-wrapper">
		<div class="container-fluid px-0">
			<div class="row g-0 text-center p-2 menu">
				<a href="#" id="beranda-tab" class="col menu-item active" onclick="showContent('beranda')">
					<i class="fas fa-home fs-4"></i>
					<span class="d-block small mt-1">Beranda</span>
				</a>
				<a href="#" id="konsultasi_kesehatan-tab" class="col menu-item" onclick="showContent('konsultasi_kesehatan')">
					<i class="fas fa-user-md fs-4"></i>
					<span class="d-block small mt-1">Konsultasi</span>
				</a>
				<a href="#" id="riwayat-tab" class="col menu-item" onclick="showContent('riwayat')">
					<i class="fas fa-file-medical fs-4"></i>
					<span class="d-block small mt-1">Riwayat</span>
				</a>
				<a href="#" id="profile-tab" class="col menu-item" onclick="showContent('profile')">
					<i class="fas fa-user fs-4"></i>
					<span class="d-block small mt-1">Profil</span>
				</a>
			</div>
		</div>
	</div>

	<div class="modal fade" id="editRequestModal" tabindex="-1" aria-labelledby="editRequestModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<div>
						<h1 class="modal-title fs-5" id="editRequestModalLabel">Ubah permintaan</h1>
						<p class="text-muted small mb-0">Hanya dapat diubah sebelum diterima.</p>
					</div>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<form id="editRequestForm" method="post" action="<?= html_escape(base_url('home/updateRequestById')); ?>">
					<div class="modal-body">
						<input type="hidden" name="requestId" id="edit_request_id">
						<input type="hidden" name="lat" id="edit_request_lat">
						<input type="hidden" name="lng" id="edit_request_lng">
						<div class="mb-3">
							<label for="edit_request_gejala" class="form-label fw-semibold">Keluhan utama</label>
							<?php if ($clinical_suggestions_enabled) : ?>
							<input type="text" class="form-control" name="gejala_utama" id="edit_request_gejala"
								placeholder="Ketik keluhan, minimal 2 karakter" data-clinical-suggestion
								data-clinical-suggestion-type="complaint"
								data-clinical-suggestion-endpoint="<?= html_escape($clinical_suggestions_endpoint); ?>"
								data-clinical-request-id-source="#edit_request_id">
							<?php else : ?>
							<select class="form-control" name="gejala_utama" id="edit_request_gejala">
								<option value="">Pilih keluhan</option>
								<?php foreach ($master_gejala_keluhan_options as $option): ?>
									<?php $option_name = trim((string) ($option->nama_keluhan ?? '')); ?>
									<?php if ($option_name === '') continue; ?>
									<option value="<?= html_escape($option_name); ?>"><?= html_escape($option_name); ?></option>
								<?php endforeach; ?>
							</select>
							<?php endif; ?>
						</div>
						<div class="mb-3">
							<label for="edit_request_keluhan" class="form-label fw-semibold">Detail keluhan</label>
							<textarea class="form-control" name="keluhan" id="edit_request_keluhan" rows="4" required></textarea>
						</div>
						<div class="mb-0">
							<label for="edit_request_alamat" class="form-label fw-semibold">Alamat atau patokan</label>
							<textarea class="form-control" name="alamat" id="edit_request_alamat" rows="3"></textarea>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
						<button type="submit" class="btn btn-success">Simpan</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="modal fade" id="modalProfil" tabindex="-1" aria-labelledby="modalProfilLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h1 class="modal-title fs-5" id="modalProfilLabel">Ubah profil</h1>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<form action="" method="post">
					<div class="modal-body">
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="nama_lengkap_edit" value="Muhammad Bani Husni" placeholder="Nama Lengkap">
							<label for="nama_lengkap_edit">Nama Lengkap</label>
						</div>
						<div class="form-floating mb-2">
							<input type="date" class="form-control shadow border-success" id="tgl_edit" value="20/07/1993" placeholder="Tanggal Lahir">
							<label for="tgl_edit">Tanggal Lahir</label>
						</div>
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="jk_edit" value="Laki-laki" placeholder="Jenis Kelamin">
							<label for="jk_edit">Jenis Kelamin</label>
						</div>
						<div class="form-floating mb-2">
							<input type="text" class="form-control shadow border-success" id="no_hp_edit" value="087775587778" placeholder="Nomor HP">
							<label for="no_hp_edit">Nomor HP</label>
						</div>
						<div class="form-floating mb-2">
							<textarea class="form-control shadow border-success" placeholder="Alamat" id="alamat_edit" style="height: 100px">BCS Logistics Center Jl. Raya Merak KM. 115, Cilegon Banten, Indonesia - 42436
	                </textarea>
							<label for="alamat_edit">Alamat</label>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
						<button type="submit" class="btn btn-success">Simpan</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="offcanvas offcanvas-top" tabindex="-1" id="offcanvasNotif" aria-labelledby="offcanvasNotifLabel">
		<div class="offcanvas-header dl-notification-header">
			<div class="dl-notification-title">
				<i class="bi bi-bell-fill"></i>
				<span class="offcanvas-title" id="offcanvasNotifLabel">Notifikasi</span>
				<span class="badge text-bg-danger" id="badgeNotifs" style="display: none;"></span>
			</div>
			<button type="button" class="btn-close dl-notification-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
		</div>
		<div class="offcanvas-body dl-notification-body">
			<div id="notificationList" class="notification-list dl-notification-list"></div>
		</div>
	</div>

	<div class="modal fade" id="ratingModal" tabindex="-1" aria-labelledby="ratingModalLabel" aria-hidden="false">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content border-0 shadow-lg">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title" id="ratingModalLabel">Beri penilaian</h5>
					<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body text-center">
					<div class="mb-3">
						<img id="gambarDokter" src="" alt="Foto Dokter" class="rounded-circle border border-success shadow-sm" width="100" height="100">
					</div>
					<p class="fw-bold">Bagaimana konsultasi Anda dengan <span id="namaDokter" class="text-success"></span>?</p>
					<!-- Form Rating -->
					<form id="ratingForm">
						<input type="hidden" name="iduser" value="<?= $this->session->userdata('id') ?>">
						<input type="hidden" name="id_dokter" id="dokIds">
						<div class="mb-3">
							<!-- Rating input -->
							<div class="rating-stars">
								<input type="radio" id="star5" name="rating" value="5" required />
								<label for="star5" title="Luar Biasa"><i class="fas fa-star"></i></label>
								<input type="radio" id="star4" name="rating" value="4" />
								<label for="star4" title="Sangat Baik"><i class="fas fa-star"></i></label>
								<input type="radio" id="star3" name="rating" value="3" />
								<label for="star3" title="Baik"><i class="fas fa-star"></i></label>
								<input type="radio" id="star2" name="rating" value="2" />
								<label for="star2" title="Cukup"><i class="fas fa-star"></i></label>
								<input type="radio" id="star1" name="rating" value="1" />
								<label for="star1" title="Buruk"><i class="fas fa-star"></i></label>
							</div>
						</div>
						<button type="submit" class="btn btn-success w-100">Kirim penilaian</button>
					</form>
				</div>
			</div>
		</div>
	</div>


	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
	<?php if ($map_provider === 'google' && !empty($google_maps_api_key)) : ?>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($google_maps_api_key); ?>"></script>
	<?php endif; ?>

	<!-- firebase dan notifikasi -->

	<?php if ($firebase_enabled) : ?>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
		<?php if ($legacy_superapp_url !== '#') : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/get-notif.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

	<script>
		(function(window, $) {
			const ns = window.doclincVisitTracking = window.doclincVisitTracking || {};
			const visitMapboxToken = <?= json_encode($mapbox_public_token); ?>;
			const visitLocationUrl = <?= json_encode(base_url('home/visit_location')); ?>;
			const wargaPinIconUrl = <?= json_encode(base_url('assets/doclinc_ui/konsultasi_nakes/warga-pin.svg')); ?>;
			const nakesPinIconUrl = <?= json_encode(base_url('assets/doclinc_ui/konsultasi_nakes/nakes-pin.svg')); ?>;
			const pollIntervalMs = 12000;
			const maps = ns.maps = ns.maps || {};
			const timers = ns.timers = ns.timers || {};

			function parseLocation(location) {
				if (!location) return null;
				const lat = parseFloat(location.latitude);
				const lng = parseFloat(location.longitude);
				if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
				return {
					latitude: lat,
					longitude: lng,
					updated_at: location.updated_at || ''
				};
			}

			function setVisitStatus(requestId, message, isError) {
				const element = document.querySelector('[data-visit-status="' + requestId + '"]');
				if (!element) return;
				element.textContent = message || '';
				element.classList.toggle('text-danger', !!isError);
				element.classList.toggle('text-success', !isError && !!message);
			}

			function setVisitSummary(requestId, response) {
				const labelElements = document.querySelectorAll('[data-visit-summary-label="' + requestId + '"]');
				const metaElements = document.querySelectorAll('[data-visit-summary-meta="' + requestId + '"]');
				const topStatusElements = document.querySelectorAll('[data-warga-top-status="' + requestId + '"]');
				const picElements = document.querySelectorAll('[data-warga-pic-label="' + requestId + '"]');
				const label = response && (response.warga_visit_status_label || response.visit_status_label) ? (response.warga_visit_status_label || response.visit_status_label) : '';
				const topLabel = response && response.warga_top_status_label ? response.warga_top_status_label : label;
				const picLabel = response && response.assigned_pic_label ? response.assigned_pic_label : '';
				labelElements.forEach(function(element) {
					if (label) {
						element.textContent = label;
					}
				});
				topStatusElements.forEach(function(element) {
					if (topLabel) {
						element.textContent = topLabel;
					}
				});
				picElements.forEach(function(element) {
					if (picLabel) {
						element.textContent = picLabel;
					}
				});
				metaElements.forEach(function(element) {
					const updatedAt = response && response.nakes && response.nakes.updated_at ? response.nakes.updated_at : '';
					if (updatedAt) {
						element.textContent = 'Diperbarui ' + updatedAt;
					}
				});
			}

			function setVisitRouteSummary(requestId, response) {
				const route = response && response.route ? response.route : {};
				const patient = response && response.patient ? response.patient : null;
				const nakes = response && response.nakes ? response.nakes : null;
				const card = document.querySelector('[data-visit-route-card="' + requestId + '"]');
				const toolbar = document.querySelector('[data-visit-toolbar="' + requestId + '"]');
				const distanceElement = document.querySelector('[data-visit-route-distance="' + requestId + '"]');
				const etaElement = document.querySelector('[data-visit-route-eta="' + requestId + '"]');
				const updatedElement = document.querySelector('[data-visit-route-updated="' + requestId + '"]');
				const statusElement = document.querySelector('[data-visit-route-status="' + requestId + '"]');
				const providerNoteElement = document.querySelector('[data-visit-route-provider-note="' + requestId + '"]');
				const arrivalElement = document.querySelector('[data-visit-arrival-message="' + requestId + '"]');
				const hasDistance = !!(route && (route.distance_text || route.eta_text));
				const hasGeometry = !!(route && route.geometry && (route.geometry.type === 'polyline6' || route.geometry.type === 'LineString'));
				const patientAvailable = !patient || patient.available !== false;
				const nakesAvailable = !!(nakes && nakes.available !== false && nakes.latitude && nakes.longitude);
				const isValhallaRoute = !!(route && route.provider === 'valhalla');
				const arrival = response && response.arrival ? response.arrival : null;
				let statusText = 'Menghitung...';

				if (!patientAvailable) {
					statusText = 'Lokasi pasien belum tersedia';
				} else if (!nakesAvailable) {
					statusText = 'Menunggu lokasi Nakes';
				} else if (isValhallaRoute) {
					statusText = 'Estimasi berdasarkan rute jalan';
				} else if (!hasGeometry && hasDistance) {
					statusText = 'Rute dihitung';
				}

				if (card) card.classList.remove('d-none');
				if (toolbar) toolbar.classList.remove('d-none');
				if (distanceElement) distanceElement.textContent = route && route.distance_text ? route.distance_text : 'Menghitung...';
				if (etaElement) etaElement.textContent = route && route.eta_text ? route.eta_text : 'Menghitung...';
				if (updatedElement) updatedElement.textContent = route && route.calculated_at ? route.calculated_at : (response && response.status === 'success' ? new Date().toLocaleString('id-ID') : '-');
				if (statusElement) statusElement.textContent = statusText;
				if (providerNoteElement) {
					providerNoteElement.textContent = isValhallaRoute ? 'Estimasi berdasarkan rute jalan' : '';
					providerNoteElement.classList.toggle('d-none', !isValhallaRoute);
				}
				if (arrivalElement) {
					const arrivalMessage = arrival && (arrival.should_prompt_arrival || arrival.distance_to_patient_text) ? (arrival.message || '') : '';
					arrivalElement.textContent = arrivalMessage;
					arrivalElement.classList.toggle('d-none', arrivalMessage === '');
				}
			}

			function getVisitStatusMessage(response, fallback) {
				const label = response && response.visit_status_label ? response.visit_status_label : '';
				const mode = response && response.consultation_mode_label ? response.consultation_mode_label : '';
				const route = response && response.route ? response.route : {};
				const distance = route && route.distance_text ? route.distance_text : 'Menghitung...';
				const eta = route && route.eta_text ? route.eta_text : 'Menghitung...';
				const routeText = 'Jarak: ' + distance + ' · ETA: ' + eta;
				const parts = [];
				if (label) parts.push(label);
				if (mode) parts.push(mode);
				if (fallback) parts.push(fallback);
				if (routeText) parts.push(routeText);
				return parts.join(' - ');
			}

			function stopPolling(requestId) {
				if (timers[requestId]) {
					clearTimeout(timers[requestId]);
					delete timers[requestId];
				}
			}

			function createVisitMarkerIcon(type) {
				return L.icon({
					iconUrl: type === 'nakes' ? nakesPinIconUrl : wargaPinIconUrl,
					iconSize: [44, 44],
					iconAnchor: [22, 44],
					popupAnchor: [0, -40]
				});
			}

			function routeLatLngs(route) {
				if (!route || !route.geometry || !Array.isArray(route.geometry.coordinates)) {
					return [];
				}
				const isPolyline6 = route.geometry.type === 'polyline6';
				const isLineString = route.geometry.type === 'LineString';
				if (!isPolyline6 && !isLineString) return [];
				return route.geometry.coordinates
					.map(function(coordinate) {
						if (!Array.isArray(coordinate) || coordinate.length < 2) return null;
						const lat = parseFloat(isPolyline6 ? coordinate[0] : coordinate[1]);
						const lng = parseFloat(isPolyline6 ? coordinate[1] : coordinate[0]);
						if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
						return [lat, lng];
					})
					.filter(Boolean);
			}

			ns.initVisitMap = function(containerId, patientLocation, nakesLocation) {
				if (!window.L) return null;
				const container = document.getElementById(containerId);
				if (!container) return null;

				let state = maps[containerId];
				const center = nakesLocation || patientLocation || {
					latitude: -6.0176,
					longitude: 106.0530
				};

				if (!state) {
					const map = L.map(container).setView([center.latitude, center.longitude], 14);
					const tileUrl = visitMapboxToken ?
						'https://api.mapbox.com/styles/v1/mapbox/streets-v11/tiles/{z}/{x}/{y}?access_token=' + encodeURIComponent(visitMapboxToken) :
						'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
					L.tileLayer(tileUrl, {
						maxZoom: 19,
						tileSize: visitMapboxToken ? 512 : 256,
						zoomOffset: visitMapboxToken ? -1 : 0,
						attribution: visitMapboxToken ? '&copy; OpenStreetMap contributors &copy; Mapbox' : '&copy; OpenStreetMap contributors'
					}).addTo(map);
					state = maps[containerId] = {
						map: map,
						markers: {},
						routeOutlineLine: null,
						routeLine: null,
						lastBounds: null,
						hasAutoFit: false,
						autoFitMode: ''
					};
					setTimeout(function() {
						map.invalidateSize();
					}, 0);
				}

				setTimeout(function() {
					state.map.invalidateSize();
				}, 50);

				return state;
			};

			function fitVisitBounds(state, bounds, forceFit, mode) {
				if (!state || !state.map || !bounds || !bounds.length) return;
				state.lastBounds = bounds.slice();
				if (!forceFit && state.hasAutoFit && !(mode === 'route' && state.autoFitMode !== 'route')) return;
				if (bounds.length > 1) {
					state.map.fitBounds(bounds, {
						padding: [54, 54],
						maxZoom: 17
					});
				} else {
					state.map.setView(bounds[0], Math.max(state.map.getZoom(), 14));
				}
				state.hasAutoFit = true;
				state.autoFitMode = mode || state.autoFitMode || 'markers';
			}

			function clearVisitRoute(mapContainerId) {
				const state = maps[mapContainerId];
				if (!state || !state.map) return;
				if (state.routeOutlineLine) {
					state.map.removeLayer(state.routeOutlineLine);
					state.routeOutlineLine = null;
				}
				if (state.routeLine) {
					state.map.removeLayer(state.routeLine);
					state.routeLine = null;
				}
			}

			ns.updateVisitMapMarkers = function(containerId, patientLocation, nakesLocation, route) {
				const state = maps[containerId];
				if (!state) return;
				const bounds = [];

				function upsertMarker(name, location, label) {
					if (!location) return;
					const latLng = [location.latitude, location.longitude];
					const isNakes = name === 'nakes';
					if (!state.markers[name]) {
						state.markers[name] = L.marker(latLng, {
							icon: createVisitMarkerIcon(name),
							zIndexOffset: isNakes ? 1000 : 0
						}).addTo(state.map).bindPopup(label);
					} else {
						state.markers[name].setLatLng(latLng);
					}
					bounds.push(latLng);
				}

				upsertMarker('patient', patientLocation, 'Pasien');
				upsertMarker('nakes', nakesLocation, 'Nakes');

				if (state.routeOutlineLine) {
					state.map.removeLayer(state.routeOutlineLine);
					state.routeOutlineLine = null;
				}
				if (state.routeLine) {
					state.map.removeLayer(state.routeLine);
					state.routeLine = null;
				}
				const routePoints = routeLatLngs(route);
				if (routePoints.length > 1) {
					state.routeOutlineLine = L.polyline(routePoints, {
						weight: 10,
						opacity: 0.32,
						color: '#052e1f',
						lineCap: 'round',
						lineJoin: 'round'
					}).addTo(state.map);
					state.routeLine = L.polyline(routePoints, {
						weight: 6,
						opacity: 0.95,
						color: '#09AD74',
						lineCap: 'round',
						lineJoin: 'round'
					}).addTo(state.map);
					routePoints.forEach(function(latLng) {
						bounds.push(latLng);
					});
				}

				fitVisitBounds(state, bounds, false, routePoints.length > 1 ? 'route' : 'markers');

				setTimeout(function() {
					state.map.invalidateSize();
				}, 0);
			};

			ns.recenterVisitRoute = function(mapContainerId) {
				const state = maps[mapContainerId];
				if (!state || !state.map) return;
				state.map.invalidateSize();
				fitVisitBounds(state, state.lastBounds || [], true, state.autoFitMode || 'markers');
			};

			ns.pollVisitLocation = function(requestId, mapContainerId) {
				stopPolling(requestId);

				function poll() {
					$.ajax({
						url: visitLocationUrl,
						type: 'GET',
						dataType: 'json',
						data: {
							request_id: requestId
						},
						success: function(response) {
							if (typeof response === 'string') {
								try {
									response = JSON.parse(response);
								} catch (error) {}
							}

							if (response && (response.tracking_active === false || (response.request_status && response.request_status !== 'Accepted'))) {
								setVisitRouteSummary(requestId, response);
								setVisitSummary(requestId, response);
								setVisitStatus(requestId, response.message || 'Pelacakan kunjungan dihentikan.');
								clearVisitRoute(mapContainerId);
								stopPolling(requestId);
								return;
							}

							if (response && response.status === 'success') {
								const patientLocation = parseLocation(response.patient);
								const nakesLocation = parseLocation(response.nakes);
								const state = ns.initVisitMap(mapContainerId, patientLocation, nakesLocation);
								if (state) {
									ns.updateVisitMapMarkers(mapContainerId, patientLocation, nakesLocation, response.route);
								}
								setVisitRouteSummary(requestId, response);
								setVisitSummary(requestId, response);
								setVisitStatus(requestId, getVisitStatusMessage(response, nakesLocation ? 'Lokasi Nakes tersedia.' : 'Lokasi Nakes belum tersedia.'));
							} else if (response && response.status === 'pending') {
								setVisitRouteSummary(requestId, response);
								setVisitSummary(requestId, response);
								setVisitStatus(requestId, getVisitStatusMessage(response, response.message || 'Lokasi Nakes belum tersedia.'));
							} else {
								setVisitRouteSummary(requestId, response || {});
								setVisitStatus(requestId, response && response.message ? response.message : 'Lokasi Nakes belum tersedia.', true);
							}

							timers[requestId] = setTimeout(poll, pollIntervalMs);
						},
						error: function(xhr) {
							const response = xhr.responseJSON || {};
							setVisitStatus(requestId, response.message || 'Lokasi Nakes belum tersedia.', true);
							if (xhr.status === 403 || xhr.status === 404 || xhr.status === 405) {
								stopPolling(requestId);
								return;
							}
							timers[requestId] = setTimeout(poll, pollIntervalMs);
						}
					});
				}

				poll();
			};

			$(document).on('click', '.visit-location-toggle', function(event) {
				event.preventDefault();
				const button = $(this);
				const requestId = button.data('request-id');
				const mapContainerId = button.data('map-id');
				if (!requestId || !mapContainerId) return;

				$('#' + mapContainerId).removeClass('d-none');
				if (maps[mapContainerId] && maps[mapContainerId].map) {
					setTimeout(function() {
						maps[mapContainerId].map.invalidateSize();
					}, 0);
				}
				ns.pollVisitLocation(requestId, mapContainerId);
			});

			$(document).on('click', '.visit-route-recenter', function(event) {
				event.preventDefault();
				const mapContainerId = $(this).data('map-id');
				if (!mapContainerId) return;
				ns.recenterVisitRoute(mapContainerId);
			});

			$(document).on('click', '.visit-route-refresh', function(event) {
				event.preventDefault();
				const button = $(this);
				const requestId = button.data('request-id');
				const mapContainerId = button.data('map-id');
				if (!requestId || !mapContainerId) return;
				$('#' + mapContainerId).removeClass('d-none');
				ns.pollVisitLocation(requestId, mapContainerId);
			});
		})(window, jQuery);
	</script>

	<!-- popup jam operasional -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const lastPopupTime = localStorage.getItem('lastPopupTime');
			const currentTime = new Date().getTime();

			if (!lastPopupTime || (currentTime - lastPopupTime) > 3600000) { // 1 hour = 3600000 ms
				Swal.fire({
					title: 'Jam layanan kunjungan',
					text: 'Jam kunjungan: 08.00–17.00.',
					icon: 'info',
					confirmButtonText: 'Tutup'
				}).then(() => {
					localStorage.setItem('lastPopupTime', currentTime.toString());
				});
			}
		});
	</script>

	<script>
		let doktIdEl = document.getElementById('doktId');
		let dokId = document.getElementById('dokId');
		if (doktIdEl && dokId) {
			dokId.value = doktIdEl.value;
		}
	</script>

	<script>
		const gambar = document.getElementById('gambar');
	</script>

	<!-- Webtoapk dan lain-lain -->
	<script>
		function exitApp() {
			Website2APK.exitApp();
		}
		window.addEventListener('load', function() {
			setTimeout(function() {
				$('#preloader').hide();
				document.getElementById('content-wrapper').style.display = 'block';
				document.getElementById('nav-bottom-wrapper').style.display = 'block';
			}, 1500);
		});
		document.addEventListener('DOMContentLoaded', function() {
			var hash = window.location.hash;
			if (hash) {
				showContent(hash.replace('#', ''));
			}
		});

		function showContent(tab) {
			let currentActiveContent = document.querySelector('.content.active');
			if (currentActiveContent) {
				currentActiveContent.classList.remove('active');
			}

			let targetContent = document.getElementById(tab);
			if (targetContent) {
				targetContent.classList.add('active');
			}

			let currentActiveMenu = document.querySelector('.nav-bottom-wrapper .menu a.active');
			if (currentActiveMenu) {
				currentActiveMenu.classList.remove('active');
			}

			let targetMenu = document.getElementById(tab + '-tab');
			if (targetMenu) {
				targetMenu.classList.add('active');
			}
		}

		function openActiveConsultation(requestId) {
			showContent('riwayat');

			const prosesTab = document.getElementById('proses-tab');
			if (prosesTab && typeof bootstrap !== 'undefined') {
				const tab = new bootstrap.Tab(prosesTab);
				tab.show();
			}

			window.location.hash = 'riwayat';
			setTimeout(function() {
				const selector = requestId ? '.dl-card[data-request-id="' + requestId + '"]' : '#proses-tab-pane';
				const target = document.querySelector(selector) || document.getElementById('proses-tab-pane');
				if (target) {
					target.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
				}
			}, 100);
		}

		$('#btn-logout').click(function(event) {
			Swal.fire({
				title: "Keluar dari akun?",
				text: "Anda perlu masuk kembali.",
				icon: "warning",
				showCancelButton: true,
				confirmButtonText: "Keluar",
				confirmButtonColor: "#09AD74",
				cancelButtonText: "Batal"
			}).then((result) => {
				if (result.isConfirmed) {
					Swal.fire({
						title: "Sampai jumpa",
						icon: "success",
						showConfirmButton: false,
						timer: 1500,
						timerProgressBar: true
					}).then((result) => {
						if (result.dismiss === Swal.DismissReason.timer) {
							window.location.href = 'login/logout';
						}
					});
				}
			});
		});

		$('#save_konsul').click(function() {
			var nama = $('#nama').val();
			var keluhan = $('#keluhan').val();
			var no_hp = $('#no_hp').val();
			var lat = $('#latitude').val();
			var lng = $('#longitude').val();
			var alamat = $('#address').val();
			var tanggal = $('#tanggal').val();

			alert(nama);
			alert(keluhan);
			alert(lat);
			alert(lng);
			alert(alamat);
			alert(tanggal);
		});
		$('.owl-carousel').owlCarousel({
			loop: true,
			autoplay: true,
			autoplayTimeout: 2500,
			autoplayHoverPause: true,
			margin: 10,
			responsiveClass: true,
			responsive: {
				0: {
					items: 1
				},
				640: {
					items: 2
				},
				1024: {
					items: 3
				}
			}
		});

		$(document).on('click', '.edit-warga-request', function(event) {
			event.preventDefault();
			event.stopPropagation();

			const button = $(this);
			const requestId = button.attr('data-request-id');
			if (!requestId) {
				Swal.fire('Gagal', 'Permintaan tidak ditemukan.', 'error');
				return;
			}

			$('#edit_request_id').val(requestId);
			const parsedComplaint = parseWargaComplaintForEdit(button.attr('data-keluhan') || '');
			$('#edit_request_gejala').val(parsedComplaint.gejala);
			if (parsedComplaint.gejala !== '' && $('#edit_request_gejala').val() !== parsedComplaint.gejala) {
				parsedComplaint.detail = 'Gejala/Keluhan utama: ' + parsedComplaint.gejala + (parsedComplaint.detail !== '' ? "\n" + parsedComplaint.detail : '');
				$('#edit_request_gejala').val('');
			}
			$('#edit_request_keluhan').val(parsedComplaint.detail);
			$('#edit_request_alamat').val(button.attr('data-location') || '');
			$('#edit_request_lat').val(button.attr('data-lat') || '');
			$('#edit_request_lng').val(button.attr('data-lng') || '');

			const modalElement = document.getElementById('editRequestModal');
			if (modalElement && window.bootstrap) {
				bootstrap.Modal.getOrCreateInstance(modalElement).show();
			} else {
				$('#editRequestModal').modal('show');
			}
		});

		function parseWargaComplaintForEdit(rawText) {
			const result = {
				gejala: '',
				detail: ''
			};
			const text = (rawText || '').trim();
			if (text === '') {
				return result;
			}

			const lines = text.split(/\r?\n/);
			const detailParts = [];
			lines.forEach(function(line) {
				const cleanLine = line.replace(/\*\*(.*?)\*\*/g, '$1').trim();
				if (cleanLine === '' || cleanLine.toLowerCase() === 'anamnesa') {
					return;
				}

				const separatorIndex = cleanLine.indexOf(':');
				if (separatorIndex === -1) {
					detailParts.push(cleanLine);
					return;
				}

				const key = cleanLine.slice(0, separatorIndex).trim().toLowerCase();
				const value = cleanLine.slice(separatorIndex + 1).trim();
				if (key === 'gejala/keluhan utama') {
					result.gejala = value;
					return;
				}
				if (key === 'deskripsi keluhan' || key === 'detail keluhan') {
					detailParts.push(value);
					return;
				}
				if (key === 'keluhan utama' || key === 'lama keluhan' || key === 'gejala tambahan') {
					detailParts.push(cleanLine);
				}
			});

			result.detail = detailParts.length > 0 ? detailParts.join("\n") : text;
			return result;
		}

		$('#editRequestForm').on('submit', function(event) {
			event.preventDefault();

			const form = $(this);
			const submitButton = form.find('button[type="submit"]');
			submitButton.prop('disabled', true).addClass('disabled');

			$.ajax({
				url: form.attr('action'),
				type: 'POST',
				dataType: 'json',
				data: form.serialize(),
				success: function(response) {
					if (typeof response === 'string') {
						try {
							response = JSON.parse(response);
						} catch (error) {}
					}

					if (response && response.status === 'success') {
						Swal.fire({
							title: 'Permintaan diperbarui.',
							text: response.message || 'Permintaan diperbarui.',
							icon: 'success',
							showConfirmButton: false,
							timer: 1200,
							timerProgressBar: true
						}).then(() => {
							window.location.reload();
						});
						return;
					}

					submitButton.prop('disabled', false).removeClass('disabled');
					Swal.fire('Permintaan belum diperbarui', response && response.message ? response.message : 'Terjadi kesalahan. Coba lagi.', 'error');
				},
				error: function(xhr) {
					submitButton.prop('disabled', false).removeClass('disabled');
					const response = xhr.responseJSON || {};
					Swal.fire('Permintaan belum diperbarui', response.message || 'Terjadi kesalahan. Coba lagi.', 'error');
				}
			});
		});

		$(document).on('click', '.cancel-warga-request', function(event) {
			event.preventDefault();
			event.stopPropagation();

			const button = $(this);
			const requestId = button.data('request-id');
			if (!requestId) {
				Swal.fire('Gagal', 'Permintaan tidak ditemukan.', 'error');
				return;
			}

			Swal.fire({
				title: 'Batalkan permintaan?',
				text: 'Permintaan yang dibatalkan tidak dapat dilanjutkan.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Batalkan',
				confirmButtonColor: '#dc3545',
				cancelButtonText: 'Batal'
			}).then((result) => {
				if (!result.isConfirmed) {
					return;
				}

				button.prop('disabled', true).addClass('disabled');
				$.ajax({
					url: '<?= base_url('home/cancel_request'); ?>',
					type: 'POST',
					dataType: 'json',
					data: {
						request_id: requestId
					},
					success: function(response) {
						if (typeof response === 'string') {
							try {
								response = JSON.parse(response);
							} catch (error) {}
						}
						if (response && response.status === 'success') {
							Swal.fire({
								title: 'Permintaan dibatalkan.',
								text: response.message || 'Permintaan dibatalkan.',
								icon: 'success',
								showConfirmButton: false,
								timer: 1200,
								timerProgressBar: true
							}).then(() => {
								window.location.reload();
							});
							return;
						}

						button.prop('disabled', false).removeClass('disabled');
						Swal.fire('Permintaan belum dibatalkan', response && response.message ? response.message : 'Terjadi kesalahan. Coba lagi.', 'error');
					},
					error: function(xhr) {
						button.prop('disabled', false).removeClass('disabled');
						const response = xhr.responseJSON || {};
						Swal.fire('Permintaan belum dibatalkan', response.message || 'Terjadi kesalahan. Coba lagi.', 'error');
					}
				});
			});
		});
	</script>

	<!-- Menagtur Auto Scroll -->
	<script>
		// 		// Mengatur auto-scroll pada kontainer
		// 		const scrollContainer = document.getElementById('scrollContainer');
		// 		let scrollAmount = 0;

		// 		function autoScroll() {
		// 			scrollAmount += 380; // Jarak scroll dalam piksel
		// 			if (scrollAmount >= scrollContainer.scrollWidth - scrollContainer.clientWidth) {
		// 				scrollAmount = 0; // Kembali ke awal jika sudah mencapai akhir
		// 			}
		// 			scrollContainer.scrollTo({
		// 				left: scrollAmount,
		// 				behavior: 'smooth'
		// 			});
		// 		}

		// 		// Mengulangi scroll setiap 3 detik
		// 		setInterval(autoScroll, 3000);
		document.addEventListener("DOMContentLoaded", function() {
			const scrollContainer = document.getElementById('scrollContainer');

			if (!scrollContainer) {
				return;
			}

			let scrollAmount = 0;

			function autoScroll() {
				if (!scrollContainer) return;

				scrollAmount += 380; // Jarak scroll dalam piksel

				if (scrollAmount >= scrollContainer.scrollWidth - scrollContainer.clientWidth) {
					scrollAmount = 0; // Kembali ke awal jika sudah mencapai akhir
				}

				scrollContainer.scrollTo({
					left: scrollAmount,
					behavior: 'smooth'
				});
			}

			// Mengulangi scroll setiap 3 detik
			setInterval(autoScroll, 3000);
		});
	</script>

	<!-- Mengirim lokasi -->
	<script>
		const mapProvider = <?= json_encode($map_provider); ?>;
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;
		const mapboxPublicToken = <?= json_encode($mapbox_public_token); ?>;

		function getFirebaseDatabase() {
			const noopRef = {
				on: function() {},
				child: function() {
					return noopRef;
				},
				remove: function() {
					return Promise.resolve();
				},
				push: function() {
					return noopRef;
				},
				set: function() {
					return Promise.resolve();
				}
			};

			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return {
					ref: function() {
						return noopRef;
					}
				};
			}

			try {
				return firebase.database();
			} catch (error) {
				return {
					ref: function() {
						return noopRef;
					}
				};
			}
		}

		let map;
		let marker;
		let geocoder;

		function formatCoordinate(value) {
			const number = Number(value);
			return Number.isFinite(number) ? number.toFixed(6) : '';
		}

		function setHeaderCoordinate(location) {
			const coordinateEl = document.getElementById('userCoordinateLabel');
			if (!coordinateEl) {
				return;
			}
			const lat = location && formatCoordinate(location.lat);
			const lng = location && formatCoordinate(location.lng);
			coordinateEl.textContent = lat && lng ? 'Lat ' + lat + ' · Lng ' + lng : 'Lokasi belum ditemukan. Coba lagi.';
		}

		function setLocationFields(location) {
			const latitudeEl = document.getElementById("latitude");
			const longitudeEl = document.getElementById("longitude");
			const latitudexEl = document.getElementById("latitudex");
			const longitudexEl = document.getElementById("longitudex");

			if (latitudeEl) latitudeEl.value = location.lat;
			if (longitudeEl) longitudeEl.value = location.lng;
			if (latitudexEl) latitudexEl.value = location.lat;
			if (longitudexEl) longitudexEl.value = location.lng;
			setHeaderCoordinate(location);
		}

		function setLocationText(address, kota) {
			const addressEl = document.getElementById("userLocationAddress");
			const kotaEl = document.getElementById('kota');

			if (addressEl) addressEl.innerHTML = address || "Lokasi belum tersedia";
			if (kotaEl) kotaEl.textContent = kota || "Lokasi belum tersedia";
		}

		function getMapboxCity(feature) {
			if (!feature) return '';

			if (feature.place_type && feature.place_type.includes('place')) {
				return feature.text || '';
			}

			const context = feature.context || [];
			const city = context.find(item => item.id && item.id.indexOf('place.') === 0) ||
				context.find(item => item.id && item.id.indexOf('district.') === 0);

			return city ? city.text : '';
		}

		function getMapboxAddress(location) {
			if (mapProvider !== 'mapbox' || !mapboxPublicToken || !window.fetch) {
				setLocationText(location.lat + ', ' + location.lng, '');
				return;
			}

			const url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' +
				encodeURIComponent(location.lng + ',' + location.lat) +
				'.json?access_token=' + encodeURIComponent(mapboxPublicToken);

			fetch(url)
				.then(response => {
					if (!response.ok) {
						throw new Error('Mapbox geocoding failed');
					}
					return response.json();
				})
				.then(data => {
					const feature = data.features && data.features.length ? data.features[0] : null;
					setLocationText(feature ? feature.place_name : 'Alamat belum ditemukan.', getMapboxCity(feature));
				})
				.catch(() => {
					setLocationText(location.lat + ', ' + location.lng, '');
				});
		}

		function initMap() {
			if (mapProvider !== 'google' || !window.google || !google.maps) {
				setLocationText('', '');

				if (navigator.geolocation) {
					navigator.geolocation.watchPosition(function(position) {
						const newLocation = {
							lat: position.coords.latitude,
							lng: position.coords.longitude,
						};

						setLocationFields(newLocation);
						getAddress(newLocation);
					}, showError);
				}
				return;
			}

			// Inisialisasi peta
			const initialLocation = {
				lat: -6.1751,
				lng: 106.8650
			}; // Lokasi awal (Jakarta)
			map = new google.maps.Map(document.getElementById("map"), {
				zoom: 15,
				center: initialLocation,
			});

			marker = new google.maps.Marker({
				position: initialLocation,
				map: map,
			});

			geocoder = new google.maps.Geocoder();

			// Mendapatkan lokasi pengguna
			if (navigator.geolocation) {
				navigator.geolocation.watchPosition(updateLocation, showError);
			} else {
				Swal.fire({
					title: "Lokasi belum tersedia",
					text: "Fitur lokasi tidak tersedia di perangkat ini.",
					icon: "error",
					confirmButtonText: "Tutup"
				});
			}
		}

		function updateLocation(position) {
			const newLocation = {
				lat: position.coords.latitude,
				lng: position.coords.longitude,
			};

			// Update posisi marker dan pusat peta
			marker.setPosition(newLocation);
			map.setCenter(newLocation);

			// Tampilkan latitude dan longitude
			setLocationFields(newLocation);

			// Mendapatkan alamat dengan Geocoder
			getAddress(newLocation);

		}

		function sendData() {
			if (mapProvider !== 'google') {
				document.querySelectorAll('.hasil').forEach((element) => {
					element.textContent = '-';
				});
				return;
			}

			// Ambil nilai dari input
			const latitude = document.getElementById('latitude').value;
			const longitude = document.getElementById('longitude').value;

			// Kirim data ke PHP menggunakan fetch
			fetch('<?= base_url('home/getDuration') ?>', { // Kirim ke halaman yang sama
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded'
					},
					body: 'latitude=' + encodeURIComponent(latitude) + '&longitude=' + encodeURIComponent(longitude)
				})
				.then(response => response.json())
				.then(result => {
					// Tampilkan respon dari PHP
					const results = document.querySelectorAll('.hasil');
					const heroCards = document.querySelectorAll('.hero-card'); // Pastikan setiap pahlawan ada di elemen dengan class ini
					const badges = document.querySelectorAll('.badge.rounded-pill.status'); // Ambil elemen badge "Available"

					// const times = result.split(/\s+/);
					// results.forEach((index) => {
					// 	index.textContent = result + ' From You';
					// });
					results.forEach((element, index) => {
						if (result[index]) { // Pastikan ada data untuk elemen ini
							// element.textContent = result[index].time + ' From You';
							const timeText = result[index].time; // Contoh: "22 mins"
							element.textContent = timeText + " dari lokasi Anda";

							// Ambil angka durasi dari string (misalnya "22 mins" -> 22)
							const duration = parseInt(timeText);

							if (duration > 60) {
								setNotAvailable(heroCards[index], badges[index]);
							}
						} else {
							element.textContent = '-';
							setNotAvailable(heroCards[index], badges[index]);
						}
					});

				})
				.catch(error => {});

			// Fungsi untuk mengubah status menjadi "Not Available"
			function setNotAvailable(heroCard, badge) {
				if (!heroCard || !badge) return;

				const link = heroCard.closest('.card').querySelector('.stretched-link');

				heroCard.classList.add('disabled');
				heroCard.style.pointerEvents = 'none';
				heroCard.style.opacity = '0.5';
				heroCard.style.cursor = 'not-allowed';

				// Ganti teks "Available" menjadi "Not Available"
				badge.textContent = "Belum tersedia";
				badge.classList.remove('bg-success');
				badge.classList.add('bg-danger');

				// Hapus link agar tidak bisa diklik
				if (link) {
					link.remove();
				}

				// Tambahkan event agar user tidak bisa klik
				heroCard.addEventListener('click', function(event) {
					event.preventDefault();
					event.stopPropagation();
					Swal.fire({
						title: 'Nakes belum tersedia',
						text: 'Pilih Nakes lain.',
						icon: 'warning',
						confirmButtonText: 'Tutup'
					});
				}, true);
			}

		}

		function getAddress(location) {
			if (mapProvider === 'mapbox') {
				getMapboxAddress(location);
				return;
			}

			if (!geocoder) {
				setLocationText(location.lat + ', ' + location.lng, '');
				return;
			}

			geocoder.geocode({
				location: location
			}, (results, status) => {
				if (status === "OK") {
					if (results[0]) {
						setLocationText(results[0].formatted_address, '');

						const components = results[0].address_components;
						const city = components.find(c => c.types.includes("locality")) ||
							components.find(c => c.types.includes("administrative_area_level_2"));
						document.getElementById('kota').textContent = city ? city.long_name : "Tidak ditemukan";
					} else {
						setLocationText('Alamat belum ditemukan.', '');
					}
				} else {
					setLocationText('Alamat belum ditemukan.', '');
				}
			});
		}

		function showError(error) {
			switch (error.code) {
				// case error.PERMISSION_DENIED:
				// 	alert("User denied the request for Geolocation.");
				// 	break;
				case error.POSITION_UNAVAILABLE:
					setLocationText('Lokasi belum ditemukan. Coba lagi.', 'Lokasi belum ditemukan. Coba lagi.');
					setHeaderCoordinate(null);
					break;
				case error.TIMEOUT:
					setLocationText('Lokasi belum ditemukan. Coba lagi.', 'Lokasi belum ditemukan. Coba lagi.');
					setHeaderCoordinate(null);
					break;
				case error.UNKNOWN_ERROR:
					setLocationText('Lokasi belum ditemukan. Coba lagi.', 'Lokasi belum ditemukan. Coba lagi.');
					setHeaderCoordinate(null);
					break;
			}
		}


		// window.onload = initMap;
		window.onload = function() {
			initMap();
			// sendData();
			// setInterval(function() {
			// 	sendData();
			// }, 2000);
			setTimeout(function() {
				sendData();
			}, 1000);
		}

		// Tambahkan ini di akhir file JavaScript Anda
		if (typeof Flutter !== 'undefined') {
			Flutter.postMessage('ready');
		}
	</script>

	<!-- validasi sebelum konsultasi -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			const cards = document.querySelectorAll('.clickable-card');

			cards.forEach(card => {
				card.addEventListener('click', function() {
					const userIdWarga = <?= $this->session->userdata('id'); ?>;
					const tanggal_loc = card.getAttribute('data-tanggal_loc');
					const link = card.getAttribute('data-link');

					const jumlah = document.getElementById('jumlah').value;
					// const uid = document.getElementById('uid').value;

					const today = new Date().toLocaleDateString('id-ID', {
						timeZone: 'Asia/Jakarta',
						year: 'numeric',
						month: '2-digit',
						day: '2-digit'
					}).split('/').reverse().join('-'); // format YYYY-MM-DD

					const uids = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'user_id')) ?>;
					const tgl = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'date')) ?>;
					const stats = <?= json_encode(array_column($getAllRequestPendingAccept->result_array(), 'request_status')) ?>;

					const userIdToCheck = String(userIdWarga);

					const kotaCilegon = document.getElementById('kota').textContent;
					if (!['Cilegon', 'Kota Cilegon'].includes(kotaCilegon)) {
						// buatkan alert yang dengan swal
						Swal.fire({
							title: 'Di luar area layanan',
							text: 'Konsultasi hanya tersedia di Kota Cilegon.',
							icon: 'warning',
							confirmButtonText: 'Tutup'
						}).then((result) => {
							if (result.isConfirmed) {
							}
						})
						return;
					}

					// jika tanggal tidak ada isinya
					if (tanggal_loc === null || tanggal_loc === '') {
						// Tanggal sudah lewat
						Swal.fire({
							title: 'Nakes belum tersedia',
							text: 'Pilih Nakes lain.',
							icon: 'warning',
							confirmButtonText: 'Tutup'
						});
						return;
					}

					if (uids.includes(userIdToCheck) === false) {
						if (jumlah > 100) {
							// Jika userIdWarga tidak sama dengan userIdPasien
							Swal.fire({
								title: 'Batas permintaan tercapai',
								text: 'Batas permintaan sudah tercapai.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
							return;
						} else {
							// Jika userIdPasien sama dengan id_user
							window.location.href = link;
							// return;
							// alert("ada");
						}
					} else if (uids.includes(userIdToCheck) === true && stats.includes('Completed')) {
						if (tgl == today) {
							Swal.fire({
								title: 'Konsultasi belum tersedia',
								text: 'Konsultasi baru belum tersedia hari ini.',
								icon: 'warning',
								confirmButtonText: 'Tutup'
							});
						} else {
							window.location.href = link;
						}
					} else {
						window.location.href = link;
					}
				});
			});
		});
	</script>

	<!-- Notifikasi Chat -->
	<script>
		const notificationListJsonUrl = <?= json_encode(base_url('notifikasi/list_json')); ?>;
		const notificationMarkReadUrl = <?= json_encode(base_url('notifikasi/mark_read')); ?>;
		const notificationPollIntervalMs = 30000;
		const notificationMarkReadInFlight = new Set();

		function getNotificationOpenErrorElement() {
			let errorElement = document.getElementById('notificationOpenError');
			if (errorElement) {
				return errorElement;
			}

			const notificationList = document.getElementById('notificationList');
			if (!notificationList || !notificationList.parentNode) {
				return null;
			}

			errorElement = document.createElement('div');
			errorElement.id = 'notificationOpenError';
			errorElement.className = 'alert alert-danger py-2 px-3 mb-2 small';
			errorElement.setAttribute('role', 'alert');
			errorElement.hidden = true;
			notificationList.parentNode.insertBefore(errorElement, notificationList);
			return errorElement;
		}

		function setNotificationOpenError(message) {
			const errorElement = getNotificationOpenErrorElement();
			if (!errorElement) {
				return;
			}
			errorElement.textContent = message || '';
			errorElement.hidden = !message;
		}

		function getNotificationBadge() {
			return Array.prototype.slice.call(document.querySelectorAll('#badgeNotif, #badgeNotifs'));
		}

		function setNotificationCount(count) {
			const badges = getNotificationBadge();
			const safeCount = Math.max(0, parseInt(count || 0, 10));
			if (!badges.length) {
				return;
			}
			badges.forEach(function(badge) {
				if (safeCount > 0) {
					badge.style.display = 'inline-flex';
					badge.textContent = safeCount > 99 ? '99' + '+' : safeCount;
				} else {
					badge.style.display = 'none';
					badge.textContent = '';
				}
			});
		}

		function syncNotificationCountFromList(notificationList) {
			if (!notificationList) {
				return;
			}
			setNotificationCount(notificationList.querySelectorAll('.notification-item').length);
		}

		function renderNotificationItem(item) {
			const notificationItem = document.createElement('div');
			notificationItem.className = 'notification-item dl-notification-item';

			const icon = document.createElement('i');
			icon.className = 'bi bi-bell-fill dl-notification-icon';
			notificationItem.appendChild(icon);

			const content = document.createElement('div');
			content.className = 'dl-notification-content';

			const title = document.createElement('strong');
			title.textContent = item.title || 'Notifikasi';
			content.appendChild(title);

			const message = document.createElement('small');
			message.textContent = item.message || 'Detail belum tersedia.';
			content.appendChild(message);

			const timestamp = document.createElement('small');
			timestamp.className = 'dl-notification-time';
			timestamp.textContent = item.created_at ? new Date(item.created_at.replace(' ', 'T')).toLocaleString('id-ID') : '';
			content.appendChild(timestamp);

			notificationItem.appendChild(content);
			notificationItem.addEventListener('click', function(event) {
				event.preventDefault();
				markNotificationRead(item, notificationItem);
			});

			return notificationItem;
		}

		function renderDatabaseNotifications(items, count) {
			const notificationList = document.getElementById('notificationList');
			if (!notificationList) {
				return;
			}
			notificationList.innerHTML = '';
			if (!items || !items.length) {
				notificationList.innerHTML = '<div class="dl-notification-empty"><i class="bi bi-bell"></i><span>Belum ada notifikasi.</span></div>';
				setNotificationCount(0);
				return;
			}
			items.forEach(function(item) {
				notificationList.appendChild(renderNotificationItem(item));
			});
			syncNotificationCountFromList(notificationList);
		}

		function loadDatabaseNotifications() {
			if (document.visibilityState === 'hidden') {
				return;
			}
			fetch(notificationListJsonUrl, {
					credentials: 'same-origin'
				})
				.then(function(response) {
					if (!response.ok) {
						throw new Error('notification_load_failed');
					}
					return response.json();
				})
				.then(function(data) {
					if (!data || data.status !== 'success') {
						return;
					}
					renderDatabaseNotifications(data.notifications || [], parseInt(data.unread_count || 0, 10));
				})
				.catch(function() {});
		}

		function startDatabaseNotificationPolling() {
			if (window.doclincNotificationPollingStarted) {
				return;
			}
			window.doclincNotificationPollingStarted = true;
			loadDatabaseNotifications();
			window.setInterval(loadDatabaseNotifications, notificationPollIntervalMs);
			document.addEventListener('visibilitychange', function() {
				if (document.visibilityState === 'visible') {
					loadDatabaseNotifications();
				}
			});
		}

		function markNotificationRead(notification, notificationElement) {
			const notificationId = notification && notification.notification_id ? notification.notification_id : notification;
			const actionUrl = notification && notification.action_url ? notification.action_url : '';
			const notificationKey = notificationId === null || typeof notificationId === 'undefined' ? '' : String(notificationId).trim();
			const isValidNotificationId = (typeof notificationId === 'string' || typeof notificationId === 'number') && /^[1-9]\d*$/.test(notificationKey);
			if (!isValidNotificationId || notificationMarkReadInFlight.has(notificationKey)) {
				return;
			}
			notificationMarkReadInFlight.add(notificationKey);
			if (notificationElement) {
				notificationElement.setAttribute('aria-busy', 'true');
			}
			const formData = new FormData();
			formData.append('notification_id', notificationId);
			fetch(notificationMarkReadUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) {
					return response.json().then(function(body) {
						const isObject = body && typeof body === 'object' && !Array.isArray(body);
						if (!response.ok || !isObject || body.status !== 'success') {
							const failure = new Error('notification_mark_read_failed');
							if (isObject && typeof body.message === 'string' && body.message.trim()) {
								failure.safeMessage = body.message.trim();
							}
							throw failure;
						}

						setNotificationOpenError('');
						if (actionUrl) {
							window.location.href = actionUrl;
							return;
						}
						loadDatabaseNotifications();
					});
				})
				.catch(function(error) {
					const safeMessage = error && typeof error.safeMessage === 'string' && error.safeMessage.trim() ? error.safeMessage.trim() : '';
					setNotificationOpenError(safeMessage || 'Notifikasi belum dapat dibuka. Coba lagi.');
				})
				.finally(function() {
					notificationMarkReadInFlight.delete(notificationKey);
					if (notificationElement) {
						notificationElement.removeAttribute('aria-busy');
					}
				});
		}

		document.addEventListener('DOMContentLoaded', startDatabaseNotificationPolling);
		document.addEventListener('DOMContentLoaded', function() {
			const highlightRequestId = new URLSearchParams(window.location.search).get('highlight_request_id');
			if (!highlightRequestId) {
				return;
			}
			document.querySelectorAll('[data-request-id]').forEach(function(card) {
				if (card.getAttribute('data-request-id') === highlightRequestId) {
					card.classList.add('border', 'border-success', 'border-2');
					card.scrollIntoView({
						behavior: 'smooth',
						block: 'center'
					});
				}
			});
		});
	</script>

	<!-- simpan lokasi ke firebase -->
	<script>
		const waktu = getFirebaseDatabase().ref('location');
		const latitudesEl = document.getElementById('latitudes');
		const longitudesEl = document.getElementById('longitudes');
		const lats = latitudesEl ? parseFloat(latitudesEl.value) : null;
		const lngs = longitudesEl ? parseFloat(longitudesEl.value) : null;

		let estimasiLat;
		let estimasiLng;

		waktu.on('value', (snapshot) => {
			if (lats === null || lngs === null) {
				return;
			}

			const data = snapshot.val();
			estimasiLat = '';
			estimasiLng = '';

			for (const key in data) {
				estimasiLat += data[key].latitude;
				estimasiLng += data[key].longitude;
			}

			const origin = {
				lat: parseFloat(estimasiLat),
				lng: parseFloat(estimasiLng)
			};

			if (mapProvider !== 'google' || !window.google || !google.maps) {
				return;
			}

			const destination = new google.maps.LatLng(lats, lngs);
			const service = new google.maps.DistanceMatrixService();


			service.getDistanceMatrix({
				origins: [origin],
				destinations: [destination],
				travelMode: google.maps.TravelMode.DRIVING,
				avoidHighways: false,
				avoidTolls: false
			}, function(response, status) {
				if (status === "OK") {
					const result = response.rows[0].elements[0];
					document.getElementById("estimasi").innerHTML = result.duration.text;
				} else {
					alert("Error: " + status);
				}
			});


		});
	</script>

	<!-- popup untuk accepted-->
	<script>
		function closePopup() {
			const popup = document.getElementById("acceptedPopup");
			if (popup) popup.style.display = "none";
		}

		// Auto-close setelah 5 detik
		setTimeout(closePopup, 5000);
	</script>

	<!-- Download resep obat -->
	<!-- <script>
		function downloadCard(cardId) {
			const element = document.getElementById(cardId);
			const opt = {
				margin: 0.3,
				filename: 'resep-' + cardId + '.pdf',
				image: {
					type: 'jpeg',
					quality: 0.98
				},
				html2canvas: {
					scale: 2
				},
				jsPDF: {
					unit: 'cm',
					format: 'a5',
					orientation: 'portrait'
				}
			};
			html2pdf().set(opt).from(element).save();
		}
	</script> -->
	<script>
		function downloadCard(cardId) {
			// Ambil elemen resep
			var cardContent = document.getElementById(cardId).innerHTML;

			// Buat jendela baru untuk mencetak
			var printWindow = window.open('', '', 'height=600,width=800');

			printWindow.document.write(`
    <html>
    <head>
      <title>Resep Dokter</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          padding: 20px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
        }
        th, td {
          padding: 8px;
          border: 1px solid #ccc;
        }
        th {
          background-color: #f8f8f8;
        }
        h5 {
          text-align: center;
        }
        .btn, .badge {
          display: none !important;
        }
      </style>
    </head>
    <body>
      ${cardContent}
    </body>
    </html>
  `);

			printWindow.document.close(); // Tutup dokumen
			printWindow.focus(); // Fokus ke jendela

			printWindow.print(); // Jalankan perintah print
			printWindow.close(); // Tutup jendela setelah cetak
		}
	</script>


	<!-- tampil notif dari nakes -->
	<script>
		var request_id = <?= json_encode($id_request ?? ''); ?>;

		// Ambil data dari node 'notif'
		const notifRef = getFirebaseDatabase().ref("notiffromdoc");

		// Dengarkan perubahan data notifikasi
		notifRef.on("value", (snapshot) => {
			const data = snapshot.val();
			if (!data) return;

			for (let id in data) {
				const notif = data[id];
				const statusNotif = document.getElementById("statusNotif");
				if (notif.status === "Nakes Menuju Lokasi" && notif.id_req === request_id) {
					if (statusNotif) {
						statusNotif.innerHTML = "Dalam perjalanan";
					}
					// Tampilkan popup
					showPopup();
					break; // tampilkan hanya sekali jika ada
				} else if (notif.id_req !== request_id) {
					if (statusNotif) {
						statusNotif.innerHTML = "Menunggu antrean";
					}
				}
			}
		});

		function showPopup() {
			const popup = document.getElementById("acceptedPopup");
			popup.style.display = "block";

			// Sembunyikan otomatis setelah 10 detik (opsional)
			setTimeout(() => {
				popup.style.display = "none";
			}, 10000);
		}

		function closePopup() {
			document.getElementById("acceptedPopup").style.display = "none";
		}
	</script>

	<!-- rating dokter -->
	<script>
		var idUsers = "<?php echo $_SESSION['id']; ?>";
		const reqIdRat = document.getElementById("reqIdRat");
		var request_id = reqIdRat ? reqIdRat.value : "";
		const profileUploadUrl = <?= json_encode(base_url('uploads/profile/')); ?>;
		const defaultProfileUrl = <?= json_encode(base_url('assets/doclinc/img/default-profile.png')); ?>;

		function safeProfileImageUrl(path) {
			path = String(path || '').trim();

			if (!path || /[<>"']/.test(path) || /(?:javascript|data)\s*:/i.test(path)) {
				return defaultProfileUrl;
			}

			path = path.replace(/\\/g, '/').replace(/^\/+/, '');

			if (path.indexOf('..') !== -1 || !/^[A-Za-z0-9._/-]+$/.test(path)) {
				return defaultProfileUrl;
			}

			return profileUploadUrl + path.split('/').map(encodeURIComponent).join('/');
		}
		// Ambil data dari node 'notif'
		const notifRate = getFirebaseDatabase().ref("rating");

		let alreadyShown = false;

		notifRate.on("value", (snapshot) => {
			if (alreadyShown) return;

			const data = snapshot.val();
			if (!data) return;

			for (let id in data) {
				const notif = data[id];

				if (notif.idUser === idUsers && notif.idReq === request_id) {
					alreadyShown = true; // mencegah tampil ulang
					const idDokter = notif.idDokter;

					$.ajax({
						url: "<?= base_url('home/getDokterRating') ?>",
						type: "POST",
						data: {
							id_dokter: idDokter
						},
						dataType: "json",
						success: function(response) {
							const dataDokter = response[0];

							document.getElementById('gambarDokter').src = safeProfileImageUrl(dataDokter.foto);
							document.getElementById('namaDokter').textContent = dataDokter.nama;
							document.getElementById('dokIds').value = idDokter;

							showPopupRate();
						}
					});

					break;
				}
			}
		});

		function showPopupRate() {
			const popupRatingModal = new bootstrap.Modal(document.getElementById('ratingModal'));
			popupRatingModal.show();
		}
	</script>

	<!-- submit rating -->
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.getElementById('ratingForm').addEventListener('submit', function(e) {
				e.preventDefault();

				// Lakukan submit rating ke server via AJAX
				const idUser = this.iduser.value;
				const idDokter = this.id_dokter.value;
				const rating = this.rating.value;

				$.ajax({
					url: '<?= base_url('home/submit_rating'); ?>',
					type: 'POST',
					data: {
						id_user: idUser,
						id_dokter: idDokter,
						rating: rating
					},
					dataType: 'json',
					success: function(data) {
						if (data.status === 'success') {

							const deleteRate = getFirebaseDatabase().ref('rating');
							deleteRate.on('value', (snapshot) => {
								const data = snapshot.val();
								if (data) {
									for (const key in data) {
										const item = data[key];
										if (item.idUser === idUser && item.idReq === request_id) {
											deleteRate.child(key).remove();
										}
									}
								}
							});

							Swal.fire({
								title: 'Penilaian disimpan.',
								text: 'Penilaian disimpan.',
								icon: 'success',
								confirmButtonText: 'Lihat riwayat'
							}).then(() => {
								const ratingModal = bootstrap.Modal.getInstance(document.getElementById('ratingModal'));
								ratingModal.hide();
								window.location.href = 'home#riwayat_konsul_selesai';
							});
						} else {
							Swal.fire({
								title: 'Penilaian belum tersimpan',
								text: 'Terjadi kesalahan. Coba lagi.',
								icon: 'error',
								confirmButtonText: 'Tutup'
							});
						}
					},
					error: function(xhr, status, error) {
						Swal.fire({
							title: 'Penilaian belum tersimpan',
							text: 'Terjadi kesalahan. Coba lagi.',
							icon: 'error',
							confirmButtonText: 'Tutup'
						});
					}
				});

			});
		});
	</script>

	<!-- mendapatkan url -->
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			const hash = window.location.hash;

			if (hash === "#riwayat" || hash === "#riwayat_konsul_selesai") {
				if (typeof showContent === 'function') {
					showContent('riwayat');
				}

				setTimeout(() => {
					let tabID = (hash === "#riwayat_konsul_selesai") ? "#selesai-tab" : "#proses-tab";
					const tabTrigger = document.querySelector(tabID);
					if (tabTrigger) {
						const tab = new bootstrap.Tab(tabTrigger);
						tab.show();
					}
				}, 300);
			}
		});
	</script>

	<?php if ($this->session->userdata('role') === 'warga') : ?>
		<script>
			window.DoclincIncomingCallWatcher = {
				enabled: true,
				incomingUrl: <?= json_encode(base_url('home/livekit_incoming_call')); ?>,
				rejectUrl: <?= json_encode(base_url('home/reject_livekit_call')); ?>,
				chatUrl: <?= json_encode(base_url('chat')); ?>,
				pollMs: 3000
			};
		</script>
		<script src="<?= html_escape(base_url('assets/js/doclinc-livekit-incoming-watcher.js')); ?>"></script>
	<?php endif; ?>
	<?php if ($clinical_suggestions_enabled) : ?>
		<script src="<?= html_escape(base_url('assets/js/doclinc-clinical-suggestions.js')); ?>"></script>
	<?php endif; ?>

</body>

</html>
