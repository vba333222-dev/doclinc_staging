<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$staff_id = isset($staff_id) ? (int) $staff_id : 0;
$active_placement = isset($active_placement) && is_object($active_placement) ? $active_placement : null;
$scheduled_transfer = isset($scheduled_transfer) && is_object($scheduled_transfer) ? $scheduled_transfer : null;
$puskesmas_options = isset($puskesmas_options) && is_array($puskesmas_options) ? $puskesmas_options : array();
$provisioning_csrf_token = isset($provisioning_csrf_token) ? (string) $provisioning_csrf_token : '';
$current_facility = $active_placement ? strtoupper((string) $active_placement->facility_code) : strtoupper((string) ($row->kode_pkm ?? ''));
$now_local = date('Y-m-d\TH:i');
$tomorrow_local = date('Y-m-d\TH:i', strtotime('+1 day'));
?>
<div class="modal fade" id="modalPlacement<?= $staff_id; ?>" tabindex="-1" role="dialog" aria-labelledby="modalPlacementLabel<?= $staff_id; ?>" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content border-0 shadow-sm">
			<div class="modal-header border-0">
				<div>
					<h5 class="modal-title mb-1" id="modalPlacementLabel<?= $staff_id; ?>">Penempatan Nakes</h5>
					<div class="small text-muted"><?= html_escape((string) ($row->nama ?? '-')); ?> · <?= html_escape((string) ($row->profesi ?? '-')); ?></div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div class="alert alert-info small">
					Penempatan menentukan fasilitas tempat Nakes boleh menerima tugas kunjungan. Tanpa penempatan aktif, form hasil kunjungan akan ditolak.
				</div>
				<?php if (!$active_placement): ?>
					<form action="<?= site_url('kelola_staff_puskesmas/create_placement'); ?>" method="post">
						<input type="hidden" name="_provisioning_csrf_token" value="<?= html_escape($provisioning_csrf_token); ?>">
						<input type="hidden" name="staff_id" value="<?= $staff_id; ?>">
						<div class="form-group">
							<label class="font-weight-bold">Fasilitas</label>
							<select class="form-control rounded-pill border-info" name="facility_code" required>
								<option value="">Pilih Puskesmas</option>
								<?php foreach ($puskesmas_options as $puskesmas): ?>
									<?php $option_code = strtoupper((string) ($puskesmas->kode_pkm ?? '')); ?>
									<option value="<?= html_escape($option_code); ?>" <?= $option_code === $current_facility ? 'selected' : ''; ?>>
										<?= html_escape((string) ($puskesmas->nama_puskesmas ?? $option_code)); ?> (<?= html_escape($option_code); ?>)
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="form-group">
							<label class="font-weight-bold">Berlaku mulai</label>
							<input type="datetime-local" class="form-control rounded-pill border-info" name="effective_from" value="<?= html_escape($now_local); ?>" max="<?= html_escape($now_local); ?>" required>
						</div>
						<div class="modal-footer border-0 px-0">
							<button type="button" class="btn btn-light rounded-pill border" data-dismiss="modal">Batal</button>
							<button type="submit" class="btn btn-info rounded-pill">Simpan penempatan</button>
						</div>
					</form>
				<?php else: ?>
					<div class="alert alert-success small">
						<div class="font-weight-bold">Penempatan aktif: <?= html_escape($current_facility); ?></div>
						<div>Sejak <?= html_escape(date('d M Y H:i', strtotime((string) $active_placement->effective_from))); ?></div>
					</div>
					<?php if ($scheduled_transfer): ?>
						<div class="alert alert-warning small">
							<div class="font-weight-bold">Transfer terjadwal</div>
							<div>Tujuan <?= html_escape((string) $scheduled_transfer->destination_facility_code); ?></div>
							<div>Efektif <?= html_escape(date('d M Y H:i', strtotime((string) $scheduled_transfer->effective_at))); ?></div>
							<?php if (!empty($scheduled_transfer->reason)): ?><div>Alasan: <?= html_escape((string) $scheduled_transfer->reason); ?></div><?php endif; ?>
						</div>
						<form action="<?= site_url('kelola_staff_puskesmas/cancel_transfer'); ?>" method="post" class="mb-2">
							<input type="hidden" name="_provisioning_csrf_token" value="<?= html_escape($provisioning_csrf_token); ?>">
							<input type="hidden" name="transfer_id" value="<?= (int) $scheduled_transfer->transfer_id; ?>">
							<button type="submit" class="btn btn-outline-danger rounded-pill btn-block">Batalkan transfer</button>
						</form>
						<form action="<?= site_url('kelola_staff_puskesmas/activate_transfer'); ?>" method="post">
							<input type="hidden" name="_provisioning_csrf_token" value="<?= html_escape($provisioning_csrf_token); ?>">
							<input type="hidden" name="transfer_id" value="<?= (int) $scheduled_transfer->transfer_id; ?>">
							<button type="submit" class="btn btn-outline-success rounded-pill btn-block" onclick="return confirm('Terapkan transfer sekarang? Pastikan waktu efektif sudah tiba.');">Terapkan transfer sekarang</button>
						</form>
					<?php else: ?>
						<form action="<?= site_url('kelola_staff_puskesmas/schedule_transfer'); ?>" method="post">
							<input type="hidden" name="_provisioning_csrf_token" value="<?= html_escape($provisioning_csrf_token); ?>">
							<input type="hidden" name="staff_id" value="<?= $staff_id; ?>">
							<div class="form-group">
								<label class="font-weight-bold">Fasilitas tujuan</label>
								<select class="form-control rounded-pill border-info" name="destination_facility" required>
									<option value="">Pilih Puskesmas tujuan</option>
									<?php foreach ($puskesmas_options as $puskesmas): ?>
										<?php $option_code = strtoupper((string) ($puskesmas->kode_pkm ?? '')); ?>
										<?php if ($option_code === $current_facility) continue; ?>
										<option value="<?= html_escape($option_code); ?>">
											<?= html_escape((string) ($puskesmas->nama_puskesmas ?? $option_code)); ?> (<?= html_escape($option_code); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="form-group">
								<label class="font-weight-bold">Efektif pada</label>
								<input type="datetime-local" class="form-control rounded-pill border-info" name="effective_at" value="<?= html_escape($tomorrow_local); ?>" required>
							</div>
							<div class="form-group">
								<label class="font-weight-bold">Alasan</label>
								<input type="text" class="form-control rounded-pill border-info" name="reason" maxlength="500" required>
							</div>
							<div class="modal-footer border-0 px-0">
								<button type="button" class="btn btn-light rounded-pill border" data-dismiss="modal">Batal</button>
								<button type="submit" class="btn btn-info rounded-pill">Jadwalkan transfer</button>
							</div>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
