<?php require_once __DIR__ . '/../inc/functions.php';
$id=(int)($_POST['id'] ?? 0);
if($id>0){ $pdo->prepare("DELETE FROM social_accounts WHERE id=?")->execute([$id]); }
header('Location: ../accounts.php');