<?php include __DIR__ . '/includes/header.php'; ?>

  <!-- HERO -->
  <section class="hero" id="home" aria-label="Portada">
<?php
  $ok = isset($_GET['ok']);
  $mailFail = isset($_GET['mail']) && $_GET['mail'] === '0';
  // Cargar banner dinámico desde site_content
  try {
    if (!function_exists('getPDO')) { require_once __DIR__ . '/includes/db.php'; }
    $pdoHero = getPDO();
    $stHero = $pdoHero->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
    $stHero->execute(['home','banner_image']);
    $bannerImg = (string)($stHero->fetchColumn() ?: '');
    // Frases del banner
    $stPhr = $pdoHero->prepare('SELECT value FROM site_content WHERE section=? AND field=?');
    $stPhr->execute(['home','banner_phrases']);
    $phrJson = (string)($stPhr->fetchColumn() ?: '');
    $phrases = [];
    if ($phrJson !== '') { 
      $tmp = json_decode($phrJson, true);
      if (is_array($tmp)) {
        foreach ($tmp as $it) {
          if (is_array($it) && isset($it['text']) && (!isset($it['active']) || $it['active'])) { $phrases[] = (string)$it['text']; }
          else if (is_string($it)) { $phrases[] = $it; }
        }
      }
    }
    if ($bannerImg !== '') {
      $src = htmlspecialchars($bannerImg, ENT_QUOTES, 'UTF-8');
      echo '<img class="bg" src="' . $src . '" alt="Banner" />';
    }
    if ($phrases) {
      $count = count($phrases);
      $durationMs = $count * 5000;
      echo '<div class="banner-phrases" data-css="1" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;text-align:center;padding:0 16px;z-index:2">';
      $i = 0;
      foreach ($phrases as $p) {
        $txt = htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8');
        $delayMs = $i * 5000;
        $style = 'position:absolute;font-size:clamp(18px,2.6vw,28px);font-weight:700;color:#fff;text-shadow:0 2px 12px rgba(0,0,0,.35);opacity:0;animation:bannerCycle ' . $durationMs . 'ms ease-in-out infinite;animation-delay:' . $delayMs . 'ms;';
        echo '<div data-banner-phrase class="banner-phrase" style="' . $style . '">'
           . $txt
           . '<div style="margin-top:12px; pointer-events:auto"><a href="#cotizar" class="btn" role="button" aria-label="Ir a cotizar">Cotizar</a></div>'
           . '</div>';
        $i++;
      }
      echo '</div>';
    }
  } catch (Throwable $e) { /* silencioso */ }
