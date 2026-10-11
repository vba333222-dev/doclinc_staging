<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$pic_map_request_id = isset($pic_map_request_id) ? (int) $pic_map_request_id : 0;
$pic_map_patient_lat = isset($pic_map_patient_lat) ? (string) $pic_map_patient_lat : '';
$pic_map_patient_lng = isset($pic_map_patient_lng) ? (string) $pic_map_patient_lng : '';
$pic_map_nakes_lat = isset($pic_map_nakes_lat) ? (string) $pic_map_nakes_lat : '';
$pic_map_nakes_lng = isset($pic_map_nakes_lng) ? (string) $pic_map_nakes_lng : '';
if ($pic_map_request_id < 1) {
	return;
}
?>
<button type="button" class="btn btn-outline-success btn-sm rounded-pill pic-map-toggle mt-2" data-request-id="<?= $pic_map_request_id; ?>" data-map-id="pic-map-<?= $pic_map_request_id; ?>">
	<i class="fas fa-map-marker-alt me-1"></i> Lihat lokasi Nakes
</button>
<div id="pic-map-<?= $pic_map_request_id; ?>" class="doclinc-visit-map mt-2 d-none"
	data-patient-lat="<?= html_escape($pic_map_patient_lat); ?>"
	data-patient-lng="<?= html_escape($pic_map_patient_lng); ?>"
	data-nakes-lat="<?= html_escape($pic_map_nakes_lat); ?>"
	data-nakes-lng="<?= html_escape($pic_map_nakes_lng); ?>"></div>
