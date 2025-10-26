<?php
// /escense/smm/tg_diag.php
// Диагностический скрипт: берёт аккаунт из БД по ?acc_id=ID и делает шаги getMe/getChat/sendMessage.
// УДАЛИ этот файл после проверки!

require_once __DIR__ . '/inc/functions.php';

header('Content-Type: text/plain; charset=utf-8');

$accId = (int)($_GET['acc_id'] ?? 0);
if ($accId <= 0) {
  http_response_code(400);
  echo "usage: tg_diag.php?acc_id=ID\n";
  exit;
}

$stmt = $pdo->prepare("SELECT a.id, a.external_id, a.access_token, sp.key_name AS provider
  FROM social_accounts a JOIN social_providers sp ON sp.id=a.provider_id WHERE a.id=?");
$stmt->execute([$accId]);
$row = $stmt->fetch();

if (!$row) { echo "account not found\n"; exit; }
if ($row['provider'] !== 'telegram') { echo "provider is not telegram\n"; exit; }

$token = trim(dec_token($row['access_token'] ?? ''));
$chat  = trim($row['external_id'] ?? '');

echo "== INPUT ==\n";
echo "acc_id:      {$row['id']}\n";
echo "provider:    {$row['provider']}\n";
echo "chat(raw):   {$chat}\n";
echo "token(len):  ".strlen($token)."\n";
echo "token(mask): ".(strlen($token)<=12?$token:(substr($token,0,6).'…'.substr($token,-6)))."\n\n";

// нормализуем chat: t.me/alias -> @alias, alias -> @alias, для приватки нужно -100...
if (preg_match('~^https?://t\.me/([A-Za-z0-9_]+)$~i', $chat, $m)) $chat='@'.$m[1];
if ($chat && $chat[0] !== '@' && !preg_match('~^-?\d+$~', $chat)) $chat='@'.$chat;

echo "chat(norm):  {$chat}\n\n";

if ($token === '') {
  echo "❌ token empty after dec_token() — проверь APP_KEY/шифр/тип поля\n";
  exit;
}

// Шаг 1: getMe — валиден ли токен?
[$c1,$r1,$e1] = http_get_raw("https://api.telegram.org/bot{$token}/getMe");
echo "GET getMe => code={$c1}\n{$r1}\nerr={$e1}\n\n";
$j1 = $r1 ? json_decode($r1,true) : null;
if ($c1 === 0) {
  echo "❌ Нет соединения с api.telegram.org (таймаут). Это не токен.\n";
  echo "- форсируй IPv4 (см. inc/functions.php CURLOPT_IPRESOLVE)\n";
  echo "- проверь у хостинга исходящие HTTPS на api.telegram.org:443\n";
  exit;
}

// Шаг 2: getChat — видит ли бот канал/чат?
[$c2,$r2,$e2] = http_get_raw("https://api.telegram.org/bot{$token}/getChat?chat_id=".urlencode($chat));
echo "GET getChat => code={$c2}\n{$r2}\nerr={$e2}\n\n";
$j2 = $r2 ? json_decode($r2,true) : null;
if (!($c2===200 && !empty($j2['ok']))) {
  echo "❌ chat_id неверный или бот не админ канала. Добавь бота админом. Для приватного канала нужен числовой -100...\n";
  exit;
}

// Шаг 3: sendMessage — есть ли право публиковать?
[$c3,$r3,$e3] = http_get_raw("https://api.telegram.org/bot{$token}/sendMessage?chat_id=".urlencode($chat)."&text=".urlencode("diag ping ✓"));
echo "GET sendMessage => code={$c3}\n{$r3}\nerr={$e3}\n\n";
$j3 = $r3 ? json_decode($r3,true) : null;
if (!($c3===200 && !empty($j3['ok']))) {
  echo "❌ нет права публиковать. Сделай бота админом канала (право «Публиковать сообщения»).\n";
  exit;
}

echo "✅ Всё ок: токен валиден, чат доступен, публикация разрешена.\n";