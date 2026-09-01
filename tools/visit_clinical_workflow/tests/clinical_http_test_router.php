<?php
$root = dirname(__DIR__, 3); $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; $file = $root . $path; if ($path !== '/' && is_file($file)) return false; require $root . DIRECTORY_SEPARATOR . 'index.php';
