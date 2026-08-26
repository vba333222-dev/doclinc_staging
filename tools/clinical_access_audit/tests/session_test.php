<?php
define('BASEPATH', __DIR__);
require_once dirname(__DIR__, 3) . '/application/libraries/Clinical_access_audit_policy.php';
require_once dirname(__DIR__, 3) . '/application/libraries/Clinical_access_audit_session.php';
class FakeSession { public $data=array(); public function userdata($k){return $this->data[$k]??null;} public function set_userdata($k,$v){$this->data[$k]=$v;} }
$session = new FakeSession();
$nonce = Clinical_access_audit_session::ensure($session);
if ($nonce === '' || $session->userdata(Clinical_access_audit_session::SESSION_KEY) !== $nonce) exit(1);
if (Clinical_access_audit_session::ensure($session) !== $nonce) exit(1);
if (Clinical_access_audit_session::hash($nonce) === $nonce) exit(1);
echo "PASS: logical nonce survives session access and only hash is reportable\n";
