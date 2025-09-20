<?php
// Ajusta estas constantes a tu entorno local/producción
// Ejemplo local (XAMPP/WAMP): user root sin password, DB tuplanseguro
// Logging unificado (rotación por tamaño y limpieza por edad)
// Carga de variables de entorno desde archivo .env (si existe)
if (!function_exists('load_dotenv')) {
  function load_dotenv(string $path): void {
    if (!is_file($path)) return;
    try {
      $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      if (!$lines) return;
      foreach ($lines as $line) {
        if (!$line || $line[0]==='#') continue;
        $pos = strpos($line, '='); if ($pos===false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos+1));
        if ($val !== '' && ($val[0]==="'" || $val[0]=='"')) { $val = trim($val, "'\""); }
        if ($key !== '') {
          @putenv($key.'='.$val);
          $_ENV[$key] = $val;
          $_SERVER[$key] = $val;
        }
      }
    } catch (Throwable $e) { /* noop */ }
  }
}
load_dotenv(__DIR__ . '/../.env');
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

// DB config: tomar primero de variables de entorno, si no, usar defaults según APP_ENV
$envDbHost = getenv('DB_HOST');
$envDbName = getenv('DB_NAME');
$envDbUser = getenv('DB_USER');
$envDbPass = getenv('DB_PASS');
if (!defined('DB_HOST')) define('DB_HOST', $envDbHost ?: (APP_ENV==='prod' ? 'localhost' : '127.0.0.1'));
if (!defined('DB_NAME')) define('DB_NAME', $envDbName ?: (APP_ENV==='prod' ? 'dbdc5w1pvpolqn' : 'tuplanseguro'));
if (!defined('DB_USER')) define('DB_USER', $envDbUser ?: (APP_ENV==='prod' ? 'uk0siof1510tr' : 'root'));
if (!defined('DB_PASS')) define('DB_PASS', $envDbPass ?: (APP_ENV==='prod' ? 'panxo2025_*' : 'root123456'));

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

// Mail (SMTP) - tomar de entorno si existe, si no usar defaults actuales
if (!defined('MAIL_DRIVER')) define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'smtp'); // log | mail | smtp
if (!defined('MAIL_FROM')) define('MAIL_FROM', getenv('MAIL_FROM') ?: 'contacto@tuplanseguro.cl');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Tu Plan Seguro');
if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: 'mail.tuplanseguro.cl');
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465)); // SSL 465
if (!defined('SMTP_USER')) define('SMTP_USER', getenv('SMTP_USER') ?: 'contacto@tuplanseguro.cl');
if (!defined('SMTP_PASS')) define('SMTP_PASS', getenv('SMTP_PASS') ?: 'panxo2025_*');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'ssl'); // Puerto 465 requiere SSL


