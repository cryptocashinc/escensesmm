<?php
require_once __DIR__ . '/inc/ui.php';
[$where,$params]=scope_posts_clause('p');
$stmt=$pdo->prepare("SELECT p.* FROM smm_posts p WHERE 1=1 {$where} ORDER BY p.created_at DESC LIMIT 300");
$stmt->execute($params); $posts=$stmt->fetchAll();
ui_header('Посты','posts');
?>
<div class="card table-wrap">
  <h2>Посты</h2>
  <table>
    <thead><tr><th>ID</th><th>Название</th><th>Статус</th><th>Создан</th><th></th></tr></thead>
    <tbody>
    <?php foreach($posts as $p): ?>
      <tr>
        <td><?= (int)$p['id'] ?></td>
        <td><a href="post_edit.php?id=<?= (int)$p['id'] ?>"><?= h($p['title'] ?: 'Без названия') ?></a></td>
        <td><?= h($p['status']) ?></td>
        <td><?= h($p['created_at']) ?></td>
        <td>
          <form action="api/post_delete.php" method="post" onsubmit="return confirm('Удалить пост?')">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn" type="submit">Удалить</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php ui_footer(); ?>
