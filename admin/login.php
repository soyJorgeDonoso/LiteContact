<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

// Si ya está logueado
if (is_logged_in()) { header('Location: index.php'); exit; }

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');
  $token = (string)($_POST['csrf'] ?? '');
  if (!csrf_check($token)) {
    $error = 'Token inválido. Actualiza la página.';
  } else if ($username === '' || $password === '') {
    $error = 'Completa usuario y contraseña.';
  } else {
    try {
      $pdo = getPDO();
      $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
      $stmt->execute([$username]);
      $user = $stmt->fetch();
      if ($user && password_verify($password, (string)$user['password_hash'])) {
        // Regeneración de ID para evitar fijación de sesión
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        $_SESSION['admin_id'] = (int)$user['id'];
        $_SESSION['last_activity'] = time();
        header('Location: index.php');
        exit;
      }
      $error = 'Credenciales inválidas.';
    } catch (Throwable $e) {
      log_error('admin login failed: ' . $e->getMessage());
      $error = 'Error interno. Intenta más tarde.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin · Iniciar sesión</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <?php $adminCss = (file_exists(__DIR__ . '/../assets/css/admin.min.css') ? '../assets/css/admin.min.css' : '../assets/css/admin.css'); $adminCssVer = @filemtime(__DIR__ . '/../' . basename($adminCss)) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($adminCss, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$adminCssVer ?>" />
</head>
<body class="auth">
  <main class="auth-card">
    <h1>Tu Plan Seguro · Admin</h1>
    <?php if ($error): ?>
      <div class="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>" />
      <label>Usuario</label>
      <input type="text" name="username" autocomplete="username" required />
      <label>Contraseña</label>
      <input type="password" name="password" autocomplete="current-password" required />
      <button type="submit" class="btn">Entrar</button>
    </form>
    <p class="muted">¿Primera vez? Pídeme crear un usuario admin.</p>
  </main>
  <script src="../assets/js/admin.js"></script>
</body>
</html>


