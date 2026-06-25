<?php
$kriteria = isset($kriteria) ? $kriteria : '';
$map_provider = $this->config->item('map_provider') ?: 'none';
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '';

if ((string) $kriteria === '0') {
	$kriteria = 'Selesai Konsultasi';
} elseif ((string) $kriteria === '1') {
	$kriteria = 'Kunjungan Nakes';
}

$keluhan_pasien = 'Keluhan tidak dapat ditampilkan.';
if (isset($keluhan) && $keluhan !== '') {
	try {
		$CI = &get_instance();
		$CI->load->library('encryption');
		$decoded_keluhan = base64_decode($keluhan);
		if ($decoded_keluhan !== FALSE) {
			$decrypted_keluhan = $CI->encryption->decrypt($decoded_keluhan);
			if ($decrypted_keluhan !== FALSE && trim((string) $decrypted_keluhan) !== '') {
				$keluhan_pasien = (string) $decrypted_keluhan;
			}
		}
	} catch (Exception $e) {
		$keluhan_pasien = 'Keluhan tidak dapat ditampilkan.';
	}
}

?>
<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Doklinc - Konsultasi Nakes</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<link rel="stylesheet" href="<?= base_url(); ?>assets/css/style.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" integrity="sha512-tS3S5qG0BlhnQROyJXvNjeEM4UpMXHrQfTGmbQ1gKmelCxlSEBUaxhRBj/EFTzpbP4RVSrpEikbmdJobCvhE3g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css" integrity="sha512-sMXtMNL1zRzolHYKEujM2AqCLUR9F2C4/05cdbxjjLSRvMQIciEPCQZo++nk7go3BtSuK9kfa/s+a4f4i5pLkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/css/bootstrap-select.min.css" integrity="sha512-g2SduJKxa4Lbn3GW+Q7rNz+pKP9AWMR++Ta8fgwsZRCUsawjPvF/BxSMkGS61VsR9yinGoEgrHPGPn2mrj8+4w==" crossorigin="anonymous" referrerpolicy="no-referrer">
	<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<style>
		:root {
			--doclinc-green: #379A69;
			--doclinc-mint: #50BFA5;
			--doclinc-bg: #F7F7F7;
			--doclinc-border: #CECECE;
			--doclinc-text: #1F2A24;
			--doclinc-muted: #66756D;
		}

		body {
			background: var(--doclinc-bg);
			color: var(--doclinc-text);
			font-size: 15px;
		}

		.consult-shell {
			max-width: 414px;
			min-height: 100vh;
			margin: 0 auto;
			background: var(--doclinc-bg);
			padding: 14px;
		}

		.consult-header {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 8px 0 16px;
		}

		.consult-back {
			width: 40px;
			height: 40px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border-radius: 14px;
			background: #fff;
			color: var(--doclinc-green);
			text-decoration: none;
			box-shadow: 0 8px 22px rgba(31, 42, 36, 0.08);
		}

		.consult-title {
			flex: 1;
			min-width: 0;
		}

		.consult-title h1 {
			margin: 0;
			font-size: 20px;
			font-weight: 700;
			letter-spacing: 0;
			color: var(--doclinc-text);
		}

		.consult-subtitle {
			margin: 2px 0 0;
			color: var(--doclinc-muted);
			font-size: 12px;
		}

		.status-badge {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 10px;
			border-radius: 999px;
			background: rgba(80, 191, 165, 0.18);
			color: var(--doclinc-green);
			font-size: 12px;
			font-weight: 700;
			white-space: nowrap;
		}

		.consult-card {
			width: 100%;
			background: #fff;
			border: 1px solid rgba(80, 191, 165, 0.45);
			border-radius: 20px;
			box-shadow: 0 10px 28px rgba(31, 42, 36, 0.06);
			padding: 16px;
			margin-bottom: 14px;
		}

		.section-heading {
			display: flex;
			align-items: center;
			gap: 8px;
			margin: 0 0 12px;
			color: var(--doclinc-green);
			font-size: 15px;
			font-weight: 700;
		}

		.summary-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 10px;
		}

		.summary-item {
			padding: 10px;
			border: 1px solid #E7ECE9;
			border-radius: 14px;
			background: #FBFDFC;
			min-width: 0;
		}

		.summary-label {
			display: block;
			margin-bottom: 4px;
			color: var(--doclinc-muted);
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
		}

		.summary-value {
			display: block;
			color: var(--doclinc-text);
			font-size: 14px;
			font-weight: 600;
			overflow-wrap: anywhere;
		}

		.complaint-text {
			margin: 0;
			color: var(--doclinc-text);
			font-size: 15px;
			line-height: 1.6;
			overflow-wrap: anywhere;
		}

		.chat-card {
			background: linear-gradient(135deg, #379A69 0%, #50BFA5 100%);
			color: #fff;
			border: 0;
		}

		.chat-copy {
			margin: 0 0 12px;
			font-size: 13px;
			opacity: 0.92;
		}

		.chat-button,
		.primary-action {
			width: 100%;
			min-height: 48px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			border: 0;
			border-radius: 14px;
			font-weight: 700;
			text-decoration: none;
		}

		.chat-button {
			background: #fff;
			color: var(--doclinc-green);
		}

		.primary-action {
			background: var(--doclinc-green);
			color: #fff;
		}

		.form-control,
		.form-select {
			width: 100%;
			border-color: var(--doclinc-border);
			border-radius: 10px;
		}

		.form-control:focus,
		.form-select:focus {
			border-color: var(--doclinc-mint);
			box-shadow: 0 0 0 0.2rem rgba(80, 191, 165, 0.18);
		}

		.form-floating>label {
			color: var(--doclinc-muted);
		}

		.optional-note {
			margin: -6px 0 10px;
			color: var(--doclinc-muted);
			font-size: 12px;
		}

		.documentation-group {
			display: grid;
			gap: 12px;
			margin-bottom: 16px;
		}

		.documentation-field {
			min-height: 118px;
			resize: vertical;
		}

		.backend-field {
			display: none;
		}

		.terapi-scroll {
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
			border: 1px solid #E7ECE9;
			border-radius: 14px;
			background: #fff;
		}

		#tabelTerapi {
			min-width: 680px;
			margin-bottom: 0;
		}

		#tabelTerapi th {
			color: var(--doclinc-green);
			font-size: 12px;
			white-space: nowrap;
		}

		#tabelTerapi td {
			vertical-align: middle;
		}

		#tabelTerapi .btn {
			border-radius: 10px;
		}

		.terapi-helper {
			margin: -4px 0 10px;
			color: var(--doclinc-muted);
			font-size: 12px;
			line-height: 1.5;
		}

		.terapi-row-message {
			display: none;
			margin: 0 0 10px;
			padding: 9px 11px;
			border-radius: 12px;
			background: #FFF7E6;
			color: #9A5B00;
			font-size: 13px;
			font-weight: 600;
		}

		.terapi-row-message.is-visible {
			display: block;
		}

		.btn-terapi-action {
			min-width: 40px;
			min-height: 40px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
		}

		.ui-autocomplete {
			z-index: 1056 !important;
		}

		.terapi-autocomplete {
			max-width: 180px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			cursor: pointer;
		}

		.terapi-autocomplete:hover {
			white-space: normal;
			overflow: visible;
			position: relative;
			z-index: 1;
			background: #fff;
		}

		#preview-image {
			max-width: 100%;
			margin-top: 10px;
			border-radius: 14px;
		}

		@media (min-width: 768px) {
			.consult-shell {
				padding-top: 24px;
				padding-bottom: 24px;
			}
		}
	</style>
