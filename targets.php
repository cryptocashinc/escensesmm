<?php
require_once __DIR__ . '/inc/ui.php';
[$where,$params]=scope_posts_clause('p');
$sql="SELECT t.*, p.title, sp.key_name AS provider, a.display_name AS acc_name
     FROM smm_post_targets t
     JOIN smm_posts p ON p.id=t.post_id
     JOIN social_accounts a ON a.id=t.social_account_id
     JOIN social_providers sp ON sp.id=a.provider_id
     WHERE 1=1 {$where} ORDER BY t.created_at DESC LIMIT 300";
$stmt=$pdo->prepare($sql); $stmt->execute($params); $rows=$stmt->fetchAll();
ui_header('Очередь публикаций','queue');
?>
<div class="card table-wrap">
  <h2>Очередь</h2>
  <table>
    <thead><tr><th>ID</th><th>Пост</th><th>Канал</th><th>Когда</th><th>Статус</th><th>Результат</th><th></th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><a href="post_edit.php?id=<?= (int)$r['post_id'] ?>"><?= h($r['title'] ?: 'Без названия') ?></a></td>
        <td><?= h($r['provider']) ?> · <?=h($r['acc_name'])?></td>
        <td><?= h($r['schedule_at']) ?> (<?=h($r['timezone'])?>)</td>
        <td><?= h($r['result_status']) ?></td>
        <td><?= h($r['error_message'] ?: ($r['external_post_id'] ?: '—')) ?></td>
        <td><?php if($r['result_status']==='queued'): ?>
          <form action="api/publish_now.php" method="post" style="display:inline">
            <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
            <button class="btn" type="submit">Постить</button>
          </form><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php ui_footer(); ?>
