<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$id = (int)($_GET['id'] ?? 0);
$return = (string)($_GET['return'] ?? '');
if ($id <= 0) { header('Location: contacts.php'); exit; }

$row = null;
try {
  $st = $pdo->prepare('SELECT * FROM contact_status WHERE id=?');
  $st->execute([$id]);
  $row = $st->fetch();
} catch (Throwable $e) { log_error('view contact: ' . $e->getMessage()); }
if (!$row) { header('Location: contacts.php'); exit; }

// Guardar confirmación de datos de contacto (sin modificar datos originales)
$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $csrf = (string)($_POST['csrf'] ?? '');
  if ($action === 'save_contact_info' && function_exists('csrf_check') && csrf_check($csrf)) {
    try {
      // Crear tabla si no existe (una fila por contacto)
      $pdo->exec('CREATE TABLE IF NOT EXISTS contact_contact_data (
        contact_id INT UNSIGNED NOT NULL PRIMARY KEY,
        email VARCHAR(150) NULL,
        phone VARCHAR(30) NULL,
        whatsapp VARCHAR(30) NULL,
        email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        phone_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        whatsapp_confirmed TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        verified_by VARCHAR(100) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_verified_by (verified_by)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
      // Asegurar columnas en entornos ya existentes
      try { $pdo->exec('ALTER TABLE contact_contact_data ADD COLUMN email VARCHAR(150) NULL'); } catch (Throwable $e) { /* noop */ }
      try { $pdo->exec('ALTER TABLE contact_contact_data ADD COLUMN phone VARCHAR(30) NULL'); } catch (Throwable $e) { /* noop */ }
      try { $pdo->exec('ALTER TABLE contact_contact_data ADD COLUMN whatsapp VARCHAR(30) NULL'); } catch (Throwable $e) { /* noop */ }

      $emailIn = trim((string)($_POST['email'] ?? ''));
      $phoneIn = trim((string)($_POST['phone'] ?? ''));
      $waIn = trim((string)($_POST['whatsapp'] ?? ''));
      $notes = trim((string)($_POST['notes'] ?? ''));
      $verifiedBy = isset($_SESSION['admin_id']) ? ('admin#' . (int)$_SESSION['admin_id']) : 'admin';

      // Validaciones básicas
      if ($emailIn !== '' && !filter_var($emailIn, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Correo inválido');
      }
      $phoneDigits = $phoneIn !== '' ? preg_replace('/\D+/', '', $phoneIn) : '';
      if ($phoneIn !== '' && strlen((string)$phoneDigits) < 8) {
        throw new RuntimeException('Teléfono inválido');
      }
      // Normalización WhatsApp a formato internacional (+56######### si corresponde)
      if ($waIn !== '') {
        $waDigits = preg_replace('/\D+/', '', $waIn);
        if ($waDigits === '' || strlen($waDigits) < 8) {
          throw new RuntimeException('WhatsApp inválido');
        }
        if (strpos($waDigits, '56') === 0) {
          $waIn = '+' . $waDigits;
        } else if (strlen($waDigits) === 9) {
          $waIn = '+56' . $waDigits;
        } else {
          $waIn = '+' . $waDigits;
        }
      }

      $sql = 'INSERT INTO contact_contact_data (contact_id, email, phone, whatsapp, notes, verified_by)
              VALUES (?,?,?,?,?,?)
              ON DUPLICATE KEY UPDATE email=VALUES(email), phone=VALUES(phone), whatsapp=VALUES(whatsapp), notes=VALUES(notes), verified_by=VALUES(verified_by)';
      $stIns = $pdo->prepare($sql);
      $stIns->execute([$id, ($emailIn!==''?$emailIn:null), ($phoneIn!==''?$phoneIn:null), ($waIn!==''?$waIn:null), ($notes!==''?$notes:null), $verifiedBy]);
      if (function_exists('app_log')) { app_log('CONTACT info saved for #' . $id); }
      $notice = 'Datos de contacto confirmados';
    } catch (Throwable $e) { log_error('save contact_contact_data: ' . $e->getMessage()); $notice = 'Error al guardar: ' . $e->getMessage(); }
  }
  if ($action === 'add_extra' && function_exists('csrf_check') && csrf_check($csrf)) {
    try {
      $pdo->exec('CREATE TABLE IF NOT EXISTS contact_contact_extra (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id INT UNSIGNED NOT NULL,
        label VARCHAR(100) NOT NULL,
        value VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_contact (contact_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
      $label = trim((string)($_POST['label'] ?? ''));
      $value = trim((string)($_POST['value'] ?? ''));
      if ($label !== '' && $value !== '') {
        $ins = $pdo->prepare('INSERT INTO contact_contact_extra (contact_id, label, value) VALUES (?,?,?)');
        $ins->execute([$id, $label, $value]);
        $notice = 'Dato adicional agregado';
      } else {
        $notice = 'Etiqueta y valor son obligatorios';
      }
    } catch (Throwable $e) { log_error('add contact_contact_extra: ' . $e->getMessage()); $notice = 'Error al agregar dato adicional'; }
  }
  if ($action === 'delete_extra' && function_exists('csrf_check') && csrf_check($csrf)) {
    try {
      $eid = (int)($_POST['extra_id'] ?? 0);
      if ($eid > 0) {
        $del = $pdo->prepare('DELETE FROM contact_contact_extra WHERE id=? AND contact_id=?');
        $del->execute([$eid, $id]);
        $notice = 'Dato adicional eliminado';
      }
    } catch (Throwable $e) { log_error('delete contact_contact_extra: ' . $e->getMessage()); $notice = 'Error al eliminar'; }
  }
}

// Asegurar tablas para lectura
try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_contact_data (
    contact_id INT UNSIGNED NOT NULL PRIMARY KEY,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    whatsapp VARCHAR(30) NULL,
    email_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    phone_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    verified_by VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
} catch (Throwable $e) { /* noop */ }
// Cargar confirmación existente
$confirm = ['notes'=>'','verified_by'=>'','updated_at'=>'','email'=>'','phone'=>'','whatsapp'=>''];
try {
  $stC = $pdo->prepare('SELECT notes, verified_by, updated_at, email, phone, whatsapp FROM contact_contact_data WHERE contact_id=?');
  $stC->execute([$id]);
  $tmp = $stC->fetch();
  if ($tmp) { $confirm = $tmp; }
} catch (Throwable $e) { /* noop */ }
// Prefill por defecto con datos originales si no hay confirmación
if (empty($confirm['email'])) { $confirm['email'] = (string)$row['email']; }
if (empty($confirm['phone'])) { $confirm['phone'] = (string)$row['phone']; }
if (empty($confirm['whatsapp'])) {
  $digits = preg_replace('/\D+/', '', (string)$row['phone']);
  $confirm['whatsapp'] = $digits ? ('+56' . ltrim($digits, '0')) : '';
}

// Cargar extras de contacto
$extras = [];
try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS contact_contact_extra (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_id INT UNSIGNED NOT NULL,
    label VARCHAR(100) NOT NULL,
    value VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_contact (contact_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  $stE = $pdo->prepare('SELECT id, label, value, updated_at FROM contact_contact_extra WHERE contact_id=? ORDER BY id DESC');
  $stE->execute([$id]);
  $extras = $stE->fetchAll();
} catch (Throwable $e) { /* noop */ }

// cargar historial
$hist = [];
try {
  $st = $pdo->prepare('SELECT old_status, new_status, note, created_at, user FROM contact_history WHERE contact_id=? ORDER BY id DESC');
  $st->execute([$id]);
  $hist = $st->fetchAll();
} catch (Throwable $e) { log_error('view history: ' . $e->getMessage()); }

// Calcular badge de "Fecha próximo contacto" según umbrales de recordatorio
$deadlineBadge = '';
$deadlineText = '';
$deadlineRemaining = '';
try {
  $deadlineRaw = (string)($row['deadline_at'] ?? '');
  $deadlineText = htmlspecialchars($deadlineRaw);
  if ($deadlineRaw && $deadlineRaw !== '0000-00-00 00:00:00') {
    // Cargar umbrales desde settings
    $rem = [ 'r1v'=>5, 'r1u'=>'d', 'c1'=>'#fde68a', 'r2v'=>2, 'r2u'=>'d', 'c2'=>'#fb923c', 'r3v'=>12, 'r3u'=>'h', 'c3'=>'#ef4444' ];
    try {
      $sqlSettings = "SELECT key_name AS k, value AS v FROM settings WHERE key_name IN ('reminder_1_value','reminder_1_unit','reminder_2_value','reminder_2_unit','reminder_3_value','reminder_3_unit','reminder_1_color','reminder_2_color','reminder_3_color')";
      $stSet = $pdo->query($sqlSettings);
      if ($stSet) {
        while ($rowSet = $stSet->fetch()) {
          switch ($rowSet['k']) {
            case 'reminder_1_value': $rem['r1v'] = (int)$rowSet['v']; break;
            case 'reminder_1_unit':  $rem['r1u'] = (string)$rowSet['v']; break;
            case 'reminder_2_value': $rem['r2v'] = (int)$rowSet['v']; break;
            case 'reminder_2_unit':  $rem['r2u'] = (string)$rowSet['v']; break;
            case 'reminder_3_value': $rem['r3v'] = (int)$rowSet['v']; break;
            case 'reminder_3_unit':  $rem['r3u'] = (string)$rowSet['v']; break;
            case 'reminder_1_color': $rem['c1']  = (string)$rowSet['v']; break;
            case 'reminder_2_color': $rem['c2']  = (string)$rowSet['v']; break;
            case 'reminder_3_color': $rem['c3']  = (string)$rowSet['v']; break;
          }
        }
      }
    } catch (Throwable $e) { /* noop */ }
    $thr1 = ($rem['r1u']==='h' ? $rem['r1v']*3600 : $rem['r1v']*86400);
    $thr2 = ($rem['r2u']==='h' ? $rem['r2v']*3600 : $rem['r2v']*86400);
    $thr3 = ($rem['r3u']==='h' ? $rem['r3v']*3600 : $rem['r3v']*86400);
    // Convertir deadline_raw a timestamp respetando timezone
    $ts = @strtotime($deadlineRaw);
    if ($ts) {
      $now = time();
      $diff = $ts - $now;
      if ($diff < 0) {
        $deadlineBadge = '<span class="badge" style="background:' . htmlspecialchars($rem['c3']) . ';color:#fff;margin-left:6px">Vencido</span>';
        $deadlineRemaining = 'Vencido';
      } else {
        // Elegir color por proximidad
        $color = '';
        if ($diff <= $thr3)            { $color = $rem['c3']; }
        else if ($diff <= $thr2)       { $color = $rem['c2']; }
        else if ($diff <= $thr1)       { $color = $rem['c1']; }
        if ($color !== '') {
          $deadlineBadge = '<span class="badge" style="background:' . htmlspecialchars($color) . ';margin-left:6px">Próximo</span>';
        }
        // Texto de tiempo restante detallado (min, h+min, días+h)
        $minutesTotal = (int)floor($diff / 60);
        if ($minutesTotal < 60) {
          $deadlineRemaining = 'Faltan ' . $minutesTotal . ' min';
        } else if ($minutesTotal < 24*60) {
          $h = (int)floor($minutesTotal / 60);
          $m = $minutesTotal % 60;
          $deadlineRemaining = 'Faltan ' . $h . ' h' . ($m>0 ? (' ' . $m . ' min') : '');
        } else {
          $days = (int)floor($diff / 86400);
          $rem = $diff - ($days * 86400);
          $h = (int)floor($rem / 3600);
          $deadlineRemaining = 'Faltan ' . $days . ' días' . ($h>0 ? (' ' . $h . ' h') : '');
        }
      }
    }
  }
} catch (Throwable $e) { /* noop */ }

// Mapear código de isapre a nombre legible
$isapreMap = [
  '1'=>'Colmena Golden Cross','3'=>'Consalud','4'=>'Banmédica','5'=>'Cruz Blanca','6'=>'Nueva Masvida','7'=>'VidaTres','8'=>'Fonasa','9'=>'Esencial','0'=>'Ninguna'
];
$rawIsapre = (string)($row['isapre'] ?? '');
$isapreDisplay = isset($isapreMap[$rawIsapre]) ? $isapreMap[$rawIsapre] : $rawIsapre;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contacto #<?= (int)$id ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
  <div class="container py-4">
    <?php $backHref = 'contacts.php' . ($return ? ('?' . $return) : ''); ?>
    <?php if ($notice): ?><div class="alert alert-info"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm mb-3" href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>">← Volver</a>
    <div class="card">
      <div class="card-header">Contacto #<?= (int)$id ?></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6"><strong>Nombre:</strong> <?= htmlspecialchars((string)$row['full_name']) ?></div>
          <div class="col-md-3"><strong>RUT:</strong> <?= htmlspecialchars((string)$row['rut']) ?></div>
          <div class="col-md-3"><strong>Edad:</strong> <?= (int)$row['age'] ?></div>
          <div class="col-md-6"><strong>Teléfono:</strong> <?= htmlspecialchars((string)$row['phone']) ?></div>
          <div class="col-md-6"><strong>Email:</strong> <?= htmlspecialchars((string)$row['email']) ?></div>
          <div class="col-md-4"><strong>Comuna:</strong> <?= htmlspecialchars((string)$row['commune']) ?></div>
          <div class="col-md-4"><strong>Isapre:</strong> <?= htmlspecialchars((string)$isapreDisplay) ?></div>
          <div class="col-md-4"><strong>Ingresos:</strong> <?= htmlspecialchars((string)$row['income']) ?></div>
          <div class="col-md-12"><strong>Comentarios:</strong><br><?= nl2br(htmlspecialchars((string)$row['comments'])) ?></div>
          <?php
            $statusText = (string)($row['status'] ?? '');
            $statusClass = 'bg-secondary';
            switch ($statusText) {
              case 'Nuevo': $statusClass = 'bg-primary'; break;
              case 'En proceso': $statusClass = 'bg-info text-dark'; break;
              case 'Pospuesto': $statusClass = 'bg-warning text-dark'; break;
              case 'Convertido': $statusClass = 'bg-success'; break;
              case 'Cerrado': default: $statusClass = 'bg-dark'; break;
            }
          ?>
          <div class="col-md-4">
            <strong>Estado:</strong>
            <span class="badge rounded-pill <?= e($statusClass) ?>" style="font-size:.95rem;padding:.55em .9em;vertical-align:middle"><?= e($statusText ?: '—') ?></span>
          </div>
          <div class="col-md-4"><strong>Fecha próximo contacto:</strong> <?= $deadlineText ?> <?= $deadlineBadge ?> <?php if ($deadlineRemaining): ?><small class="text-muted" style="margin-left:6px">(<?= htmlspecialchars($deadlineRemaining) ?>)</small><?php endif; ?></div>
          <div class="col-md-4"><strong>Canal preferido:</strong> <?= htmlspecialchars((string)($row['preferred_channel'] ?? '')) ?></div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">Datos de contacto (confirmación)</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="save_contact_info" />
          <div class="row g-3 align-items-center">
            <div class="col-md-4">
              <div class="mt-2">
                <label class="form-label">Correo</label>
                <input class="form-control form-control-sm" type="email" name="email" value="<?= htmlspecialchars((string)$confirm['email']) ?>" />
              </div>
            </div>
            <div class="col-md-4">
              <div class="mt-2">
                <label class="form-label">Teléfono</label>
                <input class="form-control form-control-sm" type="text" name="phone" value="<?= htmlspecialchars((string)$confirm['phone']) ?>" />
              </div>
            </div>
            <div class="col-md-4">
              <div class="mt-2">
                <label class="form-label">WhatsApp</label>
                <input class="form-control form-control-sm" type="text" name="whatsapp" value="<?= htmlspecialchars((string)$confirm['whatsapp']) ?>" />
              </div>
            </div>
            <div class="col-12">
              <label class="form-label" for="f_notes">Notas (opcional)</label>
              <textarea id="f_notes" name="notes" class="form-control" rows="2" placeholder="Observaciones o detalle de confirmación"><?= htmlspecialchars((string)($confirm['notes'] ?? '')) ?></textarea>
              <?php if (!empty($confirm['verified_by'])): ?>
                <div class="form-text">Última verificación por <?= htmlspecialchars((string)$confirm['verified_by']) ?> el <?= htmlspecialchars((string)$confirm['updated_at']) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-12 d-flex justify-content-end">
              <button type="submit" class="btn btn-sm btn-primary">Guardar confirmación</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Datos adicionales</span>
        <form method="post" class="d-flex gap-2 align-items-end">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
          <input type="hidden" name="action" value="add_extra" />
          <div>
            <label class="form-label">Etiqueta</label>
            <input class="form-control form-control-sm" type="text" name="label" placeholder="p. ej. página web" />
          </div>
          <div>
            <label class="form-label">Valor</label>
            <input class="form-control form-control-sm" type="text" name="value" placeholder="p. ej. https://..." />
          </div>
          <div class="mb-1">
            <button class="btn btn-sm btn-outline-primary" type="submit">Agregar</button>
          </div>
        </form>
      </div>
      <ul class="list-group list-group-flush">
        <?php if (!$extras): ?>
          <li class="list-group-item text-muted">Sin datos adicionales</li>
        <?php else: foreach ($extras as $ex): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars((string)$ex['label']) ?>:</strong> <?= htmlspecialchars((string)$ex['value']) ?>
              <div class="text-muted small">Actualizado: <?= htmlspecialchars((string)$ex['updated_at']) ?></div>
            </div>
            <form method="post" onsubmit="return confirm('¿Eliminar este dato?');">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="delete_extra" />
              <input type="hidden" name="extra_id" value="<?= (int)$ex['id'] ?>" />
              <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
            </form>
          </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>

    <div class="card mt-3">
      <div class="card-header">Historial</div>
      <ul class="list-group list-group-flush">
        <?php if (!$hist): ?>
          <li class="list-group-item text-muted">Sin historial</li>
        <?php else: foreach ($hist as $h): ?>
          <li class="list-group-item">
            <small class="text-muted">[<?= htmlspecialchars((string)$h['created_at']) ?>] <?= htmlspecialchars((string)($h['user'] ?? 'sistema')) ?></small><br>
            Estado: <?= htmlspecialchars((string)($h['old_status'] ?? '')) ?> → <?= htmlspecialchars((string)($h['new_status'] ?? '')) ?><br>
            <?php if (!empty($h['note'])): ?>Nota: <?= htmlspecialchars((string)$h['note']) ?><?php endif; ?>
          </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>
    <div class="mt-3">
      <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>">← Volver a la lista</a>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


