<?php
/**
 * Kapabilitas admin.
 *
 * Semua keputusan di halaman ini berasal dari lapisan kapabilitas aplikasi utama
 * (flag + policy + service). View tidak menghitung izin sendiri: kalau server
 * menolak, pesannya datang dari kode galat aman lapisan itu.
 */
$schema_ready = !empty($schema_ready);
$feature_enabled = !empty($feature_enabled);
$actor = isset($actor) && is_array($actor) ? $actor : array('user_id' => 0, 'role' => '', 'status' => '');
$actor_is_active_admin = !empty($actor_is_active_admin);
$actor_is_super_admin = !empty($actor_is_super_admin);
$super_admin_count = (int) ($super_admin_count ?? 0);
$allowed_codes = isset($allowed_codes) && is_array($allowed_codes) ? $allowed_codes : array();
$admins = isset($admins) && is_array($admins) ? $admins : array();
$recent_audit = isset($recent_audit) && is_array($recent_audit) ? $recent_audit : array();
$feature_reason = (string) ($feature_reason ?? '');

$capability_labels = array(
	'super_admin' => array('Super admin', 'Boleh mengubah kapabilitas admin lain.'),
	'clinical_audit' => array('Audit klinis', 'Boleh membuka catatan akses rekam medis beserta alasannya.'),
	'clinical_taxonomy_manage' => array('Kelola taksonomi klinis', 'Boleh mengubah master diagnosis dan tindakan klinis.'),
);
$can_change = $feature_enabled && $actor_is_active_admin && $actor_is_super_admin;
?>
<div class="d-sm-flex align-items-center justify-content-between pt-4 pb-4 px-4 mt-n4 mx-n4 you-are-here doclinc-page-header">
	<div>
		<h1 class="h3 mb-1 font-weight-bold doclinc-page-title">
			<i class="fas fa-user-shield" aria-hidden="true"></i> Kapabilitas admin
		</h1>
		<p class="mb-0 doclinc-page-subtitle">
			Menentukan siapa boleh melakukan apa di panel ini, di luar peran <code>admin</code>.
		</p>
	</div>
	<a href="<?= html_escape(site_url('audit')); ?>" class="btn btn-outline-primary btn-sm shadow-sm">
		<i class="fas fa-clipboard-list" aria-hidden="true"></i> Penampil audit
	</a>
</div>

<?php if (!$schema_ready): ?>
	<div class="alert alert-warning" role="status">
		Tabel <code>users</code> atau <code>admin_user_capabilities</code> belum ada di database ini, jadi belum ada yang bisa dikelola.
	</div>
