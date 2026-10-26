<?php
/**
 * Penampil audit (baca saja).
 *
 * Semua angka di sini berasal dari `audit_logs`. Metadata ditampilkan apa adanya
 * sebagai JSON karena isinya berbeda-beda antar penulis (gate sesi, modul
 * wilayah, kapabilitas admin); menerjemahkannya dengan aturan tebakan justru
 * berisiko menampilkan sebab yang salah.
 */
$schema_ready = !empty($schema_ready);
$filters = isset($filters) && is_array($filters) ? $filters : array('days' => 30, 'action' => '', 'actor_user_id' => 0);
$total = (int) ($total ?? 0);
$logs = isset($logs) && is_array($logs) ? $logs : array();
$action_summary = isset($action_summary) && is_array($action_summary) ? $action_summary : array();
$actor_summary = isset($actor_summary) && is_array($actor_summary) ? $actor_summary : array();
$coverage = isset($coverage) && is_array($coverage) ? $coverage : array('terlama' => '', 'terbaru' => '');
$action_options = isset($action_options) && is_array($action_options) ? $action_options : array();
$actor_options = isset($actor_options) && is_array($actor_options) ? $actor_options : array();
$row_limit = (int) ($row_limit ?? 200);
$period_options = array(1 => '24 jam terakhir', 7 => '7 hari terakhir', 30 => '30 hari terakhir', 90 => '90 hari terakhir', 0 => 'Seluruh periode');

$metadata_text = static function ($json) {
	$json = (string) $json;
	if ($json === '') {
		return '';
	}
	$decoded = json_decode($json, true);
	if (!is_array($decoded)) {
		return $json;
	}
	$parts = array();
	foreach ($decoded as $key => $value) {
		if (is_array($value)) {
			$value = json_encode($value, JSON_UNESCAPED_SLASHES);
		} elseif (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		}
		$parts[] = $key . ': ' . (string) $value;
	}
	return implode(' · ', $parts);
};
?>
<?php
/* Kepala halaman dari partial bersama. `&middot;` ditulis sebagai karakter
   `·` karena partial meng-escape teks: entitas akan tampil harfiah. */
$page_head = array(
	'title' => 'Penampil audit',
	'sub' => 'Catatan aksi pengguna · ' . ($period_options[(int) $filters['days']] ?? 'Seluruh periode'),
);
$this->load->view('commons/page_head', array('page_head' => $page_head));
?>

<?php if (!$schema_ready): ?>
	<div class="alert alert-warning" role="status">
		Tabel <code>audit_logs</code> belum ada di database ini, jadi belum ada catatan yang bisa ditampilkan.
	</div>