?>
  </section>

  <!-- MARCAS -->
  <section class="brands">
    <div class="container" style="overflow:hidden;padding:22px 0 8px">
      <div class="track" role="list" aria-label="Isapres asociadas">
        <!-- Duplicado para loop infinito -->
        <div class="item" role="listitem"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Colmena%20Golden%20Cross-20250525164241.webp" alt="Colmena Golden Cross"></div>
        <div class="item" role="listitem"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Consalud-20250525164251.webp" alt="Consalud"></div>
        <div class="item" role="listitem"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Banm%C3%A9dica-20250525164302.webp" alt="Banmédica"></div>
        <div class="item" role="listitem"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Cruz%20Blanca-20250525164311.webp" alt="Cruz Blanca"></div>
        <div class="item" role="listitem"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Nueva%20Masvida-20250525164322.webp" alt="Nueva Masvida"></div>
        <div class="item" role="listitem"><img loading="lazy" src="https://storage.googleapis.com/tp-files/VidaTres-20250525164333.webp" alt="VidaTres"></div>
        <div class="item" role="listitem"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Esencial-20250525164344.webp" alt="Esencial"></div>

        <!-- repetición -->
        <div class="item"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Colmena%20Golden%20Cross-20250525164241.webp" alt=""></div>
        <div class="item"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Consalud-20250525164251.webp" alt=""></div>
        <div class="item"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Banm%C3%A9dica-20250525164302.webp" alt=""></div>
        <div class="item"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Cruz%20Blanca-20250525164311.webp" alt=""></div>
        <div class="item"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Nueva%20Masvida-20250525164322.webp" alt=""></div>
        <div class="item"><img loading="lazy" src="https://storage.googleapis.com/tp-files/VidaTres-20250525164333.webp" alt=""></div>
        <div class="item"><img loading="lazy" src="https://storage.googleapis.com/tp-files/Esencial-20250525164344.webp" alt=""></div>
      </div>
      <div class="divider"></div>
    </div>
  </section>

  <!-- PASOS -->
  <section class="steps" aria-labelledby="h-steps">
    <div class="container">
      <div class="section-head reveal">
        <h2 id="h-steps">🛠️ Así de fácil es cambiarte o cotizar tu Isapre.</h2>
        <div class="line"></div>
      </div>

      <div class="steps-grid">
        <article class="step reveal" style="transition-delay:.05s">
          <div class="chev" aria-hidden="true">›</div>
          <!-- Icono 1 (formulario) -->
          <svg class="icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M5 3a2 2 0 0 0-2 2v12.8a2 2 0 0 0 2 2h8.2a2 2 0 0 0 1.4-.6l4.8-4.8a2 2 0 0 0 .6-1.4V5a2 2 0 0 0-2-2H5zm11 12.4L14.4 17H14a1 1 0 0 1-1-1v-.4L16 13.6V15.4zM7 7h10v2H7V7zm0 4h7v2H7v-2z"/>
          </svg>
          <p>Completa el formulario con tus datos.</p>
        </article>

        <article class="step reveal" style="transition-delay:.1s">
          <div class="chev" aria-hidden="true">›</div>
          <!-- Icono 2 (contacto) -->
          <svg class="icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M2 4.5A2.5 2.5 0 0 1 4.5 2h15A2.5 2.5 0 0 1 22 4.5v15a2.5 2.5 0 0 1-2.5 2.5h-15A2.5 2.5 0 0 1 2 19.5v-15zM6 7h12v2H6V7zm0 4h8v2H6v-2zm0 4h6v2H6v-2z"/>
          </svg>
          <p>Te contactamos por WhatsApp o llamada.</p>
        </article>

        <article class="step reveal" style="transition-delay:.15s">
          <!-- Icono 3 (opciones) -->
          <svg class="icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M9 12l2 2 4-4 1.4 1.4L11 16.8 7.6 13.4 9 12zM4 5h16v2H4V5zm0 12h8v2H4v-2z"/>
          </svg>
          <p>Recibes opciones personalizadas para ti o tu familia.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- EJEMPLOS -->
  <section class="examples" id="ejemplos" aria-labelledby="h-ejemplos">
    <div class="container">
      <div class="section-head reveal">
        <span class="badge">Tu Plan Seguro</span>
        <h2 id="h-ejemplos">Algunos ejemplos</h2>
        <p class="muted" style="color:var(--muted)">Algunas cotizaciones reales hechas en nuestro sistema.</p>
        <div class="line"></div>
      </div>

      <div class="cards">
        <?php
          try {
            if (!function_exists('getPDO')) { require_once __DIR__ . '/includes/db.php'; }
            $pdoEx = getPDO();
            $rows = $pdoEx->query('SELECT image_path, title, subtitle, coverage FROM examples ORDER BY position ASC, id DESC')->fetchAll();
          } catch (Throwable $e) { $rows = []; }
          if ($rows) {
            $delay = 0.05;
            foreach ($rows as $ex) {
              $img = htmlspecialchars((string)$ex['image_path'], ENT_QUOTES, 'UTF-8');
              $title = htmlspecialchars((string)$ex['title'], ENT_QUOTES, 'UTF-8');
              $subtitle = htmlspecialchars((string)($ex['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
              $featuresHtml = nl2br(htmlspecialchars((string)($ex['coverage'] ?? ''), ENT_QUOTES, 'UTF-8'));
              echo '<article class="card reveal" style="transition-delay:' . number_format($delay,2) . 's">';
              echo '<img class="logo" loading="lazy" src="' . $img . '" alt="' . $title . '">';
              echo '<div class="meta">' . $title . '</div>';
              if ($subtitle !== '') { echo '<div class="price">' . $subtitle . '</div>'; }
              echo '<span class="pill">Coberturas</span>';
              echo '<div class="features">' . $featuresHtml . '</div>';
              echo '<div class="cta"><a href="#cotizar" class="btn">Cotizar</a></div>';
              echo '</article>';
              $delay += 0.05;
            }
          }
        ?>
      </div>
    </div>
  </section>

  <!-- TESTIMONIOS -->
  <section class="testimonials" id="testimonios" aria-labelledby="h-testimonios">
    <div class="container">
      <div class="section-head reveal">
        <h2 id="h-testimonios">Lo que dicen nuestros clientes</h2>
        <p class="muted" style="color:var(--muted)">Lenguaje real, confianza real.</p>
        <div class="line"></div>
      </div>

      <div class="t-grid">
        <article class="t-card reveal" style="transition-delay:.05s">
          <img class="t-avatar" loading="lazy" src="https://i.pravatar.cc/100?img=31" alt="Foto de Camila R." />
          <div>
            <div class="t-name">Camila R.</div>
            <div class="t-role">28 años · Independiente</div>
            <div class="stars" aria-label="5 estrellas">
              <!-- 5 estrellas -->
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
            </div>
            <p>“Cotizar fue rapidísimo. Me explicaron con peras y manzanas y quedé con un plan mejor por menos plata.”</p>
          </div>
        </article>

        <article class="t-card reveal" style="transition-delay:.1s">
          <img class="t-avatar" loading="lazy" src="https://i.pravatar.cc/100?img=32" alt="Foto de Rodrigo M." />
          <div>
            <div class="t-name">Rodrigo M.</div>
            <div class="t-role">42 años · Dependiente</div>
            <div class="stars" aria-label="5 estrellas">
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor" style="color:#e5e7eb"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
            </div>
            <p>“En un día ya tenía 3 alternativas claras. Me ahorré tiempo y dolores de cabeza.”</p>
          </div>
        </article>

        <article class="t-card reveal" style="transition-delay:.15s">
          <img class="t-avatar" loading="lazy" src="https://i.pravatar.cc/100?img=33" alt="Foto de Daniela y Tomás" />
          <div>
            <div class="t-name">Daniela & Tomás</div>
            <div class="t-role">Familia con 1 carga</div>
            <div class="stars" aria-label="5 estrellas">
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
              <svg class="star" viewBox="0 0 20 20" fill="currentColor"><path d="M10 15l-5.878 3.09 1.122-6.545L.488 6.91l6.561-.953L10 0l2.951 5.957 6.561.953-4.756 4.635 1.122 6.545z"/></svg>
            </div>
            <p>“Nos orientaron súper bien con clínicas preferentes y quedamos tranquilos con la cobertura.”</p>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- FORMULARIO -->
  <section class="form-wrap" id="cotizar" aria-labelledby="h-form">
    <div class="container">
      <?php if ($ok): ?>
        <div class="alert alert-success" role="alert" style="margin-bottom:12px">
          ✅ Gracias por registrarte, nos pondremos en contacto contigo cuanto antes.<br>
          Te hemos enviado un correo de bienvenida donde encontrarás nuestra información de contacto.
        </div>
        <?php if ($mailFail): ?>
          <div class="alert alert-warning" role="alert" style="margin-bottom:12px">
            Tu registro fue exitoso, pero no se pudo enviar el correo en este momento. Nuestro equipo se pondrá en contacto contigo.
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="form-card reveal" role="form">
        <div class="form-head">
            <h3 id="h-form">🗨️ COTIZAR ISAPRES</h3>
        </div>

        <div id="formServerAlert" class="alert d-none" role="alert" aria-live="assertive"></div>

        <form action="includes/save_contact.php" method="post" novalidate>
          <div class="grid">
            <div class="col-12">
              <label for="name">Nombre y Apellido</label>
              <input id="name" name="name" type="text" autocomplete="name" required placeholder="Ej: Camila Pérez" aria-describedby="nameFeedback" />
              <div id="nameFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="rut">Rut</label>
              <input id="rut" name="rut" type="text" required placeholder="11.111.111-1" aria-describedby="rutFeedback" />
              <div id="rutFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="age">Edad</label>
              <input id="age" name="age" type="number" min="18" max="100" required value="0" aria-describedby="ageFeedback" />
              <div id="ageFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="phone">Teléfono (solo celular)</label>
              <div class="input-inline">
                <div class="prefix">+569</div>
                <input id="phone" name="phone" type="tel" inputmode="numeric" minlength="8" maxlength="9" placeholder="98765432" required aria-describedby="phoneFeedback" />
              </div>
              <div id="phoneFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" autocomplete="email" required placeholder="tu@correo.cl" aria-describedby="emailFeedback" />
              <div id="emailFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="commune">Comuna</label>
              <select id="commune" name="commune" required aria-describedby="communeFeedback">
                <option value="">Seleccione Comuna</option>
                <?php
                  include_once __DIR__ . '/includes/comunas.php';
                  if (isset($COMUNAS) && is_array($COMUNAS)) {
                    $comunasList = array_map('strval', $COMUNAS);
                    $comunasList = array_values(array_unique($comunasList));
                    natcasesort($comunasList);
                    foreach ($comunasList as $c) {
                      $cClean = htmlspecialchars((string)$c, ENT_QUOTES, 'UTF-8');
                      echo "<option value=\"{$cClean}\">{$cClean}</option>";
                    }
                  }
                ?>
              </select>
              <div id="communeFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="income">Renta imponible aprox.</label>
              <input id="income" name="income" type="text" inputmode="numeric" maxlength="11" placeholder="Ej: 1500000" required aria-describedby="incomeFeedback" />
              <div id="incomeFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="interest">¿Qué buscas en este momento?</label>
              <select id="interest" name="interest" required aria-describedby="interestFeedback">
                <option value="0" selected>Selecciona</option>
                <option value="1">Buscando información</option>
                <option value="2">Estoy cotizando</option>
                <option value="3">Quiero cambiar ya</option>
              </select>
              <div id="interestFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-6">
              <label for="charges">Cantidad de cargas</label>
              <select id="charges" name="charges">
                <option value="0" selected>Ninguna</option>
                <option>1</option><option>2</option><option>3</option>
                <option>4</option><option>5</option><option>6</option>
              </select>
            </div>

            <div class="col-6">
              <label for="isapre">Isapre actual</label>
              <select id="isapre" name="isapre" required aria-describedby="isapreFeedback">
                <option value="">Seleccione su Isapre</option>
                <option value="0">Ninguna</option>
                <option value="4">Banmédica</option>
                <option value="1">Colmena Golden Cross</option>
                <option value="3">Consalud</option>
                <option value="5">Cruz Blanca</option>
                <option value="9">Esencial</option>
                <option value="6">Nueva Masvida</option>
                <option value="7">VidaTres</option>
                <option value="8">Fonasa</option>
              </select>
              <div id="isapreFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-12">
              <label for="comments">Comentario</label>
              <textarea id="comments" name="comments" rows="3" placeholder="Escribe aquí si tienes algo que decirnos..."></textarea>
            </div>
          </div>

          <!-- Honeypot anti-bots: si se llena, se rechaza en servidor -->
          <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
            <label for="hp_field">No completar este campo</label>
            <input type="text" id="hp_field" name="hp_field" tabindex="-1" autocomplete="off" />
          </div>

          <div class="submit">
            <button id="btn_enviar_formulario_isapre" class="btn" type="button">Cotizar&nbsp;&nbsp;✈</button>
            <small>100% gratis • Te contacta un ejecutivo certificado</small>
          </div>
        </form>
      </div>
    </div>
  </section>

  <!-- Popup Inicial -->
  <div id="popup" class="popup" aria-hidden="true">
    <div class="popup-card" role="dialog" aria-modal="true" aria-labelledby="p-title">
      <button class="popup-close" aria-label="Cerrar" onclick="closePopup()">✕</button>
      <h3 id="p-title" style="margin:4px 0 8px">Antes que te vayas…</h3>
      <p style="margin:0 0 6px">Con <b>Tu Plan Seguro</b> puedes:</p>
      <ul style="margin:0 0 10px 18px">
        <li>Ahorrar hasta cientos de miles al año</li>
        <li>Acceder a exámenes $0 y clínicas preferentes</li>
        <li>Recibir asesoría premium gratis</li>
      </ul>
      <div class="popup-actions">
        <a class="btn" href="#cotizar" onclick="closePopup()">Quiero cotizar ahora</a>
        <a class="btn btn-wa" href="https://wa.me/56973650927?text=Hola%20quiero%20cotizar%20mi%20plan%20ISAPRE" target="_blank" rel="noopener" onclick="closePopup()">Hablar por WhatsApp</a>
        <button class="btn btn-outline" onclick="closePopup()">No, gracias</button>
      </div>
    </div>
  </div>

  <!-- Botón WhatsApp flotante -->
  <a class="wa-float" href="https://wa.me/56973650927?text=Hola%20quiero%20cotizar%20mi%20plan%20ISAPRE" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp">
    <!-- ícono WA -->
    <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">
      <path d="M20 3.5A10.5 10.5 0 0 0 3.6 17.8L2 22l4.3-1.6A10.5 10.5 0 1 0 20 3.5zm-5.1 14.9c-1.9 0-3.6-.6-5-1.8l-.4-.3-3 .8.8-2.9-.3-.5a7 7 0 1 1 12.7 3.7c-1.1 2.2-3.4 3.6-5.8 3.6zm3.3-4.1c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.6.1-.7.8-.9 1c-.2.2-.3.2-.6.1a5.7 5.7 0 0 1-3.3-2.9c-.2-.4 0-.5.1-.7l.5-.6.2-.4c.1-.2 0-.3 0-.5l-.7-1.7c-.2-.4-.4-.4-.6-.4h-.5c-.1 0-.4.1-.6.3a2.4 2.4 0 0 0-.8 1.8c0 1 .7 2 1 2.4l.2.2a9.7 9.7 0 0 0 4.4 3.6c.5.2.9.3 1.3.4.4 0 .8 0 1.1-.1.3 0 1.4-.6 1.6-1.1.1-.5.1-1 0-1.1s-.2-.2-.4-.3z"/>
    </svg>
  </a>

<?php include __DIR__ . '/includes/footer.php'; ?>
