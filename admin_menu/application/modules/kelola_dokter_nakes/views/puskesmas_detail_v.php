<?php
require_once dirname(APPPATH, 2) . '/application/libraries/Nakes_credential_policy.php';
/* Halaman detail satu Puskesmas: satu tempat untuk profil fasilitas, akun
   login koordinator, dan daftar stafnya. Inilah pengganti dua layar lama
   (`master_puskesmas` untuk profil dan `kelola_staff_puskesmas` untuk staf),
   jadi tidak ada lagi Puskesmas yang tampil di tiga tempat berbeda. */
$puskesmas = isset($puskesmas) ? $puskesmas : null;
$account = isset($account) ? $account : null;
$staff_section = isset($staff_section) ? (string) $staff_section : '';
$profile_schema_ready = !empty($profile_schema_ready);
$credential_policy = new Nakes_credential_policy();

/* Kode masalah dari `Master_puskesmas_m::readiness_issues()` diterjemahkan di
   sini, bukan di model: kode tetap mesin, kalimat tetap milik tampilan. */
$readiness_label_map = array(
	'facility_name' => 'nama Puskesmas',
	'facility_address' => 'alamat pelayanan',
	'facility_latitude' => 'latitude',
	'facility_longitude' => 'longitude',
);
$readiness_labels = array();
foreach ((array) ($puskesmas->readiness_issues ?? array()) as $issue) {
	$readiness_labels[] = isset($readiness_label_map[$issue]) ? $readiness_label_map[$issue] : (string) $issue;
}

if (!$puskesmas) {
	echo '<div class="container-fluid py-4"><div class="alert alert-danger">Puskesmas tidak ditemukan.</div></div>';
	return;
}

$credential_state = $account
	? $credential_policy->state('dokter', (int) ($account->must_change_password ?? 0), $account->password_changed_at ?? null)
	: null;
$credential_labels = array(
	Nakes_credential_policy::FIRST_LOGIN_PENDING => array('label' => 'Belum pernah aktivasi password', 'class' => 'badge-warning'),
	Nakes_credential_policy::ADMIN_RESET_PENDING => array('label' => 'Menunggu password baru setelah reset', 'class' => 'badge-warning'),
	Nakes_credential_policy::ACTIVE => array('label' => 'Aktif', 'class' => 'badge-success'),
	Nakes_credential_policy::NOT_APPLICABLE => array('label' => 'Tidak berlaku', 'class' => 'badge-secondary'),
);
$credential_badge = $credential_state !== null && isset($credential_labels[$credential_state])
	? $credential_labels[$credential_state]
	: array('label' => 'Belum ada akun', 'class' => 'badge-secondary');
