<?php
require_once __DIR__ . '/../inc/functions.php';
$provider=trim($_POST['provider'] ?? '');
$ext_id=trim($_POST['external_id'] ?? '');
$display=trim($_POST['display_name'] ?? '');
$access=trim($_POST['access_token'] ?? '');
$refresh=trim($_POST['refresh_token'] ?? '');
$scopes=trim($_POST['scopes'] ?? '');
if(!$provider || !$ext_id || !$access){ header('Location: ../accounts.php'); exit; }
$map=['instagram'=>1,'facebook'=>2,'threads'=>3,'linkedin'=>4,'tiktok'=>5,'telegram'=>6,'vk'=>7,'youtube'=>8];
$pid=$map[$provider] ?? null; if(!$pid){ header('Location: ../accounts.php'); exit; }
$enc=enc_token($access); $renc=$refresh?enc_token($refresh):null; $user=user_id();
$stmt=$pdo->prepare("INSERT INTO social_accounts (client_id,user_id,provider_id,external_id,display_name,access_token,refresh_token,scopes) VALUES (NULL,?,?,?,?,?,?,?)");
$stmt->execute([$user,$pid,$ext_id,$display,$enc,$renc,$scopes]);
header('Location: ../accounts.php');