</head>

<body>
	<div class="consult-shell">
		<header class="consult-header">
			<a href="<?= base_url('home_nakes'); ?>" class="consult-back" aria-label="Kembali ke beranda nakes">
				<i class="fas fa-arrow-left"></i>
			</a>
			<div class="consult-title">
				<h1>Pemeriksaan Pasien</h1>
				<p class="consult-subtitle">Request #<?= html_escape($request_id) ?></p>
			</div>
			<span class="status-badge"><i class="bi bi-check-circle-fill"></i> Accepted</span>
		</header>

		<main class="content animate__animated animate__fadeInUp animate__faster">
			<section class="consult-card">
				<h2 class="section-heading"><i class="bi bi-person-vcard"></i> Ringkasan Pasien</h2>
				<div class="summary-grid">
					<div class="summary-item">
						<span class="summary-label">Nama Pasien</span>
						<span class="summary-value"><?= html_escape(strtoupper($nama_pasien)); ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">Umur</span>
						<span class="summary-value"><?= html_escape($umur) ?> Tahun</span>
					</div>
					<div class="summary-item">
						<span class="summary-label">User ID</span>
						<span class="summary-value"><?= html_escape($userid) ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">Request ID</span>
						<span class="summary-value">#<?= html_escape($request_id) ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">Kriteria</span>
						<span class="summary-value"><?= html_escape($kriteria !== '' ? $kriteria : '-') ?></span>
					</div>
					<div class="summary-item">
						<span class="summary-label">Konteks</span>
						<span class="summary-value">Puskesmas / Nakes</span>
					</div>
				</div>
			</section>

			<section class="consult-card">
				<h2 class="section-heading"><i class="bi bi-clipboard2-pulse"></i> Keluhan Pasien</h2>
				<p class="complaint-text"><?= nl2br(html_escape($keluhan_pasien)); ?></p>
			</section>

			<section class="consult-card chat-card">
				<h2 class="section-heading text-white"><i class="bi bi-chat-dots"></i> Chat Konsultasi</h2>
				<p class="chat-copy">Buka percakapan aktif untuk membaca konteks tambahan dari pasien.</p>
				<a href="<?= html_escape(base_url('chat?request_id=' . (int) $request_id)); ?>" class="chat-button">
					<i class="bi bi-chat-dots-fill"></i> Chat Konsultasi
				</a>
			</section>

		<input type="hidden" name="userid" id="userId" value="<?= html_escape($userid) ?>">
		<input type="hidden" name="dokterid" id="dokterId" value="<?= html_escape($_SESSION['id']) ?>">
		<form id="form_konsul_nakes" enctype="multipart/form-data">
			<input type="hidden" name="request_id" id="idReq" value="<?= html_escape($request_id) ?>">
			<section class="consult-card">
				<h2 class="section-heading"><i class="bi bi-clipboard-check"></i> Selesaikan Konsultasi</h2>
				<div class="form-floating mb-3">
					<input type="text" id="diagnosa" name="diagnosa" class="form-control" placeholder="Diagnosa" required>
					<label for="diagnosa"><i class="bi bi-heart-pulse"></i> Diagnosa*</label>
				</div>
				<div class="mb-3">
					<h3 class="section-heading"><i class="bi bi-capsule"></i> Obat / Farmakoterapi</h3>
					<p class="terapi-helper">Bagian ini digunakan untuk mencatat obat/farmakoterapi bila diberikan. Kosongkan bila tidak ada obat.</p>
					<div id="terapiRowMessage" class="terapi-row-message" role="alert"></div>
					<div class="terapi-scroll">
						<table class="table table-sm table-hover table-striped" id="tabelTerapi">
								<thead class="table-success">
									<tr>
										<th>No.</th>
										<th>Terapi</th>
										<th>Jumlah Obat</th>
										<th>Cara Minum</th>
										<th>Keterangan</th>
										<th>Aksi</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>1</td>
										<td contenteditable="true" class="terapi-autocomplete">Terapi*</td>
										<td>
											<select class="form-select form-select-sm border-success">
												<option value="" selected disabled>Pilih frekuensi</option>
												<option value="1x sehari">1x sehari</option>
												<option value="2x sehari">2x sehari</option>
												<option value="3x sehari">3x sehari</option>
												<option value="4x sehari">4x sehari</option>
											</select>
										</td>
										<td>
											<select class="form-select form-select-sm border-success">
												<option value="" selected disabled>Pilih cara pakai</option>
												<option value="Sesudah makan">Sesudah makan</option>
												<option value="Sebelum makan">Sebelum makan</option>
											</select>
										</td>
										<td contenteditable="true">Masukkan keterangan</td>
										<td>
											<button type="button" class="btn btn-success btn-sm btn-terapi-action" onclick="tambahBaris(this)">
												<i class="bi bi-plus-circle"></i>
											</button>
										</td>
									</tr>
								</tbody>
							</table>
					</div>
				</div>
				<div class="documentation-group">
					<h3 class="section-heading mb-0"><i class="bi bi-journal-medical"></i> Dokumentasi Tindakan</h3>
					<div class="form-floating">
						<textarea id="ui_tindakan_non_obat" class="form-control documentation-field" placeholder="Edukasi pasien, anjuran istirahat, hidrasi/nutrisi, perawatan sederhana, observasi mandiri"></textarea>
						<label for="ui_tindakan_non_obat">Tindakan Non-Obat</label>
					</div>
					<div class="form-floating">
						<textarea id="ui_pemeriksaan_monitoring" class="form-control documentation-field" placeholder="Suhu, tekanan darah, nadi, saturasi, pemeriksaan fisik ringkas, pemeriksaan penunjang jika ada"></textarea>
						<label for="ui_pemeriksaan_monitoring">Pemeriksaan / Monitoring</label>
					</div>
					<div class="form-floating">
						<textarea id="ui_followup_edukasi" class="form-control documentation-field" placeholder="Kapan kontrol ulang, kondisi yang perlu segera diperiksa, edukasi singkat untuk pasien"></textarea>
						<label for="ui_followup_edukasi">Follow-up / Edukasi Tanda Bahaya</label>
					</div>
					<div class="form-floating">
						<textarea id="ui_catatan_tindakan_lain" class="form-control documentation-field" placeholder="Catatan tambahan tindakan atau observasi"></textarea>
						<label for="ui_catatan_tindakan_lain">Catatan Tindakan Lain</label>
					</div>
				</div>
				<div class="form-floating mb-3">
					<textarea id="ui_saran_utama" class="form-control" placeholder="Rekomendasi atau saran utama untuk pasien" style="height: 150px"></textarea>
					<label for="ui_saran_utama"><i class="bi bi-chat-dots"></i> Rekomendasi / Saran Utama*</label>
				</div>
				<textarea id="saran" name="saran" class="backend-field" aria-hidden="true"></textarea>
				<div class="form-floating mb-3">
					<input type="text" id="kriteria" name="kriteria" class="form-control" value="<?= html_escape($kriteria) ?>" readonly>
					<label for="kriteria"><i class="bi bi-clipboard-check"></i> Kriteria*</label>
				</div>
				<div class="form-floating mb-2">
					<input type="text" class="form-control" id="rujukan" name="rujukan" placeholder="Rujukan">
					<label for="rujukan"><i class="bi bi-arrow-right-circle"></i> Rujukan</label>
				</div>
				<p class="optional-note">Opsional jika pasien tidak memerlukan rujukan.</p>
				<?php if ($kriteria === 'Kunjungan Nakes') : ?>
					<div class="form-floating mb-2">
						<input type="file" class="form-control" id="file" name="file" accept="image/*">
						<label for="file"><i class="bi bi-camera"></i> Foto Kunjungan</label>
						<img id="preview-image" src="#" alt="Preview Foto" style="display:none;" class="img-thumbnail" />
					</div>
					<p class="optional-note">Opsional sesuai kebutuhan dokumentasi kunjungan.</p>
				<?php endif; ?>
				<button type="button" class="primary-action shadow-sm" id="save_konsul_nakes">
					<i class="bi bi-check2-circle"></i> Selesaikan Konsultasi
				</button>
			</section>
		</form>
		</main>
	</div>

	<!-- Modal -->
	<div class="modal fade" id="terapiModal" tabindex="-1" aria-labelledby="terapiModalLabel" aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="terapiModalLabel">Masukkan Terapi</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
				</div>
				<div class="modal-body">
					<input type="text" id="terapiInput" class="form-control" placeholder="Cari terapi...">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
					<button type="button" class="btn btn-primary" id="simpanTerapi">Simpan</button>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js" integrity="sha512-bPs7Ae6pVvhOSiIcyUClR7/q2OAsRiovw4vAkX+zJbw3ShAeeqezq50RIIcIURq7Oa20rW2n2q+fyXBNcU9lrw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.14.0-beta3/js/bootstrap-select.min.js" integrity="sha512-yrOmjPdp8qH8hgLfWpSFhC/+R9Cj9USL8uJxYIveJZGAiedxyIxwNw4RsLDlcjNlIRR4kkHaDHSmNHAkxFTmgg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<?php if ($map_provider === 'google' && !empty($google_maps_api_key)) : ?>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode($google_maps_api_key); ?>"></script>
	<?php endif; ?>

	<!-- firebase dan notifikasi -->
	<?php if ($firebase_enabled) : ?>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
		<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
		<?php if (!empty($legacy_superapp_url)) : ?>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
			<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/get-notif.js'); ?>"></script>
		<?php endif; ?>
	<?php endif; ?>

	<!-- save konsultasi -->
	<script>
		const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;
		const mapProvider = <?= json_encode($map_provider); ?>;

		function getFirebaseDatabase() {
			if (!firebaseEnabled || !window.firebase || !firebase.database) {
				return null;
			}

			try {
				return firebase.database();
			} catch (error) {
				return null;
			}
		}

		function nakesDocumentationValue(selector) {
			const value = $(selector).val();
			const trimmed = value ? value.trim() : "";
			return trimmed !== "" ? trimmed : "-";
		}

		function syncNakesDocumentationFields() {
			const saranUtama = nakesDocumentationValue('#ui_saran_utama');
			const dokumentasi = [
				"--- Dokumentasi Tindakan ---",
				"Tindakan Non-Obat:",
				nakesDocumentationValue('#ui_tindakan_non_obat'),
				"",
				"Pemeriksaan / Monitoring:",
				nakesDocumentationValue('#ui_pemeriksaan_monitoring'),
				"",
				"Follow-up / Edukasi:",
				nakesDocumentationValue('#ui_followup_edukasi'),
				"",
				"Catatan Tindakan Lain:",
				nakesDocumentationValue('#ui_catatan_tindakan_lain')
			].join("\n");

			$('#saran').val([saranUtama, dokumentasi].join("\n\n"));
		}

		$('#save_konsul_nakes').click(function() {
			const userId = document.getElementById('userId').value;
			const dokterId = document.getElementById('dokterId').value;
			const idReq = document.getElementById('idReq').value;
			const diagnosa = $('#diagnosa').val().trim();
			const saranUtama = $('#ui_saran_utama').val().trim();
			syncNakesDocumentationFields();
			const saran = $('#saran').val().trim();
			const kriteria = $('#kriteria').val().trim();

			if (!idReq || !diagnosa || !saranUtama || !saran || !kriteria) {
				Swal.fire("Gagal!", "Data tidak lengkap", "error");
				return;
			}

			var form = document.getElementById('form_konsul_nakes');
			var formData = new FormData(form);
			formData.set("request_id", idReq);
			formData.set("diagnosa", diagnosa);
			formData.set("saran", saran);
			formData.set("kriteria", kriteria);

			// Hilangkan tombol kirim selama proses berlangsung
			$('#save_konsul_nakes').prop('disabled', true).text('Mengirim...');


			// Ambil terapi dari tabel
			var terapiData = [];
			$("#tabelTerapi tbody tr").each(function(index, row) {
				var terapi = getTerapiName(row);
				var signa = getTerapiJumlah(row); // Jumlah Obat
				var caraMinum = getTerapiCara(row); // Cara Minum
				var keterangan = getTerapiKeterangan(row);

				if (!isTerapiPlaceholder(terapi)) {
					terapiData.push({
						terapi: terapi,
						jumlah: signa,
						cara: caraMinum,
						keterangan: keterangan
					});
				}
			});

			// Tambahkan data terapi dalam bentuk string JSON
			formData.set("terapi", JSON.stringify(terapiData));

			$.ajax({
				url: "<?php echo base_url(); ?>konsultasi_nakes/save_konsultasi_nakes",
				method: "POST",
				data: formData,
				processData: false, // Wajib
				contentType: false, // Wajib
				success: function(response) {
					console.log("Response:", response);
					if (typeof response === 'string') {
						try {
							response = JSON.parse(response);
						} catch (e) {}
					}
					if (response == 1 || (response && response.status === 'success')) {
						Swal.fire({
							title: "Berhasil!",
							icon: "success",
							allowOutsideClick: false, // Prevent closing by clicking outside
							allowEscapeKey: false, // Prevent closing with the escape key
							showConfirmButton: false,
							timer: 2500,
							timerProgressBar: true
						}).then((result) => {
							// kirim pesan ke warga untuk menampilkan rating dari nakes melalui firebase
							const firebaseDb = getFirebaseDatabase();
							if (!firebaseDb) {
								if (result.dismiss === Swal.DismissReason.timer) {
									window.location.href = '../../home_nakes#riwayat_konsul_selesai';
								}
								return;
							}

							const munculPopUpWarga = firebaseDb.ref('rating').push();
							munculPopUpWarga.set({
								idReq: idReq,
								idUser: userId,
								idDokter: dokterId,
								timestamp: Date.now()
							}).then(() => {
								if (result.dismiss === Swal.DismissReason.timer) {
									window.location.href = '../../home_nakes#riwayat_konsul_selesai';
								}
							}).catch(() => {
								if (result.dismiss === Swal.DismissReason.timer) {
									window.location.href = '../../home_nakes#riwayat_konsul_selesai';
								}
							})
						});
					} else {
						const message = response && response.message ? response.message : "Konsultasi gagal disimpan";
						Swal.fire("Gagal!", message, "error");
						$('#save_konsul_nakes').prop('disabled', false).html('<i class="bi bi-check2-circle"></i> Selesaikan Konsultasi');
					}
				},
				error: function(xhr, status, error) {
					console.error("Error:", xhr.responseText);
					let message = "Terjadi kesalahan AJAX";
					if (xhr.responseText) {
						try {
							const response = JSON.parse(xhr.responseText);
							if (response && response.message) {
								message = response.message;
							}
						} catch (e) {}
					}
					Swal.fire("Gagal!", message, "error");
					$('#save_konsul_nakes').prop('disabled', false).html('<i class="bi bi-check2-circle"></i> Selesaikan Konsultasi');
				}
			});
		});
	</script>

	<!-- preview image -->
	<script>
		$('#file').change(function() {
			const file = this.files[0];
			if (file) {
				let reader = new FileReader();
				reader.onload = function(e) {
					$('#preview-image')
						.attr('src', e.target.result)
						.show();
				};
				reader.readAsDataURL(file);
			} else {
				$('#preview-image').hide();
			}
		});
	</script>

	<!-- get ICD10 -->
	<script>
		$(document).ready(function() {
			$('#diagnosa').autocomplete({
				source: function(request, response) {
					$.ajax({
						url: "<?= base_url('konsultasi_nakes/getICD_json'); ?>",
						type: 'GET',
						dataType: 'json',
						data: {
							term: request.term
						},
						success: function(data) {
							response($.map(data, function(item) {
								return {
									label: item.id_keluhan + ' - ' + item.nama_keluhan,
									value: item.nama_keluhan
								}
							}));
						}
					});
				},
				minLength: 2,
			});
		})
	</script>

	<!-- tambah bari dan hapus bari -->
	<script>
		function normalizeTerapiText(value) {
			return (value || "").replace(/\s+/g, " ").trim();
		}

		function isTerapiPlaceholder(value) {
			const normalized = normalizeTerapiText(value).toLowerCase();
			return normalized === "" || normalized === "terapi*";
		}

		function getTerapiName(row) {
			return normalizeTerapiText($(row).find("td:eq(1)").text());
		}

		function getTerapiJumlah(row) {
			return normalizeTerapiText($(row).find("td:eq(2) select").val());
		}

		function getTerapiCara(row) {
			return normalizeTerapiText($(row).find("td:eq(3) select").val());
		}

		function getTerapiKeterangan(row) {
			const value = normalizeTerapiText($(row).find("td:eq(4)").text());
			return value.toLowerCase() === "masukkan keterangan" ? "" : value;
		}

		function showTerapiRowMessage(message) {
			$('#terapiRowMessage').text(message).addClass('is-visible');
		}

		function clearTerapiRowMessage() {
			$('#terapiRowMessage').text('').removeClass('is-visible');
		}

		function validateTerapiRow(row) {
			if (isTerapiPlaceholder(getTerapiName(row))) {
				showTerapiRowMessage("Isi nama obat/terapi terlebih dahulu.");
				return false;
			}
			if (getTerapiJumlah(row) === "") {
				showTerapiRowMessage("Pilih jumlah/frekuensi obat.");
				return false;
			}
			if (getTerapiCara(row) === "") {
				showTerapiRowMessage("Pilih cara minum/cara pakai.");
				return false;
			}

			clearTerapiRowMessage();
			return true;
		}

		function tambahBaris(button) {
			let row = button.closest("tr");

			if (!validateTerapiRow(row)) {
				return;
			}

			let table = document.getElementById("tabelTerapi");
			let rowCount = table.rows.length;

			// Insert row setelah baris terakhir
			let newRow = table.insertRow(rowCount);

			let cellNo = newRow.insertCell(0);
			let cellTerapi = newRow.insertCell(1);
			let cellJumlahObat = newRow.insertCell(2);
			let cellCaraMinum = newRow.insertCell(3);
			let cellKeterangan = newRow.insertCell(4);
			let cellAksi = newRow.insertCell(5);

			// Nomor otomatis (tanpa menghitung header)
			cellNo.innerHTML = rowCount - 1;

			// Buat sel yang bisa diedit
			cellTerapi.classList.add("terapi-autocomplete");
			cellTerapi.contentEditable = "true";
			cellTerapi.innerText = "Terapi*";

			cellJumlahObat.innerHTML = `
				<select class="form-select form-select-sm border-success">
					<option value="" selected disabled>Pilih frekuensi</option>
					<option value="1x sehari">1x sehari</option>
					<option value="2x sehari">2x sehari</option>
					<option value="3x sehari">3x sehari</option>
					<option value="4x sehari">4x sehari</option>
				</select>
			`;

			cellCaraMinum.innerHTML = `
				<select class="form-select form-select-sm border-success">
					<option value="" selected disabled>Pilih cara pakai</option>
					<option value="Sesudah makan">Sesudah makan</option>
					<option value="Sebelum makan">Sebelum makan</option>
				</select>
			`;

			cellKeterangan.contentEditable = "true";
			cellKeterangan.innerText = "";

			// Tambahkan tombol tambah & hapus di baris baru
			let addButton = document.createElement("button");
			addButton.innerHTML = '<i class="bi bi-plus-circle"></i>'; // Add icon
			addButton.className = "btn btn-success btn-sm btn-terapi-action";
			addButton.type = "button";
			addButton.setAttribute("onclick", "tambahBaris(this)");

			let removeButton = document.createElement("button");
			removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
			removeButton.className = "btn btn-danger btn-sm btn-terapi-action";
			removeButton.type = "button";
			removeButton.setAttribute("onclick", "hapusBaris(this)");

			cellAksi.appendChild(addButton);
			cellAksi.appendChild(removeButton);

			updateNomorUrut();
			// Update tombol aksi di baris sebelumnya
			updateTombolAksi();
		}

		function hapusBaris(button) {
			let table = document.getElementById("tabelTerapi");
			let row = button.parentElement.parentElement;

			// Cegah menghapus baris jika hanya satu yang tersisa
			if (table.rows.length > 2) {
				row.remove();
				updateNomorUrut();
				updateTombolAksi();
			} else {
				alert("Baris pertama tidak bisa dihapus jika hanya ada satu data!");
			}
		}

		function updateNomorUrut() {
			let table = document.getElementById("tabelTerapi");

			// Update nomor urut, mulai dari index 1 (karena index 0 adalah header)
			for (let i = 1; i < table.rows.length; i++) {
				table.rows[i].cells[0].innerHTML = i;
			}
		}

		function updateTombolAksi() {
			let table = document.getElementById("tabelTerapi");
			let rows = table.rows;

			for (let i = 1; i < rows.length; i++) {
				let aksiCell = rows[i].cells[5];
				aksiCell.innerHTML = "";

				if (i === rows.length - 1) {
					// Baris terakhir punya tombol tambah dan hapus
					let addButton = document.createElement("button");
					addButton.innerHTML = '<i class="bi bi-plus-circle"></i>'; // Add icon
					addButton.className = "btn btn-success btn-sm btn-terapi-action";
					addButton.type = "button";
					addButton.setAttribute("onclick", "tambahBaris(this)");
					aksiCell.appendChild(addButton);

					let removeButton = document.createElement("button");
					removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
					removeButton.className = "btn btn-danger btn-sm btn-terapi-action";
					removeButton.type = "button";
					removeButton.setAttribute("onclick", "hapusBaris(this)");
					aksiCell.appendChild(removeButton);
				} else {
					// Baris lainnya hanya memiliki tombol hapus
					let removeButton = document.createElement("button");
					removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
					removeButton.className = "btn btn-danger btn-sm btn-terapi-action";
					removeButton.type = "button";
					removeButton.setAttribute("onclick", "hapusBaris(this)");
					aksiCell.appendChild(removeButton);
				}
			}
		}
	</script>

	<!-- <script>
		$(document).ready(function() {
			$(document).on("click", ".terapi-autocomplete", function() {
				let tdElement = $(this);

				// Jika input sudah ada di dalam td, hentikan agar tidak berulang
				if (tdElement.find("input").length > 0) {
					return;
				}

				let currentText = tdElement.text().trim();

				// Buat input sementara
				let input = $("<input>", {
					type: "text",
					value: currentText,
					class: "temp-input",
				});

				// Kosongkan <td> dan tambahkan input
				tdElement.empty().append(input);
				input.focus();

				// Aktifkan autocomplete pada input
				input.autocomplete({
					source: function(request, response) {
						$.ajax({
							url: "<?= base_url('konsultasi_nakes/get_terapi') ?>",
							type: "GET",
							dataType: "json",
							data: {
								cari: request.term
							},
							success: function(data) {
								response(data);
							}
						});
					},
					minLength: 1, // Mulai autocomplete setelah mengetik 1 karakter
					select: function(event, ui) {
						tdElement.text(ui.item.value); // Simpan nilai yang dipilih ke <td>
						return false; // Mencegah perubahan default input
					}
				});

				// Saat kehilangan fokus, hapus input dan simpan nilai
				input.on("blur", function() {
					tdElement.text(input.val() || currentText); // Simpan teks di <td>
				});

				// Tangani enter agar langsung menyimpan tanpa keluar form
				input.on("keypress", function(e) {
					if (e.which === 13) { // Enter key
						tdElement.text(input.val());
						input.blur();
						return false;
					}
				});
			});
		});
	</script> -->

	<script>
		$(document).ready(function() {
			let selectedTd = null;

			$(document).on("click", ".terapi-autocomplete", function() {
				selectedTd = $(this); // Simpan referensi <td> yang diklik
				let currentText = selectedTd.text().trim();
				$("#terapiInput").val(currentText);
				$("#terapiModal").modal("show");
			});

			// Inisialisasi autocomplete saat input aktif
			$("#terapiInput").autocomplete({
				source: function(request, response) {
					$.ajax({
						url: "<?= base_url('konsultasi_nakes/get_terapi') ?>",
						type: "GET",
						dataType: "json",
						data: {
							cari: request.term
						},
						success: function(data) {
							response(data);
						}
					});
				},
				minLength: 1
			});

			// Simpan nilai dari modal ke <td>
			$("#simpanTerapi").on("click", function() {
				if (selectedTd !== null) {
					let newValue = $("#terapiInput").val();
					selectedTd.text(newValue);
					$("#terapiModal").modal("hide");
				}
			});
		});
	</script>
</body>

</html>
