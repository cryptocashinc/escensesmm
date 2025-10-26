<?php
require_once __DIR__ . '/inc/ui.php';
global $SMM_BASE;
$id = (int)($_GET['id'] ?? 0);
$post=null;
if ($id>0){ $stmt=$pdo->prepare("SELECT * FROM smm_posts WHERE id=?"); $stmt->execute([$id]); $post=$stmt->fetch(); }
$accStmt = $pdo->query("SELECT a.id, a.display_name, sp.key_name AS provider, a.external_id FROM social_accounts a JOIN social_providers sp ON sp.id=a.provider_id WHERE a.status='active' ORDER BY sp.key_name, a.display_name");
$accounts = $accStmt->fetchAll();
ui_header($id? 'Редактирование поста':'Новый пост','new');
?>
<form class="grid cols-2" action="api/post_save.php" method="post" enctype="multipart/form-data">
  <div class="card">
    <h2><?= $id? 'Редактор поста #'.(int)$id : 'Новый пост' ?></h2>
    <input type="hidden" name="id" value="<?= (int)($post['id'] ?? 0) ?>">

    <label class="help">Название
      <span class="q" tabindex="0" aria-expanded="false">?</span>
      <div class="popover">Короткий заголовок для списка. Можно оставить пустым.</div>
    </label>
    <input name="title" maxlength="191" placeholder="Например: осенний дроп — скидка 20%" value="<?=h($post['title'] ?? '')?>">

    <label class="help">Текст поста
      <span class="q" tabindex="0" aria-expanded="false">?</span>
      <div class="popover">Основной текст публикации. Абзацы и эмодзи — как в сети.</div>
    </label>
    <textarea name="body" rows="8" placeholder="Текст для публикации"><?=h($post['body'] ?? '')?></textarea>

    <label class="help">Первый комментарий (опц.)
      <span class="q" tabindex="0" aria-expanded="false">?</span>
      <div class="popover">Будет размещён комментарием сразу после поста (если поддерживается).</div>
    </label>
    <textarea name="first_comment" rows="4" placeholder="Хэштеги, UTM, дисклеймер"><?=h($post['first_comment'] ?? '')?></textarea>

    <label class="help">Ссылка (опц.)
      <span class="q" tabindex="0" aria-expanded="false">?</span>
      <div class="popover">Внешняя ссылка — если нужна. Можно оставить пустой.</div>
    </label>
    <input name="link_url" placeholder="https://example.com/landing" value="<?=h($post['link_url'] ?? '')?>">

    <label class="help">Медиа (изображения/видео)
      <span class="q" tabindex="0" aria-expanded="false">?</span>
      <div class="popover">Загрузите 1–10 файлов: JPG, PNG, WEBP, GIF, MP4.</div>
    </label>
    <input type="file" name="media[]" multiple accept="image/*,video/*">

    <div class="actionbar">
      <button class="btn" type="submit">Сохранить</button>
    </div>
  </div>

  <div class="card">
    <h2>Таргеты публикации</h2>
    <p class="muted">Выберите аккаунты и время публикации. Можно добавить несколько строк.</p>
    <div id="targetsWrap"></div>
    <button class="btn" type="button" onclick="addTarget()">+ Добавить таргет</button>

    <div class="actionbar" style="margin-top:16px">
      <button class="btn" type="submit">Сохранить</button>
    </div>
  </div>
</form>
<script>
  const ACCOUNTS = <?= json_encode($accounts, JSON_UNESCAPED_UNICODE) ?>;
  let idx = 0;
  function addTarget(){
    const wrap = document.getElementById('targetsWrap');
    const row = document.createElement('div');
    row.className = 'target-row';
    row.style.display='grid';
    row.style.gridTemplateColumns='1fr 1fr 1fr auto';
    row.style.gap='12px'; row.style.margin='10px 0';
    row.innerHTML = `
      <div>
        <label class="help">Аккаунт
          <span class="q" tabindex="0" aria-expanded="false">?</span>
          <div class="popover">Куда публиковать — канал/страница. Добавляются на странице «Аккаунты».</div>
        </label>
        <select name="targets[${idx}][account_id]" required>
          ${ACCOUNTS.map(a=>`<option value="${a.id}">${a.provider} · ${a.display_name ?? ''} (${a.external_id})</option>`).join('')}
        </select>
      </div>
      <div>
        <label class="help">Когда
          <span class="q" tabindex="0" aria-expanded="false">?</span>
          <div class="popover">Дата и время публикации по часовому поясу ниже.</div>
        </label>
        <input type="datetime-local" name="targets[${idx}][datetime]" required>
      </div>
      <div>
        <label class="help">Часовой пояс
          <span class="q" tabindex="0" aria-expanded="false">?</span>
          <div class="popover">Например: <code>Europe/Amsterdam</code>, <code>UTC</code>.</div>
        </label>
        <input type="text" name="targets[${idx}][tz]" value="Europe/Amsterdam">
      </div>
      <div style="display:flex;align-items:end">
        <button class="btn" type="button" onclick="this.closest('.target-row').remove()">Удалить</button>
      </div>
    `;
    wrap.appendChild(row); idx++;
  }
</script>
<?php ui_footer(); ?>
