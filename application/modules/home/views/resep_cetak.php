<!DOCTYPE html>
<html lang="id">

<head>
	<meta charset="UTF-8">
	<title>Resep dokter</title>
	<style>
		body {
			font-family: "Manrope", "Segoe UI", Arial, sans-serif;
			margin: 24px;
			color: #1F2A24;
			font-size: 14px;
			line-height: 1.5;
		}

		h1 {
			margin: 0 0 4px;
			font-size: 20px;
			font-weight: 800;
		}

		.doclinc-resep-note {
			margin: 0 0 18px;
			color: #4E5F57;
			font-size: 13px;
		}

		.doclinc-resep-field {
			margin: 0 0 6px;
		}

		.doclinc-resep-field strong {
			display: inline-block;
			min-width: 92px;
		}

		h2 {
			margin: 20px 0 8px;
			font-size: 15px;
			font-weight: 800;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 6px;
		}

		th,
		td {
			border: 1px solid #4E5F57;
			padding: 8px;
			text-align: left;
			vertical-align: top;
		}

		th {
			background: #F4F8F6;
			font-size: 13px;
			text-transform: uppercase;
			letter-spacing: .02em;
		}

		.doclinc-resep-empty {
			color: #64736B;
			font-style: italic;
		}

		.doclinc-resep-footer {
			margin-top: 22px;
			padding-top: 12px;
			border-top: 1px solid #E7ECE9;
			color: #64736B;
			font-size: 12px;
		}

		/* Aturan cetak: margin halaman diatur di sini, kepala tabel diulang di
		 * setiap halaman, dan satu baris obat tidak pernah terpotong dua halaman. */
		@media print {
			body { margin: 0; }
			@page { margin: 16mm; }
			thead { display: table-header-group; }
			tr { break-inside: avoid; page-break-inside: avoid; }
		}
	</style>
</head>

<body>
	<!-- Semua nilai di halaman ini berasal dari basis data (keluhan pasien dan
	     catatan dokter), jadi WAJIB di-escape. Sebelumnya dicetak mentah, sehingga
	     tanda kurung siku pada keluhan bisa merusak tata letak cetak — dan bila
	     berisi tag skrip, ikut dieksekusi di jendela cetak. -->
	<h1>Resep dokter</h1>
	<p class="doclinc-resep-note">Dokumen ini dicetak dari DocLink dan hanya untuk keperluan pengobatan pasien.</p>

	<p class="doclinc-resep-field"><strong>Tanggal:</strong> <?= html_escape((string) $resep->tanggal) ?></p>
	<p class="doclinc-resep-field"><strong>Dokter:</strong> <?= html_escape((string) $resep->nama_dokter) ?></p>
	<p class="doclinc-resep-field"><strong>Keluhan:</strong> <?= nl2br(html_escape((string) $resep->keluhan)) ?></p>
	<p class="doclinc-resep-field"><strong>Diagnosis:</strong> <?= nl2br(html_escape((string) $resep->diagnosa)) ?></p>
	<p class="doclinc-resep-field"><strong>Saran:</strong> <?= nl2br(html_escape((string) $resep->saran_dokter)) ?></p>

	<h2>Obat dan terapi</h2>
	<?php $doclinc_terapi = is_iterable($resep->terapi_list) ? $resep->terapi_list : array(); ?>
	<table>
		<thead>
			<tr>
				<th scope="col">No</th>
				<th scope="col">Nama terapi</th>
				<th scope="col">Keterangan</th>
			</tr>
		</thead>
		<tbody>
			<?php if (empty($doclinc_terapi)): ?>
				<tr>
					<td colspan="3" class="doclinc-resep-empty">Belum ada obat atau terapi yang tercatat.</td>
				</tr>
			<?php else: ?>
				<?php $no = 1;
				foreach ($doclinc_terapi as $terapi): ?>
					<tr>
						<td><?= (int) $no++ ?></td>
						<td><?= html_escape((string) $terapi->terapi) ?></td>
						<td><?= nl2br(html_escape((string) $terapi->signa)) ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<p class="doclinc-resep-footer">Dicetak dari DocLink pada <?= html_escape(date('d/m/Y H:i')) ?>.</p>

	<script>
		window.onload = function() {
			window.print();
			setTimeout(() => window.close(), 500);
		}
	</script>
</body>

</html>
