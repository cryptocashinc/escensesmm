<?php
require_once __DIR__ . '/../inc/functions.php';

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: ../accounts.php?check=bad_id&status=error'); exit; }

$stmt = $pdo->prepare("SELECT a.*, sp.key_name AS provider FROM social_accounts a
  JOIN social_providers sp ON sp.id=a.provider_id WHERE a.id=?");
$stmt->execute([$id]); $row = $stmt->fetch();
if (!$row) { header('Location: ../accounts.php?check=not_found&status=error'); exit; }

$provider = $row['provider'];
$tokenEnc = $row['access_token']; $token = dec_token($tokenEnc);
$ext      = trim($row['external_id']);

$status='error'; $note=[]; $reason='';

if ($provider === 'telegram') {
  // 1) проверка токена
  [$c1,$r1,$e1] = http_get_raw("https://api.telegram.org/bot{$token}/getMe");
  $okToken = ($c1===200 && ($j=json_decode($r1,true)) && !empty($j['ok']));
  $note[] = $okToken? 'token ok':'bad token';
  if (!$okToken) $reason = 'Неверный BOT_TOKEN (getMe)';

  // 2) проверка chat_id
  [$c2,$r2,$e2] = http_get_raw("https://api.telegram.org/bot{$token}/getChat?chat_id=".urlencode($ext));
  $okChat = ($c2===200 && ($j2=json_decode($r2,true)) && !empty($j2['ok']));
  $note[] = $okChat? 'chat ok':'bad chat_id';
  if ($okToken && !$okChat) {
    $desc = ($j2['description'] ?? $e2 ?? '');
    $reason = 'Неверный chat_id (@alias или -100…): '.$desc;
  }

  // 3) попытка отправки
  $okSend=false;
  if ($okToken && $okChat) {
    [$c3,$r3,$e3] = http_get_raw("https://api.telegram.org/bot{$token}/sendMessage?chat_id=".urlencode($ext)."&text=".urlencode("ping ✓ from Escense SMM"));
    $j3=json_decode($r3,true);
    $okSend = ($c3===200 && !empty($j3['ok']));
    $note[] = $okSend? 'send ok':'send failed';
    if (!$okSend) {
      $desc = ($j3['description'] ?? $e3 ?? '');
      $reason = 'Нет права публиковать: '.$desc.' (добавьте бота админом канала и дайте право «Публиковать сообщения»)';
    }
  }

  if ($okToken && $okChat && $okSend) { $status='active'; $reason=''; }

} elseif ($provider === 'vk') {
  // 1) токен валиден?
  [$c1,$r1,$e1] = http_post_form("https://api.vk.com/method/users.get", ['v'=>'5.199','access_token'=>$token]);
  $j1=json_decode($r1,true);
  $okToken = ($c1===200 && empty($j1['error']));
  $note[] = $okToken? 'token ok':'bad token';
  if (!$okToken) $reason = 'Неверный токен сообщества (users.get)';

  // 2) пробная публикация
  $okSend=false; $owner=$ext;
  [$c2,$r2,$e2] = http_post_form("https://api.vk.com/method/wall.post", [
    'owner_id'=>$owner,'message'=>'ping ✓ from Escense SMM','v'=>'5.199','access_token'=>$token
  ]);
  $jr = json_decode($r2,true);
  if ($c2===200 && isset($jr['response']['post_id'])) { $okSend=true; }
  $note[] = $okSend? 'send ok':'send failed';
  if ($okToken && !$okSend) {
    $reason = 'Нет права на запись в стену или неверный owner_id: '.($jr['error']['error_msg'] ?? $e2 ?? '');
  }

  if ($okToken && $okSend) { $status='active'; $reason=''; }

} else {
  $status='unknown'; $reason='Проверка для провайдера не реализована';
  $note[]='provider check not implemented';
}

// сохраняем статус + причину + время
try {
  $pdo->prepare("UPDATE social_accounts SET status=?, last_error=?, last_checked_at=NOW() WHERE id=?")
      ->execute([$status, $reason, $id]);
} catch (\Throwable $e) { /* если колонок нет — просто скипаем */ }

header('Location: ../accounts.php?check='.urlencode(implode(', ', $note)).'&status='.$status);