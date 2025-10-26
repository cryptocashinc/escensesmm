<?php
if (session_status()===PHP_SESSION_NONE) session_start();
error_reporting(E_ALL); ini_set('display_errors',1);
require_once __DIR__ . '/../inc/functions.php';

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','staff','client'], true)) {
  json_response(['ok'=>false,'error'=>'forbidden'],403);
}
$post_id = (int)($_POST['post_id'] ?? $_GET['post_id'] ?? 0);
$acc_id  = (int)($_POST['account_id'] ?? $_GET['account_id'] ?? 0);
if ($post_id<=0 || $acc_id<=0) json_response(['ok'=>false,'error'=>'post_id/account_id required'],400);

$pdo->prepare("INSERT INTO smm_post_targets
  (post_id, client_id, social_account_id, schedule_at, timezone, result_status, created_at)
  VALUES (?,?,?,?,?,'queued',NOW())")
  ->execute([$post_id,NULL,$acc_id,date('Y-m-d H:i:s'),'Europe/Amsterdam']);

json_response(['ok'=>true]);
