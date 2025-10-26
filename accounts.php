<?php
require_once __DIR__ . '/inc/ui.php';

// тянем аккаунты + провайдера, а также last_error/last_checked_at если поля есть
$stmt=$pdo->query("
  SELECT a.*, sp.key_name AS provider
  FROM social_accounts a
  JOIN social_providers sp ON sp.id=a.provider_id
  ORDER BY a.created_at DESC
");
$rows=$stmt->fetchAll();

ui_header('Аккаунты','accounts');
?>
<style>
/* локально добросим недостающие стили бейджей (если в app.css их нет) */
.badge.warn{ background:#fcf8e3; color:#8a6d3b; }
.badge.unknown{ background:#eee; color:#555; }
.badge.small{ font-size:11px; padding:2px 8px }
td .muted{ font-size:12px; }
</style>

<div class="grid cols-2">
  <div class="card table-wrap">
    <h2>Подключённые аккаунты</h2>

    <?php if (isset($_GET['check'])): ?>
      <div class="card" style="background:#dff0d8;color:#3c763d;margin-top:10px">
        Проверка: <?= h($_GET['check']) ?> (status: <?= h($_GET['status'] ?? '') ?>)
      </div>
    <?php endif; ?>

    <table>
      <thead>
        <tr>
          <th>Провайдер</th>
          <th>Имя</th>
          <th>Ext&nbsp;ID</th>
          <th>Статус</th>
          <th>Диагностика</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($rows as $r):
        $st = strtolower((string)($r['status'] ?? 'unknown'));
        $cls = 'unknown';
        if (in_array($st, ['active','ok'])) $cls = 'ok';
        elseif (in_array($st, ['error','failed'])) $cls = 'err';
        elseif (in_array($st, ['inactive'])) $cls = 'warn';
        $reason = trim($r['last_error'] ?? '');
        $checked = $r['last_checked_at'] ?? null;
      ?>
        <tr>
          <td><?=h($r['provider'])?></td>
          <td><?=h($r['display_name'] ?: $r['username'])?></td>
          <td><?=h($r['external_id'])?></td>
          <td><span class="badge <?= $cls ?>"><?= h($st ?: 'unknown') ?></span></td>
          <td>
            <?php if ($reason): ?>
              <div><?= nl2br(h($reason)) ?></div>
              <?php if ($checked): ?>
                <div class="muted">проверено: <?= h($checked) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <span class="muted">нет данных — нажмите «Проверить»</span>
            <?php endif; ?>
          </td>
          <td>
            <?php /* Кнопка «Проверить» — для всех */ ?>
            <form action="api/accounts_check.php" method="post" style="display:inline">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn" type="submit">Проверить</button>
            </form>

            <?php /* Тест-сообщение — для TG/VK */ ?>
            <?php if (in_array($r['provider'], ['telegram','vk'])): ?>
              <form action="api/accounts_test_send.php" method="post" style="display:inline">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn" type="submit">Тест-сообщение</button>
              </form>
            <?php endif; ?>

            <form action="api/accounts_delete.php" method="post" style="display:inline"
                  onsubmit="return confirm('Удалить аккаунт?')">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn" type="submit">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>Добавить аккаунт</h2>
    <div class="muted" id="providerHelpBox" style="margin:-4px 0 10px; font-size:13px">
      Выберите провайдера — появятся инструкции.
    </div>

    <form action="api/accounts_add.php" method="post">
      <label class="help">Провайдер
        <span class="q" tabindex="0" aria-expanded="false">?</span>
        <div class="popover">Выберите площадку, куда будем публиковать.</div>
      </label>
      <select id="providerSelect" name="provider" required onchange="providerHelpChange('providerSelect','providerHelpBox')">
        <option value="" disabled selected>— выбрать —</option>
        <option value="telegram">Telegram</option>
        <option value="vk">VK</option>
        <option value="facebook">Facebook Page</option>
        <option value="instagram">Instagram</option>
        <option value="threads">Threads</option>
        <option value="linkedin">LinkedIn</option>
        <option value="tiktok">TikTok</option>
        <option value="youtube">YouTube</option>
      </select>

      <label class="help">External ID
        <span class="q" tabindex="0" aria-expanded="false">?</span>
        <div class="popover">
          Идентификатор страницы/канала.<br>
          <strong>Telegram</strong>: <code>@alias</code> (публичный) или числовой <code>-100…</code> (приватный).<br>
          <strong>VK</strong>: <code>owner_id</code> — для группы с минусом (<code>-123456</code>).<br>
          <strong>Instagram</strong>: <code>ig_user_id</code>. <strong>Facebook</strong>: <code>page_id</code>.<br>
          <strong>LinkedIn</strong>: <code>urn:li:organization:123</code> или <code>urn:li:person:...</code>.<br>
          <strong>YouTube</strong>: <code>channelId</code>.
        </div>
      </label>
      <input name="external_id" placeholder="@channelname · -123456 · 17841400008460056" required>

      <label class="help">Display name
        <span class="q" tabindex="0" aria-expanded="false">?</span>
        <div class="popover">Как будет называться канал в панели (для команды).</div>
      </label>
      <input name="display_name" placeholder="Например: TG — ESCENSE">

      <label class="help">Access token
        <span class="q" tabindex="0" aria-expanded="false">?</span>
        <div class="popover">
          <strong>Telegram</strong>: бот-токен от @BotFather.<br>
          <strong>VK</strong>: токен сообщества с правами на запись в стену.<br>
          <strong>Facebook/Instagram</strong>: Page/IG токен (Graph API).<br>
          <strong>LinkedIn/YouTube/TikTok</strong>: OAuth токен (если настроено).
        </div>
      </label>
      <input name="access_token" placeholder="Пример: 123456:ABCDEF..." required>

      <label class="help">Refresh token (опц.)
        <span class="q" tabindex="0" aria-expanded="false">?</span>
        <div class="popover">Нужно только для OAuth-интеграций (не для VK/Telegram).</div>
      </label>
      <input name="refresh_token" placeholder="Если нужен для OAuth">

      <label class="help">Scopes/extra (опц.)
        <span class="q" tabindex="0" aria-expanded="false">?</span>
        <div class="popover">Доп. права/параметры — при необходимости.</div>
      </label>
      <input name="scopes" placeholder="pages_manage_posts, user_posts">

      <button class="btn" type="submit">Добавить</button>
    </form>
  </div>
</div>

<script>
// подробные инструкции по каждому провайдеру
function providerHelpChange(selId, helpId){
  const sel = document.getElementById(selId);
  const box = document.getElementById(helpId);
  if (!sel || !box) return;
  const v = sel.value;
  const HTML = {
    telegram: `
      <strong>Telegram канал/чат</strong><br>
      1) Создайте бота у @BotFather → получите <em>BOT_TOKEN</em>.<br>
      2) Добавьте бота в канал админом (право «Публиковать сообщения»).<br>
      3) <em>External ID</em>: публичный канал — <code>@alias</code>; приватный — числовой <code>-100…</code>.<br>
      4) <em>Access token</em> = BOT_TOKEN.<br>
      <span class="badge small">подсказка</span> После добавления нажмите «Проверить»: увидите ping-сообщение, статус станет <b>active</b>.
    `,
    vk: `
      <strong>VK сообщество/группа</strong><br>
      1) Получите токен сообщества с правом «управление сообществом/стеной».<br>
      2) <em>External ID</em> = <code>owner_id</code> (для группы — с минусом: <code>-123456</code>).<br>
      3) <em>Access token</em> = токен сообщества.<br>
      4) «Проверить» сделает пробную запись <code>ping</code> (или вернёт причину ошибки).
    `,
    facebook: `
      <strong>Facebook Page</strong><br>
      1) Получите <em>Page Access Token</em> через Graph API (приложение).<br>
      2) <em>External ID</em> = <code>page_id</code>.<br>
      3) Публикация требует прав: <code>pages_manage_posts</code>, <code>pages_read_engagement</code>. 
    `,
    instagram: `
      <strong>Instagram (Graph API)</strong><br>
      1) Свяжите IG с Facebook Page (проф. аккаунт).<br>
      2) <em>External ID</em> = <code>ig_user_id</code> (получается из связанной страницы).<br>
      3) Токен страницы с правами <code>instagram_basic</code>, <code>pages_manage_posts</code>.
    `,
    threads: `
      <strong>Threads</strong><br>
      Официального API для постинга нет — отображается как заглушка.
    `,
    linkedin: `
      <strong>LinkedIn</strong><br>
      1) OAuth токен приложения с <em>w_member_social</em>.<br>
      2) <em>External ID</em>: <code>urn:li:organization:123</code> (страница) или <code>urn:li:person:...</code> (личный).
    `,
    tiktok: `
      <strong>TikTok</strong><br>
      1) Нужен OAuth клиента и разрешение на публикацию (если доступно в регионе/аккаунте).<br>
      2) Публикация в текущей версии может быть недоступна — используйте «как заглушку».
    `,
    youtube: `
      <strong>YouTube</strong><br>
      1) OAuth: client_id/client_secret + токен пользователя/канала.<br>
      2) <em>External ID</em> = <code>channelId</code>.<br>
      3) Для постинга видео придётся грузить файл — поддержка в модуле по запросу.
    `
  };
  box.innerHTML = HTML[v] || 'Выберите провайдера — появятся инструкции.';
}
</script>

<?php ui_footer(); ?>