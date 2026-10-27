<?php
defined('BASEPATH') or exit('No direct script access allowed');

/* Panel admin memuat pustaka dari aplikasi utama, sama seperti
   `Password_strength_policy`: daftar Puskesmas inti dipakai juga oleh pengujian
   di `tools/`, jadi definisinya tidak boleh hanya hidup di dalam panel. */
require_once dirname(APPPATH, 2) . '/application/libraries/Core_puskesmas.php';
