<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$notice = '';
$error = '';

// Cargar favicon actual (site_content: section='site', field='site_favicon')
$current = '';
try {
  $st = $pdo->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
  $st->execute(['site', 'site_favicon']);
  $current = (string)($st->fetchColumn() ?: '');
} catch (Throwable $e) { log_error('favicon load current: ' . $e->getMessage()); }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
    $error = 'Token inválido';
  } else {
    if (!isset($_FILES['favicon']) || !is_array($_FILES['favicon'])) {
      $error = 'No se envió archivo';
    } else {
      $f = $_FILES['favicon'];
      if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        $err = (int)$f['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { $error = 'El archivo excede 512KB'; }
        else if ($err === UPLOAD_ERR_PARTIAL) { $error = 'Subida interrumpida, intenta nuevamente.'; }
        else if ($err === UPLOAD_ERR_NO_FILE) { $error = 'No se seleccionó archivo.'; }
        else { $error = 'Error al subir archivo (' . $err . ')'; }
      } else {
        $maxBytes = 512 * 1024; // 512KB
        if ((int)$f['size'] > $maxBytes) { $error = 'El archivo excede 512KB'; }
        else {
          $allowedExt = ['ico','png','svg'];
          $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
          if (!in_array($ext, $allowedExt, true)) { $error = 'Formato no permitido. ICO, PNG o SVG'; }
          else {
            $dir = __DIR__ . '/../assets/img/favicon';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $name = 'favicon_' . date('Ymd_His') . '.' . $ext;
            $dest = $dir . '/' . $name;
            if (!@move_uploaded_file($f['tmp_name'], $dest)) { $error = 'No se pudo guardar el archivo'; }
            else {
              // Eliminar favicon anterior si existía y está dentro del directorio permitido
              if ($current) {
                $old = realpath(__DIR__ . '/../' . ltrim($current,'/'));
                $base = realpath($dir);
                if ($old && $base && strpos($old, $base) === 0 && is_file($old)) { @unlink($old); }
              }
              $publicPath = 'assets/img/favicon/' . $name;
              try {
                $up = $pdo->prepare('INSERT INTO site_content (section, field, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), last_updated=CURRENT_TIMESTAMP');
                $up->execute(['site', 'site_favicon', $publicPath]);
                $current = $publicPath;
                $notice = '✅ Favicon actualizado con éxito';
                // Log admin action
                if (function_exists('app_log')) { app_log('ADMIN cambió favicon -> ' . $publicPath); }
              } catch (Throwable $e) { $error = 'Error al guardar en base de datos'; log_error('favicon save db: ' . $e->getMessage()); }
            }
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Favicon</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <main class="container">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <a href="index.php">Dashboard</a>
      <span>/</span>
      <span>Marca</span>
      <span>/</span>
      <span>Favicon</span>
    </nav>
    <?php if ($notice): ?><div class="alert" style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b">❌ <?= e($error) ?></div><?php endif; ?>

    <div class="panel">
      <h2>Favicon actual</h2>
      <?php if ($current): ?>
        <div class="preview" style="display:flex;align-items:center;gap:12px">
          <img src="../<?= e($current) ?>" alt="Favicon 16x16" style="width:16px;height:16px" />
          <img src="../<?= e($current) ?>" alt="Favicon 32x32" style="width:32px;height:32px" />
        </div>
      <?php else: ?>
        <p class="muted">No hay favicon configurado. Se usará el predeterminado.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Subir nuevo favicon</h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <label>Archivo (ICO, PNG o SVG · máx 512KB)</label>
        <input type="file" name="favicon" accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml,.ico,.png,.svg" required />
        <div class="actions"><button class="btn" type="submit">Subir favicon</button></div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>


