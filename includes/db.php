<?php
declare(strict_types=1);

// app_log y log_error se definen en config.php

// Carga configuración externa si existe
$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
  /** @noinspection PhpIncludeInspection */
  require $configPath;
}

// Defaults si no están definidos en config.php
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_NAME')) define('DB_NAME', 'tuplanseguro');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Escribe mensajes de error en log unificado sin exponer detalles al usuario
 */
function log_error(string $message): void {
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown-ip';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown-ua';
  // Registrar solo una línea por error, sin trazas largas
  app_log('[ERROR] [' . $ip . '] [' . $ua . '] ' . $message);
}

/**
 * Retorna una conexión PDO configurada con excepciones y modo seguro
 */
function getPDO(): PDO {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];
  try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Homologar sesión SQL entre DEV/QAS/PROD
    try {
      $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
      $pdo->exec("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");
      $pdo->exec("SET SESSION innodb_strict_mode = ON");
      $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY'");
    } catch (Throwable $e) {
      // No interrumpir si alguna directiva no está disponible
      app_log('PDO session init warn: ' . $e->getMessage());
    }
    return $pdo;
  } catch (Throwable $e) {
    log_error('DB connection failed: ' . $e->getMessage());
    throw new RuntimeException('Error de conexión. Inténtalo más tarde.');
  }
}


