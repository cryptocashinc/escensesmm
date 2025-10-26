<?php require_once __DIR__ . '/inc/ui.php'; ui_header('SMM — старт','calendar'); ?>
<div class="grid cols-2">
  <div class="card">
    <h2>Быстрый старт</h2>
    <ol class="muted">
      <li>Откройте «Аккаунты» и подключите каналы.</li>
      <li>Создайте «Новый пост», добавьте таргеты публикации.</li>
      <li>Проверьте «Очередь»/«Календарь»; запустите крон-воркер.</li>
    </ol>
  </div>
  <div class="card">
    <h2>Состояние</h2>
    <p>Роль: <span class="badge"><?=h($_SESSION['role'] ?? '')?></span></p>
    <p>Пользователь: <strong><?=h($_SESSION['user_name'] ?? '—')?></strong></p>
  </div>
</div>
<?php ui_footer(); ?>
