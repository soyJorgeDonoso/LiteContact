<?php
declare(strict_types=1);

// === JSON Hardening (no tocar el orden) ===
ob_start(); // capturar cualquier salida inesperada (BOM/echo/warnings)

error_reporting(E_ALL);
ini_set('display_errors', '0');           // no mostrar errores en salida
ini_set('log_errors', '1');               // loguear errores
if (!is_dir(__DIR__.'/../logs')) { @mkdir(__DIR__.'/../logs', 0777, true); }
ini_set('error_log', __DIR__ . '/../logs/system.log');

header('Content-Type: application/json; charset=utf-8');

// Shutdown handler para atrapar fatales y responder JSON válido + log detallado
register_shutdown_function(function () {
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) {
    $ts = date('Y-m-d H:i:s');
    if (function_exists('app_log')) { app_log('FATAL in ' . ($err['file']??'unknown') . ' on line ' . ($err['line']??'') . ': ' . ($err['message']??'')); }
    $buf = trim((string)(ob_get_contents() ?: ''));
    if ($buf !== '' && function_exists('app_log')) { app_log('FATAL + buffer: ' . substr($buf,0,500)); }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode([
      'ok' => false,
      'mail' => 0,
      'message' => 'Error fatal en el servidor. Revisa logs.'
    ], JSON_UNESCAPED_UNICODE);
  }
});

