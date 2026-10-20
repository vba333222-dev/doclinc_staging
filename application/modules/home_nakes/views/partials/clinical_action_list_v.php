<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$clinical_map = isset($clinical_ui) && is_array($clinical_ui) ? $clinical_ui : array();
if (empty($clinical_map)) {
	return;
}
$clinical_rows = array();
if (isset($data_request_accept) && is_object($data_request_accept)) {
	foreach ($data_request_accept->result() as $clinical_request_row) {
		$clinical_rows[(int) $clinical_request_row->request_id] = $clinical_request_row;
	}
}
if (empty($clinical_rows)) {
	return;
}
$clinical_actions = array();
foreach ($clinical_map as $clinical_request_id => $clinical_entry) {
	if (empty($clinical_entry['state']) || $clinical_entry['state'] === 'COMPLETED' || !isset($clinical_rows[(int) $clinical_request_id])) {
		continue;
	}
	$clinical_actions[] = array('entry' => $clinical_entry, 'row' => $clinical_rows[(int) $clinical_request_id]);
}
if (empty($clinical_actions)) {
	return;
}
$clinical_actions = array_slice($clinical_actions, 0, 6);
?>
<section class="cw-card cw-action-list" aria-labelledby="cwActionListTitle">
	<div class="cw-card-head">
		<div>
			<h2 class="cw-section-title" id="cwActionListTitle">Perlu tindakan</h2>
			<p class="cw-meta">Langkah berikutnya untuk pasien yang Anda tangani.</p>
		</div>
	</div>
	<div class="cw-card-body">
		<div class="cw-action-items" role="list">
			<?php foreach ($clinical_actions as $clinical_action) :
				$clinical_entry = $clinical_action['entry'];
				$clinical_row = $clinical_action['row'];
				$clinical_queue = doclinc_request_queue_number_label($clinical_row);
				$clinical_name = isset($clinical_row->nama) ? (string) $clinical_row->nama : '';
				$clinical_url = base_url('konsultasi_nakes/konsultasi/' . (int) $clinical_row->request_id)
					. (isset($clinical_entry['next_action_url']) ? (string) $clinical_entry['next_action_url'] : '');
			?>
			<a class="cw-action-item" role="listitem" href="<?= html_escape($clinical_url); ?>" data-clinical-action="<?= html_escape((string) $clinical_entry['state']); ?>">
				<span class="cw-action-item-main">
					<strong><?= html_escape($clinical_name !== '' ? $clinical_name : 'Pasien'); ?></strong>
					<span class="cw-meta"><?= html_escape($clinical_queue); ?><?= !empty($clinical_row->consultation_mode) ? ' · ' . html_escape(doclinc_consultation_mode_label($clinical_row->consultation_mode)) : ''; ?></span>
				</span>
				<span class="cw-action-item-side">
					<?php $this->load->view('partials/clinical_state_chip_v', array('clinical_ui' => $clinical_entry)); ?>
					<span class="cw-action-item-next"><?= html_escape(isset($clinical_entry['next_action']) ? (string) $clinical_entry['next_action'] : 'Buka'); ?></span>
				</span>
			</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
