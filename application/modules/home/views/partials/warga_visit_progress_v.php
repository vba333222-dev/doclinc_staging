<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if ((string) $request_status === 'Accepted') : ?>
	<?php if (!empty($is_visit)) : ?>
		<div class="doclinc-visit-summary mb-3">
			<p class="doclinc-visit-summary__label" data-visit-summary-label="<?= html_escape((int) $request_id); ?>"><?= html_escape($visit_label); ?></p>
			<?php if ($visit_updated !== '') : ?>
				<span class="doclinc-visit-meta" data-visit-summary-meta="<?= html_escape((int) $request_id); ?>">Diperbarui <?= html_escape($visit_updated); ?></span>
			<?php endif; ?>
			<?= doclinc_warga_visit_timeline($visit_timeline); ?>
		</div>
	<?php endif; ?>
	<div class="mt-3">
		<a href="<?= html_escape(base_url('chat?request_id=' . (int) $request_id)); ?>" class="btn btn-success btn-sm rounded-pill dl-btn-primary">
			<i class="fas fa-comments me-1"></i> Buka chat
		</a>
	</div>
	<?php if (!empty($is_visit) && !empty($visit_location_available)) : ?>
		<button type="button" class="btn btn-outline-success btn-sm rounded-pill visit-location-toggle" data-request-id="<?= html_escape((int) $request_id); ?>" data-map-id="visit-map-<?= html_escape((int) $request_id); ?>">
			<i class="fas fa-map-marker-alt me-1"></i> Lihat lokasi
		</button>
		<div class="doclinc-visit-status mt-2" data-visit-status="<?= html_escape((int) $request_id); ?>"></div>
		<div class="visit-map-toolbar d-none" data-visit-toolbar="<?= html_escape((int) $request_id); ?>">
			<button type="button" class="btn btn-light border visit-route-recenter" data-request-id="<?= html_escape((int) $request_id); ?>" data-map-id="visit-map-<?= html_escape((int) $request_id); ?>">
				<i class="fas fa-crosshairs me-1"></i> Pusatkan
			</button>
			<button type="button" class="btn btn-success visit-route-refresh" data-request-id="<?= html_escape((int) $request_id); ?>" data-map-id="visit-map-<?= html_escape((int) $request_id); ?>">
				<i class="fas fa-sync-alt me-1"></i> Perbarui
			</button>
		</div>
		<div id="visit-map-<?= html_escape((int) $request_id); ?>" class="doclinc-visit-map mt-2 d-none"></div>
		<div class="visit-route-card mt-2 d-none" data-visit-route-card="<?= html_escape((int) $request_id); ?>">
			<div class="d-flex justify-content-between align-items-start gap-2 mb-1">
				<div class="visit-route-card-title">Dalam perjalanan</div>
				<span class="visit-route-status" data-visit-route-status="<?= html_escape((int) $request_id); ?>">Menghitung...</span>
			</div>
			<div class="visit-route-card-row"><span>Jarak</span><strong data-visit-route-distance="<?= html_escape((int) $request_id); ?>">Menghitung...</strong></div>
			<div class="visit-route-card-row"><span>Estimasi tiba</span><strong data-visit-route-eta="<?= html_escape((int) $request_id); ?>">Menghitung...</strong></div>
			<div class="visit-route-card-row"><span>Terakhir diperbarui</span><strong data-visit-route-updated="<?= html_escape((int) $request_id); ?>">-</strong></div>
			<div class="visit-route-provider-note mt-1 d-none" data-visit-route-provider-note="<?= html_escape((int) $request_id); ?>"></div>
			<div class="visit-route-provider-note mt-1 d-none" data-visit-arrival-message="<?= html_escape((int) $request_id); ?>"></div>
		</div>
	<?php endif; ?>
<?php endif; ?>
