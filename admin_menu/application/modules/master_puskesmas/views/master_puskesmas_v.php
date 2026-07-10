<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title"><i class="fas fa-fw fa-hospital"></i> Master Puskesmas</h1>
		<div class="text-white-50 doclinc-page-subtitle">Kelola data Puskesmas aktif yang digunakan untuk routing dan koordinasi layanan Doclinc.</div>
	</div>
</div>

<div class="container-fluid">
	<?php if ($this->session->flashdata('success')): ?>
		<div class="alert alert-success shadow-sm"><?= html_escape($this->session->flashdata('success')); ?></div>
	<?php endif; ?>
	<?php if ($this->session->flashdata('error')): ?>
		<div class="alert alert-danger shadow-sm"><?= html_escape($this->session->flashdata('error')); ?></div>
	<?php endif; ?>

	<div class="card shadow mb-4 doclinc-table-card">
		<div class="card-header py-3 d-flex align-items-center justify-content-between">
			<h6 class="m-0 font-weight-bold text-primary">Daftar Puskesmas</h6>
			<button type="button" class="btn btn-sm btn-success shadow-sm rounded-pill" data-toggle="modal" data-target="#modalTambahPuskesmas">
				<i class="fas fa-plus-circle mr-1"></i> Tambah Puskesmas
			</button>
		</div>
		<div class="card-body">
			<?php if ($puskesmas && $puskesmas->num_rows() > 0): ?>
				<div class="doclinc-admin-card-toolbar">
					<label for="puskesmasCardSearch">Cari Puskesmas</label>
					<input type="search" id="puskesmasCardSearch" class="form-control doclinc-account-search" placeholder="Nama, kode, alamat, atau status">
				</div>
				<?php $edit_modals = ''; ?>
				<div class="doclinc-admin-card-grid">
					<?php foreach ($puskesmas->result() as $row): ?>
								<?php
								$is_default = strtoupper((string) ($row->kode_pkm ?? '')) === 'DEFAULT';
								$status = (string) ($row->status ?? '');
								$status_label = $is_default ? 'Legacy / Fallback' : ($status === 'aktif' ? 'Aktif' : 'Nonaktif');
								$display_name = $is_default ? 'Legacy / Belum terklasifikasi' : ($row->nama_puskesmas ?? '-');
								$search_text = implode(' ', array($display_name, $row->kode_pkm ?? '', $row->alamat ?? '', $row->latitude ?? '', $row->longitude ?? '', $status_label));
								?>
							<article class="doclinc-admin-card" data-puskesmas-search="<?= html_escape($search_text); ?>">
								<div class="doclinc-admin-card__head">
									<div>
										<h3 class="doclinc-admin-card__title"><?= html_escape($display_name); ?></h3>
										<div class="doclinc-admin-card__role">Kode: <?= html_escape($row->kode_pkm ?? '-'); ?></div>
									</div>
									<span class="doclinc-admin-badge <?= $status === 'aktif' && !$is_default ? 'is-active' : ($is_default ? 'is-warning' : 'is-inactive'); ?>">
										<?= html_escape($status_label); ?>
									</span>
								</div>

								<div class="doclinc-admin-card__body">
									<div class="doclinc-admin-card__meta doclinc-admin-card__meta--wide">
										<span>Alamat</span>
										<strong><?= html_escape($row->alamat ?: '-'); ?></strong>
									</div>
									<div class="doclinc-admin-card__meta">
										<span>Latitude</span>
										<strong><?= html_escape($row->latitude ?: '-'); ?></strong>
									</div>
									<div class="doclinc-admin-card__meta">
										<span>Longitude</span>
										<strong><?= html_escape($row->longitude ?: '-'); ?></strong>
									</div>
								</div>

								<div class="doclinc-admin-card__actions">
									<button type="button" class="btn doclinc-action-btn doclinc-action-btn--primary" data-toggle="modal" data-target="#editPuskesmas<?= html_escape($row->kode_pkm); ?>">
										<i class="fas fa-edit"></i> Edit
									</button>
									<?php if (($row->status ?? '') === 'aktif'): ?>
										<form action="<?= site_url('master_puskesmas/disable') ?>" method="post">
											<input type="hidden" name="kode_pkm" value="<?= html_escape($row->kode_pkm ?? ''); ?>">
											<button type="submit" class="btn doclinc-action-btn doclinc-action-btn--secondary" onclick="return confirm('Nonaktifkan puskesmas ini?');">
												<i class="fas fa-ban"></i> Nonaktifkan
											</button>
										</form>
									<?php elseif ($is_default): ?>
										<button type="button" class="btn doclinc-action-btn doclinc-action-btn--secondary" disabled>
											<i class="fas fa-lock"></i> Legacy
										</button>
									<?php else: ?>
										<form action="<?= site_url('master_puskesmas/enable') ?>" method="post">
											<input type="hidden" name="kode_pkm" value="<?= html_escape($row->kode_pkm ?? ''); ?>">
											<button type="submit" class="btn doclinc-action-btn doclinc-action-btn--secondary" onclick="return confirm('Aktifkan puskesmas ini?');">
												<i class="fas fa-check"></i> Aktifkan
											</button>
										</form>
									<?php endif; ?>
								</div>
							</article>

							<?php ob_start(); ?>
							<div class="modal fade" id="editPuskesmas<?= html_escape($row->kode_pkm); ?>" tabindex="-1" role="dialog" aria-hidden="true">
								<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
									<div class="modal-content border-0 shadow-sm">
										<div class="modal-header bg-info text-white">
											<h5 class="modal-title"><i class="fas fa-hospital mr-2"></i>Edit Puskesmas</h5>
											<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
												<span aria-hidden="true">&times;</span>
											</button>
										</div>
										<form action="<?= site_url('master_puskesmas/update/' . rawurlencode($row->kode_pkm ?? '')) ?>" method="post">
											<div class="modal-body bg-light">
												<div class="form-group">
													<label class="text-info">Kode Puskesmas</label>
													<input type="text" class="form-control rounded-pill border-info" value="<?= html_escape($row->kode_pkm ?? '') ?>" readonly>
													<small class="form-text text-muted">Kode Puskesmas tidak diubah dari halaman edit.</small>
												</div>
												<div class="form-group">
													<label class="text-info">Nama Puskesmas</label>
													<input type="text" class="form-control rounded-pill border-info" name="nama_puskesmas" value="<?= html_escape($row->nama_puskesmas ?? '') ?>" required>
												</div>
												<div class="form-group">
													<label class="text-info">Alamat</label>
													<textarea class="form-control border-info" name="alamat" rows="2"><?= html_escape($row->alamat ?? '') ?></textarea>
												</div>
												<div class="row">
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Latitude</label>
															<input type="text" class="form-control rounded-pill border-info" name="latitude" value="<?= html_escape($row->latitude ?? '') ?>">
														</div>
													</div>
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Longitude</label>
															<input type="text" class="form-control rounded-pill border-info" name="longitude" value="<?= html_escape($row->longitude ?? '') ?>">
														</div>
													</div>
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Status</label>
															<select class="form-control rounded-pill border-info" name="status">
																<option value="aktif" <?= ($row->status ?? '') === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
																<option value="nonaktif" <?= ($row->status ?? '') === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
															</select>
															<small class="form-text text-muted">Puskesmas nonaktif tidak dipakai sebagai pilihan operasional baru.</small>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer border-0 px-4 pb-4">
												<button type="button" class="btn btn-light rounded-pill px-4 border" data-dismiss="modal">Batal</button>
												<button type="submit" class="btn btn-info rounded-pill px-4">Simpan</button>
											</div>
										</form>
									</div>
								</div>
							</div>
							<?php $edit_modals .= ob_get_clean(); ?>
					<?php endforeach; ?>
				</div>
				<?= $edit_modals; ?>
				<div class="doclinc-admin-empty d-none" id="puskesmasCardSearchEmpty">Belum ada data Puskesmas.</div>
			<?php else: ?>
				<div class="doclinc-admin-empty">Belum ada data Puskesmas.</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		var searchInput = document.getElementById('puskesmasCardSearch');
		var emptyState = document.getElementById('puskesmasCardSearchEmpty');
		var cards = Array.prototype.slice.call(document.querySelectorAll('[data-puskesmas-search]'));

		if (!searchInput || cards.length === 0) {
			return;
		}

		searchInput.addEventListener('input', function() {
			var query = searchInput.value.toLocaleLowerCase().trim();
			var visibleCount = 0;

			cards.forEach(function(card) {
				var searchText = (card.getAttribute('data-puskesmas-search') || '').toLocaleLowerCase();
				var isVisible = query === '' || searchText.indexOf(query) !== -1;
				card.classList.toggle('d-none', !isVisible);
				if (isVisible) {
					visibleCount += 1;
				}
			});

			if (emptyState) {
				emptyState.classList.toggle('d-none', visibleCount > 0);
			}
		});
	});
