<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/inc/functions.php';

echo "<pre>";
echo "marker ESC_SMM_FUNCS_LOADED: " . (defined('ESC_SMM_FUNCS_LOADED') ? "YES" : "NO") . "\n";
echo "function http_get_raw: " . (function_exists('http_get_raw') ? "YES" : "NO") . "\n";

$files = get_included_files();
foreach ($files as $f) {
  if (substr($f, -18) === '/inc/functions.php') {
    echo "loaded file: " . $f . " (mtime=" . date('Y-m-d H:i:s', filemtime($f)) . ")\n";
  }
}

list($code, $body, $err) = http_get_raw('https://api.telegram.org');
echo "probe https://api.telegram.org -> code={$code}, err={$err}, body_len=".strlen($body)."\n";
echo "</pre>";