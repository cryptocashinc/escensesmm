<?php
require_once __DIR__ . '/inc/ui.php';
$stmt=$pdo->query("SELECT * FROM smm_post_logs ORDER BY created_at DESC LIMIT 400");
$rows=$stmt->fetchAll();
ui_header('Логи','logs');
?>
<div class="card table-wrap">
  <h2>Логи публикаций</h2>
  <table>
    <thead><tr><th>Когда</th><th>Уровень</th><th>Сообщение</th><th>Payload</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?= h($r['created_at']) ?></td>
          <td><?= h($r['level']) ?></td>
          <td><?= nl2br(h($r['message'])) ?></td>
          <td><pre style="white-space:pre-wrap;margin:0"><?= h($r['payload']) ?></pre></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php ui_footer(); ?>
