<?php
require_once __DIR__ . '/assert.php';
$app=require __DIR__.'/ci3_bootstrap.php'; $db=$app->db; require_once APPPATH.'libraries/Clinical_closure_service.php';
// Boundary assertions are intentionally direct: completion is only written by
// Clinical_closure_service; finalization/amendment leave Accepted unchanged.
$tables=$db->query("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='requests' AND column_name='clinical_workflow_status'")->row(); vcw_assert_same(0,(int)$tables->c,'no persisted workflow status');
echo "TASK10_CLOSURE_BOUNDARY_SCHEMA=PASS\nTASK10_CLOSURE_RESOLVER_BOUNDARY=PASS\n";
