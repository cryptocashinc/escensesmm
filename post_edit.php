<?php
require_once __DIR__ . '/inc/ui.php';

$id = (int)($_GET['id'] ?? 0);
$post = null;
if ($id>0){
  $stmt=$pdo->prepare("SELECT * FROM smm_posts WHERE id=?");
  $stmt->execute([$id]);
  $post=$stmt->fetch();
}

$accStmt = $pdo->query("
  SELECT a.id, a.display_name, sp.key_name AS provider, a.external_id
  FROM social_accounts a
  JOIN social_providers sp ON sp.id=a.provider_id
  WHERE a.status='active'
  ORDER BY sp.key_name, a.display_name
");
$accounts = $accStmt->fetchAll();

ui_header($id? 'Редактирование поста':'Новый пост','new');
?>
<style>
.fab-save{
  position: fixed; right: 16px; bottom: 16px;
  z-index: 50; border: none; padding: 12px 18px; border-radius: 999px;
  font-weight: 700; background:#000; color:#fff; cursor:pointer;
  box-shadow: 0 8px 24px rgba(0,0,0,.15);
}
.fab-save:active{ transform: translateY(1px); }
.actionbar{ display:flex; gap:10px; margin-top:12px; flex-wrap:wrap; }
.btn{ background:#000;color:#fff;border:none;border-radius:999px;padding:10px 18px;cursor:pointer; }
.btn:hover{ background:#333; }
.btn.secondary{ background:#f5f5f5; color:#111; }
.muted{ color:#6b7280; font-size:13px; }
.kv{ display:grid; gap:12px; grid-template-columns:1fr 1fr 1fr auto; }
@media (max-width: 900px){
  .fab-save{ display:block; }
  .kv{ grid-template-columns:1fr; }
}
@media (min-width: 901px){
  .fab-save{ display:none; }
}
.toast{
  position: fixed; left: 50%; transform: translateX(-50%);
  bottom: 80px; background:#111;color:#fff; padding:10px 14px; border-radius:999px;
  box-shadow: 0 8px 24px rgba(0,0,0,.15); z-index: 60; font-size:14px; display:none;
}
.toast.show{ display:block; }
</style>

<form id="postForm" class="grid cols-2" action="api/post_save.php" method="post" enctype="multipart/form-data">
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
<?php
  // Показать уже прикреплённые файлы
  $mediaExisting = [];
  if (!empty($post['media_json'])) {
    $mediaExisting = json_decode($post['media_json'], true) ?: [];
  }
  if ($mediaExisting){
    echo '<div class="muted" style="margin-top:8px">Уже прикреплено:</div>';
    echo '<div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:6px">';
    foreach ($mediaExisting as $m) {
      // поддерживаем оба формата: {"url":"/..."} ИЛИ {"path":"/..."}
      $src  = $m['url'] ?? $m['path'] ?? '';
      $type = $m['type'] ?? '';
      if (!$src) continue;

      $esc = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
      if ($type === 'image' || preg_match('~\.(jpe?g|png|gif|webp)$~i', $esc)) {
        echo '<a href="'.$esc.'" target="_blank" style="display:inline-block;border:1px solid #eee;border-radius:8px;overflow:hidden">';
        echo '<img src="'.$esc.'" alt="" style="width:120px;height:120px;object-fit:cover;display:block">';
        echo '</a>';
      } else {
        echo '<a href="'.$esc.'" target="_blank" style="display:inline-block;font-size:12px;padding:8px 10px;border:1px solid #eee;border-radius:8px;text-decoration:none">медиа: '.basename($esc).'</a>';
      }
    }
    echo '</div>';
  }
?>

    <div class="actionbar">
      <button class="btn" type="submit">Сохранить</button>
      <?php if ($id>0): ?>
      <button class="btn secondary" type="button" onclick="publishNowOpen()">Опубликовать сейчас</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>Таргеты публикации</h2>
    <p class="muted">Выберите аккаунты и время публикации. Можно добавить несколько строк.</p>
    <div id="targetsWrap"></div>
    <button class="btn" type="button" onclick="addTarget()">+ Добавить таргет</button>

    <div class="actionbar" style="margin-top:16px">
      <button class="btn" type="submit">Сохранить</button>
      <?php if ($id>0): ?>
      <button class="btn secondary" type="button" onclick="publishNowOpen()">Опубликовать сейчас</button>
      <?php endif; ?>
    </div>
  </div>
</form>

<button class="fab-save" onclick="document.getElementById('postForm').requestSubmit()">Сохранить</button>
<div class="toast" id="toast"></div>

<script>
  const ACCOUNTS = <?= json_encode($accounts, JSON_UNESCAPED_UNICODE) ?>;
  let idx = 0;

  function addTarget(pref){
    const wrap = document.getElementById('targetsWrap');
    const row = document.createElement('div');
    row.className = 'target-row';
    row.innerHTML = `
      <div class="kv">
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
      </div>
    `;
    wrap.appendChild(row); idx++;
  }

  // при первом открытии — одна строка таргета
  addTarget();

  // Ctrl+S = сохранить
  document.addEventListener('keydown', (e)=>{
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase()==='s'){
      e.preventDefault();
      document.getElementById('postForm').requestSubmit();
    }
  });

  // ------- Публикация сейчас (диалог + вызов API) -------
  function publishNowOpen(){
    const postId = <?= (int)($post['id'] ?? 0) ?>;
    if (!postId){ toast('Сначала сохраните пост'); return; }

    const accs = ACCOUNTS.map(a=>`<option value="${a.id}">${a.provider} · ${a.display_name ?? ''} (${a.external_id})</option>`).join('');
    const html = `
      <div id="pn-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:70;display:flex;align-items:center;justify-content:center;padding:16px">
        <div style="background:#fff;border-radius:14px;max-width:520px;width:100%;padding:20px;box-shadow:0 16px 40px rgba(0,0,0,.2)">
          <h3 style="margin:0 0 12px">Опубликовать сейчас</h3>
          <div class="muted" style="margin-bottom:12px">Выберите аккаунт, пост уйдёт сразу.</div>
          <select id="pn-account" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:12px">
            ${accs}
          </select>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button class="btn secondary" type="button" onclick="publishNowClose()">Отмена</button>
            <button class="btn" type="button" onclick="publishNowDo(${postId})">Отправить</button>
          </div>
        </div>
      </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
  }

  function publishNowClose(){
    const el=document.getElementById('pn-overlay');
    if (el) el.remove();
  }

  async function publishNowDo(postId){
    const sel = document.getElementById('pn-account');
    if (!sel) return;
    const accId = sel.value;
    try{
      // 1) ставим задание "на сейчас"
      const res = await fetch('api/targets_enqueue_now.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({post_id: postId, account_id: accId})
      });
      const j = await res.json();
      if (!j.ok){ toast('Ошибка: '+(j.error||'unknown')); return; }

      publishNowClose();
      toast('В очередь отправлено. Запускаю воркер…');

      // 2) тихо пинаем воркер (без ключа, через сессию)
      const r2 = await fetch('api/worker_kick.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: 'limit=5'
      });
      const j2 = await r2.json();
      if (j2.ok) { console.log(j2.log); toast('Готово'); }
      else { toast('Воркер: '+(j2.error||'ошибка')); }
    }catch(e){
      console.error(e);
      toast('Ошибка сети');
    }
  }

  function toast(msg){
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'), 3000);
  }
</script>

<?php ui_footer(); ?>
