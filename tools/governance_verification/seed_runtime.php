<?php
declare(strict_types=1);
$db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASS'), (string)getenv('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'seed_db_connect_failed'); exit(1); }
$sql = base64_decode((string)getenv('SEED_SQL_B64'), true);
$adminPassword = base64_decode((string)getenv('SEED_ADMIN_PASSWORD_B64'), true);
if ($sql === false || $adminPassword === false || $adminPassword === '') { fwrite(STDERR, 'seed_input_failed'); exit(1); }
$adminHash = password_hash($adminPassword, PASSWORD_BCRYPT);
$sql = str_replace('__ADMIN_HASH__', $db->real_escape_string($adminHash), $sql);
if (!$db->multi_query($sql)) { fwrite(STDERR, 'seed_sql_failed'); exit(1); }
while ($db->more_results() && $db->next_result()) {}
echo "seeded\n";
