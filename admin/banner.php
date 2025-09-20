<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$safeSet = static function(string $k, string $v): void { @ini_set($k, $v); };
$safeSet('upload_max_filesize', '20M');
$safeSet('post_max_size', '22M');
$safeSet('max_execution_time', '300');
$safeSet('max_input_time', '300');

$pdo = getPDO();
$notice = '';
$error = '';

// Cargar imagen actual
$current = '';
// Cargar frases actuales (estructura: [{text:string, active:int}])
$phrases = [];
try {
  $st = $pdo->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
  $st->execute(['home', 'banner_image']);
  $current = (string)($st->fetchColumn() ?: '');
} catch (Throwable $e) { log_error('banner load current: ' . $e->getMessage()); }
try {
  $stp = $pdo->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
  $stp->execute(['home', 'banner_phrases']);
  $json = (string)($stp->fetchColumn() ?: '');
  if ($json !== '') {
    $arr = json_decode($json, true);
    if (is_array($arr)) {
      foreach ($arr as $it) {
        if (is_array($it) && isset($it['text'])) {
          $txt = trim((string)$it['text']); if ($txt==='') continue;
          $phrases[] = ['text'=>$txt, 'active'=>(!empty($it['active'])?1:0)];
        } else if (is_string($it)) {
          $txt = trim($it); if ($txt==='') continue;
          $phrases[] = ['text'=>$txt, 'active'=>1];
        }
      }
    }
  }
} catch (Throwable $e) { log_error('banner load phrases: ' . $e->getMessage()); }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
    $error = 'Token inválido';
  } else {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add_phrase') {
      $p = trim((string)($_POST['phrase'] ?? ''));
      if ($p === '' || mb_strlen($p) > 160) { $error = 'Frase requerida (máx 160)'; }
      else {
        $phrases[] = ['text'=>$p, 'active'=>1];
        try {
          $up = $pdo->prepare('INSERT INTO site_content (section, field, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), last_updated=CURRENT_TIMESTAMP');
          $up->execute(['home', 'banner_phrases', json_encode($phrases, JSON_UNESCAPED_UNICODE)]);
          $notice = '✅ Frase agregada';
        } catch (Throwable $e) { $error = 'Error al guardar frases'; log_error('banner save phrase: ' . $e->getMessage()); }
      }
    } else if ($action === 'delete_phrase') {
      $idx = (int)($_POST['idx'] ?? -1);
      if ($idx >= 0 && $idx < count($phrases)) {
        array_splice($phrases, $idx, 1);
        try {
          $up = $pdo->prepare('INSERT INTO site_content (section, field, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), last_updated=CURRENT_TIMESTAMP');
          $up->execute(['home', 'banner_phrases', json_encode($phrases, JSON_UNESCAPED_UNICODE)]);
          $notice = '✅ Frase eliminada';
        } catch (Throwable $e) { $error = 'Error al guardar frases'; log_error('banner delete phrase: ' . $e->getMessage()); }
      }
    } else if ($action === 'toggle_phrase') {
      $idx = (int)($_POST['idx'] ?? -1);
      if ($idx >= 0 && $idx < count($phrases)) {
        $phrases[$idx]['active'] = !empty($phrases[$idx]['active']) ? 0 : 1;
        try {
          $up = $pdo->prepare('INSERT INTO site_content (section, field, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), last_updated=CURRENT_TIMESTAMP');
          $up->execute(['home', 'banner_phrases', json_encode($phrases, JSON_UNESCAPED_UNICODE)]);
          $notice = '✅ Estado actualizado';
        } catch (Throwable $e) { $error = 'Error al guardar frases'; log_error('banner toggle phrase: ' . $e->getMessage()); }
      }
    } else if (!isset($_FILES['banner']) || !is_array($_FILES['banner'])) {
      $error = 'No se envió archivo';
    } else {
      $f = $_FILES['banner'];
      if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        $err = (int)$f['error'];
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
          $error = 'El archivo excede el tamaño máximo permitido (20MB)';
        } else if ($err === UPLOAD_ERR_PARTIAL) {
          $error = 'La subida se interrumpió. Intenta nuevamente.';
        } else if ($err === UPLOAD_ERR_NO_FILE) {
          $error = 'No se seleccionó ningún archivo.';
        } else {
          $error = 'Error al subir archivo (' . $err . ')';
        }
      } else {
        $maxBytes = 20 * 1024 * 1024; // 20MB
        if ((int)$f['size'] > $maxBytes) {
          $error = 'El archivo excede el tamaño máximo permitido (20MB)';
        } else {
          $allowed = [
            'image/jpeg'=>'jpg', 'image/jpg'=>'jpg', 'image/pjpeg'=>'jpg',
            'image/png'=>'png', 'image/x-png'=>'png',
            'image/webp'=>'webp'
          ];
          $finfo = @finfo_open(FILEINFO_MIME_TYPE);
          $mime = $finfo ? (string)finfo_file($finfo, $f['tmp_name']) : '';
          if ($finfo) { @finfo_close($finfo); }
          if (!isset($allowed[$mime])) {
            $error = 'Formato no permitido. Solo se aceptan JPG, PNG o WebP';
          } else {
            $dir = __DIR__ . '/../assets/img/banner';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $name = 'banner_' . date('Ymd_His') . '.webp';
            $dest = $dir . '/' . $name;

            // Optimización: convertir/redimensionar a WEBP máx 1920px ancho
            $okSave = false;
            try {
              [$w,$h] = @getimagesize($f['tmp_name']) ?: [0,0];
              if ($w > 0 && $h > 0 && function_exists('imagecreatetruecolor') && function_exists('imagewebp')) {
                $srcImg = null;
                if (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) { $srcImg = @imagecreatefromjpeg($f['tmp_name']); }
                else if (strpos($mime, 'png') !== false) { $srcImg = @imagecreatefrompng($f['tmp_name']); if ($srcImg && function_exists('imagesavealpha')) { imagesavealpha($srcImg, true); } }
                else if (strpos($mime, 'webp') !== false) { $srcImg = @imagecreatefromwebp($f['tmp_name']); }
                if ($srcImg) {
                  $maxW = 1920;
                  $scale = $w > $maxW ? ($maxW / $w) : 1.0;
                  $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
                  $dst = imagecreatetruecolor($nw, $nh);
                  if ($dst && function_exists('imagealphablending') && function_exists('imagesavealpha')) {
                    imagealphablending($dst, false); imagesavealpha($dst, true);
                  }
                  if ($dst && @imagecopyresampled($dst, $srcImg, 0,0,0,0, $nw,$nh, $w,$h)) {
                    $okSave = @imagewebp($dst, $dest, 85);
                  }
                  if (is_resource($srcImg)) { @imagedestroy($srcImg); }
                  if (isset($dst) && is_resource($dst)) { @imagedestroy($dst); }
                }
              }
            } catch (Throwable $e) { log_error('banner optimize: ' . $e->getMessage()); }

            // Si no se pudo optimizar, guardar archivo original tal cual (como fallback)
            if (!$okSave) {
              // Fallback: mover como está, manteniendo extensión original
              $fallbackName = 'banner_' . date('Ymd_His') . '.' . $allowed[$mime];
              $fallbackDest = $dir . '/' . $fallbackName;
              if (@move_uploaded_file($f['tmp_name'], $fallbackDest)) {
                $dest = $fallbackDest; // usar la ruta movida
                $name = $fallbackName;
                $okSave = true;
              }
            }

            if (!$okSave) {
              $error = 'No se pudo guardar el archivo';
            } else {
              // Eliminar imagen anterior si existía y está dentro del directorio permitido
              if ($current) {
                $old = realpath(__DIR__ . '/../' . $current);
                $base = realpath($dir);
                if ($old && $base && strpos($old, $base) === 0 && is_file($old)) {
                  @unlink($old);
                }
              }
              // Guardar ruta relativa pública
              $publicPath = 'assets/img/banner/' . $name;
              try {
                $up = $pdo->prepare('INSERT INTO site_content (section, field, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), last_updated=CURRENT_TIMESTAMP');
                $up->execute(['home', 'banner_image', $publicPath]);
                $current = $publicPath;
                $notice = '✅ Banner actualizado con éxito';
              } catch (Throwable $e) { $error = 'Error al guardar en base de datos'; log_error('banner save db: ' . $e->getMessage()); }
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
  <title>Admin · Banner principal</title>
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
      <span>Contenido</span>
      <span>/</span>
      <span>Banner</span>
    </nav>
    <?php if ($notice): ?><div class="alert" style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b">❌ <?= e($error) ?></div><?php endif; ?>

    <div class="panel">
      <h2>Imagen actual</h2>
      <?php if ($current): ?>
        <div class="preview"><img src="../<?= e($current) ?>" alt="Banner actual" /></div>
      <?php else: ?>
        <p class="muted">No hay imagen configurada.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Subir nueva imagen</h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="upload_image" />
        <label>Archivo (JPG, PNG o WebP · máx 20MB)</label>
        <input type="file" name="banner" accept="image/*" required />
        <div class="actions"><button class="btn" type="submit">Guardar</button></div>
      </form>
    </div>

    <div class="panel">
      <h2>Frases del banner (slider)</h2>
      <form method="post" class="d-flex gap-2 align-items-end">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="add_phrase" />
        <div style="flex:1 1 480px">
          <label>Nueva frase (máx 160)</label>
          <input type="text" name="phrase" maxlength="160" class="input-sm" placeholder="Ej: Cotiza en minutos con Tu Plan Seguro" />
        </div>
        <div><button class="btn" type="submit">Agregar</button></div>
      </form>
      <?php if ($phrases): ?>
      <ul class="list" style="margin-top:10px">
        <?php foreach ($phrases as $i=>$ph): $pTxt = (string)$ph['text']; $pActive = !empty($ph['active']); ?>
        <li class="d-flex align-items-center gap-2" style="padding:6px 0;border-bottom:1px solid #eee">
          <div style="flex:1 1 auto"><span class="badge" style="background:<?= $pActive?'#dcfce7':'#fee2e2' ?>;color:<?= $pActive?'#065f46':'#991b1b' ?>;margin-right:6px;vertical-align:middle"><?= $pActive?'Activa':'Inactiva' ?></span><?= e($pTxt) ?></div>
          <form method="post" class="d-inline" style="margin-right:4px">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="toggle_phrase" />
            <input type="hidden" name="idx" value="<?= (int)$i ?>" />
            <button class="btn btn-sm" type="submit" title="<?= $pActive?'Desactivar':'Activar' ?>"><?= $pActive?'Desactivar':'Activar' ?></button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar frase?')">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
            <input type="hidden" name="action" value="delete_phrase" />
            <input type="hidden" name="idx" value="<?= (int)$i ?>" />
            <button class="btn btn-sm" type="submit" title="Eliminar">Eliminar</button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="muted">Aún no hay frases. Agrega una arriba.</p>
      <?php endif; ?>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>


