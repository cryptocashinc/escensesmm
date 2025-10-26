<?php require_once __DIR__ . '/inc/ui.php'; ui_header('Отчёты','reports'); ?>
<div class="grid cols-2">
  <div class="card">
    <h2>Публикации по статусам</h2>
    <?php $q=$pdo->query("SELECT result_status, COUNT(*) c FROM smm_post_targets GROUP BY result_status"); $stats=$q->fetchAll(); ?>
    <ul>
      <?php foreach($stats as $s): ?>
      <li><strong><?=h($s['result_status'])?></strong> — <?= (int)$s['c'] ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="card table-wrap">
    <h2>Распределение по часам</h2>
    <?php $q=$pdo->query("SELECT HOUR(schedule_at) h, COUNT(*) c FROM smm_post_targets GROUP BY h ORDER BY h"); $rows=$q->fetchAll(); ?>
    <table><thead><tr><th>Час</th><th>Кол-во</th></tr></thead><tbody>
    <?php foreach($rows as $r): ?><tr><td><?= (int)$r['h'] ?></td><td><?= (int)$r['c'] ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </div>
</div>
<?php ui_footer(); ?>
