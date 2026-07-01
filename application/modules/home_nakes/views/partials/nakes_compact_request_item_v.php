<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="dl-latest-row <?= empty($is_last_latest_request) ? 'mb-2 pb-2 border-bottom' : ''; ?>">
	<div class="dl-latest-badge"><?= html_escape(doclinc_nakes_queue_badge($latest_request)); ?></div>
	<div class="flex-grow-1 min-w-0">
		<p class="dl-latest-name"><?= html_escape(strtoupper((string) $latest_request->nama)); ?></p>
		<div class="dl-latest-meta">No. Antrian: <?= html_escape($latest_queue_code); ?></div>
		<div class="dl-muted-copy mt-1"><?= html_escape(doclinc_nakes_short_text($latest_keluhan)); ?></div>
	</div>
	<a href="#req_konsul" class="btn btn-outline-success btn-sm rounded-pill fw-bold" onclick="showContent('req_konsul')">Lihat</a>
</div>
