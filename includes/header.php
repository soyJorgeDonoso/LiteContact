<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Compara y cotiza Isapres online</title>
  <meta name="description" content="Compara planes de Isapres online. Nuestro sistema inteligente y asesoría Premium te ayudarán a elegir el mejor plan." />
  <?php
    try {
      if (!function_exists('getPDO')) { require_once __DIR__ . '/db.php'; }
      $pdoHead = getPDO();
      $stFav = $pdoHead->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
      $stFav->execute(['site','site_favicon']);
      $favicon = (string)($stFav->fetchColumn() ?: 'assets/img/favicon/default.ico');
    } catch (Throwable $e) { $favicon = 'assets/img/favicon/default.ico'; }
  ?>
  <link rel="icon" href="<?= htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <?php $cssFile = (file_exists(__DIR__ . '/../assets/css/style.min.css') ? 'assets/css/style.min.css' : 'assets/css/style.css'); $cssVer = @filemtime(__DIR__ . '/../' . $cssFile) ?: time(); ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$cssVer ?>" />
  <noscript><style>.reveal{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body>

  <!-- HEADER -->
  <header>
    <div class="container nav">
      <a class="brand" href="#home" aria-label="Ir al inicio">
        <?php
          try {
            if (!function_exists('getPDO')) { require_once __DIR__ . '/db.php'; }
            $pdo = getPDO();
            $st = $pdo->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
            $st->execute(['site','site_logo']);
            $logo = (string)($st->fetchColumn() ?: 'assets/img/logo/default-logo.png');
          } catch (Throwable $e) { $logo = 'assets/img/logo/default-logo.png'; }
        ?>
        <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="Tu Plan Seguro" />
      </a>

      <button class="burger" aria-label="Abrir menú" onclick="document.querySelector('.nav ul').classList.toggle('open')">☰</button>

      <ul>
        <li><a href="#home">Inicio</a></li>
        <li><a href="https://tuplanseguro.cl/blog/" target="_blank" rel="noopener">Blog</a></li>
        <li><a href="#h-steps">Cómo funciona</a></li>
        <li><a href="#ejemplos">Ejemplos</a></li>
        <li><a href="#testimonios">Testimonios</a></li>
      </ul>

      <button id="themeToggle" class="icon-btn" aria-label="Cambiar tema" title="Cambiar tema">🌙</button>

      <a class="btn" href="#cotizar">Cotizar un plan</a>
    </div>
  </header>


