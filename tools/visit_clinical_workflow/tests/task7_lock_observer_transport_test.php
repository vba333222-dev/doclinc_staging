<?php

require_once __DIR__ . '/assert.php';
require_once __DIR__ . '/task7_lock_observer.php';

putenv('VCW_LOCK_OBSERVER_MODE=direct');
vcw_assert_true(getenv('DOCLINC_TEST_RUNTIME') === '1', 'direct observer test runtime guard');
vcw_assert_true(getenv('VCW_LOCK_OBSERVER_USER') !== false && getenv('VCW_LOCK_OBSERVER_PASSWORD') !== false, 'direct observer requires explicit observer credential');
$edges = task7_lock_observer_edges();
vcw_assert_true(is_array($edges), 'direct lock observer returns normalized edge list');
foreach ($edges as $edge) {
    vcw_assert_true(is_array($edge) && count($edge) === 2, 'direct lock observer edge shape');
    vcw_assert_true(is_int($edge[0]) && is_int($edge[1]), 'direct lock observer edge ids');
}
echo "TASK7_DIRECT_LOCK_OBSERVER=PASS\n";