// Helper para responder JSON limpiamente SIEMPRE
function send_json(array $payload): void {
  $buf = trim(ob_get_contents() ?: '');
  if ($buf !== '' && function_exists('app_log')) { app_log('Unexpected buffer before JSON: ' . substr($buf,0,500)); }
  while (ob_get_level() > 0) { ob_end_clean(); }
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

require __DIR__ . '/db.php';
require __DIR__ . '/config.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/comunas.php';

function sc_is_ajax(): bool {
  return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}
function sc_log_app_error(string $message): void { @error_log('save_contact.php | ' . $message); }
function sc_json(array $payload): void { send_json($payload); }
function sc_redirect(string $param): void {
  $base = defined('BASE_URL') ? BASE_URL : '';
  header('Location: ' . $base . '/index.php?' . $param);
  exit;
}

try {
  if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    send_json(['ok'=>false,'mail'=>0,'message'=>'Método inválido']);
  }

  // Honeypot simple: si el campo oculto viene con contenido, responder éxito silencioso
  if (!empty($_POST['hp_field'] ?? '')) {
    if (function_exists('app_log')) { app_log('HONEYPOT triggered from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')); }
    send_json(['ok'=>true,'mail'=>0,'message'=>'Gracias']);
  }

  // Rate limit por IP: máx 5 intentos por 10 minutos
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  try {
    $pdo = getPDO();
    $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
      ip VARCHAR(45) NOT NULL,
      window_start DATETIME NOT NULL,
      count INT NOT NULL DEFAULT 1,
      PRIMARY KEY (ip, window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
    $stWin = $pdo->prepare('INSERT INTO rate_limits (ip, window_start, count) VALUES (?, DATE_FORMAT(NOW(), "%Y-%m-%d %H:%i:00"), 1)
      ON DUPLICATE KEY UPDATE count = count + 1');
    $stWin->execute([$ip]);
    $stSum = $pdo->prepare('SELECT COALESCE(SUM(count),0) FROM rate_limits WHERE ip=? AND window_start >= (NOW() - INTERVAL 10 MINUTE)');
    $stSum->execute([$ip]);
    $reqs = (int)$stSum->fetchColumn();
    if ($reqs > 5) {
      if (function_exists('app_log')) { app_log('RATE_LIMIT block ip=' . $ip . ' reqs=' . $reqs); }
      send_json(['ok'=>false,'mail'=>0,'message'=>'Demasiadas solicitudes desde tu IP. Intenta nuevamente en unos minutos.']);
    }
  } catch (Throwable $e) { /* en caso de error, no bloquear el flujo */ }

function sc_text(?string $v): string { return trim((string)$v); }
function sc_digits(?string $v): string { return preg_replace('/\D+/', '', (string)$v) ?? ''; }

$name = sc_text($_POST['name'] ?? '');
$rut = sc_text($_POST['rut'] ?? '');
$age = (int)sc_digits($_POST['age'] ?? '');
$phone = sc_digits($_POST['phone'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$commune = sc_text($_POST['commune'] ?? '');
$income = sc_digits($_POST['income'] ?? '');
$interest = (int)sc_digits($_POST['interest'] ?? '');
$charges = (int)sc_digits($_POST['charges'] ?? '0');
$isapre = (int)sc_digits($_POST['isapre'] ?? '');
$comments = sc_text($_POST['comments'] ?? '');

// Validaciones adicionales: income > 0, interest != 0, isapre definido (puede ser "0" como Ninguna)
if ($name === '' || $rut === '' || $age < 18 || $age > 100 || strlen($phone) < 8 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $commune==='') {
  send_json(['ok'=>false,'mail'=>0,'message'=>'Debes completar todos los campos correctamente']);
}
// Validar comuna contra la lista oficial
if (!function_exists('comuna_exists') || !comuna_exists($commune)) {
  send_json(['ok'=>false,'mail'=>0,'message'=>'Debes seleccionar una comuna válida']);
}
if ($income === '' || (int)$income <= 0) {
  send_json(['ok'=>false,'mail'=>0,'message'=>'Debes completar todos los campos correctamente']);
}
if ($interest === 0) {
  send_json(['ok'=>false,'mail'=>0,'message'=>'Debes completar todos los campos correctamente']);
}
// isapre puede venir vacío string -> inválido; valor "0" es válido (Ninguna)
if (!isset($_POST['isapre']) || $_POST['isapre'] === '') {
  send_json(['ok'=>false,'mail'=>0,'message'=>'Debes completar todos los campos correctamente']);
}

$phoneFull = '+569' . $phone;

  $pdo = getPDO();

  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150), rut VARCHAR(15), age INT, phone VARCHAR(20), email VARCHAR(150),
    commune VARCHAR(100), income DECIMAL(12,2) NULL, isapre VARCHAR(100), charges INT,
    comments TEXT, status ENUM("Nuevo","En proceso","Pospuesto","Convertido","Cerrado") NOT NULL DEFAULT "Nuevo",
    preferred_channel VARCHAR(50) NULL, deadline_at DATETIME NULL,
    alert_3d_sent TINYINT(1) NOT NULL DEFAULT 0,
    alert_1d_sent TINYINT(1) NOT NULL DEFAULT 0,
    alert_2h_sent TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    note TEXT NULL,
    tipo_contacto VARCHAR(50) NULL,
    content_email MEDIUMTEXT NULL,
    user VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact (contact_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  // Alinear borrado lógico con administración
  try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) { /* noop */ }
  try { $pdo->exec('ALTER TABLE contact_history ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) { /* noop */ }

  $pdo->exec('CREATE TABLE IF NOT EXISTS email_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

  // Settings para configuración global
  $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    value VARCHAR(255) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  // Valor por defecto de días para deadline si no existe
  try {
    $pdo->exec("INSERT INTO settings (key_name, value) VALUES ('default_deadline_days','3') ON DUPLICATE KEY UPDATE value=value");
  } catch (Throwable $e) { /* noop */ }
  $daysCfg = 3;
  try {
    $stmtCfg = $pdo->prepare("SELECT value FROM settings WHERE key_name='default_deadline_days'");
    $stmtCfg->execute();
    $daysCfg = (int)($stmtCfg->fetchColumn() ?: 3);
    if ($daysCfg < 0) { $daysCfg = 0; }
  } catch (Throwable $e) { if (function_exists('app_log')) { app_log('settings read failed: ' . $e->getMessage()); } }
  $deadlineAt = (new DateTime())->modify('+' . $daysCfg . ' days')->format('Y-m-d H:i:s');

  $tplChk = $pdo->prepare('SELECT id FROM email_templates WHERE name=?');
  $tplChk->execute(['welcome_mail']);
  if (!$tplChk->fetchColumn()) {
    $insTpl = $pdo->prepare('INSERT INTO email_templates (name, subject, body) VALUES (?,?,?)');
    $insTpl->execute([
      'welcome_mail',
      '¡Bienvenido/a a Tu Plan Seguro, {{full_name}}!',
      '<p>Hola {{full_name}},</p><p>Gracias por registrarte en Tu Plan Seguro. Muy pronto te contactaremos para ayudarte a elegir el mejor plan.</p><p>Saludos,<br>Equipo Tu Plan Seguro</p>'
    ]);
  }

  // Idempotencia: huella única por día (nombre+email+tel+comuna+renta+isapre+fecha)
  try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN uniq_fp VARCHAR(64) NULL UNIQUE'); } catch (Throwable $e) { /* noop */ }
  $normName = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
  $normCommune = function_exists('mb_strtolower') ? mb_strtolower($commune, 'UTF-8') : strtolower($commune);
  $normIncome = $income !== '' ? number_format((float)$income, 2, '.', '') : '0.00';
  $uniqSource = date('Y-m-d') . '|' . $normName . '|' . strtolower($email) . '|' . $phoneFull . '|' . $normCommune . '|' . $normIncome . '|' . (string)$isapre;
  $uniqFp = hash('sha256', $uniqSource);
  $insC = $pdo->prepare('INSERT INTO contact_status (uniq_fp,full_name,rut,age,phone,email,commune,income,isapre,charges,comments,status,preferred_channel,deadline_at,is_active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), is_active=1, updated_at=CURRENT_TIMESTAMP');
  $insC->execute([
    $uniqFp,
    $name,
    $rut,
    $age,
    $phoneFull,
    strtolower($email),
    $commune,
    $income !== '' ? number_format((float)$income, 2, '.', '') : null,
    (string)$isapre,
    $charges,
    $comments !== '' ? $comments : null,
    'Nuevo',
    'Registro web',
    $deadlineAt
  ]);
  $affected = (int)$insC->rowCount(); if (function_exists('app_log')) { app_log('CONTACT insert rowCount=' . $affected . ' email=' . strtolower($email) . ' phone=' . $phoneFull); }
  $isNew = ($affected === 1);
  $contactId = (int)$pdo->lastInsertId();

  if ($isNew) {
    $h0 = $pdo->prepare('INSERT INTO contact_history (contact_id, old_status, new_status, note, tipo_contacto, user) VALUES (?,?,?,?,?,?)');
    $h0->execute([$contactId, null, 'Nuevo', 'Registro en formulario público', 'Registro web', 'sistema']);
  }

  // Plantilla: primero site_content.email.welcome_mail_template; si no, email_templates.welcome_mail
  $bodyTpl = '';
  try {
    $stSc = $pdo->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
    $stSc->execute(['email','welcome_mail_template']);
    $bodyTpl = (string)($stSc->fetchColumn() ?: '');
  } catch (Throwable $e) { log_error('load site_content tmpl: ' . $e->getMessage()); }
  $tplSt = $pdo->prepare('SELECT subject, body FROM email_templates WHERE name=?');
  $tplSt->execute(['welcome_mail']);
  $tpl = $tplSt->fetch();
  $subject = $tpl ? (string)$tpl['subject'] : 'Bienvenido a Tu Plan Seguro';
  if ($bodyTpl === '') { $bodyTpl = $tpl ? (string)$tpl['body'] : '<p>Hola {{full_name}}, gracias por contactarnos.</p>'; }
  if (!function_exists('render_template_placeholders')) {
    function render_template_placeholders(string $html, array $data): string { foreach ($data as $k=>$v){ $html = str_replace('{{'.$k.'}}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $html);} return $html; }
  }
  $placeholders = [
    'full_name' => $name,
    'name' => $name,
    'email' => $email,
    'phone' => $phoneFull,
    'commune' => $commune,
  ];
  $subject = render_template_placeholders($subject, $placeholders);
  $body = render_template_placeholders($bodyTpl, $placeholders);

  // Enviar correo solo para registros nuevos (evita duplicados)
  $send = ['ok'=>true,'method'=>'log'];
  if ($isNew) {
    $send = send_welcome_email($email, $name, $subject, $body);
  }

  // Registro de historial según método utilizado
  $note = 'Correo de bienvenida registrado en logs (sin envío real)';
  if (!empty($send['method']) && ($send['method'] === 'smtp' || $send['method'] === 'mail')) {
    $note = 'Correo de bienvenida enviado';
  } else if (!empty($send['error'])) {
    $note = 'Error al enviar correo: ' . (string)$send['error'];
  }
  if ($isNew) {
    $h = $pdo->prepare('INSERT INTO contact_history (contact_id, old_status, new_status, note, tipo_contacto, content_email, user) VALUES (?,?,?,?,?,?,?)');
    $h->execute([$contactId, 'Nuevo', 'Nuevo', $note, 'Correo electrónico (automático)', $body, 'sistema']);
  }

  if (!empty($send['ok'])) {
    if (!empty($send['method']) && ($send['method'] === 'smtp' || $send['method'] === 'mail')) {
      send_json(['ok'=>true,'mail'=>1,'message'=>'Registro exitoso y correo enviado']);
    } else {
      // Si no es nuevo, no reenviamos correo y marcamos mail=0
      $msg = $isNew ? 'Registro exitoso; correo no enviado pero guardado en logs' : 'Registro existente; no se envió correo nuevamente';
      send_json(['ok'=>true,'mail'=>0,'message'=>$msg]);
    }
  } else {
    send_json(['ok'=>false,'mail'=>0,'message'=>'Error al enviar correo: ' . ($send['error'] ?? 'desconocido')]);
  }
} catch (Throwable $e) {
  $ts = date('Y-m-d H:i:s');
  $detail = "EXCEPTION in {$e->getFile()} line {$e->getLine()}: " . $e->getMessage();
  if (function_exists('log_error')) { log_error($detail); }
  else { @error_log($detail); }
  send_json(['ok'=>false,'mail'=>0,'message'=>'Error al procesar el formulario: ' . $e->getMessage()]);
}