<?php else: ?>
	<div class="alert alert-info" role="status">
		Halaman ini hanya membaca: tidak ada tombol yang bisa mengubah atau menghapus catatan audit.
		Catatan ditulis oleh gate sesi aplikasi utama, modul wilayah, dan pengelola kapabilitas admin.
		<?php if ($coverage['terlama'] !== ''): ?>
			Rentang yang tercakup pada filter ini: <strong><?= html_escape($coverage['terlama']); ?></strong>
			sampai <strong><?= html_escape($coverage['terbaru']); ?></strong>.
		<?php endif; ?>
	</div>

	<?= form_open('audit/filter', array('class' => 'dl-adm-toolbar', 'method' => 'post')); ?>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="audit_days">Periode</label>
			<select class="form-control" id="audit_days" name="days">
				<?php foreach ($period_options as $value => $label): ?>
					<option value="<?= (int) $value; ?>" <?= (int) $filters['days'] === (int) $value ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="audit_action">Jenis aksi</label>
			<select class="form-control" id="audit_action" name="action">
				<option value="">Semua aksi</option>
				<?php foreach ($action_options as $option): ?>
					<option value="<?= html_escape($option['action']); ?>" <?= $filters['action'] === $option['action'] ? 'selected' : ''; ?>>
						<?= html_escape($option['action']); ?> (<?= number_format($option['total']); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-field">
			<label class="dl-adm-toolbar-label" for="audit_actor">Aktor</label>
			<select class="form-control" id="audit_actor" name="actor_user_id">
				<option value="0">Semua aktor</option>
				<?php foreach ($actor_options as $option): ?>
					<option value="<?= (int) $option['actor_user_id']; ?>" <?= (int) $filters['actor_user_id'] === (int) $option['actor_user_id'] ? 'selected' : ''; ?>>
						<?= html_escape($option['nama'] !== '' ? $option['nama'] : 'pengguna'); ?> (#<?= (int) $option['actor_user_id']; ?>) (<?= number_format($option['total']); ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dl-adm-toolbar-actions">
			<button type="submit" class="btn btn-primary dl-adm-action">
				<i class="fas fa-filter" aria-hidden="true"></i> Terapkan filter
			</button>
		</div>
	<?= form_close(); ?>

	<div class="row mb-4">
		<div class="col-md-4 mb-3">
			<div class="card shadow-sm h-100">
				<div class="card-body">
					<div class="text-muted small text-uppercase font-weight-bold">Catatan pada filter ini</div>
					<div class="h3 mb-0 font-weight-bold"><code><?= number_format($total); ?></code></div>
				</div>
			</div>
		</div>
		<div class="col-md-4 mb-3">
			<div class="card shadow-sm h-100">
				<div class="card-body">
					<div class="text-muted small text-uppercase font-weight-bold">Ditampilkan</div>
					<div class="h3 mb-0 font-weight-bold"><code><?= number_format(count($logs)); ?></code></div>
					<div class="text-muted small">batas <?= number_format($row_limit); ?> baris terbaru per muat</div>
				</div>
			</div>
		</div>
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card shadow-sm h-100">
				<div class="card-header">
					<h2 class="h6 mb-0 font-weight-bold">Aksi terbanyak</h2>
				</div>
				<div class="card-body p-0">
					<?php if (empty($action_summary)): ?>
						<p class="text-muted m-3 mb-0">Belum ada catatan pada filter ini.</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<caption class="px-3 pt-3">Jumlah catatan per jenis aksi.</caption>
								<thead>
									<tr>
										<th scope="col">Aksi</th>
										<th scope="col" class="text-right">Jumlah</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($action_summary as $row): ?>
										<tr>
											<td><code><?= html_escape((string) $row->action); ?></code></td>
											<td class="text-right"><code><?= number_format((int) $row->total); ?></code></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card shadow-sm h-100">
				<div class="card-header">
					<h2 class="h6 mb-0 font-weight-bold">Aktor terbanyak</h2>
				</div>
				<div class="card-body p-0">
					<?php if (empty($actor_summary)): ?>
						<p class="text-muted m-3 mb-0">Belum ada catatan pada filter ini.</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<caption class="px-3 pt-3">Nama dari tabel pengguna; bila kosong, hanya id.</caption>
								<thead>
									<tr>
										<th scope="col">Aktor</th>
										<th scope="col" class="text-right">Jumlah</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($actor_summary as $row): ?>
										<tr>
											<td>
												<?= html_escape((string) ($row->nama ?? '') !== '' ? (string) $row->nama : 'pengguna'); ?>
												<span class="text-muted">#<?= (int) $row->actor_user_id; ?></span>
											</td>
											<td class="text-right"><code><?= number_format((int) $row->total); ?></code></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h2 class="h6 mb-0 font-weight-bold">Catatan terbaru</h2>
			<span class="text-muted small"><?= number_format($total); ?> catatan pada filter ini</span>
		</div>
		<div class="card-body p-0">
			<?php if (empty($logs)): ?>
				<p class="text-muted m-3 mb-0">
					Tidak ada catatan yang cocok dengan filter ini. Coba lebarkan periode atau pilih &ldquo;Semua aksi&rdquo;.
				</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-sm align-middle mb-0">
						<caption class="px-3 pt-3">Waktu dari server; metadata ditampilkan apa adanya.</caption>
						<thead>
							<tr>
								<th scope="col">Waktu</th>
								<th scope="col">Aktor</th>
								<th scope="col">Aksi</th>
								<th scope="col">Entitas</th>
								<th scope="col">Sumber</th>
								<th scope="col">Metadata</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($logs as $row): ?>
								<?php
								$summary = $metadata_text($row->metadata_json);
								$preview = $summary === ''
									? ''
									: (function_exists('mb_substr') ? mb_substr($summary, 0, 80) : substr($summary, 0, 80));
								?>
								<tr>
									<td><code><?= html_escape((string) $row->created_at); ?></code></td>
									<td>
										<?= html_escape((string) ($row->actor_name ?? '') !== '' ? (string) $row->actor_name : 'pengguna'); ?>
										<span class="text-muted">#<?= (int) $row->actor_user_id; ?></span>
									</td>
									<td><code><?= html_escape((string) $row->action); ?></code></td>
									<td>
										<?php if ((string) $row->entity_type === ''): ?>
											<span class="text-muted">-</span>
										<?php else: ?>
											<?= html_escape((string) $row->entity_type); ?>
											<span class="text-muted">/</span> <code><?= html_escape((string) $row->entity_id); ?></code>
										<?php endif; ?>
									</td>
									<td><code><?= html_escape((string) $row->ip_address); ?></code></td>
									<td>
										<?php if ($summary === ''): ?>
											<span class="text-muted">-</span>
										<?php else: ?>
											<details>
												<summary><?= html_escape($preview); ?><?= $preview !== $summary ? '…' : ''; ?></summary>
												<pre class="mb-0 small"><?= html_escape((string) $row->metadata_json); ?></pre>
											</details>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php if ($total > count($logs)): ?>
			<div class="card-footer text-muted small">
				Menampilkan <?= number_format(count($logs)); ?> catatan terbaru dari <?= number_format($total); ?>.
				Persempit periode atau pilih satu jenis aksi untuk melihat yang lebih lama.
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
