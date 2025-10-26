<?php
if (session_status()===PHP_SESSION_NONE) session_start();
error_reporting(E_ALL); ini_set('display_errors',1);
require_once __DIR__ . '/../inc/functions.php';

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff'], true)) {
  json_response(['ok'=>false,'error'=>'forbidden'],403);
}

$_GET['limit'] = (string)max(1, (int)($_POST['limit'] ?? 5));
ob_start();
require __DIR__ . '/../worker_http.php';
$out = ob_get_clean();

json_response(['ok'=>true,'log'=>$out]);
