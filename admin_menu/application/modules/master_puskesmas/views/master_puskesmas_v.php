<?php
$puskesmas_readiness_issues = isset($puskesmas_readiness_issues) && is_array($puskesmas_readiness_issues) ? $puskesmas_readiness_issues : array();
$puskesmas_readiness_summary = isset($puskesmas_readiness_summary) && is_array($puskesmas_readiness_summary) ? $puskesmas_readiness_summary : array('total' => 0, 'ready' => 0, 'attention' => 0);
$puskesmas_readiness_labels = array(
	'facility_name' => 'nama resmi',
	'facility_address' => 'alamat pelayanan lengkap',
	'facility_latitude' => 'titik latitude valid',
	'facility_longitude' => 'titik longitude valid',
);
?>
<div class="doclinc-admin-page doclinc-akun-page">
	<?php
	/* Kepala halaman dari partial bersama. Tombol "Tambah Puskesmas" tidak
	   dipindah ke sini: tombol itu sudah ada di kepala daftar, dan menaruhnya
	   dua kali di satu layar membuat dua tombol utama bersaing. */
	$page_head = array('title' => 'Master Puskesmas');
	$this->load->view('commons/page_head', array('page_head' => $page_head));
	?>

	<div class="container-fluid">
	<?php if ($this->session->flashdata('success')): ?>
		<div class="alert alert-success shadow-sm"><?= html_escape($this->session->flashdata('success')); ?></div>
	<?php endif; ?>
	<?php if ($this->session->flashdata('error')): ?>
		<div class="alert alert-danger shadow-sm"><?= html_escape($this->session->flashdata('error')); ?></div>
	<?php endif; ?>
	<div class="alert <?= (int) ($puskesmas_readiness_summary['attention'] ?? 0) > 0 ? 'alert-warning' : 'alert-success'; ?> shadow-sm" role="status">
		<strong>Kesiapan Puskesmas aktif:</strong>
		<?= html_escape((string) (int) ($puskesmas_readiness_summary['ready'] ?? 0)); ?> dari
		<?= html_escape((string) (int) ($puskesmas_readiness_summary['total'] ?? 0)); ?> siap.
		<?php if ((int) ($puskesmas_readiness_summary['attention'] ?? 0) > 0): ?>
			<?= html_escape((string) (int) $puskesmas_readiness_summary['attention']); ?> Puskesmas perlu dilengkapi oleh Administrator Dinas Kesehatan.
		<?php endif; ?>
	</div>

	<?php
	$list_card = array(
		'part' => 'open',
		'title' => 'Daftar Puskesmas',
		'sub' => 'Puskesmas aktif untuk routing dan koordinasi layanan.',
		'action' => array(
			'kind' => 'modal',
			'label' => 'Tambah Puskesmas',
			'target' => '#modalTambahPuskesmas',
			'icon' => 'fas fa-plus-circle',
			'variant' => 'success',
		),
		'card_class' => 'doclinc-account-list-card',
	);
	$this->load->view('commons/list_card', array('list_card' => $list_card));
	?>
			<?php if ($puskesmas && $puskesmas->num_rows() > 0): ?>
				<div class="doclinc-account-toolbar">
					<div class="doclinc-account-toolbar__field">
						<label for="puskesmasCardSearch">Cari Puskesmas</label>
						<input type="search" id="puskesmasCardSearch" class="form-control doclinc-account-search" placeholder="Nama, kode, alamat, atau status">
					</div>
					<div class="doclinc-account-toolbar__field">
						<label for="puskesmasReadinessFilter">Kesiapan data</label>
						<select id="puskesmasReadinessFilter" class="form-control doclinc-puskesmas-selector">
							<option value="">Semua kesiapan</option>
							<option value="attention">Perlu dilengkapi</option>
							<option value="ready">Siap</option>
						</select>
					</div>
				</div>
				<?php $edit_modals = ''; ?>
				<div class="doclinc-account-list">
					<?php foreach ($puskesmas->result() as $row): ?>
								<?php
								$is_default = strtoupper((string) ($row->kode_pkm ?? '')) === 'DEFAULT';
								$status = (string) ($row->status ?? '');
								$status_labels = array('aktif' => 'Aktif', 'valid' => 'Aktif', 'nonaktif' => 'Nonaktif', 'inactive' => 'Nonaktif');
								$status_label = $is_default ? 'Perlu ditinjau' : ($status_labels[strtolower($status)] ?? 'Status belum tersedia');
								$display_name = $is_default ? 'Puskesmas belum tersedia' : ($row->nama_puskesmas ?? 'Puskesmas belum tersedia');
								$search_text = implode(' ', array($display_name, $row->kode_pkm ?? '', $row->alamat ?? '', $row->latitude ?? '', $row->longitude ?? '', $status_label));
								$readiness_issues = $puskesmas_readiness_issues[(string) ($row->kode_pkm ?? '')] ?? array();
								$readiness_state = $status === 'aktif' ? (empty($readiness_issues) ? 'ready' : 'attention') : 'inactive';
								?>
							<div class="doclinc-account-card" data-puskesmas-search="<?= html_escape($search_text); ?>" data-puskesmas-readiness="<?= html_escape($readiness_state); ?>">
								<div class="doclinc-account-card__header">
									<div>
										<div class="doclinc-account-card__title"><?= html_escape($display_name); ?></div>
									</div>
									<span class="doclinc-account-card__status <?= $status === 'aktif' && !$is_default ? 'is-active' : ($is_default ? 'is-warning' : 'is-inactive'); ?>">
										<?= html_escape($status_label); ?>
									</span>
								</div>

								<div class="doclinc-account-card__body">
									<?php if ($status !== 'aktif'): ?>
										<div class="doclinc-account-card__note is-muted mb-3"><i class="fas fa-pause-circle mr-1"></i>Puskesmas nonaktif tidak dihitung dalam kesiapan operasional.</div>
									<?php elseif (!empty($readiness_issues)): ?>
										<div class="doclinc-account-card__note is-warning mb-3">
											<i class="fas fa-exclamation-triangle mr-1"></i>
											<strong>Lengkapi:</strong>
											<?= html_escape(implode(', ', array_map(function ($issue) use ($puskesmas_readiness_labels) {
												return $puskesmas_readiness_labels[$issue] ?? 'data Puskesmas';
											}, $readiness_issues))); ?>
										</div>
									<?php else: ?>
										<div class="doclinc-account-card__note is-muted mb-3"><i class="fas fa-check-circle mr-1"></i>Data operasional siap.</div>
									<?php endif; ?>
									<div class="doclinc-account-card__meta">
										<span>Kode Puskesmas</span>
										<strong><span class="doclinc-code-chip"><?= html_escape($row->kode_pkm ?? '-'); ?></span></strong>
									</div>
									<div class="doclinc-account-card__meta">
										<span>Alamat</span>
										<strong><?= html_escape($row->alamat ?: 'Alamat belum tersedia'); ?></strong>
									</div>
									<div class="doclinc-account-grid">
										<div class="doclinc-account-field">
											<span>Latitude</span>
											<strong><?= html_escape($row->latitude ?: 'Koordinat belum tersedia'); ?></strong>
										</div>
										<div class="doclinc-account-field">
											<span>Longitude</span>
											<strong><?= html_escape($row->longitude ?: 'Koordinat belum tersedia'); ?></strong>
										</div>
									</div>
								</div>

								<div class="doclinc-account-card__footer">
									<div class="doclinc-account-card__actions">
									<button type="button" class="btn doclinc-action-btn doclinc-action-btn--primary" data-toggle="modal" data-target="#editPuskesmas<?= html_escape($row->kode_pkm); ?>">
										<i class="fas fa-edit"></i> Ubah
									</button>
									<?php if (($row->status ?? '') === 'aktif'): ?>
										<form action="<?= site_url('master_puskesmas/disable') ?>" method="post">
											<input type="hidden" name="kode_pkm" value="<?= html_escape($row->kode_pkm ?? ''); ?>">
											<button type="submit" class="btn doclinc-action-btn doclinc-action-btn--secondary" onclick="return confirm('Nonaktifkan Puskesmas? Puskesmas tidak dapat menerima permintaan baru.');">
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
											<button type="submit" class="btn doclinc-action-btn doclinc-action-btn--secondary" onclick="return confirm('Aktifkan Puskesmas?');">
												<i class="fas fa-check"></i> Aktifkan
											</button>
										</form>
									<?php endif; ?>
									</div>
								</div>
							</div>

							<?php ob_start(); ?>
							<div class="modal fade" id="editPuskesmas<?= html_escape($row->kode_pkm); ?>" tabindex="-1" role="dialog" aria-hidden="true">
								<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
									<div class="modal-content border-0 shadow-sm">
										<div class="modal-header bg-info text-white">
											<h5 class="modal-title"><i class="fas fa-hospital mr-2"></i>Ubah Puskesmas</h5>
											<button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
												<span aria-hidden="true">&times;</span>
											</button>
										</div>
										<form action="<?= site_url('master_puskesmas/update/' . rawurlencode($row->kode_pkm ?? '')) ?>" method="post">
											<div class="modal-body bg-light">
												<div class="form-group">
													<label class="text-info">Kode Puskesmas</label>
													<input type="text" class="form-control" value="<?= html_escape($row->kode_pkm ?? '') ?>" readonly>
													<small class="form-text text-muted">Kode Puskesmas tidak diubah dari halaman edit.</small>
												</div>
												<div class="form-group">
													<label class="text-info">Nama Puskesmas</label>
													<input type="text" class="form-control" name="nama_puskesmas" value="<?= html_escape($row->nama_puskesmas ?? '') ?>" required>
												</div>
										<div class="form-group">
											<label class="text-info">Alamat</label>
											<textarea class="form-control border-info" name="alamat" rows="2" required><?= html_escape($row->alamat ?? '') ?></textarea>
											<small class="form-text text-muted">Gunakan alamat pelayanan yang lengkap dan mudah dikenali pengguna.</small>
										</div>
												<div class="row">
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Latitude</label>
														<input type="number" name="latitude" step="any" min="-90" max="90" class="form-control" value="<?= html_escape($row->latitude ?? '') ?>" required>
														</div>
													</div>
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Longitude</label>
														<input type="number" name="longitude" step="any" min="-180" max="180" class="form-control" value="<?= html_escape($row->longitude ?? '') ?>" required>
														</div>
													</div>
													<div class="col-md-4">
														<div class="form-group">
															<label class="text-info">Status</label>
															<select class="form-control" name="status">
																<option value="aktif" <?= ($row->status ?? '') === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
																<option value="nonaktif" <?= ($row->status ?? '') === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
															</select>
															<small class="form-text text-muted">Puskesmas nonaktif tidak dipakai sebagai pilihan operasional baru.</small>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer border-0 px-4 pb-4">
												<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
												<button type="submit" class="btn btn-primary">Simpan</button>
											</div>
										</form>
									</div>
								</div>
							</div>
							<?php $edit_modals .= ob_get_clean(); ?>
					<?php endforeach; ?>
				</div>
				<?= $edit_modals; ?>
				<div class="doclinc-account-empty d-none" id="puskesmasCardSearchEmpty">Belum ada data Puskesmas.</div>
			<?php else: ?>
				<div class="doclinc-account-empty">Belum ada data Puskesmas.</div>
			<?php endif; ?>
	<?php
	$list_card = array('part' => 'close');
	$this->load->view('commons/list_card', array('list_card' => $list_card));
	?>
	</div>