$account_active = $account && strtolower(trim((string) $account->status)) === 'aktif';
?>
<div class="doclinc-admin-page doclinc-akun-page">
	<?php
	$page_head = array(
		'title' => (string) $puskesmas->nama_puskesmas,
		'sub' => 'Kode ' . (string) $puskesmas->kode_pkm . ' · Kecamatan ' . (string) $puskesmas->kecamatan,
		'actions' => array(
			array('kind' => 'link', 'label' => 'Daftar Puskesmas', 'href' => site_url('kelola_dokter_nakes'), 'icon' => 'fas fa-arrow-left', 'variant' => 'outline-secondary'),
			array('kind' => 'link', 'label' => 'Ganti password akun', 'href' => site_url('kelola_dokter_nakes/password/' . rawurlencode((string) $puskesmas->kode_pkm)), 'icon' => 'fas fa-key', 'variant' => 'primary'),
		),
	);
	$this->load->view('commons/page_head', array('page_head' => $page_head));
	?>

	<div class="container-fluid">
		<?php if ($this->session->flashdata('success')): ?>
			<div class="alert alert-success shadow-sm" role="alert"><?= html_escape($this->session->flashdata('success')); ?></div>
		<?php endif; ?>
		<?php if ($this->session->flashdata('error')): ?>
			<div class="alert alert-danger shadow-sm" role="alert"><?= html_escape($this->session->flashdata('error')); ?></div>
		<?php endif; ?>

		<?php if (!$puskesmas->master_ready): ?>
			<div class="alert alert-warning shadow-sm" role="alert">
				Kode <span class="dl-adm-help-code"><?= html_escape((string) $puskesmas->kode_pkm); ?></span> belum ada di tabel
				<span class="dl-adm-help-code">m_puskesmas</span>. Profil fasilitas tidak dapat disimpan sampai baris master dibuat.
			</div>
		<?php endif; ?>

		<div class="row">
			<div class="col-lg-7">
				<div class="card shadow mb-4">
					<div class="card-header py-3">
						<h2 class="h6 m-0 font-weight-bold"><i class="fas fa-hospital mr-2" aria-hidden="true"></i>Profil fasilitas</h2>
					</div>
					<div class="card-body">
						<?php if (!$profile_schema_ready): ?>
							<p class="text-muted">
								Kolom email dan nama kepala belum tersedia. Jalankan migrasi
								<span class="dl-adm-help-code">20261027000100_m_puskesmas_profile_contact</span>
								agar keduanya dapat diisi; alamat dan telepon tetap dapat disimpan sekarang.
							</p>
						<?php endif; ?>
						<form action="<?= site_url('kelola_dokter_nakes/update_profile'); ?>" method="post">
							<input type="hidden" name="<?= html_escape($this->security->get_csrf_token_name()); ?>" value="<?= html_escape($this->security->get_csrf_hash()); ?>">
							<input type="hidden" name="kode_pkm" value="<?= html_escape((string) $puskesmas->kode_pkm); ?>">
							<div class="form-group">
								<label for="puskesmasAlamat">Alamat</label>
								<textarea class="form-control" id="puskesmasAlamat" name="alamat" rows="2" maxlength="255"><?= html_escape((string) $puskesmas->alamat); ?></textarea>
							</div>
							<div class="form-row">
								<div class="form-group col-md-6">
									<label for="puskesmasTelepon">Telepon</label>
									<input type="text" class="form-control" id="puskesmasTelepon" name="telepon" maxlength="50" value="<?= html_escape((string) $puskesmas->telepon); ?>">
								</div>
								<div class="form-group col-md-6">
									<label for="puskesmasEmail">Email resmi</label>
									<input type="email" class="form-control" id="puskesmasEmail" name="email" maxlength="150" value="<?= html_escape((string) $puskesmas->email); ?>"<?= $profile_schema_ready ? '' : ' disabled'; ?>>
								</div>
							</div>
							<div class="form-group">
								<label for="puskesmasKepala">Nama kepala Puskesmas</label>
								<input type="text" class="form-control" id="puskesmasKepala" name="kepala_puskesmas" maxlength="150" value="<?= html_escape((string) $puskesmas->kepala_puskesmas); ?>"<?= $profile_schema_ready ? '' : ' disabled'; ?>>
								<small class="text-muted">Nama yang tampil sebagai penanggung jawab fasilitas, bukan akun login.</small>
							</div>
							<div class="form-row">
								<div class="form-group col-md-6">
									<label for="puskesmasLatitude">Latitude</label>
									<input type="number" step="any" min="-90" max="90" class="form-control" id="puskesmasLatitude" name="latitude" value="<?= html_escape((string) $puskesmas->latitude); ?>">
								</div>
								<div class="form-group col-md-6">
									<label for="puskesmasLongitude">Longitude</label>
									<input type="number" step="any" min="-180" max="180" class="form-control" id="puskesmasLongitude" name="longitude" value="<?= html_escape((string) $puskesmas->longitude); ?>">
								</div>
							</div>
							<?php if (!empty($puskesmas->readiness_issues)): ?>
								<div class="alert alert-warning py-2 mb-3" role="alert">
									<strong>Belum lengkap:</strong> <?= html_escape(implode(', ', $readiness_labels)); ?>.
									Puskesmas yang datanya belum lengkap tidak dapat diaktifkan di master fasilitas.
								</div>
							<?php endif; ?>
							<button type="submit" class="btn btn-primary">
								<i class="fas fa-save mr-1" aria-hidden="true"></i>Simpan profil
							</button>
						</form>
					</div>
				</div>
			</div>


			<div class="col-lg-5">
				<div class="card shadow mb-4">
					<div class="card-header py-3">
						<h2 class="h6 m-0 font-weight-bold"><i class="fas fa-user-md mr-2" aria-hidden="true"></i>Akun login Puskesmas</h2>
					</div>
					<div class="card-body">
						<?php if ($account): ?>
							<div class="d-flex align-items-center justify-content-between mb-3">
								<div>
									<div class="font-weight-bold"><?= html_escape((string) $account->username); ?></div>
									<div class="small text-muted"><?= html_escape((string) ($account_active ? 'Akun aktif' : 'Akun nonaktif')); ?></div>
								</div>
								<span class="badge <?= html_escape($credential_badge['class']); ?>"><?= html_escape($credential_badge['label']); ?></span>
							</div>
							<dl class="row mb-3">
								<dt class="col-6">Jumlah akun</dt>
								<dd class="col-6 text-right"><?= html_escape((string) (int) $account->account_total); ?></dd>
								<dt class="col-6">Staf aktif</dt>
								<dd class="col-6 text-right"><?= html_escape((string) (int) $puskesmas->staff_active); ?> / <?= html_escape((string) (int) $puskesmas->staff_total); ?></dd>
							</dl>
							<a class="btn btn-primary btn-block" href="<?= html_escape(site_url('kelola_dokter_nakes/password/' . rawurlencode((string) $puskesmas->kode_pkm))); ?>">
								<i class="fas fa-key mr-1" aria-hidden="true"></i>Ganti password akun
							</a>
							<p class="doclinc-muted-text mt-3 mb-0">
								Setelah diganti Administrator, pengelola Puskesmas wajib membuat password pribadi saat masuk berikutnya.
							</p>
						<?php else: ?>
							<p class="mb-3">
								Puskesmas ini belum punya akun login. Tanpa akun aktif, permintaan warga tidak dapat diterima.
							</p>
							<button type="button" class="btn btn-success btn-block" data-toggle="modal" data-target="#modalTambahAkunPuskesmas">
								<i class="fas fa-plus-circle mr-1" aria-hidden="true"></i>Tambah akun Puskesmas
							</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<?php if ($staff_section === ''): ?>
			<div class="alert alert-warning shadow-sm" role="alert">
				Daftar staf tidak dapat dimuat di halaman ini. Buka
				<a href="<?= html_escape(site_url('kelola_staff_puskesmas')); ?>">pengelolaan staf</a> untuk melihat dan mengubahnya.
			</div>
		<?php else: ?>
			<?= $staff_section; ?>
		<?php endif; ?>
	</div>

