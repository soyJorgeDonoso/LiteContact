<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$notice = '';

// Manejo de acciones CRUD
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) { $notice = 'Token inválido'; }
  else {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create' || $action === 'update') {
      $phrase = trim((string)($_POST['phrase'] ?? ''));
      $name = trim((string)($_POST['name'] ?? '')) ?: null;
      $age = (isset($_POST['age']) && $_POST['age'] !== '') ? max(0, (int)$_POST['age']) : null;
      $rating = (isset($_POST['rating']) && $_POST['rating'] !== '') ? max(1, min(5, (int)$_POST['rating'])) : null;
      $charges = (isset($_POST['charges']) && $_POST['charges'] !== '') ? max(0, (int)$_POST['charges']) : null;
      $social = trim((string)($_POST['social_url'] ?? '')) ?: null;
      $isFeatured = (int)($_POST['is_featured'] ?? 0) === 1 ? 1 : 0;
      if ($phrase === '' || mb_strlen($phrase) > 200) { $notice = 'Frase requerida (máx 200)'; }
      else {
        // Subida de imagen (obligatoria en create, opcional en update)
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
              $fileName = 'testimonial_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
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
              $pos = (int)$pdo->query('SELECT COALESCE(MAX(position),0)+1 FROM testimonials')->fetchColumn();
              $st = $pdo->prepare('INSERT INTO testimonials (image_path, phrase, name, age, rating, charges, social_url, is_featured, position) VALUES (?,?,?,?,?,?,?,?,?)');
              $st->execute([$imgPath, $phrase, $name, $age, $rating, $charges, $social, $isFeatured, $pos]);
              $notice = 'Testimonio creado';
            }
          } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($imgPath) {
              $st = $pdo->prepare('UPDATE testimonials SET image_path=?, phrase=?, name=?, age=?, rating=?, charges=?, social_url=?, is_featured=? WHERE id=?');
              $st->execute([$imgPath, $phrase, $name, $age, $rating, $charges, $social, $isFeatured, $id]);
            } else {
              $st = $pdo->prepare('UPDATE testimonials SET phrase=?, name=?, age=?, rating=?, charges=?, social_url=?, is_featured=? WHERE id=?');
              $st->execute([$phrase, $name, $age, $rating, $charges, $social, $isFeatured, $id]);
            }
            $notice = 'Testimonio actualizado';
          }
        }
      }
    } else if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $st = $pdo->prepare('DELETE FROM testimonials WHERE id=?');
      $st->execute([$id]);
      $notice = 'Testimonio eliminado';
    } else if ($action === 'reorder') {
      // Reordenar: recibe ids[] en orden
      $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
      $pos = 1; $pdo->beginTransaction();
      try {
        $st = $pdo->prepare('UPDATE testimonials SET position=? WHERE id=?');
        foreach ($ids as $tid) { $st->execute([$pos++, $tid]); }
        $pdo->commit(); $notice = 'Orden actualizado';
      } catch (Throwable $e) { $pdo->rollBack(); $notice = 'Error al reordenar'; }
    }
  }
}

