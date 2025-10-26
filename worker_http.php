<?php
// /public_html/escense/smm/worker_http.php
if (session_status()===PHP_SESSION_NONE) session_start();
error_reporting(E_ALL); ini_set('display_errors',1);

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/provider.php';

$isCli   = (php_sapi_name()==='cli');
$isAdmin = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','staff'], true);
$gotKey  = isset($_GET['key']) ? (string)$_GET['key'] : '';
$hasCfg  = defined('SMM_CRON_KEY') && SMM_CRON_KEY;
$byKey   = ($gotKey && $hasCfg && hash_equals(SMM_CRON_KEY, $gotKey));
if (!($isCli || $isAdmin || $byKey)) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  echo "forbidden: need CLI or admin/staff session".($hasCfg?" or valid key":"");
  exit;
}

header('Content-Type: text/plain; charset=utf-8');
$limit = max(1, (int)($_GET['limit'] ?? 10));

$sql = "
SELECT t.*,
       p.title, p.body, p.first_comment, p.link_url, p.media_json,
       a.external_id, a.access_token, sp.key_name AS provider
FROM smm_post_targets t
JOIN smm_posts p ON p.id=t.post_id
JOIN social_accounts a ON a.id=t.social_account_id
JOIN social_providers sp ON sp.id=a.provider_id
WHERE t.result_status IN ('queued','retry')
  AND t.schedule_at <= NOW()
ORDER BY t.schedule_at ASC, t.id ASC
LIMIT {$limit}";
$jobs = $pdo->query($sql)->fetchAll();

if (!$jobs) { echo "no jobs\n"; exit; }

foreach ($jobs as $job) {
  $tid   = (int)$job['id'];
  $prov  = (string)$job['provider'];
  $ext   = (string)$job['external_id'];
  $token = dec_token((string)$job['access_token']); // ПЛЕЙН токен для адаптера

  // payload
  $media = [];
  if (!empty($job['media_json'])) {
    $arr = json_decode($job['media_json'], true);
    if (is_array($arr)) $media = $arr;
  }
  $payload = [
    'text'          => (string)($job['body'] ?? ''),
    'link'          => (string)($job['link_url'] ?? ''),
    'first_comment' => (string)($job['first_comment'] ?? ''),
    'media'         => $media,
  ];

  echo "job #{$tid} prov={$prov}\n";
  $ok=false; $err='';

  try {
    [$ok,$err] = provider_send($prov, $token, $ext, $payload);
  } catch (Throwable $ex) {
    $ok=false; $err=$ex->getMessage();
  }

  if ($ok) {
    $pdo->prepare("UPDATE smm_post_targets SET result_status='posted', result_message=NULL, posted_at=NOW() WHERE id=?")
        ->execute([$tid]);
    $pdo->prepare("INSERT INTO smm_post_logs (post_id, target_id, status, message, created_at)
                   VALUES (?,?,?,?,NOW())")
        ->execute([$job['post_id'],$tid,'posted','ok']);
    echo " -> posted\n";
  } else {
    $pdo->prepare("UPDATE smm_post_targets SET result_status='error', result_message=?, posted_at=NULL WHERE id=?")
        ->execute([substr($err,0,500), $tid]);
    $pdo->prepare("INSERT INTO smm_post_logs (post_id, target_id, status, message, created_at)
                   VALUES (?,?,?,?,NOW())")
        ->execute([$job['post_id'],$tid,'error',substr($err,0,500)]);
    echo " -> error: {$err}\n";
  }
}