<?php if (!$account): ?>
	<div class="modal fade" id="modalTambahAkunPuskesmas" tabindex="-1" role="dialog" aria-labelledby="modalTambahAkunPuskesmasLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered" role="document">
			<div class="modal-content border-0 shadow-sm">
				<div class="modal-header bg-success text-white">
					<h2 class="h5 modal-title" id="modalTambahAkunPuskesmasLabel">
						<i class="fas fa-user-plus mr-2" aria-hidden="true"></i>Akun untuk <?= html_escape((string) $puskesmas->nama_puskesmas); ?>
					</h2>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Tutup">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<form action="<?= site_url('kelola_dokter_nakes/store'); ?>" method="post">
					<input type="hidden" name="<?= html_escape($this->security->get_csrf_token_name()); ?>" value="<?= html_escape($this->security->get_csrf_hash()); ?>">
					<input type="hidden" name="remark" value="<?= html_escape((string) $puskesmas->kode_pkm); ?>">
					<input type="hidden" name="status" value="aktif">
					<div class="modal-body">
						<div class="form-group">
							<label for="akunNama">Nama pengelola</label>
							<input type="text" class="form-control" id="akunNama" name="nama" required maxlength="150">
						</div>
						<div class="form-group">
							<label for="akunEmail">Email</label>
							<input type="email" class="form-control" id="akunEmail" name="email" required maxlength="150">
						</div>
						<div class="form-group">
							<label for="akunUsername">Nama pengguna</label>
							<input type="text" class="form-control" id="akunUsername" name="username" required maxlength="100">
						</div>
						<div class="form-row">
							<div class="form-group col-md-6">
								<label for="akunPassword">Password</label>
								<input type="password" class="form-control" id="akunPassword" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
							</div>
							<div class="form-group col-md-6">
								<label for="akunPasswordConfirm">Konfirmasi password</label>
								<input type="password" class="form-control" id="akunPasswordConfirm" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
							</div>
						</div>
						<p class="doclinc-muted-text mb-0">Minimal 8 karakter: huruf besar, huruf kecil, angka, dan karakter khusus.</p>
					</div>
					<div class="modal-footer border-0">
						<button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
							<i class="fas fa-times-circle mr-1" aria-hidden="true"></i>Batal
						</button>
						<button type="submit" class="btn btn-primary">
							<i class="fas fa-check-circle mr-1" aria-hidden="true"></i>Simpan akun
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
<?php endif; ?>

</div>
