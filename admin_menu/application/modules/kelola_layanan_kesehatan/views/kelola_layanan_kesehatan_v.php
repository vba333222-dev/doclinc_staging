<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
	<h1 class="h3 mb-0 font-weight-bold"><i class="fas fa-fw fa-comments"></i> Kelola layanan kesehatan</h1>
</div>
<!-- Content Row -->
<div class="row">
	<div class="col">
		<div class="card shadow-sm">
			<div class="card-body table-responsive">
				<table class="table table-bordered" id="tbl_konsultasi" style="width:100%" cellspacing="0">
					<thead>
						<th>No.</th>
						<th>ID </th>
						<th>Keluhan</th>
						<th>Tanggal</th>
						<th>Status</th>
					</thead>
					<tbody>
						<?php
						$no = 0;
						foreach ($data_konsultasi as $row):
							$no++;
						?>
							<tr>
								<td><?= $no; ?></td>
								<td><?= html_escape($row->request_id ?? '-'); ?></td>
								<td><?= html_escape($row->request_description ?? '-'); ?></td>
								<td><?= html_escape($row->date ?? '-'); ?></td>
								<td><?php
									$status = $row->request_status ?? '';
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
		$('#tbl_konsultasi').DataTable({
			language: {
				lengthMenu: 'Tampilkan _MENU_ data',
				search: 'Cari:',
				emptyTable: 'Tidak ada data',
				zeroRecords: 'Tidak ada data sesuai filter',
				info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
				infoEmpty: 'Menampilkan 0 data',
				infoFiltered: '(difilter dari _MAX_ total data)',
				paginate: {
					previous: 'Sebelumnya',
					next: 'Berikutnya'
				}
			}
		});
	});
</script>
