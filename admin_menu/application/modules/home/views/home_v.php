<?php
$google_maps_api_key = $this->config->item('google_maps_api_key') ?: '';
$map_provider = $this->config->item('map_provider') ?: 'none';
$firebase_enabled = (bool) $this->config->item('firebase_enabled');
$legacy_superapp_url = $this->config->item('legacy_superapp_url') ?: '';
?>
<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-stethoscope"></i> Dashboard DokLinC</h1>

	<button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#chartModal">
		<i class="fas fa-chart-bar"></i> Lihat Chart
	</button>

	<!-- Modal Chart Diagnosa -->
	<div class="modal fade" id="chartModal" tabindex="-1" role="dialog" aria-labelledby="chartModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-xl" role="document">
			<div class="modal-content">
				<div class="modal-header bg-primary text-white">
					<h5 class="modal-title" id="chartModalLabel"><i class="fas fa-chart-bar"></i> Chart Diagnosa Terbanyak</h5>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="row mb-4">
						<div class="col-md-6">
							<input type="text" id="datepickerModal" class="form-control" placeholder="Pilih rentang tanggal">
							<br>
							<button id="reset-filterModal" class="btn btn-outline-danger modern-reset-btn">
								<i class="bi bi-arrow-counterclockwise me-1"></i> Reset
							</button>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="row mb-4">
								<canvas id="diagnosaChartModal" height="200"></canvas>
							</div>
							<div class="row">
								<?php
								$all_diagnosa_per_puskesmas = [];
								if ($this->db->table_exists('konsultasi') && $this->db->table_exists('requests') && $this->db->table_exists('users') && $this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users')) {
									$all_diagnosa_per_puskesmas = $this->db->query("
										SELECT
											p.nama_puskesmas,
											COUNT(*) as total
										FROM konsultasi k
										JOIN requests r ON k.request_id = r.request_id
										JOIN users u ON r.user_id = u.userId
										JOIN m_puskesmas p ON u.remark = p.kode_pkm
										GROUP BY p.nama_puskesmas
										ORDER BY p.nama_puskesmas
									")->result_array();
								}

								$all_stats = [];
								foreach ($all_diagnosa_per_puskesmas as $row) {
									$all_stats[$row['nama_puskesmas']][] = [
										'total' => $row['total']
									];
								}
								?>

								<div class="table-responsive">
									<table class="table table-bordered table-sm">
										<thead class="thead-light">
											<tr>
												<th>Puskesmas</th>
												<th>Jumlah</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($all_stats as $puskesmas => $diagnosa_list): ?>
												<?php foreach ($diagnosa_list as $i => $row): ?>
													<tr>
														<?php if ($i == 0): ?>
															<td rowspan="<?= count($diagnosa_list) ?>" class="align-middle font-weight-bold"><?= htmlspecialchars($puskesmas) ?></td>
														<?php endif; ?>
														<td><?= $row['total'] ?></td>
													</tr>
												<?php endforeach; ?>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<?php
							$diagnosa_per_puskesmas = [];
							if ($this->db->table_exists('konsultasi') && $this->db->table_exists('requests') && $this->db->table_exists('users') && $this->db->table_exists('m_puskesmas') && $this->db->field_exists('remark', 'users')) {
								$diagnosa_per_puskesmas = $this->db->query("
									SELECT
										p.nama_puskesmas,
										k.diagnosa,
										COUNT(*) as total
									FROM konsultasi k
									JOIN requests r ON k.request_id = r.request_id
									JOIN users u ON r.user_id = u.userId
									JOIN m_puskesmas p ON u.remark = p.kode_pkm
									GROUP BY p.nama_puskesmas, k.diagnosa
									ORDER BY p.nama_puskesmas, total DESC
								")->result_array();
							}

							$puskesmas_stats = [];
							foreach ($diagnosa_per_puskesmas as $row) {
								$puskesmas = $row['nama_puskesmas'];
								if (!isset($puskesmas_stats[$puskesmas])) {
									$puskesmas_stats[$puskesmas] = [];
								}
								if (count($puskesmas_stats[$puskesmas]) < 5) {
									$puskesmas_stats[$puskesmas][] = [
										'diagnosa' => $row['diagnosa'],
										'total' => $row['total']
									];
								}
							}
							?>

							<div class="table-responsive">
								<table class="table table-bordered table-sm">
									<thead class="thead-light">
										<tr>
											<th>Puskesmas</th>
											<th>Diagnosa</th>
											<th>Jumlah</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($puskesmas_stats as $puskesmas => $diagnosa_list): ?>
											<?php foreach ($diagnosa_list as $i => $row): ?>
												<tr>
													<?php if ($i == 0): ?>
														<td rowspan="<?= count($diagnosa_list) ?>" class="align-middle font-weight-bold"><?= htmlspecialchars($puskesmas) ?></td>
													<?php endif; ?>
													<td><?= htmlspecialchars($row['diagnosa']) ?></td>
													<td><?= $row['total'] ?></td>
												</tr>
											<?php endforeach; ?>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Content Row -->
<div class="row mb-4 justify-content-center">
	<!-- Kalendar untuk melihat berapa pasien yang konsultasi -->
	<div class="col-md-4 col-lg-4 col-xl-3">
		<div class="card shadow mb-4">
			<div class="card-header py-3">
				<h6 class="m-0 font-weight-bold text-primary">
					<i class="fas fa-fw fa-calendar"></i>
					Pasien Konsultasi
				</h6>
			</div>
			<div class="card-body">
				<input type="text" id="datepicker" class="form-control" placeholder="Pilih rentang tanggal">
				<br>
				<button id="reset-filter" class="btn btn-outline-danger modern-reset-btn">
					<i class="bi bi-arrow-counterclockwise me-1"></i> Reset
				</button>
			</div>
		</div>
	</div>
	<!-- End Kalendar -->

	<!-- Konsultasi Kesehatan Card ala Rumah Sakit -->
	<div class="col-lg-8 col-md-8 mb-4">
		<div class="card shadow border-0" style="background: linear-gradient(90deg, #e3f2fd 0%, #ffffff 100%);">
			<div class="card-body py-4">
				<div class="row align-items-center">
					<div class="col-md-2 text-center">
						<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto" style="width:70px; height:70px;">
							<i class="fas fa-hospital-user fa-2x text-white"></i>
						</div>
						<div class="mt-2 text-primary font-weight-bold" style="font-size: 1.1rem;">Konsultasi</div>
					</div>
					<div class="col-md-10">
						<div class="row text-center">
							<div class="col-6 col-md-3 mb-3 mb-md-0">
								<div class="card border-0 shadow-sm h-100" style="background-color: #e3fcec;">
									<div class="card-body py-3">
										<i class="fas fa-user-plus fa-lg text-success mb-2"></i>
										<div class="font-weight-bold text-success" style="font-size: 1.5rem;" id="konsultasi_baru"></div>
										<div class="small text-muted">Baru</div>
									</div>
								</div>
							</div>
							<div class="col-6 col-md-3 mb-3 mb-md-0">
								<div class="card border-0 shadow-sm h-100" style="background-color: #e3f0fc;">
									<div class="card-body py-3">
										<i class="fas fa-spinner fa-lg text-info mb-2"></i>
										<div class="font-weight-bold text-info" style="font-size: 1.5rem;" id="konsultasi_proses"></div>
										<div class="small text-muted">Diproses</div>
									</div>
								</div>
							</div>
							<div class="col-6 col-md-3 mb-3 mb-md-0">
								<div class="card border-0 shadow-sm h-100" style="background-color: #f3e3fc;">
									<div class="card-body py-3">
										<i class="fas fa-check-circle fa-lg text-primary mb-2"></i>
										<div class="d-flex flex-column align-items-center justify-content-center">
											<div class="d-flex align-items-center justify-content-center">
												<div class="font-weight-bold text-primary mr-2" style="font-size: 1.5rem;" id="konsultasi_selesai"></div>
											</div>
										</div>
										<div class="small text-muted">Selesai</div>
									</div>
								</div>
							</div>
							<div class="col-6 col-md-3">
								<div class="card border-0 shadow-sm h-100" style="background-color: #fde3e3;">
									<div class="card-body py-3">
										<i class="fas fa-ban fa-lg text-danger mb-2"></i>
										<div class="font-weight-bold text-danger" style="font-size: 1.5rem;"><?= $konsultasi_cancel; ?></div>
										<div class="small text-muted">Batal</div>
									</div>
								</div>
							</div>
						</div>
						<div class="mt-4 text-center">
							<span class="h5 font-weight-bold text-dark" id="total_konsultasi"></span>
							<span class="text-muted ml-2">Total Konsultasi</span>
						</div>
						<div class="text-center text-muted small mt-1">
							Data realtime konsultasi pasien di DokLinC
						</div>
						<div class="text-center">
							<span class="text-muted small ml-2" id="current_date"><?= date('Y-m-d'); ?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row mb-4">
	<!-- Pasien Baru -->
	<div class="col-lg-4 col-md-6 mb-4">
		<div class="card shadow border-0 h-100" style="background: linear-gradient(135deg, #e3fcec 0%, #ffffff 100%);">
			<div class="card-body py-4">
				<div class="d-flex align-items-center mb-3">
					<div class="rounded-circle bg-success d-flex align-items-center justify-content-center mr-3" style="width:56px; height:56px;">
						<i class="fas fa-notes-medical fa-2x text-white"></i>
					</div>
					<div>
						<div class="text-uppercase font-weight-bold text-success small mb-1">Pasien Baru</div>
						<div class="h3 mb-0 font-weight-bold text-success baru">0</div>
					</div>
				</div>
				<div class="list-group small border rounded" style="max-height: 320px; overflow-y: auto; background: #f8fdfb;">
					<div id="list-konsultasi-baru"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- Pasien Diproses -->
	<div class="col-lg-4 col-md-6 mb-4">
		<div class="card shadow border-0 h-100" style="background: linear-gradient(135deg, #e3f0fc 0%, #ffffff 100%);">
			<div class="card-body py-4">
				<div class="d-flex align-items-center mb-3">
					<div class="rounded-circle bg-info d-flex align-items-center justify-content-center mr-3" style="width:56px; height:56px;">
						<i class="fas fa-spinner fa-2x text-white"></i>
					</div>
					<div>
						<div class="text-uppercase font-weight-bold text-info small mb-1">Sedang Diproses</div>
						<div class="h3 mb-0 font-weight-bold text-info proses">0</div>
					</div>
				</div>
				<div class="list-group small border rounded" style="max-height: 320px; overflow-y: auto; background: #f8fbfd;">
					<div id="list-konsultasi-proses"></div>
				</div>
			</div>
		</div>
	</div>

	<!-- Pasien Selesai -->
	<div class="col-lg-4 col-md-6 mb-4">
		<div class="card shadow border-0 h-100" style="background: linear-gradient(135deg, #f3e3fc 0%, #ffffff 100%);">
			<div class="card-body py-4">
				<div class="d-flex align-items-center mb-3">
					<div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mr-3" style="width:56px; height:56px;">
						<i class="fas fa-check-circle fa-2x text-white"></i>
					</div>
					<div>
						<div id="konsultasi-selesai-container" class="mt-2">
							<!-- Data akan diisi oleh JavaScript -->
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- <div class="row mb-4">
	<div class="col-lg-12 col-md-12 mb-4">
		<div class="card shadow mb-4">
			<div class="card-header py-3">
				<h6 class="m-0 font-weight-bold text-primary">
					<i class="fas fa-chart-bar"></i>
					5 Diagnosa Terbanyak
				</h6>
			</div>
			<div class="card-body">
				<canvas id="diagnosaChart" height="180"></canvas>
			</div>
		</div>
	</div>
</div> -->


<!-- Modal Pasien Baru-->
<?php foreach ($konsultasi_baru_list->result() as $baru): ?>
	<div class="modal fade" id="detailModal<?= $baru->request_id; ?>" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel<?= $baru->request_id; ?>" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title" id="detailModalLabel<?= $baru->request_id; ?>"><i class="fas fa-user-md"></i> Detail Konsultasi</h5>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-info text-white">
							<h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Konsultasi</h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-md-6">
									<p class="mb-2"><strong><i class="fas fa-user"></i> Nama Pasien:</strong></p>
									<p class="text-primary font-weight-bold"><?= $baru->nama; ?></p>
								</div>
								<div class="col-md-6">
									<p class="mb-2"><strong><i class="fas fa-user-md"></i> Nama Dokter:</strong></p>
									<p class="text-success font-weight-bold"><?= $baru->name; ?></p>
								</div>
							</div>
							<div class="row">
								<div class="col-md-6">
									<p class="mb-2"><strong><i class="fas fa-clock"></i> Waktu Konsultasi:</strong></p>
									<!-- <p class="text-muted"><?= $baru->location; ?></p> -->

								</div>
								<div class="col-md-6">
									<p class="mb-2"><strong><i class="fas fa-map-marker-alt"></i> Alamat Pasien:</strong></p>
									<p class="text-muted"><?= $baru->location; ?></p>
								</div>
							</div>
						</div>
					</div>

					<div class="row row-cols-1 row-cols-md-2 g-3">
						<div class="col">
							<div id="map<?= $baru->request_id; ?>" style="height: 400px; border-radius: 10px; border: 2px solid #17a2b8;"></div>
						</div>
						<div class="col">
							<div class="card shadow-sm h-100">
								<div class="card-header bg-primary text-white">
									<h6 class="mb-0"><i class="fas fa-route"></i> Rute Pasien ke Dokter</h6>
								</div>
								<div class="card-body" style="max-height: 400px; overflow-y: auto; font-size: 14px;">
									<div id="routeInfo<?= $baru->request_id; ?>" class="mb-3 fw-bold text-dark"></div>
									<div id="directionsPanel<?= $baru->request_id; ?>"></div>
								</div>
							</div>
						</div>
					</div>

				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Tutup</button>
				</div>
			</div>
		</div>
	</div>
<?php endforeach; ?>

<?php foreach ($konsultasi_proses_list->result() as $proses): ?>
	<!-- Modal Pasien Proses-->
	<div class="modal fade" id="detailModal<?= $proses->request_id; ?>" tabindex="-1" role="dialog" aria-labelledby="detailModalLabel<?= $proses->request_id; ?>" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header bg-success text-white">
					<h5 class="modal-title" id="detailModalLabel<?= $proses->request_id; ?>"><i class="fas fa-user-md"></i> Detail Konsultasi</h5>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="card shadow-sm mb-4">
						<div class="card-header bg-info text-white">
							<h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Konsultasi</h5>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-md-6">
									<p class="mb-2"><strong><i class="fas fa-user"></i> Nama Pasien:</strong></p>
									<p class="text-primary font-weight-bold"><?= $proses->nama; ?></p>
								</div>
								<div class="col-md-6">
									<p class="mb-2"><strong><i class="fas fa-user-md"></i> Nama Dokter:</strong></p>
									<p class="text-success font-weight-bold"><?= $proses->name; ?></p>
								</div>
							</div>
							<div class="row">
								<div class="col-md-6">
									<!-- <p class="mb-2"><strong><i class="fas fa-clock"></i> Waktu Konsultasi:</strong></p> -->
									<p class="mb-2"><strong><i class="fas fa-file-alt"></i> Deskripsi Keluhan:</strong></p>
									<?php
									$CI = &get_instance();
									$CI->load->library('encryption');
									$raw_description = $proses->request_description ?? '';
									$decrypted_description = '-';
									if ($raw_description !== '') {
										$decoded_description = base64_decode((string) $raw_description, TRUE);
										if ($decoded_description !== FALSE && $decoded_description !== '') {
											try {
												$decrypted_value = $CI->encryption->decrypt($decoded_description);
												$decrypted_description = !empty($decrypted_value) ? $decrypted_value : $raw_description;
											} catch (Throwable $e) {
												$decrypted_description = $raw_description;
											}
										} else {
											$decrypted_description = $raw_description;
										}
									}
									?>
									<p class="text-muted"><?= html_escape($decrypted_description); ?></p>

								</div>
								<div class="col-md-6">
									<p class="mb-2"><strong><i class="fas fa-map-marker-alt"></i> Alamat Pasien:</strong></p>
									<p class="text-muted"><?= $proses->location; ?></p>
								</div>
							</div>
						</div>
					</div>

					<div class="row row-cols-1 row-cols-md-2 g-3">
						<div class="col">
							<div id="map<?= $proses->request_id; ?>" style="height: 400px; border-radius: 10px; border: 2px solid #17a2b8;"></div>
						</div>
						<div class="col">
							<div class="card shadow-sm h-100">
								<div class="card-header bg-primary text-white">
									<h6 class="mb-0"><i class="fas fa-route"></i> Rute Pasien ke Dokter</h6>
								</div>
								<div class="card-body" style="max-height: 400px; overflow-y: auto; font-size: 14px;">
									<div id="routeInfo<?= $proses->request_id; ?>" class="mb-3 fw-bold text-dark"></div>
									<div id="directionsPanel<?= $proses->request_id; ?>"></div>
								</div>
							</div>
						</div>
					</div>

				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i> Tutup</button>
				</div>
			</div>
		</div>
	</div>
<?php endforeach; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<?php if ($firebase_enabled) : ?>
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
	<?php if ($legacy_superapp_url !== '') : ?>
		<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/firebase-config.js'); ?>"></script>
		<script src="<?= html_escape(rtrim($legacy_superapp_url, '/') . '/firebase/get-notif.js'); ?>"></script>
	<?php endif; ?>
<?php endif; ?>

<script>
	flatpickr("#datepicker", {
		mode: "range",
		dateFormat: "Y-m-d",
		onChange: function(selectedDates, dateStr, instance) {
			// fetchKonsultasi(dateStr); // Panggil fungsi fetch data
			loadKonsultasiBaru(dateStr);
			// loadKonsultasiProses(dateStr)
		}
	});

	document.getElementById('reset-filter').addEventListener('click', function() {
		document.getElementById('datepicker').value = ''; // kosongkan input tanggal
		loadRealtimeKonsultasi(); // panggil ulang tanpa filter
	});

	flatpickr("#datepickerModal", {
		mode: "range",
		dateFormat: "Y-m-d",
		onChange: function(selectedDates, dateStr, instance) {
			// fetchKonsultasi(dateStr); // Panggil fungsi fetch data
			loadKonsultasiBaru(dateStr);
			// loadKonsultasiProses(dateStr)
		}
	});

	document.getElementById('reset-filterModal').addEventListener('click', function() {
		document.getElementById('datepickerModal').value = ''; // kosongkan input tanggal
		// loadRealtimeKonsultasi(); // panggil ulang tanpa filter
		alert("Hello");
	});
</script>

<!-- javascript untuk menampilkan map pasien baru -->
<script>
	const mapProvider = <?= json_encode($map_provider); ?>;
	const googleMapsEnabled = mapProvider === 'google' && <?= json_encode(!empty($google_maps_api_key)); ?>;
	const firebaseEnabled = <?= json_encode($firebase_enabled); ?>;

	function canUseRealtimeMaps() {
		return googleMapsEnabled && firebaseEnabled && window.google && window.google.maps && window.firebase && firebase.database;
	}

	document.addEventListener("DOMContentLoaded", function() {
		if (!canUseRealtimeMaps()) {
			return;
		}

		const directionsService = new google.maps.DirectionsService();
		const directionsRenderers = {}; // untuk menyimpan per modal

		<?php foreach ($konsultasi_baru_list->result() as $baru): ?>
			$('#detailModal<?= $baru->request_id ?>').on('shown.bs.modal', function() {
				const mapId = 'map<?= $baru->request_id ?>';
				const mapElement = document.getElementById(mapId);

				const pasienPos = {
					lat: parseFloat(<?= $baru->lattitude ?>),
					lng: parseFloat(<?= $baru->longitude ?>)
				};

				const map = new google.maps.Map(mapElement, {
					center: pasienPos,
					zoom: 13
				});

				const pasienMarker = new google.maps.Marker({
					position: pasienPos,
					map: map,
					title: "Pasien: <?= $baru->nama ?>"
				});

				const dokterUsername = "<?= $baru->dokter_id ?>";
				const dokterRef = firebase.database().ref('location/' + dokterUsername);

				let dokterMarker = null;
				let doctorPath = new google.maps.Polyline({
					path: [],
					geodesic: true,
					strokeColor: '#00BFFF',
					strokeOpacity: 0.8,
					strokeWeight: 4
				});
				doctorPath.setMap(map);

				directionsRenderers[<?= $baru->request_id ?>] = new google.maps.DirectionsRenderer({
					map: map,
					panel: document.getElementById('directionsPanel<?= $baru->request_id ?>')
				});

				dokterRef.on('value', function(snapshot) {
					const data = snapshot.val();
					if (data) {
						const lat = parseFloat(data.latitude);
						const lng = parseFloat(data.longitude);
						if (isNaN(lat) || isNaN(lng)) return;

						const dokterPos = {
							lat,
							lng
						};
						doctorPath.getPath().push(new google.maps.LatLng(lat, lng));

						if (!dokterMarker) {
							dokterMarker = new google.maps.Marker({
								position: dokterPos,
								map: map,
								title: "Dokter",
								icon: {
									url: 'https://img.icons8.com/emoji/48/ambulance-emoji.png',
									scaledSize: new google.maps.Size(50, 50),
									rotation: 0,
									anchor: new google.maps.Point(25, 25)
								}
							});

							map.fitBounds(new google.maps.LatLngBounds().extend(dokterPos).extend(pasienPos));
							updateRoute(dokterPos, pasienPos, directionsRenderers[<?= $baru->request_id ?>], 'routeInfo<?= $baru->request_id ?>');
						} else {

							// Perbarui posisi dan arah marker
							dokterMarker.setPosition(latLng);
							dokterMarker.setIcon({
								url: 'https://img.icons8.com/emoji/48/ambulance-emoji.png',
								scaledSize: new google.maps.Size(50, 50),
								rotation: bearing,
								anchor: new google.maps.Point(25, 25)
							});

							animateMarker(dokterMarker, dokterPos);
							updateRoute(dokterPos, pasienPos, directionsRenderers[<?= $baru->request_id ?>], 'routeInfo<?= $baru->request_id ?>');
						}
					}
				});
			});
		<?php endforeach; ?>

		function animateMarker(marker, toPosition) {
			const fromPosition = marker.getPosition();
			const deltaLat = (toPosition.lat - fromPosition.lat()) / 60;
			const deltaLng = (toPosition.lng - fromPosition.lng()) / 60;
			let i = 0;

			function moveMarker() {
				const lat = fromPosition.lat() + deltaLat * i;
				const lng = fromPosition.lng() + deltaLng * i;
				marker.setPosition(new google.maps.LatLng(lat, lng));
				if (i < 60) {
					i++;
					requestAnimationFrame(moveMarker);
				}
			}

			moveMarker();
		}

		function updateRoute(origin, destination, directionsRenderer, infoElementId) {
			directionsService.route({
				origin: origin,
				destination: destination,
				travelMode: google.maps.TravelMode.DRIVING
			}, function(response, status) {
				if (status === google.maps.DirectionsStatus.OK) {
					directionsRenderer.setDirections(response);
					const leg = response.routes[0].legs[0];
					const info = `Jarak: ${leg.distance.text}, Estimasi waktu: ${leg.duration.text}`;
					document.getElementById(infoElementId).innerText = info;
				} else {
					console.error("Gagal mengambil rute:", status);
				}
			});
		}
	});
</script>

<!-- javascript untuk menampilkan pasien proses -->
<script>
	document.addEventListener("DOMContentLoaded", function() {
		if (!canUseRealtimeMaps()) {
			return;
		}

		const directionsService = new google.maps.DirectionsService();
		const directionsRenderers = {}; // untuk menyimpan per modal

		<?php foreach ($konsultasi_proses_list->result() as $proses): ?>
			$('#detailModal<?= $proses->request_id ?>').on('shown.bs.modal', function() {
				const mapId = 'map<?= $proses->request_id ?>';
				const mapElement = document.getElementById(mapId);

				const pasienPos = {
					lat: parseFloat(<?= $proses->lattitude ?>),
					lng: parseFloat(<?= $proses->longitude ?>)
				};

				const map = new google.maps.Map(mapElement, {
					center: pasienPos,
					zoom: 13
				});

				const pasienMarker = new google.maps.Marker({
					position: pasienPos,
					map: map,
					title: "Pasien: <?= $proses->nama ?>"
				});

				const dokterUsername = "<?= $proses->dokter_id ?>";
				const dokterRef = firebase.database().ref('location/' + dokterUsername);

				let dokterMarker = null;
				let doctorPath = new google.maps.Polyline({
					path: [],
					geodesic: true,
					strokeColor: '#00BFFF',
					strokeOpacity: 0.8,
					strokeWeight: 4
				});
				doctorPath.setMap(map);

				directionsRenderers[<?= $proses->request_id ?>] = new google.maps.DirectionsRenderer({
					map: map,
					panel: document.getElementById('directionsPanel<?= $proses->request_id ?>')
				});

				dokterRef.on('value', function(snapshot) {
					const data = snapshot.val();
					if (data) {
						const lat = parseFloat(data.latitude);
						const lng = parseFloat(data.longitude);
						if (isNaN(lat) || isNaN(lng)) return;

						const dokterPos = {
							lat,
							lng
						};
						doctorPath.getPath().push(new google.maps.LatLng(lat, lng));

						if (!dokterMarker) {
							dokterMarker = new google.maps.Marker({
								position: dokterPos,
								map: map,
								title: "Dokter",
								icon: {
									url: 'https://img.icons8.com/emoji/48/ambulance-emoji.png',
									scaledSize: new google.maps.Size(50, 50),
									rotation: 0,
									anchor: new google.maps.Point(25, 25)
								}
							});

							map.fitBounds(new google.maps.LatLngBounds().extend(dokterPos).extend(pasienPos));
							updateRoute(dokterPos, pasienPos, directionsRenderers[<?= $proses->request_id ?>], 'routeInfo<?= $proses->request_id ?>');
						} else {

							// Perbarui posisi dan arah marker
							dokterMarker.setPosition(latLng);
							dokterMarker.setIcon({
								url: 'https://img.icons8.com/emoji/48/ambulance-emoji.png',
								scaledSize: new google.maps.Size(50, 50),
								rotation: bearing,
								anchor: new google.maps.Point(25, 25)
							});

							animateMarker(dokterMarker, dokterPos);
							updateRoute(dokterPos, pasienPos, directionsRenderers[<?= $proses->request_id ?>], 'routeInfo<?= $proses->request_id ?>');
						}
					}
				});
			});
		<?php endforeach; ?>

		function animateMarker(marker, toPosition) {
			const fromPosition = marker.getPosition();
			const deltaLat = (toPosition.lat - fromPosition.lat()) / 60;
			const deltaLng = (toPosition.lng - fromPosition.lng()) / 60;
			let i = 0;

			function moveMarker() {
				const lat = fromPosition.lat() + deltaLat * i;
				const lng = fromPosition.lng() + deltaLng * i;
				marker.setPosition(new google.maps.LatLng(lat, lng));
				if (i < 60) {
					i++;
					requestAnimationFrame(moveMarker);
				}
			}

			moveMarker();
		}

		function updateRoute(origin, destination, directionsRenderer, infoElementId) {
			directionsService.route({
				origin: origin,
				destination: destination,
				travelMode: google.maps.TravelMode.DRIVING
			}, function(response, status) {
				if (status === google.maps.DirectionsStatus.OK) {
					directionsRenderer.setDirections(response);
					const leg = response.routes[0].legs[0];
					const info = `Jarak: ${leg.distance.text}, Estimasi waktu: ${leg.duration.text}`;
					document.getElementById(infoElementId).innerText = info;
				} else {
					console.error("Gagal mengambil rute:", status);
				}
			});
		}
	});
</script>

<script>
	function loadRealtimeKonsultasi() {
		let tanggal = document.getElementById('datepicker').value;

		let tanggalAwal = '',
			tanggalAkhir = '';

		if (tanggal.includes('to')) {
			[tanggalAwal, tanggalAkhir] = tanggal.split(' to ');
		} else if (tanggal) {
			tanggalAwal = tanggal;
			tanggalAkhir = tanggal;
		}

		let url = "<?= site_url('home/get_realtime_konsultasi') ?>";
		if (tanggalAwal && tanggalAkhir) {
			url += "?start=" + tanggalAwal + "&end=" + tanggalAkhir;
		}

		fetch(url)
			.then(response => response.json())
			.then(data => {
				document.getElementById('total_konsultasi').textContent = data.total;
				document.getElementById('konsultasi_baru').textContent = data.baru;
				document.getElementById('konsultasi_proses').textContent = data.proses;
				// document.getElementById('konsultasi_selesai').textContent = data.selesai;
				// document.getElementById('konsultasi_selesai_total').textContent = data.total_selesai;

				const infoDiv = document.getElementById('konsultasi_selesai');
				var html = "";

				if (tanggal) {
					html += `${data.selesai} <span class="small text-muted">dari</span> ${data.total_selesai}`;
				} else {
					html += data.total_selesai;
				}

				$('#konsultasi_selesai').html(html)
			});
	}

	// Panggil pertama kali dan kemudian setiap 5 detik
	loadRealtimeKonsultasi();
	setInterval(loadRealtimeKonsultasi, 1000);

	// Ulangi pemanggilan saat tanggal berubah
	document.getElementById('datepicker').addEventListener('change', loadRealtimeKonsultasi);
</script>

<script>
	function loadKonsultasiBaru() {
		let tanggal = $('#datepicker').val(); // format yyyy-mm-dd

		$.ajax({
			url: "<?= site_url('home/get_konsultasi_baru_ajax'); ?>",
			type: "GET",
			data: {
				tanggal: tanggal
			}, // kirim tanggal ke backend
			dataType: "json",
			success: function(response) {
				let html = "";
				let total = response.length;

				response.forEach(function(item) {
					let date = new Date(item.created_at);
					let options = {
						weekday: 'long',
						year: 'numeric',
						month: 'long',
						day: 'numeric',
						hour: '2-digit',
						minute: '2-digit',
						second: '2-digit'
					};
					let formattedDate = date.toLocaleDateString('id-ID', options);

					html += `
					<a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#detailModal${item.request_id}">
						<div class="d-flex justify-content-between">
							<strong>${item.nama}</strong>
							<small class="text-muted">${item.nama_puskesmas}</small>
						</div>
						<small class="text-muted d-block">${formattedDate} - Tertunda: ${item.tertunda_jam} jam</small>
						<strong>${item.dokter}</strong>
					</a>`;
				});

				$('#list-konsultasi-baru').html(html);
				$('.baru').text(total);
			}
		});
	}

	// Trigger saat tanggal berubah
	$('#datepicker').on('change', function() {
		loadKonsultasiBaru();
	});

	// Load pertama
	loadKonsultasiBaru();

	setInterval(loadKonsultasiBaru, 1000);
</script>

<script>
	function loadKonsultasiProses() {

		let tanggal = $('#datepicker').val(); // format yyyy-mm-dd

		$.ajax({
			url: "<?= site_url('home/get_konsultasi_proses_ajax'); ?>",
			type: "GET",
			data: {
				tanggal: tanggal
			}, // kirim tanggal ke backend
			dataType: "json",
			success: function(response) {
				let html = "";
				let total = response.length;

				response.forEach(function(item) {
					// Format tanggal
					let date = new Date(item.created_at);
					let options = {
						weekday: 'long',
						year: 'numeric',
						month: 'long',
						day: 'numeric',
						hour: '2-digit',
						minute: '2-digit',
						second: '2-digit'
					};
					let formattedDate = date.toLocaleDateString('id-ID', options);

					html += `
                    <a href="#" class="list-group-item list-group-item-action" data-toggle="modal" data-target="#detailModal${item.request_id}">
                        <div class="d-flex justify-content-between">
                            <strong>${item.nama}</strong>
                            <small class="text-muted">${item.nama_puskesmas}</small>
                        </div>
                        <small class="text-muted d-block">
                            ${formattedDate} - Proses Konsultasi: ${item.tertunda_jam} jam
                        </small>
                        <strong>${item.dokter}</strong>
                    </a>
                `;
				});

				$('#list-konsultasi-proses').html(html);
				$('.proses').text(total);
			}
		});
	}

	// Trigger saat tanggal berubah
	$('#datepicker').on('change', function() {
		loadKonsultasiProses();
	});

	// Panggil pertama kali
	loadKonsultasiProses();

	// Lakukan polling tiap 5 detik
	setInterval(loadKonsultasiProses, 1000);
</script>

<script>
	function loadKonsultasiSelesai() {
		$.ajax({
			url: '<?= site_url('home/ajax_konsultasi_selesai') ?>',
			method: 'GET',
			dataType: 'json',
			success: function(res) {
				const container = $('#konsultasi-selesai-container');
				container.empty();

				let html = `<h6 class="text-uppercase font-weight-bold text-primary mb-1">Selesai</h6>
                        <h4 class="font-weight-bold text-gray-800 mb-0">${res.list.reduce((a, b) => a + parseInt(b.total_selesai), 0)}</h4>`;

				res.list.forEach(row => {
					const remark = row.remark;
					const jumlah_konsultasi = res.konsultasi_map[remark] || 0;
					const jumlah_kunjungan = res.kunjungan_map[remark] || 0;
					const persen = (row.total_selesai / res.total_konsultasi) * 100;
					html += `
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold">${row.nama_puskesmas}</span>
                            <span class="text-muted small">(${row.total_selesai} dari ${res.total_konsultasi})</span>
                        </div>
                        <div class="progress" style="height: 20px; width:100%; border-radius: 5px; background-color: #e9f7ef; border: 1px solid #d4edda;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: ${persen}%" aria-valuenow="${row.total_selesai}" aria-valuemin="0" aria-valuemax="${res.total_konsultasi}">
                                <span class="text-white font-weight-bold">${persen.toFixed(2)}%</span>
                            </div>
                        </div>
                        <small>Selesai Konsultasi: ${jumlah_konsultasi}</small><br>
                        <small>Kunjungan Nakes: ${jumlah_kunjungan}</small>
                    </div>`;
				});

				container.html(html);
			},
			error: function() {
				console.error('Gagal memuat data realtime konsultasi selesai');
			}
		});
	}

	$(document).ready(function() {
		loadKonsultasiSelesai();
		setInterval(loadKonsultasiSelesai, 1000); // refresh tiap 10 detik
	});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- <script>
	function loadDiagnosaChart() {
		$.ajax({
			url: "<?= site_url('home/get_top_diagnosa?limit=6'); ?>",
			type: "GET",
			dataType: "json",
			success: function(response) {
				console.log(response); // Debugging: tampilkan data yang diterima
				// return;
				const labels = [];
				const data = [];
				response.forEach(function(item) {
					labels.push(item.diagnosa);
					data.push(item.total);
				});

				if (window.diagnosaChartInstance) {
					window.diagnosaChartInstance.destroy();
				}

				const ctx = document.getElementById('diagnosaChart').getContext('2d');
				window.diagnosaChartInstance = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [{
							label: 'Jumlah Diagnosa',
							data: data,
							backgroundColor: [
								'#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'
							]
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: {
								display: false
							},
							tooltip: {
								enabled: true
							}
						},
						scales: {
							x: {
								title: {
									display: true,
									text: 'Diagnosa'
								}
							},
							y: {
								beginAtZero: true,
								title: {
									display: true,
									text: 'Jumlah'
								}
							}
						}
					}
				});
			}
		});
	}

	$(document).ready(function() {
		loadDiagnosaChart();
		// setInterval(loadDiagnosaChart, 10000); // refresh tiap 10 detik
	});
</script> -->

<script>
	$('#chartModal').on('shown.bs.modal', function() {
		if (window.diagnosaChartModalInstance) {
			window.diagnosaChartModalInstance.destroy();
		}
		$.ajax({
			url: "<?= site_url('home/get_top_diagnosa?limit=3'); ?>",
			type: "GET",
			dataType: "json",
			success: function(response) {
				const labels = [];
				const data = [];
				response.forEach(function(item) {
					labels.push(item.diagnosa);
					data.push(item.total);
				});
				const ctx = document.getElementById('diagnosaChartModal').getContext('2d');
				window.diagnosaChartModalInstance = new Chart(ctx, {
					type: 'bar',
					data: {
						labels: labels,
						datasets: [{
							label: 'Jumlah Diagnosa',
							data: data,
							backgroundColor: [
								'#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'
							]
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: {
								display: false
							},
							tooltip: {
								enabled: true
							}
						},
						scales: {
							x: {
								title: {
									display: true,
									text: 'Diagnosa'
								}
							},
							y: {
								beginAtZero: true,
								title: {
									display: true,
									text: 'Jumlah'
								}
							}
						}
					}
				});
			}
		});
	});
</script>
