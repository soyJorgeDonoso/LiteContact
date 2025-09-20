<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$notice = '';

// Sanitizador: solo texto plano (permite emojis y saltos de línea)
function sanitize_coverage(string $text): string {
  $clean = strip_tags($text);
  $clean = str_replace(["\r\n", "\r"], "\n", $clean ?? '');
  return $clean ?? '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) { $notice = 'Token inválido'; }
  else {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create' || $action === 'update') {
      $title = trim((string)($_POST['title'] ?? ''));
      $subtitle = trim((string)($_POST['subtitle'] ?? '')) ?: null;
      $coverage = sanitize_coverage((string)($_POST['coverage'] ?? '')) ?: null;
      if ($title === '' || mb_strlen($title) > 120) { $notice = 'Título requerido (máx 120)'; }
      else {
        $imgPath = null; $uploadError = '';
        if (!empty($_FILES['image']['name'] ?? '')) {
          $f = $_FILES['image'];
          if ($f['error'] !== UPLOAD_ERR_OK) { $uploadError = 'Error al subir imagen'; }
          else {
            $tmp = $f['tmp_name'];
            $info = @getimagesize($tmp);
            if (!$info) { $uploadError = 'Archivo no es imagen válida'; }
            else {
              $ext = image_type_to_extension($info[2], false);
              if (!in_array(strtolower($ext), ['jpg','jpeg','png','webp'], true)) { $ext = 'jpg'; }
              $fileName = 'example_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
              $destDir = __DIR__ . '/../uploads';
              if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }
              $destPath = $destDir . '/' . $fileName;
              if (!@move_uploaded_file($tmp, $destPath)) { $uploadError = 'No se pudo mover archivo'; }
              else { $imgPath = 'uploads/' . $fileName; }
            }
          }
        }
        if ($uploadError) { $notice = $uploadError; }
        else {
          if ($action === 'create') {
            if (!$imgPath) { $notice = 'Imagen requerida'; }
            else {
              // Evitar duplicados: huella por contenido (título + subtítulo + coberturas)
              $uniq = hash('sha256', mb_strtolower($title . '|' . (string)$subtitle . '|' . (string)$coverage, 'UTF-8'));
              $pos = (int)$pdo->query('SELECT COALESCE(MAX(position),0)+1 FROM examples')->fetchColumn();
              $st = $pdo->prepare('INSERT INTO examples (uniq_fp, image_path, title, subtitle, coverage, position) VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), position=position');
              $st->execute([$uniq, $imgPath, $title, $subtitle, $coverage, $pos]);
              // Si fue duplicado, limpiar archivo recién subido para no dejar huérfanos
              if ((int)$st->rowCount() !== 1 && $imgPath) { @unlink(__DIR__ . '/../' . $imgPath); }
              $notice = ((int)$st->rowCount() === 1) ? 'Ejemplo creado' : 'Registro ya existía; no se duplicó';
            }
          } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($imgPath) {
              $st = $pdo->prepare('UPDATE examples SET image_path=?, title=?, subtitle=?, coverage=? WHERE id=?');
              $st->execute([$imgPath, $title, $subtitle, $coverage, $id]);
            } else {
              $st = $pdo->prepare('UPDATE examples SET title=?, subtitle=?, coverage=? WHERE id=?');
              $st->execute([$title, $subtitle, $coverage, $id]);
            }
            $notice = 'Ejemplo actualizado';
          }
        }
        // PRG: redirigir para evitar reenvío en F5
        header('Location: examples.php?notice=' . rawurlencode($notice));
        exit;
      }
    } else if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $st = $pdo->prepare('DELETE FROM examples WHERE id=?');
      $st->execute([$id]);
      $notice = 'Ejemplo eliminado';
      header('Location: examples.php?notice=' . rawurlencode($notice));
      exit;
    } else if ($action === 'reorder') {
      $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
      $pos = 1; $pdo->beginTransaction();
      try {
        $st = $pdo->prepare('UPDATE examples SET position=? WHERE id=?');
        foreach ($ids as $eid) { $st->execute([$pos++, $eid]); }
        $pdo->commit(); $notice = 'Orden actualizado';
      } catch (Throwable $e) { $pdo->rollBack(); $notice = 'Error al reordenar'; }
      header('Location: examples.php?notice=' . rawurlencode($notice));
      exit;
    }
  }
}

