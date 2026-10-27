<?php
/* Reset password akun koordinator satu Puskesmas.
   Halaman ini hanya punya satu pekerjaan: mengganti password akun yang tertaut
   ke Puskesmas ini. Nama pengguna dan Puskesmas ditampilkan supaya Administrator
   tidak mengganti password akun yang salah - satu Puskesmas bisa punya lebih
   dari satu akun, dan yang dikelola di sini adalah akun koordinatornya. */
$puskesmas = isset($puskesmas) ? $puskesmas : null;
$account = isset($account) ? $account : null;

if (!$puskesmas) {
	echo '<div class="container-fluid py-4"><div class="alert alert-danger">Puskesmas tidak ditemukan.</div></div>';
	return;
}
?>
<div class="doclinc-admin-page doclinc-akun-page">
	<?php
	$page_head = array(
		'title' => 'Password akun Puskesmas',
		'sub' => (string) $puskesmas->nama_puskesmas . ' · Kode ' . (string) $puskesmas->kode_pkm,
		'actions' => array(
			array('kind' => 'link', 'label' => 'Kembali ke detail', 'href' => site_url('kelola_dokter_nakes/detail/' . rawurlencode((string) $puskesmas->kode_pkm)), 'icon' => 'fas fa-arrow-left', 'variant' => 'outline-secondary'),
		),
	);
	$this->load->view('commons/page_head', array('page_head' => $page_head));
	?>

	<div class="container-fluid">
		<?php if ($this->session->flashdata('error')): ?>
			<div class="alert alert-danger shadow-sm" role="alert"><?= html_escape($this->session->flashdata('error')); ?></div>
		<?php endif; ?>

		<div class="row">
			<div class="col-lg-6">
				<div class="card shadow mb-4">
					<div class="card-header py-3">
						<h2 class="h6 m-0 font-weight-bold"><i class="fas fa-key mr-2" aria-hidden="true"></i>Password baru</h2>
					</div>
					<div class="card-body">
						<?php if (!$account): ?>
							<div class="alert alert-warning mb-3" role="alert">
								Puskesmas ini belum punya akun login, jadi tidak ada password yang bisa diganti.
							</div>
							<a class="btn btn-primary" href="<?= html_escape(site_url('kelola_dokter_nakes/detail/' . rawurlencode((string) $puskesmas->kode_pkm))); ?>">
								<i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>Kembali ke detail Puskesmas
							</a>
						<?php else: ?>
							<dl class="row">
								<dt class="col-5">Nama pengguna</dt>
								<dd class="col-7"><?= html_escape((string) $account->username); ?></dd>
								<dt class="col-5">Status akun</dt>
								<dd class="col-7"><?= html_escape(strtolower(trim((string) $account->status)) === 'aktif' ? 'Aktif' : 'Nonaktif'); ?></dd>
							</dl>
							<form action="<?= site_url('kelola_dokter_nakes/reset_password'); ?>" method="post">
								<input type="hidden" name="<?= html_escape($this->security->get_csrf_token_name()); ?>" value="<?= html_escape($this->security->get_csrf_hash()); ?>">
								<input type="hidden" name="kode_pkm" value="<?= html_escape((string) $puskesmas->kode_pkm); ?>">
								<div class="form-group">
									<label for="puskesmasPassword">Password baru</label>
									<input type="password" class="form-control" id="puskesmasPassword" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
									<small class="text-muted">Minimal 8 karakter: huruf besar, huruf kecil, angka, dan karakter khusus. Tanpa spasi.</small>
								</div>
								<div class="form-group">
									<label for="puskesmasPasswordConfirm">Ulangi password baru</label>
									<input type="password" class="form-control" id="puskesmasPasswordConfirm" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
								</div>
								<button type="submit" class="btn btn-primary">
									<i class="fas fa-save mr-1" aria-hidden="true"></i>Simpan password
								</button>
							</form>
							<p class="doclinc-muted-text mt-3 mb-0">
								Setelah disimpan, semua sesi akun ini dicabut dan pengelola Puskesmas diminta membuat
								password pribadinya saat masuk berikutnya. Sampaikan password sementara ini lewat jalur resmi.
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
