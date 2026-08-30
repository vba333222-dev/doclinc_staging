<?php
$app=require __DIR__.'/ci3_bootstrap.php';
require_once APPPATH.'libraries/Clinical_finalization_service.php';
$request=(int)($argv[1]??0);$user=(int)($argv[2]??0);$key=(string)($argv[3]??'');$announce=(string)($argv[4]??'');$gate=(string)($argv[5]??'');$c=(int)$app->db->query('SELECT CONNECTION_ID() id')->row()->id;file_put_contents($announce,json_encode(['pid'=>getmypid(),'connection_id'=>$c]),LOCK_EX);$d=microtime(true)+15;while($gate!==''&&!is_file($gate)&&microtime(true)<$d)usleep(20000);$r=(new Clinical_finalization_service($app->db))->finalize($request,$user,$key);echo json_encode(['ok'=>($r['status']??'')==='success','connection_id'=>$c,'pid'=>getmypid(),'result'=>$r,'code'=>$r['safe_error_code']??null])."\n";
