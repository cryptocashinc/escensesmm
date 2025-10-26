<?php
require_once __DIR__ . '/../inc/functions.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: ../accounts.php?check=bad_id&status=error'); exit; }

$stmt = $pdo->prepare("SELECT a.*, sp.key_name AS provider FROM social_accounts a
  JOIN social_providers sp ON sp.id=a.provider_id WHERE a.id=?");
$stmt->execute([$id]); $row = $stmt->fetch();
if (!$row) { header('Location: ../accounts.php?check=not_found&status=error'); exit; }

$provider = $row['provider'];
$token = dec_token($row['access_token']);
$ext   = trim($row['external_id']);

$ok=false; $msg='';

if ($provider === 'telegram') {
  [$c,$r,$e] = http_get_raw("https://api.telegram.org/bot{$token}/sendMessage?chat_id=".urlencode($ext)."&text=".urlencode("test ✓"));
  $j=json_decode($r,true); $ok = ($c===200 && !empty($j['ok'])); $msg = $ok? 'sent':'failed: '.($j['description'] ?? $e);
} elseif ($provider === 'vk') {
  [$c,$r,$e] = http_post_form("https://api.vk.com/method/wall.post", ['owner_id'=>$ext,'message'=>'test ✓','v'=>'5.199','access_token'=>$token]);
  $j=json_decode($r,true); $ok = ($c===200 && isset($j['response']['post_id'])); $msg = $ok? 'sent':'failed: '.($j['error']['error_msg'] ?? $e);
} else {
  $msg='not implemented for provider';
}

try {
  $pdo->prepare("UPDATE social_accounts SET status=?, last_error=?, last_checked_at=NOW() WHERE id=?")
      ->execute([ $ok ? 'active' : 'error', $ok ? '' : $msg, $id ]);
} catch (\Throwable $e) {}

header('Location: ../accounts.php?check='.urlencode($msg).'&status='.($ok?'active':'error'));