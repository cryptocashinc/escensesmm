<?php require_once __DIR__ . '/../inc/functions.php';
$id=(int)($_POST['id'] ?? 0);
if($id>0){
  $pdo->prepare("DELETE FROM smm_post_targets WHERE post_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM smm_posts WHERE id=?")->execute([$id]);
}
header('Location: ../posts.php');