<?php
/* Daftar staf yang disematkan di halaman detail Puskesmas.
   Tanpa kepala halaman, tanpa filter, tanpa modal: halaman hospes sudah punya
   judul dan kartu daftarnya sendiri. Satu tautan menuju halaman staf lengkap
   untuk pekerjaan yang butuh formulir (tambah, ubah, tautkan akun, mutasi). */
$staff_rows = isset($staff_rows) && is_array($staff_rows) ? $staff_rows : array();
$table_ready = !empty($table_ready);
$embedded_kode_pkm = isset($embedded_kode_pkm) ? (string) $embedded_kode_pkm : '';
?>
<div class="card shadow mb-4">
	<div class="card-header py-3 d-flex align-items-center justify-content-between">
		<h2 class="h6 m-0 font-weight-bold">
			<i class="fas fa-users-cog mr-2" aria-hidden="true"></i>Staf Puskesmas
			<span class="text-muted font-weight-normal">(<?= html_escape((string) count($staff_rows)); ?> baris)</span>
		</h2>
		<a class="btn btn-sm btn-outline-secondary" href="<?= html_escape(site_url('kelola_staff_puskesmas' . ($embedded_kode_pkm === '' ? '' : '?kode_pkm=' . rawurlencode($embedded_kode_pkm)))); ?>">
			<i class="fas fa-external-link-alt mr-1" aria-hidden="true"></i>Buka pengelolaan staf
		</a>
	</div>
	<div class="card-body">
		<?php if (!$table_ready): ?>
			<p class="mb-0 text-muted">Data staf belum tersedia pada penyimpanan data saat ini.</p>
		<?php elseif (empty($staff_rows)): ?>
			<p class="mb-0 text-muted">
				Belum ada staf terdaftar untuk Puskesmas ini. Tambahkan lewat halaman pengelolaan staf.
			</p>
		<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm table-hover mb-0">
					<thead>
						<tr>
							<th scope="col">Nama</th>
							<th scope="col">Profesi</th>
							<th scope="col">Nomor SIP</th>
							<th scope="col">Akun login</th>
							<th scope="col">Status</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($staff_rows as $staff_row): ?>
							<?php
							$staff_name = trim((string) ($staff_row->nama ?? ''));
							$staff_gelar = isset($staff_row->gelar) ? trim((string) $staff_row->gelar) : '';
							$account_label = trim((string) ($staff_row->akun_nama ?? ''));
							if ($account_label === '') {
								$account_label = trim((string) ($staff_row->akun_username ?? ''));
							}
							$staff_status = strtolower(trim((string) ($staff_row->status ?? '')));
							?>
							<tr>
								<td>
									<?= html_escape($staff_name !== '' ? $staff_name : '-'); ?>
									<?php if ($staff_gelar !== ''): ?>
										<span class="text-muted">, <?= html_escape($staff_gelar); ?></span>
									<?php endif; ?>
								</td>
								<td><?= html_escape((string) ($staff_row->profesi ?? '-')); ?></td>
								<td><?= html_escape((string) (($staff_row->nomor_sip ?? '') !== '' ? $staff_row->nomor_sip : '-')); ?></td>
								<td>
									<?php if ($account_label !== ''): ?>
										<?= html_escape($account_label); ?>
									<?php else: ?>
										<span class="text-muted">Belum tertaut</span>
									<?php endif; ?>
								</td>
								<td>
									<span class="badge <?= $staff_status === 'aktif' ? 'badge-success' : 'badge-secondary'; ?>">
										<?= html_escape($staff_status === 'aktif' ? 'Aktif' : 'Nonaktif'); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
