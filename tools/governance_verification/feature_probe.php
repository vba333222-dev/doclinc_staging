<?php
declare(strict_types=1);
define('BASEPATH', true);
require_once '/var/www/html/application/libraries/Doclinc_feature_flags.php';
$r = Doclinc_feature_flags::resolve(getenv('DOCLINC_NAKES_PLACEMENT_ENABLED'), getenv('DOCLINC_NAKES_PLACEMENT_ENVIRONMENT'), getenv('DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT'));
echo $r['enabled'] ? "ON\n" : "OFF\n";
