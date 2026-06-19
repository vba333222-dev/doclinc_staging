<?php
defined('BASEPATH') or exit('No direct script access allowed');

$config['firebase_service_account'] = getenv('FIREBASE_SERVICE_ACCOUNT_PATH') ?: false;
$config['firebase_device_token'] = getenv('FIREBASE_DEVICE_TOKEN') ?: '';
