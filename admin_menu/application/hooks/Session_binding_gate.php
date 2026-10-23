<?php
defined('BASEPATH') or exit('No direct script access allowed');

/* Panel admin memakai gate sesi yang SAMA dengan aplikasi utama.
 *
 * Berkas ini dulu salinan yang menyimpang (49 baris): tanpa adopsi sesi lama,
 * tanpa jejak audit, tanpa penghapusan cookie, tanpa pembedaan sebab, dan
 * tanpa Sec-Fetch-Mode - padahal hook di aplikasi utama sudah punya semuanya.
 * Salinan seperti itu akan tertinggal lagi setiap kali gate utama diperbaiki,
 * jadi sekarang ia hanya menumpang implementasinya.
 *
 * Dua perbedaan antar-aplikasi dibaca dari config, bukan disalin ke sini:
 *   - session_binding_login_key    : 'is_login' (admin) vs 'logged_in' (utama)
 *   - session_binding_library_path : lokasi library bersama aplikasi utama
 *
 * Bila berkas gate utama tidak ada, CI3 melewati hook ini secara diam-diam
 * (system/core/Hooks.php memeriksa file_exists). Karena itu keberadaannya
 * diuji: lihat tools/admin_panel/tests/source_test.js.
 */
require_once dirname(APPPATH, 2) . '/application/hooks/Session_binding_gate.php';