// Listar
$rows = [];
try { $rows = $pdo->query('SELECT * FROM testimonials ORDER BY is_featured DESC, position ASC, id DESC')->fetchAll(); } catch (Throwable $e) { log_error('list testimonials: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Testimonios</title>
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
      <span>Testimonios</span>
    </nav>
    <?php if ($notice): ?><div class="alert alert-info"><?= e($notice) ?></div><?php endif; ?>

    <section class="panel">
      <h3>Agregar testimonio</h3>
      <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="create" />
        <div style="min-width:240px;flex:1 1 260px">
          <label>Imagen (avatar)</label>
          <input type="file" name="image" accept="image/*" required class="input-sm" />
        </div>
        <div style="min-width:320px;flex:2 1 360px">
          <label>Frase (máx 200)</label>
          <input type="text" name="phrase" maxlength="200" required class="input-sm" placeholder="Ej: ‘Me ayudaron en todo el proceso’" />
        </div>
        <div>
          <label>Nombre</label>
          <input type="text" name="name" class="input-sm" placeholder="Ej: Camila R." />
        </div>
        <div style="width:120px">
          <label>Edad</label>
          <input type="number" name="age" class="input-sm" min="0" max="120" />
        </div>
        <div style="width:140px">
          <label>Cargas</label>
          <input type="number" name="charges" class="input-sm" min="0" max="10" />
        </div>
        <div style="min-width:240px;flex:1 1 260px">
          <label>Red social (URL)</label>
          <input type="url" name="social_url" class="input-sm" placeholder="https://instagram.com/usuario" />
        </div>
        <div style="width:140px">
          <label>Estrellas</label>
          <select name="rating" class="select-sm">
            <option value="">—</option>
            <option>1</option><option>2</option><option>3</option><option>4</option><option selected>5</option>
          </select>
        </div>
        <div>
          <label>&nbsp;</label>
          <label class="inline"><input type="checkbox" name="is_featured" value="1" /> Destacado</label>
        </div>
        <div>
          <button class="btn btn-sm" type="submit">Agregar testimonio</button>
        </div>
      </form>
    </section>

    <div class="table-responsive">
      <form method="post" id="reorderForm">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <input type="hidden" name="action" value="reorder" />
        <input type="hidden" name="ids" id="order_ids" />
      </form>
      <table class="table table-striped table-hover align-middle" id="tTbl">
        <thead class="table-light">
          <tr><th>#</th><th>Imagen</th><th>Nombre</th><th>Edad</th><th>Cargas</th><th>Estrellas</th><th>Frase</th><th>Red social</th><th>Destacado</th><th>Acciones</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr data-id="<?= (int)$r['id'] ?>">
            <td class="drag" title="Arrastrar para reordenar" style="cursor:grab">↕</td>
            <td><?php if ($r['image_path']): ?><img src="../<?= e($r['image_path']) ?>" alt="" style="width:42px;height:42px;border-radius:999px;object-fit:cover" /><?php endif; ?></td>
            <td><?= e((string)($r['name'] ?? '')) ?></td>
            <td><?= ($r['age']!==null ? (int)$r['age'] : '') ?></td>
            <td><?= ($r['charges']!==null ? (int)$r['charges'] : '') ?></td>
            <td><?= ($r['rating']!==null ? str_repeat('★', (int)$r['rating']) : '') ?></td>
            <td><?= e((string)$r['phrase']) ?></td>
            <td><?php if (!empty($r['social_url'])): ?><a href="<?= e((string)$r['social_url']) ?>" target="_blank" rel="noopener">Perfil</a><?php endif; ?></td>
            <td><?= ((int)$r['is_featured']===1?'Sí':'No') ?></td>
            <td>
              <form method="post" enctype="multipart/form-data" class="d-inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <input type="file" name="image" accept="image/*" class="input-sm" style="max-width:220px" />
                <input type="text" name="phrase" maxlength="200" value="<?= e((string)$r['phrase']) ?>" class="input-sm" style="max-width:240px" />
                <input type="text" name="name" value="<?= e((string)($r['name'] ?? '')) ?>" class="input-sm" placeholder="Nombre" style="max-width:160px" />
                <input type="number" name="age" value="<?= ($r['age']!==null ? (int)$r['age'] : '') ?>" class="input-sm" placeholder="Edad" style="width:100px" />
                <select name="rating" class="select-sm" style="width:110px">
                  <?php for($i=1;$i<=5;$i++): ?><option value="<?= $i ?>" <?= ((int)($r['rating']??5)===$i?'selected':'') ?>><?= $i ?></option><?php endfor; ?>
                </select>
                <input type="number" name="charges" value="<?= ($r['charges']!==null ? (int)$r['charges'] : '') ?>" class="input-sm" placeholder="Cargas" style="width:100px" />
                <input type="url" name="social_url" value="<?= e((string)($r['social_url'] ?? '')) ?>" class="input-sm" placeholder="URL red social" style="max-width:220px" />
                <label class="inline"><input type="checkbox" name="is_featured" value="1" <?= ((int)$r['is_featured']===1?'checked':'') ?> /> Destacado</label>
                <button class="btn btn-sm" type="submit" title="Guardar cambios">Guardar</button>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar testimonio?')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>" />
                <button class="btn btn-sm" type="submit" title="Eliminar">Eliminar</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

<script>
  // Drag & drop simple para reordenar
  const tbody = document.querySelector('#tTbl tbody');
  let dragEl = null;
  if (tbody){
    tbody.querySelectorAll('tr').forEach(tr=>{ tr.draggable = true; });
    tbody.addEventListener('dragstart', e=>{
      const tr = e.target.closest('tr');
      if (!tr) return; dragEl = tr; tr.style.opacity = '.6';
    });
    tbody.addEventListener('dragend', e=>{ const tr = e.target.closest('tr'); if (tr) tr.style.opacity = '1';});
    tbody.addEventListener('dragover', e=>{
      e.preventDefault();
      const tr = e.target.closest('tr'); if (!tr || tr===dragEl) return;
      const rect = tr.getBoundingClientRect();
      const before = (e.clientY - rect.top) < rect.height/2;
      tbody.insertBefore(dragEl, before ? tr : tr.nextSibling);
    });
    tbody.addEventListener('drop', ()=>{
      const ids = Array.from(tbody.querySelectorAll('tr')).map(tr=>tr.getAttribute('data-id'));
      const form = document.getElementById('reorderForm');
      const input = document.getElementById('order_ids');
      input.value = ids.join(',');
      const fd = new FormData(form);
      // limpiar ids[] previos
      fd.delete('ids[]');
      ids.forEach(id=>fd.append('ids[]', id));
      fetch('testimonials.php', { method:'POST', body: fd }).then(()=>location.reload());
    });
  }
  document.querySelectorAll('#tTbl .drag').forEach(cell=>{ if (cell.parentElement) cell.parentElement.draggable = true; });
</script>

</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</html>


