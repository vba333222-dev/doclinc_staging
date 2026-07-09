<?php
$puskesmas_options = isset($puskesmas_options) ? $puskesmas_options : array();
$puskesmas_names = array();
foreach ($puskesmas_options as $puskesmas) {
	$puskesmas_names[(string) $puskesmas->kode_pkm] = $puskesmas->nama_puskesmas;
}
$account_rows = isset($data_dokter_nakes) ? $data_dokter_nakes->result() : array();
?>
<div class="doclinc-admin-page doclinc-akun-page">
	<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
		<h1 class="doclinc-page-title mb-0"><i class="fas fa-fw fa-hospital"></i> Akun Puskesmas</h1>
	</div>

	<div class="container-fluid">
		<div class="card shadow mb-4 doclinc-table-card doclinc-account-list-card">
			<div class="card-header py-3 d-flex align-items-center justify-content-between">
				<div>
					<h6 class="m-0 font-weight-bold">Daftar Akun Puskesmas</h6>
					<div class="doclinc-muted-text mt-1">Kelola akun login koordinasi Puskesmas.</div>
				</div>
				<button type="button" class="btn btn-sm btn-success shadow-sm rounded-pill doclinc-action-btn doclinc-action-primary" data-toggle="modal" data-target="#modalTambahDokterNakes">
					<i class="fas fa-plus-circle mr-1"></i> Tambah Akun Puskesmas
				</button>
			</div>
			<div class="card-body doclinc-table-body">
				<div class="doclinc-account-toolbar">
					<label for="accountCardSearch" class="sr-only">Cari akun Puskesmas</label>
					<input type="text" id="accountCardSearch" class="form-control doclinc-account-search" placeholder="Cari nama, username, email, atau Puskesmas">
				</div>

				<?php if (empty($account_rows)): ?>
					<div class="doclinc-account-empty">Belum ada akun Puskesmas.</div>
				<?php else: ?>
					<div class="doclinc-account-list" id="accountCardList">
						<?php foreach ($account_rows as $data): ?>
							<?php
							$kode_pkm = trim((string) ($data->remark ?? ''));
							$nama_pkm = $data->nama_puskesmas ?? ($puskesmas_names[$kode_pkm] ?? '');
							$puskesmas_status = $data->puskesmas_status ?? '';
							$is_active = ($data->status ?? '') === 'aktif';
							$search_text = strtolower(trim(implode(' ', array(
								$data->nama ?? '',
								$data->username ?? '',
								$data->email ?? '',
								$data->no_hp ?? '',
								$kode_pkm,
								$nama_pkm,
								$data->status ?? '',
							))));
							?>
							<div class="doclinc-account-card" data-search="<?= html_escape($search_text); ?>">
								<div class="doclinc-account-header">
									<div>
										<div class="doclinc-account-name"><?= html_escape($data->nama ?? '-'); ?></div>
										<div class="doclinc-account-meta">@<?= html_escape($data->username ?? '-'); ?></div>
									</div>
									<span class="doclinc-status-chip <?= $is_active ? 'is-active' : 'is-inactive'; ?>">
										<?= $is_active ? 'Aktif' : 'Nonaktif'; ?>
									</span>
								</div>

								<div class="doclinc-account-grid">
									<div class="doclinc-account-field">
										<span>Email</span>
										<strong class="doclinc-text-wrap"><?= html_escape($data->email ?? '-'); ?></strong>
									</div>
									<div class="doclinc-account-field">
										<span>No. Telepon</span>
										<strong><?= html_escape($data->no_hp ?? '-'); ?></strong>
									</div>
									<div class="doclinc-account-field doclinc-account-field-wide">
										<span>Puskesmas</span>
										<div class="doclinc-account-puskesmas">
											<span class="doclinc-code-chip"><?= html_escape($kode_pkm !== '' ? $kode_pkm : '-'); ?></span>
											<strong><?= html_escape($nama_pkm !== '' ? $nama_pkm : 'Belum terhubung ke Puskesmas aktif'); ?></strong>
										</div>
										<?php if ($kode_pkm !== '' && $puskesmas_status === 'nonaktif'): ?>
											<div class="doclinc-warning-text">Puskesmas nonaktif</div>
										<?php elseif ($nama_pkm === ''): ?>
											<div class="doclinc-muted-text">Periksa mapping kode Puskesmas akun ini.</div>
										<?php endif; ?>
									</div>
								</div>

								<div class="doclinc-account-actions">
									<button type="button" class="btn doclinc-action-btn" data-toggle="modal" data-target="#editModal<?= (int) $data->userId ?>">
										<i class="fas fa-edit"></i> Edit
									</button>
									<?php if ($is_active): ?>
										<form action="<?= site_url('kelola_dokter_nakes/nonaktifkan_user') ?>" method="post" class="d-inline">
											<input type="hidden" name="id_user" value="<?= html_escape($data->userId); ?>">
											<input type="hidden" name="remark_nonaktif" value="<?= html_escape($data->remark ?? ''); ?>">
											<button type="submit" class="btn doclinc-action-btn" onclick="return confirm('Nonaktifkan akun Puskesmas ini? Akun tidak dihapus dan dapat diaktifkan kembali.');">
												<i class="fas fa-ban"></i> Nonaktifkan Akun
											</button>
										</form>
									<?php else: ?>
										<form action="<?= site_url('kelola_dokter_nakes/aktifkan_user') ?>" method="post" class="d-inline">
											<input type="hidden" name="id_user" value="<?= html_escape($data->userId); ?>">
											<input type="hidden" name="remark_aktif" value="<?= html_escape($data->remark ?? ''); ?>">
											<button type="submit" class="btn doclinc-action-btn doclinc-action-primary" onclick="return confirm('Aktifkan akun Puskesmas ini?');">
												<i class="fas fa-check"></i> Aktifkan Akun
											</button>
										</form>
										<form action="<?= site_url('kelola_dokter_nakes/destroy_dokter_nakes') ?>" method="post" class="d-inline">
											<input type="hidden" name="id_user" value="<?= html_escape($data->userId); ?>">
											<button type="submit" class="btn doclinc-action-btn doclinc-action-danger doclinc-account-danger" onclick="return confirm('Hapus permanen akun ini? Aksi ini hanya boleh untuk akun test/tidak terpakai dan tidak dapat dibatalkan.');">
												<i class="fas fa-trash-alt"></i> Hapus Permanen
											</button>
										</form>
									<?php endif; ?>
								</div>
							</div>

							<div class="modal fade" id="editModal<?= (int) $data->userId ?>" tabindex="-1" role="dialog" aria-labelledby="editModalLabel<?= (int) $data->userId ?>" aria-hidden="true">
								<div class="modal-dialog modal-dialog-centered" role="document">
									<div class="modal-content border-0 shadow-sm">
										<div class="modal-header bg-info text-white">
											<h5 class="modal-title" id="editModalLabel<?= (int) $data->userId ?>">
												<i class="fas fa-hospital-user mr-2"></i>Edit Akun Puskesmas
											</h5>
											<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
												<span aria-hidden="true">&times;</span>
											</button>
										</div>
										<form action="<?= site_url('kelola_dokter_nakes/update/' . rawurlencode($data->userId)) ?>" method="post">
											<input type="hidden" name="old_photo" value="<?= html_escape($data->foto ?? '') ?>">
											<div class="form-group mt-3 px-3">
												<div class="row">
													<div class="col-md-3">
														<img src="<?= doclinc_safe_profile_image_src($data->foto ?? '') ?>" class="img-thumbnail rounded-circle" alt="Foto Profil">
													</div>
													<div class="col-md-9">
														<input type="file" class="form-control-file" name="photo">
														<small class="text-muted">Upload foto baru atau biarkan kosong untuk menggunakan foto yang ada</small>
													</div>
												</div>
											</div>
											<div class="modal-body bg-light rounded mx-3">
												<div class="form-group">
													<label class="text-info"><i class="fas fa-user-md mr-1"></i> Nama Lengkap</label>
													<input type="text" class="form-control rounded-pill border-info" name="nama" value="<?= html_escape($data->nama ?? '') ?>" required>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-envelope mr-1"></i> Email</label>
													<input type="email" class="form-control rounded-pill border-info" name="email" value="<?= html_escape($data->email ?? '') ?>" required>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-user mr-1"></i> Username</label>
													<input type="text" class="form-control rounded-pill border-info" name="username" value="<?= html_escape($data->username ?? '') ?>" required>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-clinic-medical mr-1"></i> Puskesmas</label>
													<?php if (!empty($puskesmas_options)): ?>
														<select class="form-control rounded-pill border-info" name="remark" required>
															<?php foreach ($puskesmas_options as $puskesmas): ?>
																<option value="<?= html_escape($puskesmas->kode_pkm); ?>" <?= (string) ($data->remark ?? '') === (string) $puskesmas->kode_pkm ? 'selected' : ''; ?>>
																	<?= html_escape($puskesmas->nama_puskesmas); ?> (<?= html_escape($puskesmas->kode_pkm); ?>)
																</option>
															<?php endforeach; ?>
														</select>
													<?php else: ?>
														<input type="text" class="form-control rounded-pill border-info" name="remark" value="<?= html_escape($data->remark ?? 'DEFAULT') ?>" required>
													<?php endif; ?>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-phone-alt mr-1"></i> Nomor Telepon</label>
													<input type="text" class="form-control rounded-pill border-info" name="no_hp" value="<?= html_escape($data->no_hp ?? '') ?>">
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-toggle-on mr-1"></i> Status</label>
													<select class="form-control rounded-pill border-info" name="status">
														<option value="aktif" <?= ($data->status ?? '') === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
														<option value="nonaktif" <?= ($data->status ?? '') === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
													</select>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-lock mr-1"></i> Password Baru</label>
													<input type="password" class="form-control rounded-pill border-info" name="password" minlength="8" autocomplete="new-password">
													<small class="text-muted">Biarkan kosong jika tidak ingin mengganti password.</small>
												</div>
												<div class="form-group">
													<label class="text-info"><i class="fas fa-lock mr-1"></i> Konfirmasi Password Baru</label>
													<input type="password" class="form-control rounded-pill border-info" name="confirm_password" minlength="8" autocomplete="new-password">
												</div>
											</div>
											<div class="modal-footer border-0 px-4 pb-4">
												<button type="button" class="btn btn-light rounded-pill px-4 border" data-dismiss="modal">
													<i class="fas fa-times-circle mr-1"></i>Batal
												</button>
												<button type="submit" class="btn btn-info rounded-pill px-4">
													<i class="fas fa-check-circle mr-1"></i>Simpan
												</button>
											</div>
										</form>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="doclinc-account-empty d-none" id="accountCardSearchEmpty">Belum ada akun Puskesmas.</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modalTambahDokterNakes" tabindex="-1" role="dialog" aria-labelledby="modalTambahDokterNakesLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-sm">
			<div class="modal-header bg-success text-white">
				<h5 class="modal-title" id="modalTambahDokterNakesLabel">
					<i class="fas fa-user-plus mr-2"></i>Tambah Akun Puskesmas
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('kelola_dokter_nakes/store') ?>" method="post">
				<div class="modal-body bg-light">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-user-md mr-1"></i> Nama Lengkap</label>
								<input type="text" class="form-control rounded-pill border-success" name="nama" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-envelope mr-1"></i> Email</label>
								<input type="email" class="form-control rounded-pill border-success" name="email" required>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-user mr-1"></i> Username</label>
								<input type="text" class="form-control rounded-pill border-success" name="username" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-phone-alt mr-1"></i> No HP</label>
								<input type="text" class="form-control rounded-pill border-success" name="no_hp">
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-lock mr-1"></i> Password</label>
								<input type="password" class="form-control rounded-pill border-success" name="password" minlength="8" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-lock mr-1"></i> Konfirmasi Password</label>
								<input type="password" class="form-control rounded-pill border-success" name="confirm_password" minlength="8" required>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-clinic-medical mr-1"></i> Puskesmas</label>
								<?php if (!empty($puskesmas_options)): ?>
									<select class="form-control rounded-pill border-success" name="remark">
										<?php foreach ($puskesmas_options as $puskesmas): ?>
											<option value="<?= html_escape($puskesmas->kode_pkm); ?>">
												<?= html_escape($puskesmas->nama_puskesmas); ?> (<?= html_escape($puskesmas->kode_pkm); ?>)
											</option>
										<?php endforeach; ?>
									</select>
								<?php else: ?>
									<input type="text" class="form-control rounded-pill border-success" name="remark" value="DEFAULT">
									<small class="text-muted">Fallback sementara jika master puskesmas aktif belum tersedia.</small>
								<?php endif; ?>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success"><i class="fas fa-toggle-on mr-1"></i> Status</label>
								<select class="form-control rounded-pill border-success" name="status">
									<option value="aktif">Aktif</option>
									<option value="nonaktif">Nonaktif</option>
								</select>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer border-0 px-4 pb-4">
					<button type="button" class="btn btn-light rounded-pill px-4 border" data-dismiss="modal">
						<i class="fas fa-times-circle mr-1"></i>Batal
					</button>
					<button type="submit" class="btn btn-success rounded-pill px-4">
						<i class="fas fa-check-circle mr-1"></i>Simpan
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		var searchInput = document.getElementById('accountCardSearch');
		var cards = Array.prototype.slice.call(document.querySelectorAll('.doclinc-account-card'));
		var emptyState = document.getElementById('accountCardSearchEmpty');
		if (!searchInput || cards.length === 0) {
			return;
		}

		searchInput.addEventListener('input', function() {
			var query = searchInput.value.toLowerCase().trim();
			var visibleCount = 0;

			cards.forEach(function(card) {
				var haystack = card.getAttribute('data-search') || '';
				var visible = query === '' || haystack.indexOf(query) !== -1;
				card.classList.toggle('d-none', !visible);
				if (visible) {
					visibleCount++;
				}
			});

			if (emptyState) {
				emptyState.classList.toggle('d-none', visibleCount !== 0);
			}
		});
	});
</script>
