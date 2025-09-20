  <!-- FOOTER -->
  <footer role="contentinfo" id="contacto">
    <div class="container foot-grid">
      <div>
        <div class="foot-logo">
          <a href="#home" aria-label="Ir al inicio">
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
        </div>
        <p style="max-width:420px;color:#cbd5e1">Todos nuestros ejecutivos cuentan con código vigente en la superintendencia de salud.</p>
      </div>

      <div class="foot">
        <h5>Enlaces</h5>
        <ul>
          <li><a href="https://tuplanseguro.cl/blog/" target="_blank" rel="noopener">Blog</a></li>
          <li><a href="https://www.suseso.cl/601/w3-channel.html" target="_blank" rel="noopener">Suseso</a></li>
          <li><a href="https://www.chileatiende.gob.cl/" target="_blank" rel="noopener">ChileAtiende</a></li>
        </ul>
      </div>

      <div class="foot">
        <h5>Enlaces</h5>
        <ul>
          <li><a href="http://www.supersalud.gob.cl/portal/w3-channel.html" target="_blank" rel="noopener">Superintendencia de salud</a></li>
          <li><a href="https://www.fonasa.cl/sites/fonasa/inicio" target="_blank" rel="noopener">Fonasa</a></li>
          <li><a href="https://milicenciamedica.cl/" target="_blank" rel="noopener">Compin</a></li>
        </ul>
      </div>

      <div class="foot">
        <h5>Enlaces</h5>
        <ul>
          <li><a href="/identity/Account/Login" target="_blank" rel="noopener">Backoffice</a></li>
          <li><a href="#" target="_blank" rel="noopener">Partners</a></li>
          <li><a href="#" target="_blank" rel="noopener">Privacidad</a></li>
        </ul>
      </div>
    </div>
  </footer>

  <div class="container foot-admin" aria-hidden="false">
    <a class="admin-link" href="admin/login.php" aria-label="Acceso administración">⚙️ Acceso administración</a>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php $jsFile = (file_exists(__DIR__ . '/../assets/js/main.min.js') ? 'assets/js/main.min.js' : 'assets/js/main.js'); $jsVer = @filemtime(__DIR__ . '/../' . $jsFile) ?: time(); ?>
  <script src="<?= htmlspecialchars($jsFile, ENT_QUOTES, 'UTF-8') ?>?v=<?= (int)$jsVer ?>"></script>
</body>
</html>


