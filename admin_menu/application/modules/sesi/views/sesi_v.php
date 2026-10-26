<?php
/**
 * Panel sesi & keamanan (baca saja).
 *
 * Halaman ini sengaja tidak punya tombol "akhiri sesi": memutus koneksi lewat
 * Centrifugo terbukti mematikan semua koneksi akun itu (kode 3503) dan SDK yang
 * dipakai tidak menyambung ulang untuk kode 3500-3999. Pemutusan sesi tetap
 * terjadi lewat mekanisme sesi tunggal berbasis token per perangkat.
 */
$schema_ready = !empty($schema_ready);
$bindings = isset($bindings) && is_array($bindings) ? $bindings : array();
$summary = isset($summary) && is_array($summary) ? $summary : array('total' => 0, 'aktif' => 0, 'tercabut' => 0, 'tanpa_hash' => 0);
$audit = isset($audit) && is_array($audit) ? $audit : array();
$snapshot = isset($snapshot) && is_array($snapshot) ? $snapshot : array();
?>
<?php
/* Kepala halaman dari partial bersama. */
$page_head = array(
	'title' => 'Sesi dan keamanan',
	'sub' => 'Status sesi per akun dan konfigurasi keamanan.',
	'actions' => array(
		array('kind' => 'link', 'label' => 'Penampil audit', 'href' => site_url('audit'), 'icon' => 'fas fa-clipboard-list', 'variant' => 'outline-primary'),
	),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

<div class="alert alert-info" role="status">
	<h2 class="h6 font-weight-bold">Halaman ini tidak memutus sesi siapa pun</h2>
	<p class="mb-0">
		Bukan karena fiturnya belum dibuat, tetapi karena caranya berbahaya: memutus koneksi realtime
		sebuah akun terbukti ikut mematikan perangkat yang sah (kode 3503), sementara SDK yang dipakai
		tidak menyambung ulang pada rentang kode itu. Pemutusan sesi dilakukan oleh mekanisme sesi
		tunggal berbasis token per perangkat saat akun masuk di tempat lain.
	</p>
</div>

<div class="row mb-4">
	<div class="col-md-3 col-6 mb-3">
		<div class="card shadow-sm h-100"><div class="card-body">
			<div class="text-muted small text-uppercase font-weight-bold">Akun terikat</div>
			<div class="h3 mb-0 font-weight-bold"><code><?= number_format((int) $summary['total']); ?></code></div>
		</div></div>
	</div>
	<div class="col-md-3 col-6 mb-3">
		<div class="card shadow-sm h-100"><div class="card-body">
			<div class="text-muted small text-uppercase font-weight-bold">Sesi aktif</div>
			<div class="h3 mb-0 font-weight-bold"><code><?= number_format((int) $summary['aktif']); ?></code></div>
			<div class="text-muted small">pengikatan yang belum dicabut</div>
		</div></div>
	</div>
	<div class="col-md-3 col-6 mb-3">
		<div class="card shadow-sm h-100"><div class="card-body">
			<div class="text-muted small text-uppercase font-weight-bold">Sudah dicabut</div>
			<div class="h3 mb-0 font-weight-bold"><code><?= number_format((int) $summary['tercabut']); ?></code></div>
		</div></div>
	</div>
	<div class="col-md-3 col-6 mb-3">
		<div class="card shadow-sm h-100"><div class="card-body">
			<div class="text-muted small text-uppercase font-weight-bold">Tanpa hash token</div>
			<div class="h3 mb-0 font-weight-bold"><code><?= number_format((int) $summary['tanpa_hash']); ?></code></div>
			<div class="text-muted small">baris lama yang perlu diperhatikan</div>
		</div></div>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header d-flex align-items-center justify-content-between">
		<h2 class="h6 mb-0 font-weight-bold">Konfigurasi yang benar-benar berlaku</h2>
		<span class="text-muted small">dibaca dari config dan php.ini saat halaman dibuka</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm mb-0">
				<caption class="px-3 pt-3">Termasuk yang masih bermasalah.</caption>
				<tbody>
					<?php foreach ($snapshot as $row): ?>
						<tr>
							<th scope="row" class="pl-3"><?= html_escape($row['label']); ?></th>
							<td><code><?= html_escape($row['value'] === '' ? '(kosong)' : $row['value']); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<div class="card shadow-sm mb-4">
	<div class="card-header d-flex align-items-center justify-content-between">
		<h2 class="h6 mb-0 font-weight-bold">Pengikatan sesi per akun</h2>
		<span class="text-muted small">token disimpan sebagai hash, bukan token mentah</span>
	</div>
	<div class="card-body p-0">
		<?php if (!$schema_ready): ?>
			<p class="text-muted m-3 mb-0">Tabel <code>user_login_bindings</code> belum ada di database ini.</p>
		<?php elseif (empty($bindings)): ?>
			<p class="text-muted m-3 mb-0">Belum ada pengikatan sesi tercatat pada database ini.</p>
		<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<caption class="px-3 pt-3">Satu baris per akun; sesi tunggal menyimpan satu token.</caption>
					<thead>
						<tr>
							<th scope="col">Akun</th>
							<th scope="col">Keadaan</th>
							<th scope="col">Diterbitkan</th>
							<th scope="col">Diperbarui</th>
							<th scope="col">Token</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($bindings as $row): ?>
							<tr>
								<td>
									<div class="font-weight-bold"><?= html_escape((string) ($row->nama ?? '') !== '' ? (string) $row->nama : 'pengguna'); ?></div>
									<div class="text-muted small">
										#<?= (int) $row->user_id; ?> &middot; <?= html_escape((string) $row->role); ?>
										<?php if ((string) $row->status !== 'aktif'): ?>
											<span class="badge badge-warning"><?= html_escape((string) $row->status); ?></span>
										<?php endif; ?>
									</div>
								</td>
								<td>
									<?php if ($row->revoked_at === null): ?>
										<span class="badge badge-success">aktif</span>
									<?php else: ?>
										<span class="badge badge-light border">dicabut</span>
									<?php endif; ?>
								</td>
								<td><code><?= html_escape((string) $row->issued_at); ?></code></td>
								<td><code><?= html_escape((string) $row->updated_at); ?></code></td>
								<td>
									<?php if ((string) $row->token_hash === ''): ?>
										<span class="text-muted">tanpa hash</span>
									<?php else: ?>
										<code><?= html_escape(substr((string) $row->token_hash, 0, 10)); ?>&hellip;</code>
									<?php endif; ?>
								</td>
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
		<h2 class="h6 mb-0 font-weight-bold">Jejak audit sesi terbaru</h2>
		<a href="<?= html_escape(site_url('audit')); ?>" class="btn btn-sm btn-outline-secondary">Saring audit</a>
	</div>
	<div class="card-body p-0">
		<?php if (empty($audit)): ?>
			<p class="text-muted m-3 mb-0">Belum ada catatan audit bertema sesi.</p>
		<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm align-middle mb-0">
					<caption class="px-3 pt-3">Aksi berawalan <code>session_</code> atau <code>login_</code> dari <code>audit_logs</code>.</caption>
					<thead>
						<tr>
							<th scope="col">Waktu</th>
							<th scope="col">Aktor</th>
							<th scope="col">Aksi</th>
							<th scope="col">Rincian</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($audit as $row): ?>
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
		<h2 class="h6 mb-0 font-weight-bold">Catatan teknis</h2>
	</div>
	<div class="card-body">
		<details>
			<summary>Untuk pengembang: dua kendala penelusuran galat</summary>
			<ol class="mb-0 mt-2">
			<li class="mb-2">
				<strong><code>display_errors</code> dan <code>log_errors</code> mati.</strong>
				Akibatnya galat fatal PHP hanya tampak sebagai <em>500 berbadan kosong</em>. Yang masih tertolong
				adalah galat yang ditangkap penangan galat CodeIgniter; pesannya ada di
				<code>/var/log/doclinc/log-YYYY-MM-DD.php</code> di dalam container.
			</li>
			<li>
				<strong>Flashdata panel tidak pernah kedaluwarsa.</strong>
				Pesan <code>info</code> dari satu halaman masih muncul di halaman lain berkali-kali. Penanda menu
				sudah dilepas dari flashdata (kini murni dari segmen URL), tetapi penyebab di daur hidup sesi
				belum dituntaskan - perlu ditelusuri pada penyimpanan sesi
				<code><?= html_escape((string) ($snapshot[6]['value'] ?? '')); ?></code>.
			</li>
		</ol>
		</details>
	</div>
</div>
