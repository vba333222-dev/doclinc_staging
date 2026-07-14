<?php
$filters = isset($filters) && is_array($filters) ? $filters : array();
$staff_rows = isset($staff_rows) && is_array($staff_rows) ? $staff_rows : array();
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
$account_candidates_by_staff = isset($account_candidates_by_staff) && is_array($account_candidates_by_staff) ? $account_candidates_by_staff : array();
$command_center_user_ids = isset($command_center_user_ids) && is_array($command_center_user_ids) ? $command_center_user_ids : array();
$personal_account_eligibility_by_staff = isset($personal_account_eligibility_by_staff) && is_array($personal_account_eligibility_by_staff) ? $personal_account_eligibility_by_staff : array();
$form_mode = isset($form_mode) ? (string) $form_mode : '';
$form_staff = isset($form_staff) ? $form_staff : null;
$is_form = in_array($form_mode, array('create', 'edit'), true);
$form_action = $form_mode === 'edit' && $form_staff ? site_url('kelola_staff_puskesmas/update/' . (int) $form_staff->staff_id) : site_url('kelola_staff_puskesmas/store');
$form_title = $form_mode === 'edit' ? 'Edit staf' : 'Tambah staf';
$form_values = array(
	'kode_pkm' => $form_staff ? (string) $form_staff->kode_pkm : '',
	'nama' => $form_staff ? (string) $form_staff->nama : '',
	'profesi' => $form_staff ? (string) $form_staff->profesi : '',
	'no_hp' => $form_staff ? (string) $form_staff->no_hp : '',
	'nomor_sip' => $form_staff ? (string) $form_staff->nomor_sip : '',
	'status' => $form_staff ? (string) $form_staff->status : 'aktif',
);
?>
<div class="doclinc-admin-page doclinc-akun-page">
	<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-5 px-4 mt-n4 mx-n4 you-are-here">
		<h1 class="doclinc-page-title mb-0"><i class="fas fa-fw fa-users-cog"></i> Staf Puskesmas</h1>
	</div>

	<div class="container-fluid">
	<?php if ($this->session->flashdata('success')): ?>
		<div class="alert alert-success shadow-sm"><?= html_escape($this->session->flashdata('success')); ?></div>
	<?php endif; ?>
	<?php if ($this->session->flashdata('error')): ?>
		<div class="alert alert-danger shadow-sm"><?= html_escape($this->session->flashdata('error')); ?></div>
	<?php endif; ?>

	<?php if (empty($table_ready)): ?>
		<div class="alert alert-warning shadow-sm">Data staf belum tersedia.</div>
	<?php else: ?>
		<div class="doclinc-staff-note shadow-sm">
			<i class="fas fa-info-circle"></i>
			<span>Staf dapat dipilih sebagai PIC layanan.</span>
		</div>
		<?php if ($is_form): ?>
			<div class="card shadow mb-4 doclinc-filter-card">
				<div class="card-header py-3 d-flex align-items-center justify-content-between">
					<h6 class="m-0 font-weight-bold text-primary"><?= html_escape($form_title); ?></h6>
					<a href="<?= site_url('kelola_staff_puskesmas'); ?>" class="btn btn-sm btn-light rounded-pill border">Batal</a>
				</div>
				<form action="<?= $form_action; ?>" method="post">
					<div class="card-body bg-light">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Puskesmas</label>
									<select class="form-control rounded-pill border-info" name="kode_pkm" required>
										<option value="">Pilih Puskesmas</option>
										<?php foreach ($puskesmas_options as $puskesmas): ?>
											<option value="<?= html_escape($puskesmas->kode_pkm); ?>" <?= $form_values['kode_pkm'] === (string) $puskesmas->kode_pkm ? 'selected' : ''; ?>>
												<?= html_escape($puskesmas->nama_puskesmas); ?> (<?= html_escape($puskesmas->kode_pkm); ?>)
											</option>
										<?php endforeach; ?>
									</select>
									<small class="form-text text-muted">Pilih Puskesmas staf.</small>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Nama staf</label>
									<input type="text" class="form-control rounded-pill border-info" name="nama" value="<?= html_escape($form_values['nama']); ?>" placeholder="Nama staf" required>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Profesi</label>
									<input type="text" class="form-control rounded-pill border-info" name="profesi" value="<?= html_escape($form_values['profesi']); ?>" placeholder="Profesi atau peran layanan">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">No HP</label>
									<input type="text" class="form-control rounded-pill border-info" name="no_hp" value="<?= html_escape($form_values['no_hp']); ?>" placeholder="Nomor kontak staf">
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Nomor SIP</label>
									<input type="text" class="form-control rounded-pill border-info" name="nomor_sip" value="<?= html_escape($form_values['nomor_sip']); ?>" placeholder="Nomor SIP jika ada">
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label class="text-info">Status</label>
									<select class="form-control rounded-pill border-info" name="status">
										<option value="aktif" <?= $form_values['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
										<option value="nonaktif" <?= $form_values['status'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
									</select>
									<small class="form-text text-muted">Staf aktif dapat dipilih sebagai PIC.</small>
								</div>
							</div>
						</div>
					</div>
					<div class="card-footer bg-white border-0 text-right">
						<a href="<?= site_url('kelola_staff_puskesmas'); ?>" class="btn btn-light rounded-pill px-4 border">Batal</a>
						<button type="submit" class="btn btn-success rounded-pill px-4">Simpan</button>
					</div>
				</form>
			</div>
		<?php endif; ?>

		<div class="card shadow mb-4 doclinc-table-card doclinc-account-list-card">
			<div class="card-header py-3 d-flex align-items-center justify-content-between">
				<div>
					<h6 class="m-0 font-weight-bold">Daftar staf</h6>
					<div class="doclinc-muted-text mt-1">Kelola staf dan akun personal.</div>
				</div>
				<a href="<?= site_url('kelola_staff_puskesmas/create'); ?>" class="btn btn-sm btn-success shadow-sm rounded-pill doclinc-action-btn doclinc-action-primary">
					<i class="fas fa-plus-circle mr-1"></i> Tambah staf
				</a>
			</div>
			<div class="card-body doclinc-table-body">
				<form method="post" action="<?= site_url('kelola_staff_puskesmas'); ?>" class="doclinc-account-toolbar doclinc-staff-filter-toolbar" id="staffPuskesmasFilterForm">
					<div class="row">
						<div class="col-md-4 mb-2">
							<label class="sr-only" for="staffFilterPuskesmas">Puskesmas</label>
							<select name="kode_pkm" id="staffFilterPuskesmas" class="form-control doclinc-puskesmas-selector">
								<option value="">Semua Puskesmas</option>
								<?php foreach ($puskesmas_options as $puskesmas): ?>
									<option value="<?= html_escape($puskesmas->kode_pkm); ?>" <?= (isset($filters['kode_pkm']) && $filters['kode_pkm'] === (string) $puskesmas->kode_pkm) ? 'selected' : ''; ?>>
										<?= html_escape($puskesmas->nama_puskesmas); ?> (<?= html_escape($puskesmas->kode_pkm); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-md-3 mb-2">
							<label class="sr-only" for="staffFilterStatus">Status</label>
							<select name="status" id="staffFilterStatus" class="form-control doclinc-puskesmas-selector">
								<option value="">Semua status</option>
								<option value="aktif" <?= (isset($filters['status']) && $filters['status'] === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
								<option value="nonaktif" <?= (isset($filters['status']) && $filters['status'] === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
							</select>
						</div>
						<div class="col-md-3 mb-2">
							<label class="sr-only" for="staffFilterKeyword">Kata kunci</label>
							<input type="text" name="keyword" id="staffFilterKeyword" class="form-control doclinc-account-search" value="<?= html_escape($filters['keyword'] ?? ''); ?>" placeholder="Nama, no HP, profesi, SIP">
						</div>
						<div class="col-md-2 mb-2">
							<button type="submit" class="btn btn-info rounded-pill btn-block">Terapkan filter</button>
						</div>
					</div>
				</form>

				<?php $account_modals = ''; ?>
				<?php if (!empty($staff_rows)): ?>
					<div class="doclinc-account-list">
						<?php foreach ($staff_rows as $row): ?>
									<?php
									$staff_id = (int) ($row->staff_id ?? 0);
									$linked_user_id = (int) ($row->user_id ?? 0);
									$kode_pkm = (string) ($row->kode_pkm ?? '');
									$command_center_user_id = isset($command_center_user_ids[$kode_pkm]) ? (int) $command_center_user_ids[$kode_pkm] : 0;
									$is_command_center_link = $linked_user_id > 0 && $command_center_user_id > 0 && $linked_user_id === $command_center_user_id;
									$candidates = isset($account_candidates_by_staff[$staff_id]) && is_array($account_candidates_by_staff[$staff_id]) ? $account_candidates_by_staff[$staff_id] : array();
									$akun_label = 'Belum terhubung akun login';
									if (!empty($row->akun_nama) || !empty($row->akun_username) || !empty($row->akun_email)) {
										$akun_label = trim((string) ($row->akun_nama ?: $row->akun_username ?: $row->akun_email));
									}
									$profession_label = trim((string) ($row->profesi ?? ''));
									if ($profession_label === '') {
										$profession_label = 'Staf Puskesmas';
									} else {
										$profession_labels = array(
											'dokter' => 'Dokter',
											'bidan' => 'Bidan',
											'perawat' => 'Perawat',
											'staff puskesmas' => 'Staf Puskesmas',
										);
										$profession_key = strtolower($profession_label);
										$profession_label = $profession_labels[$profession_key] ?? $profession_label;
									}
									$is_active = ($row->status ?? '') === 'aktif';
									$linked_account_is_active = ($row->akun_status ?? '') === 'aktif';
									$puskesmas_status = $row->puskesmas_status ?? null;
									$puskesmas_is_valid = trim($kode_pkm) !== ''
										&& strtoupper(trim($kode_pkm)) !== 'DEFAULT'
										&& !empty($row->nama_puskesmas)
										&& ($puskesmas_status === null || $puskesmas_status === 'aktif');
									$creation_eligibility = $personal_account_eligibility_by_staff[$staff_id] ?? array('eligible' => false, 'message' => 'Staf belum dapat dibuatkan akun personal.');
									$can_create_personal_account = !empty($creation_eligibility['eligible']);
									$creation_block_message = !empty($creation_eligibility['message']) ? $creation_eligibility['message'] : 'Staf belum dapat dibuatkan akun personal.';
									?>
									<div class="doclinc-account-card">
										<div class="doclinc-account-card__header">
											<div>
												<div class="doclinc-account-card__title"><?= html_escape($row->nama ?? '-'); ?></div>
											</div>
											<span class="doclinc-account-card__status <?= $is_active ? 'is-active' : 'is-inactive'; ?>">
												<?= $is_active ? 'Aktif' : 'Nonaktif'; ?>
											</span>
										</div>

										<div class="doclinc-account-card__body">
											<div class="doclinc-account-card__meta">
												<span>Puskesmas</span>
												<strong><?= html_escape($row->nama_puskesmas ?? 'Puskesmas tidak ditemukan'); ?></strong>
												<span class="doclinc-code-chip mt-1"><?= html_escape($row->kode_pkm ?? '-'); ?></span>
											</div>
											<div class="doclinc-account-card__meta">
												<span>Profesi</span>
												<strong><?= html_escape($profession_label); ?></strong>
											</div>
											<div class="doclinc-account-grid">
												<div class="doclinc-account-field">
													<span>No. Telepon</span>
													<strong><?= html_escape($row->no_hp ?: '-'); ?></strong>
												</div>
												<div class="doclinc-account-field">
													<span>SIP</span>
													<strong><?= html_escape($row->nomor_sip ?: '-'); ?></strong>
												</div>
											</div>
											<div class="doclinc-account-card__meta">
												<span>Akun login personal</span>
											<?php if ($linked_user_id > 0): ?>
												<strong><?= html_escape($akun_label); ?></strong>
												<?php if (!empty($row->akun_username) && $akun_label !== $row->akun_username): ?>
													<small><?= html_escape($row->akun_username); ?></small>
												<?php endif; ?>
												<?php if (!empty($row->akun_email)): ?>
													<small><?= html_escape($row->akun_email); ?></small>
												<?php endif; ?>
												<span class="doclinc-status-chip <?= $linked_account_is_active ? 'is-active' : 'is-inactive'; ?> mt-2">
													<?= $linked_account_is_active ? 'Terhubung — Aktif' : 'Terhubung — Nonaktif'; ?>
												</span>
												<?php if (!$linked_account_is_active): ?>
													<small class="text-danger mt-1">Akun belum dapat digunakan untuk login.</small>
												<?php endif; ?>
												<?php if ($is_command_center_link): ?>
													<div class="doclinc-account-card__note is-warning mt-2"><i class="fas fa-exclamation-triangle mr-1"></i> Akun ini terlihat sebagai akun koordinator Puskesmas. Periksa ulang sebelum digunakan sebagai akun personal.</div>
												<?php endif; ?>
											<?php else: ?>
												<strong class="doclinc-muted-text">Belum terhubung akun login</strong>
												<span class="doclinc-status-chip is-inactive mt-2">Belum terhubung</span>
											<?php endif; ?>
											</div>
										</div>

										<div class="doclinc-account-card__footer">
											<div class="doclinc-account-card__actions">
												<a href="<?= site_url('kelola_staff_puskesmas/edit/' . (int) $row->staff_id); ?>" class="btn doclinc-action-btn doclinc-action-btn--primary">
													<i class="fas fa-edit"></i> Ubah
												</a>
												<?php if (($row->status ?? '') === 'aktif'): ?>
													<form action="<?= site_url('kelola_staff_puskesmas/deactivate/' . (int) $row->staff_id); ?>" method="post">
														<button type="submit" class="btn doclinc-action-btn doclinc-action-btn--secondary" onclick="return confirm('Nonaktifkan staf ini? Riwayat dan PIC tidak akan dihapus.');">
															<i class="fas fa-ban"></i> Nonaktifkan
														</button>
													</form>
												<?php else: ?>
													<form action="<?= site_url('kelola_staff_puskesmas/activate/' . (int) $row->staff_id); ?>" method="post">
														<button type="submit" class="btn doclinc-action-btn doclinc-action-btn--secondary" onclick="return confirm('Aktifkan staf ini?');">
															<i class="fas fa-check"></i> Aktifkan
														</button>
													</form>
												<?php endif; ?>
												<?php if ($linked_user_id > 0): ?>
													<form action="<?= site_url('kelola_staff_puskesmas/unbind_account'); ?>" method="post">
														<input type="hidden" name="staff_id" value="<?= (int) $staff_id; ?>">
														<button type="submit" class="btn doclinc-action-btn doclinc-action-btn--warning" onclick="return confirm('Lepas akun dari staf ini? Akun tidak akan dihapus.');">
															<i class="fas fa-unlink"></i> Lepas akun
														</button>
													</form>
												<?php else: ?>
													<button type="button" class="btn doclinc-action-btn doclinc-action-btn--secondary" data-toggle="modal" data-target="#modalBindAccount<?= (int) $staff_id; ?>">
														<i class="fas fa-link"></i> Hubungkan akun
													</button>
											<?php if ($can_create_personal_account): ?>
												<button type="button" class="btn doclinc-action-btn doclinc-action-btn--primary" data-toggle="modal" data-target="#modalCreatePersonalAccount<?= (int) $staff_id; ?>">
													<i class="fas fa-user-plus"></i> Buat akun
												</button>
											<?php elseif ($is_active && $linked_user_id < 1 && $puskesmas_is_valid): ?>
												<button type="button" class="btn doclinc-action-btn doclinc-action-btn--secondary" disabled title="<?= html_escape($creation_block_message); ?>">
													<i class="fas fa-user-plus"></i> Buat akun
												</button>
											<?php endif; ?>
												<?php endif; ?>
											</div>
										</div>
									</div>
									<?php if ($linked_user_id < 1): ?>
										<?php ob_start(); ?>
										<div class="modal fade" id="modalBindAccount<?= (int) $staff_id; ?>" tabindex="-1" role="dialog" aria-labelledby="modalBindAccountLabel<?= (int) $staff_id; ?>" aria-hidden="true">
											<div class="modal-dialog" role="document">
												<div class="modal-content">
													<form action="<?= site_url('kelola_staff_puskesmas/bind_account'); ?>" method="post">
														<div class="modal-header">
															<h5 class="modal-title" id="modalBindAccountLabel<?= (int) $staff_id; ?>">Hubungkan akun personal</h5>
															<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
																<span aria-hidden="true">&times;</span>
															</button>
														</div>
														<div class="modal-body">
															<input type="hidden" name="staff_id" value="<?= (int) $staff_id; ?>">
															<div class="mb-3">
																<div class="font-weight-bold"><?= html_escape($row->nama ?? '-'); ?></div>
																<div class="small text-muted"><?= html_escape($row->nama_puskesmas ?? 'Puskesmas tidak ditemukan'); ?></div>
															</div>
															<?php if (empty($candidates)): ?>
																<div class="doclinc-staff-account-empty">Belum ada akun personal yang dapat dihubungkan untuk Puskesmas ini.</div>
															<?php else: ?>
																<div class="form-group">
																	<label class="text-info">Akun login personal</label>
																	<select class="form-control rounded-pill border-info" name="user_id" required>
																		<option value="">Pilih akun login</option>
																		<?php foreach ($candidates as $candidate): ?>
																			<?php
																			$candidate_label = trim((string) ($candidate->nama ?: $candidate->username ?: $candidate->email));
																			$candidate_meta = array();
																			if (!empty($candidate->username)) {
																				$candidate_meta[] = $candidate->username;
																			}
																			if (!empty($candidate->email)) {
																				$candidate_meta[] = $candidate->email;
																			}
																			?>
																			<option value="<?= (int) $candidate->userId; ?>">
																				<?= html_escape($candidate_label); ?><?= !empty($candidate_meta) ? ' &middot; ' . html_escape(implode(' / ', $candidate_meta)) : ''; ?>
																			</option>
																		<?php endforeach; ?>
																	</select>
																	<small class="form-text text-muted">Pilihan hanya menampilkan akun dokter aktif dari Puskesmas yang sama, bukan akun koordinator.</small>
																</div>
															<?php endif; ?>
														</div>
														<div class="modal-footer">
															<button type="button" class="btn btn-light rounded-pill border" data-dismiss="modal">Batal</button>
															<button type="submit" class="btn btn-primary rounded-pill" <?= empty($candidates) ? 'disabled' : ''; ?>>Hubungkan akun</button>
														</div>
													</form>
												</div>
											</div>
										</div>
										<?php $account_modals .= ob_get_clean(); ?>

										<?php if ($can_create_personal_account): ?>
											<?php ob_start(); ?>
											<div class="modal fade" id="modalCreatePersonalAccount<?= (int) $staff_id; ?>" tabindex="-1" role="dialog" aria-labelledby="modalCreatePersonalAccountLabel<?= (int) $staff_id; ?>" aria-hidden="true">
												<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
													<div class="modal-content border-0 shadow-sm">
														<form action="<?= site_url('kelola_staff_puskesmas/create_personal_account'); ?>" method="post">
															<div class="modal-header bg-info text-white">
																<h5 class="modal-title" id="modalCreatePersonalAccountLabel<?= (int) $staff_id; ?>"><i class="fas fa-user-plus mr-2"></i>Buat akun personal</h5>
																<button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
															</div>
															<div class="modal-body bg-light">
																<input type="hidden" name="staff_id" value="<?= (int) $staff_id; ?>">
																<div class="mb-3">
																	<div class="font-weight-bold"><?= html_escape($row->nama ?? '-'); ?></div>
																	<div class="small text-muted"><?= html_escape($profession_label); ?></div>
																	<div class="small text-muted"><?= html_escape($row->nama_puskesmas ?? '-'); ?> (<?= html_escape($row->kode_pkm ?? '-'); ?>)</div>
																</div>
																		<div class="alert alert-info">Password tidak ditampilkan kembali.</div>
																		<div class="row">
																			<div class="col-md-6"><div class="form-group">
																				<label class="text-info">Username</label>
																				<input type="text" class="form-control rounded-pill border-info" name="username" minlength="3" maxlength="100" pattern="[A-Za-z0-9._-]+" autocomplete="username" required>
																			</div></div>
																			<div class="col-md-6"><div class="form-group">
																				<label class="text-info">Email</label>
																				<input type="email" class="form-control rounded-pill border-info" name="email" maxlength="100" autocomplete="email" required>
																			</div></div>
																		</div>
																<div class="row">
																	<div class="col-md-6"><div class="form-group">
																		<label class="text-info">Password sementara</label>
																		<input type="password" class="form-control rounded-pill border-info" name="password" minlength="8" autocomplete="new-password" required>
																	</div></div>
																	<div class="col-md-6"><div class="form-group">
																		<label class="text-info">Konfirmasi password</label>
																		<input type="password" class="form-control rounded-pill border-info" name="confirm_password" minlength="8" autocomplete="new-password" required>
																	</div></div>
																</div>
															</div>
															<div class="modal-footer border-0">
																<button type="button" class="btn btn-light rounded-pill border" data-dismiss="modal">Batal</button>
																<button type="submit" class="btn btn-info rounded-pill">Buat akun</button>
															</div>
														</form>
													</div>
												</div>
											</div>
											<?php $account_modals .= ob_get_clean(); ?>
										<?php endif; ?>
									<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<?= $account_modals; ?>
				<?php else: ?>
					<div class="doclinc-account-empty">Belum ada staf untuk filter ini.</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
	</div>
</div>
