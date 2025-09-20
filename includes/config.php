<?php
// Ajusta estas constantes a tu entorno local/producción
// Ejemplo local (XAMPP/WAMP): user root sin password, DB tuplanseguro
// Logging unificado (rotación por tamaño y limpieza por edad)
if (!function_exists('app_log')) {
  function app_log(string $message): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $file = $logDir . '/system.log';
    // Rotación por tamaño: 5 MB y limpieza de históricos > 30 días
    $maxBytes = 5 * 1024 * 1024;
    try {
      if (is_file($file) && @filesize($file) > $maxBytes) {
        $ts = date('Ymd_His');
        $rotated = $logDir . '/system-' . $ts . '.log';
        @rename($file, $rotated);
      }
      $rotatedList = glob($logDir . '/system-*.log') ?: [];
      $limitTs = time() - (30 * 24 * 3600);
      foreach ($rotatedList as $old) {
        $mt = @filemtime($old);
        if ($mt !== false && $mt < $limitTs) { @unlink($old); }
      }
    } catch (Throwable $e) { /* noop */ }
    $date = date('Y-m-d H:i:s');
    $line = '[' . $date . '] ' . str_replace(["\r","\n"], ' ', $message) . "\n";
    @file_put_contents($file, $line, FILE_APPEND);
    @ini_set('log_errors', '1');
    @ini_set('error_log', $file);
  }
}
if (!function_exists('log_error')) {
  function log_error(string $message): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown-ip';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown-ua';
    app_log('[ERROR] [' . $ip . '] [' . $ua . '] ' . $message);
  }
}

// Zona horaria por defecto (evita desfases en comparaciones de fechas/horas)
try {
  if (function_exists('date_default_timezone_set')) {
    // Si existe variable de entorno TIMEZONE úsala; si no, America/Santiago por defecto
    $tz = getenv('TIMEZONE') ?: 'America/Santiago';
    @date_default_timezone_set($tz);
  }
} catch (Throwable $e) { /* noop */ }
// DB (según entorno)
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
// APP_ENV define el entorno (dev|prod). Usar exclusivamente esta variable.
if (!defined('APP_ENV')) {
  $envFromServer = getenv('APP_ENV');
  define('APP_ENV', $envFromServer ? strtolower($envFromServer) : 'dev');
}

if (APP_ENV === 'prod') {
  if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
  if (!defined('DB_NAME')) define('DB_NAME', 'dbdc5w1pvpolqn');
  if (!defined('DB_USER')) define('DB_USER', 'uk0siof1510tr');
  if (!defined('DB_PASS')) define('DB_PASS', 'panxo2025_*');
} else {
  if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
  if (!defined('DB_NAME')) define('DB_NAME', 'tuplanseguro');
  if (!defined('DB_USER')) define('DB_USER', 'root');
  if (!defined('DB_PASS')) define('DB_PASS', 'root123456'); // password local
}

// App/env
// APP_ENV ya fijado arriba
// Política unificada de errores y logging para todos los entornos
error_reporting(E_ALL);
@ini_set('display_errors', '0');
// Unificar logs en logs/system.log
try {
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
  @ini_set('log_errors', '1');
  @ini_set('error_log', $logDir . '/system.log');
} catch (Throwable $e) { /* noop */ }
// BASE_URL: intenta deducir si no está definido
if (!defined('BASE_URL')) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  // Ajusta a la carpeta del proyecto
  $basePath = '/miplanseguro';
  define('BASE_URL', $scheme . '://' . $host . $basePath);
}

// Mail (SMTP producción)
if (!defined('MAIL_DRIVER')) define('MAIL_DRIVER', 'smtp'); // log | mail | smtp
if (!defined('MAIL_FROM')) define('MAIL_FROM', 'contacto@tuplanseguro.cl');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Tu Plan Seguro');
// SMTP credenciales reales
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'mail.tuplanseguro.cl');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 465); // SSL 465
if (!defined('SMTP_USER')) define('SMTP_USER', 'contacto@tuplanseguro.cl');
if (!defined('SMTP_PASS')) define('SMTP_PASS', 'panxo2025_*');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', 'ssl'); // Puerto 465 requiere SSL


