<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$notice = '';

// Ensure settings table
try {
  $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    value VARCHAR(255) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
  $pdo->exec("INSERT INTO settings (key_name,value) VALUES ('default_deadline_days','3') ON DUPLICATE KEY UPDATE value=value");
} catch (Throwable $e) { log_error('settings ensure: ' . $e->getMessage()); }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
    $notice = 'Token inválido';
  } else {
    $days = (int)($_POST['default_deadline_days'] ?? 3);
    if ($days < 0) { $days = 0; }
    try {
      $st = $pdo->prepare("INSERT INTO settings (key_name,value) VALUES ('default_deadline_days',?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
      $st->execute([ (string)$days ]);
      $notice = 'Configuración guardada';
    } catch (Throwable $e) { log_error('settings save: ' . $e->getMessage()); $notice = 'Error al guardar'; }
  }
}

$curDays = 3;
try { $curDays = (int)($pdo->query("SELECT value FROM settings WHERE key_name='default_deadline_days'")->fetchColumn() ?: 3); } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Configuración</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    .settings-form{max-width:520px}
  </style>
  </head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <main class="container my-3">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <a href="index.php">Dashboard</a>
      <span>/</span>
      <span>Configuración</span>
    </nav>
    <?php if ($notice): ?><div class="alert alert-info"><?= e($notice) ?></div><?php endif; ?>
    <form class="settings-form" method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
      <div class="mb-3">
        <label class="form-label">Días por defecto para Próximo contacto (alta pública)</label>
        <input type="number" min="0" class="form-control input-sm" name="default_deadline_days" value="<?= (int)$curDays ?>" />
        <div class="form-text">Se suma a la fecha de registro del formulario público.</div>
      </div>
      <button class="btn btn-sm btn--primary" type="submit" title="Guardar" aria-label="Guardar">Guardar</button>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