</script>

<div class="modal fade" id="modalTambahPuskesmas" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-sm">
			<div class="modal-header bg-success text-white">
				<h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Tambah Puskesmas</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('master_puskesmas/store') ?>" method="post">
				<div class="modal-body bg-light">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success">Kode Puskesmas</label>
								<input type="text" class="form-control rounded-pill border-success" name="kode_pkm" placeholder="Contoh: 10280101" required>
								<small class="form-text text-muted">Gunakan kode resmi Puskesmas. Jangan gunakan DEFAULT untuk data aktif.</small>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success">Nama Puskesmas</label>
								<input type="text" class="form-control rounded-pill border-success" name="nama_puskesmas" placeholder="Nama Puskesmas" required>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="text-success">Alamat</label>
						<textarea class="form-control border-success" name="alamat" rows="2"></textarea>
					</div>
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Latitude</label>
								<input type="text" class="form-control rounded-pill border-success" name="latitude" placeholder="-6.000000">
								<small class="form-text text-muted">Latitude dan longitude dipakai untuk routing/monitoring lokasi.</small>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Longitude</label>
								<input type="text" class="form-control rounded-pill border-success" name="longitude" placeholder="106.000000">
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Status</label>
								<select class="form-control rounded-pill border-success" name="status">
									<option value="aktif">Aktif</option>
									<option value="nonaktif">Nonaktif</option>
								</select>
								<small class="form-text text-muted">Puskesmas nonaktif tidak dipakai sebagai pilihan operasional baru.</small>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer border-0 px-4 pb-4">
					<button type="button" class="btn btn-light rounded-pill px-4 border" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-success rounded-pill px-4">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>
