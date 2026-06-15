<?php
$kriteria = isset($_GET['kriteria']) ? $_GET['kriteria'] : 0;

if ($kriteria == 0) {
	$kriteria = 'Selesai Konsultasi';
} elseif ($kriteria == 1) {
	$kriteria = 'Kunjungan Nakes';
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
		.diagnosa-container {
			display: flex;
			flex-wrap: wrap;
		}

		.diagnosa-item {
			display: flex;
			align-items: center;
			width: calc(50% - 10px);
			/* 2 kolom jika lebih dari 5 item */
			padding: 8px;
			border-bottom: 1px solid #ddd;
		}

		.diagnosa-item label {
			flex-grow: 1;
			font-size: 16px;
		}

		.diagnosa-item input[type="checkbox"] {
			width: 18px;
			height: 18px;
			accent-color: gray;
		}

		.card {
			width: 100%;
		}

		.form-control,
		.form-select {
			width: 100%;
		}

		/* Jika kurang dari 5 data, tampil ke bawah */
		.diagnosa-container.few-items .diagnosa-item {
			width: 100%;
		}

		.btn {
			border: none;
			padding: 5px 10px;
			cursor: pointer;
			font-size: 16px;
			border-radius: 5px;
		}

		.btn-add {
			background-color: gray;
			color: white;
		}

		.btn-remove {
			background-color: gray;
			color: white;
		}

		.ui-autocomplete {
			z-index: 1056 !important;
			/* Modal Bootstrap biasanya z-index 1050-1055 */
		}

		.terapi-autocomplete {
			max-width: 80px;
			/* atur lebar maksimum kolom */
			white-space: nowrap;
			/* jangan bungkus ke baris baru */
			overflow: hidden;
			/* sembunyikan yang melebihi lebar */
			text-overflow: ellipsis;
			/* tampilkan titik-titik (...) */
		}

		.terapi-autocomplete:hover {
			white-space: normal;
			overflow: visible;
			position: relative;
			z-index: 1;
			background: #fff;
		}
	</style>
</head>

<body class="bg-light">
	<div class="backtohome">
		<a href="<?= base_url('home_nakes'); ?>">
			<i class="fas fa-arrow-left"></i>
		</a>
		<span class="ms-auto">Pemeriksaan Pasien</span>
	</div>
	<div class="hero bg-success px-3 pb-3 overflow-hidden">
		<div class="d-flex align-items-start animate__animated animate__fadeInUp animate__faster">
			<img class="rounded-4 shadow" src="<?= base_url(); ?>assets/images/rahmat.jpg" width="80px" height="80px">
			<div class="ms-2 text-white">
				<p class="mb-0"><b><?= strtoupper($nama_pasien); ?></b> <?= $umur ?> Tahun</p>
				<p class="mb-0"><b>Keluhan</b> :
					<?php
					$CI = &get_instance();
					$CI->load->library('encryption');
					$keluhan = $CI->encryption->decrypt(base64_decode($keluhan));
					echo $keluhan;
					?>
				</p>
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
	<div class="content animate__animated animate__fadeInUp animate__faster" style="padding: 15px;">
		<input type="hidden" name="userid" id="userId" value="<?= $userid ?>">
		<input type="hidden" name="dokterid" id="dokterId" value="<?= $_SESSION['id'] ?>">
		<form id="form_konsul_nakes" enctype="multipart/form-data">
			<input type="hidden" name="request_id" id="idReq" value="<?= $request_id ?>">
			<div class="form-floating mb-3">
				<input type="text" id="diagnosa" name="diagnosa" class="form-control shadow-sm border-success" placeholder="Diagnosa" required>
				<label for="diagnosa"><i class="bi bi-heart-pulse"></i> Diagnosa*</label>
			</div>
			<div class="card shadow-sm mb-3 border border-success">
				<div class="card-header bg-success text-white d-flex align-items-center">
					<p class="mb-0"><i class="bi bi-capsule"></i> Terapi*</p>
				</div>
				<div class="card-body">
					<div class="table-responsive">
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
											<option value="1x sehari">1x sehari</option>
											<option value="2x sehari">2x sehari</option>
											<option value="3x sehari">3x sehari</option>
											<option value="4x sehari">4x sehari</option>
										</select>
									</td>
									<td>
										<select class="form-select form-select-sm border-success">
											<option value="Sesudah makan">Sesudah makan</option>
											<option value="Sebelum makan">Sebelum makan</option>
										</select>
									</td>
									<td contenteditable="true">Masukkan keterangan</td>
									<td>
										<button type="button" class="btn btn-success btn-sm" onclick="tambahBaris(this)">
											<i class="bi bi-plus-circle"></i>
										</button>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="form-floating mb-3">
				<textarea id="saran" name="saran" class="form-control shadow-sm border-success" placeholder="Saran" style="height: 200px"></textarea>
				<label for="saran"><i class="bi bi-chat-dots"></i> Saran*</label>
			</div>
			<div class="form-floating mb-3">
				<input type="text" id="kriteria" name="kriteria" class="form-control shadow-sm border-success" value="<?= $kriteria ?>" readonly>
				<label for="kriteria"><i class="bi bi-clipboard-check"></i> Kriteria*</label>
			</div>
			<?php if ($kriteria === 'Kunjungan Nakes') : ?>
				<div class="form-floating mb-3">
					<input type="file" class="form-control shadow-sm border-success" id="file" name="file" accept="image/*">
					<label for="file"><i class="bi bi-camera"></i> Foto bersama pasien*</label>
					<img id="preview-image" src="#" alt="Preview Foto" style="display:none; max-width: 200px; margin-top: 10px;" class="img-thumbnail" />
				</div>
			<?php endif; ?>
			<div class="form-floating mb-3">
				<input type="text" class="form-control shadow-sm border-success" id="rujukan" name="rujukan" placeholder="Rujukan">
				<label for="rujukan"><i class="bi bi-arrow-right-circle"></i> Saran Rujukan*</label>
			</div>
			<div class="d-grid">
				<button type="button" class="btn btn-success shadow-sm" id="save_konsul_nakes">
					<i class="bi bi-save"></i> Simpan
				</button>
			</div>
		</form>
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
	<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBTfv2in7EP1cLT71-bVC-66SZsrg4Kr5w"></script>

	<!-- firebase dan notifikasi -->
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-messaging.js"></script>
	<script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-database.js"></script>
	<script src="https://idbcs.net/cilegon_bersatu/firebase/firebase-config.js"></script>
	<script src="https://idbcs.net/cilegon_bersatu/firebase/get-notif.js"></script>

	<!-- save konsultasi -->
	<script>
		$('#save_konsul_nakes').click(function() {
			const userId = document.getElementById('userId').value;
			const dokterId = document.getElementById('dokterId').value;
			const idReq = document.getElementById('idReq').value;

			var form = document.getElementById('form_konsul_nakes');
			var formData = new FormData(form);

			// Hilangkan tombol kirim selama proses berlangsung
			$('#save_konsul_nakes').prop('disabled', true).text('Mengirim...');


			// Ambil terapi dari tabel
			var terapiData = [];
			$("#tabelTerapi tbody tr").each(function(index, row) {
				var terapi = $(row).find("td:eq(1)").text().trim();
				var signa = $(row).find("td:eq(2) select").val(); // Jumlah Obat
				var caraMinum = $(row).find("td:eq(3) select").val(); // Cara Minum
				var keterangan = $(row).find("td:eq(4)").text().trim();

				if (terapi !== "") {
					terapiData.push({
						terapi: terapi,
						jumlah: signa,
						cara: caraMinum,
						keterangan: keterangan
					});
				}
			});

			// Tambahkan data terapi dalam bentuk string JSON
			formData.append("terapi", JSON.stringify(terapiData));

			$.ajax({
				url: "<?php echo base_url(); ?>konsultasi_nakes/save_konsultasi_nakes",
				method: "POST",
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					console.log("Response:", response);
					if (typeof response === 'string') {
						try { response = JSON.parse(response); } catch(e) {}
					}
					if (response.status === 'success') {
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
							const munculPopUpWarga = firebase.database().ref('rating').push();
							munculPopUpWarga.set({
								idReq: idReq,
								idUser: userId,
								idDokter: dokterId,
								timestamp: Date.now()
							}).then(() => {
								if (result.dismiss === Swal.DismissReason.timer) {
									window.location.href = '../../home_nakes#riwayat_konsul_selesai';
								}
							}).catch((error) => {
								console.error('Gagal menyimpan ke Firebase:', error);
								Swal.fire('Gagal', 'Tidak bisa menyimpan ke Firebase.', 'error');
							})
						});
					} else {
						Swal.fire("Gagal!", response.message, "error");
					}
				},
				error: function(xhr, status, error) {
					console.error("Error:", xhr.responseText);
					Swal.fire("Gagal!", "Terjadi kesalahan AJAX", "error");
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
		function tambahBaris(button) {
			let row = button.parentElement.parentElement;
			let terapi = row.cells[1].innerText.trim();
			let keterangan = row.cells[2].innerText.trim();

			// Cek apakah semua kolom terisi sebelum menambah baris baru
			if (terapi === "" || keterangan === "") {
				alert("Harap isi semua kolom sebelum menambahkan baris baru!");
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

			cellJumlahObat.innerHTML = `
				<select class="form-select form-select-sm">
					<option value="1x sehari">1x sehari</option>
					<option value="2x sehari">2x sehari</option>
					<option value="3x sehari">3x sehari</option>
					<option value="4x sehari">4x sehari</option>
				</select>
			`;

			cellCaraMinum.innerHTML = `
				<select class="form-select form-select-sm">
					<option value="Sesudah makan">Sesudah makan</option>
					<option value="Sebelum makan">Sebelum makan</option>
				</select>
			`;

			cellKeterangan.contentEditable = "true";

			// Tambahkan tombol tambah & hapus di baris baru
			let addButton = document.createElement("button");
			addButton.innerHTML = '<i class="bi bi-plus-circle"></i>'; // Add icon
			addButton.className = "btn btn-success btn-sm";
			addButton.type = "button";
			addButton.setAttribute("onclick", "tambahBaris(this)");

			let removeButton = document.createElement("button");
			removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
			removeButton.className = "btn btn-danger btn-sm";
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
					addButton.className = "btn btn-success btn-sm";
					addButton.type = "button";
					addButton.setAttribute("onclick", "tambahBaris(this)");
					aksiCell.appendChild(addButton);

					let removeButton = document.createElement("button");
					removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
					removeButton.className = "btn btn-danger btn-sm";
					removeButton.type = "button";
					removeButton.setAttribute("onclick", "hapusBaris(this)");
					aksiCell.appendChild(removeButton);
				} else {
					// Baris lainnya hanya memiliki tombol hapus
					let removeButton = document.createElement("button");
					removeButton.innerHTML = '<i class="bi bi-dash-circle"></i>'; // Remove icon
					removeButton.className = "btn btn-danger btn-sm";
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
