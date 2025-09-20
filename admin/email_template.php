<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$notice = '';
$error = '';

// Cargar plantilla actual desde site_content
$tpl = '';
try {
  $st = $pdo->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
  $st->execute(['email', 'welcome_mail_template']);
  $tpl = (string)($st->fetchColumn() ?: '');
} catch (Throwable $e) { log_error('email_template load current: ' . $e->getMessage()); }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
    $error = 'Token inválido';
  } else {
    $html = trim((string)($_POST['template'] ?? ''));
    if ($html === '') {
      $error = 'La plantilla no puede estar vacía';
    } else {
      try {
        $up = $pdo->prepare('INSERT INTO site_content (section, field, value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value), last_updated=CURRENT_TIMESTAMP');
        $up->execute(['email', 'welcome_mail_template', $html]);
        $tpl = $html;
        $notice = 'Plantilla guardada con éxito';
      } catch (Throwable $e) { $error = 'Error al guardar la plantilla'; log_error('email_template save db: ' . $e->getMessage()); }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Correo de bienvenida</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
  <style>
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    @media (max-width: 900px){ .grid{grid-template-columns:1fr} }
    iframe.preview{width:100%;height:400px;border:1px solid #d9deea;border-radius:8px;background:#fff}
  </style>
</head>
<body>
  <?php include __DIR__ . '/_nav.php'; ?>

  <main class="container">
    <nav class="breadcrumb" aria-label="breadcrumb">
      <a href="index.php">Dashboard</a>
      <span>/</span>
      <span>Notificaciones</span>
      <span>/</span>
      <span>Correo bienvenida</span>
    </nav>
    <?php if ($notice): ?><div class="alert">✅ <?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="background:#fee2e2;border-color:#fecaca;color:#991b1b">❌ <?= e($error) ?></div><?php endif; ?>

    <div class="grid">
      <div class="panel">
        <h2>Editar plantilla</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
          <label>Plantilla HTML (placeholders: {{full_name}}, {{email}}, {{phone}}, {{commune}})</label>
          <textarea name="template" rows="18" style="width:100%" required><?= htmlspecialchars($tpl, ENT_QUOTES, 'UTF-8') ?></textarea>
          <div class="actions"><button class="btn" type="submit">Guardar</button></div>
        </form>
      </div>
      <div class="panel">
        <h2>Vista previa</h2>
        <iframe class="preview" id="prev"></iframe>
        <small class="muted">Se reemplazan variables con datos de ejemplo.</small>
      </div>
    </div>
  </main>

  <script>
    const srcTemplate = `<?= json_encode($tpl ?: '<p>Hola {{full_name}},</p><p>Gracias por registrarte en Tu Plan Seguro.</p>', JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>`;
    const sample = {full_name:'Camila Pérez', email:'camila@example.com', phone:'+56998765432', commune:'Providencia'};
    function render(tpl,data){ return tpl.replace(/\{\{(.*?)\}\}/g,(m,k)=> sample[k.trim()] || ''); }
    function updatePreview(){
      const html = render(srcTemplate, sample);
      const iframe = document.getElementById('prev');
      const doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.open(); doc.write(html); doc.close();
    }
    updatePreview();
  </script>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</html>