<?php else: ?>
	<?php if (!$feature_enabled): ?>
		<div class="alert alert-warning" role="status">
			<h2 class="h6 font-weight-bold">Fitur kapabilitas admin belum diaktifkan</h2>
			<p class="mb-2">
				Halaman ini tetap terbuka supaya keadaannya terlihat, tetapi <strong>tidak ada perubahan yang akan dijalankan</strong>:
				lapisan kapabilitas menolak lebih dulu, bukan hanya tombolnya disembunyikan.
			</p>
			<p class="mb-0">
				Mengaktifkannya lewat lingkungan, bukan dari panel:
				<code>DOCLINC_ADMIN_CAPABILITIES_ENABLED=1</code> beserta
				<code>DOCLINC_ADMIN_CAPABILITIES_ENVIRONMENT</code> dan
				<code>DOCLINC_RUNTIME_ENVIRONMENT</code> yang harus bernilai sama.
				Alasan teknis saat ini: <code><?= html_escape($feature_reason); ?></code>.
			</p>
		</div>
	<?php elseif (!$actor_is_active_admin): ?>
		<div class="alert alert-danger" role="status">
			Akun Anda bukan admin aktif (role <code><?= html_escape($actor['role']); ?></code>,
			status <code><?= html_escape($actor['status']); ?></code>), jadi kapabilitas tidak dapat ditampilkan.
		</div>
	<?php elseif (!$actor_is_super_admin): ?>
		<div class="alert alert-info" role="status">
			Anda dapat melihat daftar ini, tetapi hanya <strong>super admin</strong> yang boleh mengubah kapabilitas.
			Akun Anda belum memiliki kapabilitas <code>super_admin</code>.
		</div>
	<?php else: ?>
		<div class="alert alert-info" role="status">
			Anda bertindak sebagai super admin. Saat ini ada
			<strong><?= number_format($super_admin_count); ?></strong> super admin aktif;
			super admin terakhir tidak boleh dicabut karena panel akan kehilangan pengelolanya.
		</div>
	<?php endif; ?>

	<div class="card shadow-sm mb-4">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h2 class="h6 mb-0 font-weight-bold">Akun admin (<?= number_format(count($admins)); ?>)</h2>
			<span class="text-muted small">Hanya akun berperan admin yang bisa menerima kapabilitas</span>
		</div>
		<div class="card-body p-0">
			<?php if (empty($admins)): ?>
				<p class="text-muted m-3 mb-0">Belum ada akun berperan admin di database ini.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0">
						<caption class="px-3 pt-3">Satu kolom per kapabilitas; tombol menunjukkan keadaan saat ini.</caption>
						<thead>
							<tr>
								<th scope="col">Akun</th>
								<?php foreach ($allowed_codes as $code): ?>
									<th scope="col"><?= html_escape($capability_labels[$code][0] ?? $code); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($admins as $admin): ?>
								<tr>
									<td>
										<div class="font-weight-bold"><?= html_escape($admin['nama']); ?></div>
										<div class="text-muted small">
											#<?= (int) $admin['user_id']; ?> &middot; <?= html_escape($admin['email']); ?>
											<?php if (!$admin['aktif']): ?>
												<span class="badge badge-warning">tidak aktif</span>
											<?php endif; ?>
										</div>
									</td>
									<?php foreach ($allowed_codes as $code): ?>
										<?php
										$granted = in_array($code, $admin['codes'], true);
										$is_last_super = $code === 'super_admin' && $granted && $super_admin_count <= 1;
										$blocked_reason = !$admin['aktif']
											? 'Akun ini tidak aktif.'
											: ($is_last_super ? 'Super admin terakhir tidak boleh dicabut.' : '');
										?>
										<td>
											<?php if ($granted): ?>
												<span class="badge badge-success">diberikan</span>
											<?php else: ?>
												<span class="badge badge-light border">belum</span>
											<?php endif; ?>
											<?php if ($can_change && $blocked_reason === ''): ?>
												<?= form_open($granted ? 'kapabilitas/revoke' : 'kapabilitas/grant', array('class' => 'd-inline mt-1', 'method' => 'post')); ?>
													<input type="hidden" name="target_user_id" value="<?= (int) $admin['user_id']; ?>">
													<input type="hidden" name="capability_code" value="<?= html_escape($code); ?>">
													<button type="submit" class="btn btn-sm <?= $granted ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
														aria-label="<?= $granted ? 'Cabut' : 'Berikan'; ?> kapabilitas <?= html_escape($capability_labels[$code][0] ?? $code); ?> untuk <?= html_escape($admin['nama']); ?>">
														<i class="fas <?= $granted ? 'fa-minus-circle' : 'fa-plus-circle'; ?>" aria-hidden="true"></i>
														<?= $granted ? 'Cabut' : 'Berikan'; ?>
													</button>
												<?= form_close(); ?>
											<?php elseif ($blocked_reason !== ''): ?>
												<div class="text-muted small"><?= html_escape($blocked_reason); ?></div>
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h2 class="h6 mb-0 font-weight-bold">Riwayat perubahan kapabilitas</h2>
			<a href="<?= html_escape(site_url('audit')); ?>" class="btn btn-sm btn-outline-secondary">Buka penampil audit</a>
		</div>
		<div class="card-body p-0">
			<?php if (empty($recent_audit)): ?>
				<p class="text-muted m-3 mb-0">Belum ada perubahan kapabilitas yang tercatat.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0">
						<caption class="px-3 pt-3">Diambil dari <code>audit_logs</code> dengan aksi berawalan <code>admin_capability_</code>.</caption>
						<thead>
							<tr>
								<th scope="col">Waktu</th>
								<th scope="col">Aktor</th>
								<th scope="col">Aksi</th>
								<th scope="col">Rincian</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($recent_audit as $row): ?>
								<tr>
									<td><code><?= html_escape((string) $row->created_at); ?></code></td>
									<td>
										<?= html_escape((string) ($row->actor_name ?? '') !== '' ? (string) $row->actor_name : 'pengguna'); ?>
										<span class="text-muted">#<?= (int) $row->actor_user_id; ?></span>
									</td>
									<td><code><?= html_escape((string) $row->action); ?></code></td>
									<td><pre class="mb-0 small"><?= html_escape((string) $row->metadata_json); ?></pre></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header">
			<h2 class="h6 mb-0 font-weight-bold">Arti setiap kapabilitas</h2>
		</div>
		<div class="card-body">
			<ul class="mb-0">
				<?php foreach ($allowed_codes as $code): ?>
					<li class="mb-2">
						<code><?= html_escape($code); ?></code> &mdash;
						<strong><?= html_escape($capability_labels[$code][0] ?? $code); ?></strong>:
						<?= html_escape($capability_labels[$code][1] ?? ''); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
<?php endif; ?>