</div>

<script type="text/javascript">
	document.addEventListener('DOMContentLoaded', function() {
		var searchInput = document.getElementById('puskesmasCardSearch');
		var readinessFilter = document.getElementById('puskesmasReadinessFilter');
		var emptyState = document.getElementById('puskesmasCardSearchEmpty');
		var cards = Array.prototype.slice.call(document.querySelectorAll('[data-puskesmas-search]'));

		if (!searchInput || !readinessFilter || cards.length === 0) {
			return;
		}

		function applyFilters() {
			var query = searchInput.value.toLocaleLowerCase().trim();
			var readiness = readinessFilter.value;
			var visibleCount = 0;

			cards.forEach(function(card) {
				var searchText = (card.getAttribute('data-puskesmas-search') || '').toLocaleLowerCase();
				var cardReadiness = card.getAttribute('data-puskesmas-readiness') || '';
				var isVisible = (query === '' || searchText.indexOf(query) !== -1)
					&& (readiness === '' || readiness === cardReadiness);
				card.classList.toggle('d-none', !isVisible);
				if (isVisible) {
					visibleCount += 1;
				}
			});

			if (emptyState) {
				emptyState.classList.toggle('d-none', visibleCount > 0);
			}
		}
		searchInput.addEventListener('input', applyFilters);
		readinessFilter.addEventListener('change', applyFilters);
	});
