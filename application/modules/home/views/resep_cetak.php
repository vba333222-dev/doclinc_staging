<!DOCTYPE html>
<html>

<head>
	<meta charset="UTF-8">
	<title>Resep dokter</title>
	<style>
		body {
			font-family: Arial, sans-serif;
			margin: 20px;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 10px;
		}

		th,
		td {
			border: 1px solid #000;
			padding: 8px;
			text-align: left;
		}

		h2 {
			text-align: center;
		}
	</style>
</head>

<body>
	<h2>Resep dokter</h2>

	<p><strong>Tanggal:</strong> <?= $resep->tanggal ?></p>
	<p><strong>Dokter:</strong> <?= $resep->nama_dokter ?></p>
	<p><strong>Keluhan:</strong> <?= $resep->keluhan ?></p>
	<p><strong>Diagnosis:</strong> <?= $resep->diagnosa ?></p>
	<p><strong>Saran:</strong> <?= $resep->saran_dokter ?></p>

	<h4>Obat dan terapi</h4>
	<table>
		<thead>
			<tr>
				<th>No</th>
				<th>Nama terapi</th>
				<th>Keterangan</th>
			</tr>
		</thead>
		<tbody>
			<?php $no = 1;
			foreach ($resep->terapi_list as $terapi): ?>
				<tr>
					<td><?= $no++ ?></td>
					<td><?= $terapi->terapi ?></td>
					<td><?= $terapi->signa ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<script>
		window.onload = function() {
			window.print();
			setTimeout(() => window.close(), 500);
		}
	</script>
</body>

</html>
