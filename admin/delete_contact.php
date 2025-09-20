<?php
declare(strict_types=1);

// === JSON Hardening (no tocar el orden) ===
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(__DIR__.'/../logs')) { @mkdir(__DIR__.'/../logs', 0777, true); }
ini_set('error_log', __DIR__ . '/../logs/system.log');
header('Content-Type: application/json; charset=utf-8');
register_shutdown_function(function(){
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) {
    $buf = trim(ob_get_contents() ?: '');
    if ($buf !== '') { file_put_contents(__DIR__.'/../logs/unexpected_output.log',"[".date('Y-m-d H:i:s')."] FATAL + buffer:\n".$buf."\n\n",FILE_APPEND); }
    while (ob_get_level() > 0) { ob_end_clean(); }
    echo json_encode(['ok'=>false,'message'=>'Error fatal en el servidor. Revisa logs.'], JSON_UNESCAPED_UNICODE);
  }
});
function send_json_admin(array $p): void {
  $buf = trim(ob_get_contents() ?: '');
  if ($buf !== '') { file_put_contents(__DIR__.'/../logs/unexpected_output.log',"[".date('Y-m-d H:i:s')."] Unexpected buffer before JSON:\n".$buf."\n\n",FILE_APPEND); }
  while (ob_get_level() > 0) { ob_end_clean(); }
  echo json_encode($p, JSON_UNESCAPED_UNICODE);
  exit;
}

require __DIR__ . '/_init.php';
require_login();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  send_json_admin(['ok'=>false, 'message'=>'Método inválido']);
}

$token = (string)($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
  send_json_admin(['ok'=>false, 'message'=>'CSRF inválido']);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  send_json_admin(['ok'=>false, 'message'=>'ID inválido']);
}

try {
  $pdo = getPDO();
  $exists = $pdo->prepare('SELECT id FROM contact_status WHERE id=?');
  $exists->execute([$id]);
  $cid = (int)$exists->fetchColumn();
  if ($cid === 0) {
    send_json_admin(['ok'=>false, 'message'=>'Contacto no existe']);
  }

  // Asegurar columnas de soft-delete
  try { $pdo->exec('ALTER TABLE contact_status ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) {}
  try { $pdo->exec('ALTER TABLE contact_history ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) {}
  $pdo->beginTransaction();
  $updC = $pdo->prepare('UPDATE contact_status SET is_active=0, updated_at=NOW() WHERE id=?');
  $updC->execute([$id]);
  $updH = $pdo->prepare('UPDATE contact_history SET is_active=0 WHERE contact_id=?');
  $updH->execute([$id]);
  $pdo->commit();
  send_json_admin(['ok'=>true, 'message'=>'Contacto desactivado']);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
  log_error('delete_contact: ' . $e->getMessage());
  send_json_admin(['ok'=>false, 'message'=>'Error al eliminar']);
}