</script>

<div class="modal fade" id="modalTambahPuskesmas" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-sm">
			<div class="modal-header bg-success text-white">
				<h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Tambah Puskesmas</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form action="<?= site_url('master_puskesmas/store') ?>" method="post">
				<div class="modal-body bg-light">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success">Kode Puskesmas</label>
								<input type="text" class="form-control" name="kode_pkm" placeholder="Contoh: 10280101" required>
								<small class="form-text text-muted">Pakai kode resmi Puskesmas, bukan DEFAULT.</small>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="text-success">Nama Puskesmas</label>
								<input type="text" class="form-control" name="nama_puskesmas" placeholder="Nama Puskesmas" required>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="text-success">Alamat</label>
						<textarea class="form-control border-success" name="alamat" rows="2" required></textarea>
						<small class="form-text text-muted">Gunakan alamat pelayanan yang lengkap dan mudah dikenali pengguna.</small>
					</div>
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Latitude</label>
								<input type="number" name="latitude" step="any" min="-90" max="90" class="form-control" placeholder="-6.000000" required>
								<small class="form-text text-muted">Dipakai untuk routing dan peta lokasi.</small>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Longitude</label>
								<input type="number" name="longitude" step="any" min="-180" max="180" class="form-control" placeholder="106.000000" required>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="text-success">Status</label>
								<select class="form-control" name="status">
									<option value="aktif">Aktif</option>
									<option value="nonaktif">Nonaktif</option>
								</select>
								<small class="form-text text-muted">Puskesmas nonaktif tidak dipakai sebagai pilihan operasional baru.</small>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer border-0 px-4 pb-4">
					<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-primary">Simpan</button>
				</div>
			</form>
		</div>
	</div>
</div>
