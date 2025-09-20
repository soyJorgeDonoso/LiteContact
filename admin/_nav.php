<?php
// Navegación unificada para toda la administración
?>
<?php
$alertsCount = 0;
try {
  $pdoNav = getPDO();
  $thr3 = 12 * 3600; // por defecto 12h
  $vals = [];
  try {
    $st = $pdoNav->query("SELECT key_name AS k, value AS v FROM settings WHERE key_name IN ('reminder_3_value','reminder_3_unit')");
    if ($st) { while($r = $st->fetch()) { $vals[$r['k']] = (string)$r['v']; } }
    if (isset($vals['reminder_3_value'], $vals['reminder_3_unit'])) {
      $v = (int)$vals['reminder_3_value']; $u = (string)$vals['reminder_3_unit'];
      $thr3 = ($u === 'h') ? ($v*3600) : ($v*86400);
    }
  } catch (Throwable $e) { /* noop */ }
  if (!isset($vals['reminder_3_value'])) {
    try {
      $st = $pdoNav->query("SELECT k, v FROM settings WHERE k IN ('reminder_3_value','reminder_3_unit')");
      $vals = [];
      if ($st) { while($r = $st->fetch()) { $vals[$r['k']] = (string)$r['v']; } }
      if (isset($vals['reminder_3_value'], $vals['reminder_3_unit'])) {
        $v = (int)$vals['reminder_3_value']; $u = (string)$vals['reminder_3_unit'];
        $thr3 = ($u === 'h') ? ($v*3600) : ($v*86400);
      }
    } catch (Throwable $e) { /* noop */ }
  }
  // Conteo robusto: solo activos, cualquier estado, fecha válida, dentro de ventana nivel 3
  $sqlCnt = "SELECT COUNT(*) FROM (
      SELECT CASE
        WHEN s1.deadline_at IS NULL THEN NULL
        WHEN TRIM(s1.deadline_at) = '' THEN NULL
        WHEN s1.deadline_at <= '1000-01-01 00:00:00' THEN NULL
        ELSE COALESCE(
          STR_TO_DATE(s1.deadline_at, '%Y-%m-%d %H:%i:%s'),
          STR_TO_DATE(s1.deadline_at, '%Y-%m-%d %H:%i'),
          STR_TO_DATE(s1.deadline_at, '%Y-%m-%d')
        )
      END AS deadline_efectivo
      FROM contact_status s1
      WHERE s1.is_active = 1
    ) s
    WHERE s.deadline_efectivo IS NOT NULL
      AND TIMESTAMPDIFF(SECOND, NOW(), s.deadline_efectivo) BETWEEN 0 AND ?";
  $params = [(int)$thr3];
  $sc = $pdoNav->prepare($sqlCnt); $sc->execute($params); $alertsCount = (int)$sc->fetchColumn();
} catch (Throwable $e) { $alertsCount = 0; }
?>
<header class="admin-header border-bottom">
  <div class="wrap d-flex justify-content-between align-items-center py-2">
    <div class="fw-bold">Tu Plan Seguro · Admin</div>
    <nav class="admin-menu navbar navbar-expand-md navbar-light">
      <div class="container-fluid" style="padding:0">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Menú">
          <span class="navbar-toggler-icon"></span>
        </button>
          <div class="collapse navbar-collapse" id="adminNav">
          <ul class="navbar-nav me-auto mb-2 mb-md-0">
            <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
            <li class="nav-item"><a class="nav-link" href="contacts.php">Contactos</a></li>
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
          <div class="d-flex align-items-center">
            <div class="dropdown me-2">
              <a href="#" class="position-relative nav-link" data-bs-toggle="dropdown" aria-expanded="false" title="Alertas" aria-label="Alertas">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle">
                  <path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm6-6v-5a6 6 0 1 0-12 0v5l-2 2v1h16v-1l-2-2Z"/>
                </svg>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int)$alertsCount ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="contacts.php?lvl3=1">Ver alertas de próximos contactos</a></li>
              </ul>
            </div>
            <a class="nav-link" href="logout.php">Salir</a>
          </div>
        </div>
      </div>
    </nav>
  </div>
</header>

<script>
(function(){
  try{
    var d = document;
    var head = d.head || d.getElementsByTagName('head')[0];
    function hasSel(sel){ return !!d.querySelector(sel); }
    function ensureCss(href){
      if (!Array.from(d.querySelectorAll('link[rel="stylesheet"]')).some(function(l){return (l.href||'').indexOf(href)!==-1;})){
        var link = d.createElement('link'); link.rel='stylesheet'; link.href=href; head.appendChild(link);
      }
    }
    function ensureJs(src){
      if (!Array.from(d.querySelectorAll('script[src]')).some(function(s){return (s.src||'').indexOf(src)!==-1;})){
        var s = d.createElement('script'); s.src=src; s.defer=true; d.body.appendChild(s);
      }
    }
    // CSS necesarios
    ensureCss('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
    // Hojas de estilo locales (rutas relativas desde /admin)
    ensureCss('../assets/css/admin.css');
    // JS necesarios
    ensureJs('https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js');
    // Evitar duplicado de admin.js si ya fue cargado por la página
    if (!Array.from(d.querySelectorAll('script[src]')).some(function(s){return (s.src||'').indexOf('/assets/js/admin.js')!==-1 || (s.src||'').indexOf('../assets/js/admin.js')!==-1;})){
      ensureJs('../assets/js/admin.js');
    }
  }catch(e){}
})();
</script>

<script>
// Inicialización robusta de dropdowns (por si el data-api no se autoengancha)
(function initDropdowns(){
  try{
    if (window.bootstrap && typeof window.bootstrap.Dropdown === 'function'){
      document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(el){
        try { window.bootstrap.Dropdown.getOrCreateInstance(el); } catch(_){}
      });
      return;
    }
  }catch(_){ }
  setTimeout(initDropdowns, 150);
})();
</script>

<script>
// Fallback manual si Bootstrap no está disponible: toggling básico
(function(){
  try{
    if (window.bootstrap && typeof window.bootstrap.Dropdown === 'function') return; // solo si hay Dropdown real
    var toggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
    toggles.forEach(function(tg){
      tg.addEventListener('click', function(ev){
        ev.preventDefault(); ev.stopPropagation();
        var dd = tg.closest('.dropdown'); if (!dd) return;
        var menu = dd.querySelector('.dropdown-menu'); if (!menu) return;
        var isOpen = menu.classList.contains('show');
        document.querySelectorAll('.dropdown-menu.show').forEach(function(m){ m.classList.remove('show'); m.parentElement?.querySelector('[aria-expanded="true"]')?.setAttribute('aria-expanded','false'); });
        if (!isOpen){ menu.classList.add('show'); tg.setAttribute('aria-expanded','true'); }
      });
    });
    document.addEventListener('click', function(){
      document.querySelectorAll('.dropdown-menu.show').forEach(function(m){ m.classList.remove('show'); });
      document.querySelectorAll('[data-bs-toggle="dropdown"][aria-expanded="true"]').forEach(function(t){ t.setAttribute('aria-expanded','false'); });
    });
  }catch(_){ }
})();
</script>


