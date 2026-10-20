<?php
/**
 * Fase 1 - aturan state penugasan care team.
 *
 * Aturan ini murni (tanpa DB) supaya bisa diuji cepat: perubahan jenis
 * layanan, penggantian Nakes, dan penggantian dokter penanggung jawab.
 */
if (!defined('BASEPATH')) { define('BASEPATH', dirname(__DIR__, 3) . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', dirname(__DIR__, 3) . '/application/'); }

require_once APPPATH . 'libraries/Care_team_policy.php';

$passed = 0;
$failed = 0;
function guard_check($condition, $label)
{
    global $passed, $failed;
    if ($condition) { $passed++; echo "  ok   - {$label}\n"; return true; }
    $failed++; echo "  FAIL - {$label}\n"; return false;
}

$policy = new Care_team_policy();
$accepted = array('request_status' => 'Accepted', 'consultation_mode' => 'non_visit', 'visit_status' => 'not_started');
$disposition = (object) array('decision' => 'visit', 'required_profession' => 'Perawat');

echo "== jenis layanan ==\n";
guard_check($policy->modeChangeFailure($accepted, null, 0, false, 'non_visit') === null,
    'mode yang sama tidak diblokir');
guard_check($policy->modeChangeFailure(array_merge($accepted, array('visit_status' => 'in_service')), null, 0, false, 'non_visit') === 'VISIT_ALREADY_STARTED',
    'kunjungan berjalan mengunci mode');
guard_check($policy->modeChangeFailure(array_merge($accepted, array('visit_status' => 'completed')), null, 0, false, 'visit') === 'VISIT_ALREADY_STARTED',
    'kunjungan selesai tetap mengunci mode');
guard_check($policy->modeChangeFailure($accepted, $disposition, 0, true, 'visit') === 'MODE_LOCKED_BY_DISPOSITION',
    'disposisi aktif mengunci mode saat alur klinis nyala');
guard_check($policy->modeChangeFailure($accepted, $disposition, 0, false, 'visit') === null,
    'saat alur klinis mati disposisi tidak mengunci mode legacy');
guard_check($policy->modeChangeFailure($accepted, null, 1, true, 'visit') === 'PERFORMER_LOCKED',
    'performer aktif harus dibatalkan lebih dulu saat alur klinis nyala');
guard_check($policy->modeChangeFailure($accepted, null, 1, false, 'visit') === null,
    'jalur legacy tetap boleh membatalkan performer lewat perubahan mode');
guard_check($policy->modeChangeFailure($accepted, null, 0, false, 'visit') === null,
    'perubahan sah tetap diizinkan');

echo "\n== penunjukan Nakes ==\n";
guard_check($policy->performerChangeFailure($accepted) === null, 'sebelum kunjungan boleh menunjuk/ganti');
guard_check($policy->performerChangeFailure(array_merge($accepted, array('visit_status' => 'arrived'))) === 'VISIT_ALREADY_STARTED',
    'setelah tiba Nakes tidak dapat diganti');
guard_check($policy->performerChangeFailure(array_merge($accepted, array('visit_status' => 'in_service'))) === 'VISIT_ALREADY_STARTED',
    'saat ditangani Nakes tidak dapat diganti');

echo "\n== pergantian dokter penanggung jawab ==\n";
guard_check($policy->handoverFailure($accepted, null) === null, 'sebelum disposisi boleh diganti');
guard_check($policy->handoverFailure($accepted, $disposition) === 'HANDOVER_LOCKED', 'setelah disposisi terkunci');
guard_check($policy->handoverFailure(array_merge($accepted, array('visit_status' => 'en_route')), null) === 'HANDOVER_LOCKED',
    'setelah kunjungan mulai terkunci');

echo "\n== service memakai aturan ini ==\n";
$service = file_get_contents(APPPATH . 'libraries/Care_team_service.php');
guard_check(strpos($service, 'modeChangeFailure(') !== false, 'chooseMode memakai modeChangeFailure');
guard_check(strpos($service, 'performerChangeFailure(') !== false, 'assignVisitPerformer memakai performerChangeFailure');
guard_check(strpos($service, 'handoverFailure(') !== false, 'assignResponsibleDoctor memakai handoverFailure');
guard_check(strpos($service, "responsible_doctor.assigned") !== false, 'penunjukan dokter menulis event audit');
guard_check(strpos($service, "visit_performer.assigned") !== false, 'penunjukan Nakes menulis event audit');
guard_check(strpos($service, "service_mode.selected") !== false, 'perubahan mode menulis event audit');

$disposition_service = file_get_contents(APPPATH . 'libraries/Visit_disposition_service.php');
guard_check(strpos($disposition_service, 'cancelPerformerIfNeeded(') !== false, 'disposisi membatalkan performer yang tidak sesuai');
guard_check(strpos($disposition_service, "DISPOSITION_LOCKED") !== false, 'disposisi terkunci saat kunjungan berjalan');
guard_check(strpos($disposition_service, 'field_exists($field') !== false, 'kolom penyelesaian dijaga field_exists');

echo "\nCARE_WORKFLOW_GUARDS=PASSED={$passed}, FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
