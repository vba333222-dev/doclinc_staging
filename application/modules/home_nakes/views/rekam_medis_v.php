<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$r = isset($rekam['request']) && is_array($rekam['request']) ? $rekam['request'] : array();
$p = isset($rekam['patient']) && is_array($rekam['patient']) ? $rekam['patient'] : array();
$rec = isset($rekam['record']) && is_array($rekam['record']) ? $rekam['record'] : array();
$eff = isset($rekam['effective']) && is_array($rekam['effective']) ? $rekam['effective'] : array();
$dxs = isset($rekam['diagnoses']) && is_array($rekam['diagnoses']) ? $rekam['diagnoses'] : array();
$vitals = isset($rekam['vitals']) && is_array($rekam['vitals']) ? $rekam['vitals'] : array();
$visit = isset($rekam['visit_result']) && is_array($rekam['visit_result']) ? $rekam['visit_result'] : array();
$disp = isset($rekam['disposition']) && is_array($rekam['disposition']) ? $rekam['disposition'] : array();
$amends = isset($rekam['amendments']) && is_array($rekam['amendments']) ? $rekam['amendments'] : array();

$rekam_field = static function ($key) use ($eff, $rec) {
	if (array_key_exists($key, $eff) && trim((string) $eff[$key]) !== '') {
		return trim((string) $eff[$key]);
	}
	if (array_key_exists($key, $rec) && trim((string) $rec[$key]) !== '') {
		return trim((string) $rec[$key]);
	}
	return '';
};
$rekam_time = static function ($value) {
	$value = trim((string) $value);
	if ($value === '') {
		return '-';
	}
	$time = strtotime($value);
	if ($time === false) {
		return '-';
	}
	$bulan = array(1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des');
	return date('j', $time) . ' ' . $bulan[(int) date('n', $time)] . ' ' . date('Y', $time) . ' · ' . date('H.i', $time);
};
$rekam_json_lines = static function ($json) {
	$data = json_decode((string) $json, true);
	if (!is_array($data)) {
		return array();
	}
	$lines = array();
	foreach ($data as $key => $value) {
		if (is_array($value)) {
			$value = implode(', ', array_map('strval', $value));
		}
		$label = is_string($key) ? $key : '';
		$lines[] = ($label !== '' ? $label . ': ' : '') . trim((string) $value);
	}
	return $lines;
};

$patient_name = isset($p['nama']) ? (string) $p['nama'] : (isset($r['patient_name']) ? (string) $r['patient_name'] : 'Warga');
$patient_nik = isset($p['nik']) ? (string) $p['nik'] : '-';
$mode_label = function_exists('doclinc_consultation_mode_label')
	? doclinc_consultation_mode_label(isset($r['consultation_mode']) ? $r['consultation_mode'] : '')
	: (string) (isset($r['consultation_mode']) ? $r['consultation_mode'] : '-');
$queue_label = trim((string) (isset($r['queue_code']) ? $r['queue_code'] : ''));
$service_time = !empty($r['completed_at']) ? $r['completed_at'] : (isset($rec['clinical_finalized_at']) ? $rec['clinical_finalized_at'] : '');
$has_record = !empty($rec['record_id']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekam medis · <?= html_escape($patient_name); ?> · DokLinc</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-design-system.css')); ?>">
<link rel="stylesheet" href="<?= html_escape(base_url('assets/css/doclinc-rekam-medis.css')); ?>">
</head>
<body class="dl-record-body">
	<div class="dl-record-bar">
		<a class="btn btn-outline-success" href="<?= html_escape(site_url('home_nakes') . '#riwayat_konsul'); ?>">&larr; Kembali ke riwayat</a>
		<button class="btn btn-success" type="button" onclick="window.print(); return false;">Cetak rekam medis</button>
	</div>

	<main class="dl-record-sheet">
		<header>
			<p class="dl-record-eyebrow">Rekam medis</p>
			<h1 class="dl-record-title"><?= html_escape($patient_name); ?></h1>
			<p class="dl-record-sub">
				<span class="dl-record-mono">NIK <?= html_escape($patient_nik); ?></span>
				· <?= html_escape($mode_label); ?>
				<?php if ($queue_label !== '') : ?>· <span class="dl-record-mono"><?= html_escape($queue_label); ?></span><?php endif; ?>
				· <?= html_escape($rekam_time($service_time)); ?>
			</p>
			<?php if ($has_record) : ?>
				<p class="dl-record-flag">
					Rekam #<span class="dl-record-mono"><?= html_escape((string) $rec['record_id']); ?></span>
					<?php if (!empty($rec['clinical_finalized_at'])) : ?>
						· dikunci sejak <?= html_escape($rekam_time($rec['clinical_finalized_at'])); ?>
					<?php else : ?>
						· belum difinalisasi
					<?php endif; ?>
					· setiap pembukaan dicatat
				</p>
			<?php endif; ?>
		</header>

		<section class="dl-record-section" aria-labelledby="rm-identitas">
			<h2 id="rm-identitas">Identitas pasien</h2>
			<dl class="dl-record-grid">
				<dt>Nama</dt><dd><?= html_escape($patient_name); ?></dd>
				<dt>NIK</dt><dd class="dl-record-mono"><?= html_escape($patient_nik); ?></dd>
				<dt>Tanggal lahir</dt><dd class="dl-record-mono"><?= html_escape(!empty($p['tgl_lahir']) ? (string) $p['tgl_lahir'] : '-'); ?></dd>
				<dt>Jenis kelamin</dt><dd><?= html_escape(!empty($p['gender']) ? ((string) $p['gender'] === 'L' ? 'Laki-laki' : 'Perempuan') : '-'); ?></dd>
				<dt>Nomor KK</dt><dd class="dl-record-mono"><?= html_escape(!empty($p['nomor_kk']) ? (string) $p['nomor_kk'] : '-'); ?></dd>
				<dt>BPJS/KIS</dt><dd class="dl-record-mono"><?= html_escape(!empty($p['nomor_bpjs_kis']) ? (string) $p['nomor_bpjs_kis'] : '-'); ?></dd>
				<dt>Kontak</dt><dd><?= html_escape(!empty($p['no_hp']) ? (string) $p['no_hp'] : '-'); ?></dd>
				<dt>Alamat</dt><dd><?= html_escape(!empty($p['alamat']) ? (string) $p['alamat'] : '-'); ?></dd>
			</dl>
		</section>

		<section class="dl-record-section" aria-labelledby="rm-kunjungan">
			<h2 id="rm-kunjungan">Kunjungan</h2>
			<dl class="dl-record-grid">
				<dt>Selesai</dt><dd class="dl-record-mono"><?= html_escape($rekam_time($service_time)); ?></dd>
				<dt>Mode</dt><dd><?= html_escape($mode_label !== '' ? $mode_label : '-'); ?></dd>
				<dt>Puskesmas</dt><dd><?= html_escape(!empty($r['assigned_puskesmas_name']) ? (string) $r['assigned_puskesmas_name'] : '-'); ?></dd>
				<dt>Lokasi</dt><dd><?= html_escape(!empty($r['location']) ? (string) $r['location'] : '-'); ?></dd>
				<dt>Nomor antrean</dt><dd class="dl-record-mono"><?= html_escape($queue_label !== '' ? $queue_label : '-'); ?></dd>
			</dl>
		</section>


		<?php if (!$has_record) : ?>
			<section class="dl-record-section">
				<h2>Isi rekam medis</h2>
				<p class="dl-record-empty">Permintaan ini selesai tanpa catatan klinis. Tidak ada isi yang ditampilkan, dan tidak ada kode diagnosis yang dibuat-buat.</p>
			</section>
		<?php else : ?>

		<section class="dl-record-section" aria-labelledby="rm-anamnesis">
			<h2 id="rm-anamnesis">Anamnesis</h2>
			<?php $anamnesis = $rekam_field('anamnesis'); ?>
			<?php if ($anamnesis !== '') : ?>
				<p class="dl-record-text"><?= html_escape($anamnesis); ?></p>
			<?php else : ?>
				<p class="dl-record-empty">Belum diisi.</p>
			<?php endif; ?>
		</section>

		<section class="dl-record-section" aria-labelledby="rm-diagnosis">
			<h2 id="rm-diagnosis">Diagnosis (ICD-10)</h2>
			<?php if (empty($dxs)) : ?>
				<p class="dl-record-empty">Belum ada diagnosis terkode pada rekam ini.</p>
			<?php else : ?>
				<table class="dl-record-table">
					<thead>
						<tr>
							<th scope="col">#</th>
							<th scope="col">Peran</th>
							<th scope="col">Kode</th>
							<th scope="col">Label</th>
							<th scope="col">Status</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($dxs as $dx) : ?>
							<tr>
								<td class="dl-record-mono"><?= html_escape((string) $dx['position']); ?></td>
								<td><?= html_escape($dx['diagnosis_role'] === 'primary' ? 'Primer' : 'Sekunder'); ?></td>
								<td class="dl-record-mono"><?= html_escape(!empty($dx['diagnosis_code']) ? (string) $dx['diagnosis_code'] : '-'); ?></td>
								<td><?= html_escape((string) $dx['diagnosis_label']); ?></td>
								<td>
									<?php $coded = isset($dx['coding_state']) && $dx['coding_state'] === 'coded'; ?>
									<span class="dl-record-state <?= $coded ? 'dl-record-state--coded' : 'dl-record-state--uncoded'; ?>"><?= $coded ? 'Terkode' : 'Belum dikode'; ?></span>
									<?php if (!empty($dx['reference_source'])) : ?>
										<br><small class="dl-record-mono"><?= html_escape((string) $dx['reference_source']); ?> <?= html_escape((string) ($dx['reference_version'] ?? '')); ?></small>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php $diagnosis_text = $rekam_field('diagnosis'); ?>
			<?php if ($diagnosis_text !== '' && empty($dxs)) : ?>
				<p class="dl-record-text"><?= html_escape($diagnosis_text); ?></p>
			<?php endif; ?>
		</section>


		<section class="dl-record-section" aria-labelledby="rm-vital">
			<h2 id="rm-vital">Tanda vital</h2>
			<?php if (empty($vitals)) : ?>
				<p class="dl-record-empty">Tidak ada pengukuran yang tercatat.</p>
			<?php else : ?>
				<dl class="dl-record-grid">
					<dt>Tekanan darah</dt>
					<dd class="dl-record-mono">
						<?php if ($vitals['systolic'] !== null || $vitals['diastolic'] !== null) : ?>
							<?= html_escape((string) ($vitals['systolic'] ?? '-')); ?>/<?= html_escape((string) ($vitals['diastolic'] ?? '-')); ?> mmHg
						<?php else : ?>-<?php endif; ?>
					</dd>
					<dt>Nadi</dt><dd class="dl-record-mono"><?= $vitals['pulse'] !== null ? html_escape((string) $vitals['pulse']) . ' x/menit' : '-'; ?></dd>
					<dt>Laju napas</dt><dd class="dl-record-mono"><?= $vitals['respiratory_rate'] !== null ? html_escape((string) $vitals['respiratory_rate']) . ' x/menit' : '-'; ?></dd>
					<dt>Suhu</dt><dd class="dl-record-mono"><?= $vitals['temperature_c'] !== null ? html_escape((string) $vitals['temperature_c']) . ' °C' : '-'; ?></dd>
					<dt>SpO2</dt><dd class="dl-record-mono"><?= $vitals['oxygen_saturation'] !== null ? html_escape((string) $vitals['oxygen_saturation']) . ' %' : '-'; ?></dd>
					<dt>Diukur</dt><dd class="dl-record-mono"><?= html_escape($rekam_time($vitals['measured_at'] ?? '')); ?></dd>
				</dl>
			<?php endif; ?>
		</section>

		<section class="dl-record-section" aria-labelledby="rm-hasil">
			<h2 id="rm-hasil">Hasil pemeriksaan</h2>
			<?php if (empty($visit)) : ?>
				<p class="dl-record-empty">Tidak ada hasil kunjungan yang dikirim.</p>
			<?php else : ?>
				<?php if (trim((string) ($visit['observation_summary'] ?? '')) !== '') : ?>
					<p class="dl-record-text"><?= html_escape((string) $visit['observation_summary']); ?></p>
				<?php endif; ?>
				<?php $findings = $rekam_json_lines($visit['findings_json'] ?? ''); ?>
				<?php $actions = $rekam_json_lines($visit['actions_json'] ?? ''); ?>
				<?php if (!empty($findings)) : ?>
					<dl class="dl-record-grid">
						<?php foreach ($findings as $line) : ?>
							<dt>Temuan</dt><dd><?= html_escape($line); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php if (!empty($actions)) : ?>
					<dl class="dl-record-grid">
						<?php foreach ($actions as $line) : ?>
							<dt>Tindakan</dt><dd><?= html_escape($line); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<p class="dl-record-sub">Dikirim <?= html_escape($rekam_time($visit['submitted_at'] ?? '')); ?></p>
			<?php endif; ?>
		</section>

		<section class="dl-record-section" aria-labelledby="rm-terapi">
			<h2 id="rm-terapi">Terapi dan saran</h2>
			<?php $treatment = $rekam_field('treatment'); ?>
			<?php $recommendations = $rekam_field('recommendations'); ?>
			<dl class="dl-record-grid">
				<dt>Terapi</dt>
				<dd><?= $treatment !== '' ? nl2br(html_escape($treatment), false) : '-'; ?></dd>
				<dt>Saran</dt>
				<dd><?= $recommendations !== '' ? nl2br(html_escape($recommendations), false) : '-'; ?></dd>
			</dl>
		</section>

		<section class="dl-record-section" aria-labelledby="rm-disposisi">
			<h2 id="rm-disposisi">Disposisi</h2>
			<?php if (empty($disp)) : ?>
				<p class="dl-record-empty">Tidak ada disposisi tercatat.</p>
			<?php else : ?>
				<dl class="dl-record-grid">
					<dt>Keputusan</dt><dd><?= html_escape(($disp['decision'] ?? '') === 'visit' ? 'Kunjungan' : 'Non-kunjungan'); ?></dd>
					<?php if (!empty($disp['urgency'])) : ?><dt>Urgensi</dt><dd><?= html_escape((string) $disp['urgency']); ?></dd><?php endif; ?>
					<?php if (!empty($disp['instructions'])) : ?><dt>Instruksi</dt><dd><?= nl2br(html_escape((string) $disp['instructions']), false); ?></dd><?php endif; ?>
					<?php if (!empty($disp['rationale'])) : ?><dt>Alasan</dt><dd><?= nl2br(html_escape((string) $disp['rationale']), false); ?></dd><?php endif; ?>
					<dt>Dicatat</dt><dd class="dl-record-mono"><?= html_escape($rekam_time($disp['created_at'] ?? '')); ?></dd>
				</dl>
			<?php endif; ?>
		</section>

		<section class="dl-record-section" aria-labelledby="rm-amandemen">
			<h2 id="rm-amandemen">Amandemen</h2>
			<?php if (empty($amends)) : ?>
				<p class="dl-record-empty">Tidak ada amandemen.</p>
			<?php else : ?>
				<table class="dl-record-table">
					<thead><tr><th scope="col">#</th><th scope="col">Waktu</th><th scope="col">Alasan</th></tr></thead>
					<tbody>
						<?php foreach ($amends as $amendment) : ?>
							<tr>
								<td class="dl-record-mono"><?= html_escape((string) ($amendment['sequence_no'] ?? '')); ?></td>
								<td class="dl-record-mono"><?= html_escape($rekam_time($amendment['created_at'] ?? '')); ?></td>
								<td><?= html_escape((string) ($amendment['reason'] ?? '')); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</section>

		<?php endif; ?>

		<footer class="dl-record-foot">
			Dicetak oleh <?= html_escape(isset($rekam_actor_name) && $rekam_actor_name !== '' ? $rekam_actor_name : 'Nakes'); ?>
			· <span class="dl-record-mono"><?= html_escape(date('j') . ' ' . date('M') . ' ' . date('Y') . ' · ' . date('H.i')); ?> WIB</span>
			· Setiap pembukaan rekam medis dicatat pada log audit akses klinis.
		</footer>
	</main>
</body>
</html>
