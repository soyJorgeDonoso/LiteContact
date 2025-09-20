<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
require_login();

$pdo = getPDO();
$notice=''; $error='';

// Crear tabla settings simple si no existe
try { $pdo->exec('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(50) PRIMARY KEY, v VARCHAR(200) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'); } catch (Throwable $e) {}

function get_setting(PDO $pdo, string $k, string $default=''): string {
  $st = $pdo->prepare('SELECT v FROM settings WHERE k=?'); $st->execute([$k]); $v = (string)$st->fetchColumn(); return $v!==''? $v : $default;
}
function set_setting(PDO $pdo, string $k, string $v): void {
  $st = $pdo->prepare('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)'); $st->execute([$k,$v]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '')==='POST'){
  if (!csrf_check((string)($_POST['csrf'] ?? ''))) { $error='Token inválido'; }
  else {
    $r1v = (int)($_POST['reminder_1_value'] ?? 5);
    $r1u = (string)($_POST['reminder_1_unit'] ?? 'd');
    $r2v = (int)($_POST['reminder_2_value'] ?? 2);
    $r2u = (string)($_POST['reminder_2_unit'] ?? 'd');
    $r3v = (int)($_POST['reminder_3_value'] ?? 12);
    $r3u = (string)($_POST['reminder_3_unit'] ?? 'h');
    $c1 = (string)($_POST['color_1'] ?? '#fde68a');
    $c2 = (string)($_POST['color_2'] ?? '#fb923c');
    $c3 = (string)($_POST['color_3'] ?? '#ef4444');
    try{
      set_setting($pdo,'reminder_1_value',(string)$r1v);
      set_setting($pdo,'reminder_1_unit',$r1u);
      set_setting($pdo,'reminder_2_value',(string)$r2v);
      set_setting($pdo,'reminder_2_unit',$r2u);
      set_setting($pdo,'reminder_3_value',(string)$r3v);
      set_setting($pdo,'reminder_3_unit',$r3u);
      set_setting($pdo,'reminder_1_color',$c1);
      set_setting($pdo,'reminder_2_color',$c2);
      set_setting($pdo,'reminder_3_color',$c3);
      $notice='Configuración guardada';
      if (function_exists('app_log')) { app_log('REMINDERS updated'); }
    }catch(Throwable $e){ $error='No se pudo guardar'; }
  }
}

$r1v = (int)get_setting($pdo,'reminder_1_value','5');
$r1u = get_setting($pdo,'reminder_1_unit','d');
$r2v = (int)get_setting($pdo,'reminder_2_value','2');
$r2u = get_setting($pdo,'reminder_2_unit','d');
$r3v = (int)get_setting($pdo,'reminder_3_value','12');
$r3u = get_setting($pdo,'reminder_3_unit','h');
$c1 = get_setting($pdo,'reminder_1_color','#fde68a');
$c2 = get_setting($pdo,'reminder_2_color','#fb923c');
$c3 = get_setting($pdo,'reminder_3_color','#ef4444');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Configurar avisos</title>
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
      <span>Notificaciones</span>
      <span>/</span>
      <span>Avisos</span>
    </nav>
    <?php if ($notice): ?><div class="alert">✅ <?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert" style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b">❌ <?= e($error) ?></div><?php endif; ?>

    <div class="panel">
      <h2>Umbrales de aviso</h2>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
        <div class="row g-2">
          <div class="col-md-4">
            <label> Aviso 1 </label>
            <div class="d-flex gap-2">
              <input class="form-control" type="number" min="1" name="reminder_1_value" value="<?= e((string)$r1v) ?>" style="max-width:120px" />
              <select class="form-select" name="reminder_1_unit" style="max-width:120px">
                <option value="d" <?= $r1u==='d'?'selected':'' ?>>días</option>
                <option value="h" <?= $r1u==='h'?'selected':'' ?>>horas</option>
              </select>
              <input type="color" class="form-control" name="color_1" value="<?= e($c1) ?>" title="Color" style="max-width:70px" />
              <span class="badge" style="background:<?= e($c1) ?>">preview</span>
            </div>
          </div>
          <div class="col-md-4">
            <label> Aviso 2 </label>
            <div class="d-flex gap-2">
              <input class="form-control" type="number" min="1" name="reminder_2_value" value="<?= e((string)$r2v) ?>" style="max-width:120px" />
              <select class="form-select" name="reminder_2_unit" style="max-width:120px">
                <option value="d" <?= $r2u==='d'?'selected':'' ?>>días</option>
                <option value="h" <?= $r2u==='h'?'selected':'' ?>>horas</option>
              </select>
              <input type="color" class="form-control" name="color_2" value="<?= e($c2) ?>" title="Color" style="max-width:70px" />
              <span class="badge" style="background:<?= e($c2) ?>">preview</span>
            </div>
          </div>
          <div class="col-md-4">
            <label> Aviso 3 </label>
            <div class="d-flex gap-2">
              <input class="form-control" type="number" min="1" name="reminder_3_value" value="<?= e((string)$r3v) ?>" style="max-width:120px" />
              <select class="form-select" name="reminder_3_unit" style="max-width:120px">
                <option value="d" <?= $r3u==='d'?'selected':'' ?>>días</option>
                <option value="h" <?= $r3u==='h'?'selected':'' ?>>horas</option>
              </select>
              <input type="color" class="form-control" name="color_3" value="<?= e($c3) ?>" title="Color" style="max-width:70px" />
              <span class="badge" style="background:<?= e($c3) ?>">preview</span>
            </div>
          </div>
        </div>
        <div class="actions mt-3"><button class="btn">Guardar</button></div>
      </form>
    </div>
  </main>
  <script src="../assets/js/admin.js"></script>
</body>
</html>


