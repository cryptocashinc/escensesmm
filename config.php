<?php
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем корневой конфиг: /public/escense/config.php
$rootConfig = dirname(__DIR__) . '/config.php';
if (!file_exists($rootConfig)) {
  http_response_code(500);
  die('Не найден escense/config.php: ' . $rootConfig);
}
require_once $rootConfig;

// Если $pdo не создан в корневом конфиге — создаём безопасный дефолт (при необходимости поправь креды)
if (!isset($pdo) || !$pdo) {
  try {
    $pdo = new PDO(
      'mysql:host=127.0.0.1;dbname=escense;charset=utf8mb4',
      'root',
      '',
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]
    );
  } catch (Throwable $e) {
    http_response_code(500);
    die('DB connection error: ' . $e->getMessage());
  }
}

// Ключ приложения (оставляем — пригодится для старых зашифрованных токенов/воркера)
if (!defined('APP_KEY')) {
  define('APP_KEY', '6dd2f7e6b3c94d0b92b9a7c3a04f0a5f50a1f1e3c6b2d98ae4e34a7f9bcd21ab');
}

// Явно выключаем шифрование токенов (plain text в БД)
if (!defined('SMM_ENCRYPT_TOKENS')) {
  define('SMM_ENCRYPT_TOKENS', false);
}

// Таймзона + базовый путь модуля
if (!ini_get('date.timezone')) date_default_timezone_set('Europe/Amsterdam');
$SMM_BASE = '/escense/smm';

// Ключ для HTTP-воркера (если используешь worker_http.php)
if (!defined('SMM_CRON_KEY')) {
  define('SMM_CRON_KEY', substr(hash('sha256', APP_KEY), 0, 24));
}