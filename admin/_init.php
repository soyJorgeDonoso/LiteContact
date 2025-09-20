<?php
declare(strict_types=1);

// Inicio común para secciones admin
require __DIR__ . '/../includes/db.php';

// Configuración de sesión (solo si no hay una activa)
if (session_status() !== PHP_SESSION_ACTIVE) {
  @ini_set('session.use_strict_mode', '1');
  @ini_set('session.cookie_httponly', '1');
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    @ini_set('session.cookie_secure', '1');
  }
  session_start();
}

// CSRF helpers
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function csrf_check(string $token): bool {
  return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Auth helpers
function is_logged_in(): bool {
  if (!isset($_SESSION['admin_id'])) return false;
  $timeout = 60 * 30; // 30 minutos
  $now = time();
  if (!isset($_SESSION['last_activity'])) { $_SESSION['last_activity'] = $now; return true; }
  if (($now - (int)$_SESSION['last_activity']) > $timeout) { return false; }
  $_SESSION['last_activity'] = $now;
  return true;
}
function require_login(): void {
  if (!is_logged_in()) {
    header('Location: login.php');
    exit;
  }
}

// Crear tablas mínimas si no existen
try {
  $pdo = getPDO();
  $pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

  $pdo->exec('CREATE TABLE IF NOT EXISTS site_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section VARCHAR(64) NOT NULL,
    field VARCHAR(64) NOT NULL,
    value MEDIUMTEXT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_section_field (section, field)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

  // Normalización de datos legacy: limpiar fechas inválidas y asegurar tipo DATETIME
  try { $pdo->exec("UPDATE contact_status SET deadline_at=NULL WHERE deadline_at='0000-00-00 00:00:00'"); } catch (Throwable $e) {}
  try { $pdo->exec("UPDATE contact_status SET deadline_at=NULL WHERE deadline_at=''"); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN deadline_at DATETIME NULL'); } catch (Throwable $e) {}

  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(24) NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT "pendiente",
    note VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_source_contact (source, contact_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  // Asegurar columna de soft-delete
  try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) {}

  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_interactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(24) NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_source_contact (source, contact_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  
  // Testimonios (imagen + frase)
  $pdo->exec('CREATE TABLE IF NOT EXISTS testimonials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    phrase VARCHAR(200) NOT NULL,
    name VARCHAR(80) NULL,
    age INT NULL,
    rating TINYINT NULL,
    charges INT NULL,
    social_url VARCHAR(255) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    position INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  // Asegurar columnas si la tabla existía antes
  try { $pdo->exec('ALTER TABLE testimonials ADD COLUMN name VARCHAR(80) NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE testimonials ADD COLUMN age INT NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE testimonials ADD COLUMN rating TINYINT NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE testimonials ADD COLUMN charges INT NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE testimonials ADD COLUMN social_url VARCHAR(255) NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE testimonials ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE testimonials ADD COLUMN position INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
  
  // Tabla de ejemplos (cards de "Algunos ejemplos")
  $pdo->exec('CREATE TABLE IF NOT EXISTS examples (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    title VARCHAR(120) NOT NULL,
    subtitle VARCHAR(200) NULL,
    coverage TEXT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  // Asegurar columnas si la tabla existía antes
  try { $pdo->exec('ALTER TABLE examples ADD COLUMN subtitle VARCHAR(200) NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE examples ADD COLUMN coverage TEXT NULL'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE examples ADD COLUMN position INT NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE examples ADD COLUMN uniq_fp VARCHAR(64) NULL UNIQUE'); } catch (Throwable $e) {}
} catch (Throwable $e) {
  log_error('admin/_init create tables: ' . $e->getMessage());
}

// Tabla de contactos: unificamos en contact_status
function contacts_table(PDO $pdo): array {
  return ['name' => 'contact_status', 'source' => 'contact_status'];
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Seed/ensure: usuario admin con contraseña inicial
try {
  $username = 'admin';
  $password = 'Admin1234!';
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $row = $pdo->prepare('SELECT id FROM users WHERE username=?');
  $row->execute([$username]);
  $id = $row->fetchColumn();
  if ($id) {
    $upd = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
    $upd->execute([$hash, $id]);
  } else {
    $ins = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?,?)');
    $ins->execute([$username, $hash]);
  }
} catch (Throwable $e) { log_error('admin/_init ensure admin: ' . $e->getMessage()); }


// Ampliar estructura de contact_status para almacenar campos de contacto
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN full_name VARCHAR(150) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN rut VARCHAR(15) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN age INT NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN phone VARCHAR(20) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN email VARCHAR(150) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN commune VARCHAR(100) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN income DECIMAL(12,2) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN isapre VARCHAR(100) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN charges INT NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN comments TEXT NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN preferred_channel VARCHAR(50) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN last_contact_at DATETIME NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN deadline_at DATETIME NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN alert_3d_sent TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN alert_1d_sent TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN alert_2h_sent TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable $e) {}

// Tabla de historial de cambios de estado/notas
try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    note TEXT NULL,
    user VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact (contact_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  // Asegurar columna de soft-delete en historial
  try { $pdo->exec('ALTER TABLE contact_history ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) {}
} catch (Throwable $e) { log_error('create contact_history: ' . $e->getMessage()); }

// Tabla de configuración de recordatorios
try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS reminder_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reminder_name VARCHAR(50) NOT NULL,
    interval_value INT NOT NULL,
    interval_unit ENUM("DAY","HOUR") NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  $countRem = (int)$pdo->query('SELECT COUNT(*) FROM reminder_settings')->fetchColumn();
  if ($countRem === 0) {
    $ins = $pdo->prepare('INSERT INTO reminder_settings (reminder_name, interval_value, interval_unit) VALUES (?,?,?)');
    $ins->execute(['3 days', 3, 'DAY']);
    $ins->execute(['1 day', 1, 'DAY']);
    $ins->execute(['2 hours', 2, 'HOUR']);
  }
} catch (Throwable $e) { log_error('create reminder_settings: ' . $e->getMessage()); }

// Refuerza tipos/longitudes (MODIFY) según requisitos
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN full_name VARCHAR(150) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN rut VARCHAR(15) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN age INT NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN phone VARCHAR(20) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN email VARCHAR(150) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN commune VARCHAR(100) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN income DECIMAL(12,2) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN isapre VARCHAR(100) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN charges INT NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN status ENUM(\'Nuevo\',\'En proceso\',\'Pospuesto\',\'Convertido\',\'Cerrado\') NOT NULL DEFAULT \'Nuevo\''); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN preferred_channel VARCHAR(50) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN last_contact_at DATETIME NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN deadline_at DATETIME NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN alert_3d_sent TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN alert_1d_sent TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_status MODIFY COLUMN alert_2h_sent TINYINT(1) NOT NULL DEFAULT 0'); } catch (Throwable $e) {}

// Migrar estados antiguos a 'Cerrado'
try { $pdo->exec("UPDATE contact_status SET status='Cerrado' WHERE status NOT IN ('Nuevo','En proceso','Pospuesto','Convertido','Cerrado')"); } catch (Throwable $e) {}


// Extender contact_history con tipo_contacto y content_email
try { $pdo->exec('ALTER TABLE contact_history ADD COLUMN tipo_contacto VARCHAR(50) NULL'); } catch (Throwable $e) {}
try { $pdo->exec('ALTER TABLE contact_history ADD COLUMN content_email MEDIUMTEXT NULL'); } catch (Throwable $e) {}

// Tabla de plantillas de correo
try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  // Seed de plantilla de bienvenida si no existe
  $tpl = $pdo->prepare('SELECT id FROM email_templates WHERE name=?');
  $tpl->execute(['welcome_mail']);
  if (!$tpl->fetchColumn()) {
    $ins = $pdo->prepare('INSERT INTO email_templates (name, subject, body) VALUES (?,?,?)');
    $ins->execute([
      'welcome_mail',
      '¡Gracias por contactar Tu Plan Seguro!',
      '<p>Hola {{name}},</p><p>Gracias por escribirnos. Uno de nuestros asesores te contactará pronto para ayudarte a elegir el mejor plan.</p><p>Saludos,<br>Equipo Tu Plan Seguro</p>'
    ]);
  }
} catch (Throwable $e) { log_error('create email_templates: ' . $e->getMessage()); }


