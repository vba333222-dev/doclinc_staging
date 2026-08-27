<?php

$request = (int) ($argv[1] ?? 0);
$command = (int) ($argv[2] ?? 0);
$performer = (int) ($argv[3] ?? 0);
$key = (string) ($argv[4] ?? '');
$barrier = (string) ($argv[5] ?? '');
$mode = (string) ($argv[6] ?? 'assign');
$reason = (string) ($argv[7] ?? 'concurrent');
$expectedAssignment = (int) ($argv[8] ?? 0);
if ($request < 1 || $command < 1 || $performer < 1 || $key === '' || $barrier === '' || ($mode === 'reassign' && $expectedAssignment < 1)) {
    fwrite(STDERR, "worker arguments missing\n");
    exit(2);
}

$app = require __DIR__ . '/ci3_bootstrap.php';
$db = $app->db;
$connection = $db->query('SELECT CONNECTION_ID() AS connection_id')->row();
$ready = $barrier . DIRECTORY_SEPARATOR . 'ready-' . getmypid();
file_put_contents($ready, (string) $connection->connection_id);
$deadline = microtime(true) + 30;
while (!is_file($barrier . DIRECTORY_SEPARATOR . 'release')) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "worker barrier timeout\n");
        exit(3);
    }
    usleep(10000);
}

$service = new Visit_assignment_service($db, new Visit_workflow_policy($db, false));
$result = $mode === 'reassign'
    ? $service->reassign($request, $command, $expectedAssignment, $performer, $reason, $key)
    : $service->assign($request, $command, $performer, $key);
echo 'PID=' . getmypid() . ' CONNECTION_ID=' . (int) $connection->connection_id . ' RESULT=' . json_encode($result) . "\n";
