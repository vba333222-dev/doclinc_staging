<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-comments"></i> Laporan Detail</h1>
</div>
<!-- Content Row -->
<div class="row">
	<div class="col">
		<div class="card shadow-sm">
			<div class="card-body table-responsive">
				<table class="table table-bordered" id="tbl_konsultasi" style="width:100%" cellspacing="0">
					<thead class="thead-light">
						<tr class="bg-info text-black">
							<th class="text-center" width="5%"><i class="fas fa-list-ol"></i></th>
							<th><i class="fas fa-id-badge"></i> ID</th>
							<th><i class="fas fa-comment-medical"></i> Keluhan Warga</th>
							<th><i class="fas fa-user"></i> Nama Warga</th>
							<th><i class="fas fa-map-marked-alt"></i> Puskesmas</th>
							<th><i class="fas fa-map-marker-alt"></i> Alamat</th>
							<th><i class="fas fa-calendar-alt"></i> Tanggal</th>
							<th><i class="fas fa-info-circle"></i> Status</th>
							<th><i class="fas fa-list"></i> Kriteria</th>
							<th><i class="fas fa-stethoscope"></i> Diagnosa</th>
							<th><i class="fas fa-notes-medical"></i> Saran Dokter</th>
							<th><i class="fas fa-image"></i> Foto</th>
							<th><i class="fas fa-user-md"></i> Nama Dokter</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$no = 0;
						foreach ($data_konsultasi as $row):
							$no++;
						?>
							<tr>
								<td><?= $no; ?></td>
								<td><?= $row->request_id; ?></td>
								<td><?= $row->request_description; ?></td>
								<td><?= $row->nama_warga; ?></td>
								<td><?= $row->puskesmas; ?></td>
								<td><?= $row->location; ?></td>
								<td><?= date('d-m-Y', strtotime($row->date)); ?></td>
								<td><?php
									$status = $row->request_status;
									$status_badge = '';
									switch ($status) {
										case 'Pending':
											$status_badge = '<span class="badge badge-warning"><i class="fas fa-hourglass-half"></i> Pending</span>';
											break;
										case 'Accepted':
											$status_badge = '<span class="badge badge-primary"><i class="fas fa-check-circle"></i> Accepted</span>';
											break;
										case 'Completed':
											$status_badge = '<span class="badge badge-success"><i class="fas fa-check"></i> Completed</span>';
											break;
										case 'Cancelled':
											$status_badge = '<span class="badge badge-danger"><i class="fas fa-times"></i> Cancelled</span>';
											break;
										default:
											$status_badge = '<span class="badge badge-secondary">Unknown</span>';
											break;
									}
									echo $status_badge;
									?>
								</td>
								<td><?= $row->kriteria ?></td>
								<td><?= $row->diagnosa; ?></td>
								<td><?= $row->saran; ?></td>
								<td>
									<img src="<?= base_url('../uploads/' . $row->foto) ?>" alt="Tidak ada foto" class="img-thumbnail" width="100" height="100">
								</td>
								<td><?= $row->nama_dokter; ?></td>
							</tr>
						<?php endforeach ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
	$(document).ready(function() {
		$('#tbl_konsultasi').DataTable();
	});
</script>
