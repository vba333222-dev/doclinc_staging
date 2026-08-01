<?php

define('BASEPATH', __DIR__);

if (!function_exists('base_url')) {
    function base_url($target = '')
    {
        return 'https://example.test/' . ltrim((string) $target, '/');
    }
}

require_once dirname(__DIR__, 3) . '/application/helpers/request_navigation_helper.php';

$passed = 0;
$failed = 0;

function navigation_expect($condition, $name)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n";
}

navigation_expect(doclinc_request_return_target('warga', 'Accepted') === 'home#riwayat', 'warga_active_history');
navigation_expect(doclinc_request_return_target('warga', 'Completed') === 'home#riwayat', 'warga_completed_history');
navigation_expect(doclinc_request_return_target('dokter', 'Accepted') === 'home_nakes#riwayat_konsul', 'nakes_active_history');
navigation_expect(doclinc_request_return_target('nakes', 'Pending') === 'home_nakes#riwayat_konsul', 'nakes_alias_active_history');
navigation_expect(doclinc_request_return_target('dokter', 'Completed') === 'home_nakes#riwayat_konsul_selesai', 'nakes_completed_history');
navigation_expect(doclinc_request_return_target('dokter', 'Cancelled') === 'home_nakes#riwayat_konsul_selesai', 'nakes_cancelled_history');
navigation_expect(doclinc_request_return_target('dokter', 'https://evil.example/') === 'home_nakes#riwayat_konsul', 'status_url_not_accepted');
navigation_expect(doclinc_request_return_target('https://evil.example/', 'Completed') === 'home#riwayat', 'role_url_not_accepted');
navigation_expect(doclinc_request_return_url('dokter', 'Completed') === 'https://example.test/home_nakes#riwayat_konsul_selesai', 'absolute_internal_url');

echo "NAVIGATION_UNIT_PASSED={$passed}\n";
echo "NAVIGATION_UNIT_FAILED={$failed}\n";
exit($failed === 0 ? 0 : 1);
