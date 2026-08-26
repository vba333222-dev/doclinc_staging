<?php

function vcw_assert_true($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function vcw_assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' expected=' . var_export($expected, true) . ' actual=' . var_export($actual, true));
    }
}

function vcw_assert_safe_request_id($requestId)
{
    $requestId = (int) $requestId;
    vcw_assert_true($requestId !== 47 && $requestId !== 52, 'Quarantined request id used by test fixture');
}
