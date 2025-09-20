<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$notice = '';
$error = '';

// Cargar logo actual (site_content: section='site', field='site_logo')
$current = '';
try {
  $st = $pdo->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
  $st->execute(['site', 'site_logo']);
  $current = (string)($st->fetchColumn() ?: '');
} catch (Throwable $e) { log_error('logo load current: ' . $e->getMessage()); }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
    $error = 'Token inválido';
  } else {
    if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
      $error = 'No se envió archivo';
    } else {
      $f = $_FILES['logo'];
      if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        $err = (int)$f['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { $error = 'El archivo excede 2MB'; }
        else if ($err === UPLOAD_ERR_PARTIAL) { $error = 'Subida interrumpida, intenta nuevamente.'; }
        else if ($err === UPLOAD_ERR_NO_FILE) { $error = 'No se seleccionó archivo.'; }
        else { $error = 'Error al subir archivo (' . $err . ')'; }
      } else {
        $maxBytes = 2 * 1024 * 1024; // 2MB
        if ((int)$f['size'] > $maxBytes) { $error = 'El archivo excede 2MB'; }
        else {
          $allowedExt = ['png','jpg','jpeg','webp','svg'];
          $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
          if (!in_array($ext, $allowedExt, true)) { $error = 'Formato no permitido. PNG, JPG, WEBP o SVG'; }
          else {
            $dir = __DIR__ . '/../assets/img/logo';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $name = 'logo_' . date('Ymd_His') . '.' . $ext;
            $dest = $dir . '/' . $name;
            if (!@move_uploaded_file($f['tmp_name'], $dest)) { $error = 'No se pudo guardar el archivo'; }
            else {
              // Eliminar logo anterior si existía y está dentro del directorio permitido
              if ($current) {
                $old = realpath(__DIR__ . '/../' . ltrim($current,'/'));
                $base = realpath($dir);
                if ($old && $base && strpos($old, $base) === 0 && is_file($old)) { @unlink($old); }
              }
              $publicPath = 'assets/img/logo/' . $name;
              try {
                $up = $pdo->prepare('INSERT INTO site_content (section, field, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), last_updated=CURRENT_TIMESTAMP');
                $up->execute(['site', 'site_logo', $publicPath]);
                $current = $publicPath;
                $notice = '✅ Logo actualizado con éxito';
                // Log admin action
                if (function_exists('app_log')) { app_log('ADMIN cambió logo -> ' . $publicPath); }
              } catch (Throwable $e) { $error = 'Error al guardar en base de datos'; log_error('logo save db: ' . $e->getMessage()); }
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
  <title>Admin · Logo del sitio</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
</head>
<body>
  <header class="admin-header">
    <div class="wrap">
      <div>Logo del sitio</div>
      <nav class="admin-menu navbar navbar-expand-md navbar-light">
        <div class="container-fluid" style="padding:0">
          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Menú">
            <span class="navbar-toggler-icon"></span>
          </button>
          <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto mb-2 mb-md-0">
              <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="webDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Contenido web</a>
                <ul class="dropdown-menu" aria-labelledby="webDropdown">
                  <li><a class="dropdown-item" href="examples.php">Ejemplos</a></li>
                  <li><a class="dropdown-item" href="testimonials.php">Testimonios</a></li>
                  <li><a class="dropdown-item" href="banner.php">Banner</a></li>
                  <li><a class="dropdown-item" href="logo.php">Logo</a></li>
                  <li><a class="dropdown-item" href="favicon.php">Icono Favicon</a></li>
                </ul>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="configDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Configuración</a>
                <ul class="dropdown-menu" aria-labelledby="configDropdown">
                  <li><a class="dropdown-item" href="settings.php">Día primer contacto</a></li>
                  <li><a class="dropdown-item" href="reminders.php">Umbrales de aviso</a></li>
                  <li><a class="dropdown-item" href="email_template.php">Correo bienvenida</a></li>
                </ul>
              </li>
            </ul>
            <div class="d-flex"><a class="nav-link" href="logout.php">Salir</a></div>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <main class="container">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <a href="index.php">Dashboard</a>
      <span>/</span>
      <span>Marca</span>
      <span>/</span>
      <span>Logo</span>
    </nav>
    <?php if ($notice): ?><div class="alert" style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b">❌ <?= e($error) ?></div><?php endif; ?>

    <div class="panel">
      <h2>Logo actual</h2>
      <?php if ($current): ?>
        <div class="preview"><img src="../<?= e($current) ?>" alt="Logo actual" style="max-height:80px" /></div>
      <?php else: ?>
        <p class="muted">No hay logo configurado. Se usará el predeterminado.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Subir nuevo logo</h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <label>Archivo (PNG, JPG, WEBP o SVG · máx 2MB)</label>
        <input type="file" name="logo" accept="image/*" required />
        <div class="actions"><button class="btn" type="submit">Subir nuevo logo</button></div>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>


