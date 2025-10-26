<?php
if (session_status()===PHP_SESSION_NONE) session_start();
error_reporting(E_ALL); ini_set('display_errors',1);
require_once __DIR__ . '/../inc/functions.php';

$id            = (int)($_POST['id'] ?? 0);
$title         = trim((string)($_POST['title'] ?? ''));
$body          = trim((string)($_POST['body'] ?? ''));
$first_comment = trim((string)($_POST['first_comment'] ?? ''));
$link_url      = trim((string)($_POST['link_url'] ?? ''));
$author        = (int)($_SESSION['user_id'] ?? 0);

/** uploads: /escense/smm/uploads */
$uploadDir  = dirname(__DIR__) . '/uploads';
$publicBase = '/escense/smm/uploads';

if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);
/* .htaccess на всякий пожарный */
$ht = $uploadDir.'/.htaccess';
if (!file_exists($ht)) @file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>\n");

$allowed = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
  'image/gif'  => 'gif',
  'video/mp4'  => 'mp4',
];

$media = [];
if (!empty($_FILES['media']) && is_array($_FILES['media']['name'])) {
  $cnt = count($_FILES['media']['name']);
  for ($i=0; $i<$cnt; $i++) {
    $err = (int)($_FILES['media']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) continue;
    if ($err !== UPLOAD_ERR_OK) { $media[]=['error'=>"upload_err=$err"]; continue; }

    $tmp  = $_FILES['media']['tmp_name'][$i] ?? '';
    $name = basename($_FILES['media']['name'][$i] ?? 'file');
    if (!$tmp || !is_uploaded_file($tmp)) { $media[]=['error'=>'not_uploaded']; continue; }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp); finfo_close($finfo);
    if (!isset($allowed[$mime])) { $media[]=['error'=>"bad_mime:$mime"]; continue; }

    $ext  = $allowed[$mime];
    $base = preg_replace('~[^a-zA-Z0-9_\\-]+~','-', pathinfo($name, PATHINFO_FILENAME));
    $fn   = $base.'-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.'.$ext;
    $dst  = $uploadDir.'/'.$fn;

    if (move_uploaded_file($tmp, $dst)) {
      $item = [
        'url'  => $publicBase.'/'.$fn,                 // <— КЛЮЧ url
        'type' => (strpos($mime,'image/')===0?'image':'video')
      ];
      if ($item['type']==='image') { $sz=@getimagesize($dst); if ($sz){ $item['w']=$sz[0]; $item['h']=$sz[1]; } }
      $media[] = $item;
    } else {
      $media[] = ['error'=>'move_failed'];
    }
  }
}

/* сохранить пост */
if ($id>0) {
  $sql = "UPDATE smm_posts SET title=?, body=?, first_comment=?, link_url=?, author_user_id=?, updated_at=NOW()";
  $p   = [$title,$body,$first_comment,$link_url,$author];
  if (!empty(array_filter($media, fn($m)=>empty($m['error'])))) { $sql.=", media_json=?"; $p[] = json_encode($media, JSON_UNESCAPED_UNICODE); }
  $sql.=" WHERE id=?";
  $p[]=$id;
  $stmt=$pdo->prepare($sql); $stmt->execute($p);
  $postId = $id;
} else {
  $stmt=$pdo->prepare("INSERT INTO smm_posts
    (project_id, client_id, author_user_id, title, body, first_comment, media_json, link_url, status, created_at, updated_at)
    VALUES (NULL,NULL,?,?,?,?,?,?, 'draft', NOW(), NOW())");
  $mjson = !empty(array_filter($media, fn($m)=>empty($m['error']))) ? json_encode($media, JSON_UNESCAPED_UNICODE) : null;
  $stmt->execute([$author,$title,$body,$first_comment,$mjson,$link_url]);
  $postId = (int)$pdo->lastInsertId();
}

/* таргеты */
if (!empty($_POST['targets']) && is_array($_POST['targets'])) {
  $ins = $pdo->prepare("INSERT INTO smm_post_targets
    (post_id, client_id, social_account_id, schedule_at, timezone, result_status, created_at)
    VALUES (?,?,?,?,?,'queued',NOW())");
  foreach ($_POST['targets'] as $t) {
    $accId = (int)($t['account_id'] ?? 0);
    $dt    = trim((string)($t['datetime'] ?? ''));
    $tz    = trim((string)($t['tz'] ?? 'Europe/Amsterdam'));
    if ($accId>0 && $dt!=='') {
      $dt = str_replace('T',' ',$dt).':00';
      $ins->execute([$postId,NULL,$accId,$dt,$tz]);
    }
  }
}

$okFiles = count(array_filter($media, fn($m)=>empty($m['error'])));
$fail    = count(array_filter($media, fn($m)=>!empty($m['error'])));
$_SESSION['notice'] = 'Сохранено: пост #'.$postId.' · медиа ок: '.$okFiles.($fail?(' · ошибки: '.$fail):'');

header('Location: ../posts.php');
exit;