$rows = [];
try { $rows = $pdo->query('SELECT * FROM examples ORDER BY position ASC, id DESC')->fetchAll(); } catch (Throwable $e) { log_error('list examples: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Ejemplos</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <main class="container my-3">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <a href="index.php">Dashboard</a>
      <span>/</span>
      <span>Contenido</span>
      <span>/</span>
      <span>Ejemplos</span>
    </nav>
    <?php if (!$notice && isset($_GET['notice'])) { $notice = (string)$_GET['notice']; }
    if ($notice): ?><div class="alert alert-info"><?= e($notice) ?></div><?php endif; ?>

    <section class="panel">
      <h3>Agregar ejemplo</h3>
      <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="create" />
        <div style="min-width:240px;flex:1 1 260px">
          <label>Imagen</label>
          <input type="file" name="image" accept="image/*" required class="input-sm" />
        </div>
        <div style="min-width:280px;flex:2 1 360px">
          <label>Título</label>
          <input type="text" name="title" maxlength="120" required class="input-sm" placeholder="Ej: Cotizante de 30 años" />
        </div>
        <div style="min-width:240px;flex:2 1 360px">
          <label>Subtítulo</label>
          <input type="text" name="subtitle" maxlength="200" class="input-sm" placeholder="Ej: 3,54 UF" />
        </div>
        <div style="min-width:320px;flex:1 1 100%">
          <label>Coberturas (un ítem por línea, acepta emojis)</label>
          <textarea name="coverage" rows="8" class="input-sm" data-autoresize style="width:100%;min-height:160px;resize:vertical" placeholder="Ej: 🏥 Hospitalización al 100%\n🧪 Exámenes al 70%\n🚑 Copago fijo urgencia"></textarea>
          <small style="display:block;color:#6b7280;margin-top:4px">Usa Enter para saltos de línea. Puedes pegar emojis (😀) libremente.</small>
        </div>
        <div>
          <button class="btn btn-sm" type="submit">Agregar ejemplo</button>
        </div>
      </form>
    </section>

    <div class="table-responsive">
      <form method="post" id="reorderForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="reorder" />
        <input type="hidden" name="ids" id="order_ids" />
      </form>
      <div id="examplesAcc">
        <?php foreach ($rows as $r): $eid=(int)$r['id']; ?>
        <div class="card" data-id="<?= $eid ?>" style="margin-bottom:8px;border:1px solid #e5e7eb;border-radius:8px">
          <div class="card-header" style="display:flex;align-items:center;gap:10px;padding:10px;cursor:pointer" onclick="togglePanel('ex<?= $eid ?>')">
            <span class="drag" title="Arrastrar para reordenar" style="cursor:grab">↕</span>
            <?php if (!empty($r['image_path'])): ?><img src="../<?= e($r['image_path']) ?>" alt="" style="width:42px;height:42px;border-radius:8px;object-fit:cover" /><?php endif; ?>
            <strong style="margin-right:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:40%"><?= e((string)$r['title']) ?></strong>
            <span style="color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:35%"><?= e((string)($r['subtitle'] ?? '')) ?></span>
            <div style="margin-left:auto;display:flex;align-items:center;gap:6px">
              <form method="post" class="d-inline" onsubmit="event.stopPropagation(); return confirm('¿Eliminar \u00AB<?= e((string)$r['title']) ?>\u00BB?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= $eid ?>" />
                <button class="btn btn-sm" type="submit" title="Eliminar" onclick="event.stopPropagation();">Eliminar</button>
              </form>
            </div>
          </div>
          <div id="ex<?= $eid ?>" class="card-body" style="display:none;padding:12px 12px 14px 52px">
            <div style="margin-bottom:8px;color:#374151">Coberturas:</div>
            <div style="white-space:pre-wrap;color:#111;margin-bottom:10px"><?= $r['coverage'] ? nl2br(e((string)$r['coverage'])) : '' ?></div>
            <form method="post" enctype="multipart/form-data" class="d-block">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="update" />
              <input type="hidden" name="id" value="<?= $eid ?>" />
              <div class="d-flex flex-wrap gap-2 align-items-end">
                <input type="file" name="image" accept="image/*" class="input-sm" style="max-width:220px" />
                <input type="text" name="title" maxlength="120" value="<?= e((string)$r['title']) ?>" class="input-sm" style="max-width:240px" />
                <input type="text" name="subtitle" maxlength="200" value="<?= e((string)($r['subtitle'] ?? '')) ?>" class="input-sm" style="max-width:200px" />
                <textarea name="coverage" rows="6" class="input-sm" data-autoresize style="width:100%;min-height:120px;resize:vertical" placeholder="Ej: 🏥 Hospitalización al 100%&#10;🧪 Exámenes al 70%&#10;🚑 Copago fijo urgencia"><?php echo $r['coverage'] ? htmlspecialchars((string)$r['coverage'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                <button class="btn btn-sm" type="submit" title="Guardar cambios">Guardar</button>
              </div>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar ejemplo?')" style="margin-top:6px">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="id" value="<?= $eid ?>" />
              <button class="btn btn-sm" type="submit" title="Eliminar">Eliminar</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>

<script>
  // Drag & drop reordenando tarjetas
  const acc = document.getElementById('examplesAcc');
  let dragEl = null;
  if (acc){
    acc.querySelectorAll('.card').forEach(card=>{ card.draggable = true; });
    acc.addEventListener('dragstart', e=>{
      const card = e.target.closest('.card');
      if (!card) return; dragEl = card; card.style.opacity = '.6';
    });
    acc.addEventListener('dragend', e=>{ const card = e.target.closest('.card'); if (card) card.style.opacity = '1';});
    acc.addEventListener('dragover', e=>{
      e.preventDefault();
      const card = e.target.closest('.card'); if (!card || card===dragEl) return;
      const rect = card.getBoundingClientRect();
      const before = (e.clientY - rect.top) < rect.height/2;
      acc.insertBefore(dragEl, before ? card : card.nextSibling);
    });
    acc.addEventListener('drop', ()=>{
      const ids = Array.from(acc.querySelectorAll('.card')).map(c=>c.getAttribute('data-id'));
      const form = document.getElementById('reorderForm');
      const input = document.getElementById('order_ids');
      input.value = ids.join(',');
      const fd = new FormData(form);
      fd.delete('ids[]');
      ids.forEach(id=>fd.append('ids[]', id));
      fetch('examples.php', { method:'POST', body: fd });
    });
  }
  function togglePanel(id){
    const el = document.getElementById(id);
    if (!el) return; el.style.display = (el.style.display==='none' || !el.style.display) ? 'block' : 'none';
  }
</script>

<script>
  // Autorresizing sencillo para textareas con data-autoresize
  (function(){
    const areas = document.querySelectorAll('textarea[data-autoresize]');
    areas.forEach(a=>{
      const resize = ()=>{ a.style.height = 'auto'; a.style.height = (a.scrollHeight+4) + 'px'; };
      a.addEventListener('input', resize);
      // inicial
      resize();
    });
  })();
</script>

</body>
</html>


