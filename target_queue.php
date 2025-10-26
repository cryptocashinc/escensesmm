<?php require_once __DIR__ . '/../inc/functions.php';
$post_id=(int)($_POST['post_id'] ?? 0);
$acc_id=(int)($_POST['account_id'] ?? 0);
$dt=trim($_POST['datetime'] ?? '');
$tz=trim($_POST['tz'] ?? 'Europe/Amsterdam');
if($post_id>0 && $acc_id>0 && $dt){
  $stmt=$pdo->prepare("INSERT INTO smm_post_targets (post_id, client_id, social_account_id, schedule_at, timezone, result_status) VALUES (?,NULL,?,?,?,'queued')");
  $stmt->execute([$post_id,$acc_id,$dt,$tz]);
  json_response(['ok'=>true]);
} else {
  json_response(['error'=>'bad_params'],400);
}