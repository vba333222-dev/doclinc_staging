<?php
require_once dirname(APPPATH, 2) . '/application/libraries/Nakes_credential_policy.php';
/* Daftar Puskesmas inti. Satu baris = satu Puskesmas, dan seluruh pekerjaan
   lanjutan (profil fasilitas, akun, staf) ada di halaman detailnya. Daftar ini
   sengaja TIDAK memuat formulir apa pun: dulu halaman ini adalah daftar akun
   dengan modal tambah/ubah, sementara Puskesmas-nya sendiri dikelola di dua
   layar lain. */
$credential_policy = new Nakes_credential_policy();
$puskesmas_rows = isset($puskesmas_rows) && is_array($puskesmas_rows) ? $puskesmas_rows : array();

$summary = array('account_missing' => 0, 'credential_pending' => 0, 'staff_empty' => 0, 'master_missing' => 0);
$readiness_summary = isset($puskesmas_readiness_summary) && is_array($puskesmas_readiness_summary)
	? $puskesmas_readiness_summary
	: array('total' => 0, 'ready' => 0, 'attention' => 0, 'issue_counts' => array());
$row_states = array();
foreach ($puskesmas_rows as $puskesmas_row) {
	$account_status = strtolower(trim((string) ($puskesmas_row->account_status ?? '')));
	if ((int) ($puskesmas_row->account_user_id ?? 0) < 1) {
		$summary['account_missing']++;
		$row_states[(string) $puskesmas_row->kode_pkm] = array('label' => 'Belum ada akun', 'class' => 'badge-secondary');
		continue;
	}
	if ($account_status !== 'aktif') {
		$row_states[(string) $puskesmas_row->kode_pkm] = array('label' => 'Akun nonaktif', 'class' => 'badge-secondary');
		continue;
	}
	$credential_state = $credential_policy->state(
		'dokter',
		(int) ($puskesmas_row->must_change_password ?? 0),
		$puskesmas_row->password_changed_at ?? null
	);
	if ($credential_state !== Nakes_credential_policy::ACTIVE) {
		$summary['credential_pending']++;
		$row_states[(string) $puskesmas_row->kode_pkm] = array('label' => 'Password belum diganti', 'class' => 'badge-warning');
		continue;
	}
	$row_states[(string) $puskesmas_row->kode_pkm] = array('label' => 'Siap dipakai', 'class' => 'badge-success');
}
foreach ($puskesmas_rows as $puskesmas_row) {
	if ((int) ($puskesmas_row->staff_active ?? 0) < 1) {
		$summary['staff_empty']++;
	}
	if (empty($puskesmas_row->master_ready)) {
		$summary['master_missing']++;
	}
}
?>
<div class="doclinc-admin-page doclinc-akun-page">
	<?php
	$page_head = array(
		'title' => 'Puskesmas',
		'sub' => 'Sembilan Puskesmas inti Kota Cilegon. Buka satu baris untuk mengubah profil fasilitas, akun login, dan stafnya.',
	);
	$this->load->view('commons/page_head', array('page_head' => $page_head));
	?>

	<div class="container-fluid">
		<?php if ($summary['master_missing'] > 0): ?>
			<div class="alert alert-warning shadow-sm" role="alert">
				<strong>Sebagian Puskesmas belum ada di master fasilitas:</strong>
				<?= html_escape((string) $summary['master_missing']); ?> dari <?= html_escape((string) count($puskesmas_rows)); ?> baris.
				Profil fasilitas tidak dapat disimpan untuk baris tersebut sampai migrasi master dijalankan.
			</div>
		<?php endif; ?>
		<?php if ($summary['account_missing'] > 0): ?>
			<div class="alert alert-danger shadow-sm" role="alert">
				<strong>Puskesmas tanpa akun login:</strong>
				<?= html_escape((string) $summary['account_missing']); ?> Puskesmas.
				Permintaan warga tidak dapat diterima Puskesmas yang tidak punya akun aktif.
			</div>
		<?php endif; ?>

		<?php if ($readiness_summary['attention'] > 0): ?>
			<div class="alert alert-warning shadow-sm" role="alert">
				<strong>Kesiapan Puskesmas aktif:</strong> <?= html_escape((string) $readiness_summary['attention']); ?> dari
				<?= html_escape((string) $readiness_summary['total']); ?> Puskesmas aktif belum lengkap data pelayanannya.
				Lengkapi alamat dan titik lokasi dari halaman detail masing-masing.
			</div>
		<?php endif; ?>

		<?php
		$list_card = array(
			'part' => 'open',
			'title' => 'Daftar Puskesmas',
			'count' => count($puskesmas_rows) . ' Puskesmas',
			'sub' => 'Kode resmi Kemenkes, nama fasilitas, dan kepala Puskesmas.',
			'card_class' => 'doclinc-account-list-card',
		);
		$this->load->view('commons/list_card', array('list_card' => $list_card));
		?>

		<?php if (empty($puskesmas_rows)): ?>
			<div class="doclinc-account-empty">Daftar Puskesmas inti belum tersedia.</div>
		<?php else: ?>
			<?php if ($readiness_summary['total'] > 1): ?>
				<div class="d-flex justify-content-end mb-3">
					<label class="sr-only" for="puskesmasReadinessFilter">Saring menurut kesiapan</label>
					<select class="form-control form-control-sm w-auto" id="puskesmasReadinessFilter">
						<option value="all">Semua Puskesmas</option>
						<option value="attention">Perlu dilengkapi</option>
						<option value="ready">Siap dipakai</option>
					</select>
				</div>
			<?php endif; ?>
			<div class="table-responsive">
				<table class="table table-hover align-middle mb-0">
					<thead>
						<tr>
							<th scope="col">Kode</th>
							<th scope="col">Puskesmas</th>
							<th scope="col">Nama kepala</th>
							<th scope="col">Akun login</th>
							<th scope="col">Kesiapan</th>
							<th scope="col">Staf aktif</th>
							<th scope="col">Tindakan</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($puskesmas_rows as $row): ?>
							<?php
							$kode_pkm = (string) $row->kode_pkm;
							$state = isset($row_states[$kode_pkm]) ? $row_states[$kode_pkm] : array('label' => 'Status belum tersedia', 'class' => 'badge-secondary');
							$detail_url = site_url('kelola_dokter_nakes/detail/' . rawurlencode($kode_pkm));
							$kepala = trim((string) ($row->kepala_puskesmas ?? ''));
							$status = (string) ($row->status ?? '');
							$readiness_issues = (array) ($row->readiness_issues ?? array());
							$readiness_state = $status === 'aktif' ? (empty($readiness_issues) ? 'ready' : 'attention') : 'inactive';
							$readiness_badges = array(
								'ready' => array('label' => 'Siap dipakai', 'class' => 'badge-success'),
								'attention' => array('label' => 'Perlu dilengkapi', 'class' => 'badge-warning'),
								'inactive' => array('label' => 'Nonaktif', 'class' => 'badge-secondary'),
							);
							$readiness_badge = $readiness_badges[$readiness_state];
							?>
							<tr data-puskesmas-readiness="<?= html_escape($readiness_state); ?>">
								<td><span class="dl-adm-help-code"><?= html_escape($kode_pkm); ?></span></td>
								<td>
									<a href="<?= html_escape($detail_url); ?>"><strong><?= html_escape((string) $row->nama_puskesmas); ?></strong></a>
									<div class="small text-muted">
										Kecamatan <?= html_escape((string) $row->kecamatan); ?>
										<?php if (!$row->master_ready): ?>
											&middot; belum ada di master fasilitas
										<?php elseif ((string) $row->status !== 'aktif'): ?>
											&middot; status master: <?= html_escape((string) $row->status); ?>
										<?php endif; ?>
									</div>
								</td>
								<td>
									<?php if ($kepala !== ''): ?>
										<?= html_escape($kepala); ?>
									<?php else: ?>
										<span class="text-muted">Belum diisi</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ((int) $row->account_user_id > 0): ?>
										<span class="d-block"><?= html_escape((string) $row->account_username); ?></span>
									<?php endif; ?>
									<span class="badge <?= html_escape($state['class']); ?>"><?= html_escape($state['label']); ?></span>
								</td>
								<td><span class="badge <?= html_escape($readiness_badge['class']); ?>"><?= html_escape($readiness_badge['label']); ?></span></td>
								<td><?= html_escape((string) (int) $row->staff_active); ?> / <?= html_escape((string) (int) $row->staff_total); ?></td>
								<td>
									<a class="btn btn-sm btn-primary" href="<?= html_escape($detail_url); ?>">
										<i class="fas fa-hospital-user mr-1" aria-hidden="true"></i>Kelola
									</a>
									<?php if ((int) $row->account_user_id > 0): ?>
										<a class="btn btn-sm btn-outline-secondary" href="<?= html_escape(site_url('kelola_dokter_nakes/password/' . rawurlencode($kode_pkm))); ?>">
											<i class="fas fa-key mr-1" aria-hidden="true"></i>Password
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ($summary['credential_pending'] > 0): ?>
				<p class="doclinc-muted-text mt-3 mb-0">
					<?= html_escape((string) $summary['credential_pending']); ?> akun Puskesmas masih memakai password yang diterbitkan Administrator.
					Akses operasionalnya terkunci sampai pengelola membuat password pribadi.
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		$list_card = array('part' => 'close');
		$this->load->view('commons/list_card', array('list_card' => $list_card));
		?>
	</div>
</div>

<?php if ($readiness_summary['total'] > 1): ?>
	<script>
		/* Saringan kesiapan hanya menyembunyikan baris; tidak ada permintaan
		   jaringan, jadi daftar tetap utuh saat saringan dilepas. */
		(function () {
			var filter = document.getElementById('puskesmasReadinessFilter');
			if (!filter) {
				return;
			}
			filter.addEventListener('change', function () {
				var wanted = filter.value;
				var rows = document.querySelectorAll('[data-puskesmas-readiness]');
				for (var i = 0; i < rows.length; i++) {
					var state = rows[i].getAttribute('data-puskesmas-readiness');
					var visible = wanted === 'all' || state === wanted;
					rows[i].classList.toggle('d-none', !visible);
				}
			});
		})();
	</script>
<?php endif; ?>

