<?php
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/providers/BaseProvider.php';
use Escense\SMM\Providers\BaseProvider;
$limit = 10;
$stmt = $pdo->prepare("
  SELECT t.id FROM smm_post_targets t
  WHERE t.result_status='queued' AND t.schedule_at <= NOW()
  ORDER BY t.schedule_at ASC
  LIMIT {$limit}
  FOR UPDATE
");
$pdo->beginTransaction(); $stmt->execute(); $ids = $stmt->fetchAll(PDO::FETCH_COLUMN); $pdo->commit();
foreach ($ids as $tid) {
  $pdo->beginTransaction();
  $q = $pdo->prepare("SELECT t.*, p.*, a.*, sp.key_name AS provider_key, t.id AS t_id
      FROM smm_post_targets t
      JOIN smm_posts p ON p.id=t.post_id
      JOIN social_accounts a ON a.id=t.social_account_id
      JOIN social_providers sp ON sp.id=a.provider_id
      WHERE t.id=? FOR UPDATE");
  $q->execute([$tid]); $row = $q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { $pdo->rollBack(); continue; }
  try {
      [$ok, $extId, $err] = BaseProvider::publishRow($row);
      if ($ok) {
          $pdo->prepare("UPDATE smm_post_targets SET result_status='ok', external_post_id=?, published_at=NOW() WHERE id=?")->execute([$extId, $tid]);
          $pdo->prepare("INSERT INTO smm_post_logs (post_target_id, level, message, payload) VALUES (?,?,?,?)")->execute([$tid,'info','Published OK', json_encode(['external_id'=>$extId])]);
      } else {
          $pdo->prepare("UPDATE smm_post_targets SET result_status='failed', error_message=? WHERE id=?")->execute([substr($err,0,2000), $tid]);
          $pdo->prepare("INSERT INTO smm_post_logs (post_target_id, level, message, payload) VALUES (?,?,?,?)")->execute([$tid,'error','Publish failed', json_encode(['error'=>$err])]);
      }
      $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); }
}
