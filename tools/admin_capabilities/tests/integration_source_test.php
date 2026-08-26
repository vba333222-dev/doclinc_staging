<?php
$controller = file_get_contents(dirname(__DIR__, 3) . '/admin_menu/application/modules/rekam_medis/controllers/Rekam_medis.php');
$gateway = file_get_contents(dirname(__DIR__, 3) . '/admin_menu/application/libraries/Admin_clinical_access_gateway.php');
$model = file_get_contents(dirname(__DIR__, 3) . '/admin_menu/application/modules/rekam_medis/models/Rekam_medis_m.php');
function integration_expect($ok, $label) { if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); } echo "PASS: {$label}\n"; }
integration_expect(strpos($controller, 'Admin_clinical_access_gateway') !== false, 'Admin record controller uses clinical access gateway');
integration_expect(strpos($gateway, 'Clinical_access_audit_service') !== false, 'Admin record gateway invokes clinical audit contract');
integration_expect(strpos($controller, 'featureEnabled') !== false && strpos($controller, '403') !== false, 'clinical detail remains denied when feature is off');
integration_expect(strpos($model, 'get_record_detail') !== false && strpos($model, 'diagnosis') !== false, 'Admin record model has read-only detail projection');
